<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentApproval;
use App\Entity\ContentBrief;
use App\Entity\ContentHandoff;
use App\Entity\ContentIdea;
use App\Entity\PlanSlot;
use App\Enum\ContentIdeaStatus;
use App\Workflow\WorkflowResult;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class ContentHandoffService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function handoff(ContentIdea $idea, string $key): WorkflowResult
    {
        if (preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $key) !== 1) {
            return WorkflowResult::error('Provide a valid Idempotency-Key header using letters, numbers, dot, underscore, colon or hyphen (max 128 characters).');
        }

        $existing = $this->entityManager->getRepository(ContentHandoff::class)->findOneBy(['idempotencyKey' => $key]);
        if ($existing) {
            if ($existing->getContentIdea()->getId() !== $idea->getId()) {
                return WorkflowResult::error('This idempotency key is already used for another content idea.', 409);
            }

            return WorkflowResult::success(['handoffId' => $existing->getId(), 'idempotentReplay' => true, 'status' => 'handed_off', 'payload' => $existing->getPayload()]);
        }

        if ($idea->getStatus() !== ContentIdeaStatus::Approved) {
            return WorkflowResult::error('Only explicitly approved content can be handed off.', 409);
        }
        if (!$this->entityManager->getRepository(PlanSlot::class)->findOneBy(['contentIdea' => $idea])) {
            return WorkflowResult::error('Content must have a complete weekly-plan assignment before handoff.', 409);
        }

        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        $approval = $this->entityManager->getRepository(ContentApproval::class)->findOneBy(['contentIdea' => $idea]);
        if (!$brief || empty($brief->getPayload()['visual']['mediaReference'])) {
            return WorkflowResult::error('A media reference is required for handoff.');
        }
        if (!$approval || !hash_equals($approval->getVersionHash(), $brief->getVersionHash())) {
            return WorkflowResult::error('The current brief version has no matching approval. Review and approve this exact version first.', 409);
        }

        $payload = [
            'contentIdeaId' => $idea->getId(),
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
            $row = $connection->fetchAssociative(
                'SELECT id, content_idea_id, payload FROM content_handoffs WHERE idempotency_key = :key',
                ['key' => $key],
            );
            if (is_array($row)) {
                if ((string) $row['content_idea_id'] !== $idea->getId()) {
                    return WorkflowResult::error('This idempotency key is already used for another content idea.', 409);
                }

                $replayedPayload = is_array($row['payload'])
                    ? $row['payload']
                    : json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);

                return WorkflowResult::success(['handoffId' => (string) $row['id'], 'idempotentReplay' => true, 'status' => 'handed_off', 'payload' => $replayedPayload]);
            }

            return WorkflowResult::error('This handoff request was already processed. Retry with the same Idempotency-Key to retrieve its result.', 409);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return WorkflowResult::success(['handoffId' => $handoff->getId(), 'idempotentReplay' => false, 'status' => 'handed_off', 'payload' => $payload], 201);
    }
}
