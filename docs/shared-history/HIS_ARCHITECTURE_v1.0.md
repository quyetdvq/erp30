# DinkERP Shared System History — Architecture & Data Contract

> **Mã:** HIS-ARCH-001 · **Phiên bản:** v1.0.0 · **Ngày:** 2026-10-29
> **Product policy:** APPROVED by PO in conversation: **HIS-POL-001 selective milestone audit**.
> **Implementation state:** HIS-01 CODE CANDIDATE; DEV migration/HTTP negative PBAC/production hardening **NOT_RUN**.
> **Technical authorities:** `docs/development-standards/02_SQL_FIRST_PBAC_PERFORMANCE_STANDARD.md` SQL-07/10/12/14; `03_MODULE_DELIVERY_GATES.md` G0–G7; `06_ROUTE_ARCHITECTURE_STANDARD.md`.

## 1. Mục tiêu và phân biệt hai loại History

- **Business System History:** ai khởi tạo, phê duyệt, thay đổi trạng thái, chỉnh sửa dữ liệu đã sử dụng, thay đổi dữ liệu quan trọng — dữ liệu audit **đã biên tập, append-only**.
- **Supplier Transaction History (UR-SUP-002):** PO, GR, Purchase Return, chi/thanh toán lấy từ các nguồn PUR/INV/FIN và kiểm tra Company + document VIEW. **Không triển khai bằng** `business_history_events` và vẫn **DEFERRED** theo SUP-G1.
- Các hệ `authorization_logs`, `configuration_audit_events`, M11 notification outbox tiếp tục có trách nhiệm riêng; không merge hay di dời dữ liệu.
- `SENSITIVE_ACCESS` (xem số ngân hàng đầy đủ) cần **Security Audit có quyền/retention riêng**. HIS-01 không mở API xem bank và không giả vờ đã giải quyết nhu cầu này.

## 2. HIS-POL-001 — Chính sách ghi ít nhưng đủ dấu mốc

| Tình huống | Ghi? | Nguồn sự thật |
|---|---|---|
| Khởi tạo Customer/Supplier, kể cả DRAFT | Một `CREATE` | Business Service, sau insert thành công, trong cùng transaction |
| Chỉnh sửa DRAFT, lưu nháp, request PATCH không thay đổi nội dung | Không | `HistoryPolicy` kiểm tra operational phase và diff |
| Workflow chuyển trạng thái bất kỳ thành công | Một `STATE_CHANGE` | Canonical transition; không suy `APPROVE` từ tên status |
| Workflow có hành động submit/approve/reject rõ nghĩa | `SUBMIT`/`APPROVE`/`REJECT` | Chỉ action code tương ứng đã được duyệt; không được giả danh qua `setstate` |
| Đối tượng đạt trạng thái vận hành lần đầu | Checkpoint cùng state/approval event | Bản snapshot **đã loại PII** ở thời điểm commit |
| Chỉnh sửa sau khi đang vận hành | Một `UPDATE` cho mỗi business mutation có diff | Whitelist trường, gộp một lần ghi, không nhân theo số trường |
| Đổi dữ liệu ngân hàng, kể cả ở DRAFT | `SENSITIVE_CHANGE`, **không lưu bank plaintext** | Module Bank riêng, khi SUP hoàn thành |
| Xóa/khôi phục hoặc quyết định Workflow | Event typed nếu có action nguồn thật | Không tạo fake event khi module chưa có thao tác này |
| Xem bank đầy đủ | Security Audit riêng — NOT_IMPLEMENTED | Không ghi chung vào Business History |

**Lifecycle động:** operational phase là **chính sách riêng do backend sở hữu**, không thể suy từ `initialState()`, tên state hay `is_final`. Trong HIS-01 `customer: ['ACTIVE']` theo điều kiện vận hành hiện có; `supplier: ['ACTIVE']` là candidate trước SUP implementation. Nếu người quản trị thay Lifecycle và có trạng thái vận hành khác (`APPROVED`, `ENABLED`...), phải mapping + regression + thay đổi có kiểm soát trước khi bật thao tác. Mọi transition vẫn ghi dù ở DRAFT; các PATCH trong trạng thái chưa được map là draft-like và không ghi. Không tự xếp `PENDING` thành approved/operational.

