<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentApproval;
use App\Entity\ContentBrief;
use App\Entity\ContentIdea;
use App\Enum\ContentIdeaStatus;
use App\Workflow\WorkflowResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EditContentIdeaService
{
    private const EDITABLE_FIELDS = ['title', 'topic', 'targetAudience', 'tone', 'channels'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function edit(ContentIdea $idea, array $data): WorkflowResult
    {
        if ($idea->getStatus() === ContentIdeaStatus::HandedOff) {
            return WorkflowResult::error('Handed-off content is immutable. Create a new content idea.', 409);
        }

        if ($data === []) {
            return WorkflowResult::error('At least one editable field is required.');
        }

        foreach ($data as $field => $value) {
            if (!in_array($field, self::EDITABLE_FIELDS, true)) {
                return WorkflowResult::error(sprintf('Field "%s" cannot be edited.', (string) $field));
            }

            if ($field === 'channels') {
                if (!is_array($value) || !array_is_list($value)) {
                    return WorkflowResult::error('channels must be a list of strings.');
                }

                foreach ($value as $channel) {
                    if (!is_string($channel)) {
                        return WorkflowResult::error('channels must contain only strings.');
                    }
                }

                continue;
            }

            if (!is_string($value)) {
                return WorkflowResult::error(sprintf('%s must be a string.', $field));
            }
        }

        foreach (self::EDITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            match ($field) {
                'title' => $idea->setTitle($data[$field]),
                'topic' => $idea->setTopic($data[$field]),
                'targetAudience' => $idea->setTargetAudience($data[$field]),
                'tone' => $idea->setTone($data[$field]),
                'channels' => $idea->setChannels($data[$field]),
            };
        }

        $violations = $this->validator->validate($idea);
        if (count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }

            return WorkflowResult::success([
                'error' => 'Content idea validation failed.',
                'violations' => $messages,
            ], 422);
        }

        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if ($brief) {
            $this->entityManager->remove($brief);
        }

        $approval = $this->entityManager->getRepository(ContentApproval::class)->findOneBy(['contentIdea' => $idea]);
        if ($approval) {
            $this->entityManager->remove($approval);
        }

        $idea->setStatus(ContentIdeaStatus::Draft);
        $this->entityManager->flush();

        return WorkflowResult::success([
            'ideaId' => $idea->getId(),
            'status' => 'draft',
            'message' => 'Content changed. Previous brief and approval are invalidated; regenerate the brief and request review again.',
        ]);
    }
}
