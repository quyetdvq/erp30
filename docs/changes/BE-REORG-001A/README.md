# BE-REORG-001A - Foundation runtime verification

**Base:** v1.1.5 with BE-REORG-001 already applied.
**Status:** test candidate; new MySQL/CodeIgniter tests have NOT run in the author's container.
**Scope:** tests, test helpers and one-command runner only. No application,
frontend, OpenAPI, migration, dependency, existing-test or PHPStan-config edits.
This does not start Organization/Contact/HR reorganization.

## Apply and run (PowerShell)

Save the new patch at `D:\XAMPP\htdocs\erp`, keep a checkpoint of local changes,
and run:

```powershell
Set-Location D:\XAMPP\htdocs\erp
$patch = '.\erp_v1.1.5_BE-REORG-001A_verification_2026-10-16.patch'
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw 'Patch mismatch. Stop and send the error.' }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw 'Apply failed. Stop and send the error.' }
Set-Location .\backend
php tools/test-foundation.php --confirm-test-db
```

Do NOT reapply or reverse the old BE-REORG-001 patch. The new patch is additive.
No Composer update, migrations, baseline checkout, extra accounts, API URL,
password or browser setup is required for these automated tests.
The pre-existing vendor dependencies must already be installed from the lockfile.

`--confirm-test-db` is the operator's explicit confirmation that the configured
loopback host/port and `erp_test` database are isolated/disposable. A name alone
is not proof. A local proxy/tunnel can still target a remote server: the operator
must check that no production data or connection is behind the local endpoint.
DatabaseTestTrait refreshes test tables; never use the business database, and
never run another test process on the same test database while this runs.

The runner checks environment, group, host, port, database, driver, DSN,
failover, persistent connections and table prefix before launching PHPUnit.
A guarded PHPUnit bootstrap rechecks the EFFECTIVE child configuration after
XML/env overrides and before any test can refresh tables. Local custom PHPUnit
bootstraps are refused rather than silently replaced. No credentials are logged.

The runner executes the full backend suite ONCE, checks fresh JUnit evidence
for all 13 new cases with no skipped case, then runs PHPStan. Results are merged
into the project-root **`test-result.log`** (overwrites that generated log).
Send this one log and the patch name. Inspect it for secrets before sharing.
A source snapshot is not needed for a successful run.

Exit codes: `0` all automated checks passed; `1` PHPUnit/PHPStan failed;
`2` prerequisites, safety checks or evidence gate blocked execution. Known
PHPStan diagnostics remain FAIL; compare exact diagnostics against the previous
report (125 errors) rather than accepting a count alone. No level/ignore changes.

## Added checks

Nine HTTP feature tests cover actual application routes, filters, controllers,
Shield test sessions, PBAC, workflow, repositories and the test database:

1. Province/Ward create, detail, edit and list JSON contracts and trusted audit.
2. Anonymous mutation rejects with 401 and no business-row write.
3. A visible read-only actor rejects with 403 before status validation.
4. ABSOLUTE_DENY rejects an actor that has base edit permission.
5. Editing a historical Ward succeeds; choosing another inactive Province fails.
6. An Organization address reference blocks reparent without partial writes.
7. A Contact reference blocks reparent; removing it allows the same change.
8. Dedicated Province/Ward state routes use configured transitions; PATCH cannot bypass them.
9. Hidden records are excluded before total/pagination and cannot be retrieved.

CodeIgniter's feature runner can propagate exceptions rather than invoke the
web exception dispatcher. Those paths call the REAL ApiExceptionHandler with
the produced exception/request/response, not a test-generated error envelope.
Authentication uses Shield's test session helper, not a password login. These
are in-process HTTP integration tests, **not** a real browser, Apache or
reverse-proxy exercise, and not production CSRF/login validation.

Four MySQL/InnoDB tests use independent non-persistent connections, verified by
server CONNECTION_ID(), and do not substitute a mock DB:

1. The real repository's FOR UPDATE blocks another connection (server code 1205),
   then rollback releases it and the blocked connection can write.
2. A second service invocation commits between the first read and locking read;
   stale reparent raises CONCURRENT_MODIFICATION, preserves the winner and rolls
   back a marker inserted in the losing transaction.
3. The lifecycle adapter lock blocks a second connection, then commit releases it.
4. Reload observes a newer persisted state and rejects a mutation with the old source state.

A test-only repository decorator supplies the controlled before-lock
interleaving. It forwards all SQL operations to the real repository and adds
no hook to application code. The blocking tests require a real lock-timeout
error, not a guessed sleep duration or a generic exception. Session-level
lock timeouts affect only fresh connections which are closed on teardown.
These are focused concurrency-contract tests, not load testing or proof against
all possible schedules. They do not claim to cover a concurrent Contact insert
against Ward reparent, cross-service deadlocks, or simultaneous HTTP requests.

## Browser sign-off still separate

On an isolated UI/backend environment, confirm the user can find Province/Ward
pages, open forms, see error messages and refresh the persisted result. The
automated checks reduce fixture/permission/database work but cannot prove Vue
rendering or XAMPP routing. Do not mark browser UAT done from this runner alone.
Do not point a browser at the same test database during table refresh.

## Local author verification

PHP lint, the 17-case dependency-free safety guard, synchronization helper
forwarding, subprocess output/exit-code checks, hash protection and clean
patch apply/reverse are checked separately in the delivery evidence. Full
PHPUnit, HTTP/DB locks, PHPStan and Windows execution are unverified here:
this container lacks vendor, MySQL and required PHP extensions, and its
external dependency-fetch DNS probe failed. The runner stops safely with code 2.

All existing application files and tests remain byte-identical to the base.
No production bug fix is bundled. This is an author self-review, not an
independent reviewer sign-off. Do not promote to production solely on lint.

## Roll back only this verification patch

From project root, with no overlapping edits to these new files:

```powershell
git apply -R --check .\erp_v1.1.5_BE-REORG-001A_verification_2026-10-16.patch
if ($LASTEXITCODE -ne 0) { throw 'Stop: overlapping changes.' }
git apply -R .\erp_v1.1.5_BE-REORG-001A_verification_2026-10-16.patch
```

This removes the new tests/tools/docs, not the Foundation refactor and not data.
It does not undo data modified by running DatabaseTestTrait on a test database.

## Implementation references

- CI4 v4.7.4 `system/Test/FeatureTestTrait.php` and `CIUnitTestCase.php`.
- Shield documentation: `references/testing` / `AuthenticationTesting`.
- Existing fixture patterns in P1Wf006StateAwarePbacAndLocationTransitionsTest.
- Existing ApiErrorMapper, ApiExceptionHandler and Foundation lock implementations.
