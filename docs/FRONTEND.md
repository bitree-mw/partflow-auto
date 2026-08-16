# PartFlow Auto Frontend Guide

## Purpose

This document defines how developers and AI agents should build and modify the PartFlow Auto user interface. The confirmed frontend stack is Laravel Blade and Tailwind CSS, running through Laravel on PHP 8.4. Verify the installed Laravel, Tailwind, Vite, and JavaScript versions from the repository before changing configuration.

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
- Use Sanctum only when the Blade interface intentionally calls protected API endpoints; do not create tokens for ordinary server-rendered navigation and form submissions.

## Expected view structure

Confirm the actual structure before moving existing files. A suitable convention is:

```text
resources/views/
├── layouts/
│   └── app.blade.php
├── components/
├── partials/
├── auth/
├── dashboard/
├── parts/
├── suppliers/
├── purchases/
├── inventory/
├── transfers/
├── sales/
├── customers/
├── debtors/
├── reports/
└── users/
```

Do not reorganize working views merely to match this example. Record and follow the existing repository convention.

## Required screens

| Area | Screens and capabilities | Important information |
| --- | --- | --- |
| Authentication | Login and logout. | Validation and safe authentication errors. |
| Dashboard | Business summary and alerts. | Sales, stock value, low stock, debt, and site context. |
| Parts | List, create, view, and edit parts. | Part code, name, category, brand, price, VAT, compatibility, and stock. |
| Compatibility | Assign vehicle make, model, year, engine, and fuel type. | Clear compatibility combinations and duplicate prevention. |
| Suppliers | Supplier list and maintenance. | Contact details and purchase history where authorized. |
| Purchases | Record and inspect purchases. | Supplier, site, items, cost, totals, status, and stock effect. |
| Inventory | Site stock, low-stock items, and adjustments. | Quantity on hand, threshold, movement history, and reason. |
| Transfers | Request, approve, dispatch, receive, and inspect transfers. | Source, destination, items, quantities, status, and audit trail. |
| Point of sale | Product search, cart, customer, discount, VAT, and payment. | Current-site availability and server-calculated totals. |
| Sales | Sale list, receipt/detail view, and permitted follow-up actions. | Payment status, destination, staff member, and audit information. |
| Customers and debtors | Customer records, balances, credit sales, and repayments. | Outstanding balance, due information, and payment history. |
| Users and access | User, role, permission, and site assignment management. | Only visible and usable by authorized roles. |
| Reports | Filtered operational and financial reports. | Date range, site, totals, export options if implemented. |

This table describes the required product surface; it does not prove that every screen already exists.

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

Use the commands configured in `package.json`. Common examples are:

```bash
npm install
npm run dev
npm run build
```

Do not assume an npm lint or test command exists; inspect `package.json` first.

<!-- INACTIVE: Alpine.js is used for lightweight interactions. Activate only if it is installed. -->
<!-- INACTIVE: Livewire is used for interactive screens. Activate only if it is installed. -->
<!-- INACTIVE: A JavaScript unit-test runner is configured. Record its command when confirmed. -->
<!-- INACTIVE: Browser tests cover the POS and other critical screens. Record the framework when confirmed. -->
