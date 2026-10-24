# M2 status update — 23/10/2026

- **M1 runtime:** the user confirmed login, `auth/me`, refresh-session continuity,
  logout, profile update and CSRF passing against the unchanged Frontend on XAMPP.
  These results are reported by the user, not reproduced in the authoring container.
- **M2 code:** internal read-only projection is implemented for Locations,
  Organization, People and Access scope facts. No new public routes/mutations/migrations.
- **M2 static/pure tests:** 39/39 new assertions PASS; all 70 `app/` PHP files lint PASS;
  6/6 original API routes remain; both immutable Frontend and Backend baseline pass hash checks.
- **M2 SQL/runtime acceptance:** pending `php spark m2:verify` on the user's configured DB.
  See `M2_IMPLEMENTATION_AND_QA.md`.

---

# Tình trạng thực hiện — 23/10/2026

## Đã tạo và đã chạy được (static/pure PHP)

| Kiểm tra | Kết quả |
|---|---|
| Kiểm tra Frontend nguyên bản | **PASS** 277/277 file; aggregate SHA-256 `1451b295dac3580d3c68c1e77eb2c443e19ffe652f4b3beb767a54e4fb0120e8` |
| OpenAPI v1.2.4 snapshot | **PASS** 150 operations; SHA-256 `f1736a006c2f4e1f529b22b8434be6995bcfbec8e7252d0af8ddc9d739de1728` |
| Route allowlist M1 | **PASS** 6/6 routes đã có trong OpenAPI, không có route ngoài hợp đồng |
| Kiểm tra auto-route và route đăng ký Shield ngoài /api/v1 | **PASS** không bật |
| Kiểm tra cấu hình CSRF session/rotation/JSON redirect | **PASS** cấu hình tĩnh |
| PHP syntax check trên source Backend v2 | **PASS** |
| Pure PHP contract/unit assertions (transport, ID, decimal, media URL, tên hiển thị) | **PASS** |

## Chưa kiểm thử được

- PHP extension `intl`, `mbstring`, MySQLi chưa được cài trong container khảo sát; Composer không có.
- `backend/vendor` và database đang chạy không có trong ZIP nguồn.
- **Chưa chạy HTTP integration, PHPUnit + Shield, CSRF rotation thông qua CI4 runtime, SQL permission reader với dữ liệu thật, End-to-End Frontend**.
- **Chưa xác nhận 6/6 hành vi runtime đạt tương đương**; mã vừa viết là implementation candidate, không phải release chính thức.
- M0 còn thiếu database snapshot (Shield tables, settings, migrations, FK) và HTTP golden fixtures trên hệ thống đang chạy.
- Không thay DB hoặc chạy migrations, không thực hiện chuyển media hay trỏ public route vào `/writable`.

## Không được làm trong bản M1

- Không triển khai 144 API nghiệp vụ còn thiếu theo kiểu `200 + []` giả.
- Không thay thế backend cũ, không sửa frontend, không tái tạo DB, không đưa production credential vào `.env.example`.
- Không kết luận PBAC đạt khi mới có ảnh chụp Base Permission.

## Cổng kiểm thử để chấp nhận M1

1. Trên XAMPP PHP >= 8.2, bật `intl`, `mbstring`, `mysqli`, `json`, `openssl`; chạy `composer install`, đóng băng `composer.lock` theo môi trường triển khai.
2. Kết nối bản **sao thử nghiệm** của database DinkERP v1.2.4, không chạy migration tạo mới/đổi schema.
3. Dùng `tools/smoke_identity.py`: health 200; me guest 401; csrf 200; login thiếu dữ liệu 422/CSRF regenerated; test account login/me/logout 200 và base_permissions đúng.
4. So sánh cùng bộ test account old-vs-v2: id/name/groups/permissions/status/avatar_url/remember/session expiry/profile updates/error/headers/locale.
5. Kiểm tra Origin được phép và Origin bị chặn, cookie session, CSRF stale, payload JSON hỏng, request ID không hợp lệ, 404 chưa triển khai, HTTPS proxy.

**Gate:** Không triển khai M2 API public hoặc đẩy v2 thay backend cũ trước khi đạt toàn bộ điểm kiểm thử.

## Cập nhật layout 23/10/2026

- Backend v1 chuyển sang `backend_basline/`; v2 chuyển sang `backend/`.
- Upload vật lý: `backend/public/upload/`; URL ảnh vẫn `/uploads/...` qua rewrite nội bộ.
- Không cần Alias/VHost XAMPP/Apache. Các kiểm tra bổ sung nằm ở `backend/tools/verify_layout.py`.

- Byte-for-byte baseline được khóa bằng manifest: **806 file Backend gốc**.
  Đường dẫn: `docs/backend-rewrite/backend-basline-fingerprint-v1.2.4.json`.
- Bộ kiểm tra layout chỉ kiểm tra đường dẫn và chính sách tĩnh, không phải HTTP image integration.

---

## Phụ lục M2.1 — Frontend Read Routes (2026-10-23)

- Người dùng đã xác nhận M2 `39/39` và `php spark m2:verify` PASS trên MariaDB thực.
- M2.1 đăng ký 16 operation mới (15 GET + POST resolve chỉ đọc), tổng 22/150.
- Có controller read-only và VIEW-PBAC gate riêng, không expose M2 repository trực tiếp.
- Pure tests và static routes PASS. **Chưa kiểm thử HTTP/runtime/differential PBAC trên máy người dùng.**
- Không thay Frontend, Backend baseline, migration hay upload. Xem `M21_READ_ROUTES_QA.md`.
