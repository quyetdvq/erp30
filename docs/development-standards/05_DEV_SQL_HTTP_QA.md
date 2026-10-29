# Hướng dẫn xác minh MariaDB, HTTP PBAC và hiệu năng — v1.9.23

> **Áp dụng trên DEV clone có dữ liệu được phép test.** Các ví dụ `EXPLAIN` là read-only và **không** tự cấp quyền, không tạo index, không sửa DB. Tên bảng thực tế phụ thuộc `DBPrefix` (ví dụ `dinktech_`); thay bằng tên bảng thật đã xác minh trước khi chạy.

## A. Kiểm tra hiện trạng và index (chỉ đọc)

```sql
SELECT VERSION(), @@transaction_isolation;
SHOW INDEX FROM dinktech_provinces;
SHOW INDEX FROM dinktech_wards;
SHOW INDEX FROM dinktech_contacts;
SHOW INDEX FROM dinktech_organizations;
SHOW INDEX FROM dinktech_customers;

SELECT COUNT(*) AS n_provinces FROM dinktech_provinces;
SELECT COUNT(*) AS n_wards FROM dinktech_wards;
SELECT COUNT(*) AS n_contacts FROM dinktech_contacts;
SELECT COUNT(*) AS n_orgs FROM dinktech_organizations;
```

Chú ý `province_id`, `contact_ref_id`, owner `(contact_type,contact_ref_id)`, các index status/canonical sort. Không suy ra DB có index chỉ từ code.

## B. So sánh EXPLAIN dùng index khi lọc ID canonical

```sql
-- Ví dụ read-only cho planner; không thay thế predicate PBAC của API thật.
EXPLAIN FORMAT=JSON
SELECT id, code, name, province_id, status
FROM dinktech_wards
WHERE province_id IN (1) AND status = 'ACTIVE'
ORDER BY id ASC LIMIT 15 OFFSET 0;

EXPLAIN FORMAT=JSON
SELECT id, contact_type, contact_ref_id, status
FROM dinktech_contacts
WHERE contact_ref_id IN (1) AND contact_type = 'CUSTOMER'
ORDER BY id ASC LIMIT 15 OFFSET 0;

-- Chỉ để đối chiếu biểu thức index-UNFRIENDLY cũ, không thay SQL nghiệp vụ:
EXPLAIN FORMAT=JSON
SELECT id FROM dinktech_wards
WHERE BINARY COALESCE(CAST(province_id AS CHAR), '') IN ('1')
ORDER BY id ASC LIMIT 15 OFFSET 0;
```

**Quan trọng:** Chỉ đánh giá `EXPLAIN` thực tế SQL có các điều kiện PBAC đầy đủ từ code/log/tracing có quyền và bind; ba ví dụ trên là phép so sánh riêng cho biểu thức index, **không phải** SQL của endpoint và không được dùng làm chứng cứ PBAC đúng.

## C. HTTP/API với hai actor khác quyền (không đăng token vào chat)

| Endpoint / trường hợp | Kết quả cần xác minh |
|---|---|
| `GET /api/v1/wards?page=1&page_size=15&filter[province_id]=1` | 200 với đúng Province; SQL COUNT/PAGE; total là số row actor được xem |
| `GET /api/v1/provinces?page=1&page_size=200` | 200 nếu có VIEW và API cho phép; tổng/meta đúng |
| `GET /api/v1/wards?page=1&page_size=201` | Bad request theo API; không âm thầm trả 201 row |
| `GET /api/v1/wards/{id}` cho actor không VIEW | 404/no-leak theo hợp đồng, không trả dòng dù có ID |
| `GET /api/v1/contacts?page=1&page_size=15` | Chỉ owner/parent Customer được phép xem; đúng total và allowed_actions |
| `GET /api/v1/contacts?filter[contact_ref_id]=1` | predicate canonical đã bind; không lộ Contact của owner khác |
| `GET /api/v1/customers?page=1&page_size=100` | 200 nếu có VIEW; frontend cap đồng bộ |
| `GET /api/v1/customers?page=1&page_size=101` | API từ chối theo cap=100 |
| `POST /api/v1/organizations/labels/resolve` | chỉ trả nhãn Organization actor có VIEW, không lộ qua labels/aggregate |

Kiểm tra hai tổ hợp actor A/B, quyền VIEW-only và editor, ít nhất 2 Company; dấu hiệu DENY tuyệt đối phải tiếp tục được giữ. Negative test phải thử status khác nhau, parent Customer bị từ chối và expired primary assignment Employee.

## D. Benchmark & trạng thái nghiệm thu

Mỗi endpoint quan trọng ghi: cấu hình server, phiên bản MariaDB, `DBPrefix`, số lượng records (đã ẩn dữ liệu nhạy cảm), quyền actor theo vai trò, page size, số câu SQL/request, `rows` và `key` của EXPLAIN, p50/p95 latency (tối thiểu nhiều lượt warm/cold; xác định rõ điều kiện), lock waits/errors. So sánh với v1.9.22 trên DEV clone cùng dữ liệu nếu khả thi.

Nếu dataset ít record, `EXPLAIN` không chứng minh lợi ích ở Production; ghi `INCONCLUSIVE`. Với >4096 Organization, **bắt buộc fail-closed**, đánh dấu blocker scalability và thiết kế mở rộng có kiểm chứng thay vì tăng cap tùy tiện.

## E. Regression UI và mã nguồn

Chạy `backend/tools/test-unit.ps1 CHECK`, `RUN CUS-UNIT`, M8.5/M8.6B–E/M10.2 và 3 bài M19.23. Frontend `pnpm exec vitest run`, `pnpm run type-check`, `pnpm run lint`, `pnpm run check:imports`, `pnpm run build`. Trình duyệt xác minh `/people/employees`, `/organizations`, `/customers`, lựa chọn Province/Ward có tìm kiếm, Contact unified state list, `allowed_actions` và Workflow. Tất cả hành động thực hiện trên DEV; không chạy thử nghiệp vụ ghi dữ liệu trên Production.
