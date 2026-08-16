# AI-Assisted Development Workflow

## Purpose

This document defines how AI-assisted work should be planned, implemented, reviewed, and handed back to a human developer.

## Task request format

A useful task should state:

- Goal: What behaviour should change?
- Context: Which module, screen, endpoint, or files are relevant?
- Constraints: Which architecture, response format, permissions, or compatibility rules apply?
- Done when: What tests or user-visible result prove completion?

## New feature workflow

1. Inspect similar existing features.
2. Clarify missing business rules that materially affect implementation.
3. Identify database, API, authorization, frontend, and testing impact.
4. Provide a short implementation plan for approval when the change is large.
5. Implement in small stages following the repository architecture.
6. Add or update tests.
7. Run relevant checks and review the diff.
8. Update documentation when public behaviour or architecture changes.

Preferred Laravel build order when applicable:

`Migration → Model → Form Request → Service → Controller → API Resource → Routes → Tests → Frontend`

## Bug-fix workflow

1. Reproduce or clearly identify the failure.
2. Determine the root cause rather than treating only the visible symptom.
3. Add a regression test when practical.
4. Apply the smallest safe fix.
5. Verify related paths for regressions.
6. Report the cause, change, and verification.

## Refactoring workflow

- Preserve existing externally visible behaviour unless a change is requested.
- Establish test coverage before risky structural changes.
- Avoid combining broad refactoring with unrelated feature development.
- Explain why the refactor is valuable and what risk it reduces.

## Database-change workflow

- Confirm the affected schema and data volume.
- Create a forward migration and assess rollback behaviour.
- Consider indexes, locking, nullability, defaults, existing rows, and deployment order.
- Do not run destructive commands or production migrations automatically.

## Review workflow

Prioritize findings in this order:

1. Security and data-loss risks.
2. Incorrect business behaviour.
3. Authorization and validation gaps.
4. Backward compatibility and regressions.
5. Performance and query problems.
6. Missing tests.
7. Maintainability and style.

## Human approval required

Ask before:

- Adding or removing production dependencies.
- Making a breaking API or database change.
- Changing authentication or authorization strategy.
- Running destructive or production commands.
- Replacing a major framework or architectural pattern.
- Changing business calculations without a confirmed rule.
- Deleting files, modules, migrations, or large data sets.

## Handover format

At completion, report:

- Outcome achieved.
- Files changed.
- Tests and checks run.
- Assumptions made.
- Known limitations or follow-up work.

Do not present unverified behaviour as completed.
