# DinkERP — Product Media Pilot: phê duyệt phạm vi v0.1

**Ngày ghi nhận:** 19/10/2026  
**Trạng thái:** APPROVED — phạm vi kỹ thuật v0.1.  
**Owner:** M12 Product / Media.

## Quyết định

Xác nhận trực tiếp của chủ dự án:

> Đồng ý với kế hoạch DinkERP — Product Media Pilot: phạm vi kỹ thuật v0.1

Phạm vi v0.1 đã được phê duyệt, kế thừa bản đồ 13 module, data ownership, dependency rules và thứ tự pilot → platform boundaries → Api030/Api050 → các module còn lại.

Bản gốc v0.1 được giữ nguyên trong `reference/`; bản ghi này bổ sung trạng thái phê duyệt, không sửa ngầm nội dung.

**SHA-256 phạm vi v0.1:** `c32b79eb0adfefb2fb1aae32b45835717832f6df9c3c1a773837b18ae4708806`.

## Ranh giới thực thi

Cho phép triển khai PM-0 đến PM-3 theo gate đã thống nhất. Phê duyệt phạm vi không phải nghiệm thu implementation, bảo mật, runtime hoặc quyền triển khai production.

Giữ HTTP contract, PBAC/workflow, schema, private storage layout và frontend. Không tự thêm CAS, resize, CDN, queue hoặc đổi actor/time semantics trong structural patch. Product Media vẫn là feature của Product, không là module mới.

## Trạng thái thực hiện

PM-0 đã bắt đầu; đã chốt archive baseline, chạy lại các gate tĩnh và 33 storage characterization checks. Chưa đủ service/DB/HTTP/PBAC evidence để đóng PM-0. Chưa bắt đầu PM-1, chưa sửa implementation, chưa tạo Git commit và chưa triển khai.

Chi tiết: `PM0_Status.md`, `characterization_matrix.md`, `evidence/`.
