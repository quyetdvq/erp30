# 16 — Báo cáo kiểm tra toàn vẹn & mức độ hoàn thiện bộ tài liệu P2

> **DinkERP P2 Documentation v1.0.0 · 2026-10-28**  
> Trạng thái: **đã hoàn tất trích xuất / thiết kế tài liệu để review**; **P2 implementation chưa có / QA runtime chưa chạy**. Đây là QA của **bộ tài liệu**, không phải nghiệm thu phần mềm P2.

## 1. Provenance

- Source of truth yêu cầu: `reference/00_User_Requirements_v1.0.15.md` (đính kèm nguyên văn), SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`.
- Codebase reference: `erp_v1.9.0.zip` (P1 CLOSED BASELINE), SHA-256 `464cfe340c4d1b8d8ada4357b48fb8e470d719985155b8371ba01a975dbdf626`.
- Trong source yêu cầu có 83 User Requirements (P1=40; P2=30; P3=13). **P2 có 30 UR, 29 MUST / 1 SHOULD, 147 bullet Acceptance Criteria**.
- Source code v1.9.0 gồm các module P1 và M11 Notifications; không có module backend P2 Business (Customer/Supplier/Purchase/Inventory/Sales/Finance) đã được triển khai trong ZIP.

## 2. Document completeness checks

| Mục kiểm tra | Target | Kết quả | Authority |
|---|---:|---|---|
| P2 UR gốc được copy nguyên văn, không chỉ lược ý | 30 | 30 | BASELINE-UR |
| Các đoạn P2 xuất hiện trong section phase, Pricing P1 extension, end-to-end flows | 3 | 3 | BASELINE source excerpts |
| P2 Acceptance Criteria được đánh số FR/test | 147 | 147 | BASELINE → ID derived |
| Đặc tả module P2 tự chứa UR gốc | 8 | 8 | P2-DRAFT design + BASELINE-UR |
| P2 database entities với fields/keys/FKs | 62 | 62 | P2-DRAFT |
| P2 API operation candidates | 187 | 187 | P2-DRAFT |
| P2 API path templates | 130 | 130 | P2-DRAFT |
| UI screen candidates | 31 | 31 | P2-DRAFT |
| OpenAPI 3.1 YAML schema references | 0 missing | 0 missing | Structural verification only |
| Test registry | 147 source AC + 42 negative | 189 | Tests NOT_RUN |
| Open decisions với gate/owner đề xuất | 35 | 35 OPEN | Chưa phê duyệt |
| Traceability 147 rows linking DB/API/UI/test | 147 | 147 | Full catalog cross-check |
| P1 / P3 riêng khỏi phạm vi P2 | yes | yes | UR phase boundary |

## 3. Phương pháp kiểm tra

- Parse sections theo heading `UR-*`, đọc `Target phase: P2`, xác nhận mỗi đoạn nguyên văn xuất hiện trong `docs/01_P2_USER_REQUIREMENTS_BASELINE.md` và spec module tương ứng.
- Extract riêng đoạn **P2 Operational ERP**, **P2 Extended Pricing Context** và bốn **P2 end-to-end flows** từ source (không dùng phần tóm tắt để giả làm verbatim). Đối chiếu SHA-256 nội dung.
- Từ mỗi acceptance bullet tạo một FR ID, một test ID; kiểm uniqueness và coverage không thiếu UR.
- Cross-check Proposed DB name / Proposed API route / Proposed UI screen trong từng dòng traceability với catalog thực tế trong gói.
- Parse OpenAPI 3.1 YAML, kiểm 187 operationIds, 130 paths, và tất cả local `$ref` schemas/components đều resolve. Bản YAML **chưa được contract lint/HTTP test** và có một số action-body placeholder cần quyết định.
- Kiểm tra internal Markdown links và SHA-256 của tất cả tệp bằng `tools/verify_p2_documentation.py`.
- Mọi `P2-AT-*` và `P2-NEG-*` hiện `NOT_RUN`. Chỉ có document integrity tests được chạy trong quá trình xây dựng gói.

## 4. Các giới hạn hiện hữu (không thể giả định hoàn thành)

1. `P2-DRAFT` về schema/API/UI/flows chưa được phê duyệt. User Requirements `BASELINE` không tự phê duyệt những chi tiết này.
2. Không có P2 executable code, migration đã triển khai, DB fixture runtime, provider API credentials/real callback, CI4 HTTP/E2E evidence trong gói.
3. Bản API YAML dùng các schema ứng viên; file upload multipart, provider webhook payload, các body action và precise validation/error contract phải được hoàn chỉnh sau quyết định ADR liên quan.
4. Các vấn đề mở có thể làm thay đổi schema/API trước code: P2 worker PBAC, Company/Shop mapping, purchase COMBO cost, promotion stacking/tax, inventory opening balance/serial, COD settlement/cardinality, Finance reversal/backdate, explicit P2 Import enablement.
5. Việc tương thích với Production DB MariaDB phải được kiểm trên DEV clone trước khi áp bất kỳ migration. Chưa có bằng chứng deploy Production cho P2.

## 5. Release/readiness verdict

- **P2 Requirements extraction:** COMPLETE / VERIFY.
- **P2 Downstream Design Draft:** COMPLETE FOR REVIEW, không phải APPROVED.
- **P2 Engineering Implementation:** NOT STARTED relative to v1.9.0.
- **P2 Functional UAT:** NOT RUN.
- **P2 go-live:** NOT READY, chặn ở G0/G1... đến khi ký duyệt thiết kế và có test bằng chứng.

Bộ tài liệu được bàn giao có thể sử dụng độc lập để review nghiệp vụ, thiết kế module, chia task, triển khai và bảo trì **sau khi được phê duyệt**. Nó không có quyền tự thay đổi P1 baseline hoặc thay thế source code/data/runtime proof.
