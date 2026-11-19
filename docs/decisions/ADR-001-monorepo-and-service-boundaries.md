# ADR-001: Monorepo and service boundaries

- Status: Accepted
- Date: 2026-11-19

## Context
The project combines a business application, an AI integration service and a frontend. The assignment values traceability and clear error handling more than infrastructure complexity.

## Decision
Use one Git repository with independently deployable application folders. Symfony owns all business invariants and persistence. FastAPI provides AI capabilities only. n8n orchestrates external/recurrent tasks but cannot bypass Symfony authorization or approval rules.

## Consequences
- One place for documentation and reproducible local setup.
- Independent dependencies and deployment boundaries.
- HTTP contracts are explicit between Symfony and FastAPI.
- Some operational complexity is deferred until the prototype demonstrates a need.
