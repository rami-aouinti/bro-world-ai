<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContentBrief;
use App\Entity\ContentIdea;
use App\Service\AiBriefGenerator;
use App\Service\ContentBriefService;
use App\Service\ContentHandoffService;
use App\Service\ContentReviewService;
use App\Service\EditContentIdeaService;
use App\Service\WeeklyPlanService;
use App\Workflow\JsonObjectPayload;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/workflow')]
final class WorkflowController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AiBriefGenerator $aiBriefGenerator,
        private readonly WeeklyPlanService $weeklyPlanService,
        private readonly ContentReviewService $contentReviewService,
        private readonly ContentBriefService $contentBriefService,
        private readonly EditContentIdeaService $editContentIdeaService,
        private readonly ContentHandoffService $contentHandoffService,
    ) {
    }

    #[Route('/ai/ideas', name: 'workflow_generate_ai_ideas', methods: ['POST'])]
    public function generateAiIdeas(Request $request): JsonResponse
    {
        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $campaign = $data['campaign'] ?? null;
        $audience = $data['audience'] ?? null;
        $tone = $data['tone'] ?? 'friendly and helpful';
        $channels = $data['channels'] ?? ['instagram', 'facebook'];
        $allowedChannels = ['instagram', 'facebook', 'tiktok', 'linkedin'];
        if (!is_string($campaign) || trim($campaign) === ''
            || !is_string($audience) || trim($audience) === ''
            || !is_string($tone) || trim($tone) === ''
            || !is_array($channels) || !array_is_list($channels) || $channels === []) {
            return $this->error('campaign, audience, tone and a non-empty channel list are required with valid types.');
        }
        $campaign = trim($campaign);
        $audience = trim($audience);
        $tone = trim($tone);
        if (strlen($campaign) > 255 || strlen($audience) > 255 || strlen($tone) > 80) {
            return $this->error('campaign and audience must be at most 255 characters; tone must be at most 80 characters.');
        }
        if (count($channels) > count($allowedChannels)) {
            return $this->error('Select no more than four channels.');
        }
        foreach ($channels as $channel) {
            if (!is_string($channel) || !in_array($channel, $allowedChannels, true)) {
                return $this->error('channels must contain only instagram, facebook, tiktok or linkedin.');
            }
        }

        try {
            $result = $this->aiBriefGenerator->generateIdeas([
                'campaign' => $campaign,
                'audience' => $audience,
                'tone' => $tone,
                'channels' => array_values(array_unique($channels)),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->error('AI idea generation is temporarily unavailable. Please retry later.', 503);
        }

        return new JsonResponse($result);
    }

    #[Route('/weekly-plans', name: 'workflow_create_weekly_plan', methods: ['POST'])]
    public function createWeeklyPlan(Request $request): JsonResponse
    {
        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $result = $this->weeklyPlanService->create($data);

        return new JsonResponse($result->body, $result->statusCode);
    }

    #[Route('/weekly-plans/{id}', name: 'workflow_get_weekly_plan', methods: ['GET'])]
    public function getWeeklyPlan(string $id): JsonResponse
    {
        $result = $this->weeklyPlanService->get($id);

        return new JsonResponse($result->body, $result->statusCode);
    }

    #[Route('/content-ideas/{id}/brief', name: 'workflow_generate_brief', methods: ['POST'])]
    public function generateBrief(string $id, Request $request): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) {
            return $idea;
        }

        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $result = $this->contentBriefService->generate($idea, $data);

        return new JsonResponse($result->body, $result->statusCode);
    }

    #[Route('/content-ideas/{id}/brief', name: 'workflow_get_brief', methods: ['GET'])]
    public function getBrief(string $id): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) { return $idea; }
        $brief = $this->entityManager->getRepository(ContentBrief::class)->findOneBy(['contentIdea' => $idea]);
        if (!$brief) { return $this->error('Content brief not found.', 404); }

        return new JsonResponse(['ideaId' => $id, 'versionHash' => $brief->getVersionHash(), 'createdAt' => $brief->getCreatedAt()->format(DATE_ATOM), 'brief' => $brief->getPayload()]);
    }

    #[Route('/content-ideas/{id}/review', name: 'workflow_submit_review', methods: ['POST'])]
    public function submitReview(string $id): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) {
            return $idea;
        }

        $result = $this->contentReviewService->submitForReview($idea);

        return new JsonResponse($result->body, $result->statusCode);
    }

    #[Route('/content-ideas/{id}/approve', name: 'workflow_approve', methods: ['POST'])]
    public function approve(string $id): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) {
            return $idea;
        }

        $result = $this->contentReviewService->approve($idea);

        return new JsonResponse($result->body, $result->statusCode);
    }

    #[Route('/content-ideas/{id}/handoff', name: 'workflow_handoff', methods: ['POST'])]
    public function handoff(string $id, Request $request): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) {
            return $idea;
        }

        $key = trim((string) $request->headers->get('Idempotency-Key'));
        $result = $this->contentHandoffService->handoff($idea, $key);

        return new JsonResponse($result->body, $result->statusCode);
    }

    #[Route('/content-ideas/{id}', name: 'workflow_edit_idea', methods: ['PATCH'])]
    public function editIdea(string $id, Request $request): JsonResponse
    {
        $idea = $this->findIdea($id);
        if ($idea instanceof JsonResponse) {
            return $idea;
        }

        $data = $this->body($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $result = $this->editContentIdeaService->edit($idea, $data);

        return new JsonResponse($result->body, $result->statusCode);
    }

    private function findIdea(string $id): ContentIdea|JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->error('Content idea not found.', 404);
        }

        $idea = $this->entityManager->getRepository(ContentIdea::class)->find($id);

        return $idea ?? $this->error('Content idea not found.', 404);
    }

    private function body(Request $request): array|JsonResponse
    {
        $data = JsonObjectPayload::decode($request->getContent());
        if ($data === false) {
            return $this->error('Request body must be a valid JSON object.');
        }

        return $data;
    }

    private function error(string $message, int $status = 400): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }
}
