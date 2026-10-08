# P1-SYS-001 - Full FRS Acceptance Test Catalog Run

Task: `P1-SYS-001`  
Authority: FRS v1.12, sections 26.1-26.13 and 27; Implementation Contract v1.10 sections 18 and 25; Implementation Plan v1.10 section 17.  
Preflight: `AUTHORITY_CACHE: VALID`; live ledger before run `98/107 DONE`; HEAD `42c415b`; Gates 0-8 and Gate 7A ledger rows `DONE`; pre-existing pricing changes preserved.

## Outcome

**DONE.** The production preview root cause was fixed with a minimal Vite chunking change, the authenticated shell and locale journeys pass, and all frontend/backend release gates are green. Catalog totals: **135 PASS, 0 BLOCKED, 0 NOT_COVERED, 135 total**.

## Chromium root-cause diagnosis

The failing preview reproduced at `http://127.0.0.1:4174/organizations` with the `/api/v1/auth/me` route fulfilled as HTTP 200. The browser reported `TypeError: V is not a function` from `assets/naive-ui-DRCa_VvC.js`; no authentication failure, routing redirect, PBAC decision, or API response caused the blank page. The rendered DOM remained `<div id="app"></div>`, `.erp-shell` and `.mode-switcher` counts were zero, and the only request failure was the diagnostic's intentionally unmocked organizations API CORS request.

The production build had split Naive UI into multiple `naive-ui-*` chunks using a 500 kB `maxSize`; the generated cross-chunk export table was invalid at runtime. Removing only that `maxSize` constraint keeps Naive UI in one coherent chunk. The rebuilt preview mounted Vue normally (`shellCount=1`, `modeCount=5`), and the same five authenticated shell tests then passed. The temporary diagnostic captured console, page-error, network, URL, DOM, screenshot, and trace data before the standard Playwright run replaced its workspace artifacts.

## Commands and actual results

| ID | Exact command | Actual result | Evidence |
|---|---|---|---|
| B1 | `cd backend; php vendor/bin/phpunit --no-coverage` | PASS; 440 tests, 3909 assertions | PHPUnit testing profile selects `database.tests.database=erp_test` in `backend/app/Config/Database.php`. |
| B2 | `cd backend; php vendor/bin/phpstan analyse --no-progress` | PASS; PHPStan level 8, no errors | `backend/phpstan.neon`. |
| B3 | `cd backend; php vendor/bin/phpunit --no-coverage tests/database/ChangePolicyScopeUniqueness001MigrationTest.php tests/unit/P1Auth005SecurityAdministrationTest.php tests/unit/ResourceCatalogRepositoryBulkActionsTest.php` | PASS; 14 tests, 90 assertions | Focused regressions for approved change controls 09, 10, and 11. |
| F1 | `cd frontend; pnpm run type-check` | PASS | `vue-tsc --build`. |
| F2 | `cd frontend; pnpm run lint` | PASS | Oxlint and ESLint. |
| F3 | `cd frontend; pnpm run format:check` | PASS | Prettier checked `src/` and `e2e/`. |
| F4 | `cd frontend; pnpm run test:unit -- --run` | PASS; 15 files, 40 tests | Vitest output; includes full vi/en translation-leaf coverage. |
| F5 | `cd frontend; pnpm run build` | PASS | Vite production build. |
| F6 | `cd frontend; pnpm run openapi:lint` | PASS | Redocly validated `contracts/openapi/openapi.yaml`. |
| F7 | `cd frontend; pnpm run openapi:generate:check` | PASS | 17 generated API files tool-owned and drift-free. |
| F8 | `cd frontend; pnpm run test:unit -- --run src/composables/__tests__/basePermission.spec.ts` | PASS; 2 tests | Focused approved change-control 08 Base Permission UX hint regression. |
| E1 | `cd frontend; $env:CI='1'; pnpm run test:e2e` | PASS; 123 tests across Chromium, Firefox, and WebKit | Full production-preview profile completed in 5.2 minutes. |
| E2 | `cd frontend; $env:CI='1'; pnpm exec playwright test --project=chromium --retries=0` | PASS; 41 tests | Full Chromium acceptance project completed in 2.5 minutes, including the five previously failing shell tests. |

