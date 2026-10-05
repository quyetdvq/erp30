# Gate 1 Task Registry

Pointers only. DBD is authoritative for persistence; Contract/FRS pointers
provide runtime and semantic constraints.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-DB-001 — Migration inventory & forward-only plan | P1-ENV-004 | MEDIUM | db-migration | DBD §22 Migration order/forward-only; IMP Plan §8.2 `P1-DB-001` | migration inventory and plan | opens DB-010/040 |
| P1-DB-010 — Foundation location migrations | P1-DB-001 | MEDIUM | db-migration | FRS `FRS-P1-LOC-001..002`; DBD Location domain; IMP Plan `P1-DB-010` | location schema | follow-up DB-020 |
| P1-DB-020 — Organization + Contact migrations | P1-DB-010 | MEDIUM | db-migration | FRS `FRS-P1-ORG-001..005`, `FRS-P1-CON-001..002`; DBD Organization/Contact; IMP Plan `P1-DB-020` | organization/contact schema | follow-up ORG/CON/SEED-004 |
| P1-DB-030 — Department/Position/Employee/Assignment migrations | P1-DB-020 | MEDIUM | db-migration | FRS `FRS-P1-EMP-001..005`; DBD Employee/Assignment; IMP Plan `P1-DB-030` | employee structure schema | follow-up EMP tasks |
| P1-DB-040 — Workflow/Lifecycle migrations | P1-DB-001 | MEDIUM | db-migration | FRS `FRS-P1-WF-001..005`; DBD Lifecycle; IMP Plan `P1-DB-040` | lifecycle schema | parallel DB-030; follow-up SEED-002/WF-001 |
| P1-DB-050 — Resource/Auth/Auto Numbering migrations | P1-DB-030, P1-DB-040 | HIGH | db-migration | FRS `FRS-P1-IAM-001..004`, `FRS-P1-PBAC-001..007`, `FRS-P1-NUM-001..003`; DBD §§11,13; Contract §§6–9; IMP Plan `P1-DB-050` | auth/numbering schema | follow-up SEED-001, AUTH-001, NUM-001 |
| P1-DB-060 — Product/UOM/Combo migrations | P1-DB-050 | HIGH | db-migration | FRS `FRS-P1-PRD-001..006`, `FRS-P1-UOM-001..005`, `FRS-P1-CMB-001..003`; DBD Product/UOM/Combo; IMP Plan `P1-DB-060` | product/UOM/COMBO schema | follow-up PRD/UOM/NUM-006 |
| P1-DB-070 — Pricing migrations | P1-DB-060 | HIGH | db-migration | FRS `FRS-P1-PRC-*`; DBD Pricing; Contract §14; IMP Plan `P1-DB-070` | pricing schema | follow-up PRC-001 |
| P1-DB-080 — Import migrations | P1-DB-060 | HIGH | db-migration | FRS `FRS-P1-IMP-001..005`; DBD Import; Contract §13; IMP Plan `P1-DB-080` | import schema | follow-up IMP-001 |
| P1-SEED-001 — Resource + Action seed | P1-DB-050 | HIGH | seed | Contract §7 Resource/Action seed; FRS `FRS-P1-IAM-002`; IMP Plan `P1-SEED-001` | idempotent resource/action catalog | follow-up IAM-001; parallel SEED-002 |
| P1-SEED-002 — Workflow state/lifecycle bootstrap seed | P1-DB-040 | MEDIUM | seed | Contract §8 Workflow seed; FRS `FRS-P1-WF-001`; IMP Plan `P1-SEED-002` | idempotent workflow catalog | parallel SEED-001/004; follow-up WF-001 |
| P1-SEED-003 — Privileged Authorization Group bootstrap | P1-SEED-001 | HIGH | seed | FRS `FRS-P1-IAM-003`, `FRS-P1-PBAC-007`; Contract §9 bootstrap/no bypass; IMP Plan `P1-SEED-003` | privileged group bootstrap path | follow-up IAM-001; no default batching |
| P1-SEED-004 — GROUP root bootstrap | P1-DB-020 | HIGH | seed | FRS `FRS-P1-ORG-001`; DBD Organization root; Contract §5 context; IMP Plan `P1-SEED-004` | canonical GROUP root | parallel SEED-002; follow-up ORG-002 |
| P1-DB-090 — Fresh + forward migration verification | P1-DB-010..P1-DB-080, P1-SEED-001..P1-SEED-004 | HIGH | db-migration | DBD §22; IMP Plan §8.2 `P1-DB-090` and Gate 1 exit criteria | fresh/forward evidence | closes Gate 1; follow-up Gate 2 |
