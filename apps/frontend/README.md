# Bro World AI frontend

Vue 3 + TypeScript + Vite application for the social content workflow.

## Run with Docker Compose (recommended)

From the repository root:

```bash
docker compose up -d --build frontend
docker compose logs -f frontend
```

Open [http://localhost:3000](http://localhost:3000).

The Vite development server listens on port 5173 inside the container. Docker maps it to port 3000 on the host. Requests under `/api` are proxied to the Symfony service at `http://backend:8000`, so browser requests stay same-origin and do not need a separate CORS configuration.

To rebuild after dependency or Dockerfile changes:

```bash
docker compose up -d --build frontend
```

## Run outside Docker

Requirements: Node.js 20+ and npm.

```bash
cd apps/frontend
npm install
VITE_API_PROXY_TARGET=http://localhost:8000 npm run dev -- --host 0.0.0.0
```

Open [http://localhost:5173](http://localhost:5173).

## Production build

```bash
cd apps/frontend
npm install
npm run build
npm run preview -- --host 0.0.0.0
```

The production assets are written to `dist/`.

## Implemented screens

- **Overview:** idea, plan, review and handoff metrics; team capacity and recent ideas.
- **Content ideas:** search, status filtering, idea creation and editing.
- **Weekly plan:** choose five ideas, assign the first three as priorities and the final two as reserves, allocate Person A/B and validate capacity before saving.
- **Review queue:** review and approved content.
- **Content drawer:** edit the idea, generate and inspect the brief, channel variants, creative direction, effort estimate, submit for review, approve and create a simulated handoff.

## API integration

The frontend uses the Symfony API as its source of truth:

- `GET/POST /api/content_ideas`
- `POST /api/workflow/weekly-plans`
- `GET /api/workflow/weekly-plans/{id}`
- `POST /api/workflow/content-ideas/{id}/brief`
- `GET /api/workflow/content-ideas/{id}/brief`
- `POST /api/workflow/content-ideas/{id}/review`
- `POST /api/workflow/content-ideas/{id}/approve`
- `POST /api/workflow/content-ideas/{id}/handoff`
- `PATCH /api/workflow/content-ideas/{id}`

The current weekly plan ID is kept in browser local storage to reopen the active plan after refresh. The backend currently exposes a plan-by-ID endpoint but no collection/history endpoint.

## Product boundaries

- AI suggestions do not approve content.
- Approval remains a human action and is bound to the exact brief version.
- The handoff is simulated. It persists a backend payload but does not contact or publish to social networks.
- Media references and booking URLs are placeholders; no media upload is implemented.
- Analytics, ROI, moderation, RAG and n8n automations are not presented as implemented frontend features.
