# BE-REORG-001: Foundation module test candidate

Base: the supplied ERP v1.1.5 archive, SHA-256
`f007fbf3fb783182cbd38aaad975080067b72919a3a3b14a246f7d5828357426`.

## Scope

The existing service, repository and lifecycle adapter are final, explicit
composition wrappers. Their public signatures, parameter names/defaults, return
types and constants are retained. No new logic belongs in those wrappers.

Canonical implementation:

- `app/Modules/Foundation/Application/LocationService.php`
- `app/Modules/Foundation/Application/Ports/LocationRepositoryInterface.php`
- `app/Modules/Foundation/Infrastructure/Persistence/SqlLocationRepository.php`
- `app/Modules/Foundation/Contracts/LocationErrors.php`
- `app/Integration/Foundation/LocationLifecycleAdapter.php`
- `app/Bootstrap/FoundationFactory.php`

The factory does not cache instances or register shared CI services. Application
receives a stateless repository port on the supplied connection. An explicitly
supplied repository is retained even if its caller supplies another connection.
The adapter retains optional dependency resolution so an invalid type is rejected
before any default DB resolution. Service workflow resolution remains lazy; clock
capture still occurs when each mutation starts.

No change to the API facade, callers, routes, schema/migrations/seeds, frontend,
existing tests, configuration, composer files or PHPStan level. There is no
schema deployment or migration rollback for this patch.

## Verification status

This is a patch for operator testing, **not a runtime-accepted release**.
The implementation environment lacks `vendor`, required PHP extensions and an
isolated MySQL/MariaDB instance; network/DNS is unavailable for dependency setup.
PHPUnit and PHPStan execution attempts stop at missing executables. This is an
environment blocker, not an application test failure and not a passing suite.

Available checks: native PHP syntax, public Reflection contract equality, explicit
module dependency checks, normalized original/moved method-body comparison, and
supplementary real-code execution with an in-memory DB double. The latter does
not run CodeIgniter, enforce foreign keys, prove row locks/concurrency, or test
HTTP/authorization. Full runtime evidence remains required by C01-C20 in the spec.

Review was a separate author self-review, not an independent code review.

## Safe before/after test procedure

Use a disposable checkout and an independently verified test database. Confirm
host, port and database identity; the name `erp_test` alone does not prove safety.
`DatabaseTestTrait` can recreate tables even in tests under `tests/unit`.
Do not run reset/fresh/migrate/seed against the operational database.

From `backend/`, use the same PHP, composer.lock, test DB and settings before and
after the patch. If dependencies need installing, use `composer install`, never
`composer update`. Resolve missing extensions rather than ignore platform checks.

Before applying the patch:

```powershell
php tools/test-db-harness.php inventory
if ($LASTEXITCODE -ne 0) { throw 'Stop: DB inventory failed.' }
# Stop here until the operator verifies an isolated test host/port/database.
php tools/test-db-harness.php full-tests
if ($LASTEXITCODE -ne 0) { throw 'Stop: capture and classify baseline failures.' }
php vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
if ($LASTEXITCODE -ne 0) { throw 'Stop: capture baseline PHPStan output.' }
```

The delivery package also contains the new characterization test as an external
file. Run it BEFORE applying the patch without copying the other module tests
into the original tree:

```powershell
php vendor/bin/phpunit --no-coverage ..\BE-REORG-001_package\baseline-tests\BeReorg001FoundationCharacterizationTest.php
if ($LASTEXITCODE -ne 0) { throw 'Stop: capture baseline characterization output.' }
```

After applying the patch and refreshing the autoloader:

```powershell
composer dump-autoload
if ($LASTEXITCODE -ne 0) { throw 'Stop: autoload generation failed.' }
php vendor/bin/phpunit --no-coverage --filter "BeReorg001Foundation"
if ($LASTEXITCODE -ne 0) { throw 'Stop: pilot tests failed.' }
php tools/test-db-harness.php full-tests
if ($LASTEXITCODE -ne 0) { throw 'Stop: backend regression failed.' }
php vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=1G
if ($LASTEXITCODE -ne 0) { throw 'Stop: PHPStan failed.' }
```

Do not weaken existing assertions or overwrite expected results to obtain green
output. Record pre-existing failures separately. Full default Playwright does not
replace smoke tests with the actual backend.

Real-backend smoke: province/ward list/search/filter/pagination; create and edit;
non-authorized writes; lifecycle transition; historical Ward edit; reparent with
organization/contact references; simultaneous updates against the same Ward.
The in-memory check is not evidence for MySQL lock behavior.

## Apply and rollback

From repository root, with the downloaded patch at that root:

```powershell
git status --short
git apply --check .\erp_v1.1.5_BE-REORG-001_foundation_2026-10-16.patch
if ($LASTEXITCODE -ne 0) { throw 'Stop: patch does not match this working tree.' }
git apply .\erp_v1.1.5_BE-REORG-001_foundation_2026-10-16.patch
if ($LASTEXITCODE -ne 0) { throw 'Stop: patch apply failed.' }
```

Do not use `--reject`, force overwrite local changes, or mix archive versions.
Wrapper and canonical files must be deployed together, not copied piecemeal over
live requests. Keep the candidate in a test checkout until runtime gates pass.

To reverse the patch, before making further overlapping edits:

```powershell
git apply -R --check .\erp_v1.1.5_BE-REORG-001_foundation_2026-10-16.patch
if ($LASTEXITCODE -ne 0) { throw 'Stop: inspect later edits before rollback.' }
git apply -R .\erp_v1.1.5_BE-REORG-001_foundation_2026-10-16.patch
if ($LASTEXITCODE -ne 0) { throw 'Stop: rollback failed.' }
Set-Location .\backend
composer dump-autoload
```

Do not reset the database or roll back a migration to undo this code-only patch.
The next module is out of scope until this pilot is accepted.
