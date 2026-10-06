# PRE-GATE7-ARCH-GUARD Architecture Guard Reconciliation

## Scope and state

- Outcome: reconcile the two findings reported by `architecture-guard.php` before Gate 7.
- P1 progress remains `69/100`.
- Gate 6 remains `CLOSED`.
- Gate 7 remains `0/10`.
- `P1-API-001` was not started.
- No schema, migration, public API, UI, or business-semantic change was made.

## Authority and violated rule

The governing implementation rule is `docs/implementation/P1_BACKEND_CODING_CONTRACT.md:22-25`:
repositories own persistence queries, while application/domain services own invariants and transaction orchestration and do not issue table queries directly. The guard implements this boundary in `.codex/skills/dinkerp-implementation/scripts/architecture-guard.php:59-62` by scanning service/resolver classes for `->table()` and `->query()` calls.

## Findings and reconciliation

### Finding 1

- Issue: direct database query in resolver.
- Authority source: `P1_BACKEND_CODING_CONTRACT.md:22-25` and the architecture guard query rule at `architecture-guard.php:59-62`.
- Conflicting text: the pre-change `backend/app/Authorization/EmployeeContextResolver.php:27` called `$this->db->table('employees')` directly. The same resolver also queried `employee_assignments` and `organizations` directly at its pre-change lines 40 and 77.
- Classification: **A - real architecture violation**. This was application/resolver persistence access, not an obsolete or false-positive pattern.
- Resolution: route employee lookup through `EmployeeRepository::findByUserId()`, effective assignments through `EmployeeAssignmentRepository::findEffective()`, and organization-tree reads through `OrganizationRepository::findAll()`. Existing active-employee filtering, effective-date filtering, and tree expansion remain in force. Repositories receive the resolver's existing DB connection.
- Affected documents: none.
- Affected code: `backend/app/Authorization/EmployeeContextResolver.php`.
- Remaining blocker/pending status: none for this finding.

### Finding 2

- Issue: direct database query in service/resolver.
- Authority source: `P1_BACKEND_CODING_CONTRACT.md:22-25` and the architecture guard query rule at `architecture-guard.php:59-62`.
- Conflicting text: the pre-change `backend/app/Modules/Product/Product/ProductMasterService.php:723` called `$this->db->table('organizations')` directly in `numberingContext()`.
- Classification: **A - real architecture violation**. The service was performing a persistence existence check that belongs in the repository layer.
- Resolution: route the existence check through `OrganizationRepository::find()`, preserving `EVALUATION_ORGANIZATION_NOT_FOUND` and the authoritative `GROUP` evaluation context. The repository receives the service's existing DB connection.
- Affected documents: none.
- Affected code: `backend/app/Modules/Product/Product/ProductMasterService.php`.
- Remaining blocker/pending status: none for this finding.

## Behavioral boundary

Behavior is unchanged. The correction changes only the persistence access boundary and constructor dependency wiring. No Resource/Action, authorization semantics, transaction behavior, schema, API route/payload, UI behavior, or business rule was changed.

## Validation record

Commands and results are recorded after the corrective change:

| Check | Result |
| --- | --- |
| Focused authorization/context and Product master tests | PASS; `vendor/bin/phpunit --no-coverage tests/unit/P1Ctx001EvaluationOrganizationResolverTest.php tests/unit/P1Auth002AuthorizationServiceTest.php tests/Modules/Product/Product/P1Prd003ProductMasterServiceTest.php`; 20 tests, 87 assertions |
| Full backend PHPUnit | PASS; `vendor/bin/phpunit --no-coverage`; 362 tests, 3351 assertions |
| PHPStan level 8 | PASS; `vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` |
| `validate-context-cache.php` | PASS; 7 authorities, 100 tasks, 34 rules, 6 decisions, acyclic dependency graph |
| module-map coverage | PASS through the context-cache validator; no standalone module-map validator exists in the repository |
| `validate-ledger.php` | PASS; 100 tasks, 69 DONE, 6 decisions |
| `architecture-guard.php` | PASS; zero findings; 29 application files checked |
| `git diff --check` | PASS; no whitespace errors (Git reported only the repository's existing LF/CRLF normalization warning for the two edited PHP files) |

The change is documentation/evidence plus the smallest corrective repository-boundary code change required to satisfy the existing architecture invariant. Exactly one scoped corrective/meta commit is required at closeout.
