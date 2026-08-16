# PARTFLOW-AUTO

## Description

This system is to be used as an inventory management system and also a POS for companies that are selling and buying car parts.

This repository contains a Laravel backend, REST API, and Blade frontend.

## Main features

- inventory Management for car parts. Car parts will be linked to tpes, brands and vehicle models
- Low stock levels notification
- Reporting features. Sales, purchases inventory, creditors, debtors.
- Point of sale for selling the car parts
- Dashboard Analytics. For the business performance
- Warehouse and manual stock taking
- Multi-branch control
- Customer and supplier management
- Sales analysis

## Technology

- PHP `8.4`
- Laravel
- MySql Database
- Node.js `[VERSION]`
- Blade front end
- `[CSS OR COMPONENT FRAMEWORK]`

## Requirements

- PHP and the extensions required by `composer.json`
- Composer
- Node.js and npm
- Supported database server
- Git

## Local installation

1. Clone the repository.
2. Install PHP dependencies:

   `composer install`

3. Install frontend dependencies:

   `npm install`

4. Copy `.env.example` to `.env` and set local values.
5. Generate the application key:

   `php artisan key:generate`

6. Create a local database and configure the database variables.
7. Run migrations:

   `php artisan migrate`

8. Build or start the frontend:

   `npm run dev`

9. Start Laravel using the approved local environment.

<!-- INACTIVE: Run `php artisan db:seed` only if the seeders are safe and required for local setup. -->
<!-- INACTIVE: Run `php artisan storage:link` only if the application serves public files from Laravel storage. -->

## Development commands

| Task | Command |
| --- | --- |
| Run tests | `php artisan test` |
| Check PHP formatting | `vendor/bin/pint --test` |
| Apply PHP formatting | `vendor/bin/pint` |
| Frontend development | `npm run dev` |
| Production frontend build | `npm run build` |

<!-- INACTIVE: Frontend lint: `npm run lint`. Activate only if available. -->
<!-- INACTIVE: Static analysis: `vendor/bin/phpstan analyse`. Activate only if installed. -->

## Project documentation

- AI working instructions: `AGENTS.md`
- Architecture: `docs/ARCHITECTURE.md`
- Database: `docs/DATABASE.md`
- API conventions: `docs/API_CONVENTIONS.md`
- Frontend conventions: `docs/FRONTEND.md`
- Testing: `docs/TESTING.md`
- Security: `SECURITY.md`
- Architectural decisions: `docs/DECISIONS/`

## Deployment

`[Describe the approved deployment process without including credentials, private hostnames, or secret values.]`

<!-- INACTIVE: Deployment is automated through CI/CD. Activate only after documenting the actual workflow. -->

## Support and ownership

- Product owner: `Ronald Fred Sikwese`
- Technical owner: `Bitree`
