You are continuing the Laravel Senior Developer take-home assignment:

"Subscription Billing & Usage-Metering System"

Current branch:
feature/cache-dashboard

Completed phases:

1. Project Foundation
2. Database Schema & Domain Foundation
3. High-throughput Idempotent Usage API
4. Queued Daily Usage Aggregation
5. Billing Engine, Proration & Plan Changes

This phase implements the remaining major application requirements:

1. Plan/pricing caching
2. Cache invalidation strategy
3. Merchant dashboard
4. Dashboard query performance

Do NOT implement unrelated features.

---

1. INSPECT EXISTING IMPLEMENTATION

---

Before changing anything:

* Inspect Plan model and pricing fields.
* Inspect Merchant model.
* Inspect Customer model.
* Inspect Subscription and SubscriptionSegment models.
* Inspect DailyUsage.
* Inspect Invoice/Billing implementation.
* Inspect existing cache configuration.
* Inspect Redis configuration.
* Inspect existing API response conventions.
* Inspect existing service/action architecture.
* Inspect tests.

Reuse existing architecture.

Do not redesign completed modules unnecessarily.

---

2. PLAN / PRICING CACHE

---

The assignment requires:

"Cache plan/pricing lookups (Redis or array cache is fine for the exercise) and document your invalidation strategy."

Implement a dedicated cache approach for plan/pricing lookup.

Use Laravel's cache abstraction.

Prefer Redis if the existing environment supports it.

Do not hardcode Redis-specific implementation throughout the application.

Use Laravel Cache/Redis configuration appropriately.

---

3. CACHE KEY

---

Create a predictable cache key strategy.

For example:

plan:{plan_id}:pricing

or another clean convention.

The cached value should contain only the pricing/configuration information actually needed by billing.

Avoid caching entire Eloquent models if unnecessary.

The cached representation should be simple and serialization-friendly.

---

4. WHAT TO CACHE

---

Cache the values needed for pricing calculations, such as:

* plan id
* merchant id
* base price
* billing cycle
* included usage units
* overage rate

Do not cache unrelated customer/subscription information.

---

5. CACHE TTL

---

Choose a reasonable TTL.

Document the assumption.

Do not rely on TTL alone for correctness.

The application must explicitly invalidate the relevant cache when pricing changes.

---

6. CACHE INVALIDATION

---

This is a key interview requirement.

When a plan's pricing changes:

1. Update the database.
2. Invalidate the corresponding cache key.

Do not allow stale pricing to be used indefinitely.

Use a clean mechanism.

Possible approaches:

* service/action responsible for plan updates
* model observer
* domain event/listener

Choose the simplest approach that fits the existing architecture.

Do not create a complex event system just for demonstration.

---

7. HISTORICAL BILLING SAFETY

---

IMPORTANT:

Cache must NEVER become the source of truth for historical invoices.

The database/subscription-segment pricing snapshot remains authoritative for historical billing.

The cache is only a performance optimization for current plan/pricing lookups.

If Redis is unavailable:

* application should still be able to function using database fallback where practical.

Do not make billing correctness depend entirely on Redis.

Document this explicitly.

---

8. CACHE FAILURE HANDLING

---

Do not allow a cache outage to corrupt billing.

If cache lookup fails:

* fall back to database
* optionally repopulate cache

Do not throw a billing failure merely because Redis is unavailable unless there is a genuine infrastructure reason.

Keep this implementation simple.

---

9. PLAN PRICING SERVICE

---

Create a clean service/action if useful.

Conceptually:

PlanPricingService
↓
Cache lookup
↓
Cache hit → return pricing
↓
Cache miss → database lookup → cache → return

Do not put cache logic throughout multiple controllers/services.

Billing code should depend on a clean pricing interface/service.

Avoid repositories/interfaces unless genuinely needed.

---

10. CACHE TESTS

---

Add tests for:

1. Pricing is returned correctly.
2. First lookup populates cache.
3. Subsequent lookup uses cached pricing.
4. Updating plan pricing invalidates cache.
5. After invalidation, updated pricing is returned.
6. Cache miss falls back to database.
7. Historical billing does not depend on cached current pricing.

