You are continuing the Laravel Senior Developer take-home assignment:

"Subscription Billing & Usage-Metering System"

Current branch:
feature/database-schema

The project foundation has already been implemented and pushed in the previous branch.

This phase is ONLY for designing and implementing the database/domain schema.

IMPORTANT:
Do NOT implement the usage API, queue jobs, aggregation logic, billing calculation, dashboard, caching, or business workflows yet.

GOAL:
Design a normalized, indexed database schema that correctly supports:

* multi-tenant merchants
* plans
* customers
* subscriptions
* mid-cycle plan upgrades/downgrades
* high-volume usage events
* daily usage aggregation
* invoices and invoice items

The schema must be designed with 50L+ usage-event rows in mind.

---

1. FIRST INSPECT THE EXISTING PROJECT

---

Before changing anything:

* Inspect Laravel version and database driver.
* Inspect existing migrations/models.
* Inspect project conventions from Prompt 1.
* Reuse existing conventions.
* Do not unnecessarily modify unrelated foundation code.

Do not assume PostgreSQL if the project is configured for another supported database.
Use the actual configured database driver and make reasonable choices compatible with it.

---

2. DOMAIN ENTITIES

---

Implement the following core entities.

MERCHANT

Represents a SaaS tenant.

Suggested fields:

* id
* name
* status
* timestamps

Use an appropriate status representation consistent with Laravel conventions.

PLAN

A merchant can define one or more plans.

Required concepts:

* merchant ownership
* name
* base price
* billing cycle
* included usage units
* overage rate per unit
* active/inactive state
* timestamps

Important:
Money must NOT use floating-point columns.

Use an appropriate precise representation such as:

* decimal for monetary values
  OR
* integer minor units

Choose one approach consistently and document the decision.

Do not invent unnecessary plan fields.

CUSTOMER

Belongs to a merchant.

Required concepts:

* merchant ownership
* customer identity/basic details
* active/inactive state where useful
* timestamps

A customer must never accidentally belong to another merchant's data.

SUBSCRIPTION

Represents a customer's subscription.

Required concepts:

* merchant
* customer
* current/initial plan relationship
* subscription start
* billing cycle boundaries
* status
* timestamps

The design must support historical plan changes.

Do not rely only on a mutable `plan_id` on subscriptions for historical billing.

---

3. SUBSCRIPTION SEGMENTS

---

This is a critical part of the assignment.

A customer may upgrade or downgrade their plan in the middle of a billing cycle.

Example:

Billing cycle:
September 1 → September 30

Plan A:
September 1 → September 15

Plan B:
September 16 → September 30

Usage before September 16 must use Plan A pricing.

Usage after September 16 must use Plan B pricing.

Design a `subscription_segments` table to preserve this history.

It should contain enough information to determine:

* subscription
* plan
* segment start
* segment end
* pricing applicable to that segment

IMPORTANT DESIGN DECISION:

Do not make historical billing dependent on the current mutable plan pricing.

Consider whether the segment should snapshot the applicable pricing values.

For example, if a plan's pricing is later edited, previously generated billing should remain deterministic.

Choose the safest normalized design and document the reasoning.

The schema must prevent overlapping segments for the same subscription as far as practical within the selected database.

---

4. USAGE EVENTS

---

Create a usage_events table designed specifically for high write volume.

Required concepts:

* merchant
* customer
* subscription or subscription segment where appropriate
* event timestamp/date
* usage units
* idempotency key
* timestamps

IMPORTANT:

The future endpoint will receive retried requests.

The database must provide a reliable uniqueness guarantee so the same idempotency key cannot create duplicate usage.

Do NOT rely only on application-level "check then insert".

Use an appropriate unique constraint/index.

Think carefully about the scope of idempotency.

For example, determine whether the uniqueness should be globally unique or scoped to a merchant.

Document the decision.

---

5. DAILY USAGE

---

Create a daily_usage table for pre-aggregated usage.

Purpose:

Instead of repeatedly scanning millions of raw usage events for dashboard/billing queries, daily totals can be stored.

Required concepts:

* merchant
* customer
* date
* total usage units
* appropriate subscription/segment relationship if needed for correct billing

Create a uniqueness constraint that prevents duplicate daily aggregate rows for the same logical dimension.

The design must support efficient:

* customer/day lookup
* monthly usage
* billing-cycle aggregation
* merchant dashboard aggregation

---

6. INVOICES

---

Create invoice-related tables needed for the assignment.

At minimum:

invoices
invoice_items

Invoice should support:

* merchant
* customer
* subscription
* billing period
* invoice status
* subtotal/total amounts
* timestamps

Invoice items should be able to represent:

* base subscription charge
* overage charge
* potentially separate subscription segments

Do not implement billing calculations yet.

The schema should simply support the future billing service.

Use precise money representation consistently.

---

7. NORMALIZATION

---

Keep the relational model properly normalized.

Avoid unnecessary duplicated fields.

However, when historical billing correctness requires storing a pricing snapshot, it is acceptable to intentionally denormalize/snapshot pricing.

Clearly document every such decision.

Do NOT blindly normalize everything if it makes historical billing unreliable.

---

8. INDEX STRATEGY

---

This is one of the most important requirements.

Add deliberate indexes based on expected query patterns.

At minimum, reason about indexes for:

usage_events:

* merchant + occurred_at
* merchant + customer + occurred_at
* idempotency lookup/uniqueness
* subscription/segment + occurred_at where useful

daily_usage:

* merchant + usage_date
* merchant + customer + usage_date
* customer + usage_date where useful

subscriptions:

* merchant + customer
* billing/status lookup

subscription_segments:

* subscription + start/end dates
* plan/subscription lookup where useful

