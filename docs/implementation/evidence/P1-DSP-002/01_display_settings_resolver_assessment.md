# P1-DSP-002 Assessment

Status: DONE
Task: P1-DSP-002 - Organization-path effective DisplaySettingsResolver
Risk/impact/profile: HIGH / backend-security / high-risk
Dependencies: P1-DSP-001 DONE; P1-CTX-001 DONE
Authority cache: VALID

## Scope and implementation

The resolver reuses the P1-DSP-001 `DisplaySettingRegistry`,
`DisplaySettingsContext`, and `CodeIgniterDisplaySettingsStorage`. It resolves
only registered presentation keys and reads canonical Organization ancestry
from `OrganizationService::resolveAncestorChain()`.

For an Organization target, candidates are evaluated in this order:

```text
current Organization -> exact parent chain -> GROUP -> system -> registry default
```

The resolver validates the target through the canonical service, does not accept
client ancestry or Primary Assignment input, ignores invalid legacy values, and
returns the storage/registry value without changing its canonical PHP type.
Without an Organization target it evaluates `system` then the registry default.
No mutation, authorization, audit, API/OpenAPI, frontend, Resource/Action, or
cross-request cache behavior was added. `Display.timezone` is not connected to
`BusinessTimezoneResolver` or any effective-date/business calculation path.

## Focused behavior evidence

- Leaf, ancestor, GROUP, system, and registry-default precedence: covered by
  `testNearestOrganizationPathOverrideWinsThenFallsBackThroughGroupSystemAndDefault`.
- Sibling isolation: `testSiblingOverrideNeverParticipates`.
- Missing Organization context: `testMissingOrganizationUsesSystemThenRegistryDefault`.
- Missing target validation and unknown key rejection:
  `testTargetMustExistAndAnUnknownKeyCannotBecomeRuntimeSetting`.
- Invalid/stale value exclusion, registry-only snapshot, and canonical integer
  type: `testInvalidStoredValueIsExcludedAndResolveAllUsesOnlyRegistryKeysAndTypes`.
- Re-parent path follows current persisted hierarchy:
  `testReparentingChangesTheCanonicalRuntimePath`.
- Business Timezone isolation: `testDisplayTimezoneDoesNotReplaceBusinessTimezone`.

## Validation

| Check | Result |
|---|---|
| `tests/unit/P1Dsp002DisplaySettingsResolverTest.php --no-coverage` | PASS; 7 tests, 22 assertions |
| Impacted Display/Context/Organization/Gate 0 tests | PASS; 43 tests, 182 assertions |
| Full backend PHPUnit `--no-coverage` | PASS; 421 tests, 3774 assertions |
| PHPStan level 8 (`app`) | PASS; no errors |
| Architecture guard | PASS; 44 application files checked |
| Context cache validator | PASS; 8 authorities, 107 tasks, acyclic graph |
| Ledger validator (pre-closure) | PASS; 80 DONE tasks |
| `git diff --check` | PASS |

## Closure boundary

Changed files are limited to the resolver, its focused regression suite, this
assessment, and the execution ledger. `P1-DSP-003 started: NO`.

The completion commit is recorded in the ledger using the repository's
pre-commit `0000000` convention; the actual commit hash is authoritative after
commit.
