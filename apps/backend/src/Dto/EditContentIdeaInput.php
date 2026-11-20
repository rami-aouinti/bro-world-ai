<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class EditContentIdeaInput
{
    private const EDITABLE_FIELDS = ['title', 'topic', 'targetAudience', 'tone', 'channels'];

    /** @param array<string, mixed> $changes */
    public function __construct(
        #[Assert\Callback('validateChanges')]
        public readonly array $changes,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->changes;
    }

    public function validateChanges(ExecutionContextInterface $context): void
    {
        if ($this->changes === []) {
            $context->buildViolation('At least one editable field is required.')
                ->addViolation();

            return;
        }

        foreach ($this->changes as $field => $value) {
            if (!in_array($field, self::EDITABLE_FIELDS, true)) {
                $context->buildViolation(sprintf('Field "%s" cannot be edited.', (string) $field))
                    ->atPath((string) $field)
                    ->addViolation();
                continue;
            }

            if ($field === 'channels') {
                if (!is_array($value) || !array_is_list($value)) {
                    $context->buildViolation('channels must be a list of strings.')
                        ->atPath('channels')
                        ->addViolation();
                    continue;
                }

                foreach ($value as $channel) {
                    if (!is_string($channel)) {
                        $context->buildViolation('channels must contain only strings.')
                            ->atPath('channels')
                            ->addViolation();
                        break;
                    }
                }

                continue;
            }

            if (!is_string($value)) {
                $context->buildViolation(sprintf('%s must be a string.', $field))
                    ->atPath($field)
                    ->addViolation();
            }
        }
    }
}
