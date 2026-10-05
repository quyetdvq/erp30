# Gate 2 Task Registry

Pointers only. Resolve organization context from persisted/canonical targets;
do not treat client context as authoritative.

| Task | Depends | Risk | Profile | Authority IDs / headings | Primary output | Parallel / follow-up |
|---|---|---|---|---|---|---|
| P1-ORG-001 — OrganizationRepository | P1-DB-020 | MEDIUM | service-standard | FRS `FRS-P1-ORG-001..005`; DBD Organization domain; IMP Plan `P1-ORG-001` | organization persistence adapter | parallel CON-001/EMP-001/002 after schema |
| P1-ORG-002 — OrganizationService | P1-ORG-001, P1-INF-004 | HIGH | high-risk | FRS `FRS-P1-ORG-001..005`, `FRS-P1-SVC-001`; Contract context/lock matrix; IMP Plan `P1-ORG-002` | hierarchy/context invariants | follow-up CTX-001/002; API-010 |
| P1-CON-001 — ContactRepository/Service | P1-DB-020 | MEDIUM | service-standard | FRS `FRS-P1-CON-001..002`; DBD Contact; API/UI Contact sections; IMP Plan `P1-CON-001` | contact CRUD/service | parallel ORG-001 |
| P1-EMP-001 — EmployeeRepository + manager invariant | P1-DB-030, P1-INF-004 | HIGH | high-risk | FRS `FRS-P1-EMP-003`, `FRS-P1-SVC-002`; DBD Employee; IMP Plan `P1-EMP-001` | employee persistence + manager rule | parallel EMP-002 after schema |
| P1-EMP-002 — EmployeeAssignmentRepository | P1-DB-030 | MEDIUM | service-standard | FRS `FRS-P1-EMP-004..005`; DBD Assignment; IMP Plan `P1-EMP-002` | assignment persistence adapter | follow-up EMP-003 |
| P1-EMP-003 — EmployeeAssignmentService | P1-EMP-002, P1-ORG-002, P1-INF-003, P1-INF-004 | HIGH | high-risk | FRS `FRS-P1-EMP-004..005`, `FRS-P1-SVC-002`; Contract assignment context/lock; IMP Plan `P1-EMP-003` | assignment/date/primary invariants | follow-up CTX-001; AUTH-002 |
| P1-CTX-001 — Authoritative Evaluation Organization resolver | P1-ORG-002, P1-EMP-003 | HIGH | high-risk | FRS `FRS-P1-EMP-005`, `FRS-P1-PBAC-002..004`; Contract §5 Evaluation Organization; IMP Plan `P1-CTX-001` | authoritative context resolver | follow-up AUTH-002, NUM-003 |
| P1-CTX-002 — Company owner resolver | P1-ORG-002 | MEDIUM | service-standard | FRS `FRS-P1-ORG-003..005`; Contract §5 Company resolution; IMP Plan `P1-CTX-002` | Company ancestor resolver | parallel CTX-001; follow-up API-010 |
