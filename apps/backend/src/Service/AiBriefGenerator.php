<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiBriefGenerator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
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

        $payload = $this->postJson('/api/v1/brief', [
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
        ], 'AI service request failed');

        if (!is_string($payload['hook'] ?? null) || !is_string($payload['masterCopy'] ?? null)) {
            throw new \RuntimeException('AI service response must contain a hook and master copy.');
        }

        foreach (['productionPlan', 'visual', 'channelVariants', 'effortMinutes'] as $required) {
            if (!is_array($payload[$required] ?? null)) {
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

    /**
     * @param array{campaign: string, audience: string, tone: string, channels: list<string>} $input
     * @return array{ideas: list<array<string, mixed>>, ai: array<string, mixed>, knowledgeSources: mixed}
     */
    public function generateIdeas(array $input): array
    {
        if ($this->appEnv === 'test' || trim($this->serviceUrl) === '') {
            $suggestions = [
                ['title' => 'Protect your eyes this summer', 'topic' => 'Practical summer eyewear and sun protection tips'],
                ['title' => 'Blue light: myth or reality?', 'topic' => 'A balanced explainer about screens and everyday eye comfort'],
                ['title' => 'When was your last eye check?', 'topic' => 'A friendly reminder to schedule a professional eye check'],
                ['title' => 'Discover the frame trends', 'topic' => 'Style inspiration for choosing frames that fit your personality'],
                ['title' => 'How to choose the right glasses', 'topic' => 'A helpful guide to choosing eyewear for daily routines'],
            ];
            $ideas = array_map(static fn (array $item): array => $item + [
                'targetAudience' => $input['audience'],
                'tone' => $input['tone'],
                'channels' => $input['channels'],
            ], $suggestions);

            return [
                'ideas' => $ideas,
                'ai' => ['provider' => 'deterministic-backend-fallback', 'simulated' => true, 'knowledgeContextCount' => 0],
                'knowledgeSources' => [],
            ];
        }

        $result = $this->postJson('/api/v1/ideas', [
            'campaign' => $input['campaign'],
            'audience' => $input['audience'],
            'tone' => $input['tone'],
            'channels' => $input['channels'],
            'count' => 5,
            'useKnowledgeBase' => true,
        ], 'AI idea generation failed');

        if (!isset($result['ideas']) || !is_array($result['ideas']) || count($result['ideas']) !== 5) {
            throw new \RuntimeException('AI service must return exactly five ideas.');
        }

        foreach ($result['ideas'] as $idea) {
            if (!is_array($idea)
                || empty($idea['title'])
                || empty($idea['topic'])
                || empty($idea['targetAudience'])
                || empty($idea['tone'])
                || empty($idea['channels'])) {
                throw new \RuntimeException('AI service returned an incomplete idea.');
            }
        }

        return [
            'ideas' => $result['ideas'],
            'ai' => [
                'provider' => $result['provider'] ?? 'unknown',
                'simulated' => (bool) ($result['simulated'] ?? false),
                'knowledgeContextCount' => (int) ($result['knowledgeContextCount'] ?? 0),
            ],
            'knowledgeSources' => $result['knowledgeSources'] ?? [],
        ];
    }

    /**
     * Sends a JSON request to the AI service and retries transient failures once.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(string $path, array $payload, string $failureMessage): array
    {
        $url = rtrim($this->serviceUrl, '/').$path;
        $lastTransportError = null;

        for ($attempt = 1; $attempt <= 2; ++$attempt) {
            try {
                $response = $this->httpClient->request('POST', $url, [
                    'json' => $payload,
                    'headers' => ['Accept' => 'application/json'],
                    'timeout' => 300,
                    'max_duration' => 310,
                    'max_redirects' => 0,
                ]);

                $statusCode = $response->getStatusCode();
                if ($statusCode >= 200 && $statusCode < 300) {
                    try {
                        $decoded = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $exception) {
                        throw new \RuntimeException('AI service returned invalid JSON.', previous: $exception);
                    }

                    if (!is_array($decoded)) {
                        throw new \RuntimeException('AI service response must be a JSON object.');
                    }

                    return $decoded;
                }

                $retryable = in_array($statusCode, [502, 503, 504], true);
                if ($retryable && $attempt === 1) {
                    usleep(250_000);
                    continue;
                }

                throw new \RuntimeException(sprintf('%s (HTTP %d).', $failureMessage, $statusCode));
            } catch (TransportExceptionInterface $exception) {
                $lastTransportError = $exception;
                if ($attempt === 1) {
                    usleep(250_000);
                    continue;
                }
            }
        }

        throw new \RuntimeException($failureMessage.': AI service is unreachable.', previous: $lastTransportError);
    }
}
