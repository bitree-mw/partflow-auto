# PartFlow Auto Feature and Component Inventory

## Purpose

This document is the verified feature inventory for PartFlow Auto. It was reconciled against the Laravel routes, migrations, models, Form Requests, services, controllers, API Resources, middleware, Blade views, configuration, and tests in the current repository working tree on 2026-08-16.

The status describes the complete business capability, not merely the presence of a route, model, migration, or menu item. Evidence paths identify the primary implementation; related classes may exist elsewhere in the same module.

Use these statuses consistently:

- **Implemented** — the currently supported scope has code, persistence, authorization, and an operational interface. Important limitations may still be recorded.
- **Partial** — a usable subset exists, but required workflow, integrity, interface, authorization, or test coverage is missing.
- **Planned** — the capability or correction is an approved target but is not implemented.
- **Inactive optional** — the capability is neither part of the active system nor approved current work.
- **Needs clarification** — implementation would require a business decision that the code and documentation do not provide.

Do not label a feature Implemented merely because a menu item, route, model, or migration exists.

## Verified feature status matrix

| Feature | Status | Verified implementation and limitation | Primary evidence |
| --- | --- | --- | --- |
| Email notifications and cron delivery | **Implemented — low-stock scope** | Stock-change emails and a Monday stock digest use persistent queued delivery. Opt-in cPanel cron processes the database queue in bounded runs with overlap locks. Payment reminders remain absent. Hosting SMTP delivery requires staging verification. | `app/Services/LowStockNotificationService.php`, `app/Services/ScheduledReminderService.php`, `routes/console.php`, `docs/CPANEL_DEPLOYMENT.md` |
| Grouped schema installation | **Implemented — empty databases only** | Six schema groups cover the 46 historical migrations through September 2026. The installer rejects existing tables/views and preserves historical migration names for future upgrades. Existing database imports retain their own migration ledger. Schema equivalence is tested on SQLite; target MySQL import compatibility is unverified. | `database/baseline`, `app/Console/Commands/InstallSchemaBaseline.php`, `tests/Feature/SchemaBaselineTest.php` |
| Authentication | **Partial** | Session login/logout and Sanctum register/login/me/logout exist. Blade login also creates and stores a personal access token for internal API dispatch; password reset, explicit auth throttling, and a complete registration-role policy are absent. | `app/Http/Controllers/Web/AuthSessionController.php`, `app/Services/AuthService.php`, `app/Http/Controllers/Api/AuthController.php`, `routes/web.php`, `routes/api.php` |
| Roles and permissions | **Implemented** | Custom roles store JSON permissions. `RequirePermission` supports exact and wildcard permission checks, and Blade navigation is permission-aware. No policy classes are currently used. | `app/Models/Role.php`, `app/Models/User.php`, `app/Http/Middleware/RequirePermission.php`, `bootstrap/app.php` |
| Sites and user-site access | **Implemented** | Active user-site assignments and operation flags are enforced in Form Requests, services, API reads, Blade reads, reports, dashboards, payments, and stock workflows. System administrators with `*` may use all active sites; transfers require access to both sites. | `app/Models/UserSiteAccess.php`, `app/Services/SiteAccessService.php`, `tests/Feature/SiteAccessAuthorizationTest.php` |
| User administration | **Partial** | Blade settings can create, edit, deactivate/reactivate, reset passwords, assign roles, and select a primary site, with self-deactivation and last-administrator guards. Role and user-site assignment APIs exist, but there is no conventional authenticated `/api/users` CRUD resource or full multi-site capability editor. Public registration remains outside the administrator-managed workflow. | `app/Http/Controllers/Api/RoleController.php`, `app/Http/Controllers/Api/UserSiteAccessController.php`, `app/Http/Controllers/Web/AdminSettingsController.php`, `resources/views/settings/index.blade.php`, `routes/api.php` |
| Super admin console | **Implemented** | `/suadmin` uses a password-only session (bcrypt hash in `SUADMIN_PASSWORD_HASH`, 30-minute idle and 8-hour absolute limits, 5 attempts per minute). It creates, edits, (de)activates and soft-deletes sites, and manages every user and administrator account. Normal administrators can only edit site details and (de)activate sites; deactivation and deletion require zero on-hand and reserved stock and no draft or pending documents. API site writes are closed. | `routes/suadmin.php`, `app/Services/SuperAdminAuthService.php`, `app/Services/SiteService.php`, `app/Services/UserAccountService.php`, `tests/Feature/SuperAdminConsoleTest.php` |
| Audit log | **Partial** | Append-only `audit_logs` records staff sign-ins (success, failure, logout), super admin access, user, role, site-access and site changes, and business settings changes, with actor, IP and field-level before/after values (passwords redacted). Sales, purchases, stock and payment transactions are not audited here; they keep their own movement and payment histories. Entries start from deployment of the migration. | `app/Services/AuditLogService.php`, `app/Models/AuditLog.php`, `resources/views/suadmin/audit-logs.blade.php` |
| Subscription packages | **Implemented** | The super admin selects Ignition, Drive, Overdrive or Autopilot (`config/packages.php`, stored as the `subscription_package` business setting; default Autopilot). Routes use `feature:<key>` middleware and services enforce the same rules: Ignition allows one active branch, gives every active user that branch without site assignments, requires sales to be paid in full, and hides transfers, branch access, expenses, customer balances, debtor reports, low-stock emails and custom logo/colours. Overdrive adds POS vehicle fitment search and CSV exports; Autopilot adds a 24/7 support contact card configured in `/suadmin`. Switching off a feature keeps its data. Limits: POS product cards rendered by `pos.js` still show fitment counts, and historical credit balances remain in the database on Ignition. | `app/Services/PackageService.php`, `app/Http/Middleware/RequireFeature.php`, `routes/suadmin.php`, `tests/Feature/SubscriptionPackageTest.php` |
| Company branding | **Implemented** | Administrator settings persist the company name, validated logo image, and primary, secondary, and tertiary colors. Shared layout context applies the saved branding to login, back-office, and POS screens. | `app/Services/BusinessSettingsService.php`, `app/Services/SystemConfigurationService.php`, `resources/views/settings/index.blade.php`, `tests/Feature/BusinessBrandingSettingsTest.php` |
| Product catalogue | **Implemented** | Products, product types, brands, fuel types, tax profiles, references, active state, selling and minimum-selling prices, API Resources, API CRUD, Blade management, search, and tests exist. New product floors default to 20% below selling price. | `app/Models/Product.php`, `app/Services/ProductService.php`, `app/Http/Controllers/Api/ProductController.php`, `resources/views/catalog/products`, `tests/Feature/SalesDiscountPolicyTest.php` |
| Part-code generation | **Partial** | The service generates unique compact or generic product codes, but it does not implement the documented petrol `-I`, diesel `-D`, hybrid `-H`, or electric `-E` suffix convention. | `app/Services/ProductService.php` |
| Vehicle compatibility | **Implemented** | Makes/models, engine and variant data, fuel types, a primary model link, additional compatibility records, API search, Blade forms, and duplicate controls exist. | `app/Models/CarModel.php`, `app/Models/ProductCompatibility.php`, `app/Services/CarModelService.php`, `resources/views/catalog/car-models` |
| Customers and suppliers | **Implemented — basic** | A shared `contacts` model supports customer/supplier flags, API CRUD, Blade directories, purchase selection, POS selection, and basic tests. Dedicated customer statements and supplier transaction-history pages are not included in this status. | `app/Models/Contact.php`, `app/Services/ContactService.php`, `app/Http/Controllers/Web/ContactDirectoryController.php` |
| Purchases | **Partial** | Draft and completed purchases, line costs, server totals, optional initial payments, stock receipt movements, API endpoints, Blade create/list/edit, and database transactions exist. A draft cannot later be received through an explicit action endpoint. | `app/Services/InventoryDocumentService.php`, `app/Http/Controllers/Api/PurchaseController.php`, `resources/views/purchases` |
| Per-site inventory | **Partial** | Site/product balances, reserved quantity, thresholds, uniqueness, filters, low-stock indicators, and movement-backed domain workflows exist. Direct SiteStock create/update/delete endpoints can still change or remove quantities without a stock movement. | `app/Models/SiteStock.php`, `app/Services/SiteStockService.php`, `routes/api.php` |
| Stock movements | **Implemented** | Movement records preserve type, signed change, before/after balance, document/item references, notes, actor, and timestamp. Row locking and negative/over-reserved stock checks are applied by `StockMovementService`. | `app/Models/StockMovement.php`, `app/Services/StockMovementService.php` |
| Stock adjustments and stock takes | **Implemented** | Adjustment and count documents run transactionally, require operation permission, create movements on approval, and require a reason when a stock-take variance exists. Blade stock-take flows and regression tests exist. | `app/Http/Requests/InventoryDocument/StoreStockAdjustmentRequest.php`, `app/Http/Requests/InventoryDocument/StoreStockTakeRequest.php`, `tests/Feature/ExampleTest.php` |
| Stock transfers | **Partial** | Transfer documents and items preserve source/destination and create balanced `transfer_out`/`transfer_in` movements when completed. Drafts exist, but dispatch, receipt, rejection, cancellation, processed quantities, and transition endpoints do not. | `app/Http/Controllers/Api/TransferController.php`, `app/Services/InventoryDocumentService.php`, `resources/views/catalog/sites/transfers` |
| Sales and POS | **Partial** | POS search, current-site stock, customer/payment selection, trusted server catalogue price/cost/tax snapshots, live discount feedback, totals, stock deduction, movements, balances, API checkout, Blade checkout, and regression tests exist. Receipt printing, idempotency, and void/reversal actions remain absent. | `app/Http/Controllers/Web/PosController.php`, `app/Http/Controllers/Api/SaleController.php`, `app/Services/InventoryDocumentService.php`, `tests/Feature/SalesDiscountPolicyTest.php` |
| Discounts and VAT | **Partial** | Tax profiles and server-side tax/discount allocation exist. An admin-configured maximum percentage and each product's minimum selling price are enforced transactionally, using the stricter limit. Tax exemption and inclusive/exclusive policy details are not fully defined. | `app/Services/DiscountPolicyService.php`, `app/Services/InventoryDocumentService.php`, `resources/views/settings/index.blade.php` |
| Sale and purchase returns | **Partial** | Return document types, API create/list/show endpoints, totals, site authorization, and stock movements exist. Returns are not linked to original transactions and do not implement eligibility, refund, VAT reversal, or approval rules. | `app/Http/Controllers/Api/SaleReturnController.php`, `app/Http/Controllers/Api/PurchaseReturnController.php`, `app/Services/InventoryDocumentService.php` |
| Debtors and credit sales | **Partial** | Sales retain total, paid, balance, and payment status; contacts have a credit-limit field; reports expose customer balances. The credit limit is not enforced and there is no debtor ledger, due date, aging, credit authorization, or statement workflow. | `app/Models/Contact.php`, `app/Models/InventoryDocument.php`, `app/Services/PaymentService.php`, `app/Repositories/ReportRepository.php` |
| Payments and payment accounts | **Partial** | Payment-account CRUD, payment history, partial/full balance calculation, overpayment prevention, site access, and row locking in the API create/delete flow exist. Payments are still hard-deleted rather than reversed, and `createForDocument` relies on its caller's transaction for locking. | `app/Models/Payment.php`, `app/Services/PaymentService.php`, `app/Http/Controllers/Api/PaymentAccountController.php` |
| Expenses | **Implemented — basic** | Expense categories, expense CRUD through the API, optional payment-account/site relationships, permissions, API Resources, and expense reports exist. The web back office (`/back-office/expenses`, Drive package and higher, `purchases.manage`) records, filters, totals and edits expenses and adds categories; it deliberately has no delete because expenses are not soft-deleted and no reversal policy exists (the API `DELETE` still hard-deletes). The dashboard shows the month-to-date expense total. Accounting-period and reconciliation workflows are not claimed. | `app/Models/Expense.php`, `app/Services/ExpenseService.php`, `app/Http/Controllers/Api/ExpenseController.php`, `app/Http/Controllers/Web/ExpensesController.php`, `tests/Feature/ExpensesPageTest.php` |
| Dashboard and alerts | **Partial** | Repository-backed sales, profit, stock, debtor, creditor, inventory-value, branch-performance, and alert queries exist with site scoping. Inventory value uses the lowest authorized selling value after the admin discount cap and product floor; purchase-cost value remains a comparison. Custom date ranges and debtor aging are not implemented. | `app/Repositories/DashboardRepository.php`, `app/Services/DashboardService.php`, `app/Services/AlertService.php`, `resources/views/dashboard` |
| Reports and CSV export | **Partial** | Site-scoped sales, purchase, inventory valuation, creditor, debtor, profit, payment, movement, transfer, variance, and expense datasets exist. The Blade reports page exposes five primary full-row CSV downloads through a validated session-authenticated export route; most JSON report collections remain unpaginated, and debtor aging/due dates are not implemented. | `app/Repositories/ReportRepository.php`, `app/Services/ReportExportService.php`, `app/Http/Controllers/Api/ReportController.php`, `app/Http/Controllers/Web/ReportsController.php`, `resources/views/reports/index.blade.php` |
| Testing | **Partial** | PHPUnit feature tests cover authentication, core Blade pages, catalogue operations, stock takes, purchases, POS price/quantity guards, commands, and site access. Concurrency, state transitions, returns/refunds, debtor aging, reversals, and browser behavior are not covered. | `tests/Feature`, `tests/Unit`, `phpunit.xml` |

