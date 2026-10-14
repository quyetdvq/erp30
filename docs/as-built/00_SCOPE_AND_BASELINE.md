# 00 — Scope, Current Product Baseline & Evidence

**Loại:** Product scope theo trạng thái implementation (*as-built*, không phải UR được phê duyệt).  
**Mốc:** code snapshot 2026-10-14 / local Git base `d935186...`. Thư mục frontend đã có **refactor cấu trúc v1** (xem [12_FRONTEND_REFACTOR_V1.md](12_FRONTEND_REFACTOR_V1.md)); các số liệu catalogue phản ánh source sau refactor.

## 1. Xác định đúng ý nghĩa của baseline

ERP30 là web ERP triển khai theo một repository gồm `backend/` (CodeIgniter 4, PHP), `frontend/` (Vue SPA), `contracts/openapi/`, các tài liệu và các migration. Tài liệu này ghi nhận *mã nguồn hiện có tại mốc trên*. Việc một route, class hoặc trang UI có mặt chứng minh rằng code tồn tại — **không chứng minh** nó đang phục vụ thành công trên production, đã được chuyển DB, hoặc nghiệp vụ người dùng đã ký nghiệm thu.

Phân biệt ba mốc quan trọng:

- `2cc1e9369bee76a23527cb81684497471af9166d`: commit hash do **header của file ZIP** công bố; chưa đối chiếu được với upstream GitHub do môi trường tải remote lỗi DNS.
- `d935186386e094b96d92b8fe6f4458d1610562a0`: commit cục bộ đóng gói lại nội dung ZIP, dùng làm **điểm đối chiếu code** khi viết tài liệu.
- `docs/as-built/`: tài liệu do lượt rà soát này tạo, **không** có quyền sửa lùi lịch sử P1. Không coi ngày/chỉ số này là semver của ứng dụng.

## 2. Sản phẩm đã có gì trong code

| Miền | Phạm vi đã có (source-backed) | Không suy ra từ code |
|---|---|---|
| Nền tảng và địa lý | Province, Ward, Organization hierarchy/tree; danh bạ gắn owner | Đã nhập đủ địa giới / dữ liệu thật |
| Con người | Department, Position, Employee, Employee Assignment | Tính đúng thực tế HR của đơn vị |
| Security | Shield session, users, auth groups, resources/actions, policies, logs, trực tiếp cấp permission | An toàn production chưa qua audit / pentest |
| Workflow | Workflow states, lifecycles, transitions, business state change; native status fallback | Mỗi resource đã được gắn lifecycle hợp lệ trong DB |
| Auto-numbering | Resource numbering series, validate config, cấp counter; gắn product identifier | Không có race ngoài các điều kiện được test |
| Product | Product group tree, product/UOM/conversion, combo, image, product option/batch lookup, import | Đã có order/invoice/warehouse nghiệp vụ đầy đủ |
| Pricing | Price list, price list item, giá theo company → group → default | Pricing UI/API là quotation hoặc sales order |
| Import | CSV/XLSX, create/update/upsert modes, job/row tracking | Hệ thống background queue (luồng `run()` đang đồng bộ) |
| Display | Server display overrides, organization inheritance, user local preferences | Các cấu hình khác business policy |
| SPA | Dashboard, shell/workspace tab, recent/favorites, theme, i18n VI/EN, permission-aware actions | Chỉ menu guard đã đủ thay backend PBAC |

Nguồn: [`backend/app/Config/Routes.php`](../../backend/app/Config/Routes.php), [`frontend/src/router/index.ts`](../../frontend/src/router/index.ts), [`ResourceActionSeeder.php`](../../backend/app/Database/Seeds/ResourceActionSeeder.php), [`ProductRoleService.php`](../../backend/app/Modules/Product/Product/ProductRoleService.php).

## 3. Số liệu kiểm kê source (tự động)

| Thành phần | Giá trị static scan |
|---|---:|
| Migration thuộc ứng dụng | 17 |
| Bảng tạo bởi migration ứng dụng | 31 |
| Cột tại schema định nghĩa sau migration 000017 | 355 |
| Foreign key khai báo ở local migrations | 46 |
| Route methods trong `/api/v1` | 152 (149 operations + 3 preflight OPTIONS) |
| OpenAPI operations | 149, thuộc 99 path templates |
| Named Vue route | 29 |
| PHP test files | 102 |
| Vitest spec files | 26 |
| Playwright spec files | 17 |

Nguồn kiểm kê và hash mỗi đầu vào: [`reference/inventory.json`](reference/inventory.json). Số lượng sẽ thay đổi nếu code khác baseline; không viết tay lại số liệu mà chạy script tái tạo.

## 4. Miền nghiệp vụ chưa được thấy như vertical slice hoàn chỉnh

Trong danh mục backend routes và Vue view hiện tại **không thấy** vertical slice riêng cho sales order, purchase order, inventory movement, payment, accounting journal, production order, payroll. Đây là **giới hạn phạm vi quét route/màn hình**, không phải kết luận rằng mọi service/integration cho các miền này đều không tồn tại. Không đưa các phân hệ đó vào FRS as-built nếu chưa có thêm source.

## 5. Mô hình nguồn dữ liệu, thẩm quyền và vòng đời

- **Source of truth runtime:** dữ liệu thực trong MySQL/MariaDB và cấu hình runtime. Bản chụp không chứa dữ liệu đó.
- **Thẩm quyền implementation:** `Routes.php` → controllers → services → repositories; seed và migration cho cấu trúc/giá trị khởi tạo; router/services/stores cho UI.
- **Thẩm quyền contract:** OpenAPI cho shape công bố; không phủ quyết được behavior nếu code đã lệch (cần ghi change control).
- **Tài liệu cũ:** `docs/architecture/00..11`, `docs/implementation/` là history/spec P1; nhiều thành phần đã vượt mốc P1 nên không dùng làm check-list implementation hiện tại nếu chưa reconcile.

## 6. Những gì chưa chứng minh được

Chưa kết nối DB chạy thực tế để introspect các table/vendor migrations, chưa chạy PHPUnit/Vitest/Playwright và chưa thử browser/API live; CI/CD và môi trường vận hành chưa được cung cấp. Đặc biệt code chạy với DB schema chưa áp dụng 000014/000015/000016/000017 có thể khác đáng kể; xem [08 Runtime](08_RUNTIME_OPERATIONS.md), [09 Test matrix](09_VERIFICATION_MATRIX.md).

## 7. Nguyên tắc phê duyệt sản phẩm

Sau khi dùng bản này làm mốc, mọi cải tiến mới cần có mã change request, nguồn code liên quan, business acceptance, migration/API/UI impact, test impact, backward compatibility và cập nhật tài liệu. **Không gán trạng thái `approved` cho lựa chọn chỉ suy ra từ static code**; người sở hữu nghiệp vụ phải ký nếu thay đổi là business requirement.
