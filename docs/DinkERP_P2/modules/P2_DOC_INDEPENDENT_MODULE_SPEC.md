# P2 Module DOC — Document & Attachment (Functional + Technical Specification)

> **DinkERP P2 Documentation v1.0.0** · Ngày 2026-10-28 · **Status:** DRAFT — P2 design; chưa được phê duyệt
> **Authority:** 30 P2 UR trạng thái `BASELINE` trong `00_User_Requirements_v1.0.15.md` (SHA-256 `97894e35dbf6c5f641f56574af947cafbc45122a87b9fb189ee7426503448144`).
> **Code authority:** `erp_v1.9.0.zip` = P1 CLOSED BASELINE, chưa có nghiệp vụ P2. Các bảng/API/màn hình P2 trong bộ này là **đề xuất thiết kế**, không phải code đã chạy, trừ khi ghi rõ `P1-EXISTS`.

## Quy tắc đọc độc lập

1. `BASELINE-UR`: nội dung được phê duyệt từ User Requirements, không được đổi nghĩa bằng thiết kế.
2. `P1-EXISTS`: có trong source v1.9.0, chỉ nêu những gì đã kiểm tra bằng source; không thay nghiệm thu live.
3. `P2-DRAFT`: lựa chọn thiết kế đề xuất để triển khai P2, cần duyệt trước khi migrate/implement.
4. `OPEN-DECISION`: dữ liệu nguồn chưa xác định; không tự gán thành cam kết.
5. `NOT-IMPLEMENTED`: các resource/API/bảng P2 đề xuất hiện không nằm trong P1 source.

## 1. Scope & mandatory requirements (verbatim UR)

Các phần dưới đây trích nguyên văn để file module **tự đọc và nghiệm thu độc lập**. Nội dung domain design từ mục 2 trở đi được đề xuất và cần phê duyệt.

### UR-DOC-001 - Attachment theo đối tượng nghiệp vụ
**Priority:** MUST
**Target phase:** P2
**Status:** BASELINE

Cho phép đính kèm file vào:
- Product
- Customer
- Supplier
- Employee
- Purchase
- Sales
- Inventory
- Finance
- Payroll

**Acceptance criteria**
- File xác định được đối tượng nghiệp vụ liên quan.
- Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ.

### UR-DOC-002 - Tìm kiếm và version tài liệu
**Priority:** SHOULD
**Target phase:** P2
**Status:** BASELINE

Hỗ trợ:
- Upload/Download
- Search
- Tag/Category
- Version
- Uploaded By/At

**Acceptance criteria**
- Có thể xem version hiện tại và version trước.
- Tài liệu bị thay thế không làm mất lịch sử.


---


## 2. Design-level functional decomposition (P2-DRAFT)

## Domain behavior

1. Attachment gắn vào Product/Employee và các P2 resource qua `owner_type + owner_id`; mỗi link phải kiểm tra có tồn tại owner hợp lệ và quyền trên owner ở thời điểm thao tác. Không có shortcut từ quyền `document.view` để download tài liệu khi không có quyền owner.
2. Tách **document logical identity**, **file version immutable** và **entity linkage**. Upload version mới tạo record version + current pointer theo transaction, không ghi đè blob của version cũ; link chưa cho phép đọc nội dung nếu owner không authorized.
3. Tìm kiếm: title, category, tag, uploaded_by/at, owner, version. Query phải lọc theo resource/Company authorized **trước** paging/export; closed/inactive owner vẫn giữ document history nhưng không mở quyền.
4. File store chỉ lưu `storage_key` private và content hash; download qua stream sau authorize; tránh public static path, path traversal, MIME spoofing, zip-bomb. Chính sách virus scan, size limit, content retention và allowed MIME cần approval.
5. Attachment của nghiệp vụ nhạy cảm (Finance, Payroll sau này) tuân permission của nguồn. Product/Employee P1 chỉ expose attachment khi action đã có trong Resource catalog và contract được duyệt.

## Main command/query contracts (P2-DRAFT)

