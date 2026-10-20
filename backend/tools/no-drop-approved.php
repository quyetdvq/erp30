<?php

declare(strict_types=1);

/**
 * Explicitly reviewed NO-DROP test-class allowlist.
 * NEVER add an unreviewed class. Changing this manifest is a code-reviewed action.
 * `tools/no-drop-audit.php` never modifies this file.
 *
 * @return array<string,list<string>>
 */
return [
        'numbering' => [
            'P1Num002NumberingPatternValidatorTest',
            'P1Num003ScopePeriodResolverTest',
        ],
        // Explicit seeder injection pins both authorization seeders to the
        // test's rollback-protected connection; class-level data attestation
        // must still pass before any further authorization classes are added.
        'authorization' => [
            'BeReorg008DecisionBatchTest',
            'BeReorg008DecisionParityTest',
            'BeReorg008DecisionCompatibilityTest',
            'BeReorg008DecisionFactoryTest',
            'ChangeAuthDomain001AuthorizationTest',
            'P1Auth004AssignmentEligibilityTest',
            // Context resolver fixture uses exactly the class-owned test connection.
            'P1Ctx001EvaluationOrganizationResolverTest',
            // Only the audited CompanyOwnerResolver test is permitted.
            'P1Ctx002CompanyOwnerResolverTest',
            // SQL authorized bucket-list parity: in-transaction fixtures only.
            'P1Perf005AuthorizedBucketListTest',
        ],
        // Display Settings resolver uses the one test DB connection for
        // OrganizationService and WorkflowLifecycleFixture::bind(); no SQL
        // commits, subprocesses, or migration/DDL in this class.
        'settings' => [
            'P1Db003ConfigurationAuditEventsTest',
            'P1Dsp002DisplaySettingsResolverTest',
        ],
        // Contact CRUD fixtures and service share one rollback-protected DB.
        'contact' => [
            'P1Con001ContactServiceTest',
            'BeReorg003ContactCharacterizationTest',
        ],
        // Reviewed factory/state-read classes only; lifecycle write/commit suites remain excluded.
        'workflow' => [
            'BeReorg009LifecycleFactoryTest',
            'BeReorg010StateResolutionFactoryTest',
            'BeReorg010StateResolutionDatabaseTest',
            'BeReorg011BusinessActionsFactoryTest',
            'BeReorg012WriteBoundaryFactoryTest',
            'BeReorg012WriteBoundaryCompatibilityTest',
            'BeReorg011BusinessActionsDatabaseTest',
            'BeReorg012WriteBoundaryDatabaseTest',
        ],
        // Read-only combo expansion; every product/UOM/group/edge fixture is
        // written through the rollback-protected test connection. This is
        // deliberately NOT an allowlist for UOM master mutations.
        'product' => [
            'P1Cmb002ComboExpansionServiceTest',
            // ProductRoleService only SELECTs; product rows are class-owned fixtures.
            'P1Prd004ProductRoleServiceTest',
            // SQL lookup/window pagination reads fixtures via the same test DB.
            'P1Perf007ProductLookupBatchTest',
        ],
        // Read-only price calculation and COMBO monetary allocation. The
        // test fixture writers and service dependencies share $this->db;
        // pricing mutations/committed snapshots remain disallowed.
        'pricing' => [
            'P1Prc002PricingServiceTest',
            'P1Cmb003ComboAllocationServiceTest',
        ],
        // Simple repository CRUD on a single rollback-protected connection.
        'organization' => [
            'P1Org001OrganizationRepositoryTest',
            'P1Org002OrganizationServiceTest',
            'BeReorg002OrganizationCharacterizationTest',
        ],
        'employee' => [
            'P1Emp002EmployeeAssignmentRepositoryTest',
        ],
        // Import-job envelope insert/read only; NOT import allocation/reconcile.
        'import' => [
            'P1Imp001ProductImportJobServiceTest',
            // Read-only import dependency planner and explicit test DB injection.
            'P1Imp005ProductImportModelBuilderTest',
            // Import-row paging with an in-process SQL query listener.
            'P1Perf002ImportRowPagingTest',
        ],
    ];
