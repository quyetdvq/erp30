# P1-NUM-090 Mandatory Auto Numbering Regression Assessment

Status: DONE
Task: P1-NUM-090
Risk: HIGH

## Scope

Added the mandatory Gate 4 regression suite across NUM-001 through NUM-006.
The suite verifies active/default series configuration, issued-series identity
immutability and no-hard-delete, canonical pattern/reset rules, Product
`product_code` transport mapping to `products.code`, GLOBAL occupancy and
manual/generation paths, unknown-adapter fail-closed behavior, inactive-series
rejection, namespace compatibility, rollback/no-consumption behavior, and
schema/architecture drift guards. Existing NUM-004 tests continue to execute
the real MariaDB first-counter and existing-counter concurrency workers,
occupied-candidate advancement, final collision handling, and rollback paths.

No migration, schema, Product business service, Import, API, UI, or Gate 5
implementation was added.

## Mandatory regression and defect correction

The new executable-expression regression exposed that
`CanonicalNumberingPatternValidator` accepted PHP/template/SQL markers embedded
in literal pattern text. The minimal production correction rejects executable
markers in literal segments with the existing stable invalid-pattern error;
canonical tokens and ordinary literal text remain unchanged. This is the only
production-code change required by the mandatory suite.

## Validation evidence

| Check | Result |
|---|---|
| `php vendor/bin/phpunit tests/unit/P1AutoNumberingMandatoryRegressionTest.php --no-coverage` | PASS, 5 tests / 56 assertions |
| Gate 4 focused NUM-001..006 + DB-060 suite | PASS, 60 tests / 611 assertions; includes real MariaDB concurrency workers |
| `php vendor/bin/phpunit tests/unit/P1AuthorizationMandatoryRegressionTest.php --no-coverage` | PASS, 9 tests / 68 assertions |
| `php vendor/bin/phpunit --no-coverage` | PASS, 227 tests / 2468 assertions |
| `php vendor/bin/phpstan analyse --level=8 --no-progress` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/architecture-guard.php` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | PASS |
| `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | PASS before this ledger update |
| `git diff --check` | PASS |

No architecture baseline or database migration was changed. The required
allocation, rollback, collision, and no-reuse evidence is covered by the
focused suite and its real database workers.
