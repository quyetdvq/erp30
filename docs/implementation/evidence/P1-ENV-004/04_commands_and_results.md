# P1-ENV-004 Commands and Results

All commands below were executed on 2026-10-05 from `D:\XAMPP\htdocs\erp\backend` unless stated otherwise.

| Command/check | Result | Evidence |
|---|---|---|
| `D:\XAMPP\php\php.exe -l tools\test-db-harness.php` | PASS | No syntax errors |
| `D:\XAMPP\php\php.exe tools\test-db-harness.php inventory` | PASS | Configured database `erp_test`; isolation guard PASS |
| Harness with `database.tests.database=erp` and `reset --yes` | PASS (expected refusal) | Exit 1; exact guard refused non-approved database |
| `D:\XAMPP\php\php.exe tools\test-db-harness.php fresh --yes` | PASS | Reset and current source migration chain completed; exit 0 |
| `D:\XAMPP\php\php.exe tools\test-db-harness.php rebuild --yes` | PASS | Two consecutive reset/fresh runs completed; exit 0 |
| `D:\XAMPP\php\php.exe tools\test-db-harness.php db-tests` | PASS | PHPUnit database path: 2 tests, 3 assertions |
| `D:\XAMPP\php\php.exe tools\test-db-harness.php full-tests` | PASS | PHPUnit suite: 5 tests, 7 assertions |
| Final `fresh --yes` | PASS | `erp_test` rebuilt to 25 migration rows and 29 tables |
| Development DB sentinel query | PASS | `erp` stayed at 26 migration rows, max `2026-09-29-000022` |
| Forward migration from prior schema | NOT RUN / BLOCKED | No reproducible prior schema snapshot exists |

## Harness commands

```text
php tools/test-db-harness.php inventory
php tools/test-db-harness.php prepare
php tools/test-db-harness.php reset --yes
php tools/test-db-harness.php fresh --yes
php tools/test-db-harness.php rebuild --yes
php tools/test-db-harness.php db-tests
php tools/test-db-harness.php full-tests
```

Destructive actions require `--yes` and fail unless `Config\Database::$tests` resolves exactly to `erp_test`. The child `spark` process receives `database.defaultGroup=tests`; this is required because CodeIgniter's `-g` option is a migration history group filter while the runner connection is selected by its default group.
