# Warehouse Management System

This project is a Warehouse Management System (WMS) built with Laravel and Filament.

It provides a modern admin panel for managing warehouse operations, tracking inventory, and keeping stock data organized in one central place.

## Key Features

- **Multi-warehouse tenancy** — each warehouse operates on its own subdomain, with data, users, and settings scoped per warehouse/tenant.
- **Product & inventory management** — products, brands, attribute groups, VAT rates, and stock locations, with barcode generation and downloadable barcode images per product.
- **Stock locations** — track where stock physically lives within a warehouse and move/adjust stock levels.
- **Orders** — sales order management with order statuses, order status history, and status-change activity tracking.
- **Purchase orders** — supplier purchase orders, including bulk import via spreadsheet upload.
- **Picklists** — generate and manage pick lists for fulfilling orders from warehouse stock.
- **Backorders** — track items that are open, partially fulfilled, fulfilled, or cancelled when stock is insufficient to fulfill an order immediately.
- **Clients** — client/customer records with addresses, order history, and a client-specific dashboard showing order summaries and recent orders.
- **Notes** — attach freeform notes to records across the system for internal communication and context.
- **Spreadsheet import & export** — import products and purchase orders from spreadsheets (via PhpSpreadsheet), with failed-row reporting.
- **Low stock alerts & dashboard widgets** — a dashboard with warehouse overview stats, low-stock product tables, top products, and order/revenue charts (orders per month, average order amount per month).
- **User roles and access control** — manage users and permissions per warehouse tenant.
- **Full audit/activity logging** — every create/update/delete on key resources (orders, purchase orders, clients, products, stock locations, warehouses, users, API keys, webhooks, VAT rates, attribute groups, etc.) is tracked and viewable per record, powered by Spatie Activity Log and a Filament activity log UI.
- **REST API (v1)** — API-key-authenticated endpoints for products, clients, orders, purchase orders, picklists, and backorders, for integrating external systems.
- **API key management** — issue, revoke, expire, and restrict API keys (including IP allow-listing) from the admin panel.
- **Outgoing webhooks** — subscribe external endpoints to warehouse events (order created, order status changed, order cancelled/delivered, purchase order status changed/processed, backorder created/fulfilled/cancelled) with delivery tracking and retry via queued jobs.
- **GraphQL API** — a GraphQL endpoint (via `rebing/graphql-laravel`) exposing product queries and mutations.
- **PDF generation** — generate PDF documents (e.g. picklists, orders) via Spatie Laravel PDF.
- **Health checks** — application health/status endpoint for monitoring uptime and dependencies.
- **Clean dashboard** powered by Filament, with per-tenant widgets and charts.

## Tech Stack

- Laravel 12
- Filament 4
- MySQL
- Tailwind CSS
- Spatie Activity Log, Spatie Laravel PDF, Spatie Laravel Health
- GraphQL (rebing/graphql-laravel)
- PhpSpreadsheet
- Modular architecture via `internachi/modular` (each domain — products, orders, clients, backorders, picklists, brands, notes, settings, users, platform — lives in its own module under `app-modules/`)

## Benefits

This system helps reduce manual work, improve accuracy, and give warehouse teams better visibility into stock movements and availability.

It also supports multiple warehouses with tenancy, making it easier to separate data and operations by warehouse.

## Support

For custom-made functionality, integrations, or support requests, contact **service@openguild.eu**.
