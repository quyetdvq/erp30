# P1-NUM-001 Numbering Series Configuration Assessment

Status: DONE
Risk: HIGH
Task: P1-NUM-001

## Scope

Implemented the persistence-only numbering-series repository and the
authorized configuration service. No counter allocation, pattern grammar,
scope/period resolution, Product adapter, API, UI, or NUM-002..006 work was
started.

## Configuration behavior

Series identity and canonical enum values are normalized and basic numeric,
text, Resource-existence, and caller-audit validation is enforced. Zero series,
multiple series, multiple ACTIVE non-default series, and an INACTIVE default
are valid. A Resource can have at most one ACTIVE default; a second candidate
is rejected with `NUM_ACTIVE_DEFAULT_CONFLICT`.

## Persistence and transaction boundary

`AutoNumberingSeriesRepository` owns queries, typed persistence failures,
trusted audit stamping, Resource/series `FOR UPDATE` locks, counter-exists
lookup, and transactions. Configuration mutations lock the Resource row before
checking the series set on the same MariaDB connection. The database
`UNIQUE(resource_id, series_code)` remains the final duplicate guard.

## Issuance behavior

A committed counter makes identity fields `resource_id`, `series_code`,
`scope_type`, and `reset_policy` immutable. Issued series cannot be hard-deleted
and may be stopped with `status=INACTIVE`. Prospective edits, including
`start_number`, do not touch existing counters or historical records.

## Authorization and pattern boundary

Every mutation calls the existing `AuthorizationService` contract with
`resource.configure_numbering`; GROUP evaluation is supplied by the canonical
context resolver. There is no System Admin bypass. NUM-002 is represented by a
narrow `NumberingPatternValidator` port; active configuration fails closed with
`NUM_PATTERN_VALIDATOR_REQUIRED` when that validator is not installed, so no
permissive production grammar was introduced.

## Evidence

- Focused NUM-001 suite: 9 tests / 43 assertions, PASS.
- Real MariaDB two-process active-default race: one commit, one typed conflict,
  final state has one ACTIVE default, PASS.
- Real AuthorizationService: edit-only, missing base permission, PBAC DENY,
  ABSOLUTE_DENY, and system_admin-without-base paths, PASS.
- Changed-file PHPStan level 8, PASS.
- Architecture guard, authority-cache validator, ledger validator, full
  PHPUnit, and full PHPStan are recorded in the task closeout.
