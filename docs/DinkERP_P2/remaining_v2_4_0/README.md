# DinkERP P2+P3 — Remaining user requirements (v2.4.0 baseline)

## Contents

- `DinkERP_00_Remaining_User_Requirements_v2.4.0_DRAFT.md`: business requirements delta, only missing features/slices.
- `DinkERP_Remaining_Requirements_Traceability_v2.4.0.csv`: traceability for 139 P2 baseline AC remaining, 53 P3 baseline AC and 10 new proposed acceptance checks (202 rows total).

## Authority and limitations

- Source of truth remains `../reference/00_User_Requirements_v1.0.15.md`. This folder **does not** modify, replace or approve that baseline.
- DRAFT: require Product Owner/Architecture/Security/DBA decisions before schema/API/UI implementation.
- Existing CUS/SUP CRUD, contacts, numbering and master-change history are **out of new feature scope**.
- Status is based on code inspection of `erp_v2.4.0.zip` (2026-10-31) only. No live-DB or browser acceptance is claimed.
- `P3-SRC-AC-*` and `DRAFT-AT-*` are tracking IDs, not preapproved source acceptance IDs.
- No application source, database migrations, or executable configuration changes in this documentation patch.

Next: approve scope and decisions, then create per-module functional, data, PBAC, API, UI, implementation, testing and operational documents in G0-G7.
