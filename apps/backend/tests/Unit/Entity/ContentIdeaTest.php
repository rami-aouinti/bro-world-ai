<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\ContentIdea;
use App\Enum\ContentIdeaStatus;
use PHPUnit\Framework\TestCase;

final class ContentIdeaTest extends TestCase
{
    public function testNewIdeaStartsAsDraftWithGeneratedUuid(): void
    {
        $idea = new ContentIdea();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $idea->getId(),
        );
        self::assertSame(ContentIdeaStatus::Draft, $idea->getStatus());
        $idea->setStatus(ContentIdeaStatus::Review);
        self::assertSame(ContentIdeaStatus::Review, $idea->getStatus());
        self::assertSame([], $idea->getChannels());
        self::assertInstanceOf(\DateTimeImmutable::class, $idea->getCreatedAt());
    }

    public function testSettersTrimTextAndNormalizeChannels(): void
    {
        $idea = (new ContentIdea())
            ->setTitle('  Summer campaign  ')
            ->setTopic('  Eye care  ')
            ->setTargetAudience('  Young adults  ')
            ->setTone('  Friendly  ')
            ->setChannels(['instagram', 'facebook', 'instagram']);

        self::assertSame('Summer campaign', $idea->getTitle());
        self::assertSame('Eye care', $idea->getTopic());
        self::assertSame('Young adults', $idea->getTargetAudience());
        self::assertSame('Friendly', $idea->getTone());
        self::assertSame(['instagram', 'facebook'], $idea->getChannels());
    }
}
