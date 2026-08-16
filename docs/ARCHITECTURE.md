# Application Architecture

## Purpose

This document explains how **PartFlow Auto** is structured. It helps developers and AI coding agents understand the system, place code in the correct layer, preserve established conventions, and safely extend the application without breaking existing functionality.

This document records the architecture verified in the current repository working tree on 2026-08-16. Normative rules are identified separately from implemented behavior.

## System overview

PartFlow Auto is an inventory management and point-of-sale application for a company that purchases, stores, transfers, and sells motor vehicle parts. It provides a central record of parts, suppliers, purchases, stock quantities, stock movements, sales, customers, debtors, payments, and operational reports.

The system is used mainly by management and sales staff. Management oversees users, sites or branches, products, pricing, purchases, stock, transfers, debtors, payments, and reports. Sales staff use the point-of-sale functions to search for compatible parts, serve customers, process sales, record approved discounts and payment methods, and issue transaction records.

PartFlow Auto supports multiple business sites or branches. Stock must be tracked separately for each site while remaining centrally visible to authorized management. Transfers between sites must create traceable stock movements and must not silently change quantities. Business-critical operations include receiving purchases, adjusting stock, transferring stock, completing sales, deducting sold quantities, applying VAT and discounts, recording customer debts, allocating payments, and maintaining reliable transaction histories.

## Technology stack

| Area | Technology | Description |
| --- | --- | --- |
| Backend | Laravel 12.62.0 (`^12.0`) | Locked framework version and Composer constraint. |
| Language | PHP 8.4 runtime; Composer permits `^8.2` | The local Herd runtime was verified as PHP 8.4. |
| Database | MySQL application target; SQLite is the default config and test driver | MySQL uses `utf8mb4` / `utf8mb4_unicode_ci`. The deployed MySQL server version is not verified. |
| Frontend | Laravel Blade | Provides server-rendered pages and reusable Blade components. |
| Styling | Tailwind CSS | Provides the design system, responsive layouts, colours, spacing, forms, and interface styling. |
| Frontend build | Vite 7.3.5, Tailwind CSS 4.3.1, Laravel Vite Plugin 2.1.0 | Locked versions from `package-lock.json`; Axios 1.18.1 is also installed. |
| Authentication | Laravel Sanctum 4.3.2 and Laravel session authentication | Sanctum protects API access; session authentication protects Blade/web access. Both flows require server-side authorization. |
| Hosting | Not verified | Production hosting and deployed database versions remain operational documentation gaps. |

<!-- INACTIVE: Redis is used for cache, sessions, or queues. Activate only after confirming the project configuration. -->
<!-- INACTIVE: Object storage such as Amazon S3 is used for part images, invoices, or documents. Activate only if implemented. -->
<!-- INACTIVE: WebSockets provide real-time stock or POS updates. Activate only if implemented. -->

## Application structure

The backend follows this standard request flow:

`Route → Controller → Form Request → Service → Model/Query → API Resource → ApiResponse`

The Blade frontend primarily uses Laravel web routes protected by session authentication and CSRF middleware. Protected API routes use Laravel Sanctum. Backend validation and authorization remain mandatory even when Blade pages hide or disable an action.

### Route

Routes define URLs, HTTP methods, middleware, route model binding, and access boundaries.

- API routes belong in `routes/api.php`.
- Blade and browser-facing routes belong in `routes/web.php`.
- Routes must not contain calculations, stock changes, or other business logic.
- Protected routes must use the project's authentication and authorization middleware.
- Browser-facing protected routes use session authentication; protected API routes use Sanctum.

### Controller

Controllers receive validated requests, pass data to services, and return Blade views or standardized API responses.

- API controllers belong in `app/Http/Controllers/Api`.
- Controllers must remain thin.
- Controllers must not calculate totals, VAT, discounts, balances, or stock quantities.
- Controllers must not coordinate multi-model database writes.
- Controllers must not duplicate Form Request validation.

### Form Request

Form Requests validate incoming data and may authorize the requested action.

