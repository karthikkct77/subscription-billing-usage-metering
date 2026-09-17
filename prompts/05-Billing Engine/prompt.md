You are continuing the Laravel Senior Developer take-home assignment:

"Subscription Billing & Usage-Metering System"

Current branch:
feature/billing-engine

Completed phases:

1. Project Foundation
2. Database Schema & Domain Foundation
3. High-throughput Idempotent Usage API
4. Queued Daily Usage Aggregation

This phase implements the CORE BILLING ENGINE.

Assignment requirement:

"At cycle end, the system generates an invoice: base price + overage beyond the included allowance, with proration if the subscription started mid-cycle."

And:

"Handle a customer upgrading or downgrading their plan mid-cycle: usage recorded before the change must be billed at the original plan's rate, and usage after the change at the new plan's rate, with proration reflecting both segments correctly."

This is a critical phase.

IMPORTANT:
Keep billing calculation deterministic, testable, and isolated from controllers/jobs.

---

1. INSPECT EXISTING IMPLEMENTATION

---

Before making changes:

* Inspect plans.
* Inspect subscriptions.
* Inspect subscription_segments.
* Inspect usage_events.
* Inspect daily_usage.
* Inspect invoices and invoice_items.
* Inspect existing services/actions.
* Inspect existing tests.
* Inspect existing money/data-type decisions.
* Inspect existing date/time conventions.

Reuse the existing schema and architecture.

Do not redesign the database unless a genuine blocker is discovered.

If a schema change is absolutely necessary, explain why before implementing it.

---

2. BILLING ARCHITECTURE

---

Implement billing as a dedicated service/action.

Recommended conceptual flow:

Billing command/job
↓
BillingService
↓
Subscription billing segments
↓
Daily usage
↓
Proration calculation
↓
Included usage
↓
Overage calculation
↓
Invoice
↓
Invoice items

Do NOT put billing calculations inside:

* controller
* Eloquent model
* migration
* command

Keep calculations independently testable.

---

3. BILLING PERIOD

---

A billing cycle must have:

* period start
* period end

Use a consistent date/time convention.

Be explicit about whether period end is:

* inclusive
  OR
* exclusive

Choose one convention and use it consistently.

Document the decision.

Avoid off-by-one-day errors.

---

4. BASE PRICE

---

At cycle end, calculate the base subscription charge.

For a full billing cycle:

base charge = plan base price

For a partial segment:

base charge must be prorated.

Do not assume every subscription starts on the first day of a billing cycle.

---

5. PRORATION

---

Implement deterministic proration.

Example:

Monthly plan:
₹3,000

Billing cycle:
September 1 → September 30

Subscription starts:
September 16

Only the applicable portion of the cycle should be charged.

Choose a clear proration convention.

A reasonable approach is:

daily rate = plan price / number of days in billing cycle

prorated charge =
daily rate × number of active days

IMPORTANT:
Document:

* whether calendar days or seconds are used
* how partial days are handled
* how leap years/month lengths are handled
* rounding behavior

Use a single reusable proration calculation.

Do NOT scatter proration arithmetic across multiple classes.

---

6. MONEY CALCULATION

---

Never use floating-point arithmetic for money.

Follow the money representation established in the schema phase.

If decimal columns are used:

* avoid unsafe floating-point calculations where possible
* use precise arithmetic
* define rounding rules

If minor units are used:

* perform calculations using integers where appropriate.

Document the rounding strategy.

For example:

* round monetary amounts to 2 decimal places
* define when rounding occurs
* ensure invoice total equals invoice-item totals

Choose the approach appropriate to the existing implementation.

---

7. INCLUDED USAGE

---

Each plan has included usage units.

Example:

Base price:
₹1,000

Included units:
10,000

Usage:
8,000

Charge:
₹1,000

No overage charge.

If usage is above the included allowance:

Usage:
12,500

Included:
10,000

Excess:
2,500

Overage rate:
₹0.10

Overage:
₹250

Total:
₹1,250

Implement this as reusable billing logic.

---

8. OVERAGE CALCULATION

---

For each billing segment:

billable usage =
max(0, usage - included allowance)

overage charge =
billable usage × overage rate

Be careful with:

* exactly equal to allowance
* zero usage
* one unit above allowance
* very large usage
* decimal rates if supported
* integer usage units

Test all relevant boundaries.

