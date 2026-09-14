You are working on a Laravel backend take-home assignment for a Senior Laravel Developer interview.

Assignment:
Build a Subscription Billing & Usage-Metering System for a multi-tenant SaaS scenario.

Current Git branch:
feature/project-foundation

IMPORTANT:
This is ONLY the Project Foundation phase.
Do NOT implement merchants, plans, customers, subscriptions, usage events, invoices, billing logic, dashboard logic, or other business modules yet.

GOAL:
Establish a clean, production-minded Laravel backend foundation that we can build the assignment on top of.

Before making changes:

1. Inspect the existing Laravel project structure.
2. Identify the Laravel version, PHP version, existing packages, database configuration, queue configuration, cache configuration, and testing setup.
3. Do not replace or upgrade major dependencies unnecessarily.
4. Reuse the existing project structure where it is already reasonable.
5. If something is already correctly configured, keep it instead of recreating it.

IMPLEMENT ONLY THE FOLLOWING:

1. ENVIRONMENT CONFIGURATION

* Review .env and .env.example.
* Ensure database configuration is environment-driven.
* Ensure cache, queue, and Redis configuration are environment-driven.
* Do not hardcode credentials, URLs, secrets, or environment-specific values.
* Never commit real secrets.
* Add sensible placeholder values to .env.example where required.

2. API FOUNDATION

* Establish a clean API structure under routes/api.php.
* Keep API routes versionable, preferably using /api/v1 where compatible with the existing Laravel version.
* Do not create business endpoints yet.
* Add only a simple health/status endpoint if useful, for example:
  GET /api/v1/health
* The health endpoint should confirm the application is running without exposing secrets or sensitive configuration.

3. JSON RESPONSE CONVENTION
   Establish a consistent JSON response structure for APIs.

Success example:
{
"success": true,
"data": {},
"message": "..."
}

Error example:
{
"success": false,
"message": "...",
"errors": {}
}

Do not over-engineer this.
Use Laravel's existing response mechanisms where practical.

4. EXCEPTION HANDLING
   Establish a clean API-friendly exception handling approach.

Requirements:

* Validation errors should return structured JSON.
* Unauthenticated requests should return appropriate JSON when applicable.
* Not found errors should return JSON.
* Unexpected server errors should not expose stack traces, file paths, SQL queries, environment variables, or secrets in production.
* Preserve Laravel's normal exception behavior where it is more appropriate.
* Do not build a huge custom exception framework.

5. VALIDATION FOUNDATION
   Prepare the project for Form Request based validation.
   Do not create business-specific request classes yet.

6. CODE ORGANIZATION
   Keep the architecture ready for separation of concerns.

Use Laravel conventions and prepare directories only where genuinely useful, such as:

* app/Http/Controllers/Api
* app/Http/Requests
* app/Services
* app/Actions
* app/DTOs

Do not create empty abstractions/interfaces/repositories merely for the sake of architecture.

The eventual architecture should allow:
Controller
↓
Request validation
↓
Action/Service
↓
Domain/business logic
↓
Models/database

But do not implement the business layer yet.

7. DATABASE FOUNDATION

* Verify database connectivity.
* Keep database settings configurable through .env.
* Do not create assignment-specific tables yet.
* Do not modify Laravel's default migrations unless there is a genuine foundation requirement.
* Preserve migration compatibility.

8. REDIS / CACHE FOUNDATION
   The assignment requires plan/pricing caching later.

For this phase:

* Verify Laravel cache configuration is environment-driven.
* Verify Redis can be configured through environment variables.
* Do not implement plan caching yet.
* Do not create business cache keys yet.
* Document the intended cache layer briefly if useful.

9. QUEUE FOUNDATION
   The assignment requires high-volume usage processing and queued chunked aggregation later.

For this phase:

* Verify Laravel queue configuration is environment-driven.
* Ensure the project can use Redis/database queue depending on environment.
* Do not create aggregation jobs yet.
* Do not create worker-specific business logic yet.
* Document the expected local queue worker command in README.

10. RATE LIMITING FOUNDATION
    The usage endpoint will later require rate limiting.

For this phase:

* Verify Laravel's rate limiting infrastructure is available.
* Do not create the final usage-specific limiter yet.
* Do not arbitrarily throttle all APIs.

11. TESTING FOUNDATION
    Verify the testing setup.

Add only a minimal foundation/health test if needed, such as:

* application boots successfully
* health endpoint returns HTTP 200
* health endpoint returns expected JSON structure

Do not create billing tests yet.

12. CODE QUALITY
    Inspect existing project configuration for:

* Laravel Pint
* PHPUnit/Pest
* PHPStan/Larastan if already present

Do not introduce heavy tooling unnecessarily.

If Pint is already available, ensure the foundation code follows Pint formatting.

Do not add PHPStan/Larastan unless it is already part of the project or clearly compatible without unnecessary dependency changes.

13. README FOUNDATION
    Create/update README.md with a concise initial structure containing:

# Subscription Billing & Usage-Metering System

## Overview

Briefly describe the assignment.

## Current Architecture

Explain that the project is currently in the foundation stage.

## Tech Stack

Mention the actual versions detected from the project rather than inventing versions.

## Local Setup

Document:

* composer install
* environment setup
* application key generation if required
* database configuration
* migrations command
* server command
* queue worker command
* test command

Only document commands that are actually applicable to this project.

## Environment

Explain that secrets/configuration are supplied through .env and are not committed.

## Architecture Direction

Briefly mention the planned separation of:

* API layer
* validation
* services/actions
* domain logic
* persistence
* queued processing

Do NOT document unimplemented features as if they already exist.

## Assumptions / Trade-offs

Add a small section stating that implementation decisions will be documented as the assignment progresses.

14. SECURITY
    Check for obvious foundation-level security issues:

* no committed secrets
* no debug output in production responses
* no credentials in source code
* no unnecessary sensitive data in logs
* .env should remain ignored by Git

Do not implement unrelated security features.

15. GIT SAFETY
    Before finishing:

* inspect git diff
* inspect git status
* ensure no secrets or unnecessary generated files are included
* do not commit anything automatically
* do not change branches
* do not push anything

IMPORTANT ARCHITECTURE RULES:

* Keep the implementation simple and interview-review friendly.
* Prefer Laravel conventions over custom frameworks.
* Do not over-engineer.
* Do not introduce repositories/interfaces/design patterns unless they provide real value.
* Do not create business logic in controllers.
* Do not create unused classes just for demonstration.
* Do not implement requirements from later phases yet.
* Do not invent API endpoints or fields that are not part of the assignment.
* Do not make assumptions about business rules that belong to later phases.

DELIVERABLE:
At the end, provide a concise summary containing:

1. Files created/modified
2. Configuration changes
3. Tests added
4. Commands used to verify the foundation
5. Any assumptions made
6. Any issues that need attention before starting the database/domain phase

Do not commit or push changes.