<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentApproval;
use App\Entity\ContentBrief;
use App\Entity\ContentIdea;
use App\Enum\ContentIdeaStatus;
use App\Workflow\WorkflowResult;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coordinates the review and approval lifecycle of a content idea.
 *
 * The service owns workflow rules; HTTP concerns remain in WorkflowController.
 */
final class ContentReviewService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function submitForReview(ContentIdea $idea): WorkflowResult
    {
        if (!$this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea])) {
            return WorkflowResult::error('A complete brief with a media reference and schedule is required before review.');
        }

        if (!$this->entityManager->getRepository(\App\Entity\PlanSlot::class)->findOneBy(['contentIdea' => $idea])) {
            return WorkflowResult::error('Content must be assigned to a weekly plan before review.');
        }

        if (!in_array($idea->getStatus(), [ContentIdeaStatus::Draft, ContentIdeaStatus::ReadyForPlanning], true)) {
            return WorkflowResult::error('Only draft or planned content can enter review.', 409);
        }

        $idea->setStatus(ContentIdeaStatus::Review);
        $this->entityManager->flush();

        return WorkflowResult::success([
            'ideaId' => $idea->getId(),
            'status' => $idea->getStatus()->value,
        ]);
    }

    public function approve(ContentIdea $idea): WorkflowResult
    {
        if ($idea->getStatus() !== ContentIdeaStatus::Review) {
            return WorkflowResult::error('Only content in review can be approved.', 409);
        }

        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if (!$brief) {
            return WorkflowResult::error('The reviewed brief no longer exists.', 409);
        }

        $approval = $this->entityManager->getRepository(ContentApproval::class)->findOneBy(['contentIdea' => $idea]);
        if ($approval) {
            $this->entityManager->remove($approval);
        }

        $approval = new ContentApproval($idea, $brief->getVersionHash());
        $this->entityManager->persist($approval);
        $idea->setStatus(ContentIdeaStatus::Approved);
        $this->entityManager->flush();

        return WorkflowResult::success([
            'ideaId' => $idea->getId(),
            'status' => $idea->getStatus()->value,
            'approvedVersionHash' => $approval->getVersionHash(),
            'approvedAt' => $approval->getApprovedAt()->format(DATE_ATOM),
        ]);
    }
}
