# DinkERP — Biên bản chốt kỹ thuật NFX-B

- **Mốc ghi nhận:** 2026-10-27 (Asia/Bangkok)
- **Baseline:** `erp_v1.8.0.zip` + NFX-A1 + NFX-A2 + NFX-B + Hotfix NULL + Hotfix PBAC
- **Quyết định:** `NFX-B: TECHNICAL COMPLETE / BUSINESS UAT DEFERRED`
- **Nguồn bằng chứng:** log XAMPP người dùng cung cấp trong cuộc hội thoại và xác nhận Browser UAT rút gọn 4/4.
- **Phạm vi xác nhận:** mã nguồn đã vá và môi trường kiểm thử; **không** suy diễn thành nghiệm thu Workflow nghiệp vụ thực hay production.

## 1. Kết quả đã được xác nhận

| Kiểm tra | Kết quả | Ghi chú |
|---|---:|---|
| Backend Unit | 180/180 PASS | 386 assertions |
| Backend Integration trên `erp_test` | 54/54 PASS | 372 assertions |
| Backend Reliability | 7/7 PASS | 77 assertions |
| **Tổng backend** | **241/241 PASS** | **835 assertions** |
| Frontend Vitest | 28/28 PASS | 8 test files, từ đợt kiểm thử NFX-B |
| OpenAPI generate / lint / generated check | PASS | Theo log NFX-B |
| Type-check / Vite production build | PASS | Theo log NFX-B |
| Browser UAT rút gọn | **4/4 PASS** | Người dùng xác nhận |
| Browser UAT với Workflow thực | **DEFERRED** | Chưa có Workflow nghiệp vụ thực tế để kiểm chứng |

### Các hotfix đã được xác nhận

1. **Explicit NULL Query Validation**: bác bỏ query có giá trị `null` được truyền rõ ràng, thay vì biến thành mặc định; Unit 180/180 PASS sau sửa.
2. **Fresh PBAC Per Listing**: nạp quyền Workflow mới mỗi lượt `MyWorkflowService::listing()`; bài integration *Stale state revocation and recipient change disappear on next read* đã PASS.

## 2. Phạm vi đã hoàn thành

- `/my-work` là danh sách **đối tượng được chọn nhận thông báo và còn hành động Workflow hợp lệ**, không phải hệ thống giao nhiệm vụ tổng quát.
- Dựa vào trạng thái nghiệp vụ và kiểm tra PBAC hiện tại; không lấy `read_at` làm trạng thái hoàn thành.
- API chỉ đọc, cursor có chữ ký, giới hạn quét, không thao tác phê duyệt hoặc sửa chứng từ trực tiếp.
- Không thêm migration, không ảnh hưởng 9 bảng Notifications, Email/Push/Tasks và các thay đổi đã nghiệm thu NFX-A1/A2.

## 3. Business UAT được hoãn, không phải PASS

Khi có quy trình Workflow thực, thực hiện lại trên đối tượng và tài khoản được phép dùng trong kiểm thử:

- Người có assignment hợp lệ và VIEW/setstate thấy đúng đối tượng.
- Người không có assignment không thấy đối tượng mặc dù được xem module.
- Mở đúng đối tượng, không tự chuyển trạng thái.
- Đã đọc Notifications nhưng vẫn còn hành động thì My Work vẫn hiển thị.
- Đổi trạng thái sang không còn transition: tải lại My Work không còn mục.
- Thu hồi VIEW/setstate: tải lại không còn mục.
- Thay đổi người được chỉ định: kết quả đổi đúng.
- Chuyển phiên người dùng: dữ liệu không lẫn tài khoản.

**Quy tắc đóng:** NFX-B chỉ nâng lên `BUSINESS UAT COMPLETE` khi đủ dữ liệu nghiệp vụ và các kịch bản trên được nghiệm thu; không tự động chuyển trạng thái bởi kết quả Unit/Integration.

## 4. Bàn giao giai đoạn kế tiếp

- `NFX-A1`: CLOSED.
- `NFX-A2`: CLOSED.
- `NFX-B`: **TECHNICAL COMPLETE / BUSINESS UAT DEFERRED**.
- `NFX-C`: DESIGN, chưa sửa code và chưa phát sinh migration.
- Ưu tiên NFX-C: event registry tĩnh bảo toàn hành vi, sau đó bổ sung **Email cảnh báo Import lỗi có opt-in riêng**.
- Không lấy Workflow chưa sử dụng hoặc Product chưa có owner/recipient rule làm tiền đề bắt buộc của NFX-C.

**Tài liệu liên quan:** `DinkERP_NFX_C_Business_Event_Registry_Implementation_Design_v1.8.0_A1_A2_B.md`.