- Store and update operations should use separate Form Requests when their rules differ.
- Foreign keys, quantities, prices, discounts, VAT options, dates, statuses, and nested line items must be validated.
- The application must use `$request->validated()` instead of trusting `$request->all()`.
- Cross-record rules, stock availability, site access, and state transitions belong in the service layer.

### Service

Services contain business rules and coordinate related operations.

Services are responsible for:

- Generating approved part codes.
- Calculating purchase and selling totals.
- Applying discounts and VAT rules.
- Receiving purchased stock.
- Processing stock adjustments.
- Processing site-to-site stock transfers.
- Completing sales and deducting stock.
- Recording customer debts and repayments.
- Recording the destination of bank or mobile-money payments.
- Using database transactions for operations that must succeed or fail together.

### Model or query

Models represent database entities and should mainly contain:

- Fillable fields.
- Attribute casts.
- Relationships.
- Query scopes.
- Small model-native behaviour.

Complex reports, dashboard summaries, advanced filters, and aggregation queries may use repositories or dedicated query classes. Ordinary CRUD operations should use Eloquent directly through the service layer.

### API Resource

API Resources control the public representation of models and collections.

- Resources must expose only approved fields.
- Sensitive internal fields must not be returned accidentally.
- Relationships should be included intentionally.
- Pagination metadata must follow the existing response convention.

### ApiResponse

`ApiResponse` provides the standard success and error response envelope.

- All API endpoints must preserve the established response structure.
- API endpoints must not return inconsistent custom response formats.
- Public errors must not expose SQL messages, stack traces, credentials, or internal file paths.

## Main modules

The following module map uses the classes and route families present in the repository.

| Module | Purpose | Main models or records | Route area |
| --- | --- | --- | --- |
| Authentication and users | Session login/logout, Sanctum token authentication, roles, and access control. Public registration exists; conventional API user CRUD does not. | `User`, `Role`, `PersonalAccessToken` | `/api/auth/*`, `/api/roles`, web `/login` |
| Sites or branches | Business locations and explicit per-user operation flags. | `Site`, `UserSiteAccess` | `/api/sites`, `/api/user-site-accesses` |
| Part catalogue | Products, types, brands, fuel types, references, prices, tax profiles, thresholds, and compatibility. | `Product`, `ProductType`, `Brand`, `FuelType`, `ProductReference`, `TaxProfile` | `/api/products`, `/api/product-types`, `/api/brands`, `/api/fuel-types` |
| Vehicle compatibility | Structured vehicle make/model and additional product compatibility records. | `CarMake`, `VehicleModel`, `CarModel`, `ProductCompatibility` | `/api/car-models` and product payloads |
| Contacts | Customers and suppliers share one record with role flags. | `Contact` | `/api/contacts` |
| Inventory documents | Purchases, sales, transfers, adjustments, stock takes, and returns share generic header/item tables and service logic. | `InventoryDocument`, `InventoryDocumentItem` | `/api/purchases`, `/api/sales`, `/api/transfers`, `/api/stock-adjustments`, `/api/stock-takes`, return routes |
| Inventory | Per-site balances, reservations, thresholds, and auditable movements. Direct SiteStock quantity CRUD remains an integrity gap. | `SiteStock`, `StockMovement` | `/api/site-stocks`, `/api/stock-movements` |
| Customers and debtors | Customer contacts, an unenforced contact credit-limit field, and sale-level paid/balance fields. Dedicated ledger, due date, aging, and statement records do not exist. | `Contact`, `InventoryDocument`, `Payment` | `/api/contacts`, `/api/payments`, debtor report |
| Payments | Records cash, bank, and mobile-money payments and the approved receiving account or destination. | `Payment`, `PaymentMethod`, `PaymentAccount` | `/api/payments`, `/api/payment-accounts` |
| Pricing, VAT, and discounts | Product prices and tax profiles feed server-side document calculations; several approval and rounding rules need clarification. | `Product`, `TaxProfile`, `InventoryDocumentItem` | Sales, purchases, products, and settings routes |
| Expenses | Basic categories, expenses, optional site/account assignment, and reporting. | `Expense`, `ExpenseCategory` | `/api/expenses`, `/api/expense-categories` |
| Dashboard and reports | Repository-backed summaries, operational reports, and CSV export. | `DashboardRepository`, `ReportRepository` | `/api/dashboard/summary`, `/api/reports/*` |

