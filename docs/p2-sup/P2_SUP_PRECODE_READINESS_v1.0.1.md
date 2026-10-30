# DinkERP P2-SUP — Pre-code Readiness & Unresolved Technical Gates v1.0.1

> **Date:** 2026-10-29 · **Decision approval:** **12/12 Product decisions APPROVED** · **Source/code:** NO Supplier implementation has been made.
> **Purpose:** Không hỏi lại 12 quyết định đã chốt. Phân biệt **các chi tiết kỹ thuật chưa xác minh** với **những tính năng không được duyệt trong G1**, và xác định mốc nào chặn code hay release theo `docs/development-standards/03_MODULE_DELIVERY_GATES.md`.

## 1. Governance status

| Area | Status | Meaning |
|---|---|---|
| Supplier G1 scope / DEC-01…12 | **APPROVED** | PO chọn đủ 12, không có mục bị bác bỏ. |
| UR-SUP-001 (5 AC) | **BASELINE MUST** | Nghiệp vụ phải được chứng minh ở G1/Core và final PUR integration theo từng AC. |
| UR-SUP-002 (3 AC) | **BASELINE MUST, DEPENDENCY_DEFERRED** | Chỉ triển khai khi các module nguồn PUR/INV/FIN có dữ liệu/chứng từ thật. |
| MOD-G0 Product decision approval | **PASS (Product aspect)** | Không tự suy ra chữ ký của Tech Lead, Security/DBA. |
| MOD-G1 Schema/PBAC + MOD-G2 API/UI freeze | **PENDING TECHNICAL REVIEW** | Cần thiết kế chi tiết và xác minh tương thích thực tế trước khi viết code tương ứng. |
| MOD-G3..MOD-G7 | **NOT_RUN** | Source/SUP patch/migration/UAT chưa tồn tại. |

**No-Go rule:** product decisions approved ≠ approve undocumented cryptographic storage or privilege expansion. No plaintext bank fallback, no automatic grants, no mismatch to frozen P1 routes, no migration without DEV preflight/backup.

## 2. Remaining work — items **not yet technically signed off**

