# DinkERP — Quyết định phê duyệt baseline kiến trúc P1

**Ngày ghi nhận:** 19/10/2026  
**Trạng thái quyết định:** APPROVED — theo xác nhận trực tiếp của chủ dự án trong cuộc trao đổi.  
**Loại thay đổi của gói này:** tài liệu quản trị kiến trúc; không phải patch source hoặc biên bản nghiệm thu runtime.  
**Tài liệu tham chiếu:** `DinkERP_P1_Target_Architecture_v1.0.md`.

## 1. Quyết định đã được phê duyệt

Thông điệp phê duyệt của chủ dự án:

> Chốt: Đã phê duyệt bản đồ 13 module, data ownership và dependency rules
> ; Dùng Product Media làm slice thí điểm, hoàn thiện platform boundaries rồi mới nhân rộng sang Api030, Api050 và các module còn lại.

Phạm vi phê duyệt gồm bản đồ 13 module, quyền sở hữu dữ liệu, quy tắc phụ thuộc và thứ tự triển khai nêu trên. Đây là baseline kiến trúc có hiệu lực cho các đề xuất và patch tiếp theo trong phạm vi này.

Phê duyệt này không được diễn giải thành nghiệm thu code, runtime, bảo mật, hiệu năng hoặc tự động phê duyệt mọi tên class, chi tiết API/DB change và điều chỉnh hành vi phát sinh khi triển khai.

## 2. Bản đồ module đã chốt

| Nhóm | Mã | Module |
|---|---|---|
| Foundation / Core Platform | M01 | Foundation |
| Foundation / Core Platform | M02 | ResourceCatalog |
| Foundation / Core Platform | M03 | Workflow |
| Foundation / Core Platform | M04 | Numbering |
| System Administration | M05 | Identity |
| System Administration | M06 | Authorization |
| System Administration | M07 | Settings |
| System Administration | M08 | Audit |
| Business Modules | M09 | Organization |
| Business Modules | M10 | EmployeeStructure |
| Business Modules | M11 | Contact |
| Business Modules | M12 | Product |
| Business Modules | M13 | Pricing |

Tổng cộng 13 module. Product Media là feature của M12 Product, không phải module thứ 14. SharedKernel, Infrastructure, Integration/Bridges và Frontend AppShell là các gói/nền tảng kỹ thuật, không làm tăng số module chức năng.

Data ownership và dependency rules chi tiết kế thừa nguyên văn từ tài liệu kiến trúc v1.0; bản ghi này không tự phân phối lại bảng, field, keyspace hoặc quyền ghi.

## 3. Thứ tự triển khai có hiệu lực

**Baseline/test gates → Product Media pilot → hoàn thiện platform boundaries → mở rộng Api030, Api050 → các module còn lại.**

Baseline/test gates là điều kiện an toàn để triển khai, không phải một lần xin phê duyệt lại bản đồ module.

Product Media chỉ mở một lát cắt nhỏ từ API qua application, ports, persistence/storage và kiểm thử. Việc sửa hẹp `Api050` để chuyển tiếp shared Product access/read capability được phép đưa vào phạm vi kỹ thuật của pilot; không được lấy đó làm lý do tách toàn bộ Api050 trước khi pilot được nghiệm thu.

Sau pilot, platform boundaries bao gồm việc hoàn thiện composition, Identity/Avatar, ResourceCatalog, Audit và các read/context contracts của Authorization/Workflow. Mỗi phần phải có phạm vi, contract và evidence riêng; không viết lại các core đã có.

## 4. Quy tắc thực hiện kế thừa

- Một implementation chuẩn cho mỗi rule; HTTP, import và CLI không được đi qua các bộ invariant khác nhau.
- Module khác không tự ghi bảng thuộc owner; dùng contract/port được công bố. Infrastructure thực thi abstraction phía trong; Domain không phụ thuộc Infrastructure.
- Product không phụ thuộc Pricing; canonical business/application code không gọi ngược Api*Service.
- Bootstrap dựng dependency; giữ connection, context, transaction/lock/revalidation đúng semantics của use case.
- Pure structural patch không đổi API, schema, permission, workflow, storage layout hoặc frontend route. Bug fix, performance change và thay đổi audit/time semantics có phạm vi và acceptance riêng.
- Legacy façade chỉ bỏ khi đã kiểm consumer và compatibility; không miễn trừ architecture guard theo wildcard.

## 5. Quản lý thay đổi baseline

Thay đổi số lượng/rành giới module, data owner hoặc dependency direction phải có bản ghi quyết định mới, nêu lý do, tác động và migration path; không sửa ngầm trong patch.

Các mô tả “đề xuất” trong bản v1.0 được giữ để bảo toàn lịch sử. Với đúng phạm vi ở mục 1, đọc bản v1.0 cùng bản ghi phê duyệt này. Chi tiết triển khai chưa được quyết định vẫn là đề xuất.

Các kế hoạch R0–R8/A0–A6 trước đó là backlog tham khảo. Khi có điểm khác với thứ tự đã chốt, bản ghi này ưu tiên về quyết định phê duyệt; không coi mọi đợt hoặc tên PR trong tài liệu cũ là đã được nghiệm thu.

## 6. Trạng thái thực thi

| Hạng mục | Trạng thái trong gói này |
|---|---|
| Bản đồ 13 module, data ownership, dependency rules | APPROVED |
| Lựa chọn Product Media làm pilot và thứ tự triển khai | APPROVED |
| Chi tiết file/class/test của pilot | Bản phân rã kỹ thuật v0.1 đi kèm; chưa triển khai |
| Thay đổi source, migrations, runtime configuration | Không thực hiện trong lượt ghi nhận này |
| PHPUnit/PHPStan/HTTP/DB/frontend verification mới | Không chạy trong lượt ghi nhận này |
| Release/checkpoint Git trên máy triển khai | Không tạo hoặc xác minh trong lượt này |

Không cập nhật `docs/as-built` như thể kiến trúc đích đã triển khai. Bản ghi này được xuất thành artifact, chưa được commit vào repository của dự án.

## 7. Định danh baseline

- Source archive: `erp_v1.1.8.zip`.
- Source SHA-256: `59835ace6f4dd45d207f10db4e598c1acaae3f85779e924974f8e2dbd9d0831d`.
- Architecture v1.0 SHA-256: `ad76c397247f4463f7d814f3e29da6fffacde95198c1ae7a3d1bf1484f1eee33`.
- Bản architecture v1.0 trong gói giữ nguyên byte so với tài liệu được cung cấp.

Vị trí đề xuất khi đưa vào repository: `docs/architecture/target-p1/adr/P1_Architecture_Approval_2026-10-19.md`.
