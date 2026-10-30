# HIS-03 — Compact changes_json & canonical object modal

**Ngày:** 2026-10-29  · **Trạng thái:** triển khai SOURCE/OFFLINE, DEV UAT chưa chạy.

## 1. Phạm vi được chốt

- **HIS-POL-002**: `changes_json` chỉ có một định dạng: `{ "field": [old, new] }` cho field được phép ghi giá trị; `{ "field": {} }` cho field cần che (PII / ngân hàng). Trường không đổi không xuất hiện. `null` trong tuple diễn tả thay đổi từ/đến không có giá trị. Không có xử lý tương thích cũ.
- Chính sách HIS-POL-001 giữ nguyên: ghi một CREATE, bỏ các UPDATE DRAFT thông thường, ghi STATE_CHANGE/decision/UPDATE operational/SENSITIVE_CHANGE. `checkpoint_json` vẫn là checkpoint tối thiểu được kiểm soát và **không** chuyển sang cấu trúc diff.
- Read API History của Customer và monitor cùng trình bày tuple hoặc marker `{}` không thực hiện suy luận trên schema cũ.
- Monitor bổ sung **Mã / tên đối tượng** (các giá trị hiện tại từ Customer master) bằng một `LEFT JOIN` dựa trên type `customer`, không lưu nhãn trùng lặp trong `business_history_events`, không N+1. Với Resource chưa có adapter nhãn, hiển thị dấu `—` và ID.
- Icon *Xem hồ sơ đối tượng* gọi **trực tiếp `CustomerDetailView.vue` đang được `/customers/:id` sử dụng** và truyền Customer ID persisted qua `customerId`. Không tạo modal Customer riêng trong History. Vẫn giữ icon *Chi tiết sự kiện* và modal sự kiện hiện có.
- Quyền monitor SYSTEM không cấp quyền Customer; nút Xem hồ sơ chỉ hiện khi actor có base `customer.view`. Backend `customerById` kiểm tra PBAC thực tế (trực tiếp ID được kiểm tra lại). Event monitor có thể chứa danh tính Customer với quyền SYSTEM hiện có, nên chỉ cấp quyền monitor cho auditor tin cậy.
- Supplier chưa code nên **không** join bảng Supplier hoặc hiển thị icon giả. Đăng ký adapter nhãn + modal chính thức sau khi SUP-G1 triển khai.

## 2. Hợp đồng và migration

Không thay đổi bảng hay API routes. Migration `000026`, `000027` giữ nguyên; **không có migration HIS-03**. Trường `changes_json` đã là LONGTEXT, wire API `changes` đổi schema: hai phần tử `[old, new]` hoặc `{}`. Cần đồng bộ frontend/backend trong cùng deployment (breaking wire change, không cho mixed-version).

### Bắt buộc: xóa HISTORY CŨ trên DEV trước khi chạy UI mới

Dữ liệu cũ dùng object `before/after`, `changed:true`, `before_count/after_count`; reader mới **cố ý từ chối**. Chỉ ở DEV clone có backup và khi đã tạm ngừng thao tác ghi Customer, DBA có thể thực hiện trong MariaDB:

```sql
-- XÁC NHẬN database và bảng theo DBPrefix thực tế; ví dụ dinktech_.
SELECT DATABASE();
SELECT COUNT(*) AS history_rows_before FROM dinktech_business_history_events;
START TRANSACTION;
DELETE FROM dinktech_business_history_events;
SELECT ROW_COUNT() AS history_rows_deleted;
COMMIT;
SELECT COUNT(*) AS history_rows_after FROM dinktech_business_history_events;
```

**Không xóa `customers`, `customer_interactions`, `authorization_logs`, `configuration_audit_events`** hoặc chạy `migrate:rollback`. Không tự xóa history trong migration. Xóa History cũ đồng nghĩa không còn sự kiện quá khứ và không tái tạo từ dữ liệu master; bạn đã cho phép điều này ở giai đoạn phát triển. Không thực hiện lệnh trên production.

Sau đó mới triển khai code HIS-03 và thử tạo/chuyển trạng thái/sửa Customer đang ACTIVE để xuất hiện event đúng định dạng mới. Lưu ý CREATE của Customer đã tồn tại không được tự backfill; khi thử nghiệm, tạo Customer mới nếu cần chứng cứ CREATE.

## 3. Kế hoạch QA và kết quả chờ xác nhận

1. Backend `HIS-UNIT`, `CUS-UNIT`, `UNIT-STANDARD`, `ROUTES`, `P1-STANDARDS`. Policy kiểm tra `json_encode` của marker `new stdClass` là `{}`, tuple đúng thứ tự, không PII, old format bị từ chối.
2. Frontend: `node --test scripts/his02-1-monitor-modal.contract.test.cjs scripts/his03-compact-object.contract.test.cjs`, `pnpm exec vitest run src/features/history/__tests__/historyMonitorModal.spec.ts src/features/history/__tests__/historyPresentation.spec.ts`, `pnpm run type-check`, `pnpm run check:imports`, `pnpm run build`.
3. DEV UAT `/system/history`: cột Mã / tên đối tượng đúng với Customer hiện tại; event modal hiển thị tuple `old → new` và `{}` hiển thị nhãn đã thay đổi; icon hồ sơ mở **chính modal Customer** và đóng quay về monitor; không cần quyền tổ chức để xem tên trong monitor SYSTEM nhưng không xem được hồ sơ nếu không có `customer.view`; API Customer vẫn phải từ chối nếu PBAC row DENY.
4. Kiểm tra SQL COUNT/ROWS parity, EXPLAIN indexed one-to-one Customer JOIN; số query không tăng theo page_size; no leakage Company/personal information theo policy.
5. Old-format data cố ý FAIL_CLOSED nếu không reset; ghi rõ đây là breaking migration ở DEV, không phải backward compatible.

### Chưa làm

- SUP chưa tồn tại, không cung cấp Supplier modal hay enrichment.
- Không tự động onboard Product/Organization/Employee vào recorder.
- Không phát triển API tìm kiếm global object hay thay đổi PBAC của Customer/Monitor.
