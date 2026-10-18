# BE-REORG-010 Windows Acceptance Evidence (2026-10-17)

## Scope and source
- Current Windows PowerShell log supplied by user: `Văn bản đã dán (1).txt`, 584 lines.
- Baseline log: BE-REORG-009 full acceptance, 121 PHPStan diagnostics.
- Both PHPStan report bodies compared line by line using a unified diff.
- The test runner covers backend full PHPUnit, in-process HTTP, and database locking, not browser/Apache UAT.

## Acceptance evidence
- BE010 FIX01 focused CSRF: 1 test, 47 assertions, PASS.
- BE010 HTTP/database focused: 4 tests, 104 assertions, PASS.
- Full PHPUnit: 978 tests, 9,592 assertions, PASS; 0 failures/errors.
- Foundation guard: 17 checks PASS.
- BE010: all 30 new tests executed; no failure/error/skip.
- Inherited BE009 32, BE008 26, BE007 24, PBAC authority 91 coverage gates retained.

## PHPStan identity diff
- BE009: 121 diagnostics.
- BE010: 120 diagnostics; PHPStan exits 1 because these are still errors.
- Relocated unchanged diagnostic `return.unusedType`:
  - Previous `Services/ResourceWorkflowStateResolver.php:123`.
  - Current `Modules/Workflow/StateResolution/Application/ResourceStateResolutionService.php:96`.
- Removed diagnostic `missingType.iterableValue`:
  - Previous `Services/ResourceWorkflowStateResolver.php:307` in `stateForAuthorization()`.
  - No equivalent new diagnostic.
- Every other PHPStan diagnostic report line is unchanged.
- Result: 0 new diagnostics, 1 resolved diagnostic, 1 diagnostic relocated.

## Overall status
- Backend runtime acceptance: PASS.
- PHPStan no-new-diagnostic acceptance: PASS (raw exit code remains 1).
- Final project acceptance: PENDING independent code review and Git checkpoint.
- This report does not assert browser/Apache UAT or a clean PHPStan run.
