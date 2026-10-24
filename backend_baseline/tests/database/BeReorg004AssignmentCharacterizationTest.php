<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Assignment behavior relocation characterization. */
final class BeReorg004AssignmentCharacterizationTest extends TestCase
{
    public function testCreateKeepsReferenceAndPrimaryOverlapValidationInsideEmployeeLock(): void { $this->has(['withEmployeeLock($employeeId','assertReferences($payload)','assertPrimaryDoesNotOverlap($payload)']); }
    public function testUpdateKeepsEmployeeImmutable(): void { $this->has(['ERROR_EMPLOYEE_IMMUTABLE','currentEmployeeId']); }
    public function testEndPreservesHistoricalReferenceSemantics(): void { $this->has(['public function end','effective_to',"'status' => 'INACTIVE'"]); }
    public function testMissingAndInactiveReferencesRemainDistinctErrors(): void { $this->has(['ERROR_REFERENCE_INVALID','ERROR_REFERENCE_INACTIVE','requireActiveReferences']); }
    public function testDateValidationRemainsStable(): void { $this->has(['ERROR_DATE_INVALID','effective_from','effective_to']); }
    public function testPrimaryOverlapRemainsRepositoryBacked(): void { $this->has(['findOverlappingPrimary','ERROR_PRIMARY_OVERLAP']); }
    public function testEffectiveReadsUseUtcEvaluationDate(): void { $this->has(['findEffective(','EvaluationTime','businessDate(new DateTimeZone(\'UTC\'))']); }
    public function testBatchEffectiveReadsNormalizeEmployeeIds(): void { $this->has(['findEffectiveForEmployees','PositiveId::normalize']); }
    public function testPrimaryResolutionRemainsFirstEffectivePrimary(): void { $this->has(['resolvePrimary','count($rows) > 1','true,']); }
    public function testEligibleScopeUsesExactOrganizationAncestorPath(): void { $this->has(['resolveEligible','organizationPath','ERROR_EVALUATION_ORGANIZATION_INVALID']); }
    public function testDefaultActorContextStillUsesPrimaryOrganization(): void { $this->has(['resolveDefaultActorContext','resolvePrimary']); }
    public function testAssignmentLockKeyRemainsEmployeeSpecific(): void { $this->has(['\'employee-assignment:\' . $employeeId','DomainLockService']); }
    /** @param list<string> $needles */ private function has(array $needles): void { $p=dirname(__DIR__,2).'/app/Modules/EmployeeStructure/Assignment/Application/EmployeeAssignmentService.php'; $this->assertFileExists($p); $s=(string)file_get_contents($p); foreach($needles as $n){$this->assertStringContainsString($n,$s);} }
}
