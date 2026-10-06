# Gate 3 Task Registry

Pointers only. Read PBAC, identity, and lifecycle headings before changing
security behavior; HIGH tasks require the deep negative-path profile.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-IAM-001 — Shield identity/group/direct-permission adapter | P1-SEED-001 | HIGH | high-risk | FRS `FRS-P1-IAM-001..004`; DBD Shield/Resource; Contract §6 identity; IMP Plan `P1-IAM-001` | Shield integration adapter | parallel IAM-002/AUTH-001 after seed |
| P1-IAM-002 — EffectiveProfile resolver | P1-IAM-001 | HIGH | high-risk | FRS `FRS-P1-IAM-004`; FRS `FRS-P1-PBAC-002..004`; Contract §6 effective profile; IMP Plan `P1-IAM-002` | effective profile resolution | follow-up AUTH-002 |
| P1-AUTH-001 — PolicyRepository + participant validation | P1-DB-050 | HIGH | high-risk | FRS `FRS-P1-PBAC-001..003`; DBD Policy; Contract §6 participant validation; IMP Plan `P1-AUTH-001` | policy persistence/validation | parallel IAM-001; follow-up AUTH-002 |
| P1-AUTH-002 — AuthorizationService core | P1-IAM-002, P1-AUTH-001, P1-EMP-003, P1-CTX-001 | HIGH | high-risk | FRS `FRS-P1-PBAC-001..007`; Contract §6 canonical evaluation; IMP Plan `P1-AUTH-002` | PBAC decision engine | follow-up AUTH-003..005, WF-002, API-001 |
| P1-AUTH-003 — Authorization decision evidence/reason codes | P1-AUTH-002 | HIGH | high-risk | FRS `FRS-P1-PBAC-005`; Contract §6 explainability; IMP Plan `P1-AUTH-003` | decision evidence/reasons | parallel AUTH-004/005 |
| P1-AUTH-004 — Authorization audit persistence | P1-AUTH-002 | HIGH | high-risk | FRS `FRS-P1-PBAC-006`; DBD authorization_logs; Contract §6 audit; IMP Plan `P1-AUTH-004` | authorization audit records | parallel AUTH-003/005 |
| P1-AUTH-005 — Security administration services | P1-AUTH-002 | HIGH | high-risk | FRS `FRS-P1-IAM-003`, `FRS-P1-PBAC-001`; Contract §6 admin mutation; IMP Plan `P1-AUTH-005` | group/policy/security admin services | parallel AUTH-003/004 |
| P1-WF-001 — LifecycleRepository | P1-DB-040 | MEDIUM | service-standard | FRS `FRS-P1-WF-001..003`; DBD Lifecycle; IMP Plan `P1-WF-001` | lifecycle persistence adapter | parallel IAM/AUTH work |
| P1-WF-002 — LifecycleService | P1-WF-001, P1-AUTH-002 | HIGH | high-risk | FRS `FRS-P1-WF-002..005`; Contract §13 lifecycle; IMP Plan `P1-WF-002` | authorized deterministic transitions | follow-up API-030/FE-040 |
| P1-AUTH-090 — Mandatory authorization regression suite | P1-AUTH-002..P1-AUTH-005 | HIGH | high-risk | FRS `FRS-P1-PBAC-001..007`; Contract §§6,18 mandatory tests; IMP Plan `P1-AUTH-090` | PBAC regression evidence | closes Gate 3; follow-up Gate 4 |
