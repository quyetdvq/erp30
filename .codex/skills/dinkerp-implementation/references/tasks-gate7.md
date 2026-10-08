# Gate 7 / Gate 7A Task Registry

Pointers only. Gate 7 exposes existing service contracts; controllers stay thin. Only `CONTRACT_READY` API surfaces may be implemented. Every public contract change updates OpenAPI in the same task, passes Redocly lint, and regenerates typed frontend artifacts.

Current v1.8 API Design §19 and approved CHANGE-USER-IDENTITY-MUTATION-001 now define the Shield User write/state/reset-password contract; do not reuse the historical v1.7 Shield-pending text. Exact export media type, public Price Resolution authorization, and optional COMBO preview endpoints remain `CONTRACT_PENDING` unless independently promoted. These approved overlays are not proof of runtime implementation or new P1 Task IDs. Employee gender remains DEC-P1-001.

`P1-API-020` remains the Plan task title "Employee/Assignment endpoints", but API Design §8.1–8.4 makes the Slice 2 surface explicit: Department, Position, Employee, and Employee Assignment. Its CHANGE-AUTH-DOMAIN-001 precondition must be satisfied before implementation; the task must preserve MASTER/GROUP vs ORGANIZATION scope and use the DEC-P1-001 gender catalog while keeping export media type pending.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-API-001 — Common API response/error/request-id infrastructure | P1-INF-002, P1-AUTH-002 | HIGH | api | API Design §§3–6,16–17; Contract §§3.4–3.5,4,6; FRS `FRS-P1-GEN-*`; IMP Plan `P1-API-001` | common `/api/v1` transport | follow-up API-002/003 and endpoint tasks |
| P1-API-002 — OpenAPI source + lint + frontend generation pipeline | P1-API-001, P1-ENV-003 | HIGH | api | API Design §§2.2–2.3,15–17; IMP Plan `P1-API-002` | source/lint/codegen pipeline | follow-up FE-001/002 |
| P1-API-003 — Common list/filter/sort/pagination adapter | P1-API-001 | MEDIUM | api | API Design §4; Contract §5; FRS `FRS-P1-GEN-006`, `FRS-P1-QRY-001`; IMP Plan `P1-API-003` | common query adapter | parallel endpoint slices |
| P1-API-010 — Foundation/Organization/Contact endpoints | Gate 2 | MEDIUM | api | API Design §7; Contract §§4–6; FRS `FRS-P1-LOC-*`, `ORG-*`, `CON-*`; IMP Plan `P1-API-010` | Slice 1 API | follow-up FE-010 |
| P1-API-020 — Employee/Assignment endpoints | Gate 2 | HIGH | api | API Design §8; Contract §§4–6; FRS `FRS-P1-EMP-*`; IMP Plan `P1-API-020` | Slice 2 API | follow-up FE-020 |
| P1-API-030 — Resource/Group/Policy/Lifecycle endpoints | Gate 3 | HIGH | api | API Design §9; Contract §§4,6,13; FRS `FRS-P1-IAM-001..004`, `FRS-P1-PBAC-001..007`, `FRS-P1-WF-001..005`; IMP Plan `P1-API-030` | Slice 3 API | follow-up FE-030/040 |
| P1-API-040 — Auto Numbering endpoints | Gate 4 | HIGH | api | API Design §10; Contract §§4,6,8; FRS `FRS-P1-NUM-*`; IMP Plan `P1-API-040` | numbering API | follow-up FE-050 |
| P1-API-050 — Product/UOM/Pricing/Combo endpoints | Gate 5 | HIGH | api | API Design §§11–12; Contract §§4,6,14; FRS `FRS-P1-PRD-*`, `FRS-P1-UOM-*`, `FRS-P1-PRC-*`, `FRS-P1-CMB-*`; IMP Plan `P1-API-050` | product-domain API | Price Resolution/preview remain pending; follow-up FE-060/070 |
| P1-API-060 — Product Import endpoints | Gate 6 | HIGH | api | API Design §13; Contract §§4,6,11–12; FRS `FRS-P1-IMP-001..005`; IMP Plan `P1-API-060` | import API/report | follow-up FE-080 |
| P1-API-090 — API contract regression suite | P1-API-001..P1-API-060 | HIGH | api | API Design §§3–17; Contract §§3–6,18.3; IMP Plan `P1-API-090` | API contract evidence | closes Gate 7; follow-up Gate 8 |

## Gate 7A — Display Settings Core Foundation

`P1-DSP-001..005` are the mandatory presentation-only foundation before Gate 8.
They reuse the existing Settings integration and approved audit sink; they do
not add server USER/COMPANY scopes, a generic settings table, or business-time
authority.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-DSP-001 — Settings infrastructure inventory + canonical registry/context adapter | P1-ENV-001, P1-ORG-002 | MEDIUM | service-standard | FRS `FRS-P1-DSP-001`; DBD §3.8; IMP Plan §14A | Display Settings registry/context foundation | follow-up DSP-002 |
| P1-DSP-002 — Organization-path effective DisplaySettingsResolver | P1-DSP-001, P1-CTX-001 | HIGH | high-risk | FRS `FRS-P1-DSP-002`; Contract §3.6; IMP Plan §14A | effective DisplaySettings resolver | follow-up DSP-003 |
| P1-DSP-003 — Display Settings mutation + Authorization + audit integration | P1-DSP-002, P1-AUTH-002 | HIGH | backend-security | FRS `FRS-P1-DSP-003`; Contract §§3.6,10; IMP Plan §14A | authorized atomic mutation | follow-up DSP-004 |
| P1-DSP-004 — Display Settings API + OpenAPI + generated client | P1-DSP-003, P1-API-001, P1-API-002 | HIGH | api | API Design §6A; Contract §§3.6,4,6; IMP Plan §14A | Display Settings API/client | follow-up DSP-005, Gate 8 |
| P1-DSP-005 — Frontend display runtime store + formatter/local overlay | P1-DSP-004, P1-ENV-003 | HIGH | frontend | UI Design §4B.1–4B.4; IMP Plan §14A | runtime/formatter foundation | closes Gate 7A |
