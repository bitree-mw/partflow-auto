# PartFlow Auto Database Guide

## Purpose

This document defines database responsibilities and integrity rules for PartFlow Auto. It is requirements-based. Inspect actual migrations and models before treating proposed table names as implemented.

## Database environment

- Database engine: MySQL
- Database server version: 9.7.1
- Charset/collation: `[VERIFY]`
- Test database: `[VERIFY phpunit.xml AND .env.testing]`
- Application runtime: PHP 8.4
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

## Required data areas

### Users, roles, and sites

Store users, account status, roles/permissions, sites or branches, and explicit user-site access where users can access more than one location. Session authentication and Sanctum may share the same users table. Sanctum personal access token tables must be retained if token authentication is used. Verify whether roles use custom tables or a package.

### Vehicle references and compatibility

Store makes, models, year or year ranges, engines, fuel types, and structured many-to-many compatibility between parts and vehicle configurations. Do not rely only on free-text compatibility when users need reliable search and filtering.

### Part catalogue

Store part code, name, description, brand, category, unit/packaging where used, purchase price, selling price, VAT/exemption, low-stock threshold, and active status. Part codes require a unique index. Index fields used by actual search and compatibility queries.

### Suppliers and purchases

Store suppliers, purchase headers, purchase line items, supplier reference, receiving site, statuses, quantities, actual unit cost, tax, discount, totals, actors, and timestamps. Receiving must create stock movements within the same database transaction as the approved state change.

### Site stock and movements

Store current quantity per site and part, plus traceable stock movements and adjustments. A stock record should have a unique site/part constraint. Concurrent stock writes require row locking or another approved strategy.

Recommended movement information:

- Site and part.
- Movement type and signed quantity change.
- Quantity before/after where supported.
- Source type and source identifier.
- Reference, reason, actor, timestamp, and notes.

### Stock transfers

Store source/destination, line items, requested/dispatched/received quantities as required, status, and actors/timestamps. Source and destination must differ. Dispatch and receipt must not be applied twice.

### Sales and POS

Store sale header/items, site, cashier, customer where applicable, reference, quantity, unit selling price, cost snapshot, discount, VAT, totals, payment status, and completion state. Server-derived totals and stock deduction must be committed atomically.

### Customers, debtors, and payments

Store customers, credit obligations, outstanding balances or derived balance sources, immutable payment/allocation history, payment method, approved bank/mobile destination, amount, reference, actor, and timestamp.

Never replace cumulative paid with the latest payment. Add payments to history, calculate cumulative paid, and derive the remaining balance and status.

### Reporting and audit

Reports derive from trusted transactions. Repositories/query objects may support profitability, low stock, sales, purchases, debtors, transfers, and dashboards.

<!-- INACTIVE: A general activity-log package is installed. Activate only after verification. -->
<!-- INACTIVE: Formal approval workflows exist. Activate only after confirming statuses and permissions. -->

## Core relationships

| Parent | Related record | Expected relationship |
| --- | --- | --- |
| Site | Stock | One site has many stock records. |
| Part | Stock | One part has stock at one or more sites. |
| Part | Vehicle configuration | Many-to-many through compatibility records. |
| Supplier | Purchase | One supplier has many purchases. |
| Purchase | Purchase item | One purchase has many line items. |
| Sale | Sale item | One sale has many line items. |
| Customer | Sale/debtor transaction | One customer may have many credit transactions. |
| Debtor/sale | Payment allocation | One obligation may have many payments. |
| Stock transfer | Transfer item | One transfer has many lines. |

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

<!-- INACTIVE: Returns/refunds have dedicated tables and reversal rules. Activate only when implemented and tested. -->

## Index and constraint review

At minimum, verify:

- Unique part code.
- Unique site/part stock combination.
- Foreign-key indexes.
- Transaction reference uniqueness where applicable.
- Supplier invoice uniqueness rules where applicable.
- Indexes for status, date, site, customer, supplier, part, and compatibility filters.
- Positive quantities and valid source/destination relationships.

## Repository reconciliation

The first review must inventory migrations/tables, compare them with these data areas, identify missing constraints/indexes, document statuses/relationships, and separate genuine defects from future features.
