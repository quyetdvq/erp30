# P1-NUM-003 Scope and Period Resolver Assessment

Status: DONE
Risk: HIGH
Task: P1-NUM-003

## Scope

Implemented the Auto Numbering scope and period resolver behind the existing
NUM-001 configuration and NUM-002 parsed-pattern boundaries. The resolver
returns an immutable scope/period context and does not allocate counters,
render identifiers, persist state, change schema, expose API/UI behavior, or
start NUM-004/005/006/090.

## Resolution behavior

`GROUP` resolves exactly one active canonical GROUP root and ignores a caller
supplied Evaluation Organization for scope identity. `COMPANY` resolves the
self/nearest Company owner from the authoritative Organization Context.
`ORGANIZATION` preserves the exact authoritative Evaluation Organization.
Organization and Company codes are read from the resolved canonical rows;
Company and Group prerequisites fail closed with typed domain error codes.

Period keys are `NEVER -> ALL`, `YEARLY -> YYYY`, `MONTHLY -> YYYYMM`, and
`DAILY -> YYYYMMDD`. Date values use the single `OperationContext` evaluation
time and the configured Company or Group/default business timezone. Missing,
invalid, or infrastructure-failed timezone resolution is typed; no wall-clock
time or browser timezone is used. Parsed NUM-002 metadata is consumed
directly, and raw pattern input without that metadata is rejected.

## Boundaries

`BusinessTimezoneProvider` is a narrow infrastructure port implemented by the
existing Settings resolver. The result carries scope type/id, period key,
timezone/date, canonical organization/company codes, and optional resource
type for later rendering. No counter row is read or mutated by the resolver;
the NUM-004 transaction/lock boundary remains unchanged and out of scope.

## Evidence

- Focused NUM-003 suite: 12 tests / 55 assertions, PASS.
- NUM-001, NUM-002, CTX-001, CTX-002, and Gate 0 timezone regression: 32 tests / 129 assertions, PASS.
- Full backend PHPUnit (`--no-coverage`): 199 tests / 2301 assertions, PASS.
- PHPStan level 8, architecture guard, authority context-cache validator, ledger validator, and `git diff --check`: run at task closeout.
