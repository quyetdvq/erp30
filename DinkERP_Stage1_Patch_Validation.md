# DinkERP Opt-in Lifecycle + Native State Stage 1: verification notes

## Scope and provenance

- Reconstructed replacement patch against the three ZIP snapshots `backend_v1.0.5.zip`, `frontend_v1.0.5.zip`, `contracts_v1.0.5.zip`.
- The original `DinkERP_OptIn_Lifecycle_Native_State_Stage1_After_WF006.patch` bytes were **not uploaded**, so no original-hunk-to-rebuild comparison was possible.
- The user's `35a7f52` working tree with uncommitted changes is external and was **not** inspected directly. Application on that state is **not verified**.
- No local repo or original source ZIP was modified; work used a separate temporary copy.

## What the replacement patch changes

1. Adds `ResourceNativeStateRegistry` with 10 native-only Resource types and Opt-in Native status catalogs for other Resource types.
2. Adds forward-only migration of `policies.status`, `resources.status` and native `workflow_states` codes. Preflight rejects unsafe native-only Lifecycle links and inactive existing canonical State codes before DDL.
3. Resolves persisted native/lifecycle State IDs through `workflow_states`, validates Policy `state_id`, and rejects mismatched external state hints when a persisted target is supplied.
4. Ignores inactive Policies when evaluating PBAC, and denies access to deactivated Resource types (no administrator authorization bypass). The canonical `resource` type cannot deactivate itself via normal management.
5. Keeps State and Lifecycle conceptually separate, validates Lifecycle opt-in and prevents dropping an assigned Lifecycle while dependent state-scoped Policies remain.
6. Exposes `GET /api/v1/resources/{id}/available-states` and updates OpenAPI.
7. Updates `SecurityPoliciesView.vue` to use the authoritative Resource State catalog, default All States, reset state/actions on Resource change, explicitly PATCH `state_id: null`, and edit the separate Policy status.
8. Updates `SecurityResourcesView.vue` to edit Resource status and send an explicit null when unassigning Lifecycle.
9. Adds four integration cases to `P1Wf006StateAwarePbacAndLocationTransitionsTest.php` and three pure registry tests.

## Checks performed in the sandbox

- `git apply --check` on clean v1.0.5 snapshot: PASS.
- `git apply` followed by `git apply --reverse --check`: PASS.
- `git diff --check` ignoring preexisting CRLF endings: PASS.
- `php -l` on all 12 changed PHP files: PASS (PHP 8.4 sandbox; deployment runtime PHP 8.3).
- OpenAPI YAML parsed; 87 paths, 169 schemas and zero unresolved component refs.
- **Not run:** PHPUnit, PHPStan, Vue type-check, Vitest, actual MariaDB migration or browser end-to-end. ZIP snapshots lack composer `vendor/`, Node `node_modules/`, and a configured MariaDB database.

## Safe validation in the real Windows repository

```powershell
# Do not reset or clean: the real working tree contains valuable uncommitted work.
git status --short
$patch = '.\DinkERP_OptIn_Lifecycle_Native_State_Stage1_After_WF006.patch'
git apply --check $patch
# Apply only if the previous line succeeds:
git apply $patch

git diff --check
cd backend
php spark migrate
php vendor/bin/phpunit tests/unit/P1Wf006StateAwarePbacAndLocationTransitionsTest.php
php vendor/bin/phpunit tests/unit/P1OptInLifecycleNativeStateRegistryTest.php
php vendor/bin/phpunit
php vendor/bin/phpstan analyse
cd ..\frontend
pnpm run openapi:lint
pnpm run openapi:generate:check
pnpm run type-check
pnpm run lint
pnpm run test:unit -- --run
```

**Important:** Migration may intentionally stop if any of the 10 native-only Resources already has a Lifecycle link or if a required canonical `workflow_states` code exists but is INACTIVE. Reconcile existing data with approved lifecycle/Policy behavior before proceeding; do not force-clear links or reset production data.

If `git apply --check` still fails on the local dirty working tree, capture the conflict and compare current files; do not run `git reset --hard` or force apply the patch.
