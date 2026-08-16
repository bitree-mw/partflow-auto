# PartFlow Auto Feature and Component Inventory

## Purpose

This document is the checklist for reviewing what PartFlow Auto already implements and what remains necessary. It is requirements-based because the Laravel source repository was not available during this documentation pass.

When the repository is available, assign each feature one status:

- **Implemented** — code, database, UI, authorization, and tests are present and working.
- **Partial** — some required layers or business cases are missing.
- **Planned** — approved but not implemented.
- **Not present** — absent and not currently approved.
- **Needs clarification** — business behaviour must be decided before implementation.

Do not label a feature Implemented merely because a menu item, route, model, or migration exists.

## Core feature matrix

| Feature | Business capability | Necessary backend/database parts | Necessary Blade/Tailwind parts | Critical verification |
| --- | --- | --- | --- | --- |
| Authentication | Staff securely enter and leave the system through the Blade application or API clients. | Users, session auth, Sanctum, middleware, login/logout actions, CSRF protection, and token handling where used. | Session login form, validation errors, logout action, and expired-session handling. | Web/API guard separation, protected routes, logout invalidation, and disabled/invalid user behaviour. |
| Roles and permissions | Management and sales staff have appropriate access. | Roles/permissions or policies/gates, user assignments, authorization tests. | Permission-aware navigation and management screens. | Server denial, privilege escalation, and site scope. |
| Sites/branches | Inventory and operations are separated by location. | Sites, user-site access, site-scoped queries and indexes. | Active-site selector/context and scoped lists. | Cross-site data leakage and unauthorized writes. |
| Part catalogue | Maintain sellable car parts and pricing information. | Parts, categories, brands, units, prices, VAT state, archive rules. | Search, list, create, edit, view, filters. | Unique identifiers, validation, safe archive behaviour. |
| Part-code generation | Generate consistent codes including fuel suffixes. | Generator/service, uniqueness constraint, petrol `-I`, diesel `-D`, hybrid `-H`. | Generated-code preview/display and error handling. | Collision handling and unsupported fuel cases. |
| Vehicle compatibility | Find parts by compatible vehicle specification. | Makes, models, years/ranges, engines, fuel types, pivot/compatibility records. | Compatibility form, filters, and readable badges/details. | Invalid combinations, duplicates, year boundaries. |
| Suppliers | Maintain purchase sources. | Suppliers, contacts, authorization, relationships. | Supplier list, form, detail/history. | Duplicate/invalid data and delete/archive effects. |
| Purchases | Record incoming stock and supplier costs. | Purchase headers/items, statuses, totals, receipt transaction, stock movements. | Purchase form, item editor, list/detail, receive action. | Atomic stock increase, repeat receipt, cancellation. |
| Site inventory | Know stock available at each site. | Inventory balances or derived movement ledger, thresholds, site/part uniqueness. | Site stock table, filters, low-stock indicators. | Balance accuracy and query performance. |
| Stock adjustments | Correct stock with accountability. | Adjustment record, reason, user, transaction, movement. | Controlled form, confirmation, history. | Permission, reason requirement, negative-stock rule. |
| Stock transfers | Move parts between company sites. | Transfer headers/items, statuses, source/destination, dispatch/receipt movements. | Create, approve/dispatch/receive screens, status timeline. | Valid transitions, availability, balanced site effects. |
| Point of sale | Sales staff quickly complete a sale. | Sale/service, lines, server totals, stock transaction, customer/payment links. | Search, cart, customer, totals, checkout, receipt. | Overselling, duplicate checkout, authorization, atomicity. |
| Discounts and VAT | Apply permitted discounts and tax treatments. | Discount authorization/rules, VAT configuration/exemption, decimal policy. | Transparent line/order totals and restricted controls. | Rounding, limits, inclusive/exclusive/exempt cases. |
| Customers | Store buyers used for sales and credit. | Customers, contacts, optional credit settings, site/company scope. | List, create, edit, view, sale history. | Duplicate identification and access rules. |
| Debtors/credit sales | Track amounts customers owe. | Credit sale/balance records or ledger, due data, statuses. | Debtor list, statement, aging/balance views. | Balance reconciliation and credit authorization. |
| Payments | Record debt settlement and payment destinations. | Payments, allocations, methods, bank/mobile-money destination, audit fields. | Payment form, history, printable acknowledgment. | Overpayment, destination validation, atomic allocation. |
| Dashboard | Give management and staff useful summaries. | Scoped aggregate queries/query objects and caching only if justified. | KPI cards, alerts, recent activity, site/date filters. | Totals agree with source transactions and permissions. |
| Reports | Support operational and management decisions. | Dedicated query objects/repositories for complex reports, pagination/export. | Filters, tables, totals, export controls if implemented. | Date/site boundaries, totals, performance, access. |

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

## Repository review procedure

When the application code is supplied, review it in this order:

1. Record the Laravel, MySQL, Sanctum, Tailwind, Vite, and test-framework versions. PHP is confirmed as 8.4.
2. List web/API routes, middleware, and public endpoint contracts.
3. Map migrations and models into a relationship diagram or table.
4. Map controllers to Form Requests, services, Resources, policies, and views.
5. Inventory Blade pages, components, navigation, and site-aware UI.
6. Run migrations and the existing test suite in a safe test environment.
7. Trace one purchase, transfer, sale, and debtor payment end to end.
8. Reconcile stock and financial totals against their source records.
9. Assign each feature a status from this document.
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

## Optional or unconfirmed capabilities

Keep these inactive until the business approves them and repository evidence exists.

- Customer returns, refunds, and sale reversals. Define stock, payment, VAT, and approval behaviour first
- Supplier returns and purchase reversals. Define stock and supplier-balance behaviour first.
- Offline POS. Define synchronization, conflict, security, and duplicate-sale handling before implementation.

<!-- INACTIVE: Quotations, layaway, or sales orders. Define conversion and stock-reservation rules first. -->
<!-- INACTIVE: Barcode generation and scanning. Confirm hardware, barcode format, and fallback workflow. -->
<!-- INACTIVE: Part image uploads or object storage. Define storage, limits, privacy, and cleanup. -->
<!-- INACTIVE: Expenses, cash drawers, and daily reconciliation. Define accounting and permissions first. -->
<!-- INACTIVE: Approval workflows for discounts, adjustments, purchases, or transfers. Define thresholds and roles. -->
<!-- INACTIVE: Email, SMS, or in-app notifications. Define events, recipients, retry behaviour, and provider. -->
<!-- INACTIVE: Online payment gateway. Bank/mobile-money destination recording does not by itself imply gateway processing. -->
<!-- INACTIVE: Queues, scheduled tasks, Redis, WebSockets, or realtime dashboards. Activate only for a confirmed need. -->
