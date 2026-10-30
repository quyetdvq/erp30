# P2-SUP — Current Approved Design Entry Point

**Phiên bản thiết kế:** **v1.0.1** (2026-10-29; 12/12 SUP-DEC-01..12 APPROVED, không thay đổi). **Trạng thái triển khai tại snapshot 2026-10-30:** SUP-T01..T05 + SUP-T06A backend đạt SOURCE/OFFLINE PASS theo tài liệu QA và kết quả operator; SUP-T06B1 frontend owner-policy + lint fix đã đạt Windows offline/type/lint/build PASS; SUP-T06B2 chỉ là hợp đồng OpenAPI **review candidate**, chưa được MOD-G2/PC-07 phê duyệt và chưa có Supplier Contact UI. **MOD-G1/G2/G4-G7, Security/DBA, DEV HTTP/MariaDB và release vẫn PENDING/NOT_RUN.** Những dòng `NOT_IMPLEMENTED` trong các bản thiết kế cũ là lịch sử tại thời điểm biên soạn, không phải bằng chứng cần triển khai lại task.

## Tài liệu và bằng chứng triển khai SUP hiện tại

1. [Decision Register v1.0.1](P2_SUP_DESIGN_DECISIONS_v1.0.1.md): 12/12 quyết định đã duyệt và những diễn giải kỹ thuật nhất quán.
2. [Technical Design v1.0.1](P2_SUP_TECHNICAL_DESIGN_v1.0.1.md): dữ liệu, Group/Company, Numbering, Workflow, secure bank, Contacts, SQL-first, API/UI, 8 AC.
3. [Implementation Plan v1.0.1](P2_SUP_IMPLEMENTATION_PLAN_v1.0.1.md): T00..T11, kiểm thử/gate và phần PUR/FIN phụ thuộc.
4. [Pre-code Readiness v1.0.1](P2_SUP_PRECODE_READINESS_v1.0.1.md): PC-01..PC-10 chưa nghiệm thu và điều kiện START/STOP.
5. [Consistency Audit v1.0.1](P2_SUP_DOC_CONSISTENCY_AUDIT_v1.0.1.md): phát hiện, nguồn đối chiếu, quyết định xử lý và giới hạn xác minh.

**Thứ tự nguồn thẩm quyền:** `docs/DinkERP_P2/modules/P2_SUP_INDEPENDENT_MODULE_SPEC.md` §1 (UR/AC) → quyết định SUP v1.0.1 → `docs/development-standards/` → kỹ thuật được review/OpenAPI MOD-G2 → code. Nội dung dự thảo P2 cũ không tự thay các quyết định đã duyệt. Không sửa 8 AC hoặc các route P1 frozen. `README_v1.0.md` và `*_v1.0.md` là lịch sử không được dùng làm handoff kỹ thuật mới.

**Baseline source hiện tại:** `erp_v2.1.0.zip`, SHA-256 `3de9f21c303a76f2103d5e2ab335f391626115937c5ceb6baf5dc5656b76589b`, tiếp nối bằng incremental patch SUP-T06A, SUP-T06B1, Vitest typed-mock lint fix và T06B2 review-only package (không đổi canonical OpenAPI). `erp_v2.0.0_customer_required_phone_fixed.zip` là baseline của tài liệu thiết kế ngày 2026-10-29, không phải source triển khai mới nhất. DEV clone và migration registry phải xác minh trước migration.

**Bằng chứng source:** [T06A Backend QA](SUP_T06_SHARED_CONTACT_BACKEND_SOURCE_QA_2026-10-30.md) · [T06B1 FE owner preflight QA](SUP_T06B1_CONTACT_FE_OWNER_PREP_QA_2026-10-30.md) · [T06B2 Contact OpenAPI review](SUP_T06B2_CONTACT_OPENAPI_REVIEW_2026-10-30.md). Các hồ sơ T01–T05 ở cùng thư mục.

**Trạng thái:** `PO_SCOPE_APPROVED / SUP_T06A_OFFLINE_PASS / SUP_T06B1_WINDOWS_FE_OFFLINE_PASS / SUP_T06B2_REVIEW_CANDIDATE_ONLY / PC06_PC07_REVIEW_PENDING / DEV_UAT_NOT_RUN / RELEASE_BLOCKED`.
