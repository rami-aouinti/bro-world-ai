# Bro World AI — Social Media Workflow Platform

AI-assisted platform to plan, draft, review and approve social-media content for a fictional optical retailer.

## Stack and responsibilities
- Backend: PHP 8.4 / Symfony 7.4 / API Platform / Doctrine — business rules, capacity, approval and handoff.
- AI service: Python 3.12 / FastAPI — structured idea and brief generation, embeddings and retrieval.
- Local LLM: Ollama — no paid model API required.
- RAG: PostgreSQL 16 + pgvector — synthetic brand, product and workflow knowledge.
- Automation: n8n — weekly planning, AI production, approval email draft and guarded handoff.
- Frontend: Vue 3 / TypeScript / Vite.
- Infrastructure: Docker Compose.

## Implemented workflow
- Weekly planning creates five ideas: three priorities and two reserves.
- Person A capacity is 90 minutes; Person B capacity is 150 minutes. The backend rejects over-capacity plans with HTTP 422.
- AI-assisted briefs contain a hook, master copy, channel variants, production steps, visual direction, placeholder booking URL and effort estimate.
- FastAPI uses a deterministic mock by default and can use local Ollama generation.
- RAG ingestion and retrieval use pgvector. Mock embeddings are deterministic test vectors, not semantic embeddings.
- Review and approval are human-controlled and bound to the exact brief version.
- Editing invalidates the previous brief and approval.
- Handoff is simulated, approval-guarded and idempotent. No post is published.
- Four n8n workflow exports are committed under workflows/exports.

## Prototype boundaries
No real social accounts, customers, publishing APIs, paid AI APIs or personal data are used. Media and booking references are placeholders. The approval email workflow returns an unsent draft. Inspiration findings and knowledge documents are synthetic. See docs/AI-AND-RAG.md, docs/N8N-WORKFLOWS.md, docs/OPERATIONS-AND-FUTURE-SCOPE.md, docs/IMPLEMENTATION-STATUS.md and docs/ACCEPTANCE-TESTS.md.

## Start locally
```bash
cp .env.example .env
docker compose config --quiet
docker compose up -d --build
```

- Frontend: http://localhost:3000
- Symfony API: http://localhost:8000/api
- FastAPI docs: http://localhost:8001/docs
- n8n: http://localhost:5678
- Ollama: http://localhost:11434

Default AI_PROVIDER=mock makes the stack testable without downloading a model. To use a real local model, see docs/AI-AND-RAG.md.

Load synthetic RAG documents:

```bash
python scripts/ingest-knowledge.py
```

Import n8n workflows from workflows/exports using the UI. Follow docs/N8N-WORKFLOWS.md.

## Backend acceptance tests
```bash
docker compose exec -e APP_ENV=test -e TEST_TOKEN=1 backend vendor/bin/phpunit --testdox
```

## AI service tests
```bash
docker compose exec ai-service pytest -q
```

The previously verified backend baseline was 13 tests / 97 assertions. Re-run the commands after pulling latest master; new AI/RAG code has not yet been executed in the local Docker environment.
