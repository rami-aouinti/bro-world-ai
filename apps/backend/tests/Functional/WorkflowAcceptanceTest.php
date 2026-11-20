<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Doctrine\DBAL\Connection;

final class WorkflowAcceptanceTest extends WebTestCase
{
    private AbstractBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement(
            'TRUNCATE TABLE content_handoffs, content_approvals, content_briefs, plan_slots, weekly_plans, content_ideas RESTART IDENTITY CASCADE'
        );
    }

    public function testResponsesExposeAndPreserveARequestId(): void
    {
        $this->client->request('GET', '/api/workflow/weekly-plans/not-a-real-id', [], [], [
            'HTTP_X_REQUEST_ID' => 'acceptance-trace-2026-01',
        ]);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertSame('acceptance-trace-2026-01', $this->client->getResponse()->headers->get('X-Request-Id'));
    }

    public function testMissingContentIdeaReturnsNotFound(): void
    {
        $this->client->request('POST', '/api/workflow/content-ideas/00000000-0000-4000-8000-000000000000/review');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
        self::assertSame('Content idea not found.', $this->responseData()['error']);
    }

    public function testAiIdeaSuggestionsReturnFiveDraftsWithoutPersistingThem(): void
    {
        $this->postJson('/api/workflow/ai/ideas', [
            'campaign' => 'Synthetic summer eyewear campaign',
            'audience' => 'Adults aged 25 to 40',
            'tone' => 'Friendly and informative',
            'channels' => ['instagram', 'facebook'],
        ]);

        $response = $this->responseData();
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(5, $response['ideas']);
        self::assertTrue($response['ai']['simulated']);
        self::assertSame('0', $this->countRows('content_ideas'));
    }

    public function testAt01CreatesAWeeklyPlanWithFiveDistinctIdeas(): void
    {
        $ideas = $this->createIdeas(5);
        $response = $this->createPlan($ideas);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        self::assertCount(5, $response['slots']);
        self::assertSame(3, count(array_filter($response['slots'], static fn (array $slot): bool => $slot['type'] === 'priority')));
        self::assertSame(2, count(array_filter($response['slots'], static fn (array $slot): bool => $slot['type'] === 'reserve')));
        self::assertSame('1', $this->countRows('weekly_plans'));
        self::assertSame('5', $this->countRows('plan_slots'));
    }

    public function testAt01bWeeklyPlanCreationRetryReturnsTheExistingPlan(): void
    {
        $firstIdeas = $this->createIdeas(5);
        $first = $this->createPlan($firstIdeas);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        // A retried workflow may already have generated another batch of ideas.
        // The weekly plan endpoint must replay the existing plan without duplicating it.
        $secondIdeas = $this->createIdeas(5);
        $this->postJson('/api/workflow/weekly-plans', [
            'weekStart' => '2027-01-04',
            'slots' => $this->buildSlots($secondIdeas),
        ]);

        $replay = $this->responseData();
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertTrue($replay['idempotentReplay']);
        self::assertSame($first['id'], $replay['id']);
        self::assertSame($first['slots'], $replay['slots']);
        self::assertSame('1', $this->countRows('weekly_plans'));
        self::assertSame('5', $this->countRows('plan_slots'));
    }

    public function testAt02RejectsAnInvalidPriorityReserveDistribution(): void
    {
        $ideas = $this->createIdeas(5);
        $slots = $this->buildSlots($ideas);
        $slots[3]['type'] = 'priority';

        $this->postJson('/api/workflow/weekly-plans', [
            'weekStart' => '2027-01-04',
            'slots' => $slots,
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('weekly_plans'));
        self::assertSame('0', $this->countRows('plan_slots'));
    }

    public function testAt03RejectsAssignmentsThatExceedPersonCapacity(): void
    {
        $ideas = $this->createIdeas(5);
        $slots = $this->buildSlots($ideas);
        $slots[0]['assignee'] = 'A';
        $slots[0]['estimatedMinutes'] = 31;
        $slots[1]['assignee'] = 'A';
        $slots[1]['estimatedMinutes'] = 30;
        $slots[2]['assignee'] = 'A';
        $slots[2]['estimatedMinutes'] = 30;
        $slots[4]['assignee'] = 'B';

        $this->postJson('/api/workflow/weekly-plans', [
            'weekStart' => '2027-01-04',
            'slots' => $slots,
        ]);

        $body = $this->responseData();
        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame(91, $body['capacityWarnings']['A']['plannedMinutes']);
        self::assertSame(90, $body['capacityWarnings']['A']['capacityMinutes']);
        self::assertSame(1, $body['capacityWarnings']['A']['overByMinutes']);
        self::assertSame('0', $this->countRows('weekly_plans'));
    }

    public function testAt04RejectsBriefWithoutMediaReference(): void
    {
        $ideaId = $this->createIdeas(1)[0];
        $this->postJson('/api/workflow/content-ideas/'.$ideaId.'/brief', [
            'scheduledAt' => '2027-01-05T10:00:00+01:00',
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('content_briefs'));
    }

    public function testAt04bRejectsNonStringBriefInputs(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->postJson('/api/workflow/content-ideas/'.$ideaId.'/brief', [
            'mediaReference' => ['not' => 'a string'],
            'scheduledAt' => '2027-01-05T10:00:00+01:00',
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('content_briefs'));
    }

    public function testAt04cRejectsNonStringScheduledAt(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->postJson('/api/workflow/content-ideas/'.$ideaId.'/brief', [
            'mediaReference' => 'synthetic://acceptance-test/product-image',
            'scheduledAt' => ['date' => '2027-01-05'],
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('content_briefs'));
    }

    public function testAt05RejectsReviewWithoutBriefAndPlanAssignment(): void
    {
        $ideaId = $this->createIdeas(1)[0];
        $this->client->request('POST', '/api/workflow/content-ideas/'.$ideaId.'/review');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('draft', $this->ideaStatus($ideaId));
    }

    public function testAt06RejectsApprovalWhenIdeaIsNotInReview(): void
    {
        $ideaId = $this->createIdeas(1)[0];
        $this->client->request('POST', '/api/workflow/content-ideas/'.$ideaId.'/approve');

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        self::assertSame('draft', $this->ideaStatus($ideaId));
    }

    public function testAt07RejectsHandoffWithoutApproval(): void
    {
        $ideaId = $this->createIdeas(1)[0];
        $this->client->request(
            'POST',
            '/api/workflow/content-ideas/'.$ideaId.'/handoff',
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => 'at07-no-approval'],
        );

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('content_handoffs'));
    }

    public function testAt08HandoffRetryWithSameIdempotencyKeyReturnsSameHandoff(): void
    {
        $ideas = $this->createIdeas(5);
        $ideaId = $ideas[0];
        $this->createPlan($ideas);
        $this->prepareForApproval($ideaId);

        $this->client->request(
            'POST',
            '/api/workflow/content-ideas/'.$ideaId.'/handoff',
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => 'at08-exactly-once'],
        );
        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $first = $this->responseData();

        $this->client->request(
            'POST',
            '/api/workflow/content-ideas/'.$ideaId.'/handoff',
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => 'at08-exactly-once'],
        );
        $second = $this->responseData();

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertFalse($first['idempotentReplay']);
        self::assertTrue($second['idempotentReplay']);
        self::assertSame($first['handoffId'], $second['handoffId']);
        self::assertSame('1', $this->countRows('content_handoffs'));
    }

    public function testAt09RejectsReusingAnIdempotencyKeyForAnotherIdea(): void
    {
        $ideas = $this->createIdeas(5);
        $this->createPlan($ideas);
        $this->prepareForApproval($ideas[0]);

        $this->client->request(
            'POST',
            '/api/workflow/content-ideas/'.$ideas[0].'/handoff',
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => 'at09-reused-key'],
        );
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        $this->client->request(
            'POST',
            '/api/workflow/content-ideas/'.$ideas[1].'/handoff',
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => 'at09-reused-key'],
        );

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        self::assertSame('1', $this->countRows('content_handoffs'));
    }

    public function testAt10EditingApprovedContentInvalidatesBriefAndApproval(): void
    {
        $ideas = $this->createIdeas(5);
        $ideaId = $ideas[0];
        $this->createPlan($ideas);
        $this->prepareForApproval($ideaId);

        $this->patchJson('/api/workflow/content-ideas/'.$ideaId, ['topic' => 'Updated topic']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('draft', $this->ideaStatus($ideaId));
        self::assertSame('0', $this->countRows('content_briefs'));
        self::assertSame('0', $this->countRows('content_approvals'));
    }

    public function testAt11HandedOffContentCannotBeEdited(): void
    {
        $ideas = $this->createIdeas(5);
        $ideaId = $ideas[0];
        $this->createPlan($ideas);
        $this->prepareForApproval($ideaId);

        $this->client->request(
            'POST',
            '/api/workflow/content-ideas/'.$ideaId.'/handoff',
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => 'at11-immutable'],
        );
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        $this->patchJson('/api/workflow/content-ideas/'.$ideaId, ['topic' => 'Attempted change']);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        self::assertSame('handed_off', $this->ideaStatus($ideaId));
        self::assertSame('1', $this->countRows('content_handoffs'));
    }

    public function testAt12RejectsNonStringIdeaFieldsWithoutChangingStoredContent(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->patchJson('/api/workflow/content-ideas/'.$ideaId, ['topic' => ['unexpected' => 'array']]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('Eyewear topic 1', $this->storedIdeaTopic($ideaId));
        self::assertSame('draft', $this->ideaStatus($ideaId));
    }

    public function testAt13RejectsMalformedChannelListsBeforeChangingContent(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->patchJson('/api/workflow/content-ideas/'.$ideaId, ['channels' => ['instagram', 42]]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('draft', $this->ideaStatus($ideaId));
        self::assertSame('2', $this->countIdeaChannels($ideaId));
    }

    public function testAt14RejectsUnknownIdeaFields(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->patchJson('/api/workflow/content-ideas/'.$ideaId, ['status' => 'approved']);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('draft', $this->ideaStatus($ideaId));
    }

    public function testAt21RejectsTopLevelJsonArrays(): void
    {
        $this->client->request(
            'POST',
            '/api/workflow/weekly-plans',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '[{"weekStart":"2027-01-04"}]',
        );

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('Request body must be a valid JSON object.', $this->responseData()['error']);
        self::assertSame('0', $this->countRows('weekly_plans'));
    }

    public function testAt22RejectsTopLevelJsonScalars(): void
    {
        $this->client->request(
            'POST',
            '/api/workflow/weekly-plans',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '"not-an-object"',
        );

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('Request body must be a valid JSON object.', $this->responseData()['error']);
        self::assertSame('0', $this->countRows('weekly_plans'));
    }

    private function storedIdeaTopic(string $ideaId): string
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine')->getConnection();

        return (string) $connection->fetchOne('SELECT topic FROM content_ideas WHERE id = ?', [$ideaId]);
    }

    private function countIdeaChannels(string $ideaId): string
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine')->getConnection();

        return (string) $connection->fetchOne("SELECT jsonb_array_length(channels::jsonb) FROM content_ideas WHERE id = ?", [$ideaId]);
    }

    public function testAt15RejectsInvalidIdeaValuesWithoutMutatingTheManagedEntity(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->patchJson('/api/workflow/content-ideas/'.$ideaId, ['topic' => '']);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame('Eyewear topic 1', $this->storedIdeaTopic($ideaId));
    }

    public function testAt16RejectsMalformedWeeklyPlanFieldTypes(): void
    {
        $this->postJson('/api/workflow/weekly-plans', [
            'weekStart' => ['not' => 'a date'],
            'slots' => [],
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('weekly_plans'));
    }

    public function testAt17RejectsMalformedSlotIdeaIdTypes(): void
    {
        $ideas = $this->createIdeas(5);
        $slots = $this->buildSlots($ideas);
        $slots[0]['ideaId'] = ['invalid' => 'uuid'];

        $this->postJson('/api/workflow/weekly-plans', [
            'weekStart' => '2027-01-04',
            'slots' => $slots,
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('weekly_plans'));
    }

    public function testAt18RejectsNonStringAiIdeaGenerationFields(): void
    {
        $this->postJson('/api/workflow/ai/ideas', [
            'campaign' => ['unexpected' => 'array'],
            'audience' => 'Adults aged 25 to 40',
            'tone' => 'Friendly',
            'channels' => ['instagram'],
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('content_ideas'));
    }

    public function testAt19RejectsOverlongBriefMediaReferences(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->postJson('/api/workflow/content-ideas/'.$ideaId.'/brief', [
            'mediaReference' => str_repeat('x', 2049),
            'scheduledAt' => '2027-01-05T10:00:00+01:00',
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('content_briefs'));
    }

    public function testAt20RejectsInvalidIdempotencyKeyCharacters(): void
    {
        $ideaId = $this->createIdeas(1)[0];

        $this->client->request(
            'POST',
            '/api/workflow/content-ideas/'.$ideaId.'/handoff',
            [],
            [],
            ['HTTP_IDEMPOTENCY_KEY' => 'invalid key with spaces'],
        );

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame('0', $this->countRows('content_handoffs'));
    }

    /**
     * @return list<string>
     */
    private function createIdeas(int $count): array
    {
        $ids = [];
        for ($index = 1; $index <= $count; ++$index) {
            $this->postJson('/api/content_ideas', [
                'title' => 'Acceptance idea '.$index,
                'topic' => 'Eyewear topic '.$index,
                'targetAudience' => 'Adults aged 25 to 40',
                'tone' => 'Friendly and informative',
                'channels' => ['instagram', 'facebook'],
            ]);
            self::assertSame(201, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());
            $body = $this->responseData();
            $id = $body['id'] ?? null;
            if (!is_string($id) || $id === '') {
                self::fail('The ContentIdea API response did not contain an id: '.$this->client->getResponse()->getContent());
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param list<string> $ideas
     * @return array<string, mixed>
     */
    private function createPlan(array $ideas): array
    {
        $this->postJson('/api/workflow/weekly-plans', [
            'weekStart' => '2027-01-04',
            'slots' => $this->buildSlots($ideas),
        ]);

        return $this->responseData();
    }

    /**
     * @param list<string> $ideas
     * @return list<array{ideaId: string, type: string, assignee: string, estimatedMinutes: int}>
     */
    private function buildSlots(array $ideas): array
    {
        $slots = [];
        foreach ($ideas as $index => $ideaId) {
            $slots[] = [
                'ideaId' => $ideaId,
                'type' => $index < 3 ? 'priority' : 'reserve',
                'assignee' => $index % 2 === 0 ? 'A' : 'B',
                'estimatedMinutes' => 15,
            ];
        }

        return $slots;
    }

    private function prepareForApproval(string $ideaId): void
    {
        $this->postJson('/api/workflow/content-ideas/'.$ideaId.'/brief', [
            'mediaReference' => 'synthetic://acceptance-test/product-image',
            'scheduledAt' => '2027-01-05T10:00:00+01:00',
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());

        $this->client->request('POST', '/api/workflow/content-ideas/'.$ideaId.'/review');
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());

        $this->client->request('POST', '/api/workflow/content-ideas/'.$ideaId.'/approve');
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent());
    }

    private function postJson(string $uri, array $payload): void
    {
        $this->client->request(
            'POST',
            $uri,
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function patchJson(string $uri, array $payload): void
    {
        $this->client->request(
            'PATCH',
            $uri,
            [],
            [],
            ['CONTENT_TYPE' => 'application/merge-patch+json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function countRows(string $table): string
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine')->getConnection();

        return (string) $connection->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    private function ideaStatus(string $ideaId): string
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get('doctrine')->getConnection();

        return (string) $connection->fetchOne('SELECT status FROM content_ideas WHERE id = ?', [$ideaId]);
    }
}
