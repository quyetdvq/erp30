# P1-SYS-002 - Authorization/Security Regression Run

Task: `P1-SYS-002`
Outcome: **DONE**
Risk/impact/profile: HIGH / backend-security / high-risk
HEAD before closeout: `3c8213114a44ee55f7751926b4d2b00c3df8a2e0`
Live ledger before closeout: `99/107 DONE`; P1-SYS-002 was `NOT_STARTED`.

## Preflight and scope

`php .codex/skills/dinkerp-implementation/scripts/task-context.php P1-SYS-002`
returned `AUTHORITY_CACHE: VALID`, live ledger `99/107 DONE`, and dependencies
Gate 3, Gate 7, Gate 7A, and P1-DSP-090. The dependency rows were `DONE` in the
live ledger. The required authority was read from FRS v1.12 sections 26.3/27,
Implementation Contract v1.10 sections 6/18/25, Implementation Plan v1.10
section 17, `P1_DECISIONS.md`, and approved change controls 08, 09, 10, and 11.

The Git worktree was on `main` at the pinned HEAD. Pre-existing Pricing changes
were preserved and excluded from this task:

- `backend/app/Modules/Pricing/PriceList/PriceListService.php`
- `backend/tests/Modules/Pricing/PriceList/P1Prc001PriceListServiceTest.php`

No SYS-003..007 scope was duplicated. This run covers authorization/security
regression evidence only; concurrency, production configuration, OpenAPI drift,
and release/UAT packages remain owned by their dedicated system tasks except for
the security-specific negative and serialization checks required here.

## Test profile and commands

The database isolation guard reported `CI_ENVIRONMENT=testing`, host
`127.0.0.1:3306`, database `erp_test`, prefix `dinktech_`, and `Isolation guard:
PASS` (18 migration rows visible).

| ID | Command | Result |
|---|---|---|
| H1 | Focused backend/API security profile: `php vendor/bin/phpunit --no-coverage` over `P1AuthorizationMandatoryRegressionTest`, `P1Auth001..005`, `P1Iam001..002`, `P1Ctx001..002`, `ChangeAuthDomain001AuthorizationTest`, `ChangeAuthDomain001MigrationTest`, `ChangePolicyScopeUniqueness001MigrationTest`, `ResourceCatalogRepositoryBulkActionsTest`, `P1Api001TransportTest`, `P1Api003ListQueryTest`, `P1Api090ContractRegressionTest`, `P1Dsp001..004`, `P1Api040AutoNumberingTest`, `P1Num001..006`, `P1Api060ProductImportTest`, `P1Imp001`, `P1Imp006..008`, `P1Imp090`, `P1Db050`, and `P1Seed003` | **PASS** - 234 tests, 1,647 assertions |
| H2 | `php vendor/bin/phpunit --no-coverage` | **PASS** - 440 tests, 3,907 assertions |
| H3 | `php vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress` | **PASS** - no errors, level 8 |
| F1 | `pnpm run test:unit -- --run src/composables/__tests__/basePermission.spec.ts src/services/__tests__/errorBoundary.spec.ts` | **PASS** - 2 files, 9 tests |
| F2 | `pnpm exec playwright test e2e/fe030-security.spec.ts --project=chromium --retries=0` | **PASS** - 5 tests |
| G1 | `php .codex/skills/dinkerp-implementation/scripts/validate-context-cache.php` | **PASS** - 12 authority hashes, 107 tasks |
| G2 | `php .codex/skills/dinkerp-implementation/scripts/validate-ledger.php` | **PASS** - dependency graph acyclic, 99/107 before closeout |
| G3 | `git diff --check` | **PASS** |

## Security evidence matrix