## Confirmed planned work

The following changes are active targets but are not implemented:

- Remove direct SiteStock quantity creation, mutation, and deletion from the public API. Quantity changes must go through a stock adjustment or stock take so that every change produces a movement; direct editing may remain only for `low_stock_level`.
- Add an explicit purchase receiving action and allowed draft-to-received transition.
- Add transfer request/approval/dispatch/receipt transitions with duplicate-processing protection.
- Replace destructive payment and completed-transaction correction with approved void or compensating reversal records.
- Add debtor due dates, aging, statements, credit authorization, and a reconciled history or ledger.
- Align part-code generation with the final approved fuel-suffix rule without changing existing codes unexpectedly.

## Business rules requiring clarification

Do not implement these areas until the listed behavior is confirmed:

- Which purchase fields remain editable after draft creation, who may receive, and whether partial receiving is supported.
- The complete transfer state machine, approval thresholds, partial dispatch/receipt rules, rejection, cancellation, and damaged/missing-stock handling.
- Return eligibility, original-document linkage, return windows, refund destination, payment effect, VAT reversal, and approval.
- Whether a payment correction uses void plus replacement, a compensating entry, or another immutable reversal model.
- Credit-sale authorization, limits, due dates, aging bands, collection status, and customer-statement format.
- Discount limits, whether discounts are fixed or percentage based, and which roles may override price, tax profile, or discount.
- The exact petrol/diesel/hybrid/electric part-code suffix rule and how existing codes remain stable.
- The costing method used by stock valuation and profit reporting.
- Whether reservation documents will become an active stock-hold workflow and how reservations expire or release.
- Whether expenses may be global/unassigned or must always belong to an active site.

