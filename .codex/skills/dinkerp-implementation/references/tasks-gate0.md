# Gate 0 Task Registry

Pointers only. Read the cited headings in the authority documents before work.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-ENV-001 — Repository & runtime inventory | none | LOW | docs | IMP Plan §8.1 `P1-ENV-001`; Contract §§3,9,15,20 | verified repository/runtime map | opens ENV-002..004, INF-001..003, INF-005..006 |
| P1-ENV-002 — Backend quality command baseline | P1-ENV-001 | LOW | service-standard | IMP Plan §8.1 `P1-ENV-002`; Contract §20 Quality gates | repeatable PHPUnit/PHPStan gate | parallel ENV-003/004 after ENV-001 |
| P1-ENV-003 — Frontend quality command baseline | P1-ENV-001 | LOW | frontend | IMP Plan §8.1 `P1-ENV-003`; UI Design §21 Quality gates | repeatable lint/type/test/build gate | parallel ENV-002/004 after ENV-001 |
| P1-ENV-004 — Test database and migration harness | P1-ENV-001 | MEDIUM | db-migration | IMP Plan §§4,8.1 `P1-ENV-004`; DBD §22 Migration/forward rules | isolated DB test workflow | prerequisite DB-001; parallel ENV-002/003 |
| P1-INF-001 — Clock + Evaluation Time adapter | P1-ENV-001 | MEDIUM | service-standard | FRS `FRS-P1-GEN-008`; Contract §4 Time; IMP Plan `P1-INF-001` | single-time-provider abstraction | parallel INF-002/003/005/006 |
| P1-INF-002 — Request/Correlation ID middleware | P1-ENV-001 | MEDIUM | api | FRS `FRS-P1-GEN-003`; Contract §3 API/request_id; IMP Plan `P1-INF-002` | request_id propagation | follow-up API-001 |
| P1-INF-003 — Settings/BusinessTimezone resolver | P1-ENV-001 | MEDIUM | service-standard | FRS `FRS-P1-GEN-008`; Contract §5 Business timezone; IMP Plan `P1-INF-003` | Company/GROUP timezone integration | follow-up NUM-001/003 |
| P1-INF-004 — DomainLockService MariaDB adapter | P1-ENV-004 | HIGH | high-risk | Contract §10 DomainLock/advisory lock; DBD concurrency rules; IMP Plan `P1-INF-004` | namespaced advisory-lock abstraction | follow-up ORG-002, NUM-004, CMB-001 |
| P1-INF-005 — Decimal arithmetic readiness decision | P1-ENV-001 | HIGH | high-risk | FRS `FRS-P1-GEN-007`; Contract §14 Decimal/Pricing; IMP Plan `P1-INF-005` | approved safe decimal mechanism | follow-up CMB-003/PRC-002 |
| P1-INF-006 — Backend locale negotiation / i18n foundation | P1-ENV-001 | MEDIUM | api | FRS `FRS-P1-GEN-009`; Contract §5 Locale; API Design locale section; IMP Plan `P1-INF-006` | `vi`/`en` request locale + translations | follow-up API-001 and FE slices |
