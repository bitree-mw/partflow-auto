# PartFlow Auto API Conventions

## Purpose

This document records the API conventions verified from `routes/api.php`, controllers, Form Requests, API Resources, and `app/Support/ApiResponse.php` on 2026-08-16. Normative guidance is identified separately from current limitations.

## Standard request flow

`Route → Controller → Form Request → Service → Model/Query → API Resource → ApiResponse`

- Routes define HTTP method, URI, middleware, and route binding.
- Controllers remain thin and call one clear business action.
- Form Requests authorize and validate input.
- Services coordinate business rules and transactions.
- Models and query objects handle persistence and querying.
- API Resources define public representations.
- `ApiResponse` applies the existing success/error envelope.

## Base path and versioning

The API is currently unversioned under `/api`. Do not add `/v1` or alter prefixes unless versioning and consumer migration are approved.

## Authentication and authorization

- Use Laravel Sanctum for authenticated API requests.
- Use Laravel session authentication for protected Blade/web routes; those web routes are not part of the public API contract.
- API login and registration issue Sanctum personal access tokens. The Blade login also creates a token stored in the session for server-side internal API dispatch; this mixed session/token bridge is implemented but should be treated as architectural debt, not the preferred shape for new web actions.
- Protect restricted API routes with the project's Sanctum middleware, normally `auth:sanctum` unless the repository establishes another convention.
- Enforce permissions through policies, gates, middleware, or the established roles implementation.
- Enforce site access on the backend for every site-scoped resource.
- Return `401 Unauthorized` when authentication is missing or invalid.
- Return `403 Forbidden` when an authenticated user lacks permission.
- Do not reveal whether inaccessible cross-site records exist.
- Public endpoints are `POST /api/auth/register` and `POST /api/auth/login`; all other API routes use `auth:sanctum`.

## Response envelope

`app/Support/ApiResponse.php` defines the implemented success shape:

```json
{
  "success": true,
  "message": "Sale created successfully.",
  "data": {}
}
```

Implemented errors use:

```json
{
  "success": false,
  "message": "The request could not be completed.",
  "errors": {}
}
```

`data` is always present on success and may be `null`; `errors` is present on errors only when supplied; optional success `meta` is supported. Delete responses return HTTP 200 with `data: null`. Do not change these details without checking API consumers and tests.

## HTTP status codes

| Status | Use |
| --- | --- |
| `200 OK` | Successful read or update with a response body. |
| `201 Created` | Resource successfully created. |
| `204 No Content` | Successful operation intentionally returning no body, only if compatible with the response convention. |
| `400 Bad Request` | Malformed or unsupported request outside normal validation. |
| `401 Unauthorized` | Missing or invalid authentication. |
| `403 Forbidden` | Authenticated but not permitted. |
| `404 Not Found` | Resource is absent or intentionally hidden by access scope. |
| `409 Conflict` | State conflict, duplicate processing, or invalid concurrency condition. |
| `422 Unprocessable Content` | Validation or business-rule failure represented as validation. |
| `500 Internal Server Error` | Unexpected server error; return a safe message and log details. |

## Validation conventions

- Use Form Request classes instead of controller validation for non-trivial endpoints.
- Keep input-shape rules in the request.
- Keep stock, pricing, VAT, transfer, debt, and state-transition rules in services/domain logic.
- Use database constraints as the final integrity boundary.
- Validate referenced records within the user's authorized site scope.
- Use separate create and update requests when rules or authorization differ.
- Do not accept calculated totals from clients as authoritative.

## Resource conventions

- Return API Resources for models and Resource Collections for lists.
- Expose only fields required by the API consumer.
- Use stable field names and consistent relationship representations.
- Avoid database writes or business calculations in Resources.
- Avoid accidental lazy-loaded queries; controllers/services should load required relationships.
- Format dates consistently, normally as ISO 8601 strings.
- Represent money consistently according to the documented storage and API convention.

## Pagination, filtering, and sorting

- Paginate large collections.
- Preserve the current pagination envelope.
- Accept filters through documented query parameters.
- Whitelist sortable fields and sort direction.
- Apply site and authorization scope before other filters.
- Prevent unbounded exports or collection responses.

