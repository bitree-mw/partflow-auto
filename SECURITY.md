# Security Guidance

## Purpose

This file defines security expectations for `Partflow Auto`. It supplements `AGENTS.md` and applies to code, configuration, database work, tests, and documentation.

## Protected assets

- User accounts, credentials, tokens, and sessions.
- Personal, financial, employment, customer, or health information stored by the application.
- Business transactions, reports, stock, payments, or approval records.
- Uploaded files and generated exports.
- Database backups, logs, configuration, and infrastructure details.

## Security invariants

- Every protected action requires authentication and server-side authorization.
- Users may access only records permitted by their role, ownership, tenant, or branch scope.
- Client-supplied identifiers, prices, totals, permissions, and statuses are never trusted without validation.
- Passwords are stored only using Laravel-supported hashing.
- Secrets remain in environment or secret-management systems and are never committed.
- Public API errors do not expose internal implementation details.
- Sensitive operations maintain an appropriate audit trail.

## Prohibited automatic actions

AI agents must not automatically:

- Read, display, replace, or commit `.env` secrets.
- Connect to or modify production systems.
- Run destructive database commands.
- Disable authentication, authorization, CSRF protection, rate limits, or validation to make a test pass.
- Add a hard-coded password, token, API key, bypass account, or hidden administrator route.
- Log passwords, tokens, full payment information, or unnecessary personal data.
- Upload repository code or data to an unapproved third-party service.
- Change firewall, server, hosting, DNS, or cloud configuration without exact authorization.

## Authentication and sessions

- Use the project’s established authentication method.
- Regenerate sessions after login where session authentication is used.
- Revoke or expire tokens according to documented application rules.
- Apply rate limiting to login, password reset, verification, and other abuse-sensitive endpoints.

<!-- INACTIVE: Two-factor authentication is required. Activate only if implemented or approved. -->
<!-- INACTIVE: Sanctum personal access tokens have named abilities. Activate only if token abilities are configured. -->

## Authorization

- Use policies, gates, middleware, roles, permissions, ownership checks, or scoped queries.
- Do not rely on hidden buttons or frontend route guards.
- Prevent insecure direct object reference by checking access to every requested record.
- Default to denial when permission is unclear.

## Input, output, and uploads

- Validate type, format, length, range, and allowed values.
- Escape output using framework conventions.
- Whitelist sortable and filterable database fields.
- Validate uploaded file type, size, extension, and storage visibility.
- Generate server-side filenames and prevent path traversal.

<!-- INACTIVE: Uploaded files require malware scanning. Activate only if an approved scanner is integrated. -->

## Reporting security concerns

When a likely vulnerability is found:

1. Do not exploit production or access unrelated data.
2. Record the affected file and behaviour.
3. Explain realistic impact and required conditions.
4. Propose the smallest safe fix.
5. Add a regression test where practical.
6. Avoid placing secret values or sensitive records in the report.
