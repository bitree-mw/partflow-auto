# PartFlow Auto API Conventions

## Purpose

This document defines the expected API shape for PartFlow Auto. Existing public contracts take priority: inspect routes, controllers, Resources, tests, and the current `ApiResponse` helper before changing response formats.

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

Use the existing route configuration. A common base path is `/api`, but do not add `/v1` or alter prefixes unless versioning is an approved project decision.

## Authentication and authorization

- Use Laravel Sanctum for authenticated API requests.
- Use Laravel session authentication for protected Blade/web routes; those web routes are not part of the public API contract.
- Confirm whether the API client uses personal access tokens or Sanctum's stateful cookie authentication before modifying login, logout, CSRF, CORS, or middleware configuration.
- Protect restricted API routes with the project's Sanctum middleware, normally `auth:sanctum` unless the repository establishes another convention.
- Enforce permissions through policies, gates, middleware, or the established roles implementation.
- Enforce site access on the backend for every site-scoped resource.
- Return `401 Unauthorized` when authentication is missing or invalid.
- Return `403 Forbidden` when an authenticated user lacks permission.
- Do not reveal whether inaccessible cross-site records exist.
- Do not return an API token from ordinary session-based Blade login unless that is an explicit existing requirement.

## Response envelope

Preserve the actual `ApiResponse` contract. If the project has not yet standardized it, a suitable structure is:

```json
{
  "success": true,
  "message": "Sale created successfully.",
  "data": {}
}
```

For errors:

```json
{
  "success": false,
  "message": "The request could not be completed.",
  "errors": {}
}
```

Do not change field names, nesting, pagination metadata, or message behaviour without checking API consumers and tests.

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

## Expected endpoint families

The following families describe PartFlow Auto's required capabilities. Actual paths and availability must be verified in `routes/api.php` and route registrations.

| Capability | Typical endpoints or actions |
| --- | --- |
| Authentication | Login, logout, and current user. |
| Users/access | Users, roles, permissions, and site assignments. |
| Sites | List permitted sites and manage sites where authorized. |
| Parts | CRUD/search, compatibility, pricing, and low-stock settings. |
| Vehicle data | Makes, models, years, engines, and fuel types. |
| Suppliers | Supplier CRUD and purchase-related lookup. |
| Purchases | Create, list, view, receive/post, or cancel according to the status model. |
| Inventory | Site balances, stock movements, low-stock list, and controlled adjustments. |
| Transfers | Create, approve, dispatch, receive, reject, or cancel according to allowed transitions. |
| Sales | Checkout, list, view, and supported void/return actions. |
| Customers/debtors | Customer CRUD, balances, credit activity, and payment allocation. |
| Reports | Sales, purchases, stock, movement, transfer, profit, and debtor reports where implemented. |

Do not create every route in this table automatically. First compare the required capability with existing routes and the approved implementation scope.

## Stateful business actions

Use explicit action endpoints when an operation changes business state and ordinary CRUD would hide important rules. Examples may include:

```text
POST /transfers/{transfer}/dispatch
POST /transfers/{transfer}/receive
POST /purchases/{purchase}/receive
POST /sales/{sale}/payments
```

Exact verbs and paths must follow existing project conventions. Each action should:

1. Authorize the user and site.
2. Validate required input.
3. Confirm the current state allows the transition.
4. Run atomically in a database transaction.
5. Record the responsible user and audit details.
6. Return the updated Resource through `ApiResponse`.

## Stock and financial rules

- Never trust client-provided stock balances, subtotals, VAT, discounts, totals, or outstanding balances.
- Recalculate authoritative values on the server.
- Check stock for the correct site at the time of the committed operation.
- Use transactions for purchases, transfers, sales, stock adjustments, debts, and payments.
- Use locking or another documented concurrency strategy where simultaneous operations can oversell stock.
- Make duplicate submission behaviour explicit for checkout, receipt, dispatch, and payment endpoints.

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
