# P1-NUM-002 Numbering Pattern Validator Assessment

Status: DONE
Risk: HIGH
Task: P1-NUM-002

## Scope

Implemented the pure canonical Auto Numbering pattern parser/validator behind
the NUM-001 `NumberingPatternValidator` boundary. The implementation parses
only the eight approved token forms, rejects unknown or unbalanced expressions,
and never evaluates pattern input. No Organization, Company, timezone, period,
counter, rendering, adapter, API, or UI behavior was added.

## Parsed representation

`ParsedNumberingPattern` is immutable and exposes the unchanged normalized
pattern, ordered literal/token segments, sequence width, token/date metadata,
discriminator flags, a deterministic minimum rendered length, and a structural
collision signature. Sequence width is minimum padding; the representation does
not allocate or render a sequence value.

## Semantic validation

`CanonicalNumberingPatternValidator` enforces exactly one positive integer
`{SEQ:n}`, reset-period requirements, and the explicit namespace compatibility
rules for GLOBAL/COMPANY/ORGANIZATION identifiers and GROUP/COMPANY/ORGANIZATION
counters. GROUP plus `{COMPANY_CODE}` is rejected statically. `{ORG_CODE}` and
`{RESOURCE}` remain syntax/compatibility metadata only. Optional
`identifier_max_length` is checked only where the lower bound is provable; no
Resource adapter registry was introduced.

The set validator rejects deterministic equivalent active-series streams. It
intentionally ignores sequence-width differences as a namespace separator and
accepts structurally distinct patterns when no collision is proven.

## NUM-001 integration

NUM-001 authorization, Resource-row locking, active-default serialization,
issuance immutability, no-hard-delete, and transaction behavior are unchanged.
When the injected validator implements the compatibility extension, create and
update also validate the active Resource series set. Pattern validation runs
before persistence and does not touch `auto_numbering_counters`. The original
fail-closed behavior remains when no validator is wired.

## Evidence

- Focused NUM-002 suite: 9 tests / 53 assertions, PASS.
- NUM-001 + NUM-002 + mandatory authorization regression: 27 tests / 164 assertions, PASS.
- Full backend PHPUnit (`--no-coverage`): 187 tests / 2246 assertions, PASS.
- PHPStan level 8: PASS.
- Architecture guard: PASS.
- Authority context-cache validator: PASS.
- Ledger validator and `git diff --check`: PASS at task closeout.
