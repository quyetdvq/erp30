<?php

declare(strict_types=1);

/**
 * Exact UNIT-STANDARD suite. This is not an NO-DROP allowlist.
 * DatabaseTestTrait retains CI4 migration/refresh behavior on the configured erp_test connection.
 * Values are expected public test-method counts (123 total).
 * No test here may be selected by the 91-class / 506-case NO-DROP lane.
 *
 * @return array<string,int>
 */
return [
    'P1Api050ProductSqlPaginationTest' => 15,
    'P1Auth005SecurityAdministrationTest' => 11,
    'P1AutoNumberingMandatoryRegressionTest' => 5,
    'P1Dsp003DisplaySettingsMutationServiceTest' => 5,
    'P1Emp001EmployeeRepositoryTest' => 5,
    'P1Emp003EmployeeAssignmentServiceTest' => 8,
    'P1Num001AutoNumberingTest' => 9,
    'P1Num004CounterAllocatorTest' => 10,
    'P1Num005ResourceIdentifierAdapterTest' => 6,
    'P1Num006ProductIdentifierAdapterTest' => 7,
    'P1Wf001LifecycleRepositoryTest' => 6,
    'P1Wf006StateAwarePbacAndLocationTransitionsTest' => 21,
    'P1Wf007NativeStateContractTest' => 15,
];
