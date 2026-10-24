# DinkERP Backend v2 — M3.3B1.1: Hotfix tài liệu tham chiếu kiểm thử

## 1. Nguyên nhân

Log XAMPP cho thấy hai bộ kiểm thử M3.3B1 không chạy hết vì thiếu tệp tham chiếu tại máy cục bộ:

- `backend/tests/m33b_rules_pure.php` gọi trực tiếp `backend_basline/.../AuthorizationGroupService.php`. Nếu `backend_basline` được cài không đầy đủ, PHP dừng ngay khi `require`.
- `backend/tests/m33b_contract_pure.php` đọc `docs/backend-rewrite/openapi-operations-v1.2.4.json`. Khi thiếu file, `file_get_contents()` thất bại và `json_decode()` phát sinh `JsonException`.

Đây là **lỗi đóng gói/phụ thuộc kiểm thử**, chưa phải bằng chứng lỗi xử lý PBAC hoặc transaction.

## 2. Phạm vi sửa

Bản vá bổ sung **snapshot chỉ dành cho kiểm thử**, được khóa bằng SHA-256. Test ưu tiên sử dụng file cục bộ nếu file có mặt và đúng checksum. Nếu file cục bộ **không tồn tại**, test chuyển sang snapshot đã xác minh và ghi rõ nguồn. Nếu file có mặt nhưng **khác checksum**, test dừng, không âm thầm fallback.

| Nguồn | SHA-256 chính thức |
|---|---|
| `AuthorizationGroupService.php` từ `erp_v1.2.4.zip` | `7c4bdcfa439493b7602d9a2724c1a369c615abddd45b3f6817c3bf03d1821714` |
| Manifest `openapi-operations-v1.2.4.json` đã đóng băng | `5afe430a5189706b573cb8c1edd15fcbcf891ace0bb3fe9501a49154817a16e9` |
| OpenAPI YAML gốc trong `erp_v1.2.4.zip` | `f1736a006c2f4e1f529b22b8434be6995bcfbec8e7252d0af8ddc9d739de1728` |

Snapshot `AuthorizationGroupService.php` được lấy nguyên byte từ ZIP baseline gốc. Manifest chứa hash OpenAPI YAML gốc và được đối chiếu với bản inventory đã đóng băng trước đó.

**Không thay đổi:** `backend/app`, routing, `DINKERP_M33B_WRITE_MODE`, `.env`, `vendor`, `frontend`, `backend_basline`, database, hay thư mục upload.

## 3. Danh sách file cập nhật

- `backend/tests/m33b_rules_pure.php`: kiểm tra checksum và lựa chọn service baseline chính xác.
- `backend/tests/m33b_contract_pure.php`: kiểm tra checksum và lựa chọn OpenAPI manifest chính xác.
- `backend/tests/fixtures/baseline_v1_2_4/app/Modules/Authorization/Administration/Groups/Application/AuthorizationGroupService.php`: snapshot baseline chỉ dành cho test.
- `backend/tests/fixtures/baseline_v1_2_4/contract/openapi-operations-v1.2.4.json`: snapshot manifest chỉ dành cho test.
- `docs/backend-rewrite/openapi-operations-v1.2.4.json`: bổ sung lại manifest ở đường dẫn chuẩn (nếu trước đó thiếu).
- `docs/backend-rewrite/M3_3B1_MUTATION_IMPLEMENTATION_QA.md`: bổ sung ghi chú bản vá.
- Tài liệu hướng dẫn này.

## 4. Hướng dẫn áp dụng trên Windows/XAMPP

Giải nén ZIP Overlay vào `D:\XAMPP\htdocs\erp\`. File trong ZIP bắt đầu bằng `backend/` và `docs/`. Nên sao lưu những file test đã chỉnh sửa cục bộ trước khi chép đè.

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m33b_rules_pure.php
php backend\tests\m33b_contract_pure.php
php backend\tests\m33b_transaction_fixture.php
php backend\tests\m33b_runtime_pure.php

cd backend
php spark m33b:verify
php spark routes
```

Kết quả mong đợi: `57/57` mutation rules, `20/20` route assertions, `24/24` contract assertions, `34/34` transaction fixtures và `17/17` write-PBAC fixtures; lệnh CLI `m33b:verify` chỉ đọc database.

Nếu máy vẫn thiếu tệp trong `backend_basline`, kết quả test rules sẽ ghi rõ:

```text
SOURCE: SHA-256-verified v1.2.4 test snapshot; NOT locally installed backend_basline.
TOTAL: 57 M3.3B1 pure assertions passed.
```

Có thể chủ động chọn nguồn snapshot:

```powershell
php backend\tests\m33b_rules_pure.php --snapshot-only
php backend\tests\m33b_contract_pure.php --snapshot-only
```

Hoặc yêu cầu **bắt buộc sử dụng file được cài cục bộ**, không cho phép fallback:

```powershell
php backend\tests\m33b_rules_pure.php --require-installed
php backend\tests\m33b_contract_pure.php --require-repo-contract
```

`--require-installed` sẽ báo lỗi nếu `backend_basline` chưa đầy đủ. Đó là kết quả đúng theo chế độ kiểm tra nghiêm ngặt.

## 5. Kiểm thử khi bàn giao

- **18/18** bộ test PHP từ M1 đến M3.3B1 đạt; không có test thất bại.
- **193/193** file PHP trong `backend/app` và `backend/tests` đạt kiểm tra cú pháp.
- Kiểm thử các tình huống **tham chiếu đầy đủ**, **thiếu tham chiếu**, **file sai SHA-256** và **chế độ strict** cho kết quả đúng dự kiến.
- Không có thay đổi nào đối với runtime hay tổng số **53 API** đã đăng ký.

## 6. Phạm vi chưa nghiệm thu

Bản vá này chỉ sửa hai bài test. Kết quả kiểm thử hiện có chưa chứng minh sự tương đương HTTP mutation hoặc transaction thực trên MariaDB. Giữ `DINKERP_M33B_WRITE_MODE=off` trong môi trường đang hoạt động. Việc thử tạo/sửa/xóa phải thực hiện trên **bản sao database riêng**, có kiểm tra rollback, scope, Shield và phản hồi tiếng Việt/Anh so với baseline.