## Necessary cross-cutting parts

Every implemented feature should include the relevant parts below:

### Backend

- Routes with authentication and authorization middleware.
- Thin controllers.
- Form Requests for validation and request authorization.
- Services for business actions and database transactions.
- Models with relationships, casts, scopes, and safe assignment rules.
- API Resources and the standard `ApiResponse` envelope for API output.
- Policies, gates, or the existing permission mechanism.
- Events, notifications, or jobs only where an actual requirement exists.

### Database

- Migrations with foreign keys, indexes, uniqueness, and appropriate decimal/integer types.
- Site ownership or scope wherever records are location-specific.
- Status and audit fields for stateful transactions.
- Stock movements or an equally auditable inventory strategy.
- Reversal/void strategy for posted business transactions.
- Seeders only for stable reference data or development/demo needs.

### Frontend

- Blade screen or component for the authorized workflow.
- Tailwind styling consistent with the application.
- Validation, empty, error, success, and permission-denied states.
- Filters and server pagination for large lists.
- Responsive and keyboard-accessible controls.
- Clear current-site and transaction-status context.

### Quality and operations

- Feature tests for success, validation, authorization, site isolation, and failure rollback.
- Unit tests for complex calculations or state rules.
- Logging that avoids credentials and sensitive financial/customer data.
- Documented environment variables without secrets in source control.
- Backup, restore, deployment, and monitoring procedures confirmed for production.

