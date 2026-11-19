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

The backend container installs Composer dependencies on startup. The project directory is bind-mounted, so source changes are immediately visible.

## Database

The backend connects to the PostgreSQL service using the Docker network hostname `postgres`. To create/update the schema:

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
```

## Tests

```bash
docker compose exec backend php bin/phpunit
```

## First resource: ContentIdea

A content idea stores its title, topic, target audience, tone, selected social channels, status and timestamps.

- The server generates the UUID.
- New ideas always start in `draft`.
- Status and timestamps are read-only through this first API resource.
- Supported channels: `instagram`, `facebook`, `tiktok`, `linkedin`.
- The first API slice exposes collection/item reads and creation only.

This is intentionally a small vertical slice. Weekly planning, time-capacity validation, content versions, approval snapshots and idempotent handoff will be implemented as separate application use cases.
