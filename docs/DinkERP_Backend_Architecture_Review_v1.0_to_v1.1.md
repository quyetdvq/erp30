# DinkERP — Architecture Standard Review & Change Record

**Tài liệu nguồn:** `DinkERP_Backend_System_Architecture_Standard_v1.0.md`  
**Tài liệu sau rà soát:** `DinkERP_Backend_System_Architecture_Standard_v1.1.md`  
**Ngày:** 22/10/2026  
**Tính chất:** Rà soát/hiệu chỉnh thiết kế; **không sửa mã nguồn hoặc database**.  
**Trạng thái:** Candidate Baseline cần phê duyệt, không phải thông báo hệ thống đang tuân thủ đầy đủ.

## 1. Kết luận

Tư tưởng cốt lõi của v1.0 **được giữ**: Pragmatic Modular Monolith, một backend CodeIgniter 4, một MariaDB, ranh giới module theo ownership, Controller → Service → Repository khi phù hợp, tối ưu SQL trước cache, không lạm dụng DDD/ports/interface và chuyển đổi legacy theo từng use case. Không phát hiện lý do kiến trúc để chuyển sang microservices hoặc viết lại backend.

Tuy nhiên, cần sửa một số đoạn **dễ bị diễn giải sai thành quyết định runtime**, và một số đoạn **chưa nhất quán** với chính tài liệu hoặc code v1.2.2. Bản v1.1 xử lý các điểm này và bổ sung quy tắc có thể dùng trong design review/CI.

## 2. Danh sách vấn đề và hiệu chỉnh

| ID | Mức độ | Phân loại | Điểm v1.0 | Nguy cơ nếu áp dụng nguyên văn | Cách sửa trong v1.1 |
|---|---|---|---|---|---|
| R01 | Cao | Thiếu điều kiện | §7.1 PBAC `NO_MATCH → ALLOW` | Cấp quyền khi Base Permission, employee, Resource Status hoặc resolver không hợp lệ | §7.1 ghi đủ mandatory gates trước policy lanes |
| R02 | Cao | Dễ hiểu sai | §7.1 precedence policy | `DENY` một lane bị áp như global deny, hoặc applicable `ABSOLUTE_DENY` bị mất | §7.1 và Phụ lục B phân biệt per-lane, actor/assignment aggregation |
| R03 | Cao | Chưa nhất quán | §3.1 dùng cạnh Authorization ↔ EmployeeStructure nhưng §5.3 cấm vòng | Có thể tạo circular import/service constructor | §3.1, §5.3 mô tả public DAG; read port tại consumer, adapter ở Bootstrap |
| R04 | Cao | Chưa rõ operation model | §9.1 “mỗi luồng ghi có một transaction owner” | Developer bọc `BEGIN` quanh mọi call, nested vào Numbering allocator | §9.1 `atomicStatement` / `ownsTransaction` / `participatesInOwner` / `readSnapshot` |
| R05 | Cao | Chưa rõ behavior | §6.4 PBAC recheck sau SQL page | `meta.total` hoặc page bị lệch khi post-filter denied rows | §6.4 cấm post-filter không sửa total/page; yêu cầu SQL equivalence hoặc fallback đúng |
| R06 | Cao | Chưa rõ owner | §5/§10 “mỗi bảng có business owner”, §12 generic job persistence | Bảng `import_jobs`/audit bị coi là shared SQL mutable tự do | §5.2, §10.1, §12.1 bổ sung platform-technical table owner |
| R07 | Trung bình | Chưa rõ consistency | §14.1 yêu cầu cùng session toàn operation | Áp dụng vô lý cho read ngoài transaction; hoặc bỏ quên same-session cho locks/writes | §14.1 phân biệt read context với transactional participants |
| R08 | Trung bình | Chưa rõ thẩm quyền | §0.1 coi ADR cao hơn standard nhưng chưa giới hạn thay product contract | ADR kỹ thuật vô tình ghi đè FRS/DBD/OpenAPI | §0.1 xác định approved change đúng thẩm quyền, conflict record |
| R09 | Trung bình | Hình thức hóa | §1/§4/§16 mặc định luôn Controller, Repo và API | Module nội bộ hoặc tính toán thuần bị tạo class/endpoint không cần | §2, §4, §16 cho N/A phù hợp, giữ boundaries cốt lõi |
| R10 | Trung bình | Chuẩn tài liệu chưa nhất quán | §3.2 và §16: `MODULE.md` có thể ở central hoặc module | Dev mới không biết phải lưu ở đâu, code cũ bị đòi rewrite hình thức | Root mới luôn có `MODULE.md`, legacy dùng central registry chuyển tiếp |
| R11 | Trung bình | Chưa rõ deploy | §10.3 forward-only vs migration `down()` | Hiểu nhầm cấm `down()`; hoặc rollback DB phá dữ liệu | Mô tả expand/compatible/backfill/contract, không sửa migration applied |
| R12 | Trung bình | Sai kỳ vọng về gate | §15.2 nêu 5 PASS, guard 7 cảnh báo và DB chưa chạy | Coi baseline test xanh đầy đủ; chặn PR bằng false-positive | Nêu status PASS/FAIL/NOT_RUN/BLOCKED_ENV, classification & negative fixtures |
| R13 | Trung bình | Khuôn mẫu chưa đủ rõ | §16 yêu cầu API `CONTRACT_READY` cho module | Internal-only use case bị buộc tạo API; surface pending bị tự phát minh | API N/A khi thực sự không có HTTP, pending vẫn STOP |
| R14 | Trung bình | Quản lý legacy | §17–20 chuyển đổi nhưng chưa có conformance states | Phê duyệt tài liệu bị hiểu là hệ thống đã được refactor | Phụ lục E `COMPLIANT`, `LEGACY_APPROVED`, `GAP_TO_FIX`, `NOT_VERIFIED` |
| R15 | Trung bình | Vòng đời nghiệp vụ | Phụ lục B P1 invariants | Quy tắc P1 như chưa có Variant bị hiểu là vĩnh viễn | Có versioned overlay theo phase; chỉ change bằng approved contract |
| R16 | Thấp | Thiếu công cụ | Phụ lục A và template MDR 1.0 | Thiếu technical owner, transaction modes và gate evidence | Đồng bộ template MDR v1.1 + checklist 27 câu |

