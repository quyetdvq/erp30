# Gate 8 Task Registry

Pointers only. UI permission filtering is UX only; backend authorization and
canonical keys remain authoritative.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-FE-001 — App shell/router/session/error boundary | P1-API-001, P1-API-002, Gate 7A | MEDIUM | frontend | UI Design §§2–6 shell/router/session; API Design auth/error; IMP Plan `P1-FE-001` | approved app shell | follow-up FE-002 |
| P1-FE-002 — Typed API integration + common query state | P1-API-002, Gate 7A | MEDIUM | frontend | UI Design §§7–9 data/query state; API Design typed client; IMP Plan `P1-FE-002` | typed API/query layer | follow-up DSP-006 |
| P1-DSP-006 — Display Settings administration + My Display Preferences UI | P1-DSP-004, P1-DSP-005, P1-FE-001, P1-FE-002 | HIGH | frontend | UI Design §4B.5–4B.8; IMP Plan §15 Early Gate 8 | Display Settings UI checkpoint | follow-up DSP-090 |
| P1-DSP-090 — Display Settings regression + Playwright/contract closure | P1-DSP-001..P1-DSP-006 | HIGH | cross-layer | API Design §6A; UI Design §4B.9; IMP Plan §15 checkpoint | Display Settings closure evidence | unlocks FE-003 and feature UI |
| P1-FE-003 — Common table/filter/pagination/export components | P1-FE-002, P1-API-003, P1-DSP-090 | MEDIUM | frontend | UI Design table/query sections; FRS `FRS-P1-GEN-006`; IMP Plan `P1-FE-003` | shared list primitives | parallel FE-010/020 after DSP-090 |
| P1-FE-010 — Location/Organization/Contact UI | P1-API-010 | MEDIUM | frontend | UI Design Location/Organization/Contact sections; FRS `LOC-*`, `ORG-*`, `CON-*`; IMP Plan `P1-FE-010` | Slice 1 UI | parallel FE-020 |
| P1-FE-020 — Employee/Assignment UI | P1-API-020 | HIGH | frontend | UI Design Employee/Assignment sections; FRS `EMP-*`; IMP Plan `P1-FE-020` | Slice 2 UI | parallel FE-010; follow-up FE-090 |
| P1-FE-030 — Authorization Group/Resource/Policy UI | P1-API-030 | HIGH | frontend | UI Design IAM/PBAC sections; FRS `IAM-*`, `PBAC-*`; IMP Plan `P1-FE-030` | security admin UI | parallel FE-040 |
| P1-FE-040 — Workflow/Lifecycle UI | P1-API-030 | MEDIUM | frontend | UI Design Workflow/Lifecycle sections; FRS `WF-*`; IMP Plan `P1-FE-040` | lifecycle UI | parallel FE-030 |
| P1-FE-050 — Auto Numbering configuration UI | P1-API-040 | HIGH | frontend | UI Design Auto Numbering section; FRS `NUM-*`; IMP Plan `P1-FE-050` | numbering configuration UI | follow-up FE-090 |
| P1-FE-060 — Product/UOM UI | P1-API-050 | HIGH | frontend | UI Design Product/UOM sections; FRS `PRD-*`, `UOM-*`; IMP Plan `P1-FE-060` | product/UOM UI | parallel FE-070 |
| P1-FE-070 — Pricing/COMBO UI | P1-API-050 | HIGH | frontend | UI Design Pricing/COMBO sections; FRS `PRC-*`, `CMB-*`; IMP Plan `P1-FE-070` | pricing/COMBO UI | parallel FE-060 |
| P1-FE-080 — Product Import UI/report | P1-API-060 | HIGH | frontend | UI Design `UI-P1-IMP-001..003`; FRS `IMP-*`; IMP Plan `P1-FE-080` | import wizard/report UI | follow-up FE-090 |
| P1-FE-090 — Accessibility + critical Playwright flows | P1-FE-010..P1-FE-080 | HIGH | frontend | UI Design §21 accessibility/critical flows; IMP Plan `P1-FE-090` | critical UI/accessibility evidence | closes Gate 8; follow-up Gate 9 |
