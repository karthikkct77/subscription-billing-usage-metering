We now need to implement the Dashboard UI for the Subscription Billing & Usage-Metering System.

IMPORTANT CONTEXT:

The backend/API implementation is already complete.

Current frontend structure is minimal:

resources/
├── css/
├── js/
└── views/
    └── welcome.blade.php

routes/web.php currently contains only the root route.

The assignment includes a "Suggested UI Reference (Wireframe)" for the merchant dashboard.

The wireframe is LOW-FIDELITY and is only a layout reference.

The assignment explicitly says:

"Visual styling is entirely up to you; we're evaluating the schema, aggregation and caching decisions behind it, not the UI polish."

Therefore, create a simple, professional, interview-demo-quality dashboard UI.

IMPORTANT:
- Do NOT redesign the backend.
- Do NOT modify existing database schema.
- Do NOT modify existing billing logic.
- Do NOT create fake business logic.
- Do NOT hardcode dashboard metrics.
- Do NOT invent new API endpoints.
- Reuse the existing dashboard API/business logic.
- Use actual database-backed data.
- Do not commit or push Git changes.

==================================================
1. DASHBOARD PURPOSE
==================================================

Create a Merchant Dashboard that visually demonstrates the dashboard functionality already implemented by the backend.

The dashboard should clearly show:

1. Top 5 customers by usage this month.
2. Projected overage revenue for the current billing cycle.
3. Customers whose usage dropped by more than 50% month-over-month.

The UI should make these three assignment requirements immediately visible.

==================================================
2. WEB ROUTE
==================================================

Add a web route for the dashboard.

Suggested:

GET /dashboard

Use an appropriate Laravel controller.

Do not expose business logic directly inside the Blade view.

The web controller should obtain the merchant dashboard data using the existing application architecture.

IMPORTANT:

Do NOT invent a new dashboard API endpoint.

Reuse the existing dashboard implementation.

If the existing dashboard API is already available internally through a service/action, reuse that service/action where appropriate instead of making an unnecessary HTTP request from Laravel to itself.

==================================================
3. MERCHANT CONTEXT
==================================================

The assignment is multi-tenant.

There may not be a full authentication UI because authentication is not the main assignment requirement.

For the demo UI, use a clearly documented development/demo merchant context based on the existing data model.

Do NOT introduce a fake authentication system.

Do NOT bypass tenant isolation in the underlying dashboard query.

If a merchant ID is required for the demo route, use a clean and explicit approach such as:

GET /dashboard?merchant_id={id}

or another simple approach consistent with the existing project.

Document the chosen demo approach.

Do not expose unrelated merchant data.

==================================================
4. DASHBOARD LAYOUT
==================================================

Create a professional dashboard layout.

Suggested structure:

--------------------------------------------------
Subscription Billing Dashboard
--------------------------------------------------

[ Current Month Usage ]
[ Projected Overage Revenue ]
[ Customers At Risk ]

--------------------------------------------------
Top 5 Customers by Usage
--------------------------------------------------

Customer | Usage Units | Rank

--------------------------------------------------
Projected Overage Revenue
--------------------------------------------------

Current billing cycle
Projected revenue

--------------------------------------------------
Usage Drop >50% MoM
--------------------------------------------------

Customer | Previous Month | Current Month | Change %

--------------------------------------------------

The visual design is your choice.

Keep it clean and professional.

Avoid excessive animations or unnecessary UI complexity.

==================================================
5. TOP 5 CUSTOMERS
==================================================

Display the actual dashboard API data.

Show:

- customer name
- usage units
- ranking

Sort according to the backend result.

Do not calculate a different top-5 result inside Blade.

The backend remains responsible for business calculations.

==================================================
6. PROJECTED OVERAGE REVENUE
==================================================

Display the actual projected overage revenue returned by the existing dashboard implementation.

Show:

- current billing cycle
- projected overage revenue
- appropriate currency formatting.

Do not recalculate the value in JavaScript or Blade.