## Future repository review procedure

For future reconciliation passes, review in this order:

1. Record the Laravel, MySQL, Sanctum, Tailwind, Vite, and test-framework versions. PHP is confirmed as 8.4.
2. List web/API routes, middleware, and public endpoint contracts.
3. Map migrations and models into a relationship diagram or table.
4. Map controllers to Form Requests, services, Resources, policies, and views.
5. Inventory Blade pages, components, navigation, and site-aware UI.
6. Run migrations and the existing test suite in a safe test environment.
7. Trace one purchase, transfer, sale, and debtor payment end to end.
8. Reconcile stock and financial totals against their source records.
9. Reconfirm each feature status from current code and tests.
10. Produce a prioritized gap list: data-integrity/security first, business-critical gaps second, usability and maintainability afterward.

## Required repository-review output

The completed review should report:

- Confirmed technology versions and architecture.
- Implemented feature list with evidence paths.
- Partial or missing features and affected layers.
- Database schema and integrity risks.
- API consistency and breaking-contract risks.
- Frontend coverage and usability/accessibility gaps.
- Test coverage and untested critical workflows.
- Security and authorization concerns.
- Recommended work grouped as critical, high, medium, and optional.

## Inactive optional features

These capabilities are not active and are not approved current work. Keep them inactive until the business defines the requirement.

- Offline POS. Define synchronization, conflict, security, and duplicate-sale handling before implementation.
- Quotations, layaway, sales orders, and quotation-to-sale conversion.
- Barcode generation and scanner workflows. Existing references and text search are not barcode generation/scanning.
- Part-image upload, object storage, image transformation, and cleanup workflows.
- Cash drawers, till opening/closing, and daily cash reconciliation beyond recorded expenses and payment accounts.
- SMS and provider-backed notifications beyond the implemented low-stock emails.
- Online payment gateways. Recording bank or mobile-money destinations is not gateway integration.
- Accounting, ERP, supplier-catalogue, vehicle-data, or other third-party integrations.
- Redis-backed features, scheduled jobs beyond the implemented stock reminders and cron queue worker, WebSockets, and realtime dashboards.
- Two-factor authentication, named Sanctum token abilities, OpenAPI generation, and API idempotency keys.
- Browser-test automation and a JavaScript unit-test runner.
