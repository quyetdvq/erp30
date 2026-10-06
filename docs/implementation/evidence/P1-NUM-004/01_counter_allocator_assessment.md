# P1-NUM-004 Counter Allocator Assessment

Status: DONE
Risk: HIGH
Task: P1-NUM-004

## Scope

Implemented the canonical Auto Numbering counter repository and allocator on
top of NUM-001 series configuration, NUM-002 parsed patterns, NUM-003 runtime
scope/period context, and the existing INF-004 DomainLockService. The task
does not add schema, ResourceIdentifierAdapter metadata, Product behavior,
API/UI behavior, or NUM-005/006/090 work.

## Counter and transaction behavior

`AutoNumberingCounterRepository` owns identity lookup, `FOR UPDATE` lookup,
first-row insertion, `last_value` update, and typed duplicate/FK/persistence
errors. Locked lookup requires an active transaction. `AutoNumberingCounterAllocator`
starts one transaction around candidate checks, business mutation callback, and
counter mutation. Existing counters use `FOR UPDATE` and advance by
`increment_by`; first counters hold the canonical advisory key
`auto-number:{series_id}:{scope_organization_id}:{period_key}`, re-check after
lock acquisition, and insert the actual issued `start_number` only after the
business callback succeeds. No placeholder counter is persisted.

## Rendering and collision boundary

Candidates render only from immutable NUM-002 segments and NUM-003 context:
sequence minimum padding, date parts, Company/Organization codes, and the
canonical Resource type. Missing runtime values fail closed. A narrow
`AutoNumberingBusinessMutation` port owns occupied pre-check and final business
mutation; only the typed identifier-collision code advances the sequence.
Unrelated failures propagate. Candidate attempts are bounded, and the counter
stores the actual successfully committed sequence.

## Concurrency and rollback evidence

Real independent MariaDB worker processes prove existing-counter `FOR UPDATE`
serialization (`105`, `110`) and first-counter advisory-lock serialization
(`100`, `105`) with exactly one counter identity. Focused tests also prove
occupied/final-collision skipping, first/existing rollback and sequence reuse,
committed non-reuse, rendering, scope/period identity isolation, FK/lock typed
failures, and repository transaction guards.

## Evidence

- Focused NUM-004 suite: 10 tests / 54 assertions, PASS.
- NUM-001, NUM-002, NUM-003 and Gate 0 DomainLock regressions: 31 tests / 152 assertions, PASS.
- Full backend PHPUnit (`--no-coverage`): 209 tests / 2355 assertions, PASS.
- PHPStan level 8, architecture guard, authority context-cache validator, ledger validator, and `git diff --check`: run at task closeout.
