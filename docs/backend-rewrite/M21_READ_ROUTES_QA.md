# DinkERP Backend v2 — M2.1: Frontend Read Routes

**Ngày:** 2026-10-23  
**Nền:** M0/M1 đã được người dùng nghiệm thu thực tế, M2 PASS 39/39 và `php spark m2:verify` PASS trên DB của người dùng.

## 1. Vì sao cần M2.1?

M2 đã làm xong các read model phục vụ M3 nhưng cố ý chưa đăng ký HTTP route.
Frontend cũ gọi trực tiếp các API đã có trong OpenAPI, vì vậy không thể test
màn hình danh sách/chi tiết chỉ bằng `m2:verify`.

M2.1 đưa **bản adapter đọc** lên HTTP với phiên Shield và PBAC per-row.
**Không copy lại controller/service cũ, không đổi Frontend, không đổi database.**

## 2. Route tương thích với Frontend

| HTTP | Endpoint | Mục đích |
|---|---|---|
| GET | `/api/v1/provinces` | Danh sách tỉnh/thành |
| GET | `/api/v1/provinces/{id}` | Chi tiết tỉnh/thành |
| GET | `/api/v1/wards` | Danh sách phường/xã |
| GET | `/api/v1/wards/{id}` | Chi tiết phường/xã |
| GET | `/api/v1/organizations` | Danh sách tổ chức |
| GET | `/api/v1/organizations/tree` | Cây tổ chức theo trang và dữ liệu đã được phép xem |
| GET | `/api/v1/organizations/{id}` | Chi tiết tổ chức |
| POST | `/api/v1/organizations/labels/resolve` | Resolve nhãn tối đa 50 tổ chức; **không ghi**, vẫn yêu cầu CSRF |
| GET | `/api/v1/departments` | Danh sách phòng ban |
| GET | `/api/v1/departments/{id}` | Chi tiết phòng ban |
| GET | `/api/v1/positions` | Danh sách chức danh |
| GET | `/api/v1/positions/{id}` | Chi tiết chức danh |
| GET | `/api/v1/employees` | Danh sách nhân viên |
| GET | `/api/v1/employees/{id}` | Chi tiết nhân viên |
| GET | `/api/v1/employee-assignments` | Danh sách phân công |
| GET | `/api/v1/employee-assignments/{id}` | Chi tiết phân công |

**Tổng cộng:** 16 operation mới + 6 operation M1 = **22/150** operation OpenAPI.

### Contract giữ nguyên

- Response danh sách: `{ "data": [...], "meta": { "page": 1, "page_size": 50, "total": 0, "request_id": "..." } }`.
- Chi tiết: `{ "data": { ... }, "meta": { "request_id": "..." } }`.
- BIGINT ID là JSON string, `employee_assignment.is_primary` là boolean.
- `q`, `page`, `page_size`, `sort`, `filter`; page size tối đa 200; filtering phải xảy ra **sau PBAC nhưng trước pagination/total**.
- Không đăng ký POST/PATCH/DELETE CRUD mới. Các API còn thiếu không trả 200 giả.

## 3. Phân quyền và an toàn

`ReadViewGate` đánh giá trạng thái tài khoản/nhân viên, Base Permission,
assignment hiệu lực UTC, Resource domain/state từ persisted row, scope từ cây tổ
chức, group ACTIVE, policy ACTIVE, `ALLOW`, `DENY`, `ABSOLUTE_DENY`.
`PolicyReadLanes` tái hiện quy tắc v1: ABSOLUTE_DENY luôn phủ quyết; DENY bình
thường chỉ từ chối nếu tất cả nhánh đều DENY; nhánh không có policy là neutral
sau khi đã qua Base Permission và employment checks.

- Khách chưa đăng nhập: `401`.
- Record không tồn tại hoặc không có quyền đọc: `404`.
- List/total chỉ tính bản ghi đã qua PBAC.
- Tree không truy vấn thêm cha nằm ngoài kết quả đã lọc để tránh tiết lộ tên/ID.
- Chỉ đọc, không chạy migrations, không thêm file upload.

**Giới hạn phải nghiệm thu:** đây là lát cắt PBAC VIEW cho M2.1, không phải toàn
bộ Access/PBAC M3. Bộ so sánh trên DB thật cần kiểm tra quyền, state/lifecycle,
scope, group và concurrent changes. Luồng list hiện lọc PHP sau khi đọc các bản
ghi trong phạm vi module; M3 cần benchmark và tối ưu SQL khi dữ liệu lớn.

## 4. Cài bản Overlay trên Windows/XAMPP

1. Sao lưu `backend/app/Config/Routes.php` và các file ghi đè nếu bạn đã sửa local.
2. Giải nén `DinkERP_Backend_v2_M2.1_ReadRoutes_Overlay.zip` vào `D:\\XAMPP\\htdocs\\erp\\`.
3. Không giải nén lại bản full repository lên `.env`, `vendor` hoặc `public/upload` đang chạy.
4. Tại PowerShell:

```powershell
cd D:\XAMPP\htdocs\erp
php backend\tests\m1_pure.php
php backend\tests\m2_pure.php
php backend\tests\m21_pure.php
python backend\tools\verify_routes.py
python backend\tools\verify_m2.py
cd backend
php spark m2:verify
php spark m21:verify
php spark routes
```

## 5. Test Frontend thật (không chỉnh code)

- Đăng nhập từ Frontend như M1 đã thử, F5, mở các màn hình danh sách tỉnh,
  phường, tổ chức, phòng ban, chức danh, nhân viên, phân công nhân sự.
- Dùng DevTools → Network kiểm tra request GET và HTTP response.
- Tài khoản có quyền: GET trả 200 và data/meta đúng; chỉ được thấy các bản ghi
  mà actor có quyền. Nếu danh sách rỗng, kiểm tra Base Permission và PBAC thay
  vì mặc định coi là bug.
- Tài khoản guest: toàn bộ GET bảo vệ trả 401. Tài khoản scope hẹp phải không
  thể GET chi tiết, list hay resolve các bản ghi ngoài scope.
- API mutation như `POST /provinces`, `PATCH /employees/{id}` **chưa có**:
  nút Tạo/Lưu chưa test được ở M2.1.

## 6. Điều kiện để coi M2.1 hoàn tất runtime

1. `php spark m21:verify` PASS, `php spark routes` hiện đủ 22 route.
2. Cùng **ít nhất ba actor**: (i) quyền đầy đủ, (ii) quyền hạn chế tổ chức,
   (iii) bị DENY hoặc ABSOLUTE_DENY; so sánh Backend baseline với v2.
3. So sánh dữ liệu JSON/meta/sort/filter/total, các state ACTIVE và INACTIVE,
   assignment hết hạn và cây nhiều tầng. GET detail vượt scope phải 404.
4. Test rate và memory khi `employee_assignments` hoặc `organizations` lớn.
5. Chỉ xem M2.1 là ứng viên tích hợp Frontend; chưa coi M3 PBAC là hoàn tất.
