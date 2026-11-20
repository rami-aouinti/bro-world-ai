<?php

declare(strict_types=1);

namespace App\Workflow;

final readonly class WorkflowResult
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public array $body,
        public int $statusCode = 200,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function success(array $body, int $statusCode = 200): self
    {
        return new self($body, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400): self
    {
        return new self(['error' => $message], $statusCode);
    }
}