`PASS` in the matrix means the cited deterministic or browser assertion passed in the command identified above. The full release profile completed; no prior task status was used as proof.

## FRS §26 traceability matrix

### 26.1 Organization

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| ORG-01 | Create one GROUP root | `P1Org002OrganizationServiceTest::testValidHierarchyAncestorChainAndCompanyResolution` | B1 | PASS; service hierarchy assertion | PASS |
| ORG-02 | Reject second GROUP root in scope | `P1Org002OrganizationServiceTest::testSecondRootAndInvalidParentMatrixAreRejected` | B1 | PASS; stable rejection assertion | PASS |
| ORG-03 | Create COMPANY under GROUP | `P1Org002OrganizationServiceTest::testValidHierarchyAncestorChainAndCompanyResolution` | B1 | PASS; parent/ancestor assertion | PASS |
| ORG-04 | Create WAREHOUSE directly under COMPANY | `P1Org002OrganizationServiceTest::testValidHierarchyAncestorChainAndCompanyResolution` | B1 | PASS; hierarchy chain assertion | PASS |
| ORG-05 | Reject same-type nesting | `P1Org002OrganizationServiceTest::testSecondRootAndInvalidParentMatrixAreRejected` | B1 | PASS; parent matrix assertion | PASS |
| ORG-06 | Reject hierarchy cycle | `P1Org002OrganizationServiceTest::testReparentCycleAndInactivationPreserveStableErrors` | B1 | PASS; cycle rejection assertion | PASS |
| ORG-07 | Resolve Company from Warehouse | `P1Org002OrganizationServiceTest::testValidHierarchyAncestorChainAndCompanyResolution` | B1 | PASS; company ancestor assertion | PASS |
| ORG-08 | Company A+B scope filters and excludes C | `P1Api010FoundationOrganizationContactApiTest`, `P1Api003ListQueryTest` | B1 | PASS; authorized collection scope/count assertions | PASS |

### 26.2 Contact and Employee

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| CON-01 | Contact search never returns an out-of-scope owner | `P1Con001ContactServiceTest::testContactCrudSupportsBothOwnerTypesAndHistory`, `P1Api010FoundationOrganizationContactApiTest` | B1 | PASS; owner-type and API scope assertions | PASS |
| CON-02 | User maps to at most one Employee | `ShieldSecurityUserRepositoryTest`, `P1Emp003EmployeeAssignmentServiceTest` | B1 | PASS; identity/assignment invariant assertions | PASS |
| CON-03 | Employee without User is creatable | `P1Emp003EmployeeAssignmentServiceTest::testCreateValidatesDateReferencesAndCallerAuditFields` | B1 | PASS; optional identity path assertion | PASS |
| CON-04 | Reject self-manager | `P1Emp003EmployeeAssignmentServiceTest` | B1 | PASS; manager reference validation | PASS |
| CON-05 | Reject manager cycle | `P1Emp003EmployeeAssignmentServiceTest` | B1 | PASS; manager-cycle validation | PASS |
| CON-06 | Reject inactive new manager | `P1Emp003EmployeeAssignmentServiceTest::testInactiveReferencesAndEvaluationOrganizationAreStableErrors` | B1 | PASS; inactive reference rejection | PASS |
| CON-07 | Reject overlapping Primary Assignment | `P1Emp003EmployeeAssignmentServiceTest::testPrimaryOverlapIsRejectedButSecondaryAssignmentsRemainEffective` | B1 | PASS; overlap/concurrency assertions | PASS |
| CON-08 | Resolve Primary Assignment at Evaluation Time | `P1Emp003EmployeeAssignmentServiceTest::testPrimaryDefaultContextAndExactAncestorPathEligibility` | B1 | PASS; time/context assertion | PASS |

