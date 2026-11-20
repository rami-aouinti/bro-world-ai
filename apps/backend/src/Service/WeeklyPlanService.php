<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentIdea;
use App\Entity\PlanSlot;
use App\Entity\WeeklyPlan;
use App\Enum\ContentIdeaStatus;
use App\Workflow\WorkflowResult;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class WeeklyPlanService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

        public function create(array $data): WorkflowResult
    {
        $weekStartInput = $data['weekStart'] ?? null;
        if (!is_string($weekStartInput) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartInput)) {
            return WorkflowResult::error('weekStart is required and must use YYYY-MM-DD.');
        }
        $weekStart = \DateTimeImmutable::createFromFormat('!Y-m-d', $weekStartInput);
        $dateErrors = \DateTimeImmutable::getLastErrors();
        if (!$weekStart || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $weekStart->format('Y-m-d') !== $weekStartInput) {
            return WorkflowResult::error('weekStart must be a real calendar date in YYYY-MM-DD format.');
        }
        if ($weekStart->format('N') !== '1') {
            return WorkflowResult::error('weekStart must be a Monday.');
        }

        $slots = $data['slots'] ?? null;
        if (!is_array($slots) || !array_is_list($slots) || count($slots) !== 5) {
            return WorkflowResult::error('A weekly plan must contain exactly five slots: three priority and two reserve.');
        }

        $priority = count(array_filter($slots, static fn ($slot) => is_array($slot) && ($slot['type'] ?? null) === 'priority'));
        $reserve = count(array_filter($slots, static fn ($slot) => is_array($slot) && ($slot['type'] ?? null) === 'reserve'));
        if ($priority !== 3 || $reserve !== 2) {
            return WorkflowResult::error('A weekly plan requires exactly three priority slots and two reserve slots.');
        }

        $capacityA = filter_var($data['capacityPersonA'] ?? 90, FILTER_VALIDATE_INT);
        $capacityB = filter_var($data['capacityPersonB'] ?? 150, FILTER_VALIDATE_INT);
        if ($capacityA === false || $capacityB === false || $capacityA < 0 || $capacityB < 0) {
            return WorkflowResult::error('Capacities must be non-negative integers.');
        }

        $existing = $this->entityManager->getRepository(WeeklyPlan::class)->findOneBy(['weekStart' => $weekStart->setTime(0, 0)]);
        if ($existing) {
            // A retry for the same week is a safe replay: return the persisted plan
            // instead of failing with 409 or creating duplicate slots.
            return $this->buildPlanResult($existing, true);
        }

        $ideaIds = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                return WorkflowResult::error('Each slot must be an object.');
            }
            if (!is_string($slot['ideaId'] ?? null) || trim($slot['ideaId']) === '') {
                return WorkflowResult::error('Each slot must contain a valid string ideaId.');
            }
            $ideaId = trim($slot['ideaId']);
            if ($ideaId === '') {
                return WorkflowResult::error('Each slot must contain a valid ideaId.');
            }
            $ideaIds[] = $ideaId;
        }

        // Load all referenced ideas in one query instead of one query per slot.
        $ideasById = [];
        foreach ($this->entityManager->getRepository(ContentIdea::class)->findBy(['id' => array_values(array_unique($ideaIds))]) as $idea) {
            $ideasById[$idea->getId()] = $idea;
        }

        $used = ['A' => 0, 'B' => 0];
        $seenIdeas = [];
        $resolved = [];
        foreach ($slots as $slot) {
            $ideaId = trim($slot['ideaId']);
            $type = $slot['type'] ?? null;
            $assignee = $slot['assignee'] ?? null;
            $rawMinutes = $slot['estimatedMinutes'] ?? null;
            $minutes = is_int($rawMinutes) ? $rawMinutes : (is_string($rawMinutes) ? filter_var($rawMinutes, FILTER_VALIDATE_INT) : false);
            if (!is_string($type) || !in_array($type, ['priority', 'reserve'], true)
                || !is_string($assignee) || !in_array($assignee, ['A', 'B'], true)
                || $minutes === false || $minutes < 1) {
                return WorkflowResult::error('Each slot needs type priority|reserve, assignee A|B and positive estimatedMinutes.');
            }
            if (isset($seenIdeas[$ideaId])) {
                return WorkflowResult::error('A content idea can only appear once in a weekly plan.');
            }
            $idea = $ideasById[$ideaId] ?? null;
            if (!$idea) {
                return WorkflowResult::error(sprintf('Content idea "%s" was not found.', $ideaId), 404);
            }
            if ($idea->getStatus() === ContentIdeaStatus::HandedOff) {
                return WorkflowResult::error('Handed-off content cannot be planned again.');
            }
            $seenIdeas[$ideaId] = true;
            $used[$assignee] += $minutes;
            $resolved[] = [$idea, $type, $assignee, $minutes];
        }

        $overCapacity = [];
        if ($used['A'] > $capacityA) {
            $overCapacity['A'] = ['plannedMinutes' => $used['A'], 'capacityMinutes' => $capacityA, 'overByMinutes' => $used['A'] - $capacityA];
        }
        if ($used['B'] > $capacityB) {
            $overCapacity['B'] = ['plannedMinutes' => $used['B'], 'capacityMinutes' => $capacityB, 'overByMinutes' => $used['B'] - $capacityB];
        }
        if ($overCapacity !== []) {
            return new WorkflowResult(['error' => 'Capacity exceeded. Re-plan or increase capacity explicitly.', 'capacityWarnings' => $overCapacity], 422);
        }

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $plan = new WeeklyPlan($weekStart, $capacityA, $capacityB);
            $this->entityManager->persist($plan);
            foreach ($resolved as [$idea, $type, $assignee, $minutes]) {
                $this->entityManager->persist(new PlanSlot($plan, $idea, $type, $assignee, $minutes));
                if ($idea->getStatus() === ContentIdeaStatus::Draft) {
                    $idea->setStatus(ContentIdeaStatus::ReadyForPlanning);
                }
            }
            $this->entityManager->flush();
            $connection->commit();
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();

            return WorkflowResult::error('A weekly plan already exists for this weekStart.', 409);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return WorkflowResult::success([
            'id' => $plan->getId(),
            'weekStart' => $plan->getWeekStart()->format('Y-m-d'),
            'status' => $plan->getStatus(),
            'capacity' => ['A' => $capacityA, 'B' => $capacityB],
            'allocatedMinutes' => $used,
            'slots' => array_map(static fn ($item) => [
                'ideaId' => $item[0]->getId(),
                'title' => $item[0]->getTitle(),
                'type' => $item[1],
                'assignee' => $item[2],
                'estimatedMinutes' => $item[3],
            ], $resolved),
        ], 201);
    }

        public function get(string $id): WorkflowResult
    {
        if (!Uuid::isValid($id)) {
            return WorkflowResult::error('Weekly plan not found.', 404);
        }

        $plan = $this->entityManager->getRepository(WeeklyPlan::class)->find($id);
        if (!$plan) {
            return WorkflowResult::error('Weekly plan not found.', 404);
        }

        return $this->buildPlanResult($plan);
    }

    private function buildPlanResult(WeeklyPlan $plan, bool $idempotentReplay = false): WorkflowResult
    {
        // Fetch-join ideas to avoid lazy-loading one relation for every slot.
        $slots = $this->entityManager->createQueryBuilder()
            ->select('slot', 'idea')
            ->from(PlanSlot::class, 'slot')
            ->innerJoin('slot.contentIdea', 'idea')
            ->where('slot.weeklyPlan = :plan')
            ->setParameter('plan', $plan)
            ->getQuery()
            ->getResult();

        $allocated = ['A' => 0, 'B' => 0];
        $items = [];
        foreach ($slots as $slot) {
            $allocated[$slot->getAssignee()] += $slot->getEstimatedMinutes();
            $items[] = [
                'ideaId' => $slot->getContentIdea()->getId(),
                'title' => $slot->getContentIdea()->getTitle(),
                'type' => $slot->getSlotType(),
                'assignee' => $slot->getAssignee(),
                'estimatedMinutes' => $slot->getEstimatedMinutes(),
            ];
        }

        $body = [
            'id' => $plan->getId(),
            'weekStart' => $plan->getWeekStart()->format('Y-m-d'),
            'status' => $plan->getStatus(),
            'capacity' => ['A' => $plan->getCapacityPersonA(), 'B' => $plan->getCapacityPersonB()],
            'allocatedMinutes' => $allocated,
            'remainingMinutes' => [
                'A' => $plan->getCapacityPersonA() - $allocated['A'],
                'B' => $plan->getCapacityPersonB() - $allocated['B'],
            ],
            'slots' => $items,
        ];

        if ($idempotentReplay) {
            $body['idempotentReplay'] = true;
        }

        return WorkflowResult::success($body);
    }
}
