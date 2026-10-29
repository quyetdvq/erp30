# DinkERP — Frontend, SQL-first & Delivery Standards v1.1.0

> **29/10/2026 · TECHNICAL REVIEW COMPLETED** · Hệ quy chuẩn bền vững cho SUP/PUR/INV/SAL/FIN/DOC và cải tiến P1. Không thay thế UR/PBAC/Lifecycle đã khóa. **Mã nguồn kèm theo là implementation candidate v1.9.23**, chưa nghiệm thu DB/HTTP.

## Danh mục

- [01 — Frontend UI Standard](01_FRONTEND_UI_STANDARD.md): một list / nhiều trạng thái, row actions, shared controls, page-size theo từng resource, Workflow/Contact UI, i18n, responsive.
- [02 — SQL-first PBAC & Performance](02_SQL_FIRST_PBAC_PERFORMANCE_STANDARD.md): owner scope, sql COUNT/PAGE, search parity/index, không N+1, PBAC hints, scope bound, EXPLAIN/benchmark.
- [03 — Module Delivery Gates](03_MODULE_DELIVERY_GATES.md): G0–G7 và các điều kiện nghiệm thu trước khi phát hành.
- [04 — P1 Remediation v1.9.23](04_P1_REMEDIATION_REPORT.md): thay đổi thực tế/điểm chưa hoàn thành.
- [05 — MariaDB & HTTP UAT](05_DEV_SQL_HTTP_QA.md): câu lệnh kiểm tra và bằng chứng cần nhận trên DEV.
- [06 — API Routing Architecture (PROPOSED)](06_ROUTE_ARCHITECTURE_STANDARD.md): một entrypoint Routes theo domain, bảo toàn route signatures/150 frozen operations, lộ trình migrate legacy.

**Nguồn:** ba tài liệu v1.0.0 được giữ nguyên; bản 1.1.0 chỉnh nội dung không hợp lý và bổ sung v1.9.23. Không sửa code/database của môi trường người dùng. Cần approver chốt mức độ áp dụng cho các module mới nếu thay đổi UR đang khóa.
