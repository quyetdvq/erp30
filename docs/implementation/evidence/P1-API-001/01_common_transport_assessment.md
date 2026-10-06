# P1-API-001 Common Transport Assessment

## Scope

Implemented only the common `/api/v1` transport boundary. No business route,
list/query adapter, OpenAPI generation pipeline, frontend code, schema change,
or authorization semantic change was added.

Dependencies were resolved from the compiled task context as `P1-INF-002` and
`P1-AUTH-002`; the authority cache was valid before implementation.

## Implemented contract

- Existing INF-002 `RequestContextFilter`/`RequestContext` remains the request
  correlation and locale source. The API exception edge also establishes that
  context for unmatched routes, which do not reach normal route filters.
- `/api/v1` responses use `data`/`meta` success envelopes and
  `error`/`meta` error envelopes.
- Request IDs accept the existing valid incoming format, generate UUID v4 when
  absent/invalid, echo `X-Request-ID`, and include `meta.request_id`.
- `vi` and `en` are supported; unsupported or missing language falls back to
  `vi`; localized human text includes `Content-Language` while machine codes
  remain stable.
- Identifier fields and decimal domain values serialize as strings; keys are
  normalized to snake_case.
- API exceptions map to 400/401/403/404/409/422/500. Production-facing 500
  responses are generic and do not expose exception messages, SQL, paths, or
  traces. Existing non-API requests delegate to the CodeIgniter handler.
- Existing Health and Auth controllers now use the shared transport.

## Validation evidence

| Check | Result |
|---|---|
| `vendor/bin/phpunit --no-coverage tests/unit/P1Api001TransportTest.php` | PASS, 4 tests / 33 assertions |
| `vendor/bin/phpunit --no-coverage tests/unit/Gate0InfrastructureTest.php` | PASS, 5 tests / 12 assertions |
| Relevant AUTH regression (`P1Auth002`, `P1Auth003`, `P1Auth004`, `P1AuthorizationMandatoryRegressionTest`) | PASS, 25 tests / 134 assertions |
| `vendor/bin/phpunit --no-coverage` | PASS, 366 tests / 3386 assertions |
| `vendor/bin/phpstan analyse --configuration phpstan.neon --no-progress` | PASS, level 8 |
| `pnpm openapi:lint` | PASS |
| `validate-context-cache.php` | PASS, cache valid |
| `validate-ledger.php` | PASS, 70 DONE tasks / Gate 7 1 DONE |
| `architecture-guard.php` | PASS |
| `git diff --check` | PASS |

Live smoke checks against `/api/v1/health`, unmatched `/api/v1/*`, and
unauthenticated `/api/v1/auth/me` confirmed envelope shape, request-ID header,
language fallback/selection, generic 404 behavior, and 401 mapping. Typed
frontend generation remains API-002 scope and was not started.