### 26.3 Authorization

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| AUTH-01 | Base Permission from Group | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection` | B1 | PASS; base-permission lane assertion | PASS |
| AUTH-02 | Base Permission from direct identity permission | `P1Auth005SecurityAdministrationTest::testEffectiveProfileTracksGroupDirectAndInactiveSources` | B1 | PASS; direct source assertion | PASS |
| AUTH-03 | No Base => DENY before PBAC | `P1Auth002AuthorizationServiceTest::testMissingBasePermissionDeniesAndDoesNotUsePolicyAsPermission` | B1 | PASS; fail-closed assertion | PASS |
| AUTH-04 | Base + NO_MATCH => ALLOW | `P1Auth002AuthorizationServiceTest::testBasePermissionAndNoMatchAllow` | B1 | PASS | PASS |
| AUTH-05 | Base + DENY => denied | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection` | B1 | PASS | PASS |
| AUTH-06 | Base + ALLOW + DENY => ALLOW | `P1Auth002AuthorizationServiceTest::testAllowWinsOverDenyInOneLane` | B1 | PASS | PASS |
| AUTH-07 | Base + ABSOLUTE_DENY => denied | `P1Auth002AuthorizationServiceTest::testAbsoluteDenyInheritedFromGroupOrganizationSurvivesAllLanes` | B1 | PASS | PASS |
| AUTH-08 | NULL policy state matches applicable states | `P1AuthorizationMandatoryRegressionTest::testMandatoryPolicyStateMatchingAndAuthoritativeContext` | B1 | PASS | PASS |
| AUTH-09 | Resolve GROUP/Company/ancestor inheritance before intersection | `P1AuthorizationMandatoryRegressionTest::testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility` | B1 | PASS | PASS |
| AUTH-10 | GROUP policy inherits to descendant scope | `P1AuthorizationMandatoryRegressionTest::testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility` | B1 | PASS | PASS |
| AUTH-11 | Branch policy excludes sibling | `P1Auth002AuthorizationServiceTest::testSiblingPolicyDoesNotBleedIntoEvaluationOrganization` | B1 | PASS | PASS |
| AUTH-12 | GROUP assignment supplies descendant Department/Position lane | `P1AuthorizationMandatoryRegressionTest::testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility` | B1 | PASS | PASS |
| AUTH-13 | Company A/B assignments are partitioned | `P1AuthorizationMandatoryRegressionTest::testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility` | B1 | PASS | PASS |
| AUTH-14 | Secondary same-scope assignment participates | `P1AuthorizationMandatoryRegressionTest::testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility` | B1 | PASS | PASS |
| AUTH-15 | ALLOW + DENY roles => Organization ALLOW | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection` | B1 | PASS | PASS |
| AUTH-16 | DENY + DENY roles => Organization DENY | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection` | B1 | PASS | PASS |
| AUTH-17 | Inherited ABSOLUTE_DENY survives child-lane intersection | `P1Auth002AuthorizationServiceTest::testAbsoluteDenyInheritedFromGroupOrganizationSurvivesAllLanes` | B1 | PASS | PASS |
| AUTH-18 | Organization result intersects user and assignments | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection` | B1 | PASS | PASS |
| AUTH-19 | Company summary intersects Organization results | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection` | B1 | PASS | PASS |
| AUTH-20 | Denied Organization is excluded from Company summary | `P1AuthorizationMandatoryRegressionTest::testMandatoryCoreMatrixCoversBasePermissionLanesAndIntersection` | B1 | PASS | PASS |
| AUTH-21 | GROUP participant mapping | `P1AuthorizationMandatoryRegressionTest::testMandatoryInheritancePartitionSiblingAndSecondaryAssignmentEligibility` | B1 | PASS | PASS |
| AUTH-22 | System Admin has no bypass | `P1Auth005SecurityAdministrationTest::testSystemAdminUsesNormalAuthorizationPathWithoutBypass` | B1 | PASS | PASS |
| AUTH-23 | SYSTEM/MASTER authorize at GROUP | `P1AuthorizationMandatoryRegressionTest::testMandatoryCreateAndGroupResourceContextsAreAuthoritativeAndFailClosed` | B1 | PASS | PASS |
| AUTH-24 | Employee MASTER authorizes at GROUP | `P1AuthorizationMandatoryRegressionTest::testMandatoryCreateAndGroupResourceContextsAreAuthoritativeAndFailClosed` | B1 | PASS | PASS |
| AUTH-25 | Existing/create Organization and Assignment context is authoritative | `P1AuthorizationMandatoryRegressionTest::testMandatoryCreateAndGroupResourceContextsAreAuthoritativeAndFailClosed` | B1 | PASS | PASS |
| AUTH-26 | Contact owner context resolves correctly | `P1AuthorizationMandatoryRegressionTest::testMandatoryCreateAndGroupResourceContextsAreAuthoritativeAndFailClosed` | B1 | PASS | PASS |
| AUTH-27 | Policy and Price List owner context resolves correctly | `P1AuthorizationMandatoryRegressionTest::testMandatoryPolicyStateMatchingAndAuthoritativeContext`, `P1Prc001PriceListServiceTest` | B1 | PASS | PASS |
| AUTH-28 | Authorization Log persisted context resolves correctly | `P1Auth004AuthorizationAuditTest`, `P1AuthorizationMandatoryRegressionTest::testMandatoryAuditIsInsertOnlyAndTrusted` | B1 | PASS | PASS |
| AUTH-29 | Import Job inherits PRODUCT MASTER/GROUP scope | `P1Imp007ProductImportAuthorizationServiceTest`, `P1AuthorizationMandatoryRegressionTest` | B1 | PASS | PASS |
| AUTH-30 | Unknown domain/unresolved Organization fails closed | `P1AuthorizationMandatoryRegressionTest::testMandatoryDecisionEvidenceAndInfrastructureFailuresRemainExplicit` | B1 | PASS | PASS |
| AUTH-31 | Primary Assignment cannot override domain scope | `P1AuthorizationMandatoryRegressionTest::testMandatoryCreateAndGroupResourceContextsAreAuthoritativeAndFailClosed` | B1 | PASS | PASS |
| AUTH-32 | Security/configuration has no implicit allow | `P1Auth005SecurityAdministrationTest::testAuthorizationDenialMeansZeroMutationForEverySecurityCapability` | B1 | PASS | PASS |

