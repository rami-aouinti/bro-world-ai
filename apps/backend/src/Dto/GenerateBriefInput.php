<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class GenerateBriefInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 2048)]
        public readonly mixed $mediaReference,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 64)]
        public readonly mixed $scheduledAt,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload['mediaReference'] ?? null, $payload['scheduledAt'] ?? null);
    }

    /** @return array{mediaReference: string, scheduledAt: string} */
    public function toArray(): array
    {
        return ['mediaReference' => trim($this->mediaReference), 'scheduledAt' => trim($this->scheduledAt)];
    }
}
