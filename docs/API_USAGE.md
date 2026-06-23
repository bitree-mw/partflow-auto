# PartFlow Auto API Usage Guide

This guide explains how to call the PartFlow Auto API from a frontend, mobile app, or API client.

## Base URL

Local XAMPP example:

```text
http://127.0.0.1/partflow-auto/public/api
```

Laravel serve example:

```text
http://127.0.0.1:8000/api
```

## Response Shape

Successful responses use the standard API envelope:

```json
{
  "success": true,
  "message": "Request successful",
  "data": {}
}
```

Failed responses use:

```json
{
  "success": false,
  "message": "Request failed",
  "errors": {}
}
```

## Authentication

Register or log in first. All API routes are protected by Sanctum except register and login.

```http
POST /api/auth/login
Content-Type: application/json
Accept: application/json
```

```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

Use the returned token on protected requests:

```http
Authorization: Bearer YOUR_TOKEN_HERE
Accept: application/json
Content-Type: application/json
```

## Common Protected Calls

Current user:

```http
GET /api/auth/me
Authorization: Bearer YOUR_TOKEN_HERE
```

Logout:

```http
POST /api/auth/logout
Authorization: Bearer YOUR_TOKEN_HERE
```

## POS Product Search

Search products available at a specific site:

```http
GET /api/pos/products?site_id=1&search=demio
Authorization: Bearer YOUR_TOKEN_HERE
```

Search by compatible car model:

```http
GET /api/pos/products?site_id=1&compatible_car_model_id=5
Authorization: Bearer YOUR_TOKEN_HERE
```

The POS response includes product details, compatible cars, site stock, available quantity, selling price, brand, fuel type, origin, and tax profile.

## Create a POS Sale

```http
POST /api/pos/sales
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json
```

```json
{
  "contact_id": 1,
  "source_site_id": 1,
  "document_date": "2026-06-23 10:30:00",
  "discount_amount": 0,
  "items": [
    {
      "product_id": 10,
      "quantity": 2,
      "unit_price": 8500,
      "discount_amount": 0
    }
  ],
  "payment": {
    "payment_account_id": 1,
    "amount": 17000,
    "payment_method": "cash",
    "transaction_reference": "POS-1042"
  }
}
```

When completed, the sale reduces `site_stocks.quantity_on_hand`, validates available quantity, records `sale_out` stock movements, calculates VAT, and updates payment status.

## Purchases

```http
POST /api/purchases
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json
```

```json
{
  "contact_id": 2,
  "destination_site_id": 1,
  "items": [
    {
      "product_id": 10,
      "quantity": 12,
      "unit_cost": 5000,
      "unit_price": 8500
    }
  ],
  "payment": {
    "payment_account_id": 1,
    "amount": 30000,
    "payment_method": "cash"
  }
}
```

Completed purchases increase site stock and create `purchase_in` stock movements.

## Transfers

```http
POST /api/transfers
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json
```

```json
{
  "source_site_id": 1,
  "destination_site_id": 2,
  "items": [
    {
      "product_id": 10,
      "quantity": 4
    }
  ]
}
```

Transfers create both `transfer_out` and `transfer_in` stock movements.

## Payments

```http
POST /api/payments
Authorization: Bearer YOUR_TOKEN_HERE
Content-Type: application/json
```

```json
{
  "inventory_document_id": 15,
  "payment_account_id": 1,
  "amount": 25000,
  "payment_method": "cash",
  "payment_date": "2026-06-23 12:00:00"
}
```

Payments update `paid_amount`, `balance_amount`, and `payment_status` on the related inventory document.

## Stock Movements

Stock movements are usually created by inventory document workflows. To review history:

```http
GET /api/stock-movements?site_id=1&product_id=10
Authorization: Bearer YOUR_TOKEN_HERE
```

## Reports

Examples:

```http
GET /api/reports/current-stock-by-site?site_id=1
GET /api/reports/low-stock-by-site?site_id=1
GET /api/reports/stock-valuation
GET /api/reports/sales-by-date-range?date_from=2026-06-01&date_to=2026-06-23
GET /api/reports/payments-by-account?date_from=2026-06-01&date_to=2026-06-23
```

## Dashboard

```http
GET /api/dashboard/summary
Authorization: Bearer YOUR_TOKEN_HERE
```

The dashboard summary returns today sales, today profit, stock value, low-stock counts, out-of-stock counts, customer balances, and recent activity.

## Validation Notes

- Send `Accept: application/json` on every API request.
- Use numeric IDs from existing resources such as products, sites, contacts, tax profiles, and payment accounts.
- Sales and transfers fail if requested quantity exceeds available stock.
- `payment_status` can be `unpaid`, `partial`, or `paid`.
- Inventory document statuses commonly use `draft`, `completed`, or `approved` depending on document type.