Returns and expenses are present. Returns are partial because they are not linked to original documents and lack approved refund/VAT-reversal rules. See `docs/FEATURES_AND_COMPONENTS.md` for status classifications.
<!-- INACTIVE: Quotations are converted into sales. Activate only if implemented. -->
<!-- INACTIVE: Barcode generation and scanning are implemented. Activate only if supported by the current system. -->

## Part identification and compatibility

Each part should have a stable, unique identifier or generated part code. The established PartFlow Auto code convention may combine relevant values such as brand, vehicle model, part identifier, and fuel suffix:

- `-I` for petrol.
- `-D` for diesel.
- `-H` for hybrid.
- `-E` for electric.

The exact code-generation rule must be confirmed from the existing service before it is changed. Previously generated codes must remain stable unless an approved migration strategy exists.

A part may be compatible with more than one vehicle configuration. Compatibility must therefore be modelled as structured relational data rather than stored only as a description string.

## Roles and permissions

| Role | Description | Main permissions |
| --- | --- | --- |
| Administrator | Maintains system-level configuration and access. | Manage users, roles, permissions, sites, settings, and all authorized modules. |
| Management | Oversees business operations across permitted sites. | Manage parts, suppliers, purchases, pricing, stock, transfers, customers, debtors, payments, and reports. |
| Sales Staff | Conducts customer-facing sales operations at assigned sites. | Search parts, view compatibility and selling prices, manage permitted customers, create sales, apply allowed discounts, and record approved payment methods. |

<!-- INACTIVE: Storekeeper or Inventory Officer is a separate role. Activate only if the business separates stock duties from management and sales. -->

Authorization must be enforced with middleware, policies, gates, Form Request authorization, or the project's established role-permission implementation. Blade visibility checks improve the interface but never replace backend authorization.

Where users can access multiple sites, access should be represented explicitly instead of assuming every user belongs permanently to only one site.

## Core transaction flows

### Purchase and stock increase — current flow

1. An authorized user records the supplier and purchase items.
2. The server validates parts, quantities, purchase prices, VAT, and site access.
3. The purchase is saved using a database transaction.
4. A purchase created as `completed` immediately increases stock; a `draft` does not. There is no later receive action.
5. Each increase creates a traceable stock movement.

### Sale and stock deduction

1. Sales staff select a site, customer where applicable, and sale items.
2. The server checks site access, current prices, stock availability, VAT, exemptions, and allowed discounts.
3. The server calculates line totals and the final amount; client totals are not trusted.
4. The sale, items, payment or debtor record, stock deductions, and stock movements are committed in one database transaction.
5. If any required step fails, the complete operation rolls back.

### Stock transfer — current flow and planned transition

1. An authorized user selects the source site, destination site, and quantities.
2. The server confirms source stock and access permissions.
3. A transfer created as `completed` immediately applies both source and destination movements in one transaction; a `draft` does not move stock.
4. Requested/dispatched/received quantity fields and separate dispatch/receipt actions are not implemented.
5. The planned state machine must prevent duplicate dispatch/receipt and receiving into the source site.

### Debtor payment

1. An authorized user records a payment against a customer debt.
2. The server validates the outstanding balance and payment destination.
3. The payment and balance adjustment occur in one transaction.
4. Historical sale and payment records remain traceable.

## Stock and financial integrity rules

- Stock must be tracked per site.
- Every stock change must have a source transaction or approved adjustment reason.
- Sales must not deduct stock from a site the user cannot access.
- Client-submitted totals, balances, discounts, costs, profits, and taxes must be recalculated or verified by the server.
- Purchase and selling prices must remain distinct.
- Completed transaction line items must preserve the price, VAT, and discount values used at the time of the transaction.
- Multi-record stock, sale, purchase, transfer, payment, and debtor operations must use database transactions.
- Financial values must use decimal database fields and explicit rounding rules.
- Reports must be derived from validated transaction records and must respect site and permission boundaries.