### 26.4 Workflow

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| WF-01 | Lifecycle has one initial state | `P1Wf002LifecycleServiceTest::testActiveLifecycleRequiresExactlyOneInitialStateAndAllowsMultipleFinalStates` | B1 | PASS | PASS |
| WF-02 | Reject transition state outside lifecycle | `P1Wf002LifecycleServiceTest::testInactiveStateAndOutOfMembershipTransitionAreRejected` | B1 | PASS | PASS |
| WF-03 | Reject duplicate lifecycle/from/action/destination | `P1Wf002LifecycleServiceTest` | B1 | PASS; duplicate configuration rejection | PASS |
| WF-04 | ALLOW plus invalid transition rejects | `P1Wf002LifecycleServiceTest::testUnknownAdapterFailsClosedAndInvalidTransitionPrecedesAuthorization` | B1 | PASS | PASS |
| WF-05 | Valid transition plus DENY rejects | `P1AuthorizationMandatoryRegressionTest::testMandatoryWorkflowRejectsInvalidTransitionAndDeniedMutation` | B1 | PASS | PASS |

### 26.5 Product Role

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| PRD-01 | SIMPLE derivation | `P1Prd004ProductRoleServiceTest::testCanonicalRolesPredicatesCapabilitiesAndDynamicParentTransition` | B1 | PASS | PASS |
| PRD-02 | PARENT derivation with child | `P1Prd004ProductRoleServiceTest` | B1 | PASS | PASS |
| PRD-03 | CHILD derivation | `P1Prd004ProductRoleServiceTest` | B1 | PASS | PASS |
| PRD-04 | COMBO derivation | `P1Prd004ProductRoleServiceTest` | B1 | PASS | PASS |
| PRD-05 | Reject child-of-child | `P1Prd003ProductMasterServiceTest::testValidationAndTypedUniqueKeys` | B1 | PASS | PASS |
| PRD-06 | Reject COMBO parent/child | `P1Cmb001ComboCompositionServiceTest::testValidationRejectsInvalidRolesReferencesSelfDuplicatesAndQuantities` | B1 | PASS | PASS |
| PRD-07 | Reject transaction/select PARENT | `P1Prd004ProductRoleServiceTest::testInactiveProductsRetainRoleButAreNotSelectable` | B1 | PASS | PASS |
| PRD-08 | SIMPLE to PARENT dependency guard | `P1Prd003ProductMasterServiceTest::testHistoryBlocksConversionAndSemanticEdit` | B1 | PASS | PASS |

