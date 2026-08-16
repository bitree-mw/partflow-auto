# PartFlow Auto Testing Guide

## Purpose

PartFlow Auto tests must protect inventory quantities, financial totals, authorization, and site boundaries. A visually correct result is not enough if stock, debt, VAT, or payments can become inconsistent.

Before changing tests, inspect `phpunit.xml`, `composer.json`, `package.json`, the existing test folders, and the configured test database.

The local runtime is PHP 8.4. Composer locks PHPUnit 11.5.55 and Laravel Pint 1.29.3. `phpunit.xml` forces SQLite `:memory:`, array cache/session, and the synchronous queue driver; tests must never be pointed at production data. The application target is MySQL, so SQLite-only success does not prove MySQL-specific concurrency or constraint behavior.

## Verified suite status

As of 2026-08-16, the current working tree has 30 passing tests with 276 assertions. Coverage includes API/session authentication, core Blade pages, catalogue operations, stock takes, purchases, POS server-price and stock guards, catalogue import/replacement commands, and cross-cutting user-site access.

This is functional coverage, not complete business-flow coverage. No browser suite, JavaScript unit runner, static-analysis tool, or code-coverage threshold is configured.

## Test levels

| Test type | Purpose | Typical targets |
| --- | --- | --- |
| Feature tests | Verify complete HTTP behaviour and database effects. | Authentication, API endpoints, web forms, sales, purchases, transfers, payments. |
| Unit tests | Verify isolated business calculations or state rules. | Part-code generation, totals, VAT, discounts, transition rules. |
| Blade/component tests | Verify important rendered content and authorization-dependent controls. | Navigation, validation errors, totals, status badges. |
| Browser tests | Verify critical interactive workflows when a browser framework exists. | POS checkout, transfer receiving, complex filters. |

Prefer feature tests for business workflows because they exercise authorization, validation, services, persistence, and response formatting together.

Authentication coverage must distinguish between:

- Session-authenticated Blade/web requests, including CSRF behaviour where applicable.
- Sanctum-authenticated API requests, including missing, invalid, or revoked credentials.
- Authorization after authentication, including role and site restrictions.

## Standard commands

Use the repository's documented commands. Common Laravel commands are:

```bash
php artisan test
php artisan test --filter=Sale
```

Run the configured frontend build after Blade or Tailwind work:

```bash
npm run build
```

<!-- INACTIVE: Parallel tests are supported with `php artisan test --parallel`. Activate after confirming database isolation. -->
<!-- INACTIVE: Static analysis is configured. Record the PHPStan or Larastan command here. -->

Laravel Pint is configured:

```bash
vendor/bin/pint --test
vendor/bin/pint
```

The repository-wide `pint --test` baseline is currently not clean. Do not broadly format unrelated files as part of a focused change.

<!-- INACTIVE: JavaScript linting and tests are configured. Record the package scripts here. -->

## Baseline feature-test checklist

For each endpoint or state-changing web action, test the relevant items below:

- Successful authorized request.
- Guest or unauthenticated request.
- Authenticated user without the required role or permission.
- User attempting to access a site they are not assigned to.
- Required-field, type, range, and relationship validation.
- Invalid status transition.
- Correct response or redirect structure.
- Correct database records and audit fields.
- No partial database changes when an operation fails.
- Important edge cases and a regression case for every fixed bug.

## Required module coverage

| Module | Critical tests |
| --- | --- |
| Authentication | Login success/failure, logout, protected routes, disabled users if supported. |
| Roles and permissions | Allowed actions, denied actions, site assignment, privilege escalation attempts. |
| Sites | Site scoping and prevention of cross-site reads or writes. |
| Part catalogue | Create/update validation, unique code, price/cost rules, archive behaviour. |
| Part-code generation | Petrol `-I`, diesel `-D`, hybrid `-H`, uniqueness, unsupported/blank fuel handling. |
| Compatibility | Valid make/model/year/engine combinations and duplicate prevention. |
| Suppliers and purchases | Totals, status, supplier validation, site receipt, stock movement creation. |
| Inventory | Quantity-on-hand calculation, low-stock threshold, adjustment reason and authorization. |
| Transfers | Valid source/destination, available stock, status transitions, dispatch and receipt effects. |
| Sales/POS | Current-site stock, authoritative prices, quantities, discounts, VAT, totals, atomic checkout. |
| Customers and debtors | Credit authorization, balance creation, partial payment, overpayment prevention. |
| Payments | Valid method/destination, amount allocation, balance update, immutable audit history. |
| Reports | Filters, site scope, totals, date boundaries, pagination/export if implemented. |

## Inventory integrity tests

Inventory tests should prove that:

- Stock never changes without a traceable business operation or movement record.
- Purchase receipt increases the correct site's stock exactly once.
- Sale completion decreases the correct site's stock exactly once.
- A transfer does not create stock; source and destination effects balance according to the documented status flow.
- A failed transaction leaves stock and related records unchanged.
- Negative stock is rejected unless a documented override exists.
- Repeating the same request cannot accidentally apply the movement twice where idempotency is required.

Use database transactions and row locking in the implementation where concurrent checkouts or transfers could oversell stock. Add an integration test for the chosen concurrency strategy when the test environment supports it.

## Financial integrity tests

Test monetary calculations using the application's stored unit, ideally integer minor units or fixed-precision decimals rather than binary floats.

Cover:

- Line subtotal and order subtotal.
- Authorized fixed or percentage discounts and their limits.
- VAT-inclusive, VAT-exclusive, and exempt behaviour actually supported by the application.
- Rounding at the documented stage.
- Paid, partially paid, and unpaid balances.
- Payment destination requirements for bank or mobile money.
- Prevention of negative totals and unintended overpayment.
- Reversal or void behaviour if implemented.

## Factories and fixtures

- Use factories for users, sites, parts, suppliers, customers, and transactions.
- Create named factory states for roles, fuel types, taxable/exempt parts, and transaction statuses when useful.
- Keep each test's setup focused; do not depend on broad production seeders unless the suite documents that convention.
- Make site ownership explicit in test data.
- Use realistic boundary values for stock and money.

## Test naming and organization

- Organize tests by module or business capability.
- Test names should state the condition and expected outcome.
- Keep one primary behaviour per test.
- Use data providers/datasets for repeated validation cases where the installed test framework supports them.
- Follow the repository's existing PHPUnit or Pest convention; do not introduce the other framework casually.

## Bug-fix rule

Every business-impacting bug fix must include a regression test that fails before the fix and passes afterward. This is mandatory for bugs affecting stock, transfers, prices, VAT, discounts, debts, payments, authorization, and site isolation.

## Verified coverage gaps

The following capabilities are partial, planned, or unclear and do not yet have complete tests:

- Concurrent sale/transfer/stock writes using the deployed MySQL behavior.
- Preventing every direct stock quantity change outside movement-backed workflows.
- Purchase draft-to-receive and transfer dispatch/receipt transitions, which are not implemented.
- Duplicate checkout/action idempotency.
- Return eligibility, original-sale/purchase linkage, refund allocation, and VAT reversal.
- Debtor due dates, aging, statements, credit limits, and collection workflow.
- Immutable payment and completed-transaction reversal behavior.
- Fuel-suffix part-code rules.
- Browser behavior, accessibility, report CSV authentication, and JavaScript interactions.

## Test report for AI work

At handoff, an AI agent must report:

1. Tests added or changed.
2. Commands executed.
3. Results and any failures.
4. Checks not run and why.
5. Remaining risk or unverified behaviour.
