# Operations, analytics and future scope

This document describes how the prototype could be operated and extended without claiming that external integrations are already implemented.

## Weekly operations
- Start each week with five candidate ideas: three priorities and two reserves.
- Check assigned minutes against Person A (90 min) and Person B (150 min) capacities.
- Keep a reserve slot available for changes or urgent topics.
- Review the generated brief, source context, media reference, schedule and channel variants.
- A human approves the exact content version. Any edit invalidates the approval and requires another review.
- The handoff is currently simulated and idempotent.

The n8n weekly workflow produces a text overview and an unsent email draft. It does not send mail.

## Analytics and ROI — planned
A future analytics integration could import per-post reach, impressions, engagement, link clicks, bookings and spend from approved platform exports or APIs. Every metric should retain its source, retrieval timestamp, time window and attribution limitations.

Possible metrics:
- Engagement rate = (likes + comments + shares + saves) / impressions.
- Click-through rate = link clicks / impressions.
- Booking conversion = attributed bookings / tracked link clicks.
- Cost per booking = campaign spend / attributed bookings.
- Return on ad spend = attributed revenue / advertising spend.

These metrics are not currently calculated. Organic and paid campaigns must be distinguished, and attribution should not be presented as causal proof.

## Comment moderation — planned, human in the loop
A future workflow could classify comments into questions, service requests, complaints, spam and potentially harmful content. The AI may suggest a response or flag a case, but it must not automatically send replies, hide comments or make account actions. A human moderator reviews the original comment, suggested response and context before taking action. Sensitive or safety-related cases should be escalated.

## Operations and reliability — planned
- Monitor FastAPI, Symfony, PostgreSQL, Ollama and n8n health.
- Add structured logs with workflow execution IDs and content IDs.
- Add retry limits and dead-letter handling for asynchronous operations.
- Store n8n encryption keys and credentials outside Git.
- Add authentication and authorization between services before any production deployment.
- Add retention policies for uploaded media, prompts and generated content.
- Add concurrent idempotency tests and browser end-to-end tests.

## Explicit prototype boundaries
No real social platform publishing, media upload, booking integration, SMTP delivery, analytics ingestion, ROI calculation or comment moderation is connected in this repository.
