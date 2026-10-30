# FE-MODAL-01 — Rà soát và cố định chiều cao modal nhiều tab

> **Ngày:** 2026-10-29 · **Baseline:** `erp_v2.0.0_his03_3_customer_history_modal_fixed.zip`
> **Phạm vi:** Frontend-only; không sửa PHP, API, database, migration, PBAC, Workflow hoặc Routes.
> **Trạng thái:** Source contract PASS; type-check/build/Vitest và browser UAT cần xác nhận trên Windows.

## 1. Kết quả kiểm kê

Đã rà soát `frontend/src` theo các mẫu `role="dialog"`, `role="tablist"`, `detail-tabs`, `security-tabs`, `workflow-tabs`, `NTabs` và trạng thái `activeTab`/`tab`.

| STT | Modal có nhiều tab | Hiện trạng trước thay đổi | Cách xử lý |
| --- | --- | --- | --- |
| 1 | Customer detail (`features/customer/pages/CustomerDetailView.vue`) | Đã cố định chiều cao trong HIS-03.3 | Giữ nguyên code và regression đã PASS |
| 2 | Product detail (`components/products/ProductDetailModal.vue`) | Chỉ có `max-height`; grid phụ thuộc nút quay về sản phẩm cha | Dùng lớp CSS chung; flex; body cuộn; reset scroll khi đổi tab |
| 3 | Employee detail (`features/people/pages/PeopleEmployeesView.vue`) | Cuộn toàn bộ dialog, chiều cao thay đổi theo nội dung | Dùng lớp CSS chung; bốn tab chia sẻ một vùng cuộn; header/footer cố định |
| 4 | Lifecycle detail (`features/workflow/pages/LifecyclesView.vue`) | Chỉ có `max-height` | Dùng lớp CSS chung; cuộn body và reset khi đổi tab; không sửa modal State/Transition con |
| 5 | Security Users detail (`features/security/pages/SecurityUsersView.vue`) | Chỉ có `max-height` | Dùng lớp CSS chung; ba tab có vùng cuộn độc lập |
| 6 | Security Resources detail (`features/security/pages/SecurityResourcesView.vue`) | Chỉ có `max-height` | Dùng lớp CSS chung; hai tab Resource/Actions giữ footer ổn định |
| 7 | Security Authorization Groups detail (`features/security/pages/SecurityAuthorizationGroupsView.vue`) | Chỉ có `max-height` | Dùng lớp CSS chung; ba tab không làm dịch chuyển nút lưu và footer |

**Không thuộc nhóm modal nhiều tab:** `OrganizationsView.vue` (tab trong *panel* split-view của trang); `DisplaySettingsView.vue`, `DisplayPreferencesView.vue` (tab cấp trang); `AppShell.vue` (workspace tabs); các form Create/Edit, modal Contact, Product Relations và dialog một nội dung. Các thành phần này giữ chiều cao tự nhiên.

## 2. Quy tắc giao diện dùng chung

- Bổ sung lớp CSS opt-in `tabbed-detail-workspace` tại `frontend/src/styles/global.css`. Chỉ gắn vào **modal chi tiết nhiều tab**, không gắn vào form tạo/sửa.
- Chiều cao ổn định trên desktop: `min(760px, calc(100dvh - 40px))`, fallback `100vh`. Modal tự co lại ở màn hình thấp và giữ khoảng đệm 20px phía trên/dưới theo backdrop hiện tại.
- Header, thanh tab, điều hướng cha (nếu có) và footer không nằm trong vùng cuộn.
- Chỉ `.modal-card__body`, `.detail-body` hoặc `.tabbed-detail-workspace__body` cuộn; `min-height: 0`, `min-width: 0`, không cuộn toàn bộ khung modal.
- Tabs dài có thể cuộn ngang **trong thanh tab**; bảng rộng có thể cuộn **trong body**, không làm phình toàn bộ modal.
- Product, Lifecycle, Employee dùng `:key` theo tab để tạo lại body và trả scroll về đầu; Security tabs vốn dùng `v-if`, đã tự tạo lại body.
- Customer đã đáp ứng chuẩn này từ HIS-03.3 và không cần thay đổi thêm.
- Mọi modal con và form Create/Edit vẫn dùng chiều cao tự nhiên.

