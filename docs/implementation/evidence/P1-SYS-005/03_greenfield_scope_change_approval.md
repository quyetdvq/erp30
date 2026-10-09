# P1-SYS-005 - Greenfield-Only Release Scope Change Approval

Date: 2026-10-09  
Task: `P1-SYS-005`  
Decision: **APPROVED**

## Approved replacement acceptance

The P1 production release is approved as **greenfield-only**. Existing-schema
upgrade and legacy harmonization are excluded from this release because no
authoritative populated legacy baseline, provenance-bearing dump, or
representative legacy snapshot survived the approved reset. The missing
legacy rehearsal is therefore replaced by this explicit release-scope change;
it is not represented as a passed legacy migration rehearsal.

This approval applies to the user-approved decision table supplied on
2026-10-09. It does not amend the pinned UR, FRS, DBD, Contract, Plan, API, or
UI baselines for any future release. A later legacy upgrade requires a new
scope decision and an authoritative restored source in isolated `erp_test`.

## Release conditions

- New installations must use the canonical P1 fresh migration/bootstrap path.
- Deployment runbooks must state that legacy conversion is out of scope for
  this release and must not run an unverified conversion against `erp` or
  production.
- SYS-005 acceptance is satisfied by the approved scope replacement and the
  existing fresh/forward/idempotent Gate 1 evidence; legacy data preservation
  remains explicitly not verified and is not a release claim.
- Any future legacy track must provide schema/migration history, identities,
  representative Product/UOM/Pricing/Import rows, checksum, restore steps,
  and a complete existing-schema rehearsal before being accepted.

## Evidence and outcome

The original rehearsal remains preserved as historical evidence:
`01_existing_schema_forward_migration_rehearsal.md` records why the legacy
path could not run. The approved replacement is recorded here and in the live
ledger. No migration history was rewritten and no development database was
reset or used as a legacy substitute.

**Outcome: DONE by approved greenfield-only scope replacement.**
