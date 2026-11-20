<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentBrief;
use App\Entity\ContentIdea;
use App\Enum\ContentIdeaStatus;
use App\Workflow\WorkflowResult;
use Doctrine\ORM\EntityManagerInterface;

final class ContentBriefService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AiBriefGenerator $aiBriefGenerator,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function generate(ContentIdea $idea, array $input): WorkflowResult
    {
        if (in_array($idea->getStatus(), [ContentIdeaStatus::Approved, ContentIdeaStatus::HandedOff], true)) {
            return WorkflowResult::error('Approved or handed-off content must be edited through a new draft version.', 409);
        }

        if (!isset($input['mediaReference']) || !is_string($input['mediaReference'])) {
            return WorkflowResult::error('mediaReference must be a non-empty string.');
        }

        $mediaReference = trim($input['mediaReference']);
        if ($mediaReference === '') {
            return WorkflowResult::error('mediaReference is required before a brief can be reviewed or handed off.');
        }
        if (strlen($mediaReference) > 2048) {
            return WorkflowResult::error('mediaReference must not exceed 2048 characters.');
        }

        if (!isset($input['scheduledAt']) || !is_string($input['scheduledAt'])) {
            return WorkflowResult::error('scheduledAt must be a valid date-time string.');
        }

        $scheduledAtInput = trim($input['scheduledAt']);
        if ($scheduledAtInput === '') {
            return WorkflowResult::error('scheduledAt is required because the schedule is part of the approval snapshot.');
        }
        if (strlen($scheduledAtInput) > 64) {
            return WorkflowResult::error('scheduledAt must not exceed 64 characters.');
        }

        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtInput);
        } catch (\Throwable) {
            return WorkflowResult::error('scheduledAt must be a valid date-time.');
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
            return WorkflowResult::error('AI content generation is temporarily unavailable: ' . $exception->getMessage(), 503);
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
                'ai' => ['provider' => 'deterministic-backend-fallback', 'simulated' => true, 'knowledgeContextCount' => 0],
            ];
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

        return WorkflowResult::success(['ideaId' => $idea->getId(), 'versionHash' => $hash, 'brief' => $payload], 201);
    }
}
