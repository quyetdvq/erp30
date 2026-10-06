# P1-API-002 OpenAPI Codegen Pipeline Assessment

## Scope

Implemented only the canonical OpenAPI and generated-client pipeline required
by `P1-API-002`. No business endpoint, list/query adapter, pending capability,
backend semantic, or UI screen was added.

The valid compiled task context identified this as HIGH-risk `api-contract`
work with dependencies `P1-API-001` and `P1-ENV-003`. The authority used was
Plan v1.5, API Design v1.3, and UI Design v1.4; the authority cache was valid
before implementation.

## Pipeline

- Canonical entrypoint: `contracts/openapi/openapi.yaml`.
- Redocly configuration: `contracts/openapi/redocly.yaml`, extending the
  repository's recommended ruleset with an explicit unused-common-component
  allowance for reusable response schemas.
- Frontend generator: `@hey-api/openapi-ts` 0.99.0 via
  `frontend/openapi-ts.config.ts` and `pnpm run openapi:generate`.
- Deterministic artifact location: `frontend/src/api/generated/`.
- Generated files carry the generator marker, are excluded from manual
  formatting/linting, and are marked `linguist-generated`; the
  `openapi:generate:check` command makes tool ownership explicit and fails if a
  TypeScript artifact lacks the marker.

## Common transport contract

The OpenAPI entrypoint documents `/api/v1`, snake_case response keys, success
and error envelopes, request IDs in the header and `meta.request_id`,
`Accept-Language` (`vi`/`en`, fallback behavior documented), `Content-Language`,
stable error codes, and reusable 400/401/403/404/409/422/500 error responses.
`BigInt` and `Decimal` schemas constrain BIGINT and exact decimal/money/quantity
values to JSON strings. Health remains the only path; no business endpoint was
invented.

## Validation evidence

| Check | Result |
|---|---|
| `pnpm run openapi:lint` | PASS, Redocly CLI 2.53.0 |
| `pnpm run openapi:generate` | PASS, 17 generated TypeScript files |
| Consecutive generation hash comparison | PASS, two runs byte-identical |
| `pnpm run openapi:generate:check` | PASS, all generated TypeScript files tool-owned |
| `pnpm run type-check` | PASS |
| `pnpm run lint` | PASS, ESLint and Oxlint |
| `pnpm run format:check` | PASS |
| `pnpm run build` | PASS |
| Focused API/INF-002/AUTH regressions | PASS, 26 tests / 115 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS, 367 tests / 3388 assertions |
| `vendor/bin/phpstan analyse --configuration phpstan.neon --level=8 --no-progress --memory-limit=1G` | PASS |
| `validate-context-cache.php` | PASS, cache valid |
| `architecture-guard.php` | PASS |
| `validate-ledger.php` | PASS, 71 DONE tasks / Gate 7 2 DONE |
| `git diff --check` | PASS |

No API-003 work or business endpoint was started. The final task commit is the
single scoped `P1-API-002` commit reported with the completion result.