---

9. CRITICAL: PLAN CHANGE MID-CYCLE

---

This is one of the most important requirements.

Example:

Billing cycle:
September 1 → September 30

Customer starts on:

Plan A
September 1 → September 15

Then upgrades:

Plan B
September 16 → September 30

Usage:

September 1–15:
8,000 units

September 16–30:
20,000 units

The billing engine must NOT combine all usage and apply only Plan B.

Instead:

Segment A
Plan A pricing
Plan A included units
Plan A overage rate
Plan A prorated base charge
Plan A usage

Segment B
Plan B pricing
Plan B included units
Plan B overage rate
Plan B prorated base charge
Plan B usage

Then:

Invoice total =
Segment A charges
+
Segment B charges

---

10. SEGMENT-LEVEL USAGE ALLOWANCE

---

Be precise about included usage when a plan is active for only part of a billing cycle.

Do NOT automatically give the full monthly included allowance to every partial segment unless that is your explicitly documented business assumption.

Choose a reasonable interpretation.

A strong approach is to prorate included usage based on the segment's active portion of the billing cycle.

For example:

Monthly plan:
10,000 included units

Plan active for half the cycle:

prorated included allowance =
10,000 × active_days / cycle_days

Then calculate overage against that segment-specific allowance.

IMPORTANT:
This is a business-rule assumption.

Document the exact formula and rationale in README.

If the existing assignment wording could reasonably be interpreted differently, clearly state the chosen assumption.

---

11. UPGRADE VS DOWNGRADE

---

The billing engine should not care whether the segment change is called an upgrade or downgrade.

It should process chronological subscription segments.

For example:

Segment 1:
Plan A
Sep 1–10

Segment 2:
Plan B
Sep 11–20

Segment 3:
Plan C
Sep 21–30

Calculate each segment independently.

Do not assume there can only be one plan change.

---

12. HISTORICAL PRICING

---

Billing must use the pricing applicable at the time the segment was active.

Do not blindly read current plan pricing if the plan may have changed later.

Use the historical pricing snapshot established in the database schema.

For example, if Plan A was:

base = ₹1,000
overage = ₹0.10

and later edited to:

base = ₹1,500
overage = ₹0.20

historical Segment A must still use the original pricing applicable to that segment.

Document this explicitly.

---

13. USAGE SOURCE

---

Prefer daily_usage for billing calculations where appropriate.

Do not scan all raw usage_events during invoice generation.

The billing engine should use pre-aggregated usage wherever possible.

However, make sure the aggregation granularity still allows correct billing across subscription segments.

If the current daily_usage design is insufficient to distinguish segment pricing, make the smallest justified schema adjustment.

Do not rebuild the whole schema.

---

14. INVOICE CREATION

---

Generate:

invoices
+
invoice_items

An invoice should contain:

* merchant
* customer
* subscription
* billing period
* subtotal
* total
* status

Invoice items should clearly identify charges.

At minimum support:

1. Base subscription charge
2. Overage charge

For multiple subscription segments, create understandable invoice items.

For example:

Base charge — Plan A
Overage charge — Plan A
Base charge — Plan B
Overage charge — Plan B

This makes the invoice auditable.

---

15. BILLING IDEMPOTENCY

---

Cycle-end billing may be retried.

Do NOT allow duplicate invoices for the same:

merchant
+
customer
+
subscription
+
billing period

Use database uniqueness where appropriate.

The billing operation must be retry-safe.

If an invoice already exists for the billing period, do not create another invoice.

Document the strategy.

---

16. TRANSACTION

---

Invoice generation should be atomic.

Conceptually:

BEGIN TRANSACTION

calculate charges
create invoice
create invoice items
finalize totals

COMMIT

If anything fails:

ROLLBACK

Do not leave half-created invoices.

Avoid long-running transactions around unnecessary operations.

---

17. BILLING SERVICE DESIGN

---

Keep calculation functions independently testable.

A reasonable conceptual design:

BillingService
├── calculateSegmentCharge()
├── calculateProration()
├── calculateIncludedAllowance()
├── calculateOverage()
└── generateInvoice()

You may split calculations into smaller dedicated classes if that improves clarity.

Do not over-engineer.

The most important requirement is that the mathematical logic is isolated and testable.

---

18. DECIMAL / ROUNDING EDGE CASES