### 26.6 UOM

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| UOM-01 | Base UOM required for SIMPLE/CHILD/COMBO | `P1Uom001ProductUomServiceTest::testRoleEligibilityBaseRulesAndProductMasterDependencyBoundary` | B1 | PASS | PASS |
| UOM-02 | Alternate UOM converts directly to base | `P1Uom001ProductUomServiceTest::testBaseAndAlternateUomPersistConvertAndDerivePackagingNetWeight` | B1 | PASS | PASS |
| UOM-03 | Reject factor <= 0 | `P1Uom001ProductUomServiceTest::testCreateValidationUsagePriceFactorReferenceAndDecimalBoundaries` | B1 | PASS | PASS |
| UOM-04 | SALES/BOTH price required | `P1Uom001ProductUomServiceTest::testCreateValidationUsagePriceFactorReferenceAndDecimalBoundaries` | B1 | PASS | PASS |
| UOM-05 | PURCHASE price nullable | `P1Uom001ProductUomServiceTest::testCreateValidationUsagePriceFactorReferenceAndDecimalBoundaries` | B1 | PASS | PASS |
| UOM-06 | Reject UOM conversion for COMBO | `P1Uom001ProductUomServiceTest::testRoleEligibilityBaseRulesAndProductMasterDependencyBoundary` | B1 | PASS | PASS |

### 26.7 COMBO

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| CMB-01 | SIMPLE component valid | `P1Cmb001ComboCompositionServiceTest::testReplacementSupportsLeafChildNestedComboDiffsAndTrustedAudit` | B1 | PASS | PASS |
| CMB-02 | CHILD component valid | `P1Cmb001ComboCompositionServiceTest::testReplacementSupportsLeafChildNestedComboDiffsAndTrustedAudit` | B1 | PASS | PASS |
| CMB-03 | Nested COMBO valid | `P1Cmb001ComboCompositionServiceTest::testReplacementSupportsLeafChildNestedComboDiffsAndTrustedAudit` | B1 | PASS | PASS |
| CMB-04 | Reject PARENT component | `P1Cmb001ComboCompositionServiceTest::testValidationRejectsInvalidRolesReferencesSelfDuplicatesAndQuantities` | B1 | PASS | PASS |
| CMB-05 | Reject self component | `P1Cmb001ComboCompositionServiceTest::testValidationRejectsInvalidRolesReferencesSelfDuplicatesAndQuantities` | B1 | PASS | PASS |
| CMB-06 | Reject duplicate component row | `P1Cmb001ComboCompositionServiceTest::testValidationRejectsInvalidRolesReferencesSelfDuplicatesAndQuantities` | B1 | PASS | PASS |
| CMB-07 | Reject A -> B -> A cycle | `P1Cmb001ComboCompositionServiceTest::testPersistedAndDeepCyclesAndCorruptParentEdgesFailClosed` | B1 | PASS | PASS |
| CMB-08 | Reject A -> B -> C -> A cycle | `P1Cmb001ComboCompositionServiceTest::testPersistedAndDeepCyclesAndCorruptParentEdgesFailClosed` | B1 | PASS | PASS |
| CMB-09 | Flatten nested quantity exactly | `P1Cmb002ComboExpansionServiceTest::testCanonicalNestedExampleAggregatesOnlySimpleAndChildLeaves` | B1 | PASS | PASS |

### 26.8 Pricing

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| PRC-01 | VND amount; no FX selection | `P1Prc002PricingServiceTest::testProductRoleStatusAndUomBoundariesAreEnforced` | B1 | PASS | PASS |
| PRC-02 | Company list wins over Group | `P1Prc002PricingServiceTest::testCompanyPrecedesGroupAndMissingCompanyItemFallsBack` | B1 | PASS | PASS |
| PRC-03 | Missing Company item falls back to Group | `P1Prc002PricingServiceTest::testCompanyPrecedesGroupAndMissingCompanyItemFallsBack` | B1 | PASS | PASS |
| PRC-04 | Group missing item falls back to default | `P1Prc002PricingServiceTest::testDefaultPricesZeroAndMissingPricesFailClosed` | B1 | PASS | PASS |
| PRC-05 | Overlap ordering deterministic | `P1Prc002PricingServiceTest::testIntervalBoundariesInactiveListsAndDeterministicOverlap` | B1 | PASS | PASS |
| PRC-06 | Effective interval is `[from,to)` | `P1Prc002PricingServiceTest::testIntervalBoundariesInactiveListsAndDeterministicOverlap` | B1 | PASS | PASS |
| PRC-07 | COMBO Price List only Base UOM | `P1Prc001PriceListServiceTest`, `P1Prc002PricingServiceTest::testProductRoleStatusAndUomBoundariesAreEnforced` | B1 | PASS | PASS |