Use Laravel's cache testing/fake facilities where appropriate.

Do not make tests depend on an actual Redis server unless the project already requires Redis for tests.

---

11. MERCHANT DASHBOARD

---

Implement:

GET /api/v1/merchants/{id}/dashboard

Follow the project's existing API versioning convention.

The dashboard must return:

1. Top 5 customers by usage this month
2. Projected overage revenue for the current cycle
3. Customers whose usage dropped >50% month-over-month

Do NOT add unrelated dashboard metrics.

---

12. TENANT ISOLATION

---

The dashboard must be strictly scoped to the requested merchant.

A customer belonging to another merchant must never appear.

All dashboard queries must include merchant scope appropriately.

Do not load all merchants/customers and filter them in PHP.

Filtering should happen in the database.

---

13. TOP 5 CUSTOMERS BY USAGE THIS MONTH

---

Use daily_usage rather than raw usage_events.

Determine:

current calendar month
→ usage grouped by customer
→ SUM(total_units)
→ descending order
→ LIMIT 5

Return useful information such as:

* customer id
* customer name
* total usage units

Do not expose unnecessary fields.

Use database aggregation.

Do not load all customers and calculate totals in PHP.

---

14. CURRENT MONTH DEFINITION

---

Define "this month" consistently using the application timezone.

Do not depend on the server's arbitrary timezone.

Use the Laravel/application timezone already established by the project.

Document the assumption.

---

15. PROJECTED OVERAGE REVENUE

---

The dashboard must return:

"projected overage revenue for the current cycle"

Do NOT simply return already-billed overage.

This is a projection.

Design a reasonable calculation based on current cycle usage and remaining time in the cycle.

Document the exact assumption.

A reasonable approach is:

1. Determine current billing cycle.
2. Calculate usage accumulated so far.
3. Estimate cycle usage based on elapsed portion of the cycle.
4. Compare projected usage against included allowance.
5. Apply applicable overage pricing.
6. Sum projected overage across relevant active subscriptions.

IMPORTANT:

Plan changes mid-cycle must be considered.

If a subscription has multiple segments during the current cycle, do not blindly apply one current plan to the entire cycle.

Use the existing subscription-segment model and applicable pricing.

Do not make this calculation depend on raw usage_events if daily_usage can provide the necessary data.

---

16. PROJECTION ASSUMPTION

---

Document the projection formula clearly.

For example:

projected_cycle_usage =
usage_to_date / elapsed_cycle_days × total_cycle_days

Then:

projected_overage =
max(0, projected_usage - included_allowance)

Then:

projected_revenue =
projected_overage × overage_rate

However, because subscriptions can have multiple plan segments, adapt the formula appropriately.

Handle:

* very early cycle where elapsed days are small
* zero usage
* full cycle
* mid-cycle plan change

Avoid division by zero.

The goal is a reasonable business projection, not a machine-learning forecast.

---

17. CHURN RISK

---

Return customers whose usage dropped more than 50% month-over-month.

Compare:

current month usage
vs
previous month usage

Churn risk condition:

current_usage < previous_usage × 0.50

Consider carefully what to do when previous usage is zero.

Do not incorrectly label a customer with:

previous = 0
current = 0

as churn risk.

Document the assumption.

Return useful information such as:

* customer id
* customer name
* current month usage
* previous month usage
* percentage change

Calculate the percentage change safely.

---

18. DASHBOARD RESPONSE

---

Use a clean response structure.

Conceptually:

{
"success": true,
"data": {
"top_customers_by_usage": [],
"projected_overage_revenue": 0,
"churn_risk_customers": []
},
"message": "Dashboard retrieved successfully."
}

Use the existing response convention.

Do not expose unnecessary internal/database fields.

---

19. DASHBOARD ARCHITECTURE

---

Do not put all dashboard queries inside the controller.

Use a dedicated service/action.

Conceptually:

MerchantDashboardController
↓
MerchantDashboardService
├── topCustomersByUsage()
├── projectedOverageRevenue()
└── churnRiskCustomers()