Common parameters may include:

```text
page, per_page, search, site_id, status, from, to, sort, direction
```

Only implement parameters supported by the endpoint and document them in its tests or API documentation.

## Implemented endpoint families

The protected API currently exposes these families:

| Capability | Typical endpoints or actions |
| --- | --- |
| Authentication | Login, logout, and current user. |
| Users/access | Users, roles, permissions, and site assignments. |
| Sites | List permitted sites and manage sites where authorized. |
| Parts | CRUD/search, compatibility, pricing, and low-stock settings. |
| Vehicle data | Makes, models, years, engines, and fuel types. |
| Suppliers | Supplier CRUD and purchase-related lookup. |
| Purchases | Create, list, and view. No receive/cancel action endpoint exists. |
| Inventory | Site balances, stock movements, low-stock list, and controlled adjustments. |
| Transfers | Create, list, and view. No approve/dispatch/receive/reject/cancel actions exist. |
| Sales | POS checkout plus sale list/create/view; sale-return routes exist, but void/refund rules are incomplete. |
| Customers/debtors | Contact CRUD (including a credit-limit field) and document balance reporting; no enforced credit policy, ledger, aging, or statement API exists. |
| Payments/expenses | Payments, payment accounts/methods, expenses, and expense categories. Payments are currently deletable. |
| Reports | Stock, sales, purchases, movements, transfers, profit, customer balances, payments, variances, expenses, and CSV export. |

Do not create every route in this table automatically. First compare the required capability with existing routes and the approved implementation scope.

## Stateful business actions

The following action endpoints are **planned**, not implemented:

```text
POST /transfers/{transfer}/dispatch
POST /transfers/{transfer}/receive
POST /purchases/{purchase}/receive
POST /sales/{sale}/payments
```

Exact verbs and paths require approval. When implemented, each action should:

1. Authorize the user and site.
2. Validate required input.
3. Confirm the current state allows the transition.
4. Run atomically in a database transaction.
5. Record the responsible user and audit details.
6. Return the updated Resource through `ApiResponse`.

## Stock and financial rules

- Never trust client-provided stock balances, subtotals, VAT, discounts, totals, or outstanding balances.
- Recalculate authoritative values on the server.
- Sale pricing uses the current catalogue selling price and trusted cost/tax data. Discounts may not exceed the admin percentage cap or reduce a product below its minimum selling price.
- Check stock for the correct site at the time of the committed operation.
- Use transactions for purchases, transfers, sales, stock adjustments, debts, and payments.
- Use locking or another documented concurrency strategy where simultaneous operations can oversell stock.
- Make duplicate submission behaviour explicit for checkout, receipt, dispatch, and payment endpoints.

### Current SiteStock exception

`/api/site-stocks` currently exposes create, update, and delete endpoints that can directly alter or remove quantities without a `StockMovement`. This is a verified integrity gap, not an approved convention. Planned behavior is to allow direct threshold maintenance only; quantity changes must use stock adjustments or stock takes.

## Collection behavior

Many current `index` actions return unpaginated Resource collections. Pagination, sort parameters, and consistent list metadata are planned improvements; clients must not assume every collection currently returns pagination metadata.

## Dates, money, and statuses

- Store dates in the project's standard timezone strategy and return consistent ISO 8601 values.
- Do not use floating-point arithmetic for authoritative money.
- Use the application's currency consistently and document multi-currency behaviour before implementing it.
- Use named constants or enums when already supported by the project version and conventions.
- Validate status transitions; do not accept arbitrary status updates through generic CRUD endpoints.

## API documentation and change control

- Update endpoint tests and documentation with every contract change.
- Treat removed fields, renamed fields, changed types, new required input, and response-envelope changes as breaking changes.
- Preserve compatibility unless a migration/versioning plan is approved.
- Mention affected Blade pages or external consumers in the change summary.

<!-- INACTIVE: OpenAPI documentation is generated. Record its source file and generation command when confirmed. -->
<!-- INACTIVE: API idempotency keys are supported. Activate for critical write endpoints only after defining storage and replay behaviour. -->
<!-- INACTIVE: API rate limits differ by route group. Record the confirmed limits and middleware here. -->
