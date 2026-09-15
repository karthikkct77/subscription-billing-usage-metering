You are continuing the Laravel Senior Developer take-home assignment:

"Subscription Billing & Usage-Metering System"

Current branch:
feature/usage-api

Previous phases completed:

1. Project Foundation
2. Database Schema & Domain Foundation

This phase is ONLY for implementing the Usage Ingestion API.

Assignment requirement:

"POST /usage endpoint to record a usage event. It should be safe to call at high throughput and idempotent (a retried request must not double-count)."

Implement this requirement in a production-minded but appropriately scoped way.

IMPORTANT:
Do NOT implement:

* billing calculation
* invoice generation
* daily aggregation jobs
* dashboard
* plan caching
* subscription upgrade/downgrade workflow
* complex reporting

Those belong to later phases.

---

1. INSPECT EXISTING IMPLEMENTATION

---

Before changing anything:

* Inspect existing models and migrations from the database-schema phase.
* Inspect existing API response conventions.
* Inspect exception handling.
* Inspect route versioning.
* Inspect Laravel version and database driver.
* Reuse existing project conventions.

Do not recreate or replace working foundation code.

---

2. ENDPOINT

---

Implement:

POST /api/v1/usage

The endpoint should record a usage event.

Use the existing project API versioning convention if it differs from /api/v1.

Do not introduce unnecessary endpoint variants.

---

3. REQUEST PAYLOAD

---

Use a Form Request for validation.

The request should contain only fields genuinely required by the domain.

A reasonable payload is:

{
"merchant_id": 1,
"customer_id": 101,
"occurred_at": "2026-09-14T10:30:00Z",
"units": 5,
"idempotency_key": "evt_abc123"
}

Validate:

merchant_id:

* required
* valid existing merchant

customer_id:

* required
* valid existing customer

occurred_at:

* required
* valid date/time

units:

* required
* positive numeric/integer according to the database/domain decision
* must not allow zero or negative usage

idempotency_key:

* required
* string
* sensible maximum length
* must match the database uniqueness design

Do not add arbitrary fields.

---

4. TENANT / CUSTOMER OWNERSHIP VALIDATION

---

A critical requirement:

A customer must belong to the merchant specified in the request.

Do NOT simply validate:

exists:customers,id

and then trust the client.

The request must prevent cross-tenant/customer mismatch.

For example:

merchant_id = 1
customer_id = customer belonging to merchant 2

must fail validation/business validation.

Use an appropriate Laravel validation or service-level approach.

Do not duplicate tenant validation logic across controllers.

---

5. SUBSCRIPTION VALIDATION

---

Before recording usage, determine what domain validation is actually required based on the schema.

A usage event should belong to a valid customer/merchant context.

If the current database design requires an active subscription or subscription segment to associate the event correctly, validate that appropriately.

IMPORTANT:

Do not invent billing rules here.

The usage ingestion API should remain lightweight.

Do not calculate:

* overage
* invoice amount
* billing period charges
* proration

Those belong to the billing layer.

---

6. IDEMPOTENCY

---

This is one of the most important requirements.

A retry with the same idempotency key must NOT create another usage event.

Do NOT implement this only as:

1. SELECT to see whether key exists
2. INSERT if not exists

That is vulnerable to race conditions.

The database unique constraint created in the previous phase is the final protection.

Implement application logic that handles the duplicate-key case safely.

Expected behavior:

First request:

POST /usage
idempotency_key = evt_abc123

→ usage event created.

Retry:

POST /usage
idempotency_key = evt_abc123

→ must not create another event.

Choose a clean API response strategy for retries.

A reasonable behavior is to return the existing usage event as an idempotent success response rather than treating a legitimate retry as a server error.

Document this decision.

IMPORTANT:

If the same idempotency key is reused with materially different payload data, do not silently accept the conflicting request.

For example:

First request:
evt_abc123
units = 5

Retry:
evt_abc123
units = 100

This should be detected as an idempotency conflict.

Design a lightweight mechanism to detect this safely.

Do not over-engineer a separate idempotency framework unless it is genuinely justified.

---

7. CONCURRENCY

---

Think about concurrent requests using the same idempotency key.

Example:

Request A:
evt_abc123

Request B:
evt_abc123

arrive at almost the same time.

The implementation must rely on the database uniqueness guarantee rather than application-level locking alone.

Use appropriate transaction/error handling.

Do not introduce distributed locks for this simple requirement.

---

8. ARCHITECTURE

---

Do NOT put the usage creation workflow entirely inside the controller.

Use clear separation:

Controller
↓
Form Request
↓
Action/Service
↓
Transaction/persistence
↓
UsageEvent

Choose either an Action or Service based on the existing project architecture.

Keep it simple.

Example conceptual structure:

UsageController
UsageRequest
RecordUsageAction

Do not create repositories/interfaces just for ceremony.

---

9. TRANSACTION

---

Use a database transaction where appropriate.

The usage creation operation should be atomic.

Do not perform unnecessary external operations inside the transaction.

The endpoint should remain fast.

---

10. HIGH-THROUGHPUT CONSIDERATIONS

