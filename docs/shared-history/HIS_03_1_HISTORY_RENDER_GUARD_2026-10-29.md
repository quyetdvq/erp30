# HIS-03.1 — History Monitor & Customer History Render Guard

**Ngày:** 2026-10-29
**Phạm vi:** sửa hai lỗi giao diện HIS-03 khi dữ liệu History không theo HIS-POL-002.
**Baseline:** `erp_v2.0.0_his03_compact_changes_object_modal_fixed.zip`.
**Không thay đổi:** HTTP routes, PBAC, bảng/migration, quy tắc ghi History, canonical CustomerDetailView, modal dùng chung.

## Chẩn đoán

Ở HIS-03, `historyChangeLines()` chủ động ném `HISTORY_COMPACT_CHANGES_INVALID` với `changes` không khớp định dạng compact **mới**. Tuy nhiên hàm được gọi *ngay trong Vue render* từ hai nơi:

- `HistoryMonitorView.vue` → `detailFields` → `TableDetailModal` → màn hình `/system/history`.
- `BusinessHistoryPanel.vue` → `changeSummary(event)` trong `v-for` → Customer detail tab **Lịch sử thay đổi**.

Nếu một sự kiện có `changes` cũ/malformed thì ngoại lệ lan lên Vue khiến vùng modal/tab không được render. Điều này phù hợp với ảnh, nhưng log Console/Network và DB thực tế vẫn cần kiểm tra để khẳng định nguyên nhân trên DEV.

## Sửa lỗi (HIS-03.1)

1. Giữ **strict-only** decoder `historyChangeLines`: `{"field":[before,after]}` hoặc `{"field":{}}`. `null` được phép khi không có diff. Cấm legacy `{changed:true}`, `{before,after}`, nested objects trong tuple và tên field không hợp lệ.
2. Thêm adapter `historyChangeDisplay` riêng cho giao diện: bắt đúng `HISTORY_COMPACT_CHANGES_INVALID`, **trả `invalid:true` và không hiển thị bất kỳ giá trị diff lỗi nào**. Không diễn giải / convert legacy.
3. Modal sự kiện vẫn hiển thị metadata (event, actor, time, object, state); khi diff lỗi, hiển thị thông báo `history.invalidChangesFormat` thay vì làm trắng modal.
4. Panel Customer xử lý từng sự kiện riêng, giữ sự kiện hợp lệ khác và hiển thị thông báo lỗi ở bản ghi sai, không làm mất toàn bộ tab.
5. Cập nhật kiểm thử nguồn HIS, contract JS, Vitest và thêm native Node smoke test cho chính helper TypeScript.

**Giới hạn:** Backend `HistoryEventPresenter` vẫn cố ý từ chối legacy. Nếu API đang chạy mã HIS-03 mới và DB còn History cũ, `GET /api/v1/history/monitor` / `GET /api/v1/history/customers/{id}` có thể báo lỗi trước khi frontend nhận được sự kiện. Bản sửa frontend **không** xử lý sự cố dữ liệu backend này; cần làm preflight dưới đây.

## Preflight & xử lý dữ liệu DEV cũ

Trước tiên **backup DEV**, xác nhận đúng database, đúng DBPrefix. Không chạy SQL xóa trên production. Không cần migration mới.

```sql
SELECT DATABASE();
SELECT COUNT(*) AS total_events FROM dinktech_business_history_events;
-- Tìm dấu hiệu legacy, chỉ trả số lượng; không đưa thông tin nhạy cảm ra log.
SELECT COUNT(*) AS suspect_legacy_rows
FROM dinktech_business_history_events
WHERE changes_json LIKE '%"changed"%'
   OR changes_json LIKE '%"before"%'
   OR changes_json LIKE '%"after"%'
   OR changes_json LIKE '%"before_count"%'
   OR changes_json LIKE '%"after_count"%';
```

Đây là **heuristic**, không chứng minh tất cả các dòng khác đều đúng schema. Khi được phép reset toàn bộ History trên DEV như đã chốt trong HIS-03, DBA chủ động tạm dừng các thao tác ghi Customer/SUP rồi thực hiện:

```sql
START TRANSACTION;
DELETE FROM dinktech_business_history_events;
SELECT ROW_COUNT() AS deleted_rows;
COMMIT;
SELECT COUNT(*) AS remaining_events FROM dinktech_business_history_events;
```

Không xóa `customers`, PBAC, `configuration_audit_events`, `authorization_logs`, không rollback migrations. Sau đó tạo sự kiện HIS-03 **mới** (chuyển trạng thái mới hoặc sửa Customer đang vận hành), xác nhận hai API trả `200` với shape `changes` đúng. Nếu API vẫn trả dữ liệu/shape cũ thì kiểm tra bản backend thực đang được server chạy và OPcache; không mở chế độ đọc legacy.

## QA chạy trên Windows

```powershell
cd D:\XAMPP\htdocs\erp\frontend
node --experimental-strip-types --test scripts/his03-1-render-safety.test.mjs
node --test scripts/his02-1-monitor-modal.contract.test.cjs scripts/his03-compact-object.contract.test.cjs
pnpm exec vitest run src/features/history/__tests__/historyPresentation.spec.ts src/features/history/__tests__/businessHistoryPanel.spec.ts src/features/history/__tests__/historyMonitorModal.spec.ts
pnpm run type-check
pnpm run check:imports
pnpm run build
```

UAT: mở `/system/history` → icon chi tiết sự kiện → modal hiển thị; mở icon hồ sơ Customer → tab Lịch sử thay đổi → render các sự kiện; kiểm tra `DRAFT → ACTIVE` và `{}`. Nếu gặp sự kiện legacy (ví dụ server chưa đồng bộ), chỉ thấy cảnh báo định dạng, không thấy giá trị nhạy cảm. Với actor không có `business_history_monitor.view`, API và icon vẫn bị chặn như trước.

**Trạng thái tại khi bàn giao:** Source/backend purity, native Node smoke, imports và static checks kiểm chứng tại đây; `pnpm` Vitest/type-check/build phải chạy ở Windows có dependencies. Không gọi đây là production acceptance khi chưa có UAT/HTTP evidence.
