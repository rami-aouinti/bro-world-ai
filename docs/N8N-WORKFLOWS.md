# n8n workflow exports and setup

Import the JSON files from the n8n UI at http://localhost:5678. Use the workflow menu and choose Import from File (wording may vary by n8n version).

No credentials are embedded. URLs use Docker-internal service names.

## Included workflows

| Export | Trigger | Responsibility |
|---|---|---|
| weekly-ai-planning.json | Monday 08:00 Europe/Berlin and manual webhook | Generate five ideas, persist them in Symfony, create a 3-priority / 2-reserve plan with A=60/90 and B=110/150 minutes, and return a weekly text overview plus an unsent email draft. |
| ai-content-production.json | Webhook | Ask Symfony to create a brief; Symfony calls FastAPI and persists the result. |
| approval-email-draft.json | Webhook | Prepare an unsent email draft as JSON. It cannot approve content. |
| approved-handoff.json | Webhook | Request handoff with an idempotency key; Symfony enforces approval and media checks. |

## Activate and test
1. Start the stack: docker compose up -d --build.
2. Open n8n and complete its initial owner setup if prompted.
3. Import the four JSON files.
4. Save the workflows. Keep the weekly schedule inactive until ready.
5. For a first weekly run, use the manual webhook URL shown by n8n in test mode, or activate the Monday schedule.
6. Confirm five ideas and one weekly plan appear in Symfony.
7. Trigger AI content production with ideaId, mediaReference and scheduledAt.
8. Trigger approval-email-draft with ideaId, title and optional synthetic reviewerEmail.
9. Approve the exact brief in the frontend before triggering approved-handoff. Include ideaId and a stable idempotencyKey.

Example content-production body:

§§§json
{
  "ideaId": "REPLACE_WITH_CONTENT_ID",
  "mediaReference": "media://sample/summer-eyewear-hero",
  "scheduledAt": "2026-12-01T10:00:00+01:00"
}
§§§

Example handoff body:

§§§json
{
  "ideaId": "REPLACE_WITH_APPROVED_CONTENT_ID",
  "idempotencyKey": "demo-handoff-001"
}
§§§

## Important behavior
- Weekly workflow creates five idea records before creating the plan. Do not run it more than once for the same week: the backend rejects a second plan for that Monday, but extra ideas created before the rejection remain in the library.
- The sample allocation assigns the first two ideas to Person A (30+30 minutes) and the other three to Person B (40+40+30 minutes). This is deterministic sample allocation, not an AI staffing decision.
- Approval workflow returns an unsent email draft. No SMTP credentials or real delivery are configured.
- Handoff does not publish anything. It calls Symfony's simulated handoff endpoint.
- Failed or unapproved handoff is rejected by Symfony; n8n must not bypass approval.
