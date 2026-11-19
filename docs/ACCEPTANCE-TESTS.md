# Required acceptance scenarios

These are the acceptance criteria for the Symfony workflow implementation. The initial AI-service health/generation tests are separate and do not claim that the full publishing workflow is already implemented.

| ID | Input | Expected result |
|---|---|---|
| AT-01 | Complete content version, media reference, channels, schedule and approval; run handoff twice | Exactly one simulated handoff; second run is an idempotent no-op |
| AT-02 | Missing approval OR missing media reference | Handoff rejected; no payload created |
| AT-03 | Edit text after approval | Approval becomes stale; new review is required |
| AT-04 | Planned workload exceeds Person A 90m or Person B 150m | Explicit warning or documented re-plan; capacity is never silently increased |

Every test report must distinguish executed, simulated and planned behavior. Errors must never be reported as successful publication.
