# FastAPI AI service

The service exposes structured content generation, five-idea suggestions and a pgvector-backed retrieval API.

## Endpoints

- \`GET /health\`: provider health.
- \`POST /api/v1/generate\`: one channel-specific draft.
- \`POST /api/v1/ideas\`: exactly five synthetic campaign ideas.
- \`POST /api/v1/brief\`: structured brief with channel variants and an optional RAG context lookup.
- \`POST /api/v1/knowledge\`: replace a source's chunks and embeddings.
- \`POST /api/v1/retrieve\`: retrieve the most similar knowledge chunks.

OpenAPI documentation is available at \`/docs\`.

## Providers

- \`AI_PROVIDER=mock\` is the default. It returns deterministic examples for repeatable tests. Mock embeddings are deterministic hash vectors and are **not semantic embeddings**.
- \`AI_PROVIDER=ollama\` calls Ollama using \`OLLAMA_MODEL\` for generation and \`OLLAMA_EMBEDDING_MODEL\` for embeddings. The default embedding model is \`nomic-embed-text\` (768 dimensions).

## RAG

Knowledge chunks are stored in PostgreSQL using the pgvector \`vector(768)\` type. The service creates the extension and table on startup. Ingesting a source replaces the existing chunks for that source ID. Retrieval uses cosine distance.

Example:

\`\`\`bash
curl -X POST http://localhost:8001/api/v1/knowledge \\
  -H 'Content-Type: application/json' \\
  -d '{"sourceId":"brand-guide-001","title":"Brand guide","text":"Our optician brand uses a friendly, clear and helpful tone. Never invent prices, medical claims or product specifications.","metadata":{"kind":"synthetic-brand-guideline"}}'

curl -X POST http://localhost:8001/api/v1/retrieve \\
  -H 'Content-Type: application/json' \\
  -d '{"query":"What tone should social posts use?","topK":3}'
\`\`\`

For meaningful semantic retrieval, use \`AI_PROVIDER=ollama\` and pull both models:

\`\`\`bash
docker compose exec ollama ollama pull llama3.2:3b
docker compose exec ollama ollama pull nomic-embed-text
\`\`\`

The mock provider is intended for deterministic acceptance tests only; its hash vectors do not provide semantic similarity.

## Tests

\`\`\`bash
cd apps/ai-service
python -m pip install -r requirements.txt
pytest -q
\`\`\`
