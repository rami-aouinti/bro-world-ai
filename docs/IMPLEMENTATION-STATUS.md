# Implementation status

Last updated: 2026-11-19

## Implemented

- Docker Compose infrastructure for PostgreSQL/pgvector, Ollama, n8n, FastAPI and Symfony.
- Symfony 7.4 + API Platform ContentIdea resource backed by Doctrine/PostgreSQL.
- FastAPI AI adapter with deterministic mock provider by default and optional Ollama provider.
- Weekly plan creation and retrieval: exactly five distinct ideas, three priority and two reserve.
- Capacity allocation for Person A (90 minutes) and Person B (150 minutes by default), with explicit HTTP 422 over-capacity details.
- Deterministic content brief generation with hook, master copy, production plan, visual direction, placeholder booking URL, channel-specific variants and effort estimate.
- Review and approval lifecycle with a persisted SHA-256 snapshot bound to the exact brief version.
- PATCH editing that invalidates the old brief and approval and returns the idea to draft.
- Simulated handoff guarded by approval and media reference, with a database unique idempotency key and replay response.
- Backend usage documentation and acceptance test suite.

## Local verification completed (2026-11-19)

- Docker Compose backend was built and started.
- Doctrine migrations through `Version20261119140100` were executed against the dedicated test database `bro_world_ai_test1`.
- `doctrine:schema:validate --env=test` confirmed that Doctrine mapping and database schema are in sync.
- PHPUnit was run with `APP_ENV=test` and `TEST_TOKEN=1`.
- Result: **13 tests passed, 97 assertions, 0 failures** (2 unit tests and 11 workflow acceptance tests, AT-01 to AT-11).
- The test database is isolated from the development database by the `_test1` suffix.

Reproducible test command from the repository root:

```bash
docker compose exec \
  -e APP_ENV=test \
  -e TEST_TOKEN=1 \
  backend vendor/bin/phpunit --testdox
```

Apply development migrations and validate the development schema separately:

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console doctrine:schema:validate
```

## Simulated / not connected to external services

- AI generation is deterministic when `AI_PROVIDER=mock`; Ollama is an optional local provider.
- Social publishing handoff persists a payload only. No social network is contacted and no post is published.
- Media references and booking URLs in sample briefs are placeholders; the prototype does not upload or verify media.
- Sample brand, people, ideas, source references and metrics are synthetic.

## Not implemented yet

- Completed frontend screens and end-to-end browser workflow.
- RAG ingestion, chunking, embeddings and retrieval.
- Exported/connected n8n workflow definitions and real notifications.
- Real social network publishing, analytics calculations, ROI dashboard and comment moderation.

Do not describe planned features or static example fixtures as implemented behavior. Keep implemented, simulated and planned capabilities clearly separated.
