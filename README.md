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

## Prototype scope
The first milestone implements a complete, auditable workflow:
1. Five weekly ideas: three priorities and two reserves.
2. Capacity planning for two fictional team members.
3. One complete production brief and channel variants.
4. Versioned human approval.
5. A simulated publishing handoff with idempotency.
6. A weekly text report and four required acceptance scenarios.

No real social accounts, customers, publishing APIs or personal data are used. AI output may be deterministic and is clearly labelled as simulated.

## Repository layout
- `apps/backend`: Symfony business API
- `apps/ai-service`: FastAPI AI adapter
- `apps/frontend`: Vue application
- `infrastructure`: Docker, database and n8n configuration
- `workflows/exports`: n8n workflow exports
- `data`: synthetic input and generated examples
- `docs`: architecture, decisions, test evidence and roadmap

## Getting started
See [docs/GETTING_STARTED.md](docs/GETTING_STARTED.md) for local setup. Copy `.env.example` to `.env` and never commit secrets.

## Status
Scaffolding and prototype foundation. Features are labelled as implemented, simulated or planned in the documentation.
