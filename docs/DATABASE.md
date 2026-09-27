# PartFlow Auto Database Guide

## Purpose

This document records the schema and integrity rules verified in the current repository working tree on 2026-09-15. Requirements that are not implemented are explicitly labelled planned or requiring clarification.

## Database environment

- Application target: MySQL configured in `config/database.php`; the reviewed local server is MySQL 9.7.1, while the deployed server version is not verified.
- Default connection in configuration: SQLite unless `DB_CONNECTION` overrides it.
- MySQL configuration defaults: `utf8mb4` / `utf8mb4_unicode_ci`; the reviewed local database reports `utf8mb4` / `utf8mb4_0900_ai_ci`.
- Automated test database: SQLite `:memory:` from `phpunit.xml`.
- Application runtime: PHP 8.4.25 and Laravel 12.62.0 locally; Composer permits PHP `^8.2` and Laravel `^12.0`.
- Money: recommended `decimal(15,2)` unless the existing schema has an approved alternative.
- Quantities: integers for indivisible parts, or an approved decimal precision where measured stock is supported.

## Conventions

- Use `snake_case` tables and columns.
- Use `{model}_id` foreign keys unless existing conventions differ.
- Create new migrations for changes; do not rewrite applied migrations.
- Define indexes, foreign keys, nullability, defaults, and delete behaviour explicitly.
- Never use floating-point columns for money.
- Record timestamps and actors where auditability matters.
- Use soft deletes only when restoration is required and query/uniqueness effects are understood.

## Implemented schema map

The repository contains 43 migration files. The main implemented tables are:

| Area | Tables/models | Important relationships and constraints |
| --- | --- | --- |
| Identity and access | `users`, `roles`, `user_site_access`, `personal_access_tokens`, `sessions` | Users belong to a role and have many site-access rows with operation flags. Sanctum tokens and browser sessions share the user identity. |
| Business configuration | `business_settings`, `sites`, `payment_accounts`, `expense_categories` | Operational records reference sites and, where applicable, payment accounts. |
| Catalogue | `products`, `product_types`, `brands`, `fuel_types`, `tax_profiles`, `product_references` | Product codes are unique; products relate to type/brand/fuel/tax and may have many references. |
| Vehicle compatibility | `car_makes`, `vehicle_models`, `car_models`, `product_compatibilities` | Vehicle-model reference data belongs to makes; product-facing car models and compatibility rows preserve make/model/year/engine/variant details. |
| Contacts | `contacts` | One table represents customers and suppliers through flags. |
| Transactions | `inventory_documents`, `inventory_document_items` | Purchases, sales, transfers, adjustments, stock takes, and returns use `document_type`; items preserve quantity, cost, price, discount, tax, and totals. |
| Stock | `site_stocks`, `stock_movements` | Site stock is unique per site/product and records the current low-stock notification cycle. Movements reference site, product, optional document/item, actor, before/after balance, and signed change. |
| Money and audit | `payments`, `expenses`, `audit_logs` | Payments belong to inventory documents and optional accounts; expenses may reference a site/account/category; API audit middleware writes logs. |

## Implemented data areas and limitations

### Users, roles, and sites

The application uses custom `roles` with JSON permissions, `users.role_id`, and unique `user_site_access` rows containing operation flags. The users table is shared by browser sessions and Sanctum tokens.

### Vehicle references and compatibility

The schema contains `car_makes`/`vehicle_models` reference data, product-facing `car_models`, a primary product model foreign key, and additional `product_compatibilities`. Compatibility includes structured year, engine, variant, and fuel information alongside descriptive fields.

### Part catalogue

`products` stores a unique code, name/description, product type, brand, fuel type, origin, units/pack size, default purchase/selling price, minimum selling price, tax profile, low-stock threshold, active state, and optional primary car model. New products default the minimum selling price to 80% of the selling price; references and compatibility are separate related tables.

### September 2026 client-workbook baseline

The local database was reset on 2026-09-22 from `client_docs/Warehouse_September_2026.xlsx` through the validated warehouse catalogue manifest. The reset retains application configuration and reference data, including users, sites, product types, makes, and vehicle models. It removes the previous products, contacts, expenses, scheduled reminders, inventory documents, site balances, and stock movements before loading the warehouse baseline.

The warehouse workbook reconciles to 466 consolidated products and 1,356 units at Limbe Warehouse (`LMBWH`). Two warehouse products have zero opening quantity. One approved opening adjustment contains 464 line items and movements, so every positive warehouse opening balance remains traceable.

On 2026-09-23, the six other workbooks in `client_docs` were reconciled row by row to 161 products and 954 units. The retained CPT source list contributes another 39 products and 55 units. The store import reuses 22 reviewed matches to warehouse products, creates 178 distinct products where no safe match exists, and produces 200 Limbe Store (`LMBST`) stock rows totalling 1,009 units. Nineteen store rows have zero quantity. One approved store stock take contains 181 changed line items and movements. The resulting catalogue contains 644 products; the local database still contains no sale, purchase, or transfer history.

### Suppliers and purchases

Suppliers use `contacts`; purchases use `inventory_documents` and `inventory_document_items`. Completed creation records costs/totals and creates `purchase_in` movements transactionally. A separate draft-to-received transition is planned and does not exist.

### Site stock and movements

