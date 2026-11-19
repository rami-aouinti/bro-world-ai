<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentBrief;
use App\Entity\ContentHandoff;
use App\Entity\ContentIdea;
use App\Entity\PlanSlot;
use App\Entity\WeeklyPlan;
use App\Enum\ContentIdeaStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/workflow')]
final class WorkflowController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/weekly-plans', name: 'workflow_create_weekly_plan', methods: ['POST'])]
    public function createWeeklyPlan(Request $request): JsonResponse
    {
        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $weekStartInput = (string) ($data['weekStart'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartInput)) {
            return $this->error('weekStart is required and must use YYYY-MM-DD.');
        }
        try {
            $weekStart = new \DateTimeImmutable($weekStartInput);
        } catch (\Throwable) {
            return $this->error('weekStart must be a valid date (YYYY-MM-DD).');
        }

        $slots = $data['slots'] ?? null;
        if (!is_array($slots) || count($slots) !== 5) {
            return $this->error('A weekly plan must contain exactly five slots: three priority and two reserve.');
        }

        $priority = count(array_filter($slots, static fn ($slot) => is_array($slot) && ($slot['type'] ?? null) === 'priority'));
        $reserve = count(array_filter($slots, static fn ($slot) => is_array($slot) && ($slot['type'] ?? null) === 'reserve'));
        if ($priority !== 3 || $reserve !== 2) {
            return $this->error('A weekly plan requires exactly three priority slots and two reserve slots.');
        }

        $capacityA = filter_var($data['capacityPersonA'] ?? 90, FILTER_VALIDATE_INT);
        $capacityB = filter_var($data['capacityPersonB'] ?? 150, FILTER_VALIDATE_INT);
        if ($capacityA === false || $capacityB === false || $capacityA < 0 || $capacityB < 0) {
            return $this->error('Capacities must be non-negative integers.');
        }

        $existing = $this->entityManager->getRepository(WeeklyPlan::class)->findOneBy(['weekStart' => $weekStart->setTime(0, 0)]);
        if ($existing) {
            return $this->error('A weekly plan already exists for this weekStart.', 409);
        }

        $used = ['A' => 0, 'B' => 0];
        $seenIdeas = [];
        $resolved = [];
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                return $this->error('Each slot must be an object.');
            }
            $ideaId = (string) ($slot['ideaId'] ?? '');
            $type = (string) ($slot['type'] ?? '');
            $assignee = (string) ($slot['assignee'] ?? '');
            $minutes = filter_var($slot['estimatedMinutes'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($type, ['priority', 'reserve'], true) || !in_array($assignee, ['A', 'B'], true) || $minutes === false || $minutes < 1) {
                return $this->error('Each slot needs type priority|reserve, assignee A|B and positive estimatedMinutes.');
            }
            if (isset($seenIdeas[$ideaId])) {
                return $this->error('A content idea can only appear once in a weekly plan.');
            }
            $idea = $this->entityManager->getRepository(ContentIdea::class)->find($ideaId);
            if (!$idea) {
                return $this->error(sprintf('Content idea "%s" was not found.', $ideaId), 404);
            }
            if ($idea->getStatus() === ContentIdeaStatus::HandedOff) {
                return $this->error('Handed-off content cannot be planned again.');
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
            return new JsonResponse(['error' => 'Capacity exceeded. Re-plan or increase capacity explicitly.', 'capacityWarnings' => $overCapacity], 422);
        }

        $plan = new WeeklyPlan($weekStart, $capacityA, $capacityB);
        $this->entityManager->persist($plan);
        foreach ($resolved as [$idea, $type, $assignee, $minutes]) {
            $this->entityManager->persist(new PlanSlot($plan, $idea, $type, $assignee, $minutes));
            if ($idea->getStatus() === ContentIdeaStatus::Draft) {
                $idea->setStatus(ContentIdeaStatus::ReadyForPlanning);
            }
        }
        $this->entityManager->flush();

        return new JsonResponse([
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

    #[Route('/content-ideas/{id}/brief', name: 'workflow_generate_brief', methods: ['POST'])]
    public function generateBrief(string $id, Request $request): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) {
            return $idea;
        }
        if (in_array($idea->getStatus(), [ContentIdeaStatus::Approved, ContentIdeaStatus::HandedOff], true)) {
            return $this->error('Approved or handed-off content must be edited through a new draft version.', 409);
        }

        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $mediaReference = trim((string) ($data['mediaReference'] ?? ''));
        if ($mediaReference === '') {
            return $this->error('mediaReference is required before a brief can be reviewed or handed off.');
        }

        $channels = $idea->getChannels();
        $variants = [];
        foreach ($channels as $channel) {
            $variants[$channel] = match ($channel) {
                'instagram' => ['copy' => $idea->getTopic() . ' — entdecke unsere Auswahl. Speichere den Beitrag für deinen nächsten Besuch.', 'format' => 'feed post + story', 'cta' => 'Termin vereinbaren'],
                'facebook' => ['copy' => $idea->getTopic() . '. Wir beraten dich persönlich und finden gemeinsam das passende Modell.', 'format' => 'feed post', 'cta' => 'Mehr erfahren'],
                'tiktok' => ['copy' => '3 Dinge, die du bei ' . $idea->getTopic() . ' beachten solltest.', 'format' => 'short video', 'cta' => 'Folge uns für mehr Tipps'],
                'linkedin' => ['copy' => 'Ein kurzer Einblick: ' . $idea->getTopic() . '. Qualität und persönliche Beratung stehen im Mittelpunkt.', 'format' => 'professional post', 'cta' => 'Kontakt aufnehmen'],
                default => ['copy' => $idea->getTopic(), 'format' => 'post', 'cta' => 'Mehr erfahren'],
            };
        }

        $payload = [
            'idea' => ['id' => $idea->getId(), 'title' => $idea->getTitle(), 'topic' => $idea->getTopic(), 'audience' => $idea->getTargetAudience(), 'tone' => $idea->getTone()],
            'hook' => 'Du suchst eine Brille, die zu deinem Alltag passt?',
            'masterCopy' => $idea->getTopic() . '. Entdecke unsere Auswahl und lass dich persönlich beraten.',
            'productionPlan' => ['format' => 'Product photo or 15-second vertical video', 'steps' => ['Prepare product and location', 'Capture photo/video', 'Review accessibility and brand tone', 'Export channel variants']],
            'visual' => ['overlay' => 'Finde deinen Look', 'titleImage' => 'Hero product image with clean background', 'mediaReference' => $mediaReference],
            'bookingLink' => 'https://example.invalid/appointment',
            'channelVariants' => $variants,
            'effortMinutes' => ['research' => 15, 'copyAndVariants' => 25, 'production' => 35, 'coordinationAndApproval' => 15, 'total' => 90],
            'assumptions' => ['Synthetic example copy; human review required.', 'Booking URL is a placeholder and must be replaced before real use.', 'No media file is uploaded or published by this prototype.'],
        ];
        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $canonical);

        $existing = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if ($existing) {
            $this->entityManager->remove($existing);
        }
        $this->entityManager->persist(new ContentBrief($idea, $payload, $hash));
        $idea->setStatus(ContentIdeaStatus::Draft);
        $this->entityManager->flush();

        return new JsonResponse(['ideaId' => $id, 'versionHash' => $hash, 'brief' => $payload], 201);
    }

    #[Route('/content-ideas/{id}/review', name: 'workflow_submit_review', methods: ['POST'])]
    public function submitReview(string $id): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        if (!$this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea])) {
            return $this->error('A complete brief with a media reference is required before review.');
        }
        if (!in_array($idea->getStatus(), [ContentIdeaStatus::Draft, ContentIdeaStatus::ReadyForPlanning], true)) {
            return $this->error('Only draft or planned content can enter review.', 409);
        }
        $idea->setStatus(ContentIdeaStatus::Review);
        $this->entityManager->flush();

        return new JsonResponse(['ideaId' => $id, 'status' => $idea->getStatus()->value]);
    }

    #[Route('/content-ideas/{id}/approve', name: 'workflow_approve', methods: ['POST'])]
    public function approve(string $id): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        if ($idea->getStatus() !== ContentIdeaStatus::Review) {
            return $this->error('Only content in review can be approved.', 409);
        }
        $idea->setStatus(ContentIdeaStatus::Approved);
        $this->entityManager->flush();
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);

        return new JsonResponse(['ideaId' => $id, 'status' => $idea->getStatus()->value, 'approvedVersionHash' => $brief->getVersionHash()]);
    }

    #[Route('/content-ideas/{id}/handoff', name: 'workflow_handoff', methods: ['POST'])]
    public function handoff(string $id, Request $request): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        $data = $this->body($request);
        if ($data instanceof JsonResponse) { return $data; }
        $key = trim((string) ($request->headers->get('Idempotency-Key') ?: ($data['idempotencyKey'] ?? '')));
        if ($key === '' || strlen($key) > 128) {
            return $this->error('Provide an Idempotency-Key header (max 128 characters).');
        }

        $existing = $this->entityManager->getRepository(ContentHandoff::class)->findOneBy(['idempotencyKey' => $key]);
        if ($existing) {
            if ($existing->getContentIdea()->getId() !== $id) {
                return $this->error('This idempotency key is already used for another content idea.', 409);
            }
            return new JsonResponse(['handoffId' => $existing->getId(), 'idempotentReplay' => true, 'status' => 'handed_off', 'payload' => $existing->getPayload()]);
        }
        if ($idea->getStatus() !== ContentIdeaStatus::Approved) {
            return $this->error('Only explicitly approved content can be handed off.', 409);
        }
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if (!$brief || empty($brief->getPayload()['visual']['mediaReference'])) {
            return $this->error('A media reference is required for handoff.');
        }

        $payload = [
            'contentIdeaId' => $id,
            'versionHash' => $brief->getVersionHash(),
            'channels' => $idea->getChannels(),
            'scheduledAt' => $data['scheduledAt'] ?? null,
            'mode' => 'simulated',
            'brief' => $brief->getPayload(),
        ];
        $handoff = new ContentHandoff($idea, $key, $brief->getVersionHash(), $payload);
        $this->entityManager->persist($handoff);
        $idea->setStatus(ContentIdeaStatus::HandedOff);
        $this->entityManager->flush();

        return new JsonResponse(['handoffId' => $handoff->getId(), 'idempotentReplay' => false, 'status' => 'handed_off', 'payload' => $payload], 201);
    }

    #[Route('/content-ideas/{id}', name: 'workflow_edit_idea', methods: ['PATCH'])]
    public function editIdea(string $id, Request $request): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        if ($idea->getStatus() === ContentIdeaStatus::HandedOff) {
            return $this->error('Handed-off content is immutable. Create a new content idea.', 409);
        }
        $data = $this->body($request);
        if ($data instanceof JsonResponse) { return $data; }
        foreach (['title', 'topic', 'targetAudience', 'tone', 'channels'] as $field) {
            if (!array_key_exists($field, $data)) { continue; }
            match ($field) {
                'title' => $idea->setTitle((string) $data[$field]),
                'topic' => $idea->setTopic((string) $data[$field]),
                'targetAudience' => $idea->setTargetAudience((string) $data[$field]),
                'tone' => $idea->setTone((string) $data[$field]),
                'channels' => is_array($data[$field]) ? $idea->setChannels($data[$field]) : throw new \\InvalidArgumentException('channels must be an array.'),
            };
        }
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if ($brief) { $this->entityManager->remove($brief); }
        $idea->setStatus(ContentIdeaStatus::Draft);
        $this->entityManager->flush();

        return new JsonResponse(['ideaId' => $id, 'status' => 'draft', 'message' => 'Content changed. Previous brief and approval are invalidated; regenerate the brief and request review again.']);
    }

    private function findIdea(string $id): ContentIdea|JsonResponse
    {
        $idea = $this->entityManager->getRepository(ContentIdea::class)->find($id);

        return $idea ?? $this->error('Content idea not found.', 404);
    }

    private function body(Request $request): array|JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            return $this->error('Request body must be valid JSON.');
        }

        return $data;
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
