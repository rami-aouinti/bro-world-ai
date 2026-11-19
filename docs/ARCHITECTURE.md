# Architecture

## System boundaries

- **Vue 3** is the presentation layer. It never decides whether a post is approved.
- **Symfony 7.4 + API Platform** owns users, ideas, schedules, capacity rules, post versions, approval decisions and publishing handoff.
- **FastAPI** exposes AI capabilities. It returns drafts; it cannot approve or publish content.
- **Ollama** is an interchangeable local LLM provider. A deterministic mock is the default for repeatable tests.
- **PostgreSQL + pgvector** stores business records and, in a later milestone, document embeddings.
- **n8n** orchestrates notifications and scheduled jobs. It calls Symfony APIs and does not bypass business rules.

## Prototype lifecycle

`draft -> review -> approved -> handed_off`

A review decision references an immutable snapshot containing:
- content version and text hash;
- media reference;
- selected channels;
- planned publication timestamp.

Any change to these fields invalidates the approval. Handoff requires a valid approval and complete media/channel/schedule assignments. The handoff uses a unique idempotency key so retries cannot create duplicate payloads.

## Trust boundaries

All data is synthetic. Publishing is a local simulation only. The AI provider is explicitly labelled as mock or Ollama. Generated content must be reviewed by a human.
