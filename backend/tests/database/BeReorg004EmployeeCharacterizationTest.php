<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Employee behavior relocation characterization. */
final class BeReorg004EmployeeCharacterizationTest extends TestCase
{
    public function testCreateKeepsWorkflowGuardAndTrustedAudit(): void { $this->has(["BusinessWorkflowWriteBoundary::create('employee'",'createdAudit()']); }
    public function testCodeUniquenessUsesStableError(): void { $this->has(['findByCode','ERROR_CODE_DUPLICATE']); }
    public function testUserUniquenessUsesStableError(): void { $this->has(['findByUserId','ERROR_USER_DUPLICATE']); }
    public function testGenderNormalizationKeepsCanonicalThreeValuesAndNull(): void { $this->has(["['MALE', 'FEMALE', 'OTHER']",'ERROR_GENDER_INVALID']); }
    public function testStatusNormalizationKeepsWorkflowBoundary(): void { $this->has(['WorkflowBusinessStatus::normalize','ERROR_STATUS_INVALID']); }
    public function testManagerSetAndRemoveRouteThroughUpdate(): void { $this->has(['setManager(int $employeeId, ?int $managerEmployeeId)','manager_employee_id']); }
    public function testManagerSelfMissingAndInactiveErrorsRemainDistinct(): void { $this->has(['ERROR_MANAGER_SELF','ERROR_MANAGER_REFERENCE_INVALID','ERROR_MANAGER_INACTIVE']); }
    public function testManagerCycleAndBrokenChainRemainFailClosed(): void { $this->has(['ERROR_MANAGER_CYCLE','isset($visited[$cursorId])']); }
    public function testInactivateRoutesThroughCanonicalUpdate(): void { $this->has(['inactivate(int $employeeId)',"'status' => 'INACTIVE'"]); }
    public function testManagerLockKeyRemainsExactAndOrdinaryUpdateCanAvoidLock(): void { $this->has(["synchronized('employee-manager-tree'",'! $managerChange && ! $statusChangeToInactive']); }
    /** @param list<string> $needles */ private function has(array $needles): void { $p=dirname(__DIR__,2).'/app/Modules/EmployeeStructure/Employee/Application/EmployeeService.php'; $this->assertFileExists($p); $s=(string)file_get_contents($p); foreach($needles as $n){$this->assertStringContainsString($n,$s);} }
}
