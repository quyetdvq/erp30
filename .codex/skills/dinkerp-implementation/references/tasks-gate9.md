# Gate 9 Task Registry

Pointers only. Gate 9 is release verification and cannot close with static or
mock-only evidence where an authenticated/runtime journey is required.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-SYS-001 — Full FRS Acceptance Test Catalog run | Gates 1-8 | HIGH | release | FRS §Acceptance Test Catalog; IMP Plan Gate 9 / `P1-SYS-001` | full acceptance evidence | parallel SYS-002..007 where dependencies pass |
| P1-SYS-002 — Authorization/security regression run | Gate 3, Gate 7 | HIGH | high-risk | FRS `FRS-P1-PBAC-001..007`, `FRS-P1-IAM-001..004`; Contract §§6,18; IMP Plan `P1-SYS-002` | security regression evidence | parallel SYS-003/004 |
| P1-SYS-003 — Concurrency/locking regression run | Gates 2,4,5,6 | HIGH | high-risk | Contract §9; FRS `FRS-P1-SVC-001..011`, `FRS-P1-NUM-001..003`, `FRS-P1-CMB-001..003`, `FRS-P1-IMP-001..005`; IMP Plan `P1-SYS-003` | concurrency evidence | parallel SYS-002/004 |
| P1-SYS-004 — Fresh migration rehearsal | Gate 1 | HIGH | release | DBD §22; IMP Plan `P1-SYS-004` | fresh rehearsal evidence | parallel SYS-005 |
| P1-SYS-005 — Existing-schema forward migration rehearsal | Gate 1 | HIGH | release | DBD §22 forward-only; Contract §19 migration/bootstrap; IMP Plan `P1-SYS-005` | forward rehearsal evidence | parallel SYS-004 |
| P1-SYS-006 — OpenAPI/frontend contract drift check | Gates 7-8 | HIGH | release | API Design §§15–17; UI Design typed integration; IMP Plan `P1-SYS-006` | drift-free contract evidence | parallel SYS-007 |
| P1-SYS-007 — Production configuration review | Gates 0-8 | HIGH | release | Contract §20 production config; IMP Plan `P1-SYS-007` | production readiness review | follow-up UAT-001 |
| P1-UAT-001 — P1 business UAT package | P1-SYS-001..P1-SYS-007 | HIGH | release | UR business scope; FRS acceptance catalog; IMP Plan `P1-UAT-001` | UAT package/results | follow-up RC-001 |
| P1-RC-001 — Release candidate evidence pack | P1-UAT-001 | HIGH | release | IMP Plan §16 RC evidence minimum; Contract §23 DoD | release candidate evidence pack | terminal task |
