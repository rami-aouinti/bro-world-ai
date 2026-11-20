<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AiBriefGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiBriefGeneratorTest extends TestCase
{
    public function testGenerateIdeasUsesHttpClientAndMapsTheProviderResponse(): void
    {
        $expectedIdeas = $this->fiveIdeas();
        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                'ideas' => $expectedIdeas,
                'provider' => 'test-provider',
                'simulated' => false,
                'knowledgeContextCount' => 3,
                'knowledgeSources' => [],
            ]), ['http_code' => 200]),
        ]);

        $generator = new AiBriefGenerator($client, 'https://ai.example.test', 'prod');
        $result = $generator->generateIdeas([
            'campaign' => 'Summer',
            'audience' => 'Adults',
            'tone' => 'Friendly',
            'channels' => ['instagram'],
        ]);

        self::assertSame($expectedIdeas, $result['ideas']);
        self::assertSame('test-provider', $result['ai']['provider']);
        self::assertFalse($result['ai']['simulated']);
        self::assertSame(3, $result['ai']['knowledgeContextCount']);
    }

    public function testGenerateIdeasRetriesOneTransientGatewayFailure(): void
    {
        $client = new MockHttpClient([
            new MockResponse('temporarily unavailable', ['http_code' => 503]),
            new MockResponse((string) json_encode([
                'ideas' => $this->fiveIdeas(),
                'provider' => 'test-provider',
                'simulated' => false,
            ]), ['http_code' => 200]),
        ]);

        $generator = new AiBriefGenerator($client, 'https://ai.example.test', 'prod');
        $result = $generator->generateIdeas([
            'campaign' => 'Summer',
            'audience' => 'Adults',
            'tone' => 'Friendly',
            'channels' => ['instagram'],
        ]);

        self::assertCount(5, $result['ideas']);
    }

    public function testGenerateIdeasDoesNotRetryClientErrors(): void
    {
        $client = new MockHttpClient([
            new MockResponse('invalid request', ['http_code' => 400]),
        ]);

        $generator = new AiBriefGenerator($client, 'https://ai.example.test', 'prod');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI idea generation failed (HTTP 400).');

        $generator->generateIdeas([
            'campaign' => 'Summer',
            'audience' => 'Adults',
            'tone' => 'Friendly',
            'channels' => ['instagram'],
        ]);
    }

    /** @return list<array{title: string, topic: string, targetAudience: string, tone: string, channels: list<string>}> */
    private function fiveIdeas(): array
    {
        return [
            ['title' => 'Idea 1', 'topic' => 'Topic 1', 'targetAudience' => 'Adults', 'tone' => 'Friendly', 'channels' => ['instagram']],
            ['title' => 'Idea 2', 'topic' => 'Topic 2', 'targetAudience' => 'Adults', 'tone' => 'Friendly', 'channels' => ['instagram']],
            ['title' => 'Idea 3', 'topic' => 'Topic 3', 'targetAudience' => 'Adults', 'tone' => 'Friendly', 'channels' => ['instagram']],
            ['title' => 'Idea 4', 'topic' => 'Topic 4', 'targetAudience' => 'Adults', 'tone' => 'Friendly', 'channels' => ['instagram']],
            ['title' => 'Idea 5', 'topic' => 'Topic 5', 'targetAudience' => 'Adults', 'tone' => 'Friendly', 'channels' => ['instagram']],
        ];
    }
}
