# P2 Module Handoff — [Module] / [Wave]

**Status:** DRAFT · **Baseline:** P1 v1.9.0 · **Parent UR:** [UR-*]

## 1. Functional boundary

Owner, Company scope, shared P1 service dependencies, actors, legal states, POST side effects, non-goals. Include UR exact references.

## 2. Technical scope

- New migration(s), fields, FK/indexes/unique constraints:
- Resource.Type/Actions, PBAC evaluation context:
- Controller + Application service + domain rules:
- OpenAPI operations, DTOs, client generation:
- Vue screen + route + i18n / presentation-only rules:
- Adapter/webhook/job/retention where applicable:

## 3. Transaction & security

- Posted mutable/immutable fields, idempotency keys:
- Row lock order, rollback, concurrency cases:
- SQL-scoped list/sort/page and cross-Company deny:
- Audit/secret redaction, CORS/CSRF protection:

## 4. Tests

| UR/FR | Test ID | Implementation | Evidence |
|---|---|---|---|

## 5. Deployment & signoff

DEV clone dry-run, backup, migration compatibility, API lint, client generation, P1 regression, release enable/disable, runbook, accepted waivers and reviewer signoff.
