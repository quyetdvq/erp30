<?php

declare(strict_types=1);

/**
 * Pinned UNIT NO-DROP discovery from ERP v1.2.0.
 *
 * Only approved rollback classes from no-drop-approved.php may run in
 * --mode=unit-safe. Candidate conversions require opt-in and runtime verification.
 * This manifest does NOT confer NO-DROP approval.
 *
 * LF-normalized SHA-256 prevents silent auto-promotion after source edits.
 */
return [
    'non_database_trait' => [
        'AppShieldUserModelTest' => '9c34764ea0af0d8c959ffb8f0cf40736beaf98b17e88d38a39e24e49282e828e',
        'BeReorg001FoundationBoundaryTest' => 'e1264f851a92ce0012a7494716f781d58a6d273d46470d7f934444b10ec10e29',
        'BeReorg001FoundationCompatibilityTest' => 'e9cc8ce003884e8c7aa82d7d3de4be79f842fc60645eec0dd226c1131b54a069',
        'BeReorg001FoundationPersistenceTypesTest' => '1ce4c39d9d7b3ceca9929524bd985d0b7901b42807fcbc60a5a01f00ccb48dd7',
        'BeReorg002OrganizationBoundaryTest' => '4a54a820defaa42cdce619d5fb9ea25262cadb5ac4437cab81a6ae92532c3c09',
        'BeReorg002OrganizationCompatibilityTest' => 'af8b222371b95e850e2579647d38468633e58f8bfb2fb4b317a79c5e7bbb0244',
        'BeReorg003ContactBoundaryTest' => 'e4576adce400b0e26ab259846335c9ea1272997d2ff84ddfcaf56e081e03d52b',
        'BeReorg003ContactCompatibilityTest' => '7e21976eb56761604dd62b356070e4164c95bed4176a9689a3fa13da416d1ed6',
        'BeReorg004EmployeeStructureBoundaryTest' => '2a110a106b0c6a219e05ce590126d0773b2c6e0288c976943e67adbacd9499f1',
        'BeReorg004EmployeeStructureCompatibilityTest' => '82048da94757d6188f2220d2837cd57d86579fff9d4dec66ac635a369d53ba1d',
        'BeReorg005SettingsBoundaryTest' => '608312d30c8d88c732d997b1cca406b2a96bd9a17d4d78d94d94aad17d2f8467',
        'BeReorg005SettingsCompatibilityTest' => 'ce987832005df088239fc2ca884461eb5c3504f3fe073718889ac75632e9d8b7',
        'BeReorg006NumberingBoundaryTest' => '70c960383fe204f5adb9d874c7226b1f47e0851e2e7220a26a4ccc860550f7ff',
        'BeReorg006NumberingCompatibilityTest' => '9153ce5ce4420232d48621b7aeb9183cacff81e85466d9a48465556adca4133e',
        'BeReorg006NumberingFactoryTest' => '4b1b20e93855880b2d22b480fc43c18c34aa4fd0f9116d2f362f930fcee61f0e',
        'BeReorg007AuthorizationBoundaryTest' => '21dc5c30a5452eb8e54e2ba7805c64ec3cd7ba409857f921672d8276514c16a3',
        'BeReorg007AuthorizationCompatibilityTest' => '3b513ca43d2f8e98a5ed3f5f247a0979508264bcaadc974bde3dc93e8d052af4',
        'BeReorg008DecisionBoundaryTest' => '25f881f6fdf4f0ff20b4f56fcf83790afc49cf9c612307f87fbf592908f6bd96',
        'BeReorg009LifecycleBoundaryTest' => '0ec0881c2fc4e92674ba9b982c16f11b2f8dc81b726cee3ab527fe5cd9dd8b12',
        'BeReorg010StateResolutionBoundaryTest' => '0f88d1d8ece53741a999c402ba2eccc4f27756142049d4bdbadf3943f14d0056',
        'BeReorg010StateResolutionCacheTest' => '2ba858940cd60eacd73db24f8044473666e240c407ee930d33e00e01457e3b81',
        'BeReorg010StateResolutionCompatibilityTest' => 'ece85384ca36df7dba342d98351ee760744993f87c73c97f8c8479e4c37e7ed0',
        'BeReorg010StateResolutionDomainTest' => '93180d6dc9386d521c592d1f1d088abfee3f8fed4998083e87f99941d3db277d',
        'BeReorg011BusinessActionsBoundaryTest' => '411f1cf04c4c7d870e4f435e9b012af32bf0a13301fb42fec68269ecea070b31',
        'BeReorg011BusinessActionsCompatibilityTest' => '06aae04ed454f2e3f364481284ff6a78fc7fc0fae865a1b8d01c85ba12c5b91b',
        'BeReorg012InitialStateRulesTest' => '6addb609fdb58a0c7961d605c1cb366650e53039d0f83fae883b4f7399e47c1e',
        'BeReorg012WriteBoundaryBoundaryTest' => '19fe8ee18d87fbf92fcef65ba6153b30cdb6e6ff33e02aeab9d97f50a5209b9e',
        'BeReorg012WriteBoundaryDomainTest' => '15aea83181d200020b88bc0ee2194c13cb352e6cf11bf584e1b665acdc8043ec',
        'BusinessWorkflowTenNativeBoundaryTest' => '160859501f76a145240274d5a4af9509ac1851c7016dffb511a5bcb87f95e39b',
        'CanonicalRepositoryHelperTest' => 'ae72873341750e2f023db127e1d9f6a896f34eed5ded4905c794f9df20282660',
        'CorsPreflightRouteTest' => 'dfc8266bc7509fc246196b359e5767d5283d289ee6981f679ef5614a9dd87136',
        'Gate0InfrastructureTest' => '5bdaed4ac1787663c2e210b096b5b6f2ed75d6ae8f942cfed28fbb87d6c2d28d',
        'HealthTest' => '84736aab7348d639aa8ba0b6573471fc409787c3a3834eabfe476647f1751d9c',
        'P1Api001TransportTest' => 'a1a3b6ebde10465a0de6af72fb2985f0c9fc89157ccdcf87427844b56348d21b',
        'P1Api003ListQueryTest' => '3b3f932a918699bda2bb5d3ab548d02c1f704eafac1edce896916016c31ec7cc',
        'P1Api010FoundationOrganizationContactApiTest' => '6455a92698732ebfe5c81cde9ac4e7549b14d24eeda235a2bbc6c2a2c51ea581',
        'P1Api050ProductCatalogTest' => 'c99642e984d57b47cb034547b75b57b041b9906032341c5d3014cbddb6336607',
        'P1Api060ProductImportTest' => 'e268a8e7bf32d568bb8eac9b1c2dbf89f20fbf9df23fea9cb9f76d5377e87453',
        'P1Api090ContractRegressionTest' => 'c6029bbb2f6b45da98f95b2f7036bea187f3f86b1339e86e044f210f56bd26a0',
        'P1Auth003AuthorizationDecisionTest' => '7b25009db2cb4bddc945c032770279909673c7c41a893f39fde2d5241e4611f2',
        'P1Dsp001DisplaySettingsTest' => '784fd2cd9745392d79f4856f7aa548bb74d9d949951bcde2da3d4d118f0caaa3',
        'P1Perf003ImportRowSqlGuardTest' => 'adfad8cff661c84a52f0d112bbf3b25b37fd4b1f29b6019d7c99a3964aa85ffc',
        'P1Perf004ReadOnlyAuthorizationContextTest' => '9b8dd141f2a6606847300f66a80bf713740ad329aecac86341633b553fe84363',
        'P1Sys007ProductionDecisionTest' => 'bc9aefe837c5efe618a8a0ca83ef5387a769ccda4b2bf4a6da83e8dc8598daed',
        'P1WorkflowCreateInitialStateTest' => '696f48687b74b3aed0a519a648af297007c94520f89b8e0b65fcbab5a9c4cb05',
        'ProductImageStorageTest' => '53669ea8798b30ec473cf99ac9e1f3baff0e478403465cec8bda128b093dd285',
        'ResourceCatalogRepositoryBulkActionsTest' => '708da37707af588e54737dcf9ea57a46bfea79b36d7c7193dc731bf162c270d9',
        'ShieldSecurityUserRepositoryTest' => '1541a803cf61ff0f3e75a30a4ae4188d88339e33c6182646e426e9f19261cec3',
        'UserAvatarApiContractTest' => 'de19a7092f84bf8dad7ce841b7536381b6776d79034c6c325416e169b113bfb8',
        'UserAvatarStorageTest' => '1dd9e328b68e5115524d25f82a6877b2b5d890e4a506c2e67e2f365f917d6cdd',
        'UserDisplayFieldsStructureTest' => 'ec7578d9e93aa0da9549a70fd49d261d80c491cc88ad1740412c9f87a6a9df82',
        'UserDisplayMetadataResetPolicyTest' => 'c53fb8ef887c0c1f4e18066d0ddfb4f9fccbbf8c17479d5fc2f81f75cc07d0fb',
        'UserIdentityInputTest' => '3fbc0a25e829818559b01273393869320df0cd7bbdb4babd54e4df84d497061d',
    ],
    // 18 classes: XAMPP batch 2026-10-21, 120/120 tests and 787 assertions.
    // Reviewed for the unit-safe lane only; source hashes remain mandatory.
    // Does NOT add them to the separate module/cohort allowlist.
    'candidates_verified' => [
        'BeReorg007AuthorizationFactoryTest' => 'd903e8be95ecc2361512a2bf3938713e92bce0ee156131aea5d248ce2f2d999c',
        'BeReorg009LifecycleCompatibilityTest' => '10915b21dc4b8f50deadf2f7103ee35190bd0823562c18de276b93ce9b314e35',
        'HardenP1Test' => '4208e5fda91232d18d3e33862fabcaaf2391351176fc333a8506bad39cda1f05',
        'P1Api020EmployeeStructureTest' => '66bbf768da6cba119c2f81172cbbf9f11985373cc555c6b346b73651fe666061',
        'P1Api040AutoNumberingTest' => '363dedce2d635a8f81342f0dfee7e3b0fc92f177a729dd6d13d95a8c02fc78fa',
        'P1Auth001PolicyRepositoryTest' => '24433902ce3d60c37987abdebc036a799c3ef74f217d190e3b81a450ca76798c',
        'P1Auth002AuthorizationServiceTest' => 'b8d1385f31dbb6e4b22cd694790545ef948beffc33e92ebeb196a54dc5601efd',
        'P1Auth004AuthorizationAuditTest' => '102b5b6b164f8971088301ea786a447dc8ab97677b5d49edfc4d97e165919fe0',
        'P1AuthorizationMandatoryRegressionTest' => '5dae9ef3163891c325eacf1267238a93b850f27cacac4b0bf255803d30faf14c',
        'P1ContactAuth001OwnerAwareAuthorizationTest' => 'ae6208a85637d4ebe5e21e6bc95aff97a1fd637b894d0a5dba9c2702ce61c848',
        'P1Dsp004DisplaySettingsApiTest' => 'e0681441edd7da49d6d1e7100782443e8aa23c7cf1b802d18cfc8c2987b20522',
        'P1Iam001ShieldIdentityAdapterTest' => 'bd18004d1ca32bb383e8c46483e114b9538622ed06aa55da2274669f8e3d1cb0',
        'P1Iam002EffectiveProfileResolverTest' => '7c9eedee1298ea5a3f4358da029a833f4b7602cdcd70fa9c343f2dd661fec2a5',
        'P1Loc010OptimizationTest' => '11f8f2d314fbc8bc64488fd26cfb217d86328d3f77de465bc9f4f2b19ecf8ea0',
        'P1Perf006OwnerAwareSqlPaginationTest' => '930adc6831b85a612328a8df8ffc22c65cceb94566e7653e24a20227bbb2b944',
        'P1Perf008PriceListSqlPaginationTest' => '02fc97aaf2688270c525ec4f8281976fac1db1801510fff0d44c2dc06d6756c4',
        'P1Wf002LifecycleServiceTest' => '78a39c409fb191756fdc0f9a28e4255f97db33bc56d68595390a4a1f10e8b6b5',
        'P1WorkflowCreateLifecycleIntegrationTest' => 'd15bc429b29d7c6ce8c76e264673b202cb7100e17683ff2d13465f9578697c22',
    ],
    // UNIT-STANDARD: all remaining classes are deliberately excluded from NO-DROP.
    'candidates_unverified' => [],
    'blocked' => [
        'P1AutoNumberingMandatoryRegressionTest' => 'Standard CI4 migrations/transactions: allocator owns its commits.',
        'P1Num005ResourceIdentifierAdapterTest' => 'Standard CI4 tests: real counter transaction and generated identifiers.',
        'P1Num006ProductIdentifierAdapterTest' => 'Standard CI4 tests: product identifier issuance and rollback.',
        'P1Wf006StateAwarePbacAndLocationTransitionsTest' => 'Standard CI4 tests: independent product list snapshot.',
        'P1Wf007NativeStateContractTest' => 'Standard CI4 tests: independent snapshot and test-only status restoration.',
        'P1Api050ProductSqlPaginationTest' => 'Concurrent second-connection SQL snapshot and real commit/visibility invariants.',
        'P1Auth005SecurityAdministrationTest' => 'Cross-connection permission replacement and worker use committed state.',
        'P1Dsp003DisplaySettingsMutationServiceTest' => 'Already excluded from current PHPUnit config; audit rollback/commit semantics.',
        'P1Emp001EmployeeRepositoryTest' => 'CREATE TEMPORARY TABLE requires extra privileges; replace with seeded FK-safe fixture.',
        'P1Emp003EmployeeAssignmentServiceTest' => 'Cross-connection overlap/concurrency checks cannot be nested in test rollback.',
        'P1Num001AutoNumberingTest' => 'External worker/transaction commit semantics require separate isolation.',
        'P1Num004CounterAllocatorTest' => 'Spawned worker/concurrent committed allocator checks need dedicated cleanup.',
        'P1Wf001LifecycleRepositoryTest' => 'Explicit transaction commit/rollback assertions need dedicated isolation.',
    ],
];
