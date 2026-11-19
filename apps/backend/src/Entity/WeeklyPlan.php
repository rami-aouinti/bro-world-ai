<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'weekly_plans')]
#[ORM\UniqueConstraint(name: 'uniq_weekly_plan_start', fields: ['weekStart'])]
class WeeklyPlan
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', length: 36)]
    private string $id;

    #[ORM\Column(name: 'week_start', type: 'date_immutable')]
    private \DateTimeImmutable $weekStart;

    #[ORM\Column(name: 'capacity_person_a')]
    private int $capacityPersonA = 90;

    #[ORM\Column(name: 'capacity_person_b')]
    private int $capacityPersonB = 150;

    #[ORM\Column(length: 20)]
    private string $status = 'planned';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(\DateTimeImmutable $weekStart, int $capacityPersonA = 90, int $capacityPersonB = 150)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->weekStart = $weekStart->setTime(0, 0);
        $this->capacityPersonA = $capacityPersonA;
        $this->capacityPersonB = $capacityPersonB;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getWeekStart(): \DateTimeImmutable { return $this->weekStart; }
    public function getCapacityPersonA(): int { return $this->capacityPersonA; }
    public function getCapacityPersonB(): int { return $this->capacityPersonB; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
