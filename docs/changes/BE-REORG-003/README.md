# BE-REORG-003 — Contact module relocation

## Scope
BE-REORG-003 moves the existing Contact application and SQL persistence implementation behind `App\Modules\Contact` while keeping `App\Services\ContactService` and `App\Repositories\ContactRepository` as explicit compatibility delegates. `ContactLifecycleAdapter`, API-010, controllers, authorization/IAM and workflow infrastructure remain in place.

This is behavior-preserving relocation only. It does not change schema, migrations, routes, OpenAPI, frontend code, Contact owner kinds, lifecycle rules, authorization behavior, transactions or locking. No Contact domain lock is introduced.

## Patch order
Apply only after the accepted chain through `BE-REORG-002_FIX01`. Do not reapply earlier patches.

## Runtime verification
From `D:\XAMPP\htdocs\erp\backend` run exactly one guarded command:

```powershell
php tools/test-contact.php --confirm-test-db
```

The runner accepts no other execution flag. It verifies the disposable loopback `erp_test` target, obtains the existing exclusive runner lock, runs full PHPUnit exactly once, validates fresh JUnit evidence for Foundation, Organization and Contact gates, then runs the unchanged PHPStan command. Output is written to project-root `test-result.log`.

`DatabaseTestTrait` can refresh test tables. Never run another test process concurrently against `erp_test`.

If no unrelated tests were added, the expected suite size after this patch is 743 tests: accepted 711 plus 32 BE-REORG-003 cases. Required Contact evidence is 4 boundary + 10 compatibility + 9 characterization + 5 HTTP + 4 lifecycle cases, with none skipped.

PHPStan is still expected to report the inherited 121 diagnostics unless another approved change modifies them. Acceptance compares the exact normalized diagnostic set; count-only equality is insufficient. Do not add ignores, lower the level or regenerate a baseline.

## What runtime tests prove
The HTTP tests run in-process through CodeIgniter routes/filters/controllers, Shield sessions, PBAC and the real test database. Lifecycle tests exercise the unchanged `ContactLifecycleAdapter` with real workflow transactions/row locking. They do not prove browser rendering, Apache/XAMPP routing, reverse-proxy behavior or general race freedom.

## Rollback
On a tree with no later overlapping edits, reverse only the BE-REORG-003 patch. There is no database rollback, migration, seed or Composer command for this code-only reorganization.
