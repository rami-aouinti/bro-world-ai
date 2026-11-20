<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateWeeklyPlanInput
{
    public function __construct(
        #[Assert\Type('string')]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}$/', message: 'weekStart must use YYYY-MM-DD.')]
        public readonly mixed $weekStart,
        #[Assert\Type('array')]
        #[Assert\Count(exactly: 5)]
        #[Assert\All([new Assert\Type('array')])]
        public readonly mixed $slots,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload['weekStart'] ?? null, $payload['slots'] ?? null);
    }

    /** @return array{weekStart: string, slots: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['weekStart' => $this->weekStart, 'slots' => $this->slots];
    }
}
