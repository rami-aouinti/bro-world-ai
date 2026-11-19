# AI, Ollama and RAG

## Responsibility boundaries
- Vue collects campaign inputs and displays suggestions.
- Symfony owns business rules, capacity, lifecycle, version hashes, human approval and handoff.
- FastAPI provides structured generation, embeddings and retrieval.
- Ollama is the local LLM and embedding runtime; no paid API is required.
- PostgreSQL + pgvector stores synthetic knowledge.
- n8n coordinates workflows and never bypasses Symfony approval rules.

AI output is always an unapproved draft. It cannot approve or publish content.

## Providers
The default is AI_PROVIDER=mock. It is deterministic for repeatable tests. Mock embeddings are stable hash vectors, not semantic embeddings; they only exercise the vector storage/retrieval plumbing.

For actual local generation and semantic embeddings, set in .env:

```dotenv
AI_PROVIDER=ollama
OLLAMA_MODEL=llama3.2:3b
OLLAMA_EMBEDDING_MODEL=nomic-embed-text
```

Download both models:

```bash
docker compose exec ollama ollama pull llama3.2:3b
docker compose exec ollama ollama pull nomic-embed-text
docker compose up -d --build ai-service backend
```

The embedding model must return 768 dimensions, matching the pgvector column.

## Symfony integration
When a brief is requested, Symfony calls POST http://ai-service:8001/api/v1/brief with the idea, audience, tone, channels, schedule and media reference. FastAPI returns a structured brief. Symfony verifies required fields, requested channel variants and the media reference, then stores the payload and computes the version hash.

In APP_ENV=test, the adapter deliberately uses the deterministic backend fallback. PHPUnit does not depend on Ollama, FastAPI availability or model downloads. In local Docker development, Symfony uses FastAPI.

If FastAPI is unavailable in development, Symfony returns HTTP 503 rather than silently pretending an AI call succeeded.

## RAG lifecycle
1. Synthetic brand, product and approval documents live in data/synthetic/knowledge-documents.json.
2. The ingestion script sends each document to POST /api/v1/knowledge.
3. FastAPI normalizes and chunks text with overlap.
4. The configured embedding provider creates one 768-dimensional vector per chunk.
5. Chunks and metadata are stored in ai_knowledge_chunks using pgvector.
6. During brief generation, FastAPI embeds the topic/audience query and retrieves the five nearest chunks using cosine distance.
7. Retrieved text is added to the generation prompt. It does not override explicit inputs or Symfony business rules.

Load the sample knowledge base:

```bash
python scripts/ingest-knowledge.py
```

The script expects the AI service at http://localhost:8001. Override with AI_SERVICE_URL if needed.

Useful endpoints: GET /health, POST /api/v1/ideas, POST /api/v1/brief, POST /api/v1/knowledge and POST /api/v1/retrieve. OpenAPI docs: http://localhost:8001/docs.

## Safety and limitations
- All sample brand, product and inspiration data is synthetic.
- Inspiration findings are creative hypotheses; metrics are unknown, not verified trends.
- No real social/customer data is ingested.
- No real booking, media upload or publishing API is called.
- Ollama output can still be inaccurate. Human review remains mandatory.
- The mock provider tests contracts and vector plumbing, not semantic quality.
