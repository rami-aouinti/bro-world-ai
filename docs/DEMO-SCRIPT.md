# Demo script — Bro World AI

This script is designed for a 10–15 minute walkthrough of the assignment. Use only the fictional data in this repository.

## 1. Start and verify the stack

```bash
cp .env.example .env
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

Open:
- Frontend: http://localhost:3000
- FastAPI docs: http://localhost:8001/docs
- n8n: http://localhost:5678

Default AI_PROVIDER=mock keeps the demo deterministic and avoids model downloads.

## 2. Load RAG documents

```bash
python scripts/ingest-knowledge.py
```

The knowledge set includes synthetic brand guidelines, fictional product context, approval rules and three inspiration findings:
- SYN-IG-001 — Instagram
- SYN-TT-002 — TikTok
- SYN-LI-003 — LinkedIn

All metrics are unknown. The findings are creative hypotheses, not verified trends. The mock embedding validates the storage path only; use Ollama for semantic retrieval.

## 3. Demonstrate AI suggestions in the frontend

1. Open Content ideas.
2. Click Generate 5 AI ideas.
3. Inspect the provider and retrieved source IDs.
4. Add the ideas you want to keep to the library.
5. Explain that suggestions are drafts and require human selection.

## 4. Create and review a weekly plan

1. Select exactly five ideas in Weekly plan.
2. Keep three priorities and two reserves.
3. Assign a total of 60 minutes to Person A (capacity 90).
4. Assign a total of 110 minutes to Person B (capacity 150).
5. Save the plan and show remaining capacity.

Optional negative test: allocate more than 90 minutes to Person A. The API rejects the plan with HTTP 422 and reports the overrun.

## 5. Generate an AI brief and hand it off

1. Open a planned idea.
2. Set a synthetic media reference and a future schedule.
3. Generate the brief and inspect the hook, master copy, visual direction, channel variants and effort breakdown.
4. Submit it for review.
5. Approve the exact version.
6. Trigger the simulated handoff.
7. Repeat with the same idempotency key and show that no duplicate is created.

Optional negative test: edit the content after approval. The old brief and approval are invalidated; the content must be reviewed again.

## 6. Demonstrate n8n

Import the JSON files from workflows/exports.

- Weekly AI Planning: run the manual webhook once; inspect the five created ideas, weekly plan, text overview and unsent email draft.
- AI Content Production: submit ideaId, mediaReference and scheduledAt; n8n calls Symfony, which calls FastAPI.
- Approval Email Draft: submit a synthetic reviewer event; show the email JSON and delivery=not_sent.
- Approved Handoff: submit an approved ideaId and stable idempotencyKey; Symfony checks approval and returns the simulated payload.

Do not execute Weekly AI Planning more than once for the same week; it creates ideas before the backend checks whether a plan already exists.

## 7. Optional real local model

Set AI_PROVIDER=ollama in .env, then pull:

```bash
docker compose exec ollama ollama pull llama3.2:3b
docker compose exec ollama ollama pull nomic-embed-text
docker compose up -d --build ai-service backend
```

Regenerate suggestions and a brief. Explain that the mock provider is for repeatable tests, while Ollama provides local generation and semantic embeddings.

## 8. Explain future scope

Analytics and ROI, comment moderation with human review, real media uploads, booking integration and real social publishing are documented future work. The current prototype does not send email or publish posts.