Choose an appropriate architecture based on existing code.

Avoid creating excessive abstraction.

---

20. DASHBOARD PERFORMANCE

---

This endpoint may be queried frequently.

Requirements:

* Use daily_usage for historical usage.
* Use database-side aggregation.
* Use appropriate indexes.
* Avoid N+1 queries.
* Avoid loading unnecessary rows.
* Avoid iterating millions of usage events in PHP.
* Select only required columns.
* Keep tenant filtering inside SQL/database queries.

Consider whether small dashboard result sets can be cached.

Do not cache the entire dashboard indefinitely.

If you add dashboard caching, use a short TTL and document invalidation/expiry.

Plan/pricing cache is mandatory.
Dashboard caching is optional.

Do not add dashboard caching if it complicates correctness unnecessarily.

---

21. QUERY REVIEW

---

Review the generated SQL/query patterns.

Think about:

Top customers:
GROUP BY customer
SUM daily usage
ORDER BY total usage DESC
LIMIT 5

Churn:
current month aggregate
previous month aggregate
compare results

Do not execute one query per customer.

Use aggregate queries and joins/subqueries appropriately.

If two aggregate queries are clearer and more maintainable than one highly complex SQL query, prefer clarity.

This is an interview assignment; readability matters.

---

22. DASHBOARD TESTS

---

Add feature/integration tests for:

1. Dashboard returns HTTP 200.
2. Only requested merchant data is returned.
3. Top 5 customers are correctly ordered by usage.
4. More than 5 customers returns only top 5.
5. Current month usage is calculated correctly.
6. Projected overage revenue is calculated correctly.
7. Plan pricing is applied correctly.
8. Current-cycle plan changes are handled.
9. Zero usage does not produce incorrect overage.
10. Churn customer with >50% usage drop is returned.
11. Exactly 50% drop follows the documented boundary rule.
12. Less than 50% drop is not returned.
13. Previous month zero usage is handled safely.
14. Customers from another merchant never appear.
15. Empty merchant data returns a valid empty response.

Use realistic test fixtures.

Do not rely on arbitrary current system dates in tests.

Use fixed dates/time travel where appropriate.

---

23. CACHE + DASHBOARD INTEGRATION

---

Ensure billing and dashboard pricing lookup uses the pricing service where appropriate.

Do not duplicate cache lookup code.

However:

Historical invoice calculations must continue to use historical segment pricing and must not accidentally switch to current cached plan pricing.

---

24. README

---

Update README with:

## Pricing Cache

Document:

* cache key
* cached values
* TTL
* cache hit/miss behavior
* database fallback
* invalidation strategy
* Redis dependency/fallback

## Dashboard

Document:

* top customer query
* projected overage formula
* churn-risk formula
* tenant isolation
* performance considerations

## Dashboard Assumptions

Explicitly document:

* timezone
* current month definition
* projection formula
* zero previous-month usage behavior
* 50% boundary behavior
* plan-change handling

---

25. QUALITY

---

Before finishing:

Run:

php artisan test

Run the cache tests specifically.

Run dashboard feature tests specifically.

Run Pint if available.

Run:

php artisan route:list

Review query logic and indexes.

Inspect:

git diff --stat
git status

Do NOT commit.
Do NOT push.
Do NOT switch branches.

---

## FINAL OUTPUT

Provide:

1. Cache implementation
2. Cache key strategy
3. TTL
4. Cache invalidation strategy
5. Cache fallback behavior
6. Dashboard endpoint
7. Top 5 usage implementation
8. Projected overage formula
9. Churn-risk formula
10. Tenant isolation approach
11. Query/performance strategy
12. Tests added
13. README updates
14. Commands/tests executed
15. Assumptions and edge cases

IMPORTANT:

This is a Senior Laravel Developer interview assignment.

Prioritize:

* correctness
* SQL/database efficiency
* tenant isolation
* deterministic calculations
* cache correctness
* clean service boundaries
* testability
* clear documented assumptions

Do not over-engineer.
