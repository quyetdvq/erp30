# 05 — REST API Design & Contract (as-built)

**Version surface:** `/api/v1`. **Mức bằng chứng:** [S]. Route/method và OpenAPI được so khớp tĩnh; request/response bodies, schema examples và error status **chưa được chứng thực bằng HTTP integration**.

## 1. Danh mục đầy đủ và kết quả đối chiếu

| Nguồn | Tổng | Kết quả |
|---|---:|---|
| Backend [`Routes.php`](../../backend/app/Config/Routes.php) | 152 API method-path entries | 149 nghiệp vụ và 3 CORS `OPTIONS` |
| [`openapi.yaml`](../../contracts/openapi/openapi.yaml) | 149 operations / 99 URL templates | Trùng method/path-signature với **149/149** route nghiệp vụ |
| Backend nghiệp vụ không mô tả OpenAPI | 0 | Theo static scanner trên snapshot này |
| OpenAPI operations không có backend route | 0 | Theo static scanner trên snapshot này |

3 route **OPTIONS** thuộc cơ chế preflight (`/api/v1/(:any)`, `/api/v1/auth/(:any)`, `/api/v1/health`), không được tính là thiết kế API thiếu. Danh mục tất cả method/path, handler, `operationId` và line-number: [API_ENDPOINTS.md](reference/API_ENDPOINTS.md).

> Phép so khớp chuẩn hóa placeholder `(:num)` / `(:segment)` và `{id}` / `{resource}` thành token param, nên **không xác nhận constraint kiểu param**, thứ tự match runtime, schema, auth hay phản hồi. Không nói “API 100% đúng OpenAPI” từ con số 149/149.

## 2. Convention truyền dữ liệu

```json
{
  "data": { "id": "123", "status": "ACTIVE" },
  "meta": { "request_id": "abc123" }
}
```

```json
{
  "data": [],
  "meta": { "page": 1, "page_size": 50, "total": 0, "request_id": "abc123" }
}
```

```json
{
  "error": { "code": "DOMAIN_ERROR_CODE", "message": "Human-readable message", "details": [] },
  "meta": { "request_id": "abc123" }
}
```

Ví dụ là **shape minh họa**, không phải fixture của endpoint. Source [`ApiTransport.php`](../../backend/app/Infrastructure/Api/ApiTransport.php) định nghĩa envelope; `204 No Content` không có body. Chuẩn tên property API `snake_case`; BIGINT ID và decimal được normalize dạng string, còn `page/page_size/total` là integer. Headers quan trọng: `X-Request-ID`, `Content-Language` (theo locale); FE gửi `Accept-Language` và credential cookies.

**List query:** [`CommonListQueryAdapter.php`](../../backend/app/Infrastructure/Api/CommonListQueryAdapter.php) chỉ nhận `q`, `page`, `page_size`, `sort`, `filter`; mặc định `page=1`, `page_size=50`, tối đa `page_size=200`. Các trường search/sort/filter phụ thuộc allowlist từng endpoint, không nhận trường bất kỳ. Sort cú pháp `name,-created_at` khi allowlist cho phép; thêm tie-break `id ASC` nếu thiếu. Total phải tính trên **tập bản ghi actor được phép thấy** trước pagination.

## 3. API map theo resource group

