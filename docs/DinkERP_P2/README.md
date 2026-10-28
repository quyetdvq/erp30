# DinkERP P2 — Bộ tài liệu độc lập v1.0.0

**Ngày phát hành tài liệu:** 2026-10-28  
**Base source:** `erp_v1.9.0.zip` — **P1 CLOSED BASELINE** (source SHA-256 `464cfe340c4d1b8d8ada4357b48fb8e470d719985155b8371ba01a975dbdf626`).  
**Normative requirement:** `00_User_Requirements_v1.0.15.md` — SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`.  
**P2 scope:** 30 UR / 147 Acceptance Criteria, 29 MUST + 1 SHOULD.  
**Implementation status:** P2 technical design is **DRAFT / NOT IMPLEMENTED / NOT RUN**; User Requirements themselves are BASELINE.

## Độc lập nghĩa là gì?

- Tài liệu **không cần hồ sơ P1 khác để hiểu phạm vi và nghiệm thu P2**: có nguyên văn 30 UR P2, các đoạn P2 rải trong phần khác, tóm tắt đầy đủ invariant P1 áp dụng, cùng FRS, kiến trúc, DB, API, UI, security, integration, kế hoạch, QA và runbook.
- **8 file module độc lập** chép nguyên văn UR thuộc module đó, sau đó cung cấp riêng bảng/API/UI/AC/test và quyết định mở.
- `reference/00_User_Requirements_v1.0.15.md` là bản gốc để truy nguyên, không cần truy cập ngoài ZIP.
- Đặc tả P2 **không phải** bytes của mã nguồn P2 vì P2 chưa có mã nguồn trong P1 v1.9.0. Việc triển khai cần codebase P1 gốc, database/credentials/dependencies thật và phê duyệt thiết kế.

## Nên bắt đầu tại đây

1. **[P2 master handbook](P2_MASTER_HANDBOOK_FULL.md)** — tất cả chương kỹ thuật trong một file (không phụ thuộc đường dẫn tới file khác để đọc).
2. **[User Requirements P2](docs/01_P2_USER_REQUIREMENTS_BASELINE.md)** — 30 UR giữ nguyên; 147 AC; P2 cross-phase sections nguyên văn.
3. **[P1 contract](docs/02_P2_SCOPE_AND_P1_CONTRACT.md)** — Company/PBAC/COMBO/Pricing/Workflow/Numbering/Import invariant và giới hạn.
4. **[FRS P2](docs/03_P2_FUNCTIONAL_REQUIREMENTS.md)** — 147 FR/test IDs.
5. **[Business flows & Lifecycle](docs/04_P2_BUSINESS_FLOWS_AND_LIFECYCLES.md)**.
6. **[Data Design](docs/05_P2_DATABASE_DESIGN.md)** — 62 proposed entities.
7. **[API Design](docs/06_P2_API_DESIGN.md)** — 187 proposed operations, [draft OpenAPI](contracts/openapi/p2.draft.openapi.yaml).
8. **[UI/UX](docs/07_P2_UI_UX_DESIGN.md)** — 31 proposed screens.
9. **[PBAC & Security](docs/08_P2_AUTHORIZATION_AND_AUDIT.md)**.
10. **[Integrations & Idempotency](docs/09_P2_INTEGRATIONS_AND_IDEMPOTENCY.md)**.
11. **[Implementation Contract](docs/10_P2_IMPLEMENTATION_CONTRACT.md)**.
12. **[Delivery Plan](docs/11_P2_DELIVERY_PLAN_AND_GATES.md)**.
13. **[Testing/UAT](docs/12_P2_TEST_STRATEGY_AND_UAT.md)** and [147-item printable checklist](qa/P2_UAT_CHECKLIST_147_CRITERIA.md).
14. **[Runbook](docs/13_P2_OPERATIONS_AND_MAINTENANCE_RUNBOOK.md)**.
15. **[Source gaps/open decisions](docs/14_P2_GAPS_AND_OPEN_DECISIONS.md)** — 35 OPEN items.
16. **[Traceability](docs/15_P2_TRACEABILITY_MATRIX.md)**.
17. **[Documentation integrity report](docs/16_P2_DOCUMENTATION_INTEGRITY_REPORT.md)**.

## 8 tài liệu module độc lập

| Module | File | UR IDs |
|---|---|---|
| **DOC** | [`P2_DOC_INDEPENDENT_MODULE_SPEC.md`](modules/P2_DOC_INDEPENDENT_MODULE_SPEC.md) | `UR-DOC-001`, `UR-DOC-002` |
| **CUS** | [`P2_CUS_INDEPENDENT_MODULE_SPEC.md`](modules/P2_CUS_INDEPENDENT_MODULE_SPEC.md) | `UR-CUS-001`, `UR-CUS-002` |
| **PRC** | [`P2_PRC_INDEPENDENT_MODULE_SPEC.md`](modules/P2_PRC_INDEPENDENT_MODULE_SPEC.md) | `UR-PRC-002` |
| **SUP** | [`P2_SUP_INDEPENDENT_MODULE_SPEC.md`](modules/P2_SUP_INDEPENDENT_MODULE_SPEC.md) | `UR-SUP-001`, `UR-SUP-002` |
| **PUR** | [`P2_PUR_INDEPENDENT_MODULE_SPEC.md`](modules/P2_PUR_INDEPENDENT_MODULE_SPEC.md) | `UR-PUR-001`, `UR-PUR-002`, `UR-PUR-003`, `UR-PUR-004` |
| **INV** | [`P2_INV_INDEPENDENT_MODULE_SPEC.md`](modules/P2_INV_INDEPENDENT_MODULE_SPEC.md) | `UR-INV-001`, `UR-INV-002`, `UR-INV-003`, `UR-INV-004`, `UR-INV-005`, `UR-INV-006` |
| **SAL** | [`P2_SAL_INDEPENDENT_MODULE_SPEC.md`](modules/P2_SAL_INDEPENDENT_MODULE_SPEC.md) | `UR-SAL-001`, `UR-SAL-002`, `UR-SAL-003`, `UR-SAL-004`, `UR-SAL-005`, `UR-SAL-006`, `UR-SAL-007`, `UR-SAL-008` |
| **FIN** | [`P2_FIN_INDEPENDENT_MODULE_SPEC.md`](modules/P2_FIN_INDEPENDENT_MODULE_SPEC.md) | `UR-FIN-001`, `UR-FIN-002`, `UR-FIN-003`, `UR-FIN-004`, `UR-FIN-005` |

## Reference files máy đọc được

- `reference/P2_REQUIREMENTS_AND_ACCEPTANCE.csv`: source UR → FR → Acceptance → Test.
- `reference/P2_END_TO_END_TRACEABILITY.csv`: full 147 rows → proposed table/API/screen/test/status.
- `reference/P2_DATABASE_ENTITY_CATALOG.csv` & `.json`: 62 candidate tables/fields/indexes/FKs.
- `reference/P2_API_OPERATION_CATALOG.csv`: 187 candidate operations; OpenAPI 3.1 `contracts/openapi/p2.draft.openapi.yaml`.
- `reference/P2_UI_SCREEN_CATALOG.csv`: 31 candidate screens.
- `reference/P2_TEST_CASE_REGISTRY.csv`: 147 AC tests + 42 cross-cutting negatives (`NOT_RUN`).
- `reference/P2_OPEN_DECISIONS.csv`: 35 decisions `OPEN`; approval stage specified.
- `reference/P2_EXACT_CROSS_SECTION_PROVENANCE.json`: exact original P2-related cross-sections.
- `reference/BASELINE_METADATA.json`: input provenance with SHA-256.
- `reference/P2_PACKAGE_MANIFEST.json`: SHA-256 all package files (except itself).

## Quy tắc thay đổi / phối hợp

- `BASELINE-UR`: bắt buộc giữ đúng nội dung đã khóa. Muốn thay đổi tạo Change ID, cập nhật requirement version + traceability.
- `P2-DRAFT`: chưa phê duyệt; phải chốt 35 Open Decisions phù hợp với gate trước viết migrations/API/UI/worker.
- **Ưu tiên:** security+Company ownership+inventory post/reserve/idempotency/COD money; không bypass PBAC bằng service identity, không tạo stock từ combo hoặc finance posting từ COD status.
- CI4 migrations P1 000001..000022 không sửa; P2 dùng forward-only additive migration sau preflight database. Production DB không được giả định trùng clone.
- Release chỉ PASS khi có test runtime evidence (DB/HTTP/E2E) cho 147 criteria, không phải chỉ có một bản tài liệu đẹp.

## Chạy kiểm chứng tài liệu

```bash
python3 tools/verify_p2_documentation.py
```

Tool Python không cần internet/DB; dùng thư viện chuẩn để so URI/UR/FR/test/catalog và SHA-256. Kiểm chứng này **không chạy application test suite**.