---

Test carefully for:

* zero usage
* usage exactly equal to included units
* usage one unit above included units
* no overage
* high overage
* fractional monetary rate
* partial cycle
* one-day segment
* full-cycle segment
* multiple segments
* plan upgrade
* plan downgrade
* segment boundary date
* month with 28 days
* month with 29 days
* month with 30 days
* month with 31 days

Use deterministic expected values.

---

19. TESTS

---

This phase MUST have strong automated tests.

At minimum:

PRORATION:

1. Full-cycle subscription charges full base price.
2. Mid-cycle start is prorated.
3. One-day segment works correctly.
4. Different month lengths work correctly.
5. Leap-year February works correctly.
6. Rounding is deterministic.

OVERAGE:
7. Usage below allowance produces zero overage.
8. Usage exactly at allowance produces zero overage.
9. Usage above allowance calculates correct overage.
10. Large usage calculates correctly.

PLAN CHANGES:
11. Upgrade mid-cycle bills pre-change usage at old pricing.
12. Upgrade mid-cycle bills post-change usage at new pricing.
13. Downgrade mid-cycle behaves correctly.
14. Multiple plan segments calculate independently.
15. Segment-specific included allowance is correct.
16. Historical pricing is respected.

INVOICE:
17. Invoice contains correct base charges.
18. Invoice contains correct overage charges.
19. Invoice total equals invoice item totals.
20. Retrying billing does not create duplicate invoices.
21. Failed invoice creation does not leave partial invoice data.

Do not mock the core mathematical calculations unnecessarily.

Prefer realistic database-backed tests for invoice generation.

---

20. BILLING COMMAND / JOB

---

Provide a clean way to trigger cycle-end billing.

A command or queued job is appropriate.

For example:

php artisan billing:generate --date=2026-09-30

Choose a clean interface.

The command/job should:

* identify subscriptions whose billing cycle has ended
* dispatch or execute invoice generation appropriately
* avoid duplicate invoices
* support retries

If processing many subscriptions, use chunking and queue jobs rather than processing all customers in one long-running request.

Do not expose billing generation through a web controller.

---

21. QUEUED BILLING

---

If creating a billing job:

* make it queueable
* make it retry-safe
* use sensible timeout/retry settings
* avoid processing huge numbers of subscriptions in one job

A good conceptual flow is:

Billing command
↓
find due subscriptions
↓
dispatch GenerateInvoice job per subscription
↓
BillingService
↓
invoice

Document the reasoning.

---

22. README

---

Update README with:

## Billing Architecture

Explain the billing flow.

## Proration Formula

Clearly document the formula.

## Overage Formula

Clearly document the formula.

## Segment-Based Billing

Explain how upgrades/downgrades are handled.

## Historical Pricing

Explain why pricing snapshots are used.

## Rounding

Explain monetary precision and rounding.

## Billing Idempotency

Explain how duplicate invoice creation is prevented.

## Billing Assumptions

Explicitly document any business assumptions, especially:

* proration method
* included usage proration
* cycle boundaries
* rounding

The assignment specifically says ambiguity should be handled using best judgment and documented.

---

23. PERFORMANCE

---

Do not scan raw usage_events for every invoice.

Use:

* daily aggregates
* proper indexes
* bounded queries
* chunked subscription processing
* queued jobs

The billing architecture should be able to scale beyond a small demo.

---

24. QUALITY

---

Before finishing:

Run:

php artisan test

Run the billing tests specifically.

Run Pint if available.

Run migrations/tests from a clean database where practical.

Inspect:

git diff --stat
git status

Do NOT commit.
Do NOT push.
Do NOT switch branches.

---

## FINAL OUTPUT

Provide:

1. Billing service/action architecture
2. Proration formula
3. Included allowance formula
4. Overage formula
5. Segment billing approach
6. Upgrade/downgrade handling
7. Historical pricing approach
8. Invoice generation flow
9. Billing idempotency strategy
10. Queue/job/command design
11. Tests added
12. README updates
13. Commands/tests executed
14. Assumptions and edge cases

IMPORTANT:

This is a Senior Laravel Developer interview.

The interviewer will inspect the billing mathematics closely.

Prioritize:

* correctness
* deterministic calculations
* historical accuracy
* retry safety
* testability
* clean separation of concerns

Do not optimize for maximum code volume.