> Các mục “dễ hiểu sai/thiếu điều kiện” không đồng nghĩa runtime hiện tại có lỗi. Đây là điểm cần sửa **trong văn bản baseline**, không phải bug report.

## 3. Bằng chứng đối chiếu mã nguồn (v1.2.2)

- `backend/app/Modules/Authorization/Decision/Application/PbacDecisionEngine.php`: kiểm tra Base Permission, employee ACTIVE, effective assignments, resource inactive và per-lane policy (`evaluateLane`); tổng hợp actor lane với assignment lanes.
- `backend/app/Modules/Numbering/Allocation/Application/AutoNumberingCounterAllocator.php`: `allocate()` từ chối `transDepth > 0`, mở transaction trước callback và commit sau persistence.
- `backend/app/Infrastructure/Mutation/OperationContext.php` và `MutationContext.php`: actor, evaluationTime, requestId, systemActor đã có.
- `backend/app/Modules/Product/Product/ProductRepository.php` và `backend/app/Modules/Pricing/PriceList/PriceListRepository.php`: snapshot list từ chối nested transaction.
- `backend/app/Infrastructure/Import/Persistence/ImportJobRepository.php`, `ImportJobRowRepository.php`: technical persistence cho `import_jobs` và `import_job_rows`.
- `.codex/skills/dinkerp-implementation/references/project-invariants.md`: các guardrails P1; không thay cho approved full business contracts.

**Giới hạn:** Đây là source/document review; không chạy full MariaDB/Shield integration, concurrency benchmark hoặc production performance. Những nhận định trên là bằng chứng cấu trúc/logic thấy trong code, không xác nhận toàn bộ runtime.

## 4. Những gì cố ý không thay đổi

- Không đổi số lượng 12 root module hiện tại, URL `/api/v1`, OpenAPI hoặc DB schema.
- Không đổi PBAC precedence, NUMBERING transaction/collision, P1 Product Group/Product/Combo/Pricing invariants.
- Không thêm global UnitOfWork, ORM, service bus, DI container, cache phân quyền xuyên request hoặc bắt buộc có four-layer Clean Architecture.
- Không yêu cầu flatten Authorization/Workflow/Numbering hoặc rewrite compatibility shim có test.

## 5. Điều kiện để áp dụng thành baseline chính thức

1. Approve kiến trúc AR-01..AR-13 và xác định owner/reviewer cho tiêu chuẩn.
2. Approve/ghi nhận ownership của bảng nghiệp vụ và kỹ thuật, cùng public entrypoints của 12 module.
3. Phân loại 7 cảnh báo architecture guard và bật CI rule nhận diện **vi phạm mới** với whitelist có ràng buộc.
4. Cung cấp pipeline MariaDB/Shield để kiểm thử thay đổi PBAC, transaction, Numbering, Workflow.
5. Ban hành template MDR v1.1 cho mọi root module mới; mở central conformance register cho 12 module legacy.
6. Duyệt riêng những quyết định mở: Product COMBO + AUTO transaction redesign, read-only platform seams, cross-module SQL projection và performance thresholds.

**Đề xuất:** phê duyệt v1.1 như *architecture policy baseline*, sau đó xác lập *implementation conformance baseline* theo từng module; không đặt cả hai trạng thái này bằng nhau.
