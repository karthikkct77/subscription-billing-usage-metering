We are now implementing the final integration and end-to-end testing phase for the Subscription Billing & Usage-Metering System.

Current branch:
feature/integration-tests

IMPORTANT:
- Do not redesign the existing architecture.
- Do not replace working implementations.
- Do not modify completed modules unnecessarily.
- Do not invent new business requirements.
- Reuse the existing services, actions, jobs, models, repositories, routes, database schema, cache strategy, and billing logic.
- Do not commit or push Git changes.
- Follow the existing project coding conventions.
- Keep the implementation interview-quality and production-oriented.

OBJECTIVE:

Create comprehensive integration/end-to-end test coverage proving that the complete subscription billing and usage-metering workflow works correctly across the modules already implemented.

==================================================
1. COMPLETE END-TO-END FLOW TEST
==================================================

Create an integration test covering the complete lifecycle:

Merchant
  ↓
Plan
  ↓
Customer
  ↓
Subscription
  ↓
Usage Events
  ↓
Daily Usage Aggregation
  ↓
Billing
  ↓
Invoice + Invoice Items

The test should verify:

1. Create a merchant.
2. Create a plan with:
   - base price
   - billing cycle
   - included usage units
   - overage rate
3. Create a customer belonging to the merchant.
4. Create an active subscription.
5. Create multiple usage events.
6. Run the existing aggregation job/service.
7. Verify daily_usage records.
8. Run the existing billing service/job/command.
9. Verify invoice creation.
10. Verify invoice items.
11. Verify:
    - base charge
    - included usage
    - actual usage
    - overage units
    - overage amount
    - total invoice amount.

Use the application's existing APIs/services rather than duplicating business logic inside tests.

==================================================
2. USAGE API INTEGRATION TEST
==================================================

Add integration coverage for POST /api/v1/usage.

Verify:

- valid usage event succeeds
- invalid payload fails validation
- unknown customer is rejected
- customer belonging to another merchant is rejected
- customer without valid subscription is rejected if that is the existing business rule
- valid idempotency key creates exactly one usage event
- retry with same idempotency key returns the existing result
- same idempotency key with different payload is rejected
- concurrent duplicate requests cannot create duplicate usage events
- rate limiting is enforced.

Do not weaken existing API behavior just to make tests pass.

==================================================
3. AGGREGATION INTEGRATION TESTS
==================================================

Verify the existing usage aggregation implementation.

Cover:

- multiple events for same customer/day are summed
- multiple customers are aggregated independently
- duplicate/retried events do not double count
- aggregation can safely be run more than once
- late-arriving usage events can be reflected after re-running aggregation
- chunked processing still produces correct totals
- merchant/customer/date boundaries are respected.

Verify the daily_usage unique logical key and upsert/recompute behavior.

==================================================
4. BILLING INTEGRATION TESTS
==================================================

Cover realistic billing scenarios.

Scenario A:
Normal subscription within included usage.

Expected:
- base charge only
- no overage.

Scenario B:
Usage exceeds included allowance.

Expected:
- base charge
- overage units
- overage charge
- correct invoice total.

Scenario C:
Mid-cycle upgrade.

Expected:
- before change → original plan pricing
- after change → new plan pricing
- both periods are represented by subscription segments
- correct proration
- correct included usage allocation
- correct overage calculation.

Scenario D:
Mid-cycle downgrade.

Expected:
- same segment-level pricing principle
- historical pricing remains correct
- no retroactive repricing.

Scenario E:
Billing is executed twice for the same billing period.

Expected:
- no duplicate invoice
- billing remains idempotent.

Scenario F:
No usage during cycle.

Expected:
- base/prorated subscription charge according to existing implementation
- zero overage.

==================================================
5. PRORATION EDGE CASES
==================================================

Add tests for:

- subscription starts on first day of billing cycle
- subscription starts mid-cycle
- plan changes exactly at a segment boundary
- very short segment
- final day of cycle
- multiple segments within one billing cycle.

Use the existing documented proration assumptions.

Do not introduce a new proration formula.

==================================================
6. CACHE INTEGRATION TESTS
==================================================

Verify the existing PlanPricingService/cache implementation.

Cover:

- first pricing lookup reads from database
- subsequent lookup uses cache
- cache contains expected pricing data
- plan pricing update invalidates the relevant cache key
- next lookup retrieves fresh pricing
- cache failure/unavailability falls back safely to database
- historical billing does NOT depend on the current pricing cache.

