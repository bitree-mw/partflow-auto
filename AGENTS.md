# AGENTS.md

## 1. Purpose

This file contains mandatory instructions for AI coding agents working on **PartFlow Auto**. The application is an existing Laravel inventory and point-of-sale system for a motor vehicle parts business. Preserve working behaviour and inspect the repository before changing code.

## 2. Project summary

- Application: PartFlow Auto.
- Backend: Laravel running on PHP 8.4; verify the exact Laravel version from `composer.json`.
- Frontend: Laravel Blade and Tailwind CSS.
- Asset build: verify Vite and frontend dependencies from `package.json`.
- Authentication: Laravel Sanctum for protected API access and Laravel session authentication for Blade/web access.
- Database: MySQL; verify the server version, charset, and collation without exposing credentials.
- Users: administrators, management, and sales staff.
- Scope: parts, vehicle compatibility, suppliers, purchases, multi-site stock, transfers, POS sales, customers, debtors, payments, discounts, VAT, and reporting.

## 3. Mandatory do's

- Inspect existing routes, migrations, models, requests, services, controllers, resources, middleware, Blade views, and response helpers before editing.
- Follow existing names and public response contracts where they are working.
- Keep controllers thin and business logic in services.
- Validate input through Form Requests.
- Enforce permissions, role access, and site access on the server.
- Keep API authentication and web authentication boundaries explicit: use Sanctum middleware for protected API routes and session-backed web middleware for protected Blade routes.
- Recalculate totals, VAT, discounts, balances, and stock effects on the server.
- Use database transactions for purchases, receiving, adjustments, transfers, sales, debtor payments, and other multi-write operations.
- Lock stock or balance records where concurrent writes could corrupt quantities or amounts.
- Record traceable stock movements and payment/debtor histories.
- Use API Resources and the shared `ApiResponse` structure.
- Reuse Blade layouts, components, form controls, tables, badges, filters, pagination, and Tailwind conventions.
- Add or update relevant tests and run the narrowest applicable checks.
- Work one feature or coherent change at a time.
- Report changed files, checks run, assumptions, and unresolved risks.

## 4. Mandatory don'ts

- Do not place calculations, database orchestration, or substantial queries in controllers.
- Do not trust prices, totals, taxes, discounts, profit, stock, balances, roles, ownership, or site access submitted by the frontend.
- Do not modify an old migration that may already have run; create a new migration.
- Do not run `migrate:fresh`, `db:wipe`, destructive SQL, broad deletes, or production migrations without explicit approval.
- Do not read, expose, replace, or commit `.env` secrets.
- Do not access or modify production systems automatically.
- Do not add repositories for ordinary CRUD.
- Do not return raw models when an API Resource is required.
- Do not silently change the `ApiResponse` envelope, route names, field types, statuses, or calculations.
- Do not change stock without a stock-movement record and approved source or reason.
- Do not overwrite cumulative debtor or payment amounts with only the latest payment.
- Do not hard-delete completed financial or stock transactions without an approved reversal policy.
- Do not install, remove, or upgrade packages without explaining the need and impact.
- Do not replace Blade, Tailwind, Laravel, MySQL, Sanctum, session authentication, or the existing architecture unless explicitly requested.
- Do not claim tests passed unless they were actually run successfully.
- Do not invent missing business rules or describe planned features as implemented.

## 5. First repository review

Before the first implementation task:

1. Read `composer.json` and `package.json`.
2. Inspect `routes/api.php` and `routes/web.php`.
3. Locate `ApiResponse`, exception handling, authentication, and role/permission middleware.
4. Inventory migrations, models, Form Requests, services, controllers, resources, policies, repositories, and Blade views.
5. Compare the code with `docs/FEATURES_AND_COMPONENTS.md`.
6. Classify each feature as implemented, partial, planned, or absent.
7. Update documentation only with verified facts.
8. List unclear business rules for human confirmation.

## 6. Required reading by task

