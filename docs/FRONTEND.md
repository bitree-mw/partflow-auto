# PartFlow Auto Frontend Guide

## Purpose

This document records the Blade frontend verified on 2026-08-16 and the rules for extending it. The locked stack is Laravel 12.62.0, Blade, Tailwind CSS 4.3.1, Vite 7.3.5, Laravel Vite Plugin 2.1.0, Axios 1.18.1, and vanilla JavaScript, running locally on PHP 8.4.

## Frontend architecture

The preferred flow is:

`Route → Controller → Blade view → Blade components/partials → Tailwind styles`

- Blade views render server-provided data.
- Controllers prepare page data but do not contain business calculations.
- Services remain responsible for stock, pricing, VAT, debt, and transfer rules.
- Blade components provide reusable visual elements.
- Tailwind utilities provide styling; avoid adding custom CSS when existing utilities or components are sufficient.
- Protected Blade pages use Laravel session authentication.
- Web forms must include CSRF protection and display Laravel validation errors safely.
- The current Blade login creates a Sanctum personal access token stored in the session for server-side internal API dispatch. This is implemented but is architectural debt; new web actions should not expand that coupling without an explicit decision.

## Expected view structure

The verified structure is:

```text
resources/views/
├── layouts/
│   └── app.blade.php
├── components/
├── partials/
├── auth/                  # login
├── dashboard/
├── catalog/               # products, reference data, sites, transfers, stock takes
├── contacts/              # customer and supplier screens
├── purchases/
├── sales/
├── payment-accounts/
├── reports/
├── settings/
├── alerts/
└── pos.blade.php
```

Do not reorganize working views merely to match this example. Record and follow the existing repository convention.

## Verified screen status

| Area | Status | Verified surface and limitation |
| --- | --- | --- |
| Authentication | **Partial** | Login/logout pages exist; password reset and a web registration flow do not. |
| Dashboard and alerts | **Partial** | Summary and alert pages exist with branch scoping, responsive 7/14/30-day revenue movement, inventory-value comparison, debtor/creditor comparison, low-stock alerts, sales distribution, and branch performance. Custom dashboard date ranges and debtor aging are not implemented. |
| Catalogue and compatibility | **Implemented** | Product, brand, type, fuel, car-model, and compatibility management screens exist. |
| Contacts | **Implemented — basic** | Customer and supplier list/create/delete screens exist; transaction-history/statement pages do not. |
| Purchases | **Partial** | List/create/edit screens exist; there is no receive action screen. |
| Site inventory | **Partial** | Site overview and stock-take screens exist; direct quantity CRUD is not an approved adjustment workflow. |
| Transfers | **Partial** | List/create/show screens exist; approve, dispatch, receive, reject, and cancel screens do not. |
| POS and sales | **Partial** | Search/cart/customer/payment checkout and sale list/edit exist; printable receipt, idempotent checkout, and void/reversal UI do not. |
| Customers and debtors | **Partial** | Customer records, a credit-limit field, and sale balances exist; limit enforcement, due dates, aging, statements, and collection workflow do not. |
| Users and access | **Partial** | Settings manage roles and site assignments; a conventional user CRUD interface does not exist. |
| Reports | **Partial** | Report filters and export link exist; the export link does not currently attach the bearer token required by the API route. |
| Payment accounts | **Implemented — basic** | List/create/show/edit screens exist. Payment reversal/reconciliation UI is not claimed. |

Status definitions and planned work are maintained in `docs/FEATURES_AND_COMPONENTS.md`.

## Layout and navigation

- Use one primary application layout unless the repository documents separate layouts.
- Show the active site or branch prominently when users can access more than one site.
- Hide navigation items the user cannot access, while still enforcing authorization on the server.
- Preserve useful filters and pagination parameters when navigating back from detail pages.
- Use clear status badges for purchases, transfers, sales, and debts.
- Display success and error messages consistently.

## Reusable Blade components

Prefer existing components. Add a component when the same pattern occurs repeatedly, for example:

- Buttons and links with consistent variants.
- Form labels, inputs, selects, checkboxes, and validation messages.
- Confirmation dialogs.
- Alerts and flash messages.
- Tables, empty states, pagination, and loading states.
- Status badges.
- Currency and quantity displays.
- Site selector and date-range filter.

Components should focus on presentation. They must not calculate authoritative prices, taxes, stock, or permissions.

## Forms and safe interactions

- Include CSRF protection in every state-changing web form.
- Display server-side validation errors beside the relevant fields.
- Preserve safe old input after validation failure.
- Do not trust hidden fields for totals, discounts, VAT, costs, user IDs, or site IDs.
- Disable or guard repeated submission where duplicate purchases, transfers, sales, or payments could be created.
- Use explicit confirmation for irreversible or high-impact actions.
- Never use a simple delete button for a transaction that should be voided or reversed.

## Point-of-sale experience

The POS should allow staff to:

1. Search by part code, name, brand, category, or vehicle compatibility.
2. See current-site availability before adding an item.
3. Add and update cart quantities without exceeding permitted stock.
4. Select a customer or use the documented walk-in-customer flow.
5. Apply only authorized discounts.
6. See VAT and totals calculated by the backend.
7. Choose an allowed payment method and bank/mobile-money destination where relevant.
8. Clearly distinguish paid, partially paid, and credit sales.
9. Receive a success screen or receipt after the server commits the sale.

The browser may show estimates for responsiveness, but the backend result is authoritative.

## Lists, filters, and pagination

- Paginate large datasets on the server.
- Use query-string filters so filtered pages can be bookmarked and shared.
- Whitelist allowed sort fields and directions.
- Provide useful empty states rather than blank tables.
- Common filters include site, status, date range, supplier, customer, category, and low-stock state.
- Avoid loading complete part, customer, sale, or movement tables into the browser.

## Tailwind CSS rules

### Do

- Reuse the project color, spacing, typography, and component conventions.
- Keep utility groups readable and consistent with nearby code.
- Design mobile-first and verify common desktop POS widths.
- Use visible focus styles and sufficient color contrast.
- Run the configured asset build after changing classes or templates.

### Do not

- Introduce another CSS framework without approval.
- Add arbitrary values repeatedly when a theme token or reusable component is appropriate.
- Build authorization rules into CSS visibility.
- duplicate long class strings across many views when a component would improve consistency.
- Modify Tailwind or Vite configuration without checking the installed versions and build process.
- Do not hard code colours in view files.

## Responsive design and accessibility

- Every form control must have an associated label.
- Keyboard users must be able to reach all actions.
- Do not communicate transfer, debt, or stock status through color alone.
- Tables should remain usable on smaller screens through responsive columns, cards, or controlled horizontal scrolling.
- Confirmation and validation messages should be readable by assistive technology.
- Use semantic buttons, links, headings, tables, and landmark elements.

## Frontend/backend boundary

The frontend may improve usability, but it must not be the sole enforcement point for:

- Authorization or site access.
- Available stock.
- Transfer status transitions.
- Part-code uniqueness.
- Discount limits.
- VAT calculation or exemption.
- Sale, debt, and payment totals.
- Bank or mobile-money destination validity.

## Verification commands

The configured package scripts are:

```bash
npm run dev
npm run build
```

Do not assume an npm lint or test command exists; inspect `package.json` first.

## Inactive optional frontend capabilities

Alpine.js, Livewire, React, Vue, a JavaScript unit-test runner, and browser-test automation are not installed or active. Offline POS, barcode scanning, image uploads, and realtime updates are also inactive optional features rather than implemented UI capabilities.
