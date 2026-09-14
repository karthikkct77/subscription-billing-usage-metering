# Subscription Billing & Usage-Metering System

## Overview

This project is a multi-tenant SaaS Subscription Billing & Usage-Metering backend application built with Laravel. It is designed to handle multi-tenancy, plan subscriptions, metered usage event ingestion, usage aggregation, and automated invoice processing.

## Current Architecture

The codebase is currently in the **Project Foundation** stage. The core infrastructure, API routing, standard JSON response structures, exception handling, directory organization, environment configuration, database connection, and testing setup have been established. Business modules (merchants, plans, customers, subscriptions, usage events, and invoices) will be implemented in subsequent phases.

## Tech Stack

- **PHP**: `^8.2` (Detected runtime: `PHP 8.2.12`)
- **Framework**: Laravel `12.x` (Detected version: `12.69.2`)
- **Database**: SQLite (default for local development), with MySQL/PostgreSQL support
- **Queue / Cache**: Database / Redis driver configuration via environment variables
- **Testing**: PHPUnit `11.x`
- **Code Styling**: Laravel Pint `1.24`

## Local Setup

Follow these steps to set up the project locally:

1. **Clone the repository & install dependencies**:
   ```bash
   composer install
   ```

2. **Configure Environment**:
   ```bash
   cp .env.example .env
   ```

3. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

4. **Run Database Migrations**:
   ```bash
   php artisan migrate
   ```

5. **Start Local Development Server**:
   ```bash
   php artisan serve
   ```

6. **Start Local Queue Worker**:
   ```bash
   php artisan queue:work
   ```

7. **Run Test Suite**:
   ```bash
   php artisan test
   ```

8. **Code Formatting (Pint)**:
   ```bash
   vendor/bin/pint
   ```

## Environment

All secrets and environment-specific parameters are supplied via the `.env` file and are excluded from Git version control. Refer to `.env.example` for all configurable environment variables.

## Architecture Direction

The application follows a clean separation of concerns using standard Laravel conventions:

- **API Layer**: Controller routes prefixed under `/api/v1` handling HTTP requests.
- **Validation**: Form Request classes managing payload validation rules.
- **Actions / Services**: Encapsulating discrete domain actions and application service workflows.
- **Domain Logic**: Business logic and domain entities decoupled from delivery mechanisms.
- **Persistence**: Eloquent Models and database migrations.
- **Queued Processing**: Asynchronous workers for chunked event aggregation and background processing.

## Assumptions / Trade-offs

- **Foundation Scope**: Non-business functionality is intentionally deferred to subsequent phase implementations to maintain focus on foundation quality.
- **Response Convention**: Standardized JSON responses for API endpoints (`success`, `data`, `message` for success; `success`, `message`, `errors` for errors).
- **Exception Handling**: API exception responses sanitize uncaught server exceptions in production mode to prevent information disclosure.
