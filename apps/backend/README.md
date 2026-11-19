# Symfony backend

Symfony 7.4 + API Platform is the source of truth for planning, capacity, content versions, approval decisions and simulated publishing handoff. The AI service may suggest content, but it must never approve or publish it.

## Run locally with Docker Compose

From the repository root:

```bash
docker compose up -d --build backend
docker compose logs -f backend
```

The backend is available at:

- API Platform docs: http://localhost:8000/api
- Content ideas collection: http://localhost:8000/api/content_ideas
- Workflow endpoints: http://localhost:8000/api/workflow

The backend container installs Composer dependencies on startup. The project directory is bind-mounted, so source changes are immediately visible.

## Database

The backend connects to PostgreSQL using the Docker network hostname `postgres`. Apply migrations:

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
```

## Tests

```bash
docker compose exec backend vendor/bin/phpunit
```

## 1. Content ideas

Create an idea:

```bash
curl -X POST http://localhost:8000/api/content_ideas \
  -H 'Content-Type: application/ld+json' \
  -H 'Accept: application/ld+json' \
  -d '{"title":"Herbstkollektion","topic":"Neue Brillenmodelle","targetAudience":"Junge Erwachsene","tone":"Modern und freundlich","channels":["instagram","facebook"]}'
```

The server generates a UUID and new ideas start in `draft`. Supported channels: `instagram`, `facebook`, `tiktok`, `linkedin`.

## 2. Weekly planning and capacity

Create five ideas first, then submit a weekly plan with exactly three priority slots and two reserve slots. Each slot must identify its idea, owner (`A` or `B`) and estimated minutes.

```bash
curl -X POST http://localhost:8000/api/workflow/weekly-plans \
  -H 'Content-Type: application/json' \
  -d '{
    "weekStart":"2026-11-23",
    "capacityPersonA":90,
    "capacityPersonB":150,
    "slots":[
      {"ideaId":"IDEA_UUID_1","type":"priority","assignee":"A","estimatedMinutes":30},
      {"ideaId":"IDEA_UUID_2","type":"priority","assignee":"A","estimatedMinutes":30},
      {"ideaId":"IDEA_UUID_3","type":"priority","assignee":"B","estimatedMinutes":45},
      {"ideaId":"IDEA_UUID_4","type":"reserve","assignee":"B","estimatedMinutes":30},
      {"ideaId":"IDEA_UUID_5","type":"reserve","assignee":"B","estimatedMinutes":30}
    ]
  }'
```

Defaults are 90 minutes for Person A and 150 minutes for Person B. Capacity is calculated from each person's assigned slot estimates. A request exceeding either limit returns HTTP 422 with the exact overrun; the API does not silently reduce estimates or capacity. A week can have one plan.

## 3. Content brief

Generate a complete deterministic sample brief. `mediaReference` is a required reference to an already selected asset; this prototype does not upload or verify a real file.

```bash
curl -X POST http://localhost:8000/api/workflow/content-ideas/IDEA_UUID/brief \
  -H 'Content-Type: application/json' \
  -d '{"mediaReference":"media://sample/hero-image-001"}'
```

The brief contains a hook, master copy, production steps, visual overlay/title-image direction, placeholder booking URL, distinct variants for each selected channel and an effort breakdown. The copy and media reference are synthetic examples, not verified campaign recommendations.

## 4. Review and approval

The state machine is:

`draft → review → approved → handed_off`

Submit a brief for review:

```bash
curl -X POST http://localhost:8000/api/workflow/content-ideas/IDEA_UUID/review
```

Explicitly approve the reviewed version:

```bash
curl -X POST http://localhost:8000/api/workflow/content-ideas/IDEA_UUID/approve
```

Only an idea with a complete brief and media reference can enter review. Only an idea in `review` can be approved. Approval creates a persistent approval record containing the SHA-256 hash of the exact approved brief and its timestamp. Handoff verifies that the current brief hash still matches this approval record.

Edit a non-handed-off idea with PATCH:

```bash
curl -X PATCH http://localhost:8000/api/workflow/content-ideas/IDEA_UUID \
  -H 'Content-Type: application/json' \
  -d '{"title":"Updated title"}'
```

Any edit invalidates the prior brief and approval by removing both records and resetting the idea to `draft`. Regenerate the brief and repeat review/approval. Handed-off content is immutable; create a new idea for a later revision.

## 5. Idempotent simulated handoff

```bash
curl -X POST http://localhost:8000/api/workflow/content-ideas/IDEA_UUID/handoff \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: campaign-idea-001-v1' \
  -d '{"scheduledAt":"2026-11-25T10:00:00+01:00"}'
```

Handoff is allowed only after explicit approval and with a media reference. The response is a simulated handoff payload; no social network is contacted and no post is published. Repeating the request with the same key returns the original handoff (`idempotentReplay: true`) rather than creating another row. The database enforces a unique constraint on the idempotency key.

## Architecture and boundaries

- Symfony/Doctrine owns the business rules and persistent state.
- The AI service can propose text, but it cannot change lifecycle state or approve content.
- This implementation uses deterministic sample copy so acceptance tests do not depend on an external model.
- The weekly plan has one slot per idea, with three priority and two reserve slots.
- The brief's effort estimate is illustrative; capacity planning uses the per-slot estimates supplied in the weekly-plan request.
- Media and booking references are placeholders. Human review is required.
- Handoff is a persisted simulation only. Real social publishing, analytics, ROI and moderation integrations are not implemented.