## 3. Tương thích và lưu ý

- Không sửa quyền, request/response, định tuyến, Workflow, logic lưu form hay bộ lọc danh sách.
- Modal có thể mở dialog con (Employee Assignment, Lifecycle State/Transition, Security edit). Shared ContactFormModal được bọc trong `Teleport to="body"` tại ContactList, tránh bị cắt bởi vùng cuộn modal Employee/Customer; vẫn cần kiểm tra thứ tự overlay/focus trên trình duyệt.
- Product detail dùng bố cục flex để nút quay về sản phẩm cha tùy chọn không làm chiếm sai hàng body/footer của grid cũ.
- Một số test refactor V2 cũ cố định *byte-level* template lịch sử; nếu chạy lại các lệnh kiểm tra snapshot lịch sử này, thay đổi giao diện được phê duyệt có thể khiến test đó FAIL. Không tự cập nhật hash quá khứ. Dùng kiểm tra UI mới và regression hiện hành làm bằng chứng cho thay đổi này.
- Customer modal từ `/customers` và `/system/history` vẫn dùng một component gốc, không tạo bản sao.

## 4. Kết quả QA tại môi trường tạo patch

| Kiểm tra | Kết quả |
| --- | --- |
| `node --test scripts/fe-tabbed-modal-height.contract.test.cjs` | **9/9 PASS** |
| Kết hợp thêm HIS-03.3 Customer modal contract | **12/12 PASS** |
| Contact modal Teleport ra ngoài vùng cuộn | **Source contract PASS** |
| `node scripts/check-imports.cjs` | **267 nguồn TS/Vue, 930 import edges, 0 lỗi** |
| `git diff --check` | **PASS** |
| Git apply/reverse trên source HIS-03.3 sạch | Kiểm tra lúc đóng gói |
| Frontend `pnpm run type-check` và `pnpm run build` | **NOT_RUN** — source ZIP không kèm `node_modules` |
| Vitest và kiểm tra desktop/mobile thực tế | **NOT_RUN** — chờ Windows |
| MariaDB/HTTP UAT | **Không cần** cho patch frontend-only |

## 5. Lệnh kiểm thử trên Windows

```powershell
cd D:\XAMPP\htdocs\erp\frontend
node --test scripts/fe-tabbed-modal-height.contract.test.cjs scripts/his03-3-customer-history-modal.contract.test.cjs
pnpm exec vitest run src/features/history/__tests__/businessHistoryPanel.spec.ts src/features/history/__tests__/historyMonitorModal.spec.ts src/features/history/__tests__/historyPresentation.spec.ts
pnpm run type-check
pnpm run check:imports
pnpm run build
```

**UAT cần thực hiện:** ở desktop 1366×768 và mobile 390×640, mở lần lượt Product, Employee, Lifecycle, Security Users, Security Resources, Authorization Groups và Customer detail. Chuyển từ tab ít nội dung sang tab dài (Employee Assignments/Contacts; Lifecycle Transitions; Security Permissions; Customer History). Xác nhận modal không thay đổi chiều cao theo tab; header/tabs/footer luôn nhìn thấy; chỉ body cuộn; không tràn ngang toàn modal. Kiểm tra thêm mở/đóng dialog con, Esc, focus và lưu form khi có quyền.

## 6. Chuẩn áp dụng cho P2-SUP

Khi xây dựng Supplier detail với nhiều tab, tái sử dụng `tabbed-detail-workspace` cùng một body cuộn thay vì sao chép CSS chiều cao. **Form New Supplier một màn** không áp dụng quy tắc chiều cao cưỡng bức. Không tuyên bố nghiệm thu trình duyệt khi UAT chưa chạy.
