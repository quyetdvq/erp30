# DinkERP Backend v2 — Baseline Fidelity Policy

**Decision:** Functional behavior in `erp_v1.2.4.zip` is authoritative for the Backend Rewrite. Frontend source and the OpenAPI contract determine which behaviors must remain callable. Architecture and class names may change; business and HTTP semantics must not change accidentally.

## Evidence order

1. `erp_v1.2.4.zip` — reference source, including app translations, controllers, services, SQL and tests. SHA-256 of ZIP: `64d612196ef88e773b8f8015ed64881d3550ad02cb250405a3c5bccf7415507b`.
2. OpenAPI v1.2.4 and actual Frontend API calls — required request/response shape.
3. Executing baseline on a **copy** of the actual database and inspecting HTTP responses — behavioral evidence, especially for authorization, locales, and edge cases.
4. Existing Backend v2 tests — regression protection. A test against v2 or a pinned fixture does **not** prove end-to-end equivalence to a separately running baseline.

The locally installed `backend_basline` may be incomplete. M3.2.1's test explicitly found two missing PBAC engine files. If any original source is unavailable locally, use pinned source from the original ZIP **and disclose that substitution**; do not claim to have tested the installed baseline.

## Required parity dimensions for every implemented operation

- Endpoint, verb, optional inputs, content type, CSRF, cookie/session, CORS, redirects, request-id.
- Status code, machine-readable error code, error details (including field and message), `Content-Language`, envelope, response headers, and zero-content behavior.
- **Bilingual Backend:** `vi` (default) and `en`, `Accept-Language` negotiation, every app translation group, validation errors, Shield/CI4 Vietnamese overrides, field-level/domain-level messages, non-API exceptions where applicable. Maintain the exact baseline translation strings unless an explicit product decision approves a change.
- Null versus empty list/object, boolean encoding, ID as string, decimal as precise string, timestamp/timezone, stable search/sort/filter/page/count.
- Data visibility under PBAC: Shield base permissions, actor eligibility, assignment effective windows in UTC, organization scopes, `ALLOW`/`DENY`/`ABSOLUTE_DENY`, and all 10 independent Resource State exceptions.
- Lifecycle members, transitions, state sentinels and state precedence; no unapproved fallback from Lifecycle to native status.
- Transaction boundary, concurrency, optimistic version, idempotence if baseline supports it; no write outside authorized scope.
- File/avatar/product-image validation, URL shape and access controls. **Approved intentional difference:** physical storage is now `backend/public/upload/` while old Frontend URLs `/uploads/YYYY/...` remain supported.
- Audit records, side effects, import mode/row limits, numbering, error codes and normalized payloads.

## Hard gates for future M3.3+ work

1. Each new API operation must have a row in a parity table with baseline sources and representative requests/responses in **both `vi` and `en`**.
2. Unit tests must cover regular and error behavior; HTTP tests must compare status + headers + JSON, not just HTTP 200.
3. Include an authorized, a limited, and an unauthorized actor; run policy fixtures for every meaningful State/lane effect. Data outside scope must not appear in list, `total`, lookup, details or relationships.
4. Keep the 22 previously approved operations unchanged unless a new regression test demonstrates the deviation is a baseline correction.
5. Introduce or activate no write API before unified PBAC authorization is fully enforced and runtime-tested.
6. No database migration or Frontend change without explicit reason and approval. Preserve user-approved directories: `backend/`, `backend_basline/`, `backend/public/upload/`.
7. Record **remaining differences** and the reason for each; a new module is not finished while any unexplained baseline mismatch remains.

## Current known limits (after M3.2.2)

- Language files and baseline error-code message mapping are restored and pinned. This does **not** mean all 150 operations are implemented: only the existing 22 operations remain registered in Backend v2.
- Existing M3.2 actor parity checks compare M3.2 to M2.1.1 on the same persisted data; the `m32_baseline_lanes_pure.php` check compares to pinned baseline lane methods. Neither substitutes for separate live-baseline HTTP/PBAC tests.
- Locale negotiation on actual HTTP, Shield validation messages, exception details and individual business responses still need real XAMPP + MariaDB verification.
- Future Pricing, Catalog and other modules must preserve baseline decimal object serialization/precision when implemented; do not cast money to binary floats.
