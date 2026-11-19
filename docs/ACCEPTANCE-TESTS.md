# Required acceptance scenarios

The Symfony workflow API has an automated functional acceptance suite. The scenarios below were executed locally on 2026-11-19 against the dedicated test database `bro_world_ai_test1`.

## Results

<badge>13/13 PHPUnit tests passed</badge>

- 2 unit tests passed.
- 11 functional acceptance tests passed (AT-01 through AT-11).
- 97 assertions, 0 failures.

| ID | Scenario | Expected result | Result |
|---|---|---|---|
| AT-01 | Create a weekly plan with 5 distinct ideas, 3 priority and 2 reserve, each assigned to A or B with estimated minutes | HTTP 201; one plan and five slots persisted | Passed |
| AT-02 | Submit an invalid priority/reserve distribution or a slot count other than five | HTTP 400; no plan persisted | Passed |
| AT-03 | Assign Person A more than 90 minutes or Person B more than 150 minutes | HTTP 422 with planned, allowed and overrun minutes; no plan persisted | Passed |
| AT-04 | Generate a brief without a media reference | HTTP 400; no brief persisted | Passed |
| AT-05 | Submit for review without a brief or weekly-plan assignment | HTTP 400; lifecycle state unchanged | Passed |
| AT-06 | Approve content that is not in review | HTTP 409 | Passed |
| AT-07 | Handoff content without approval | HTTP 409; no handoff persisted | Passed |
| AT-08 | Approve the exact brief snapshot and hand it off twice using the same idempotency key | First request creates one handoff; retry returns the same handoff ID with idempotentReplay=true | Passed |
| AT-09 | Reuse an idempotency key for a different content idea | HTTP 409 | Passed |
| AT-10 | Edit an approved idea using PATCH | State resets to draft, previous brief is deleted and approval is invalidated | Passed |
| AT-11 | Edit content after it has been handed off | HTTP 409; handed-off record remains immutable | Passed |

## Reproduce locally

Run from the repository root:

```bash
docker compose exec \
  -e APP_ENV=test \
  -e TEST_TOKEN=1 \
  backend vendor/bin/phpunit --testdox
```

The explicit environment variables are important: `APP_ENV=test` activates Symfony's test configuration, and `TEST_TOKEN=1` selects the isolated `bro_world_ai_test1` database.

The test suite truncates its workflow tables before each functional test. Ensure the test environment and test database suffix are used; do not run these tests against the development database.

## Scope and limitations

- These tests verify the Symfony backend workflow and persistence behavior. They are not end-to-end browser tests.
- Handoff is simulated only. No social network is contacted and no content is published.
- The suite verifies sequential retries with the same idempotency key. It does not currently prove race-free behavior under simultaneous concurrent requests.
- AI generation, media upload, notifications, analytics and ROI are not covered by these acceptance tests.
