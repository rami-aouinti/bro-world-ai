# n8n workflow exports

Import the JSON exports into the local n8n instance. See docs/N8N-WORKFLOWS.md for setup, triggers, payloads and limitations.

Exports:
- weekly-ai-planning.json
- ai-content-production.json
- approval-email-draft.json
- approved-handoff.json

The workflows use Docker service names (ai-service and backend) and contain no credentials. n8n is an orchestrator only. Symfony remains the source of truth for capacity, approval and handoff. The email is not sent and social publishing is simulated.
