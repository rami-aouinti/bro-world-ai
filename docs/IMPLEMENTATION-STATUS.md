# Implementation status

Last updated: 2026-11-19

## Implemented in the repository

- Repository structure and project documentation.
- Docker Compose infrastructure for PostgreSQL/pgvector, Ollama, n8n, FastAPI and Symfony.
- FastAPI health and generation endpoints with deterministic mock provider by default and optional Ollama provider.
- Symfony 7.4 + API Platform ContentIdea resource backed by Doctrine/PostgreSQL.
- Weekly plan creation and read endpoint: exactly five distinct ideas, three priority and two reserve.
- Capacity allocation for Person A (90 minutes) and Person B (150 minutes by default), with explicit HTTP 422 over-capacity details.
- Deterministic content brief generation with hook, master copy, production plan, visual direction, placeholder booking URL, channel-specific variants and effort estimate.
- Review/approval lifecycle with a persisted SHA-256 approval snapshot bound to the exact brief version.
- PATCH editing that invalidates the old brief and approval and returns the idea to draft.
- Simulated handoff guarded by approval and media reference, with a database unique idempotency key and replay response.
- Backend usage documentation and acceptance verification checklist.

## Executed locally (evidence available in conversation)

- Docker Compose backend built and started after adding required environment variables.
- Initial Doctrine migration Version20261119130000 executed successfully.
- A ContentIdea POST returned HTTP 201 and a generated UUID, confirming the first API/Doctrine/PostgreSQL vertical slice.

## Implemented but still requiring local verification

- Workflow migrations Version20261119140000 and Version20261119140100.
- Weekly plan, capacity, brief, approval invalidation and idempotent handoff endpoints.
- doctrine:schema:validate, PHPUnit and all workflow acceptance scenarios after pulling the latest master.

## Simulated / not connected to external services

- LLM generation when AI_PROVIDER=mock.
- Social publishing handoff: persisted payload only; no social network is contacted and no post is published.
- Media references and booking URL in sample briefs.
- All sample brand, people, ideas, source references and metrics.

## Not implemented yet

- Frontend screens.
- RAG ingestion, chunking, embeddings and retrieval.
- n8n workflow definitions and real notifications.
- Real social network publishing, analytics calculations, ROI dashboard and comment moderation.

Do not describe planned features or static example fixtures as implemented behavior. Update this file after local workflow verification.
