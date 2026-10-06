# P1-NUM-005 Resource Identifier Adapter Assessment

Status: DONE
Task: P1-NUM-005
Risk: HIGH

## Scope

Implemented the generic Resource identifier contract and orchestration boundary
only. The numbering layer now consumes approved runtime metadata and an
explicit adapter registry; it does not know concrete business tables,
identifier columns, Product SQL, or resource-specific uniqueness queries.
Product adapter behavior remains out of scope for P1-NUM-006.

## Contract and registry

`ResourceIdentifierMetadata` validates canonical Resource type, identifier
field identity, positive maximum length, and `GLOBAL`/`COMPANY`/
`ORGANIZATION` uniqueness scope without persistence. `ResourceIdentifierAdapter`
exposes metadata, scoped occupancy, manual validation, and typed collision
classification. `ResourceIdentifierAdapterRegistry` is explicit, deterministic,
rejects duplicate/key-mismatched registrations, and fails closed for unknown
types. No dynamic SQL, reflection, client-selected table/column, or Product
adapter was added.

## Integration behavior

`AutoNumberService` resolves the canonical Resource row and series by Resource
identity, validates explicit ACTIVE series or the single ACTIVE default, and
preserves manual behavior when no default exists. It passes the authoritative
NUM-003 Evaluation Organization, operation time, and resolved scope/period to
the existing NUM-004 allocator. Adapter metadata is supplied to the existing
NUM-002 parser/compatibility validator; rendered and manual identifiers are
length-checked without truncation.

`AUTO_REQUIRED` rejects manual create identifiers and delegates blank values to
NUM-004. `AUTO_IF_EMPTY` validates and persists a nonblank manual identifier as
`generated=false` without creating or advancing a counter; blank values use
NUM-004. Adapter collision classification is translated only to the existing
typed `NUM_IDENTIFIER_COLLISION`; unrelated failures propagate. The adapter
callback remains inside the allocator's one transaction and cannot commit a
second transaction through this boundary. Ordinary issuance adds no
`resource.configure_numbering` authorization and no SYSTEM_ADMIN bypass.

## Validation evidence

| Check | Result |
|---|---|
| `php vendor/bin/phpunit tests/unit/P1Num005ResourceIdentifierAdapterTest.php --no-coverage` | PASS, 6 tests / 27 assertions |
| NUM-001/002/003/004 + NUM-005 focused suite | PASS, 46 tests / 232 assertions; includes real existing-counter and first-counter races |
| Authorization regression + NUM-001 | PASS, 18 tests / 111 assertions |
| `php vendor/bin/phpunit --no-coverage` | PASS, 215 tests / 2382 assertions |
| `php vendor/bin/phpstan analyse --level=8 --no-progress` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/architecture-guard.php` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS after ledger update |
| `git diff --check` | PASS |

No architecture baseline, migration, Product service, import, API, or UI file
was changed.