==================================================
7. USAGE DROP >50%
==================================================

Display customers identified by the backend as having usage dropped by more than 50% month-over-month.

Show:

- customer
- previous month usage
- current month usage
- percentage change/drop.

If there are no customers, show a clean empty state:

"No customers with >50% usage drop."

Do not treat zero previous-month usage incorrectly.

The backend remains the source of truth.

==================================================
8. EMPTY STATES
==================================================

Handle empty data gracefully.

Examples:

- No customers found.
- No usage data available.
- No projected overage.
- No customers with significant usage drop.

Do not show broken tables or PHP errors.

==================================================
9. ERROR HANDLING
==================================================

If dashboard data cannot be loaded:

Display a professional error state.

Example:

"Unable to load dashboard data. Please try again."

Do not expose stack traces or sensitive exception information in the UI.

==================================================
10. UI TECHNOLOGY
==================================================

Use the simplest technology already supported by the Laravel project.

Prefer:

- Blade
- CSS
- minimal JavaScript only when genuinely useful.

Do not introduce React/Vue/Inertia unless the project already uses it for the current application.

Do not install a large UI framework unnecessarily.

If Bootstrap/Tailwind is already available, reuse it.

Otherwise create clean lightweight CSS.

==================================================
11. RESPONSIVE DESIGN
==================================================

Dashboard should work reasonably on:

- desktop
- laptop
- tablet.

Mobile perfection is not required.

The primary interview demo target is desktop browser.

==================================================
12. VISUAL QUALITY
==================================================

Create a polished but intentionally simple SaaS dashboard.

Include:

- page header
- metric cards
- clean tables
- spacing
- readable typography
- consistent borders
- responsive layout
- loading/error/empty states where appropriate.

Do not spend excessive time on visual effects.

The goal is to demonstrate backend data through a professional UI.

==================================================
13. API / SERVICE INTEGRATION
==================================================

IMPORTANT:

Inspect the existing dashboard implementation before writing UI code.

Identify:

- existing dashboard service/action
- controller
- response structure
- required merchant identifier
- exact field names.

Use the ACTUAL implementation.

Do not invent field names.

Do not assume the response structure.

==================================================
14. DATA FLOW
==================================================

The final flow should conceptually be:

Browser
   ↓
Laravel web route
   ↓
Dashboard controller
   ↓
Existing dashboard service/action
   ↓
Existing database queries / aggregation
   ↓
Blade view
   ↓
Rendered dashboard

Do not duplicate dashboard business calculations in the UI.

==================================================
15. SECURITY
==================================================

Ensure the demo route does not allow arbitrary cross-tenant data access.

If merchant_id comes from a request parameter:

- validate it
- ensure it references a valid merchant
- use the existing tenant-scoped dashboard logic.

Do not expose internal exception details.

==================================================
16. README
==================================================

Add a short section explaining:

"Dashboard UI"

Include:

- dashboard URL
- demo merchant selection approach
- data source
- why UI calculations are kept out of Blade
- relationship between dashboard UI and existing dashboard backend.

Make it clear that the UI is a lightweight demonstration layer for the backend assignment.

==================================================
17. VALIDATION
==================================================

After implementation:

Run:

php artisan test

Run:

php artisan route:list

Then start the application:

php artisan serve

Open:

http://127.0.0.1:8000/dashboard

Verify the dashboard renders correctly.

Verify:

- actual data appears
- top 5 customers appear
- projected overage appears
- >50% usage drop section appears
- empty states work
- no PHP errors
- no console errors
- no cross-tenant leakage.

Do not commit or push.

==================================================
18. FINAL REPORT
==================================================

After implementation report:

1. Files created/modified.
2. Dashboard URL.
3. Existing backend service/API reused.
4. Actual data fields used.
5. UI sections implemented.
6. Test result.
7. Any assumptions made.
8. Any remaining issues.

IMPORTANT:

The dashboard is a presentation layer only.

Keep all business calculations, aggregation, billing, caching, and tenant isolation in the existing backend architecture.