<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'plan_slots', indexes: [new ORM\Index(name: 'idx_plan_slots_plan', columns: ['weekly_plan_id'])], uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_plan_slot_idea', columns: ['content_idea_id'])])]
class PlanSlot
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: WeeklyPlan::class)]
    #[ORM\JoinColumn(name: 'weekly_plan_id', nullable: false, onDelete: 'CASCADE')]
    private WeeklyPlan $weeklyPlan;

    #[ORM\ManyToOne(targetEntity: ContentIdea::class)]
    #[ORM\JoinColumn(name: 'content_idea_id', nullable: false, onDelete: 'CASCADE')]
    private ContentIdea $contentIdea;

    #[ORM\Column(name: 'slot_type', length: 20)]
    private string $slotType;

    #[ORM\Column(length: 1)]
    private string $assignee;

    #[ORM\Column(name: 'estimated_minutes')]
    private int $estimatedMinutes;

    public function __construct(WeeklyPlan $weeklyPlan, ContentIdea $contentIdea, string $slotType, string $assignee, int $estimatedMinutes)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->weeklyPlan = $weeklyPlan;
        $this->contentIdea = $contentIdea;
        $this->slotType = $slotType;
        $this->assignee = $assignee;
        $this->estimatedMinutes = $estimatedMinutes;
    }

    public function getId(): string { return $this->id; }
    public function getWeeklyPlan(): WeeklyPlan { return $this->weeklyPlan; }
    public function getContentIdea(): ContentIdea { return $this->contentIdea; }
    public function getSlotType(): string { return $this->slotType; }
    public function getAssignee(): string { return $this->assignee; }
    public function getEstimatedMinutes(): int { return $this->estimatedMinutes; }
}