- Architecture or new module: `docs/ARCHITECTURE.md`
- Database, stock, balances, or payments: `docs/DATABASE.md`
- API endpoint or response: `docs/API_CONVENTIONS.md`
- Blade or Tailwind change: `docs/FRONTEND.md`
- Tests or verification: `docs/TESTING.md`
- Feature inventory or gap review: `docs/FEATURES_AND_COMPONENTS.md`
- Security-sensitive change: `SECURITY.md`
- Larger task: `docs/AI_WORKFLOW.md`

Read only documents relevant to the current task.

## 7. Required architecture

`Route → Controller → Form Request → Service → Model/Query → API Resource → ApiResponse`

- API controllers: `app/Http/Controllers/Api`
- Form Requests: `app/Http/Requests`
- Services: `app/Services`
- Models: `app/Models`
- API Resources: `app/Http/Resources`
- Policies: `app/Policies`
- Repositories: `app/Repositories`, only for reports, dashboards, aggregation, advanced filtering, or heavy reusable queries.

Use the real repository structure when it differs. Do not move working files merely for visual uniformity.

## 8. Feature implementation sequence

`Requirements/flow → Migration → Model → Form Request → Service → Controller → API Resource → Routes → Tests → Blade frontend`

For large features, present a short plan and confirm unclear business rules before coding. During interactive guidance, proceed one natural stage at a time.

## 9. PartFlow Auto domain rules

- Stock is tracked per site or branch.
- Every stock change has a traceable movement source.
- Purchases increase stock only at the approved receiving stage.
- Sales deduct stock only at the approved completion stage.
- Transfers preserve source, destination, items, quantities, statuses, actors, and timestamps.
- A transfer cannot be received twice or into its source site.
- Part compatibility may include make, model, year range, engine, and fuel type.
- Part codes are stable and follow the confirmed rule, including approved `-I`, `-D`, and `-H` suffixes.
- Purchase and selling prices remain distinct.
- Completed line items preserve transaction-time cost, price, discount, and VAT.
- Debtor payments create history and reduce a verified outstanding balance.
- Bank and mobile-money payments record the approved receiving destination.
- Reports use trusted records and respect role/site boundaries.

## 10. Blade and Tailwind rules

- Blade displays prepared data and collects input; it does not query the database or calculate business totals.
- Reuse existing layouts, components, partials, and Tailwind patterns.
- Keep navigation permission-aware while retaining backend authorization.
- Show validation, loading, empty, success, and safe failure states.
- Keep POS screens efficient for sales-counter use.
- Do not introduce React, Vue, Livewire, Alpine.js, or another UI library unless already installed or approved.

## 11. Authentication rules

- Use Laravel session authentication for browser-facing Blade routes and forms.
- Use Laravel Sanctum for protected API endpoints.
- Apply CSRF protection to session-authenticated web forms.
- Do not mix token issuance into ordinary Blade login unless the existing application explicitly requires it.
- Do not assume a session-authenticated user may access every API or site-scoped resource.
- Logout must invalidate the relevant session or token according to the route and client being used.
- Authorization through policies, gates, middleware, or Form Requests remains required after authentication.

## 12. Verification commands

Use only commands confirmed by the repository:

- `php artisan test`
- `php artisan test --filter=TestName`
- `vendor/bin/pint --test`
- `vendor/bin/pint`
- `npm run dev`
- `npm run build`

## 13. Code review priorities

1. Data loss, security, authorization, and site-boundary risks.
2. Incorrect stock, price, VAT, discount, debt, payment, or profit calculations.
3. Invalid state transitions and missing transactions or locks.
4. API compatibility and Blade regressions.
5. Query performance, indexes, eager loading, filtering, and pagination.
6. Missing tests and documentation drift.
7. Maintainability and formatting.

## 14. Definition of done

A task is complete when the requested behaviour works, architecture is followed, permissions and validation are enforced, financial and stock writes are transaction-safe, relevant checks pass, public contracts remain consistent, and the final report states exactly what was verified.
