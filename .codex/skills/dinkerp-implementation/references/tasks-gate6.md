# Gate 6 Task Registry

Pointers only. Import is Product-only, two-gate authorized, dependency-graph
driven, and row-atomic.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-IMP-001 — ImportJob repository/service skeleton | P1-DB-080 | MEDIUM | service-standard | FRS `FRS-P1-IMP-001..005`; DBD Import; Contract §13; IMP Plan `P1-IMP-001` | import job service skeleton | parallel IMP-002/003 after contract |
| P1-IMP-002 — CSV parser | P1-IMP-001 | MEDIUM | service-standard | FRS `FRS-P1-IMP-001`; Contract §13 CSV rules; IMP Plan `P1-IMP-002` | strict CSV parser | parallel IMP-003 |
| P1-IMP-003 — XLSX parser | P1-IMP-001 | MEDIUM | service-standard | FRS `FRS-P1-IMP-001`; Contract §13 XLSX/formula rules; IMP Plan `P1-IMP-003` | strict XLSX parser | parallel IMP-002 |
| P1-IMP-004 — Header/null/mode validation | P1-IMP-002, P1-IMP-003 | HIGH | high-risk | FRS `FRS-P1-IMP-001..002`; Contract §13 template/mode/null rules; IMP Plan `P1-IMP-004` | whole-file validation | follow-up IMP-005 |
| P1-IMP-005 — Product import model + source-key graph | P1-IMP-004, P1-PRD-004, P1-UOM-001, P1-CMB-001 | HIGH | high-risk | FRS `FRS-P1-IMP-002..003`; Contract §13 source-key graph; IMP Plan `P1-IMP-005` | dependency graph/model | follow-up IMP-006 |
| P1-IMP-006 — Product row mutation + Auto Number | P1-IMP-005, P1-NUM-006 | HIGH | high-risk | FRS `FRS-P1-IMP-003..005`, `FRS-P1-NUM-003`; Contract §§8,13 row transaction; IMP Plan `P1-IMP-006` | atomic product row mutation | follow-up IMP-007/008 |
| P1-IMP-007 — Two-gate import authorization | P1-IMP-006, P1-AUTH-002 | HIGH | high-risk | FRS `FRS-P1-IMP-004`, `FRS-P1-PBAC-*`; Contract §13 two-gate auth; IMP Plan `P1-IMP-007` | import + row authorization | parallel IMP-008 |
| P1-IMP-008 — Row result/job reconciliation | P1-IMP-006 | MEDIUM | service-standard | FRS `FRS-P1-IMP-005`; Contract §13 row results; IMP Plan `P1-IMP-008` | reconciled job/report result | follow-up IMP-090 |
| P1-IMP-090 — Import regression suite | P1-IMP-001..P1-IMP-008 | HIGH | high-risk | FRS `FRS-P1-IMP-001..005`; Contract §13 mandatory tests; IMP Plan `P1-IMP-090` | import regression evidence | closes Gate 6; follow-up Gate 7 |
