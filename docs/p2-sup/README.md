# P2-SUP — Current Approved Design Entry Point

**Phiên bản tài liệu hiện hành:** **v1.0.1** (2026-10-29, đính chính kỹ thuật cho v1.0.0). **PO approvals:** SUP-DEC-01..12 **APPROVED**; nội dung 12 quyết định không thay đổi. **Code SUP:** NOT_STARTED; MOD-G1..G7 chưa PASS. Bản v1.0.0 trước đây được giữ như lịch sử.

## Tài liệu hiệu lực để bắt đầu SUP-T00

1. [Decision Register v1.0.1](P2_SUP_DESIGN_DECISIONS_v1.0.1.md): 12/12 quyết định đã duyệt và những diễn giải kỹ thuật nhất quán.
2. [Technical Design v1.0.1](P2_SUP_TECHNICAL_DESIGN_v1.0.1.md): dữ liệu, Group/Company, Numbering, Workflow, secure bank, Contacts, SQL-first, API/UI, 8 AC.
3. [Implementation Plan v1.0.1](P2_SUP_IMPLEMENTATION_PLAN_v1.0.1.md): T00..T11, kiểm thử/gate và phần PUR/FIN phụ thuộc.
4. [Pre-code Readiness v1.0.1](P2_SUP_PRECODE_READINESS_v1.0.1.md): PC-01..PC-10 chưa nghiệm thu và điều kiện START/STOP.
5. [Consistency Audit v1.0.1](P2_SUP_DOC_CONSISTENCY_AUDIT_v1.0.1.md): phát hiện, nguồn đối chiếu, quyết định xử lý và giới hạn xác minh.

**Thứ tự nguồn thẩm quyền:** `docs/DinkERP_P2/modules/P2_SUP_INDEPENDENT_MODULE_SPEC.md` §1 (UR/AC) → quyết định SUP v1.0.1 → `docs/development-standards/` → kỹ thuật được review/OpenAPI MOD-G2 → code. Nội dung dự thảo P2 cũ không tự thay các quyết định đã duyệt. Không sửa 8 AC hoặc các route P1 frozen. `README_v1.0.md` và `*_v1.0.md` là lịch sử không được dùng làm handoff kỹ thuật mới.

**Baseline thực tế:** `erp_v2.0.0_customer_required_phone_fixed.zip`, SHA-256 `9af6a01ec451400c8d3cd580396b1e6d3b294c0073b4c0675532a9752bb4aa28`; App migration `000025` đã có trong ZIP. DEV clone/working tree phải xác minh lại trước migration.

**Trạng thái:** `PO_SCOPE_APPROVED / DOCS_AUDITED / ENGINEERING_PREFLIGHT_PENDING / NO_CODE_CHANGE`.
