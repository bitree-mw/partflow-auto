# Security Guidance

## Purpose

This file defines security expectations for `Partflow Auto`. It supplements `AGENTS.md` and applies to code, configuration, database work, tests, and documentation.

## Protected assets

- User accounts, credentials, tokens, and sessions.
- Personal, financial, employment, customer, or health information stored by the application.
- Business transactions, reports, stock, payments, or approval records.
- Uploaded files and generated exports.
- Database backups, logs, configuration, and infrastructure details.

## Security invariants

- Every protected action requires authentication and server-side authorization.
- Users may access only records permitted by their role, ownership, tenant, or branch scope.
- Client-supplied identifiers, prices, totals, permissions, and statuses are never trusted without validation.
- Passwords are stored only using Laravel-supported hashing.
- Secrets remain in environment or secret-management systems and are never committed.
- Public API errors do not expose internal implementation details.
- Sensitive operations maintain an appropriate audit trail.

## Verified current security state

This section records implementation findings from the current repository working tree on 2026-08-16. It does not weaken the invariants above.

### Implemented controls

- Blade routes use session authentication and CSRF middleware; protected API routes use `auth:sanctum`.
- `RequirePermission` enforces exact or wildcard role permissions on the server.
- `SiteAccessService`, Form Request authorization, service checks, scoped queries, dashboards, and reports enforce active user-site access and operation flags in the current working tree. Transfers require access to both sites.
- Core purchase, sale, transfer, stock-adjustment, stock-take, and payment operations use database transactions; stock movement writes use row locking and reject invalid negative/over-reserved balances.
- API responses use `app/Support/ApiResponse.php`, and API audit middleware records protected requests.

### Partial controls and risks

| Risk | Evidence | Required direction |
| --- | --- | --- |
| Blade login creates a Sanctum token and stores it in the session for internal API calls. | `app/Http/Controllers/Web/AuthSessionController.php` | Decide whether to retain this bridge or move web actions to session-only services/controllers; ensure token lifecycle does not accumulate unnecessary tokens. |
| Public API registration creates users without a normal administrator-managed role workflow. | `routes/api.php`, `app/Services/AuthService.php` | Confirm whether registration should remain public and which role/status new users receive. |
| No explicit login/register throttling is attached to the auth routes. | `routes/api.php`, `routes/web.php` | Add an approved throttle policy and regression tests. |
| Direct SiteStock CRUD can create, update, or delete quantities without a movement. | `routes/api.php`, `app/Services/SiteStockService.php` | Confirmed planned correction: quantity changes must use adjustments or stock takes; direct edit may remain only for `low_stock_level`. |
| Sale input protection replaces catalogue price but does not remove client-submitted `unit_cost`. | `app/Http/Controllers/Api/SaleController.php` | Derive cost snapshots from trusted stock/catalogue costing after the costing rule is confirmed. |
| Payments can be hard-deleted and balances recalculated. | `app/Services/PaymentService.php`, web/API payment routes | Replace destructive correction with the approved immutable reversal method. |
| Non-production exception responses may include file and line details. | `bootstrap/app.php` | Keep production debug disabled and consider limiting internal paths in all API responses. |
| The default system-user seeder contains the fallback literal `password`. | `database/seeders/DefaultSystemUserSeeder.php` | Require an explicit environment-supplied value or random bootstrap credential outside production fixtures. |

### Inactive optional controls

Two-factor authentication, named Sanctum token abilities, upload malware scanning, OpenAPI security generation, and route-specific rate-limit policies are not active. They remain optional until approved, except baseline auth throttling listed above, which is a recommended security correction.

### Business rules requiring clarification

- Public registration policy and the initial role/status assigned to a registrant.
- Token lifetime, device/session limits, and revocation expectations for API and Blade users.
- Immutable correction/reversal rules for payments, completed sales, purchases, transfers, and returns.
- Costing source for sale profit snapshots and who may override price, discount, or tax.
- Return/refund eligibility, approval, destination, VAT effect, and original-document linkage.

## Prohibited automatic actions

AI agents must not automatically:

- Read, display, replace, or commit `.env` secrets.
- Connect to or modify production systems.
- Run destructive database commands.
- Disable authentication, authorization, CSRF protection, rate limits, or validation to make a test pass.
- Add a hard-coded password, token, API key, bypass account, or hidden administrator route.
- Log passwords, tokens, full payment information, or unnecessary personal data.
- Upload repository code or data to an unapproved third-party service.
- Change firewall, server, hosting, DNS, or cloud configuration without exact authorization.

## Authentication and sessions

- Use the project’s established authentication method.
- Regenerate sessions after login where session authentication is used.
- Revoke or expire tokens according to documented application rules.
- Apply rate limiting to login, password reset, verification, and other abuse-sensitive endpoints.

<!-- INACTIVE: Two-factor authentication is required. Activate only if implemented or approved. -->
<!-- INACTIVE: Sanctum personal access tokens have named abilities. Activate only if token abilities are configured. -->

## Authorization

- Use policies, gates, middleware, roles, permissions, ownership checks, or scoped queries.
- Do not rely on hidden buttons or frontend route guards.
- Prevent insecure direct object reference by checking access to every requested record.
- Default to denial when permission is unclear.

## Input, output, and uploads

- Validate type, format, length, range, and allowed values.
- Escape output using framework conventions.
- Whitelist sortable and filterable database fields.
- Validate uploaded file type, size, extension, and storage visibility.
- Generate server-side filenames and prevent path traversal.

<!-- INACTIVE: Uploaded files require malware scanning. Activate only if an approved scanner is integrated. -->

## Reporting security concerns

When a likely vulnerability is found:

1. Do not exploit production or access unrelated data.
2. Record the affected file and behaviour.
3. Explain realistic impact and required conditions.
4. Propose the smallest safe fix.
5. Add a regression test where practical.
6. Avoid placing secret values or sensitive records in the report.
