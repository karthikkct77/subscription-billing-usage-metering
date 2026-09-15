You are continuing the Laravel Senior Developer take-home assignment:

"Subscription Billing & Usage-Metering System"

Current branch:
feature/usage-aggregation

Completed phases:

1. Project Foundation
2. Database Schema & Domain Foundation
3. High-throughput Idempotent Usage API

This phase focuses ONLY on asynchronous usage aggregation.

Assignment requirement:

"A queued, chunked job that aggregates a customer's daily usage and, at cycle end, generates the invoice with correct proration and overage math."

IMPORTANT:
In this phase, implement the DAILY USAGE AGGREGATION infrastructure only.

Do NOT implement final invoice generation or complete billing calculation yet.

Billing/proration will be implemented in the next phase after the aggregation foundation is correct.

---

1. INSPECT EXISTING IMPLEMENTATION

---

Before changing anything:

* Inspect existing usage_events schema.
* Inspect daily_usage schema.
* Inspect subscription/subscription_segments schema.
* Inspect existing queue configuration.
* Inspect existing models/services/actions.
* Inspect existing API and architecture conventions.
* Reuse existing implementation.
* Do not duplicate existing logic.

---

2. AGGREGATION ARCHITECTURE

---

The desired flow is:

Usage API
↓
usage_events
↓
queued aggregation job
↓
chunked processing
↓
daily_usage

The usage endpoint must remain lightweight.

Do NOT aggregate synchronously inside POST /usage.

---

3. QUEUED JOB

---

Create a Laravel queued job responsible for aggregating usage events.

Use a clear name such as:

AggregateDailyUsage

or an equivalent name consistent with the project.

The job must:

* be queueable
* be retry-safe
* process data in chunks
* avoid loading millions of rows into memory
* aggregate usage by customer/day
* update/create daily_usage records safely

Do not use:

UsageEvent::all()

or any approach that loads the complete dataset.

---

4. CHUNKING

---

Use an appropriate Laravel chunking strategy.

Prefer chunkById/lazyById or another primary-key-based approach where appropriate.

Explain why primary-key-based chunking is safer than offset pagination for large datasets.

The implementation must remain suitable for tens of millions of usage rows.

Do not use OFFSET-based pagination for the core aggregation scan.

---

5. AGGREGATION WINDOW

---

Do NOT blindly scan the entire usage_events table every time.

The job should operate on a defined aggregation window.

For example:

merchant/customer/date range

or another clearly scoped range that matches the domain.

Choose a practical approach and document the assumption.

The job must be able to process:

* a single customer/day
* a date range
* a batch/window of usage events

without requiring the entire usage table to be scanned.

---

6. DAILY AGGREGATION

---

Aggregate usage conceptually as:

merchant_id
customer_id
usage_date

SUM(units)

Example raw events:

Customer 101
2026-09-14:
10
20
5
15

Result:

daily_usage:
customer_id = 101
usage_date = 2026-09-14
total_units = 50

Use the existing unique constraint/design from the schema phase.

---

7. UPSERT / IDEMPOTENCY

---

Aggregation jobs may be retried.

The aggregation must therefore be safe to execute more than once.

Do NOT allow:

First run:
daily_usage = 50

Retry:
daily_usage = 100

The job must not double-count.

Choose an appropriate strategy such as:

* recompute the aggregate for the affected date/window and upsert the exact value

rather than blindly incrementing totals.

The database uniqueness constraint should be used as part of the guarantee.

Document the decision.

---

8. HANDLING LATE EVENTS

---

Think about usage events arriving late.

Example:

September 14 usage event arrives on September 16.

The aggregation strategy should allow the September 14 daily total to be corrected.

Do not assume all events arrive in perfect chronological order.

Document how late-arriving usage is handled.

---

9. TRANSACTION / CONSISTENCY

---

The update of daily_usage must be consistent.

Use appropriate database transactions or atomic upsert behavior.

Avoid unnecessarily long transactions around huge datasets.

Do not hold a transaction open while processing an entire month's data.

---

10. QUEUE RETRIES

---

Configure reasonable retry behavior for the aggregation job.

Consider:

* tries
* backoff
* timeout

Use values appropriate for the exercise.

Do not introduce unnecessary queue infrastructure.

If Redis is configured, use the existing queue configuration.

---

11. JOB DISPATCHING

---

Provide a clean mechanism to dispatch aggregation work.

Do not make the usage API synchronously perform aggregation.

For the exercise, a command/scheduler/manual dispatch mechanism may be used to trigger aggregation.

If creating an Artisan command is useful, use something conceptually like:

php artisan usage:aggregate

The command should support a practical date/window scope.

Do not create unnecessary CLI complexity.

---

12. AGGREGATION SERVICE

---

