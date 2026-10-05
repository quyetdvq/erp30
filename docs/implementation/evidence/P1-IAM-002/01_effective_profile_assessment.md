# P1-IAM-002 EffectiveProfile Assessment

## Outcome

- Task: P1-IAM-002 - EffectiveProfile resolver.
- Status: DONE.
- Dependency: P1-IAM-001 is DONE and supplies the canonical Shield identity/group/direct-permission adapter.
- Scope is limited to runtime Base Permission resolution.
- No public route, API contract, UI, migration, or profile persistence was added.

## Resolution contract

- Active Authorization Group permissions and valid direct user additions are unioned.
- Direct user permissions are additions only; there is no direct DENY representation.
- Duplicate Resource + Action keys collapse deterministically and are returned sorted.
- Unknown, malformed, inactive, or future mappings do not create a Base Permission.
- SYSTEM_ADMIN follows the same adapter path as every other group; no bypass branch exists.
- The result contains only `BasePermission` values and no PBAC, Organization, Assignment, or Workflow state.
- Persistence exceptions from the adapter propagate and are not converted to an empty profile.
- No `profiles` table or EffectiveProfile persistence side effect is introduced.

## Validation evidence

| Check | Result |
|---|---|
| `task-context.php P1-IAM-002` | PASS; authority cache valid, dependency DONE, HIGH/backend-security |
| Focused P1-IAM-002 PHPUnit | PASS; 8 tests, 24 assertions |
| Focused IAM/privileged seed PHPUnit | PASS; 20 tests, 66 assertions |
| Full backend PHPUnit `--no-coverage` | PASS; 110 tests, 1,858 assertions |
| PHPStan level 8 | PASS; no errors |
| Architecture guard | PASS; 8 application files checked |
| Context-cache validator | PASS; 100 tasks, acyclic dependency graph |
| Ledger validator before closeout | PASS; 100 rows, 33 DONE |
| Scoped task-file `git diff --check` | PASS |
| Repository-wide `git diff --check` | NOT PASS; pre-existing protected `backend/app/Config/Cors.php` trailing whitespace only |

## Boundary

The pre-existing `backend/app/Config/Cors.php` modification was preserved,
unstaged, and excluded from the task commit. No PBAC decision, organization
scope, assignment lane, workflow evaluation, cache authority, or API/UI work
was started.