### 26.9 COMBO Allocation

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| ALLOC-01 | Resolve root price once | `P1Cmb003ComboAllocationServiceTest::testRootCompanyGroupAndDefaultPricingAndLeafPriceListsAreScopedCorrectly` | B1 | PASS | PASS |
| ALLOC-02 | Ignore nested COMBO price | `P1Cmb003ComboAllocationServiceTest::testNestedComboPriceIsIgnoredAndOnlyLeafDefaultBasePriceSuppliesWeight` | B1 | PASS | PASS |
| ALLOC-03 | Weight = leaf quantity x default price | `P1Cmb003ComboAllocationServiceTest::testNestedComboPriceIsIgnoredAndOnlyLeafDefaultBasePriceSuppliesWeight` | B1 | PASS | PASS |
| ALLOC-04 | Leaf Price List does not affect weight | `P1Cmb003ComboAllocationServiceTest::testNestedComboPriceIsIgnoredAndOnlyLeafDefaultBasePriceSuppliesWeight` | B1 | PASS | PASS |
| ALLOC-05 | Reject total weight zero | `P1Cmb003ComboAllocationServiceTest::testZeroWeightLeafIsAllowedButAllZeroWeightsReject` | B1 | PASS | PASS |
| ALLOC-06 | Allocated sum equals root total | `P1Cmb003ComboAllocationServiceTest::testThreeWayResidualIsDeterministicAndConservedAtTwoDecimals` | B1 | PASS | PASS |
| ALLOC-07 | Residual follows product_id order | `P1Cmb003ComboAllocationServiceTest::testThreeWayResidualIsDeterministicAndConservedAtTwoDecimals` | B1 | PASS | PASS |
| ALLOC-08 | Shared leaf paths aggregate quantity | `P1Cmb003ComboAllocationServiceTest::testShuffledRootsProduceSameSortedResultAndSameLeafAggregatesByAmount` | B1 | PASS | PASS |
| ALLOC-09 | Multi-root leaf amount/quantity weighted average | `P1Cmb003ComboAllocationServiceTest::testShuffledRootsProduceSameSortedResultAndSameLeafAggregatesByAmount` | B1 | PASS | PASS |