invoices:

* merchant + billing period
* customer + billing period
* subscription + billing period

Do not blindly create every possible index.

For every important index, consider:

* query benefit
* write overhead
* cardinality
* expected access pattern

Document the reasoning.

---

9. 50L+ USAGE EVENT SCALABILITY

---

Add a README section:

"Scaling to 50L+ Usage Events"

Explicitly discuss:

1. Index strategy
2. Why raw usage events should not be repeatedly scanned
3. Daily aggregation
4. Chunked processing
5. Queue-based processing
6. Database growth considerations
7. Partitioning strategy
8. Archival/retention strategy
9. Read/write workload separation if scale increases
10. Potential future use of dedicated analytics/storage systems if required

IMPORTANT:

Do not implement complex partitioning just for demonstration unless it is appropriate for the actual database and project.

Instead, if partitioning is not implemented in this take-home, document a practical strategy such as time-based partitioning by month.

Explain:

* partition key
* why time-based partitioning is suitable
* expected benefits
* operational trade-offs

The interviewer specifically wants to see that you have considered 50L+ rows.

---

10. MULTI-TENANCY

---

Every tenant-owned table must have a clear merchant/tenant ownership path.

Avoid cross-tenant data access by design.

Think about:

Merchant
↓
Plan
Customer
Subscription
Usage
Invoice

Where appropriate, include merchant_id directly for efficient tenant-scoped queries.

If merchant_id is intentionally duplicated for query efficiency, document why.

Do not introduce a complex tenancy package.

Keep tenancy explicit and understandable.

---

11. FOREIGN KEYS & DELETE BEHAVIOR

---

Use foreign keys where appropriate.

Choose delete behavior deliberately.

For example:

* Do not allow accidental deletion of historical billing data.
* Historical invoices and usage records should not disappear casually because a parent record was deleted.
* Prefer restrictive behavior or soft deletion where appropriate.

Do not add soft deletes everywhere automatically.

Make the decision based on the domain.

---

12. MODELS & RELATIONSHIPS

---

Create Eloquent models for the implemented tables.

Define only useful relationships.

Examples:

Merchant:

* plans
* customers
* subscriptions
* usage events
* invoices

Plan:

* merchant
* subscriptions/segments where applicable

Customer:

* merchant
* subscriptions
* usage events
* invoices

Subscription:

* merchant
* customer
* plan
* segments
* invoices

SubscriptionSegment:

* subscription
* plan
* usage events/daily usage if applicable

UsageEvent:

* merchant
* customer
* subscription/segment if appropriate

DailyUsage:

* merchant
* customer
* subscription/segment if required

Invoice:

* merchant
* customer
* subscription
* items

InvoiceItem:

* invoice
* subscription segment if applicable

Avoid unnecessary relationships.

---

13. FACTORIES / SEEDERS

---

Create basic factories only where useful for future testing.

Do not generate huge usage datasets yet.

Do not create fake production-like business workflows.

A small development seeder may be created if genuinely useful, but keep it minimal.

---

14. MIGRATIONS

---

Create clean, ordered Laravel migrations.

Requirements:

* foreign keys
* appropriate data types
* indexes
* unique constraints
* timestamps
* sensible defaults where appropriate

Do not edit old migrations unnecessarily if they have already been committed in the foundation branch.

Create new migrations for this phase.

---

15. TESTING

---

Add database-level/model tests that verify the schema's important guarantees.

At minimum test:

1. Merchant can have multiple plans.
2. Customer belongs to a merchant.
3. Subscription belongs to customer and merchant.
4. Subscription can contain multiple historical segments.
5. Duplicate usage idempotency key is rejected.
6. Daily usage uniqueness prevents duplicate logical aggregates.
7. Invoice can contain multiple invoice items.
8. Important relationships work correctly.

Do NOT implement billing calculations yet.

---

16. README DOCUMENTATION

---

Update README.md with:

## Database Architecture

Explain the main entities.

## Entity Relationships

Include a simple textual or Mermaid ER diagram if appropriate.

Example style:

Merchant
├── Plans
├── Customers
│     └── Subscriptions
│           └── Subscription Segments
│
├── Usage Events
├── Daily Usage
└── Invoices
└── Invoice Items

## Index Strategy

Explain the major indexes and why they exist.

## Idempotency Strategy

Explain how the database prevents duplicate usage events.

## Multi-Tenancy Strategy

Explain how tenant isolation is represented.

## Historical Pricing Strategy

Explain how plan upgrades/downgrades preserve the pricing required for historical billing.

## 50L+ Usage Event Scalability

Explain:

* indexes
* aggregation
* chunking
* queues
* partitioning
* retention/archival
* future scaling options

Be honest about what is implemented now versus what is planned.

---

17. QUALITY & SAFETY

---

Before finishing:

* Run migrations from a clean database.
* Run the test suite.
* Run Laravel Pint if available.
* Check migration rollback where practical.
* Inspect git diff.
* Inspect git status.
* Do not commit.
* Do not push.
* Do not switch branches.

Do not implement:

* usage API
* billing service
* aggregation jobs
* dashboard
* Redis plan caching
* rate limiter
* invoice generation logic

Those belong to later phases.

---

## FINAL OUTPUT

At the end, provide:

1. Tables created
2. Important fields
3. Important indexes
4. Foreign-key/delete decisions
5. Idempotency design
6. Subscription segment design
7. Historical pricing decision
8. 50L+ scalability strategy
9. Tests added
10. README sections updated
11. Commands/tests executed
12. Any concerns or assumptions

Most importantly:

Do not over-engineer the schema.
Every table, column, relationship, and index must have a clear reason connected to the assignment requirements.
