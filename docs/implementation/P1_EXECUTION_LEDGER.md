# P1 Execution Ledger

This ledger is lightweight operational evidence. It is not architecture
authority and does not replace UR, FRS, DBD, the Implementation Contract, the
Implementation Plan, API Design, or UI Design.

As of the P1-AUTH-003 task commit, the registry contains exactly 100 P1 tasks.
Repository evidence verifies 37 completed tasks: Gate 0 is 10/10, Gate 1 is
14/14, Gate 2 is 8/8, and Gate 3 has opened at 5/10. The previously supplied 28/100 and 4/8 checkpoint is
stale: the accepted `c4049f0` P1-ORG-002 commit is present in current history.
No task is downgraded to force that stale count.

## Overall Progress

| Measure | Progress |
|---|---:|
| P1 tasks DONE | 37/100 |
| Gate 0 | 10/10 DONE |
| Gate 1 | 14/14 DONE |
| Gate 2 | 8/8 DONE |
| Gate 3 | 5/10 DONE |
| Gate 4 | 0/7 DONE |
| Gate 5 | 0/11 DONE |
| Gate 6 | 0/9 DONE |
| Gate 7 | 0/10 DONE |
| Gate 8 | 0/12 DONE |
| Gate 9 | 0/9 DONE |

## Task Ledger

