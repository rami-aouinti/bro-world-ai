# Required acceptance scenarios

The workflow API is implemented in the Symfony backend. The following scenarios are the required verification checklist. Do not describe a scenario as passed until it has been run against the local stack and its response/database result has been checked.

| ID | Scenario | Expected result |
|---|---|---|
| AT-01 | Create a weekly plan with 5 distinct ideas, 3 priority and 2 reserve, each assigned to A or B with estimated minutes | HTTP 201; one plan and five slots persisted |
| AT-02 | Submit 4 priority slots, 1 reserve, or any count other than 3+2 | HTTP 400; no plan persisted |
| AT-03 | Assign Person A more than 90 minutes or Person B more than 150 minutes | HTTP 422 with planned, allowed and overrun minutes; no plan persisted |
| AT-04 | Generate a brief without a media reference or approved schedule | HTTP 400; no brief persisted |
| AT-05 | Submit for review without a brief or weekly-plan assignment | HTTP 400; lifecycle state unchanged |
| AT-06 | Approve content that is not in review | HTTP 409 |
| AT-07 | Handoff content without approval or media reference | HTTP 409/400; no handoff persisted |
| AT-08 | Approve the exact brief snapshot (copy, media reference, channels and schedule) and hand it off twice using the same idempotency key | First request creates one handoff; retry returns the same handoff ID with idempotentReplay=true |
| AT-09 | Reuse an idempotency key for a different content idea | HTTP 409 |
| AT-10 | Edit an approved idea using PATCH | State resets to draft, previous brief is deleted, approval is invalidated; new brief/review/approval required |
| AT-11 | Edit content after it has been handed off | HTTP 409; handed-off record remains immutable |

## Local verification commands

After pulling master and applying migrations:

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console doctrine:schema:validate
docker compose exec backend vendor/bin/phpunit
docker compose exec backend php bin/console debug:router
```

Create five ideas through POST /api/content_ideas, then use the workflow endpoints documented in apps/backend/README.md. Inspect persisted rows with the PostgreSQL container or the API read endpoints.

## Evidence status

- The first ContentIdea POST was successfully executed locally and returned HTTP 201.
- The first database migration (Version20261119130000) was successfully executed locally.
- The second workflow migration and workflow acceptance scenarios have been committed but still require local execution after pulling the latest master.
- The handoff is simulated only. No social network is contacted and no content is published.

Errors must never be reported as successful publication. Every test report must distinguish executed, simulated and planned behavior.