- `DocumentLinkService.link(Actor, ownerType, ownerId, documentId)`: resolve authoritative owner, PBAC owner and document, unique link, audit.
- `DocumentVersionService.upload(Actor, documentId, Stream, checksum)`: allowlisted MIME/size, opaque private key, append-only version, transactional metadata; cleanup orphan uploaded object safely on DB failure.
- `DocumentSearchService.list(Actor, filters, page)`: SQL-scoped accessible owner set; no polymorphic owner loopholes.

## Failure/rollback tests

Unauthorized owner download (even with document permission) -> 403/404; concurrent same version number -> one commit; storage success/DB fail -> orphan cleanup; DB success/storage fail -> no current pointer change; malicious filename traversal -> reject; version history kept. 

## Decisions not yet specified by UR

Storage (filesystem/S3), per-type MIME/size, version retention, content scanning, owner/link action privilege boundaries, legal hold and duplicate content handling.

## 3. Proposed database entities (P2-DRAFT)

| Entity | Purpose | Important consistency constraint |
|---|---|---|
| `documents` | Document logical identity | UNIQUE(code); index(status,category_code) |
| `document_versions` | Immutable uploaded versions | UNIQUE(document_id,version_no); index(content_sha256) |
| `document_links` | Polymorphic resource ownership | UNIQUE(document_id,owner_type,owner_id); INDEX(owner_type,owner_id) |
| `document_tags` | Reusable document tags | UNIQUE(code) |
| `document_tag_links` | Document-tag join | UNIQUE(document_id,tag_id) |

## 4. Proposed REST API operations (P2-DRAFT)

| Method | Path | Candidate Resource.Action |
|---|---|---|
| GET | `/api/v1/documents` | `document.view` |
| GET | `/api/v1/documents/{id}` | `document.view` |
| POST | `/api/v1/documents` | `document.create` |
| PATCH | `/api/v1/documents/{id}` | `document.edit` |
| GET | `/api/v1/document-tags` | `document.view` |
| GET | `/api/v1/document-tags/{id}` | `document.view` |
| POST | `/api/v1/document-tags` | `document.create` |
| PATCH | `/api/v1/document-tags/{id}` | `document.edit` |
| POST | `/api/v1/document-tags/{id}/actions/set-state` | `document.setstate` |
| GET | `/api/v1/documents/{id}/versions` | `document.view` |
| POST | `/api/v1/documents/{id}/versions` | `document.edit` |
| GET | `/api/v1/documents/{id}/download` | `document.view` |
| GET | `/api/v1/documents/{id}/links` | `document.view` |
| POST | `/api/v1/documents/{id}/links` | `document.edit` |
| DELETE | `/api/v1/documents/{id}/links/{linkId}` | `document.edit` |

## 5. Proposed frontend screens (P2-DRAFT)

| UI ID | Navigation | Screen behavior |
|---|---|---|
| `P2-UI-DOC-01` | `/documents` | Documents search, tags, linked entity selector, versions, upload/download |

## 6. Complete acceptance registry (BASELINE criteria)

| FR | Acceptance criterion (source wording) | Test ID |
|---|---|---|
| `P2-FR-DOC-001-01` | File xác định được đối tượng nghiệp vụ liên quan. | `P2-AT-DOC-001-01` |
| `P2-FR-DOC-001-02` | Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ. | `P2-AT-DOC-001-02` |
| `P2-FR-DOC-002-01` | Có thể xem version hiện tại và version trước. | `P2-AT-DOC-002-01` |
| `P2-FR-DOC-002-02` | Tài liệu bị thay thế không làm mất lịch sử. | `P2-AT-DOC-002-02` |

## 7. Domain test/approval handoff

- Trước thiết kế DB/API/UI: freeze OPEN-DECISION liên quan, lifecycle, permission, Company authority, immutable posting semantics.
- Trước code: review migration forward-only, tests negative/cross-company/idempotency/rollback và consistency với các P1 services.
- Trước release: chạy runtime trên DEV clone (CI4 + MariaDB + frontend), log test IDs và proof; không nhận PASS chỉ vì có file tài liệu hoặc route.

**State:** toàn bộ đề xuất DB/API/FE của module chưa tồn tại như nghiệp vụ P2 trong v1.9.0. Requirement P2 gốc trong §1 vẫn `BASELINE`.