### 2.1 Nội dung sự kiện

- `CREATE`: metadata, status ban đầu, actor/time và create-once marker; **không copy toàn bộ hồ sơ**.
- `UPDATE`: chỉ trường có khác biệt, key allowlist. `phone`, `name`, `email`, `tax_code`, `address`, Province/Ward và Contact IDs được gắn `changed:true` không lưu giá trị cũ/mới. Code nhóm, loại và cờ `all_companies` được phép lưu values đã kiểm soát.
- `company_ids`: theo dõi thay đổi như **set**, bỏ qua đảo thứ tự; chỉ ghi count trước/sau, không đưa Company IDs vào lịch sử MASTER. Không thể từ bản ghi này tái tạo đầy đủ allowlist quá khứ; yêu cầu legal evidence cao hơn cần ADR/secure snapshot riêng.
- `STATE_CHANGE`: lưu state before/after + action; nếu vào operational state thêm checkpoint chọn lọc `*_present`, mã không nhạy cảm, số lượng company. **Không phải snapshot pháp lý đầy đủ**.
- `SENSITIVE_CHANGE`: chỉ `bank_account: { changed: true }` + action set/replace/remove, **không** có ciphertext/key/last4/secret/raw metadata.

## 3. Bảng `business_history_events` (migration 000026)

| Field | Type | Quy tắc |
|---|---|---|
| `id` | BIGINT unsigned PK | Thứ tự commit xấp xỉ qua auto-increment; UTC `created_at` |
| `resource_type`, `resource_id` | VARCHAR(50), BIGINT unsigned | Định danh polymorphic; **không** FK một resource cụ thể |
| `event_type`, `action_code` | VARCHAR(30/50) | Enum logic trong recorder, không nhận HTTP |
| `status_before`, `status_after` | VARCHAR(50) nullable | Chỉ record trạng thái nguồn thật |
| `owner_domain`, `owner_organization_id` | VARCHAR(20), BIGINT nullable | HIS-01 chỉ MASTER/NULL; ORGANIZATION mở riêng với provider được duyệt |
| `changes_json`, `checkpoint_json` | LONGTEXT nullable | JSON đã chuẩn hóa, ≤32 KiB mỗi payload từ recorder; no raw entity JSON |
| `actor_user_id`, `actor_type` | BIGINT nullable, VARCHAR(12) | HIS-01 chỉ USER, sau đó mở SYSTEM/JOB theo ADR/authorization |
| `request_id`, `created_at` | VARCHAR(128), DATETIME UTC | Từ RequestMetadata, không tin client actor |
| `create_marker` | VARCHAR(8) nullable | `CREATE` chỉ cho sự kiện create; unique `(resource_type,resource_id,create_marker)`, còn lại NULL |

Indexes: unique create-once, `ix_history_resource(resource_type,resource_id,id)`, actor+id, owner+id, request_id. DBPrefix qua CI4 `Forge` và query whitelist. `down()` chặn nếu có dữ liệu. Không sửa schema P1/CUS. No backfill sự kiện cũ, không tự giả lịch sử từ `updated_at`.

**Append-only:** application chỉ có insert và GET; không có HTTP PUT/PATCH/DELETE History. `CREATE` idempotency được DB chặn. Để bảo vệ **chống DBA/DB-account sửa trực tiếp**, trước production phải có biện pháp DB-level như trigger UPDATE/DELETE deny hoặc account/grants riêng, backup/restore & tamper evidence được DBA/Security review. **HIS-01 source không tuyên bố immutability chống thao tác DB có đặc quyền.**

## 4. Tích hợp transactional

```text
CustomerService (owns physical transaction)
  validate persisted owner/PBAC/Workflow -> business DML
  -> HistoryRecorder.created/updated/transitioned (same BaseConnection, transDepth > 0)
  -> COMMIT; History insert fails => rollback business DML
```

