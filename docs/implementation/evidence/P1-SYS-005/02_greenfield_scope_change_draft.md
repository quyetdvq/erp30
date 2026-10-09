# P1-SYS-005 - Greenfield Release Scope Change Draft

Status: **DRAFT / NOT APPROVED**  
Version: `P1-SYS-005-SCOPE-CHANGE-v1.0`  
Date: 2026-10-09

## Proposed change

For the proposed P1 production release, installation is **greenfield only**.
Legacy schema upgrade/harmonization is excluded from the release scope because
the approved legacy reset left no authoritative populated schema baseline,
dump, provenance record, or representative data snapshot from which an
existing-schema forward rehearsal could be proven.

This draft does not alter DBD/Contract/Plan authority, mark `P1-SYS-005`
`DONE`, waive Gate 9, or authorize a development/production migration.

## Required approval

| Role | Decision required | Status |
|---|---|---|
| Product Owner | Accept greenfield-only release scope and customer/deployment impact. | OPEN |
| Architecture | Approve exclusion of legacy upgrade from this release and record the follow-up track. | OPEN |
| Release owner | Confirm SYS-005 remains an independent blocker until this change is formally recorded. | OPEN |
| Security/Operations | Confirm staging/production runbooks do not execute legacy conversion. | OPEN |

Approval must identify the approved change-control ticket, effective release,
and follow-up owner. No names, signatures, ticket IDs, or dates are fabricated
in this draft.

## Conditions to become effective

1. Attach this draft to the approved PO/Architecture change-control record.
2. Update the release scope, deployment runbook, and risk register to state
   greenfield-only installation.
3. Keep the SYS-005 ledger row `BLOCKED` until the approved record is linked and
   the release gate explicitly accepts the scope change.
4. If legacy upgrade is later required, provide a provenance-bearing dump or
   schema snapshot, restore it into isolated `erp_test`, and rerun the full
   existing-schema rehearsal without rewriting applied migrations.

## Evidence boundary

The existing SYS-005 evidence proves fresh/forward/idempotent checks on the
canonical test baseline and proves the absence of a recoverable legacy source.
It does not prove legacy data preservation or harmonization. This distinction
must remain visible in the formal approval and release notes.
