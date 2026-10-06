# P1-NUM-006 Product Identifier Adapter Assessment

Status: DONE
Task: P1-NUM-006
Risk: HIGH

## Scope

Implemented the concrete Product `ResourceIdentifierAdapter` only. The
approved mapping is `product_code` (transport/import alias) to `products.code`
(canonical domain/database field), with max length 50 and GLOBAL uniqueness.
No `product_code` column, metadata table, Product CRUD/service, Product role,
UOM, pricing, COMBO, import, API, or UI behavior was added.

## Adapter and registry

`ProductIdentifierAdapter` uses the injected current CodeIgniter connection and
queries only `products.code`, without status, product type, Company,
Organization, or counter-scope filtering. Inactive rows remain occupied.
Manual validation enforces nonblank, max length, and the same global occupancy
namespace. `ResourceIdentifierAdapterRegistry` explicitly registers Product
when using the canonical default registry; unknown Resources remain fail-closed
and Product is not a generic fallback.

Only the typed `products.code` unique constraint is classified as
`AutoNumberingIdentifierCollision` eligible for NUM-004 advancement. `sku`,
`barcode`, FK, validation, and unrelated persistence failures propagate.
Database constraint diagnostics are used only for constraint identity, never
localized user-facing messages.

## Integration

Product metadata is exposed through the optional transport alias field and feeds
the existing NUM-002 compatibility validator. GLOBAL Product numbering accepts
GROUP patterns without a discriminator and requires the existing COMPANY_CODE
or ORG_CODE discriminator rules for narrower COMPANY/ORGANIZATION counters.
Generated occupancy, business mutation, and counter updates remain inside the
NUM-004/NUM-005 transaction and connection boundary; rollback leaves no
Product row or counter state. No Product authorization or `resource.configure_numbering`
behavior was added.

## Validation evidence

| Check | Result |
|---|---|
| `php vendor/bin/phpunit tests/unit/P1Num006ProductIdentifierAdapterTest.php --no-coverage` | PASS, 7 tests / 30 assertions |
| NUM-001..006 + DB-060 focused suite | PASS, 55 tests / 555 assertions; includes real NUM-004 races |
| `php vendor/bin/phpunit tests/database/P1Db060ProductUomComboTest.php --no-coverage` | PASS, 2 tests / 293 assertions |
| `php vendor/bin/phpunit --no-coverage` | PASS, 222 tests / 2412 assertions |
| `php vendor/bin/phpstan analyse --level=8 --no-progress` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/architecture-guard.php` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS after ledger update |
| `git diff --check` | PASS |

No architecture baseline or database migration was changed.