| Invariant / required negative path | Executable evidence | Result |
|---|---|---|
| Group and direct Base Permission; missing Base Permission denies before PBAC | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection`; `P1Auth002AuthorizationServiceTest::testMissingBasePermissionDeniesAndDoesNotUsePolicyAsPermission`; `P1Auth005SecurityAdministrationTest::testEffectiveProfileTracksGroupDirectAndInactiveSources` | **PASS** |
| NO_MATCH, DENY, ALLOW + DENY, ABSOLUTE_DENY precedence | `P1Auth002AuthorizationServiceTest` NO_MATCH/ALLOW/ABSOLUTE_DENY tests; mandatory core matrix | **PASS** |
| Multi-assignment denied-set intersection, secondary assignment, inheritance-before-intersection, and exact sibling/Company partition | `P1AuthorizationMandatoryRegressionTest::testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility`; `P1Auth002AuthorizationServiceTest::testTwoDeniedAssignmentLanesDenyByIntersection`, `testSiblingPolicyDoesNotBleedIntoEvaluationOrganization` | **PASS** |
| SYSTEM_ADMIN has no implicit allow and remains subject to Base Permission/PBAC/ABSOLUTE_DENY | `P1AuthorizationMandatoryRegressionTest::testMandatorySystemAdminUsesNormalBasePolicyAndSecurityPaths`; `P1Auth005SecurityAdministrationTest::testSystemAdminUsesNormalAuthorizationPathWithoutBypass`; import System Admin negative path | **PASS** |
| Resource Domain and authoritative Evaluation Organization; SYSTEM/MASTER resolve at GROUP; ORGANIZATION context is persisted/owner/parent-derived | `ChangeAuthDomain001AuthorizationTest` domain/resolver matrix; `P1AuthorizationMandatoryRegressionTest::testMandatoryCreateAndGroupResourceContextsAreAuthoritativeAndFailClosed`; `P1Ctx001/002` | **PASS** |
| Missing/unknown/unresolved context fails closed; client context cannot override authority; cross-Company isolation | `P1AuthorizationMandatoryRegressionTest` fail-closed/context tests; `P1Auth002AuthorizationServiceTest::testClientContextMismatchIsRejectedByAuthoritativeResolver`; sibling and Company assignment tests | **PASS** |
| 401/403/404 information boundary, stable error codes, no exception/SQL/secret leakage, scoped totals | `P1Api001TransportTest`; `P1Api003ListQueryTest`; `P1Api090ContractRegressionTest` error taxonomy and scoped collection checks | **PASS** |
| Shield-only identity mutation; state restrictions; group membership and direct ALLOW permissions; independent sensitive actions | `P1Auth005SecurityAdministrationTest` Shield mutation, denied zero-write, rollback, profile revocation, and independent capability tests; `P1Iam001ShieldIdentityAdapterTest`; `P1Seed003PrivilegedAuthorizationGroupTest` | **PASS** |
| Dedicated reset-password authorization is separate from `user.edit`; no local password authority | Shield identity/security administration suites and approved `CHANGE-USER-IDENTITY-MUTATION-001` contract boundary | **PASS** |
| Policy scope uniqueness, NULL versus concrete state, duplicate conflict, deterministic reconciliation, and atomic action/effect persistence | `P1Auth001PolicyRepositoryTest::testCanonicalScopeRejectsDuplicateNullStateAndAllowsConcreteState`; `ChangePolicyScopeUniqueness001MigrationTest` reconciliation and unproven-equivalence block tests; `P1Auth005SecurityAdministrationTest::testPolicyAdministrationValidatesResourceActionsAndNeverCreatesBasePermission` | **PASS** |
| Authorization audit is insert-only, trusted, and records sensitive decisions without caller-controlled fields | `P1Auth004AuthorizationAuditTest` full audit matrix; `P1AuthorizationMandatoryRegressionTest::testMandatoryAuditIsInsertOnlyAndTrusted` | **PASS** |
| Display Settings view/edit PBAC, visibility/inheritance/isolation, and presentation-only boundary | `P1Dsp001..004` and the authenticated Display Settings regressions included in H2; P1-DSP-090 dependency evidence | **PASS** |
| Lifecycle authorization remains separate from transition validity | `P1AuthorizationMandatoryRegressionTest::testMandatoryWorkflowRejectsInvalidTransitionAndDeniedMutation` | **PASS** |
| Numbering configuration uses dedicated permission, scope isolation, immutable issuance, and no allocation on denied/validation paths | `P1Api040AutoNumberingTest`, `P1Num001..006`, `P1Db050ResourceAuthorizationNumberingTest` | **PASS** |
| Product Import is Product-only and enforces GROUP `product.import` plus per-row create/edit/setstate gates, with row/number atomicity | `P1Imp007ProductImportAuthorizationServiceTest` full two-gate matrix; `P1Imp090ProductImportRegressionTest` gate-one/system negative, dependency, row, and generated-code checks; `P1Api060ProductImportTest` | **PASS** |
| Permission-aware frontend remains a UX hint; backend remains authoritative and 403/404 remain visible | F1 Base Permission/error boundary units; F2 `fe030-security.spec.ts` authenticated User/Group/Resource/Policy/Log/Workflow journey | **PASS** |

## Contract and closeout

No production source or schema changed. No Resource/Action, route, PBAC rule,
Shield authority, or frontend authorization semantics were introduced or
weakened. The required regression profile is fully green; there are no blockers
or unresolved assumptions for P1-SYS-002. The ledger update is evidence-only and
uses the repository's pre-commit `0000000` convention; the actual Git commit
hash is authoritative after commit.

Next dependency: `P1-SYS-003` (Concurrency/locking regression run).