| Ref | Pending technical question / input | Proposed implementation under approved decisions (not a new business choice) | Owner / evidence to close | Stops |
|---|---|---|---|---|
| **PC-01** | Actual Git baseline and migration registry | Freeze project commit/ZIP SHA; compare working tree; `php spark migrate:status`. Current supplied ZIP has **000025 Customer Phone**; use next genuinely free ID. | Backend/DBA/operator: SHA, git status, DEV status, backup | **T01 migration** |
| **PC-02** | Exact Group/Company persisted relationships and SQL owner | Group MASTER identity from canonical root; Company Usage validates type ACTIVE/root; `supplier_group_code` = classification (STANDARD), not Group organization ID. Define FK/UNIQUE and DBPrefix in reviewed DDL. | Backend + DBA + Security: schema/ERD + authorization probes | **T01/T03** |
| **PC-03** | Bank at-rest crypto, secrets, **durable access audit** | One optional ciphertext payload, key outside DB/repo, authenticated encryption/masking, bank `view_bank/edit_bank`, explicit GET reveal and PUT clear, `Cache-Control: no-store`, CAS shared version. AES-256-GCM/OpenSSL is a **candidate**, not signed. Existing `configuration_audit_events` is configuration-specific: PROVE safe reuse or approve bounded security schema exception before T01 bank migration. Key outage must not disable bankless Supplier profile. | **Security Lead + DBA/Backend**: signed threat/crypto design + negative tests | **T01 bank columns, T04B and release** |
| **PC-04** | P1 Numbering mode/Series behavior for `supplier` | `CustomerNumberIssuer` allows manual with default `AUTO_IF_EMPTY`; SUP **must be stricter** per DEC-05: configured default (ACTIVE or INACTIVE) blocks manual fallback, valid default uses AUTO, no default allows validated manual; requested series cannot bypass broken default. GROUP only, `{RESOURCE}=supplier`, counter+insert atomic; optional default seeding opt-in. | Backend/Numbering owner: source review + sequence/negative tests | **T02/T04** |
| **PC-05** | Workflow resource binding and source of initial status | `initialState('supplier')` must exist, match VARCHAR20 and be preserved; no hardcoded ACTIVE. Default seed only for absent Supplier resource and never reset existing customized Lifecycle. P1 `transitionTarget` rejects branching same-action targets; specify 409 fail-closed or separately reviewed canonical extension. SetState requires expected_version. Purchase eligibility only ACTIVE; if no ACTIVE/INACTIVE graph, record PUR acceptance dependency explicitly. | Workflow owner + PO if local state graph is materially different; seed idempotence tests | **T02/T04/T05** |
| **PC-06** | Contact owner SUPPLIER in access/read/audit | Review `ContactRules/Scope/Sql/Service`, `supplier_contact` PBAC AND persisted parent Supplier VIEW, Contact parent batch/query and M8.7 audit compiler four-owner parity; keep frozen nine-resource audit catalog and P1 owner tests unchanged. | Backend/Security: source coverage matrix + pure/source negative regressions | **T06** |
| **PC-07** | Canonical Supplier OpenAPI wire formats and router ownership | Adopt **flat** `status`,`company_id`,`supplier_group_code`,`supplier_type` (Customer-compatible), `page_size` default 25/max 100; Vue ListQuery adapter maps filter object. Freeze DTOs for generic create/PATCH, SetState `{status,expected_version}`, Bank GET mask/`reveal=1`/PUT set-or-clear with expected_version and response `no-store`, stable error codes and route ownership. | Tech Lead + FE/API: OpenAPI diff, consumer tests, compiled routes | **T07/T08** |
| **PC-08** | Controlled catalog provider and i18n | STANDARD group and IMMEDIATE/NET15/NET30/NET60 approved; small read-only catalog may use reviewed backend constant/config + mirrored OpenAPI/TS enum and vi/en labels (matching CUS `CUSTOMER_GROUP`), or a lookup if G2 requires runtime updates. **Do not invent a new CRUD or lookup endpoint without contract review.** | Backend/Frontend: catalog metadata/lookup contract, tests | **T02/T08** |
| **PC-09** | Schema and performance readiness | Representative Supplier/usage/contact counts, Group-root FK, `UNIQUE(group_organization_id,supplier_code)`, `row_version`, `NOT NULL` required columns, charset/collation and 512-company cap, EXPLAIN/count/page/detail/lookup and query-count budget; no fabricated p95. | DBA/Backend + operator: DEV dataset and benchmark plan | **G4 / before release** |
| **PC-10** | Runtime UAT evidence and access roles | Operator frontend/Network: allowed/denied PBAC, DRAFT initial, branching transition DENY, duplicate phone ACCEPT, duplicate code REJECT, default AUTO_IF_EMPTY manual denial, bank deny/reveal/clear/no-store/audit, Company scope and version conflicts; negative privileges must exist on DEV. | Operator/QA: redacted screenshots and response evidence | **G5/G6/G7** |

### 2.1 Exact technical decisions not assumed to be Product-approved

These are **implementation details**, not additional functionality approvals. Engineers should record an ADR or review artifact (not silently invent): cipher/key rotation format; whether bank ciphertext metadata resides in existing Supplier columns and exact types; final Supplier Group root FK/UNIQUE DDL; Numbering rendering/series behavior; Supplier bootstrap Lifecycle IDs; JSON request/response shape for reveal and errors; numeric performance target for actual workload.

If a technical review discovers that the approved product rule cannot be safely implemented under the source, report a bounded conflict and change request. Do **not** treat the original choice as re-opened until such a conflict is demonstrated.

## 3. Source regression risks already known

1. **Customer phone hotfix:** last migration in supplied ZIP is 000025; SUP must not overwrite it, inherit its UNIQUE phone constraint, or copy CustomerPhone into Supplier as a uniqueness key.
2. **Customer Lifecycle fix:** do not restore the old `CUSTOMER_INITIAL_STATE_INVALID` behavior by hardcoding Supplier initial ACTIVE; test default DRAFT.
3. **Customer Type mismatch:** Customer enum uses `ORGANIZATION` while SUP-approved Type uses `COMPANY`.
4. **Shared Contact SQL:** with new Supplier owner, both runtime and read-only audit must supply the fourth owner scope. In the past, omission of Customer scopes caused 9 vs 7 parity; do not repeat.
5. **Workflow module cycles:** architecture cleanup separated cross-module calls; do not add a new dependency cycle Workflow→Supplier→Notifications.
6. **Frozen routes:** P1 has 180 registrations, of which 150 are frozen P1 operations. SUP additions need reviewed registry handling, not checksum edits to force PASS.

