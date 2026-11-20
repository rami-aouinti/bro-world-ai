<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentApproval;
use App\Entity\ContentBrief;
use App\Entity\ContentHandoff;
use App\Entity\ContentIdea;
use App\Entity\PlanSlot;
use App\Entity\WeeklyPlan;
use App\Enum\ContentIdeaStatus;
use App\Service\AiBriefGenerator;
use App\Service\WeeklyPlanService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/workflow')]
final class WorkflowController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly AiBriefGenerator $aiBriefGenerator,
        private readonly WeeklyPlanService $weeklyPlanService,
    ) {
    }

    #[Route('/ai/ideas', name: 'workflow_generate_ai_ideas', methods: ['POST'])]
    public function generateAiIdeas(Request $request): JsonResponse
    {
        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $campaign = trim((string) ($data['campaign'] ?? ''));
        $audience = trim((string) ($data['audience'] ?? ''));
        $tone = trim((string) ($data['tone'] ?? 'friendly and helpful'));
        $channels = $data['channels'] ?? ['instagram', 'facebook'];
        $allowedChannels = ['instagram', 'facebook', 'tiktok', 'linkedin'];
        if ($campaign === '' || $audience === '' || !is_array($channels) || $channels === []) {
            return $this->error('campaign, audience and at least one channel are required.');
        }
        foreach ($channels as $channel) {
            if (!is_string($channel) || !in_array($channel, $allowedChannels, true)) {
                return $this->error('channels must contain only instagram, facebook, tiktok or linkedin.');
            }
        }

        try {
            $result = $this->aiBriefGenerator->generateIdeas([
                'campaign' => $campaign,
                'audience' => $audience,
                'tone' => $tone,
                'channels' => array_values(array_unique($channels)),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->error('AI idea generation is temporarily unavailable: ' . $exception->getMessage(), 503);
        }

        return new JsonResponse($result);
    }

    #[Route('/weekly-plans', name: 'workflow_create_weekly_plan', methods: ['POST'])]
    #[Route('/weekly-plans', name: 'workflow_create_weekly_plan', methods: ['POST'])]
    public function createWeeklyPlan(Request $request): JsonResponse
    {
        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $result = $this->weeklyPlanService->create($data);

        return new JsonResponse($result->body, $result->statusCode);
    }

    #[Route('/weekly-plans/{id}', name: 'workflow_get_weekly_plan', methods: ['GET'])]
    public function getWeeklyPlan(string $id): JsonResponse
    {
        $result = $this->weeklyPlanService->get($id);

        return new JsonResponse($result->body, $result->statusCode);
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
        $scheduledAtInput = trim((string) ($data['scheduledAt'] ?? ''));
        if ($scheduledAtInput === '') {
            return $this->error('scheduledAt is required because the schedule is part of the approval snapshot.');
        }
        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtInput);
        } catch (\Throwable) {
            return $this->error('scheduledAt must be a valid date-time.');
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

        try {
            $payload = $this->aiBriefGenerator->generate([
                'id' => $idea->getId(),
                'title' => $idea->getTitle(),
                'topic' => $idea->getTopic(),
                'audience' => $idea->getTargetAudience(),
                'tone' => $idea->getTone(),
                'channels' => $channels,
            ], $scheduledAt, $mediaReference);
        } catch (\RuntimeException $exception) {
            return $this->error('AI content generation is temporarily unavailable: ' . $exception->getMessage(), 503);
        }

        if ($payload === null) {
            $payload = [
                'idea' => ['id' => $idea->getId(), 'title' => $idea->getTitle(), 'topic' => $idea->getTopic(), 'audience' => $idea->getTargetAudience(), 'tone' => $idea->getTone()],
                'channels' => $channels,
                'scheduledAt' => $scheduledAt->format(DATE_ATOM),
                'hook' => 'Du suchst eine Brille, die zu deinem Alltag passt?',
                'masterCopy' => $idea->getTopic() . '. Entdecke unsere Auswahl und lass dich persönlich beraten.',
                'productionPlan' => ['format' => 'Product photo or 15-second vertical video', 'steps' => ['Prepare product and location', 'Capture photo/video', 'Review accessibility and brand tone', 'Export channel variants']],
                'visual' => ['overlay' => 'Finde deinen Look', 'titleImage' => 'Hero product image with clean background', 'mediaReference' => $mediaReference],
                'bookingLink' => 'https://example.invalid/appointment',
                'channelVariants' => $variants,
                'effortMinutes' => ['research' => 15, 'copyAndVariants' => 25, 'production' => 35, 'coordinationAndApproval' => 15, 'total' => 90],
                'assumptions' => ['Synthetic example copy; human review required.', 'Booking URL is a placeholder and must be replaced before real use.', 'No media file is uploaded or published by this prototype.'],
            ];
            $payload['ai'] = ['provider' => 'deterministic-backend-fallback', 'simulated' => true, 'knowledgeContextCount' => 0];
        }

        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $canonical);

        $existing = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if ($existing) {
            $existing->replacePayload($payload, $hash);
        } else {
            $this->entityManager->persist(new ContentBrief($idea, $payload, $hash));
        }
        $idea->setStatus(ContentIdeaStatus::Draft);
        $this->entityManager->flush();

        return new JsonResponse(['ideaId' => $id, 'versionHash' => $hash, 'brief' => $payload], 201);
    }

    #[Route('/content-ideas/{id}/brief', name: 'workflow_get_brief', methods: ['GET'])]
    public function getBrief(string $id): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if (!$brief) { return $this->error('Content brief not found.', 404); }

        return new JsonResponse(['ideaId' => $id, 'versionHash' => $brief->getVersionHash(), 'createdAt' => $brief->getCreatedAt()->format(DATE_ATOM), 'brief' => $brief->getPayload()]);
    }

    #[Route('/content-ideas/{id}/review', name: 'workflow_submit_review', methods: ['POST'])]
    public function submitReview(string $id): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        if (!$this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea])) {
            return $this->error('A complete brief with a media reference and schedule is required before review.');
        }
        if (!$this->entityManager->getRepository(PlanSlot::class)->findOneBy(['contentIdea' => $idea])) {
            return $this->error('Content must be assigned to a weekly plan before review.');
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
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if (!$brief) {
            return $this->error('The reviewed brief no longer exists.', 409);
        }
        $approval = $this->entityManager->getRepository(ContentApproval::class)->findOneBy(['contentIdea' => $idea]);
        if ($approval) {
            $this->entityManager->remove($approval);
        }
        $approval = new ContentApproval($idea, $brief->getVersionHash());
        $this->entityManager->persist($approval);
        $idea->setStatus(ContentIdeaStatus::Approved);
        $this->entityManager->flush();

        return new JsonResponse(['ideaId' => $id, 'status' => $idea->getStatus()->value, 'approvedVersionHash' => $approval->getVersionHash(), 'approvedAt' => $approval->getApprovedAt()->format(DATE_ATOM)]);
    }

    #[Route('/content-ideas/{id}/handoff', name: 'workflow_handoff', methods: ['POST'])]
    public function handoff(string $id, Request $request): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        $key = trim((string) $request->headers->get('Idempotency-Key'));
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
        if (!$this->entityManager->getRepository(PlanSlot::class)->findOneBy(['contentIdea' => $idea])) {
            return $this->error('Content must have a complete weekly-plan assignment before handoff.', 409);
        }
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        $approval = $this->entityManager->getRepository(ContentApproval::class)->findOneBy(['contentIdea' => $idea]);
        if (!$brief || empty($brief->getPayload()['visual']['mediaReference'])) {
            return $this->error('A media reference is required for handoff.');
        }
        if (!$approval || !hash_equals($approval->getVersionHash(), $brief->getVersionHash())) {
            return $this->error('The current brief version has no matching approval. Review and approve this exact version first.', 409);
        }

        $payload = [
            'contentIdeaId' => $id,
            'versionHash' => $brief->getVersionHash(),
            'channels' => $idea->getChannels(),
            'scheduledAt' => $brief->getPayload()['scheduledAt'],
            'mode' => 'simulated',
            'brief' => $brief->getPayload(),
        ];
        $handoff = new ContentHandoff($idea, $key, $brief->getVersionHash(), $payload);
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->entityManager->persist($handoff);
            $idea->setStatus(ContentIdeaStatus::HandedOff);
            $this->entityManager->flush();
            $connection->commit();
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();

            // A concurrent request may have committed this key after our initial
            // lookup. Read via DBAL because the failed ORM flush may have affected
            // the UnitOfWork state for this request.
            $row = $connection->fetchAssociative(
                'SELECT id, content_idea_id, payload FROM content_handoffs WHERE idempotency_key = :key',
                ['key' => $key],
            );
            if (is_array($row)) {
                if ((string) $row['content_idea_id'] !== $id) {
                    return $this->error('This idempotency key is already used for another content idea.', 409);
                }

                $replayedPayload = is_array($row['payload'])
                    ? $row['payload']
                    : json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);

                return new JsonResponse([
                    'handoffId' => (string) $row['id'],
                    'idempotentReplay' => true,
                    'status' => 'handed_off',
                    'payload' => $replayedPayload,
                ]);
            }

            return $this->error('This handoff request was already processed. Retry with the same Idempotency-Key to retrieve its result.', 409);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

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
        if (array_key_exists('channels', $data) && !is_array($data['channels'])) {
            return $this->error('channels must be an array.');
        }
        foreach (['title', 'topic', 'targetAudience', 'tone', 'channels'] as $field) {
            if (!array_key_exists($field, $data)) { continue; }
            match ($field) {
                'title' => $idea->setTitle((string) $data[$field]),
                'topic' => $idea->setTopic((string) $data[$field]),
                'targetAudience' => $idea->setTargetAudience((string) $data[$field]),
                'tone' => $idea->setTone((string) $data[$field]),
                'channels' => $idea->setChannels($data[$field]),
            };
        }
        $violations = $this->validator->validate($idea);
        if (count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }

            return new JsonResponse(['error' => 'Content idea validation failed.', 'violations' => $messages], 422);
        }
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if ($brief) { $this->entityManager->remove($brief); }
        $approval = $this->entityManager->getRepository(ContentApproval::class)->findOneBy(['contentIdea' => $idea]);
        if ($approval) { $this->entityManager->remove($approval); }
        $idea->setStatus(ContentIdeaStatus::Draft);
        $this->entityManager->flush();

        return new JsonResponse(['ideaId' => $id, 'status' => 'draft', 'message' => 'Content changed. Previous brief and approval are invalidated; regenerate the brief and request review again.']);
    }

    private function findIdea(string $id): ContentIdea|JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->error('Content idea not found.', 404);
        }

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
