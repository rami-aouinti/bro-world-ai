# Getting started

## Prerequisites
- Docker Desktop / Docker Engine with Compose v2
- Git
- For local app development: PHP 8.3+, Composer, Node.js 20+, Python 3.12+

## Start infrastructure

```bash
cp .env.example .env
docker compose config
docker compose up -d --build
docker compose ps
```

Services:
- Frontend (Vue 3): http://localhost:3000
- Symfony API: http://localhost:8000/api
- PostgreSQL + pgvector: localhost:5432
- Ollama: localhost:11434
- n8n: http://localhost:5678
- FastAPI: http://localhost:8001/docs

The default AI provider is `mock`, so tests do not require a downloaded model or an API key. The frontend proxies browser requests from `/api` to the Symfony backend through the Docker network.

## Optional: enable Ollama

Set `AI_PROVIDER=ollama` in `.env`, then:

```bash
docker compose exec ollama ollama pull llama3.2:3b
```

Restart the AI service:

```bash
docker compose up -d --build ai-service
```

## Local AI-service tests

```bash
cd apps/ai-service
python -m venv .venv
# Windows PowerShell: .venv\\Scripts\\Activate.ps1
# macOS/Linux: source .venv/bin/activate
pip install -r requirements.txt
pip install httpx
pytest
```

## Safety
Never use real social-media accounts or customer data. Never commit `.env` or API credentials. Handoff is simulated; no real post is published.

## Frontend development

The frontend starts with the full Docker Compose stack. To rebuild or restart only the frontend:

```bash
docker compose up -d --build frontend
docker compose logs -f frontend
```

Open http://localhost:3000. See [apps/frontend/README.md](../apps/frontend/README.md) for standalone Node.js development and production build commands.
