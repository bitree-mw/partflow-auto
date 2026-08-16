# Architectural Decision Records

## Purpose

This folder records important technical decisions so future developers and AI agents understand why the application uses a particular approach.

Use a decision record when choosing or changing:

- Authentication strategy.
- API versioning or response format.
- Frontend framework or state management.
- Repository or service patterns.
- Queue, cache, search, storage, or notification technology.
- Multi-tenancy or branch-scoping strategy.
- Financial calculation or audit design.
- A dependency that significantly affects the architecture.

Do not create a decision record for routine implementation details.

## Naming

Use sequential filenames:

- `0001-use-sanctum-for-api-authentication.md`
- `0002-standardize-api-responses.md`
- `0003-adopt-queue-for-email-notifications.md`

Copy `0000-template.md`, assign the next number, and replace all placeholders.

## Status values

- Proposed: Under discussion and not yet approved.
- Accepted: Approved and currently applicable.
- Superseded: Replaced by a newer decision.
- Rejected: Considered but not adopted.
- Deprecated: Still present but planned for removal.