## 4. Recommended sequence to clear blockers without more PO questionnaires

1. **Engineering preflight / review only:** capture source SHA + DEV migration status; map Group/Company/Numbering/Workflow/Contacts, draft DDL and bank threat model; lock OpenAPI/FE contracts against current source. This is **SUP-T00**, not runtime code deployment.
2. **Technical approvals:** Security+DBA sign bank+schema; Tech+API owner sign Numbering, PBAC, Lifecycle and DTOs. Document any implementation deviation in a specific ADR with risk and test evidence.
3. **Start code in small reviewed patches:** migration/resource, SQL/service, Contact, bank, routes/OpenAPI, Vue; no database writes or UAT claims before operator-run steps.
4. **DEV/Operator UAT:** only after source/build PASS and explicit database migration approval; operator tests in frontend rather than assistant-run HTTP scripts. Mark each gate PASS/FAIL/NOT_RUN.

## 5. Open vs excluded items

**Open (technical verification; no new PO scope approval needed unless conflict):** PC-01..PC-10 above.

**Explicitly excluded from SUP-G1:** multiple banks/addresses, dedicated Supplier Group CRUD, Payment term engine, Notes, Supplier Portal, onboarding vendor approval, performance scorecards, purchase orders, vendor price lists, unified Customer–Supplier identity, historical transactions without real source. Do not categorize these excluded features as "pending" obligations for T01–T09.

**Backlog dependency, not a new G1 feature:** Supplier History remains `UR-SUP-002` and must be completed through PUR/INV/FIN work, preserving the 3 baseline AC.

## 6. Ready-to-code decision boundary

**Today:** `PO_SCOPE_APPROVED / ENGINEERING_PREFLIGHT_REQUIRED / SUP_CODE_NOT_STARTED`.

**Permit T01 after:** PC-01/02/03 DDL/crypto are closed and migration strategy documented. Permit T02/T04 when Numbering/Workflow (PC-04/05/08) is checked; T06 after PC-06; T07/T08 after PC-07; G4–G7 after PC-09/10. Do not require repeating the 12-choice Product questionnaire.

**Default operator instruction:** no `php spark migrate`, no policy grants, no bank secret pasted into reports, no production promotion based solely on documents.

## 7. Kết quả audit chéo v1.0.1 và ranh giới STOP/GO

**Chưa có blocker Product mới**: 12/12 DEC vẫn APPROVED. Những điều sau là engineering review chưa có bằng chứng, không được tự đánh dấu CLOSED:

1. **Before T01 migration:** PC-01/02: đúng source/DEV migrations và Group/Company DDL; PC-03: giải pháp ciphertext và **audit sink durable được Security/DBA chấp thuận**; PC-09: index/FK/collation/nullability. Mọi thay đổi schema ngoài hai bảng Core và bank metadata phải có ADR phạm vi bảo mật rõ.
2. **Before T02/T04:** PC-04: Supplier-specific default-series precedence (không copy CUS `AUTO_IF_EMPTY`), GROUP counters, optional opt-in Numbering seed; PC-05: initial Lifecycle, multi-target fail-closed, state column/transition/version semantics; PC-08: catalog source.
3. **Before T06:** PC-06: Shared Contacts four-owner predicate/parent VIEW, Contact audit plan; test denied IDs và old three owners.
4. **Before T07/T08:** PC-07: OpenAPI flat filters, 25 default/cap 100, exact SetState/CAS/Bank DTO, no-store, request-id, response/error mapping; FE generated clients and i18n.
5. **Before G7:** PC-09/10: actual MariaDB EXPLAIN, source regression, operator PBAC/Bank browser+Network UAT, Security signoff. No bank audit = no Supplier G1 release, dù profile không có bank vẫn tạo được.

**Bảo lưu nguyên tắc:** không tự thay đổi thông số kinh doanh đã được PO chốt, không bịa Supplier History khi chưa có PUR/FIN, không yêu cầu khởi động lại M19.25B để tiếp tục viết SUP. Trong trạng thái hiện tại: `PO_APPROVED / DESIGN_RECONCILED / TECHNICAL_PREFLIGHT_PENDING / CODE_NOT_STARTED`.
