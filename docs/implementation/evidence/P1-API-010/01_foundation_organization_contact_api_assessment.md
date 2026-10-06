# P1-API-010 Foundation / Organization / Contact API Assessment

## Outcome

Implemented the CONTRACT_READY Foundation, Organization, Organization tree,
and Contact HTTP surfaces under `/api/v1`. No export response, Employee API,
API-020 route, schema change, or new Resource/Action was added.

## Adapter and application boundary

- `FoundationOrganizationContact` is a thin HTTP controller. It delegates JSON
  parsing, query parsing, and all business work to the API-010 application
  service.
- `Api010FoundationOrganizationContactService` builds an actor-bound
  `OperationContext`, calls `AuthorizationService`, and delegates mutations to
  `FoundationLocationService`, `OrganizationService`, and `ContactService`.
- `AuthorizedArrayListSource` accepts only rows already passed through the
  endpoint authorization/data-scope predicate. `CommonListQueryAdapter` then
  applies endpoint allowlists, query validation, deterministic sort, and
  pagination. Totals are counted from that same authorized row set.

## Implemented routes

- Province: list, create, get, patch, set-state.
- Ward: list, create, get, patch, set-state, including `province_id` filter and
  service-level province validation.
- Organization: list, create, get, patch, set-state, and visible-node tree.
- Contact: list, create, get, patch, set-state, with contact type/reference and
  owner-derived authorization.
- No `/export` route was added.

## Authorization and transport

- Province/Ward authorization evaluates the GROUP context through the existing
  PBAC service.
- Organization creation uses the canonical `parent_id` context; existing
  targets use the persisted target organization.
- Contact authorization derives the owner organization from the persisted or
  requested organization owner, or the employee primary assignment. Client
  evaluation context is never trusted.
- Individual visibility is checked before mutation, producing 404 for an
  invisible target and 403 for a visible target with a denied action.
- Lists authorize rows before filtering and counting, preserving
  authorization-safe totals and hiding unauthorized tree/contact rows.
- ApiTransport preserves BIGINT entity identifiers as strings while collection
  page, page_size, and total remain JSON integers; request IDs and `vi`/`en`
  headers remain shared API-001 behavior.

## OpenAPI and generated client

OpenAPI contains all implemented API-010 paths and request/resource/collection
schemas. Export and API-020 surfaces remain absent. Generated frontend files are
produced by `@hey-api/openapi-ts` and pass the repository ownership check.

## Validation evidence

- `vendor\\bin\\phpunit --no-coverage tests\\unit\\P1Api010FoundationOrganizationContactApiTest.php` |
  PASS, 3 tests / 6 assertions.
- `vendor\\bin\\phpunit --no-coverage tests\\unit\\P1Api001TransportTest.php tests\\unit\\P1Api003ListQueryTest.php tests\\unit\\P1Org001OrganizationRepositoryTest.php tests\\unit\\P1Org002OrganizationServiceTest.php tests\\unit\\P1Con001ContactServiceTest.php` |
  PASS, 26 tests / 119 assertions.
- `vendor\\bin\\phpunit --no-coverage` | PASS, 378 tests / 3,441 assertions.
- `vendor\\bin\\phpstan analyse --configuration=phpstan.neon --no-progress` |
  PASS, level 8.
- `pnpm run openapi:lint` | PASS.
- `pnpm run openapi:generate:check` | PASS, 17 tool-owned generated files.
- `pnpm run type-check` and `pnpm run build` | PASS.
- `validate-context-cache.php` | PASS, valid cache and acyclic dependencies.
- `architecture-guard.php` | PASS.
- `validate-ledger.php` | PASS, 73 completed tasks.
- `git diff --check` | PASS.

## Scope note

The pre-existing modification under `docs/thamkhao (Không được sử dụng)/` was
left untouched and is excluded from the task commit.
