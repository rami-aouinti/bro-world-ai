# Getting started

## Prerequisites
- Docker Desktop / Docker Engine with Compose v2
- Git
- Python 3.12+ only if running the synthetic RAG ingestion script from the host

## Start the stack
```bash
cp .env.example .env
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

Services:
- Frontend (Vue 3): http://localhost:3000
- Symfony API: http://localhost:8000/api
- FastAPI docs: http://localhost:8001/docs
- PostgreSQL + pgvector: localhost:5432
- Ollama: localhost:11434
- n8n: http://localhost:5678

The default AI provider is mock; no model download or API key is required. Symfony calls FastAPI for brief generation in local Docker development. The backend PHPUnit environment deliberately uses a deterministic fallback.

## Enable local Ollama generation and semantic RAG
Set these values in .env:

```dotenv
AI_PROVIDER=ollama
OLLAMA_MODEL=llama3.2:3b
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
```

Download both local models and rebuild the AI service:

```bash
docker compose exec ollama ollama pull llama3.2:3b
docker compose exec ollama ollama pull nomic-embed-text
docker compose up -d --build ai-service backend
```

The embedding model must produce 768-dimensional vectors for the current pgvector schema.

## Load synthetic knowledge documents
After the stack is healthy:

```bash
python scripts/ingest-knowledge.py
```

This sends three synthetic brand/product/approval documents to FastAPI. With the default mock provider this validates ingestion and vector persistence only; mock vectors are not semantic. Use Ollama and nomic-embed-text for meaningful similarity search.

## Import n8n workflows
Open http://localhost:5678, complete the initial owner setup if prompted, then import the JSON files from workflows/exports. Read docs/N8N-WORKFLOWS.md before activating the Monday schedule. The email workflow returns an unsent draft and handoff remains simulated.

## Tests
Backend:
```bash
docker compose exec -e APP_ENV=test -e TEST_TOKEN=1 backend vendor/bin/phpunit --testdox
```

AI service:
```bash
docker compose exec ai-service pytest -q
```

## Safety
Never use real social accounts, customer data or production credentials in this prototype. AI content always requires human review. The handoff does not publish a post.