| Nhóm | Prefix / action minh họa | Ghi chú |
|---|---|---|
| System/auth | `/health`, `/auth/login`, `/auth/logout`, `/auth/csrf`, `/auth/me`, `/auth/profile` | Session Shield & CSRF |
| Foundation | `/provinces`, `/wards`, `/organizations`, `/contacts` | CRUD + state; tree/resolve bổ sung |
| HR | `/departments`, `/positions`, `/employees`, `/employee-assignments` | Business scope employee context |
| Security | `/security/users`, `/users`, `/authorization-groups`, `/resources`, `/policies`, `/authorization-logs` | Owner/operator permissions; `authorization-logs` chỉ đọc ở API công bố |
| Workflow | `/workflow-states`, `/lifecycles`, `/business-workflow/{resource}/...` | Transition phụ thuộc persisted state và PBAC |
| Numbering | `/resources/{id}/numbering-series/...` | Config và validate-series |
| Product | `/product-groups`, `/product-uoms`, `/products`, `/products/{id}/uom-conversions`, `/products/{id}/combo-items` | `PUT` replace collections |
| Product lookup | `/products/options`, `/products/details/resolve`, `/products/relations-preview`, ... | Result PBAC-filtered, batch bounded |
| Product image | `GET/POST/DELETE /products/{id}/image` | File binary riêng; multipart POST field `image` |
| Pricing | `/price-lists`, `/price-lists/{id}/items` | Quản lý price list, không có sales order pricing endpoint ở snapshot |
| Import | `POST /products/import-jobs`, `GET /import-jobs`, `/import-jobs/{id}/rows` | Upload CSV/XLSX và lưu tiến độ; thực thi synchronous |
| Display | `/display-settings/runtime`, `/definitions`, `/values`, `PATCH /display-settings`, `POST .../actions/reset` | Inheritance System/Organization |

Dùng catalogue generated hoặc file OpenAPI để biết **đúng từng endpoint**; bảng này là bản đồ, không cam kết mọi resource có GET/DELETE giống nhau.

## 4. Endpoints dễ sai khi bám P1

- `GET/POST/DELETE /api/v1/products/{id}/image`: GET trả binary có `Content-Type`, `Cache-Control: private,no-store` và `nosniff`; POST upload `multipart/form-data`, DELETE tháo ảnh. Ảnh được authorise bằng `product.view` hoặc `product.edit`. Source [`ProductImages.php`](../../backend/app/Controllers/Api/ProductImages.php).
- `/api/v1/business-workflow/{resource}/states`, `/{id}/options`, `/{id}/actions/{action}`: trạng thái native hoặc lifecycle, option theo PBAC action và persisted target; `execute` chỉ dùng khi **đã gắn lifecycle**, còn set-state native đi qua đường resource-specific. Source [`BusinessWorkflowActions.php`](../../backend/app/Services/BusinessWorkflowActions.php).
- `/api/v1/contacts`: URL vẫn là Contact CRUD một bảng nhưng resource authorization phụ thuộc `owner` theo `organization_contact` / `employee_contact`, **không còn** `contact.*`. Source [`ContactAuthorizationRegistry.php`](../../backend/app/Authorization/ContactAuthorizationRegistry.php).
- `/api/v1/products/import-jobs`: POST đang parse và xử lý job trong cùng request; không suy ra protocol `202 Accepted`/poll background.

## 5. Generated clients và tương thích

Canonical contract là [`contracts/openapi/openapi.yaml`](../../contracts/openapi/openapi.yaml); generated TypeScript ở [`frontend/src/api/generated`](../../frontend/src/api/generated). Khi cập nhật API chạy từ `frontend/`:

```sh
pnpm run openapi:lint
pnpm run openapi:generate:check
pnpm run type-check
```

**Giới hạn:** match route/OpenAPI không kiểm soát breaking change request/response. Cần schema validator và contract test cho nullable/required, multipart/binary, error codes, pagination, trạng thái 400/401/403/404/409/422 và i18n. FE adapter [`api.ts`](../../frontend/src/services/api.ts) có cơ chế mutex/rotate CSRF, tuyệt đối tránh thay bằng axios caller tự gắn token rời rạc.

## 6. Quy tắc API khi cải tiến

Định nghĩa acceptance trước; update route, controller, service, OpenAPI, generated SDK, service client, UI và tests đồng thời. Đối với operations PBAC, negative tests phải đảm bảo denied entity không lộ qua list `total`, option/search/resolve và lookup. Nếu thêm/deprecate endpoint phải ghi lịch migration client; không phá silent v1 semver nếu client đang sử dụng.