---

This endpoint may receive high write volume.

Design it accordingly.

Do:

* lightweight validation
* indexed lookups
* database uniqueness for idempotency
* minimal joins
* minimal queries
* atomic insert
* appropriate transaction scope

Do NOT:

* calculate billing synchronously
* aggregate millions of records synchronously
* calculate monthly usage
* generate invoices
* perform heavy reporting queries
* dispatch unnecessary synchronous work

The eventual aggregation will happen asynchronously.

Add a README section:

## Usage Ingestion Scalability

Explain why the endpoint is intentionally lightweight.

Mention:

* indexed lookups
* database-level idempotency
* asynchronous aggregation
* queue processing
* potential future load balancing/database scaling

---

11. RATE LIMITING

---

The assignment requires basic rate-limiting on the usage endpoint.

Implement a dedicated rate limiter for:

POST /api/v1/usage

Do NOT globally throttle all API routes.

Choose a reasonable limit for the exercise and document the assumption.

The rate limit should ideally be scoped by merchant/client identity rather than treating every request globally.

Because authentication may not yet exist, use the safest available request identifier appropriate to the current assignment architecture.

Do not build a full authentication system solely for rate limiting.

Document the limitation and how this would evolve in production.

---

12. API RESPONSE

---

Use the response convention established in the foundation phase.

Successful new event:

HTTP 201

Example conceptual response:

{
"success": true,
"data": {
"id": 123,
"merchant_id": 1,
"customer_id": 101,
"occurred_at": "...",
"units": 5,
"idempotency_key": "evt_abc123"
},
"message": "Usage recorded successfully."
}

Successful idempotent retry may return:

HTTP 200

with the existing usage event.

Do not expose unnecessary database fields.

---

13. ERROR HANDLING

---

Handle:

* invalid merchant
* invalid customer
* customer/merchant mismatch
* invalid units
* invalid timestamp
* invalid idempotency key
* duplicate/conflicting idempotency request
* unexpected database failures

Use appropriate HTTP status codes.

Do not expose SQL errors or stack traces.

---

14. LOGGING

---

Add useful structured logging only where appropriate.

For example, unexpected usage-ingestion failures may log:

* merchant identifier
* idempotency key
* exception context

Do NOT log sensitive payloads unnecessarily.

Do not add excessive logs for every successful usage event if that would create a high-volume logging problem.

Explain the logging trade-off briefly in README if useful.

---

15. TESTS

---

This phase MUST include meaningful feature/integration tests.

At minimum test:

1. Valid usage event returns 201.
2. Usage event is persisted.
3. Invalid merchant is rejected.
4. Invalid customer is rejected.
5. Customer belonging to another merchant is rejected.
6. Zero usage is rejected.
7. Negative usage is rejected.
8. Invalid timestamp is rejected.
9. Missing idempotency key is rejected.
10. Same idempotency key does not create a duplicate.
11. Retrying the same idempotency key returns the existing event appropriately.
12. Reusing the same idempotency key with different usage data is rejected as a conflict.
13. Rate limiting is enforced.
14. Important response structure is correct.

If practical, add a concurrency-oriented test around duplicate idempotency handling.

Do not write brittle tests that depend on implementation internals.

---

16. DATABASE QUERY EFFICIENCY

---

Review the queries generated by this endpoint.

Avoid unnecessary model loading.

Do not use:

* N+1 queries
* full-table scans
* unnecessary relationship loading

Use the indexes created in the database-schema phase.

Keep the request path efficient.

---

17. DOCUMENTATION

---

Update README with:

## Usage API

Include:

* endpoint
* request example
* response example
* validation rules
* idempotency behavior
* rate limiting assumption

## Idempotency

Explain:

* database unique constraint
* retry behavior
* conflict behavior
* concurrency handling

## High Throughput

Explain why heavy work is intentionally not performed inside the request.

Do not claim production-scale guarantees that have not actually been implemented.

---

18. CODE QUALITY

---

Follow Laravel conventions.

Use:

* Form Requests
* API Controllers
* Actions/Services where useful
* Eloquent
* database transactions where appropriate
* feature tests

Avoid:

* fat controllers
* unnecessary abstractions
* static global state
* duplicated validation logic
* raw SQL unless genuinely justified

Run Laravel Pint if available.

---

19. VERIFICATION

---

Before finishing:

Run:

php artisan test

Run relevant feature tests specifically.

Run:

php artisan route:list

Run Pint if available.

Inspect:

git diff --stat
git status

Do NOT commit.
Do NOT push.
Do NOT switch branches.

---

## FINAL OUTPUT

Provide a concise implementation summary:

1. Endpoint created
2. Request validation
3. Tenant/customer validation
4. Idempotency implementation
5. Conflict behavior
6. Concurrency handling
7. Rate limiting
8. Architecture/classes created
9. Tests added
10. README updates
11. Commands/tests executed
12. Any assumptions or concerns

Remember:

This is a Senior Laravel Developer interview assignment.

Prioritize correctness, simplicity, database guarantees, high-throughput thinking, and clean separation of concerns over unnecessary abstraction.
