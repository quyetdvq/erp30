# Gate 4 Task Registry

Pointers only. Number allocation and business create must share one transaction
and serialization boundary.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-NUM-001 — Numbering series repository/config service | P1-DB-050, P1-AUTH-002, P1-INF-003 | HIGH | high-risk | FRS `FRS-P1-NUM-001`; DBD Auto Numbering; Contract §8 config; IMP Plan `P1-NUM-001` | series configuration service | parallel NUM-002 after contract |
| P1-NUM-002 — Pattern parser/validator | P1-NUM-001 | HIGH | high-risk | FRS `FRS-P1-NUM-002`; Contract §8 canonical tokens; IMP Plan `P1-NUM-002` | safe pattern validator | follow-up NUM-004 |
| P1-NUM-003 — Scope + period resolver | P1-NUM-001, P1-CTX-001, P1-INF-003 | HIGH | high-risk | FRS `FRS-P1-NUM-002`; Contract §§3.2–3.3,8 timezone/scope; IMP Plan `P1-NUM-003` | scope/period resolution | parallel NUM-002; follow-up NUM-004 |
| P1-NUM-004 — Counter allocator + transaction locking | P1-NUM-002, P1-NUM-003, P1-INF-004 | HIGH | high-risk | FRS `FRS-P1-NUM-002`; DBD counters; Contract §9 lock/transaction; IMP Plan `P1-NUM-004` | serialized counter allocator | follow-up NUM-005/090 |
| P1-NUM-005 — Resource identifier adapter contract | P1-NUM-004 | HIGH | high-risk | FRS `FRS-P1-NUM-001..003`; Contract §8 resource adapter; IMP Plan `P1-NUM-005` | identifier adapter contract | follow-up NUM-006 |
| P1-NUM-006 — Product identifier adapter | P1-NUM-005, P1-DB-060 | HIGH | high-risk | FRS `FRS-P1-NUM-003`; DBD Product identity; Contract §8 product mapping; IMP Plan `P1-NUM-006` | `product_code` to `products.code` adapter | follow-up PRD-003, IMP-006 |
| P1-NUM-090 — Auto Number concurrency/regression suite | P1-NUM-001..P1-NUM-006 | HIGH | high-risk | FRS `FRS-P1-NUM-001..003`; Contract §§8,9,18.5 mandatory tests; IMP Plan `P1-NUM-090` | allocation/rollback/no-reuse evidence | closes Gate 4; follow-up Gate 5 |
