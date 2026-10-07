# Gate 5 Task Registry

Pointers only. Product roles, UOM, Pricing, and COMBO semantics remain in FRS/
DBD/Contract; do not derive alternate rules in consumers.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-PRD-001 — ProductGroup hierarchy repository/service | P1-DB-060, P1-INF-004 | MEDIUM | service-standard | UR-PRD-007; FRS `FRS-P1-UOM-001`; DBD v1.14 Product Group adjacency-list; Contract v1.9 `product-group-tree`; IMP Plan `P1-PRD-001` | product-group hierarchy service | parallel PRD-002 |
| P1-PRD-002 — ProductUom master repository/service | P1-DB-060 | MEDIUM | service-standard | FRS `FRS-P1-UOM-002..003`; DBD UOM catalog; IMP Plan `P1-PRD-002` | UOM master service | parallel PRD-001 |
| P1-PRD-003 — ProductRepository/master mutation service | P1-DB-060, P1-NUM-006 | HIGH | high-risk | FRS `FRS-P1-PRD-001..003`, `FRS-P1-PRD-006`; DBD Product; DBD §§15.4,19; Contract §15 service boundary; IMP Plan `P1-PRD-003` | product master mutation | follow-up PRD-004/UOM-001 |
| P1-PRD-004 — ProductRoleService | P1-PRD-003 | HIGH | high-risk | FRS `FRS-P1-PRD-001..006`; DBD §§15.4.1–15.4.3; Contract §15 service boundary; IMP Plan `P1-PRD-004` | centralized role/leaf semantics | follow-up CMB/IMP graph |
| P1-UOM-001 — ProductUomService | P1-PRD-002, P1-PRD-004 | HIGH | high-risk | FRS `FRS-P1-UOM-003..005`; DBD Product UOM; DBD §15.5; Contract §15 service boundary; IMP Plan `P1-UOM-001` | product UOM/conversion service | follow-up PRC-002/CMB-003 |
| P1-PRC-001 — PriceList repository/service | P1-DB-070 | MEDIUM | service-standard | FRS `FRS-P1-PRC-001`; DBD Pricing; IMP Plan `P1-PRC-001` | price-list service | parallel PRD/UOM after schema |
| P1-PRC-002 — PricingService | P1-PRC-001, P1-UOM-001, P1-INF-001 | HIGH | high-risk | FRS `FRS-P1-PRC-001`; Contract §14 resolution/fallback; IMP Plan `P1-PRC-002` | deterministic pricing resolution | follow-up CMB-003/API-050 |
| P1-CMB-001 — Combo composition mutation service | P1-PRD-004, P1-INF-004 | HIGH | high-risk | FRS `FRS-P1-CMB-001`; DBD Combo; DBD §15.6; Contract §9.3 lock matrix; IMP Plan `P1-CMB-001` | validated COMBO composition | follow-up CMB-002 |
| P1-CMB-002 — ComboExpansionService | P1-CMB-001 | HIGH | high-risk | FRS `FRS-P1-CMB-001..002`; DBD §§15.6,16.5; FRS COMBO rules; IMP Plan `P1-CMB-002` | flattened leaf expansion | follow-up CMB-003 |
| P1-CMB-003 — ComboAllocationService | P1-CMB-002, P1-PRC-002, P1-INF-005 | HIGH | high-risk | FRS `FRS-P1-CMB-002..003`, `CMBPRC-*`; Contract §14 decimal allocation; IMP Plan `P1-CMB-003` | deterministic decimal allocation | follow-up PRD-090/API-050 |
| P1-PRD-090 — Product/UOM/Pricing/Combo regression suite | P1-PRD-*, P1-UOM-*, P1-PRC-*, P1-CMB-* | HIGH | high-risk | FRS `FRS-P1-PRD-001..006`, `FRS-P1-UOM-001..005`, `FRS-P1-PRC-001`, `FRS-P1-CMB-001..003`; Contract §§9,14,15,18; IMP Plan `P1-PRD-090` | product-domain regression evidence | closes Gate 5; follow-up Gate 6 |
