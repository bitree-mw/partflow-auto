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
| Authentication | **Partial** | Session login/logout and Sanctum register/login/me/logout exist. Blade login also creates and stores a personal access token for internal API dispatch; password reset, explicit auth throttling, and a complete registration-role policy are absent. | `app/Http/Controllers/Web/AuthSessionController.php`, `app/Services/AuthService.php`, `app/Http/Controllers/Api/AuthController.php`, `routes/web.php`, `routes/api.php` |
| Roles and permissions | **Implemented** | Custom roles store JSON permissions. `RequirePermission` supports exact and wildcard permission checks, and Blade navigation is permission-aware. No policy classes are currently used. | `app/Models/Role.php`, `app/Models/User.php`, `app/Http/Middleware/RequirePermission.php`, `bootstrap/app.php` |
| Sites and user-site access | **Implemented** | Active user-site assignments and operation flags are enforced in Form Requests, services, API reads, Blade reads, reports, dashboards, payments, and stock workflows. System administrators with `*` may use all active sites; transfers require access to both sites. | `app/Models/UserSiteAccess.php`, `app/Services/SiteAccessService.php`, `tests/Feature/SiteAccessAuthorizationTest.php` |
| User administration | **Partial** | Blade settings can create, edit, deactivate/reactivate, reset passwords, assign roles, and select a primary site, with self-deactivation and last-administrator guards. Role and user-site assignment APIs exist, but there is no conventional authenticated `/api/users` CRUD resource or full multi-site capability editor. Public registration remains outside the administrator-managed workflow. | `app/Http/Controllers/Api/RoleController.php`, `app/Http/Controllers/Api/UserSiteAccessController.php`, `app/Http/Controllers/Web/AdminSettingsController.php`, `resources/views/settings/index.blade.php`, `routes/api.php` |
| Product catalogue | **Implemented** | Products, product types, brands, fuel types, tax profiles, references, active state, pricing fields, API Resources, API CRUD, Blade management, search, and tests exist. | `app/Models/Product.php`, `app/Services/ProductService.php`, `app/Http/Controllers/Api/ProductController.php`, `resources/views/catalog/products`, `tests/Feature/ExampleTest.php` |
| Part-code generation | **Partial** | The service generates unique compact or generic product codes, but it does not implement the documented petrol `-I`, diesel `-D`, hybrid `-H`, or electric `-E` suffix convention. | `app/Services/ProductService.php` |
| Vehicle compatibility | **Implemented** | Makes/models, engine and variant data, fuel types, a primary model link, additional compatibility records, API search, Blade forms, and duplicate controls exist. | `app/Models/CarModel.php`, `app/Models/ProductCompatibility.php`, `app/Services/CarModelService.php`, `resources/views/catalog/car-models` |
| Customers and suppliers | **Implemented — basic** | A shared `contacts` model supports customer/supplier flags, API CRUD, Blade directories, purchase selection, POS selection, and basic tests. Dedicated customer statements and supplier transaction-history pages are not included in this status. | `app/Models/Contact.php`, `app/Services/ContactService.php`, `app/Http/Controllers/Web/ContactDirectoryController.php` |
| Purchases | **Partial** | Draft and completed purchases, line costs, server totals, optional initial payments, stock receipt movements, API endpoints, Blade create/list/edit, and database transactions exist. A draft cannot later be received through an explicit action endpoint. | `app/Services/InventoryDocumentService.php`, `app/Http/Controllers/Api/PurchaseController.php`, `resources/views/purchases` |
| Per-site inventory | **Partial** | Site/product balances, reserved quantity, thresholds, uniqueness, filters, low-stock indicators, and movement-backed domain workflows exist. Direct SiteStock create/update/delete endpoints can still change or remove quantities without a stock movement. | `app/Models/SiteStock.php`, `app/Services/SiteStockService.php`, `routes/api.php` |
| Stock movements | **Implemented** | Movement records preserve type, signed change, before/after balance, document/item references, notes, actor, and timestamp. Row locking and negative/over-reserved stock checks are applied by `StockMovementService`. | `app/Models/StockMovement.php`, `app/Services/StockMovementService.php` |
| Stock adjustments and stock takes | **Implemented** | Adjustment and count documents run transactionally, require operation permission, create movements on approval, and require a reason when a stock-take variance exists. Blade stock-take flows and regression tests exist. | `app/Http/Requests/InventoryDocument/StoreStockAdjustmentRequest.php`, `app/Http/Requests/InventoryDocument/StoreStockTakeRequest.php`, `tests/Feature/ExampleTest.php` |
| Stock transfers | **Partial** | Transfer documents and items preserve source/destination and create balanced `transfer_out`/`transfer_in` movements when completed. Drafts exist, but dispatch, receipt, rejection, cancellation, processed quantities, and transition endpoints do not. | `app/Http/Controllers/Api/TransferController.php`, `app/Services/InventoryDocumentService.php`, `resources/views/catalog/sites/transfers` |
| Sales and POS | **Partial** | POS search, current-site stock, customer/payment selection, server catalogue price, totals, stock deduction, movements, balances, API checkout, Blade checkout, and regression tests exist. Receipt printing, idempotency, void/reversal actions, and complete protection from client-submitted cost data remain absent. | `app/Http/Controllers/Web/PosController.php`, `app/Http/Controllers/Api/SaleController.php`, `app/Services/PosProductSearchService.php`, `tests/Feature/HighestPriorityGuardsTest.php` |
| Discounts and VAT | **Partial** | Tax profiles and server-side tax/discount allocation exist, and price override uses a permission check. Approved discount thresholds, exemptions, inclusive/exclusive policy, and who may override each value are not fully defined. | `app/Models/TaxProfile.php`, `app/Services/InventoryDocumentService.php`, `app/Http/Controllers/Api/SaleController.php` |
| Sale and purchase returns | **Partial** | Return document types, API create/list/show endpoints, totals, site authorization, and stock movements exist. Returns are not linked to original transactions and do not implement eligibility, refund, VAT reversal, or approval rules. | `app/Http/Controllers/Api/SaleReturnController.php`, `app/Http/Controllers/Api/PurchaseReturnController.php`, `app/Services/InventoryDocumentService.php` |
| Debtors and credit sales | **Partial** | Sales retain total, paid, balance, and payment status; contacts have a credit-limit field; reports expose customer balances. The credit limit is not enforced and there is no debtor ledger, due date, aging, credit authorization, or statement workflow. | `app/Models/Contact.php`, `app/Models/InventoryDocument.php`, `app/Services/PaymentService.php`, `app/Repositories/ReportRepository.php` |
| Payments and payment accounts | **Partial** | Payment-account CRUD, payment history, partial/full balance calculation, overpayment prevention, site access, and row locking in the API create/delete flow exist. Payments are still hard-deleted rather than reversed, and `createForDocument` relies on its caller's transaction for locking. | `app/Models/Payment.php`, `app/Services/PaymentService.php`, `app/Http/Controllers/Api/PaymentAccountController.php` |
| Expenses | **Implemented — basic** | Expense categories, expense CRUD, optional payment-account/site relationships, permissions, API Resources, and expense reports exist. Accounting-period and reconciliation workflows are not claimed. | `app/Models/Expense.php`, `app/Services/ExpenseService.php`, `app/Http/Controllers/Api/ExpenseController.php` |
| Dashboard and alerts | **Partial** | Repository-backed sales, profit, stock, debtor, creditor, inventory-value, branch-performance, and alert queries exist with site scoping. The Blade dashboard includes responsive 7/14/30-day revenue movement plus stock-value and debtor/creditor comparisons; custom date ranges and debtor aging are not implemented. | `app/Repositories/DashboardRepository.php`, `app/Services/DashboardService.php`, `app/Services/AlertService.php`, `resources/views/dashboard` |
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
- Email, SMS, or provider-backed notifications.
- Online payment gateways. Recording bank or mobile-money destinations is not gateway integration.
- Accounting, ERP, supplier-catalogue, vehicle-data, or other third-party integrations.
- Redis-backed features, queues, scheduled jobs, WebSockets, and realtime dashboards.
- Two-factor authentication, named Sanctum token abilities, OpenAPI generation, and API idempotency keys.
- Browser-test automation and a JavaScript unit-test runner.