Chỉ canonical Business Service gọi recorder. Không có public POST History. Các background jobs/imports khi tham gia phải gọi lại chính service hoặc adapter có actor_type và permission provenance được duyệt, không tự insert raw JSON. Không phát tán event sau COMMIT rồi mới cố ghi audit. Không coi UI `allowed_actions` là quyền.

## 5. Đọc History và PBAC

- HIS-01 expose **duy nhất** `GET /api/v1/history/customers/{id}` với Shield session, `page/page_size`, default 25/cap 50, `meta.total` và `id DESC`. Chỉ adapter Customer được review; **không có global list**, không endpoint generic tùy ý chỉ resource.
- `HistoryReadService::customer` mở **một REPEATABLE READ snapshot**, gọi `HistoryParentViewPort`. Adapter `App\Bootstrap\CustomerHistoryVisibility` biên dịch `AuthorizationSqlScopeCompiler(CustomerScopeProvider)` + `CustomerSql::detail(id, scope)` + canonical `canView('customer', row)` **trước** History COUNT/ROWS. Lớp `History` không import `Customer`, tránh module dependency cycle khi `CustomerService` dùng `HistoryRecorder`. Nếu không VIEW => no-leak 404. Không gửi list/count trước khi xác thực parent.
- History SQL chốt `resource_type='customer'`, `resource_id` bind và `owner_domain=MASTER`. Các trường nhạy cảm không vào response; event actor ID chỉ là metadata sau parent VIEW.
- Với Supplier về sau: thêm endpoint riêng `history/suppliers/{id}`, SQL-first SupplierScopeProvider + exact persisted Supplier VIEW; `SENSITIVE_CHANGE` cần quyết định riêng về quyền truy cập metadata và xử lý đúng PBAC; không chỉ đổi route `customer` thành `supplier`.
- Không lập global History statistics/exports/search khi chưa có kế hoạch authorized SQL scope đa resource; tránh count leak qua Company hay trạng thái PBAC.

## 6. Data retention/performance

- Mỗi mutation trả tối đa 1 row business UPDATE. Không ghi DRAFT edits, no-op changes hoặc 1 row/field. Limit JSON 32 KiB.
- History là audit event, không phải full version/temporal DB. Chưa có migration partition/archive tự động; retain-to-archive là **OPEN TECHNICAL POLICY** trước production, không xoá ngầm bằng cron.
- Query COUNT/PAGE dựa `(resource_type,resource_id,id)`; đo EXPLAIN trên DEV và dữ liệu tăng trưởng. Nếu hot object lớn, version tiếp theo có thể dùng keyset cursor thay OFFSET theo SQL-10, nhưng phải cập nhật contract/UI cùng lúc.
- Audit records từ trước rollout **không tồn tại**; UI thông báo rõ.

## 7. Acceptance & không được đánh dấu PASS khi chưa chạy

1. Tạo Customer (kể cả DRAFT) => 1 CREATE; rollback Customer nếu History INSERT fail; unique create marker.
2. 15 lần sửa DRAFT => 0 UPDATE; đổi trạng thái thật => STATE_CHANGE; ACTIVE edit có diff => 1 UPDATE; PATCH không đổi => 0 UPDATE.
3. Không lưu PII/bank raw; payload không chứa trường ngoài whitelist; việc cập nhật nhiều trường cho 1 UPDATE.
4. Actor A VIEW đúng Customer đọc được; Actor B không VIEW không thể đọc ID, COUNT hoặc sự kiện; Lifecycle membership hiệu lực với snapshot, không lộ thông tin khi xóa policy.
5. Không ảnh hưởng `customer_interactions`, notifications, contact, P1 routes/fixtures; 180 frozen + 1 approved History route.
6. Migration 000026 trên DEV backup + EXPLAIN + HTTP/BROWSER UAT; DBA review append-only DB guard; production **BLOCKED** cho tới khi có đủ bằng chứng.