<!-- INACTIVE: Negative stock is allowed. Activate only if management explicitly approves the rule and the system records the resulting risk. -->
<!-- INACTIVE: Finalized sales, purchases, or payments may be edited directly. Activate only after defining audit and reversal controls. -->

## Blade frontend architecture

The frontend uses Laravel Blade and Tailwind CSS.

- Blade layouts should provide shared page structure, navigation, alerts, scripts, and styles.
- Reusable interface elements should use Blade components or established partials.
- Tailwind utility classes should follow the existing design system and responsive breakpoints.
- Pages should reuse existing tables, filters, forms, modals, status badges, pagination, and validation-error components.
- Blade templates should display data and collect input; they must not contain business calculations or database queries.
- Controllers or view models should provide prepared data to views.
- Forms must include CSRF protection where applicable.
- Validation errors, success messages, empty states, loading behaviour, and permission restrictions must be clear to users.
- The frontend must remain usable on common desktop screen sizes used at sales counters and management offices.

<!-- INACTIVE: Alpine.js is used for lightweight frontend interaction. Activate only if it exists in `package.json` or the current templates. -->
<!-- INACTIVE: Livewire components are used. Activate only if Livewire is installed and already part of the architecture. -->

## External integrations

No external integration is currently confirmed in this architecture document.

Bank and mobile-money destinations are internal payment-recording options unless the application is connected to an actual payment provider.

<!-- INACTIVE: Online payment gateway integration. Do not treat recorded bank or mobile-money destinations as gateway integration. -->
<!-- INACTIVE: Email or SMS notifications. Activate only if a provider and notification workflow are implemented. -->
<!-- INACTIVE: Third-party accounting, ERP, supplier-catalogue, or vehicle-data integration. Activate only if implemented. -->

## Background processing

No queue or scheduled task is currently confirmed in this document.

<!-- INACTIVE: Laravel queues process reports, imports, notifications, or other background jobs. When activated, document the queue driver, jobs, retries, timeouts, and failed-job handling. -->
<!-- INACTIVE: Laravel Scheduler runs recurring tasks. When activated, list each scheduled command, frequency, purpose, and failure-monitoring process. -->

## Architectural boundaries

- Frontend checks must not be trusted as the only authorization control.
- Controllers must not contain business rules, calculations, stock updates, or complex queries.
- Form Requests handle input validation; services enforce business and state-transition rules.
- API Resources must not perform database writes.
- Services should return domain results rather than Blade views or HTTP responses.
- Repositories must not be created for ordinary CRUD operations.
- Stock quantities must not be changed without a traceable stock movement.
- Sale totals, VAT, discounts, debtor balances, and profit figures must not rely solely on values submitted by the frontend.
- Existing API response structures and public contracts must be preserved unless a breaking change is explicitly approved.
- Existing database migrations that may have run in another environment must not be edited; create new migrations for schema changes.

## Verified limitations and items to clarify

| Item | Impact | Required action |
| --- | --- | --- |
| Direct SiteStock write CRUD can change/delete quantities without movements. | Stock history can diverge from balances. | Planned: restrict direct editing to `low_stock_level`; use adjustments/stock takes for quantities. |
| Purchase drafts have no receive action; transfers have no dispatch/receipt state machine. | Draft records cannot progress through controlled business transitions. | Confirm state rules, then add explicit actions. |
| Blade login creates a personal access token for internal API dispatch. | Session and token lifecycles are coupled and each login adds a token. | Decide whether the Blade UI should use session-only web actions or retain this documented bridge. |
| Payments can be hard-deleted. | Financial history can be removed instead of reversed. | Confirm and implement an immutable reversal strategy. |
| Part-code generation does not implement the documented fuel suffixes. | Generated identifiers differ from the stated business convention. | Confirm the suffix and existing-code migration/stability rules. |
| Production database server and hosting are not documented. | Deployment compatibility and operational controls remain unknown. | Record non-secret production facts. |

Add confirmed technical debt only after inspecting the existing implementation. Do not label an architectural difference as technical debt merely because another approach is preferred.
