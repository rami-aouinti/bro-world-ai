# Bro World AI — Social Media Workflow Platform

AI-assisted platform to plan, draft, review and approve social-media content for a fictional optical retailer.

## Stack

- Backend: PHP 8.3+ / Symfony 7.4 / API Platform
- AI service: Python 3.11+ / FastAPI
- Local LLM runtime: Ollama (provider adapter; deterministic mock for tests)
- Frontend: Vue 3 / TypeScript / Vite
- Database: PostgreSQL 16 with pgvector
- Workflow automation: n8n
- Local infrastructure: Docker Compose

## Current milestone

The Symfony backend workflow is implemented and its automated test suite passes locally. A Vue 3 + TypeScript frontend is now implemented and wired into Docker Compose:

- 2 unit tests and 11 functional acceptance tests.
- 13 tests passed, 97 assertions, 0 failures (2026-11-19).
- Weekly planning with five ideas: three priorities and two reserves.
- Capacity checks for two fictional team members.
- Brief generation with channel variants and production guidance.
- Version-bound human review and approval.
- Simulated, idempotent publishing handoff.

The backend is the source of truth for business rules and workflow state. AI may suggest content, but it cannot approve or publish it.

## Prototype boundaries

No real social accounts, customers, publishing APIs or personal data are used. Media and booking references are placeholders. The handoff persists a simulated payload; it does not publish a post.

The frontend includes an overview dashboard, idea library, weekly planning and capacity allocation, review queue, brief editor, human approval and simulated handoff. The frontend build still needs to be verified in the local Docker environment. RAG ingestion/retrieval, connected n8n automations, real publishing, analytics, ROI calculations and comment moderation remain future work. See [docs/IMPLEMENTATION-STATUS.md](docs/IMPLEMENTATION-STATUS.md) for the detailed status and [docs/ACCEPTANCE-TESTS.md](docs/ACCEPTANCE-TESTS.md) for test evidence.

## Repository layout

- `apps/backend`: Symfony business API
- `apps/ai-service`: FastAPI AI adapter
- `apps/frontend`: Vue application
- `infrastructure`: Docker, database and n8n configuration
- `workflows/exports`: n8n workflow exports
- `data`: synthetic input and generated examples
- `docs`: architecture, decisions, test evidence and roadmap

## Getting started

See [docs/GETTING_STARTED.md](docs/GETTING_STARTED.md) for local setup and [apps/frontend/README.md](apps/frontend/README.md) for frontend details. Copy `.env.example` to `.env` and never commit secrets.

## Verification

Run the backend acceptance suite with the isolated test environment:

```bash
docker compose exec \
  -e APP_ENV=test \
  -e TEST_TOKEN=1 \
  backend vendor/bin/phpunit --testdox
```