Use the existing cache abstraction and test-friendly cache configuration.

==================================================
7. DASHBOARD INTEGRATION TESTS
==================================================

Test:

GET /api/v1/merchants/{id}/dashboard

Verify:

1. Top 5 customers by usage for current month.
2. Correct descending usage order.
3. Only the requested merchant's customers are included.
4. Current-cycle projected overage revenue is calculated correctly.
5. Customers whose usage dropped by more than 50% month-over-month are identified correctly.
6. Customer with zero previous-month usage does not create a false >50% drop.
7. Dashboard does not produce cross-tenant data leakage.
8. Empty data produces a valid safe response.

Use realistic daily_usage data instead of manually mocking final dashboard results.

==================================================
8. MULTI-TENANT ISOLATION TEST
==================================================

Create at least one explicit cross-tenant test.

Example:

Merchant A:
- customer A
- plan A
- subscription A
- usage A

Merchant B:
- customer B
- plan B
- subscription B
- usage B

Verify:

- Merchant A dashboard never includes Merchant B data.
- Merchant A cannot create usage for Merchant B customer.
- Merchant A billing never processes Merchant B subscription.
- Merchant A queries remain tenant scoped.

This test is important because multi-tenancy is a core requirement.

==================================================
9. DATABASE / MIGRATION TEST
==================================================

Ensure the full test suite can run against a clean database.

Verify:

- migrations execute successfully from zero
- foreign keys are valid
- unique constraints work
- important indexes exist
- factories/seeders required by tests are reliable.

Do not add unnecessary indexes just for tests.

==================================================
10. QUEUE / JOB TESTING
==================================================

Test queued workflows without making production code synchronous.

Use Laravel's queue testing facilities where appropriate.

Verify:

- aggregation job is dispatched correctly
- billing job is dispatched correctly if applicable
- jobs contain the correct tenant/date/subscription context
- duplicate job execution remains safe because underlying operations are idempotent.

Do not remove queue behavior simply to make tests easier.

==================================================
11. TEST QUALITY REQUIREMENTS
==================================================

Follow these principles:

- Tests should verify business behavior, not implementation details.
- Avoid excessive mocking.
- Prefer database-backed integration tests for billing, aggregation, dashboard, and tenant isolation.
- Use factories where available.
- Keep each test focused.
- Use descriptive test names.
- Do not duplicate production formulas inside tests unnecessarily.
- Assertions should clearly explain expected financial values.
- Keep tests deterministic.
- Avoid dependence on real Redis/external services unless the existing project explicitly requires it.

==================================================
12. FULL TEST SUITE VALIDATION
==================================================

After implementing the tests, run:

php artisan test

If the project uses PHPUnit directly, also ensure:

./vendor/bin/phpunit

Run the formatter/linter already configured by the project.

Fix only genuine application/test issues discovered during validation.

Do not hide failures by weakening assertions.

==================================================
13. REVIEW EXISTING REQUIREMENTS
==================================================

Before finishing, review the complete assignment requirements and verify that the implementation has test coverage for:

- normalized schema
- 50L+ usage-event scalability considerations
- indexing
- POST /usage
- idempotency
- high-throughput design
- queued aggregation
- chunked processing
- billing
- proration
- overage
- mid-cycle upgrade
- mid-cycle downgrade
- historical pricing
- cache
- cache invalidation
- dashboard
- top 5 usage customers
- projected overage revenue
- >50% MoM usage drop
- rate limiting
- multi-tenancy
- integration tests.

Do not implement unrelated features.

==================================================
14. README TESTING SECTION
==================================================

Update README with a concise "Testing Strategy" section explaining:

- unit vs integration tests
- billing test coverage
- aggregation test coverage
- idempotency testing
- multi-tenant isolation testing
- cache testing
- dashboard testing
- queue testing
- how to run the complete test suite.

Also mention any realistic limitations or assumptions.

==================================================
15. FINAL OUTPUT FROM YOU
==================================================

After implementation, report:

1. Files created/modified.
2. Integration tests added.
3. Important scenarios covered.
4. Full test command executed.
5. Final test result.
6. Any issues discovered and fixed.
7. Any remaining known limitations.

Do not commit or push changes.

IMPORTANT:
Do not redesign existing completed modules.
Do not modify the database schema unless absolutely required to make the existing implementation correct.
Do not introduce unnecessary packages.
Do not add speculative features.
Focus on proving the existing architecture works end-to-end.