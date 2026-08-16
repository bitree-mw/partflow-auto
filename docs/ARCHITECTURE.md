# Application Architecture

## Purpose

This document explains how **PartFlow Auto** is structured. It helps developers and AI coding agents understand the system, place code in the correct layer, preserve established conventions, and safely extend the application without breaking existing functionality.

This document describes the intended architecture. Before changing the application, the developer or AI agent must inspect the existing code and confirm the actual class names, routes, database tables, installed packages, and framework versions.

## System overview

PartFlow Auto is an inventory management and point-of-sale application for a company that purchases, stores, transfers, and sells motor vehicle parts. It provides a central record of parts, suppliers, purchases, stock quantities, stock movements, sales, customers, debtors, payments, and operational reports.

The system is used mainly by management and sales staff. Management oversees users, sites or branches, products, pricing, purchases, stock, transfers, debtors, payments, and reports. Sales staff use the point-of-sale functions to search for compatible parts, serve customers, process sales, record approved discounts and payment methods, and issue transaction records.

PartFlow Auto supports multiple business sites or branches. Stock must be tracked separately for each site while remaining centrally visible to authorized management. Transfers between sites must create traceable stock movements and must not silently change quantities. Business-critical operations include receiving purchases, adjusting stock, transferring stock, completing sales, deducting sold quantities, applying VAT and discounts, recording customer debts, allocating payments, and maintaining reliable transaction histories.

## Technology stack

| Area | Technology | Description |
| --- | --- | --- |
| Backend | Laravel `[VERIFY VERSION FROM composer.json]` | Handles business logic, API endpoints, validation, authorization, database access, and transaction processing. |
| Language | PHP 8.4 | Server-side programming language used by Laravel. |
| Database | MySQL 9.7.1 | Stores users, products, compatibility data, purchases, stock, sales, debts, payments, and audit information. |
| Frontend | Laravel Blade | Provides server-rendered pages and reusable Blade components. |
| Styling | Tailwind CSS | Provides the design system, responsive layouts, colours, spacing, forms, and interface styling. |
| Frontend build | Vite `[VERIFY FROM package.json]` | Compiles frontend JavaScript and Tailwind CSS assets. |
| Authentication | Laravel Sanctum and Laravel session authentication | Sanctum protects API access; session authentication protects Blade/web access. Both flows require server-side authorization. |
| Hosting | `[VERIFY PRODUCTION ENVIRONMENT]` | Record the hosting type without including credentials, private IP addresses, or secrets. |

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

The model and route names below describe the intended responsibilities. Existing names in the repository take precedence and must be verified before changes are made.

| Module | Purpose | Main models or records | Route area |
| --- | --- | --- | --- |
| Authentication and users | Login, logout, current-user information, user management, roles, and access control. | `User`, `Role`, `Permission` or the project's equivalent | `/api/login`, `/api/logout`, `/api/users` |
| Sites or branches | Represents business locations and controls which stock and transactions users may access. | `Site` or `Branch`, user-site access records | `/api/sites` or `/api/branches` |
| Part catalogue | Maintains part names, descriptions, brands, categories, units, packaging, part codes, purchase prices, selling prices, VAT settings, and low-stock levels. | `Part`, `Brand`, `Category`, `Unit` | `/api/parts`, `/api/brands`, `/api/categories` |
| Vehicle compatibility | Links parts to compatible vehicle makes, models, years, engines, and fuel types. | `VehicleMake`, `VehicleModel`, `VehicleEngine`, `PartCompatibility` | `/api/vehicles`, `/api/parts/{part}/compatibilities` |
| Suppliers | Maintains suppliers used when purchasing stock. | `Supplier` | `/api/suppliers` |
| Purchases | Records stock purchased from suppliers, actual purchase prices, quantities, and receiving status. | `Purchase`, `PurchaseItem` | `/api/purchases` |
| Inventory | Tracks stock available at each site and records every quantity increase, decrease, correction, or reservation. | `Stock`, `StockMovement`, `StockAdjustment` | `/api/stocks`, `/api/stock-adjustments` |
| Stock transfers | Moves stock between sites using controlled transfer statuses and traceable transfer items. | `StockTransfer`, `StockTransferItem` | `/api/stock-transfers` |
| Point of sale and sales | Searches parts, builds a sale, applies approved pricing rules, records payment, completes the sale, and deducts stock. | `Sale`, `SaleItem` | `/api/sales`, `/api/pos` |
| Customers and debtors | Maintains customers, credit sales, outstanding balances, payment allocations, and debtor histories. | `Customer`, `CustomerAccount`, `DebtorTransaction` or project equivalents | `/api/customers`, `/api/debtors` |
| Payments | Records cash, bank, and mobile-money payments and the approved receiving account or destination. | `Payment`, `PaymentMethod`, `PaymentAccount` | `/api/payments`, `/api/payment-accounts` |
| Pricing, VAT, and discounts | Enforces selling-price, tax, exemption, and discount rules without trusting client-calculated totals. | Part pricing fields or dedicated pricing records | Normally handled through parts, sales, and settings routes |
| Dashboard and reports | Provides stock, low-stock, sales, purchases, profit, debtors, transfers, and performance summaries. | Reporting queries and resources | `/api/dashboard`, `/api/reports` |

<!-- INACTIVE: Expenses are managed as a separate module. Activate only if expense tracking exists in PartFlow Auto. -->
<!-- INACTIVE: Returns and refunds are implemented. Activate only after documenting stock, payment, and audit effects. -->
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

### Purchase and receiving

1. An authorized user records the supplier and purchase items.
2. The server validates parts, quantities, purchase prices, VAT, and site access.
3. The purchase is saved using a database transaction.
4. Stock increases only when the purchase reaches the approved receiving state.
5. Each increase creates a traceable stock movement.

### Sale and stock deduction

1. Sales staff select a site, customer where applicable, and sale items.
2. The server checks site access, current prices, stock availability, VAT, exemptions, and allowed discounts.
3. The server calculates line totals and the final amount; client totals are not trusted.
4. The sale, items, payment or debtor record, stock deductions, and stock movements are committed in one database transaction.
5. If any required step fails, the complete operation rolls back.

### Stock transfer

1. An authorized user selects the source site, destination site, and quantities.
2. The server confirms source stock and access permissions.
3. Transfer items preserve the requested and processed quantities.
4. Dispatch and receipt must create traceable stock movements at the appropriate sites.
5. A transfer must not be completed twice or received by the source site.

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

## Known limitations and items to verify

| Item | Impact | Required action |
| --- | --- | --- |
| Laravel and PHP versions are not recorded here. | Commands and available framework features may differ by version. | Read `composer.json` and update the technology stack. |
| Database engine and version require confirmation. | SQL features, indexing, and migration behaviour may differ. | Verify configuration without copying credentials into this document. |
| Exact route and model names require confirmation. | Documentation may differ from existing implementation names. | Compare this document with `routes/`, `app/Models`, controllers, and migrations. |
| Role-permission implementation requires confirmation. | Authorization guidance cannot name the correct middleware or package yet. | Inspect middleware, policies, gates, migrations, and installed packages. |
| Hosting environment is not documented. | Deployment and infrastructure constraints are incomplete. | Record a safe, non-secret description of the production environment. |

Add confirmed technical debt only after inspecting the existing implementation. Do not label an architectural difference as technical debt merely because another approach is preferred.
