# Implementation status

Last updated: 2026-11-19

## Implemented in master

### Core application
- Symfony 7.4 + API Platform + Doctrine/PostgreSQL owns the business workflow and state.
- Vue 3 + TypeScript + Vite frontend provides overview, ideas, weekly planning, capacity allocation, brief editing, review, approval and simulated handoff.
- Weekly plans require exactly five distinct ideas: three priorities and two reserves.
- Default weekly capacity is Person A=90 minutes and Person B=150 minutes. Over-capacity requests return HTTP 422.
- Briefs require a media reference and scheduled time.
- Review and approval are human-controlled and bound to the exact brief version hash.
- Editing invalidates the old brief and approval.
- Simulated handoff checks approval, assignment and media reference and uses a unique idempotency key.

### AI service and RAG
- FastAPI exposes /api/v1/generate, /api/v1/ideas, /api/v1/brief, /api/v1/knowledge and /api/v1/retrieve.
- Deterministic mock provider is the default; Ollama is an optional local generation and embedding provider.
- Symfony calls FastAPI for brief generation in local Docker development. Functional tests use a deterministic backend fallback and do not require Ollama.
- pgvector stores 768-dimensional knowledge chunks with source IDs and metadata.
- Sample brand, product, approval and inspiration documents can be ingested with python scripts/ingest-knowledge.py; retrieved source IDs are returned with AI suggestions and briefs.
- Mock embedding is a stable hash vector used only to test storage/retrieval plumbing. It is not semantic search. Use Ollama + nomic-embed-text for semantic embeddings.

### n8n
- Four importable exports exist in workflows/exports: weekly AI planning, AI content production, approval email draft and approved handoff.
- Weekly planning creates five AI-suggested ideas and a capacity-safe plan, then returns a text overview and an unsent email draft.
- Content production delegates to Symfony, which calls FastAPI.
- Approval notification produces an unsent email draft; it does not send mail or approve content.
- Handoff calls Symfony and remains simulated; Symfony enforces approval and idempotency.
- Workflows must be imported and activated in the local n8n UI before they execute.

### Synthetic assignment material
- Campaign input, three synthetic inspiration findings with channel/source IDs, synthetic brand guidelines, RAG documents, weekly plan example, AI brief example, weekly overview and unsent email drafts are in data/synthetic.
- Unknown inspiration metrics are explicitly null. Findings are fictional creative hypotheses, not verified trends.

## Previously verified backend baseline
On 2026-11-19, before the AI/RAG integration changes, PHPUnit reported 13 tests passed, 97 assertions, 0 failures (2 unit and 11 functional acceptance tests).

The latest AI/RAG and Symfony-to-FastAPI changes are committed to master, but still require local verification after pulling:
- Docker Compose config and rebuild.
- FastAPI pytest suite.
- Symfony PHPUnit suite and Doctrine schema validation.
- Real Ollama generation after pulling both models.
- pgvector ingestion and retrieval with Ollama embeddings.
- Import and manual execution of each n8n workflow.
- Browser-level end-to-end workflow.

## Simulated / not connected to external services
- Default AI mock output is deterministic. Actual model generation requires AI_PROVIDER=ollama and pulling the models.
- Mock embeddings are not semantically meaningful.
- Social publishing handoff persists a payload only. No social network is contacted.
- Media references and booking URLs are placeholders.
- Approval email is a JSON draft only; no email is sent.
- All sample brand, inspiration, people, ideas, sources and metrics are synthetic.

## Not implemented
- Real social network publishing.
- Real analytics ingestion, ROI calculations or dashboard.
- Comment moderation workflow with human review.
- Real media upload/asset storage and booking integration.
- Automated browser E2E and frontend component tests.
- Production authentication, secrets management, monitoring and deployment hardening.

Do not report features as locally verified until the commands and manual checks above have been run.


## Local verification fixes (2026-11-19)

- Configure `PYTHONPATH=/app` in the AI service image so pytest can import `app` when run from the container root.
- Register PostgreSQL `vector` as a Doctrine `string` introspection mapping. The pgvector table is owned by FastAPI and is not a Doctrine entity; this mapping prevents DBAL schema introspection from failing on the extension type.
- These two configuration fixes are committed but still require a local rebuild and verification.
