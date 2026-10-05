# Gate 7 Task Registry

Pointers only. Public API changes require OpenAPI lint and generated frontend
artifact refresh in the same task.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-API-001 — Common API response/error/request-id infrastructure | P1-INF-002, P1-AUTH-002 | HIGH | api | API Design §§2–5; Contract §3 transport/error; FRS `FRS-P1-GEN-*`; IMP Plan `P1-API-001` | common `/api/v1` transport | follow-up API-002/003 and endpoint tasks |
| P1-API-002 — OpenAPI source + lint + frontend generation pipeline | P1-API-001, P1-ENV-003 | HIGH | api | API Design §19 OpenAPI/generation; Contract §3; IMP Plan `P1-API-002` | source/lint/codegen pipeline | follow-up FE-001/002 |
| P1-API-003 — Common list/filter/sort/pagination adapter | P1-API-001 | MEDIUM | api | FRS `FRS-P1-GEN-006`; API Design query/list sections; IMP Plan `P1-API-003` | common query adapter | parallel API endpoint slices |
| P1-API-010 — Foundation/Organization/Contact endpoints | Gate 2 | MEDIUM | api | API Design Foundation/Organization/Contact sections; FRS `FRS-P1-LOC-*`, `ORG-*`, `CON-*`; IMP Plan `P1-API-010` | Slice 1 API | follow-up FE-010 |
| P1-API-020 — Employee/Assignment endpoints | Gate 2 | HIGH | api | API Design Employee/Assignment sections; FRS `FRS-P1-EMP-*`; IMP Plan `P1-API-020` | Slice 2 API | follow-up FE-020 |
| P1-API-030 — Resource/Group/Policy/Lifecycle endpoints | Gate 3 | HIGH | api | API Design IAM/PBAC/Workflow sections; FRS `FRS-P1-IAM-001..004`, `FRS-P1-PBAC-001..007`, `FRS-P1-WF-001..005`; IMP Plan `P1-API-030` | Slice 3 API | follow-up FE-030/040 |
| P1-API-040 — Auto Numbering endpoints | Gate 4 | HIGH | api | API Design Auto Numbering section; FRS `FRS-P1-NUM-*`; IMP Plan `P1-API-040` | numbering API | follow-up FE-050 |
| P1-API-050 — Product/UOM/Pricing/Combo endpoints | Gate 5 | HIGH | api | API Design Product/Pricing/COMBO sections; FRS `FRS-P1-PRD-001..006`, `FRS-P1-UOM-001..005`, `FRS-P1-PRC-001`, `FRS-P1-CMB-001..003`; IMP Plan `P1-API-050` | product-domain API | follow-up FE-060/070 |
| P1-API-060 — Product Import endpoints | Gate 6 | HIGH | api | API Design Import section; FRS `FRS-P1-IMP-001..005`; IMP Plan `P1-API-060` | import API/report | follow-up FE-080 |
| P1-API-090 — API contract regression suite | P1-API-001..P1-API-060 | HIGH | api | API Design §§3–19; Contract §3; IMP Plan `P1-API-090` | API contract evidence | closes Gate 7; follow-up Gate 8 |