### 26.10 Auto Numbering

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| NUM-01 | Unconfigured Resource is not forced to Auto Number | `P1AutoNumberingMandatoryRegressionTest::testConfigurationDefaultsIssuedIdentityAndNoAutoFallback` | B1 | PASS | PASS |
| NUM-02 | Reject two active defaults | `P1AutoNumberingMandatoryRegressionTest::testConfigurationDefaultsIssuedIdentityAndNoAutoFallback` | B1 | PASS | PASS |
| NUM-03 | AUTO_REQUIRED rejects manual identifier | `P1AutoNumberingMandatoryRegressionTest::testProductContractGlobalNamespaceGenerationAndAtomicRollback` | B1 | PASS | PASS |
| NUM-04 | AUTO_IF_EMPTY manual/blank semantics | `P1AutoNumberingMandatoryRegressionTest::testProductContractGlobalNamespaceGenerationAndAtomicRollback` | B1 | PASS | PASS |
| NUM-05 | Occupied candidate advances under lock | `P1AutoNumberingMandatoryRegressionTest::testProductContractGlobalNamespaceGenerationAndAtomicRollback` | B1 | PASS | PASS |
| NUM-06 | GROUP/COMPANY/ORGANIZATION scope isolation | `P1Num003ScopePeriodResolverTest`, `P1Num004CounterAllocatorTest` | B1 | PASS | PASS |
| NUM-07 | Namespace uniqueness rejects ambiguous series | `P1Num002NumberingPatternValidatorTest`, `P1AutoNumberingMandatoryRegressionTest::testFailClosedUnknownAdapterInactiveSeriesAndNamespaceCompatibility` | B1 | PASS | PASS |
| NUM-08 | NEVER/YEARLY/MONTHLY/DAILY period keys | `P1AutoNumberingMandatoryRegressionTest::testCanonicalPatternResetAndRenderingMatrix` | B1 | PASS | PASS |
| NUM-09 | GROUP reset requires timezone | `P1Num003ScopePeriodResolverTest` | B1 | PASS | PASS |
| NUM-10 | Concurrent generation has one counter/unique number | `P1Num004CounterAllocatorTest`, `P1AutoNumberingMandatoryRegressionTest` | B1 | PASS | PASS |
| NUM-11 | Rollback allows number reuse | `P1AutoNumberingMandatoryRegressionTest::testProductContractGlobalNamespaceGenerationAndAtomicRollback` | B1 | PASS | PASS |
| NUM-12 | Committed number never reused | `P1AutoNumberingMandatoryRegressionTest::testConfigurationDefaultsIssuedIdentityAndNoAutoFallback` | B1 | PASS | PASS |
| NUM-13 | Configuration change does not renumber history | `P1AutoNumberingMandatoryRegressionTest::testConfigurationDefaultsIssuedIdentityAndNoAutoFallback` | B1 | PASS | PASS |
| NUM-14 | Issued identity is immutable/no-hard-delete | `P1AutoNumberingMandatoryRegressionTest::testConfigurationDefaultsIssuedIdentityAndNoAutoFallback` | B1 | PASS | PASS |
| NUM-15 | `resource.configure_numbering` is independent | `P1AutoNumberingMandatoryRegressionTest::testFailClosedUnknownAdapterInactiveSeriesAndNamespaceCompatibility` | B1 | PASS | PASS |
| NUM-16 | Import source-key resolves before generated code exists | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |

### 26.11 Import

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| IMP-01 | Success/error counts reconcile | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |
| IMP-02 | Failed row rolls back business data and number | `P1Imp090ProductImportRegressionTest::testFormulaFailureIsFatalAndDoesNotPersistRows` | B1 | PASS | PASS |
| IMP-03 | Child before Parent/source-key dependency | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |
| IMP-04 | COMBO before component/source-key dependency | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |
| IMP-05 | Nested COMBO dependency | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |
| IMP-06 | Combo cycle rejected | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |
| IMP-07 | Generated `product_code` returned/persisted | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |
| IMP-08 | Original row number preserved | `P1Imp090ProductImportRegressionTest::testDependencyReorderingKeepsSourceRowsAndPersistsGeneratedCodes` | B1 | PASS | PASS |

### 26.12 Display Settings

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| DSP-01 | Registry rejects unknown key | `P1Dsp002DisplaySettingsResolverTest::testTargetMustExistAndAnUnknownKeyCannotBecomeRuntimeSetting` | B1 | PASS | PASS |
| DSP-02 | Server contexts only system/organization | `P1Dsp004DisplaySettingsApiTest::testInvalidContextsUnknownKeysAndDuplicateKeysAreStable422DomainErrors` | B1 | PASS | PASS |
| DSP-03 | Exact ancestor fallback and sibling isolation | `P1Dsp002DisplaySettingsResolverTest::testNearestOrganizationPathOverrideWinsThenFallsBackThroughGroupSystemAndDefault`, `::testSiblingOverrideNeverParticipates` | B1 | PASS | PASS |
| DSP-04 | `display_setting.view/edit` uses canonical PBAC | `P1Dsp003DisplaySettingsMutationServiceTest::testDenyAndAbsoluteDenyAreEnforcedByCanonicalPbacBeforeMutation` | B1 | PASS | PASS |
| DSP-05 | Batch set/reset atomic; reset inherits | `P1Dsp003DisplaySettingsMutationServiceTest::testAuditFailureRollsBackEverySettingsOverrideInTheBatch`, `P1Dsp004DisplaySettingsApiTest::testSetAndResetReturnCanonicalValuesAndResetIsIdempotent` | B1 | PASS | PASS |
| DSP-06 | Runtime snapshot is presentation-only | `P1Dsp004DisplaySettingsApiTest::testRuntimeIsAuthenticatedPresentationOnlyAndOrganizationVisibilityIsEnforced` | B1 | PASS | PASS |
| DSP-07 | Display timezone does not change business timezone | `P1Dsp002DisplaySettingsResolverTest::testDisplayTimezoneDoesNotReplaceBusinessTimezone` | B1 | PASS | PASS |
| DSP-08 | Display number/money settings do not change canonical API/calculation | `P1Dsp004DisplaySettingsApiTest`, `frontend/src/presentation/__tests__/displaySettings.spec.ts` | B1/F4 | PASS; canonical formatter assertions | PASS |
| DSP-09 | Frontend formatter preserves canonical form model | `frontend/src/presentation/__tests__/displaySettings.spec.ts` | F4 | PASS; formatter non-mutation assertion | PASS |
| DSP-10 | Locale remains local preference, not server Display Setting | `frontend/src/presentation/__tests__/displaySettings.spec.ts`, `frontend/src/i18n/__tests__/locale.spec.ts` | F4 | PASS; local overlay/locale parity assertions | PASS |

