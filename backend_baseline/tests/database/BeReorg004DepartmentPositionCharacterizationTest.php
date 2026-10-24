<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** @internal Source-level characterization pins relocated Department/Position behavior to the accepted checkpoint. */
final class BeReorg004DepartmentPositionCharacterizationTest extends TestCase
{
    public function testDepartmentCreateKeepsWorkflowGuardAuditAndTreeLock(): void { $s=$this->department(); $this->assertAll($s,["BusinessWorkflowWriteBoundary::create('department'","createdAudit()","synchronized('department-tree'"]); }
    public function testDepartmentDuplicateCodeUsesStableError(): void { $s=$this->department(); $this->assertAll($s,['assertCodeAvailable','ERROR_CODE_DUPLICATE']); }
    public function testDepartmentMissingParentPrecedesCycleWalk(): void { $s=$this->department(); $this->assertAll($s,['ERROR_REFERENCE_INVALID','ERROR_HIERARCHY_CYCLE','assertParentAndCycle']); }
    public function testDepartmentUpdateKeepsDirectStateGuardAndCheckpointLocking(): void { $s=$this->department(); $this->assertAll($s,["assertNoDirectStateMutation('department'",'return $this->withTreeLock']); }
    public function testDepartmentHierarchyWalkFailsClosedOnBrokenOrRepeatedNodes(): void { $s=$this->department(); $this->assertAll($s,['isset($visited[$cursorId])',"['field' => 'parent_id']"]); }
    public function testPositionCreateKeepsWorkflowGuardDefaultsAndAudit(): void { $s=$this->position(); $this->assertAll($s,["BusinessWorkflowWriteBoundary::create('position'",'$data[\'status\'] = \'ACTIVE\'','createdAudit()']); }
    public function testPositionDuplicateCodeUsesStableError(): void { $s=$this->position(); $this->assertAll($s,['assertCodeAvailable','ERROR_CODE_DUPLICATE']); }
    public function testPositionLevelKeepsNullableNegativeAndStringIntegerSupport(): void { $s=$this->position(); $this->assertAll($s,['$data[\'level\'] !== null','is_int($data[\'level\'])',"preg_match('/\\A-?[0-9]+\\z/'"]); }
    public function testPositionStatusKeepsWorkflowNormalizationAndDirectStateGuard(): void { $s=$this->position(); $this->assertAll($s,['WorkflowBusinessStatus::normalize',"assertNoDirectStateMutation('position'"]); }
    public function testPositionRemainsWithoutDomainLockDependency(): void { $s=$this->position(); $this->assertStringNotContainsString('DomainLockService',$s); $this->assertStringNotContainsString('MariaDbDomainLockService',$s); }
    private function department(): string { return $this->source('Department/Application/DepartmentService.php'); }
    private function position(): string { return $this->source('Position/Application/PositionService.php'); }
    private function source(string $relative): string { $p=dirname(__DIR__,2).'/app/Modules/EmployeeStructure/'.$relative; $this->assertFileExists($p); return (string)file_get_contents($p); }
    /** @param list<string> $needles */ private function assertAll(string $source,array $needles): void { foreach($needles as $n){$this->assertStringContainsString($n,$source);} }
}