Keep aggregation logic outside the Job where appropriate.

Recommended conceptual architecture:

Command / Scheduler
↓
AggregateDailyUsage Job
↓
UsageAggregationService
↓
UsageEvent query
↓
DailyUsage upsert

The Job should orchestrate.
The service should contain the actual aggregation behavior.

Do not create repositories/interfaces unless genuinely useful.

---

13. BILLING SEGMENT AWARENESS

---

The current phase is NOT billing calculation.

However, the aggregation design must not destroy information needed for future billing.

Because subscriptions can change plans mid-cycle, make sure daily aggregation preserves enough information to determine the applicable subscription segment/plan when billing is later calculated.

If the existing schema associates usage events with subscription/segment, use that design correctly.

Do NOT calculate plan price or proration in this phase.

Document any assumption.

---

14. LARGE DATASET CONSIDERATIONS

---

This assignment explicitly mentions 50L+ usage-event rows.

The implementation should demonstrate awareness of:

* indexed filtering
* chunking
* queue workers
* bounded memory
* batch writes
* retry safety
* late-arriving events
* database write contention

Do not attempt to benchmark 50L rows locally.

Instead, explain in README why the implementation is designed for large volumes.

---

15. PERFORMANCE

---

Review the query strategy.

Avoid:

* N+1 queries
* loading individual usage events and then saving individual daily records one by one
* OFFSET pagination
* repeated full-table scans
* unnecessary model hydration

Prefer database-side aggregation where practical:

GROUP BY customer/date
SUM(units)

Then perform efficient bulk upserts.

Use Eloquent/query builder according to what provides the best clarity and performance.

For this high-volume path, using the query builder is acceptable if it is more efficient than hydrating thousands of models.

Document the trade-off.

---

16. TESTING

---

Add meaningful tests.

At minimum:

1. Aggregates multiple events for the same customer/day.
2. Creates a daily_usage record when none exists.
3. Updates/recomputes an existing daily_usage record correctly.
4. Running the same aggregation twice does not double-count.
5. Multiple customers are aggregated independently.
6. Different dates are aggregated independently.
7. Date/window filtering works.
8. Late-arriving usage can correct a previous daily total.
9. Chunked processing does not require loading all events into memory.
10. Queue job can be dispatched.
11. Job retry behavior does not corrupt daily totals.

If practical, add a test proving duplicate/repeated execution is idempotent.

Do not write tests that depend heavily on internal implementation details.

---

17. ARTISAN COMMAND / SCHEDULER

---

If you create an aggregation command, make it useful for manual execution.

Example conceptual usage:

php artisan usage:aggregate --date=2026-09-14

or:

php artisan usage:aggregate --from=2026-09-01 --to=2026-09-14

Choose the simplest useful interface.

If adding scheduler configuration, do not claim it is production-ready unless actually configured.

A daily scheduler can be documented as a future/production consideration if appropriate.

---

18. README

---

Update README with:

## Usage Aggregation Architecture

Explain:

usage_events
↓
queue
↓
chunked job
↓
database aggregation
↓
daily_usage

## Chunking Strategy

Explain:

* why chunkById/lazyById is used
* why OFFSET is avoided
* memory behavior

## Idempotent Aggregation

Explain:

* repeated jobs
* exact recomputation/upsert
* unique daily key

## Late Arriving Events

Explain how late events are handled.

## Queue Strategy

Explain:

* worker
* retries
* backoff
* failure behavior

## 50L+ Scalability

Explain how this design behaves with 50L+ raw usage rows.

Be honest about what is implemented and what would be improved at higher scale.

---

19. NO BILLING YET

---

DO NOT implement:

* invoice generation
* invoice items calculation
* base price calculation
* overage calculation
* proration
* upgrade/downgrade billing calculation
* projected revenue

These will be implemented in the next billing phase.

---

20. QUALITY CHECK

---

Before finishing:

Run:

php artisan test

Run relevant aggregation tests specifically.

Run:

php artisan route:list

Run the aggregation command against test/development data if applicable.

Run Pint if available.

Inspect:

git diff --stat
git status

Do NOT commit.
Do NOT push.
Do NOT switch branches.

---

## FINAL OUTPUT

Provide:

1. Job created
2. Service/action created
3. Command created, if any
4. Aggregation strategy
5. Chunking strategy
6. Idempotency strategy
7. Late-event handling
8. Query/performance approach
9. Queue retry configuration
10. Tests added
11. README updates
12. Commands/tests executed
13. Any assumptions or concerns

Remember:

This is a Senior Laravel interview assignment.

The goal is not to demonstrate the largest amount of code.

The goal is to demonstrate a deliberate, scalable, retry-safe, database-efficient aggregation architecture that can reasonably evolve toward 50L+ usage-event rows.
