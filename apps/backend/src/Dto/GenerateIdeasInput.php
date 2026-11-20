<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class GenerateIdeasInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly mixed $campaign,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255)]
        public readonly mixed $audience,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 80)]
        public readonly mixed $tone,
        #[Assert\Type('array')]
        #[Assert\Count(min: 1, max: 4)]
        #[Assert\All([
            new Assert\Type('string'),
            new Assert\Choice(choices: ['instagram', 'facebook', 'tiktok', 'linkedin']),
        ])]
        public readonly mixed $channels,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['campaign'] ?? null,
            $payload['audience'] ?? null,
            $payload['tone'] ?? 'friendly and helpful',
            $payload['channels'] ?? ['instagram', 'facebook'],
        );
    }

    /** @return array{campaign: string, audience: string, tone: string, channels: list<string>} */
    public function toArray(): array
    {
        return [
            'campaign' => trim($this->campaign),
            'audience' => trim($this->audience),
            'tone' => trim($this->tone),
            'channels' => array_values(array_unique($this->channels)),
        ];
    }

    #[Assert\Callback]
    public function validateChannelList(ExecutionContextInterface $context): void
    {
        if (is_array($this->channels) && !array_is_list($this->channels)) {
            $context->buildViolation('channels must be a list.')
                ->atPath('channels')
                ->addViolation();
        }
    }
}