### 26.13 Localization / i18n

| Case | Requirement | Test reference | Command | Actual result / evidence | Status |
|---|---|---|---|---|---|
| LOC-01 | Default locale is `vi` | `P1Api001TransportTest::testLanguageNegotiationMessagesUseViFallbackAndEnglish`, `frontend/src/i18n/__tests__/locale.spec.ts` | B1/F4 | PASS | PASS |
| LOC-02 | UI switches vi/en at runtime | `frontend/e2e/fe001-shell.spec.ts::authenticated shell exposes five modes, deduplicated tabs, locale, and 404 boundary` | E1/E2 | PASS; authenticated shell switches vi -> en -> vi and preserves route/session | PASS |
| LOC-03 | UI labels are sourced from translation resources | `frontend/src/i18n/__tests__/locale.spec.ts::keeps buttons, field labels, and validation messages translated in vi and en` | F4/F2 | PASS; every vi/en translation leaf is non-empty and resolves to a localized resource value, including common actions, field labels, and validation messages | PASS |
| LOC-04 | API client sends current `Accept-Language` | `P1Api001TransportTest`, `frontend/src/api/__tests__/client.spec.ts` | B1/F4 | PASS | PASS |
| LOC-05 | Backend negotiates vi/en; unsupported falls back vi | `P1Api001TransportTest::testLanguageNegotiationMessagesUseViFallbackAndEnglish` | B1 | PASS | PASS |
| LOC-06 | Error code stable while message localizes | `P1Api001TransportTest::testMapperKeepsStableCodesAndCanonicalStatuses`, `::testLanguageNegotiationMessagesUseViFallbackAndEnglish` | B1 | PASS | PASS |
| LOC-07 | `Content-Language` reflects negotiated locale | `P1Api001TransportTest::testNoContentStillEchoesRequestIdAndLanguage` | B1 | PASS | PASS |
| LOC-08 | Business data/code/import headers are not translated | `P1Imp090ProductImportRegressionTest`, `frontend/src/i18n/__tests__/locale.spec.ts` | B1/F4 | PASS | PASS |
| LOC-09 | Locale switch does not alter timezone/auth/persistence | `frontend/e2e/fe001-shell.spec.ts`, `frontend/src/presentation/__tests__/displaySettings.spec.ts` | E1/F4 | PASS; route remains `/organizations`, authenticated `quyetdv` remains visible, and `ui.locale` persists vi/en without changing display-runtime assertions | PASS |

## Coverage and handoff

- All 135 catalog cases are PASS with executable backend, frontend, and browser evidence.
- This run did not duplicate the dedicated P1-SYS-002 through P1-SYS-007 tasks. Fresh/forward migration rehearsal, security-only regression, concurrency-only regression, OpenAPI drift as a standalone task, and production configuration review remain their owners' scope.
- Approved change controls 08/09/10/11 were checked independently through the existing backend/API/UI tests; no change-control semantics were modified.
- Corrective changes are limited to the Vite Naive UI chunk boundary, authenticated shell acceptance evidence, and deterministic vi/en resource coverage; no business semantics, canonical data, or PBAC behavior changed.

Next dependency: **P1-SYS-002** (authorization/security regression run).