| Task ID | Status | Commit(s) | Validation/Profile | Note |
|---|---|---|---|---|
| P1-ENV-001 | DONE | d8cd9c3 | docs | Gate 0 assessment and consolidation evidence. |
| P1-ENV-002 | DONE | d8cd9c3 | service-standard | Gate 0 final assessment records backend quality PASS. |
| P1-ENV-003 | DONE | d8cd9c3 | frontend | Gate 0 final assessment records frontend gates PASS. |
| P1-ENV-004 | DONE | d8cd9c3 | db-migration | Assessment 10 and Gate 0 final evidence. |
| P1-INF-001 | DONE | d8cd9c3 | service-standard | Gate 0 final assessment records Clock/Evaluation Time PASS. |
| P1-INF-002 | DONE | d8cd9c3 | api | Gate 0 final assessment records request/correlation ID PASS. |
| P1-INF-003 | DONE | d8cd9c3 | service-standard | Gate 0 final assessment records Settings/timezone PASS. |
| P1-INF-004 | DONE | d8cd9c3 | high-risk | Gate 0 final assessment records DomainLock PASS. |
| P1-INF-005 | DONE | d8cd9c3 | high-risk | Gate 0 final assessment records Decimal readiness PASS. |
| P1-INF-006 | DONE | d8cd9c3 | api | Gate 0 final assessment records locale foundation PASS. |
| P1-DB-001 | DONE | 1aee09a, 2af84e3 | db-migration | Migration inventory and consolidation checkpoint. |
| P1-DB-010 | DONE | 35233ae | db-migration | Location schema implementation. |
| P1-DB-020 | DONE | 91cddf3 | db-migration | Organization/contact schema implementation. |
| P1-DB-030 | DONE | f822f5c | db-migration | Employee structure schema implementation. |
| P1-DB-040 | DONE | 47337c8 | db-migration | Workflow/lifecycle schema implementation. |
| P1-DB-050 | DONE | fb15731 | db-migration | Resource/auth/numbering schema implementation. |
| P1-DB-060 | DONE | 067a4ab | db-migration | Product/UOM/COMBO schema implementation. |
| P1-DB-070 | DONE | 9a60986 | db-migration | Pricing schema implementation. |
| P1-DB-080 | DONE | f02e8d1 | db-migration | Import schema implementation. |
| P1-SEED-001 | DONE | 72c8d92 | seed | Resource/action seed implementation. |
| P1-SEED-002 | DONE | 545f629 | seed | Workflow state seed implementation. |
| P1-SEED-003 | DONE | a833971 | seed | Privileged group bootstrap implementation. |
| P1-SEED-004 | DONE | f5a2c45, 6caac68 | seed | Group root bootstrap and corrective serialization. |
| P1-DB-090 | DONE | 4d6a59b | db-migration | Fresh/forward migration verification. |
| P1-ORG-001 | DONE | 6b40a5e | service-standard | Organization repository implementation. |
| P1-ORG-002 | DONE | c4049f0 | high-risk | Organization service hierarchy/context invariants. |
| P1-CON-001 | DONE | 9c895e2 | service-standard | Contact repository/service implementation. |
| P1-EMP-001 | DONE | cafc23e, 2ccfb27, 838fa34 | high-risk | Implementation plus stable-code/localization corrections. |
| P1-EMP-002 | DONE | 876c158 | service-standard | Employee assignment repository implementation. |
| P1-EMP-003 | DONE | de71390, 1d95f5e | high-risk | Corrective real concurrent Primary Assignment regression: two independent connections, one commit, overlap failure, and final-state proof. |
| P1-CTX-001 | DONE | 45b0184 | high-risk | Authoritative Evaluation Organization resolver and HIGH-risk assessment evidence. |
| P1-CTX-002 | DONE | efdca4f | service-standard | Company owner resolver delegates to canonical OrganizationService hierarchy resolution; focused and backend quality gates pass. |
| P1-IAM-001 | DONE | a63781c | high-risk | Shield identity/group/direct-permission adapter; active legacy privileged/public PBAC entrypoints removed. Boundary deviation (preflight required STOP/BLOCK on bypass) is recorded in the assessment; removal is accepted as security containment because these were unapproved legacy routes and Shield auth remains enabled. |
| P1-IAM-002 | DONE | 1a6895e | high-risk | EffectiveProfile runtime union of active group permissions and valid direct additions; no PBAC/context/profile persistence. |
| P1-AUTH-001 | DONE | ba0557a | high-risk | PolicyRepository, Policy Action primitives, canonical participant/resource validation, atomic mutation, and HIGH-risk evidence. |
| P1-AUTH-002 | DONE | 1e3b70d | high-risk | AuthorizationService core: canonical context/path, PBAC lanes/QT1, absolute-deny propagation, denied-set intersection, and explainable non-persisted decision object. Evidence: `docs/implementation/evidence/P1-AUTH-002/01_authorization_service_assessment.md`. |
| P1-AUTH-003 | DONE | 6803ad3 | high-risk | Deterministic internal AuthorizationDecision reason semantics, matched/decisive policy evidence, Base Permission source, assignment/request/time evidence. Evidence: `docs/implementation/evidence/P1-AUTH-003/01_decision_evidence_assessment.md`. |
| P1-AUTH-004 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-AUTH-005 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-WF-001 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-WF-002 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-AUTH-090 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-NUM-001 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-NUM-002 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-NUM-003 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-NUM-004 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-NUM-005 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-NUM-006 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-NUM-090 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-PRD-001 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-PRD-002 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-PRD-003 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-PRD-004 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-UOM-001 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-PRC-001 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-PRC-002 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-CMB-001 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-CMB-002 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-CMB-003 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-PRD-090 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-IMP-001 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-IMP-002 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-IMP-003 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-IMP-004 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-IMP-005 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-IMP-006 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-IMP-007 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-IMP-008 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-IMP-090 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-API-001 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-API-002 | NOT_STARTED | — | high-risk | Gate 0 artifact exists, but this task has no accepted task commit. |
| P1-API-003 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-API-010 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-API-020 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-API-030 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-API-040 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-API-050 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-API-060 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-API-090 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-FE-001 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-FE-002 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-FE-003 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-FE-010 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-FE-020 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-FE-030 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-FE-040 | NOT_STARTED | — | service-standard | No accepted implementation evidence. |
| P1-FE-050 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-FE-060 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-FE-070 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-FE-080 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-FE-090 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-SYS-001 | NOT_STARTED | — | release | No accepted implementation evidence. |
| P1-SYS-002 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-SYS-003 | NOT_STARTED | — | high-risk | No accepted implementation evidence. |
| P1-SYS-004 | NOT_STARTED | — | release | No accepted implementation evidence. |
| P1-SYS-005 | NOT_STARTED | — | release | No accepted implementation evidence. |
| P1-SYS-006 | NOT_STARTED | — | release | No accepted implementation evidence. |
| P1-SYS-007 | NOT_STARTED | — | release | No accepted implementation evidence. |
| P1-UAT-001 | NOT_STARTED | — | release | No accepted implementation evidence. |
| P1-RC-001 | NOT_STARTED | — | release | No accepted implementation evidence. |

## Corrective / Operational Work

Corrective tasks in this section are not part of the 100-task P1 registry and
do not change the `29/100` completion count.

| Corrective ID | Status | Commit | Profile | Note |
|---|---|---|---|---|
| HARDEN-P1-001 | DONE | see commit subject | high-risk | Backend operation context, audit stamping, repository failure semantics, Contact merged-state validation, typed concurrency, timezone resolution, coding contract, and architecture guard. |