`site_stocks` stores current and reserved quantity, thresholds, and the nullable `low_stock_notified_at` cycle marker per site/product, with a unique site/product constraint. `StockMovementService` locks balance rows, records movements for domain workflows, and evaluates low-stock notification transitions on the same locked row. However, the public SiteStock CRUD service can directly create, update, or delete quantity rows without movements. Removing quantity mutation from that CRUD surface is confirmed planned work; only threshold editing may remain direct.

Recommended movement information:

- Site and part.
- Movement type and signed quantity change.
- Quantity before/after where supported.
- Source type and source identifier.
- Reference, reason, actor, timestamp, and notes.

### Stock transfers

Transfers use generic inventory documents with source/destination and items. A completed transfer immediately creates balanced `transfer_out` and `transfer_in` movements. Requested/dispatched/received quantities and explicit dispatch/receipt transitions are not implemented and require business rules.

### Sales and POS

Sales use inventory-document headers/items and preserve site, cashier, optional customer, quantity, trusted selling-price and cost snapshots, discount, VAT, totals, payment status, and completion state. The stricter of the admin maximum discount percentage and product minimum selling price is enforced during transactional sale creation. Completed sale creation and its stock/payment effects are transactional.

### Customers, debtors, and payments

Sales store `paid_amount`, `balance_amount`, and `payment_status`, and related `payments` preserve payment history and destination. `contacts.credit_limit` exists but is not enforced by a documented credit-authorization workflow. Dedicated obligations, due dates, aging, allocations, statements, and a debtor ledger are not implemented.

Never replace cumulative paid with the latest payment. Add payments to history, calculate cumulative paid, and derive the remaining balance and status.

### Reporting and audit

`scheduled_email_reminders` tracks weekly stock digests separately from stock/financial records. A unique type/period pair prevents repeated scheduled dispatch. `sent_at` records SMTP acceptance, not confirmed inbox delivery. These records do not change stock quantities, document balances, or the stock-change notification cycle.

Reports derive from trusted transactions. Repositories/query objects may support profitability, low stock, sales, purchases, debtors, transfers, and dashboards.

<!-- INACTIVE: A general activity-log package is installed. Activate only after verification. -->
<!-- INACTIVE: Formal approval workflows exist. Activate only after confirming statuses and permissions. -->

## Core relationships

| Parent | Related record | Verified relationship |
| --- | --- | --- |
| `User` | `Role`, `UserSiteAccess` | A user belongs to an optional role and has many unique site assignments. |
| `Site` | `SiteStock`, `InventoryDocument`, `StockMovement`, `Expense` | Site-scoped records use source, destination, or direct site foreign keys as appropriate. |
| `Product` | `SiteStock`, `ProductCompatibility`, `InventoryDocumentItem`, `StockMovement` | A product has per-site balances, compatibility rows, transaction lines, and movement history. |
| `Contact` | `InventoryDocument` | Customer and supplier documents reference the shared contact table. |
| `InventoryDocument` | `InventoryDocumentItem`, `Payment`, `StockMovement` | A generic transaction header has lines, payment history, and movement history. |
| `PaymentAccount` | `Payment`, `Expense` | Recorded payments and expenses may identify the receiving/paying account. |

## Status and transition rules

Document actual values and allowed transitions for purchases, transfers, sales, payments, and active/inactive records. Prefer enums only when compatible with existing project conventions. Adding a status requires changes to validation, services, filters, resources, Blade badges, reports, and tests.

## Stock and financial integrity

- Derive totals, tax, discounts, balances, and profit from trusted server values.
- Use transactions for all related multi-table writes.
- Lock contested stock or balance records.
- Preserve transaction-time cost, price, VAT, and discount snapshots.
- Every stock change requires a movement record.
- Every debtor payment requires an immutable payment/allocation record.
- Prevent duplicate receiving, dispatch, receipt, sale completion, and payment application.

## Deletion and reversal

- Prefer inactive reference records where transactions depend on them.
- Preserve completed purchases, sales, transfers, movements, and payments.
- Correct transactions through approved void, cancellation, return, reversal, or adjustment workflows.
- Review cascade deletes deliberately.

Sale and purchase returns use the generic inventory-document tables and create stock movements. They are partial: no original-document link, eligibility rules, refund allocation, VAT reversal, or approved reversal workflow exists.

## Index and constraint review

At minimum, verify:

- Unique part code.
- Unique site/part stock combination.
- Foreign-key indexes.
- Transaction reference uniqueness where applicable.
- Supplier invoice uniqueness rules where applicable.
- Indexes for status, date, site, customer, supplier, part, and compatibility filters.
- Positive quantities and valid source/destination relationships.

## Verified integrity risks and planned corrections

- **Planned:** eliminate direct `quantity_on_hand`/`reserved_quantity` SiteStock writes and deletes; route all changes through an adjustment or stock take with a movement.
- **Planned:** add controlled purchase receiving and transfer dispatch/receipt state transitions with duplicate-processing protection.
- **Planned:** replace payment hard deletion with the approved immutable reversal method.
- **Partial:** returns affect stock but are not tied to the original transaction or its payment/tax effects.
- **Partial:** debtor balances are document-level fields rather than a reconciled ledger with due dates and aging.
- **Needs clarification:** costing method, decimal rounding stage, reservation lifecycle, supplier-invoice uniqueness, global expenses, and completed-document correction rules.

## Repository reconciliation

Future reviews must compare migrations and models with this map, identify new constraints and indexes, and keep implemented, partial, planned, inactive optional, and unclear rules distinct.
