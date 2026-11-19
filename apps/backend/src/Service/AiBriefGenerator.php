<?php

declare(strict_types=1);

namespace App\Service;

final class AiBriefGenerator
{
    public function __construct(
        private readonly string $serviceUrl,
        private readonly string $appEnv,
    ) {
    }

    /**
     * Returns null in tests or when no AI endpoint is configured, allowing a deterministic fallback.
     *
     * @param array{ id: string, title: string, topic: string, audience: string, tone: string, channels: list<string> } $idea
     */
    public function generate(array $idea, \DateTimeImmutable $scheduledAt, string $mediaReference): ?array
    {
        if ($this->appEnv === 'test' || trim($this->serviceUrl) === '') {
            return null;
        }

        $body = json_encode([
            'ideaId' => $idea['id'],
            'title' => $idea['title'],
            'topic' => $idea['topic'],
            'audience' => $idea['audience'],
            'tone' => $idea['tone'],
            'channels' => $idea['channels'],
            'scheduledAt' => $scheduledAt->format(DATE_ATOM),
            'mediaReference' => $mediaReference,
            'bookingLink' => 'https://example.invalid/appointment',
            'useKnowledgeBase' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 120,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents(rtrim($this->serviceUrl, '/') . '/api/v1/brief', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($response === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
            throw new \RuntimeException('AI service request failed: ' . ($statusLine !== '' ? $statusLine : 'connection unavailable'));
        }

        try {
            $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('AI service returned invalid JSON.', previous: $exception);
        }

        foreach (['hook', 'masterCopy', 'productionPlan', 'visual', 'channelVariants', 'effortMinutes'] as $required) {
            if (!isset($payload[$required]) || !is_array($payload[$required]) && !is_string($payload[$required])) {
                throw new \RuntimeException(sprintf('AI service response is missing "%s".', $required));
            }
        }
        if (($payload['visual']['mediaReference'] ?? null) !== $mediaReference) {
            throw new \RuntimeException('AI service response did not preserve the supplied media reference.');
        }
        foreach ($idea['channels'] as $channel) {
            if (!isset($payload['channelVariants'][$channel])) {
                throw new \RuntimeException(sprintf('AI service response is missing the "%s" channel variant.', $channel));
            }
        }

        return $payload;
    }
}
