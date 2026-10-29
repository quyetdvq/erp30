# DinkERP — Quy chuẩn giao diện Frontend cho các module tiếp theo

> **Mã:** FE-STD-01 · **Phiên bản:** 1.1.0 · **Ngày:** 2026-10-29
> **Trạng thái:** **TECHNICALLY REVIEWED v1.1.0 — bộ quy chuẩn định hướng; các thay đổi UR/PBAC phải phê duyệt riêng**.
> **Đối tượng:** Các module P2 tiếp theo (SUP, DOC, PUR, INV, SAL, FIN, PRC, tích hợp) và những lần chỉnh sửa UI P1/CUS/M11 có liên quan.
> **Cơ sở đã đối chiếu:** code Vue/TypeScript DinkERP đến v1.9.22 và bản P1-refactor v1.9.23 (chưa nghiệm thu runtime); các màn hình Employees, Organizations, Product, Customer, Contact, Workflow. Các P2 UR đã phê duyệt giữ quyền ưu tiên cao hơn quy chuẩn này.

## 0. Cách đọc

- **[SOURCE]**: Thành phần/quy ước đã thấy trong mã nguồn hiện tại. Không đồng nghĩa đã nghiệm thu UI trên mọi trình duyệt.
- **[REQUIRED — PROPOSED]**: Quy tắc đề nghị bắt buộc cho các module từ thời điểm được phê duyệt; không hồi tố làm thay đổi hợp đồng P1 đã khóa.
- **[REVIEW]**: Ngoại lệ/chỉ tiêu cần kiến trúc sư và PO phê duyệt trước khi thay cấu trúc hoặc API.

## 1. Kiến trúc màn hình, quy tắc đồng bộ

### FE-01 — Giữ một thiết kế ERP duy nhất [REQUIRED — PROPOSED]

1. Dùng App/Workspace Shell hiện có: breadcrumb, page heading, toolbar, bảng nội dung, modal/drawer, màu/typography/icon, spacing và hành vi responsive phải bám các màn hình chuẩn `/people/employees`, `/organizations`, `/products`, `/customers`.
2. Không tự dựng bảng, nút, badge, phân trang mới bằng Bootstrap/HTML/CSS nếu component chung đã đáp ứng; ngoại lệ có lý do thiết kế/test được review. Khi thiếu khả năng, mở rộng component chung có kiểm thử hồi quy; không nhân bản component theo module.
3. Giữ tương thích đường dẫn hiện có, breadcrumb và điều hướng tab của workspace. Route mới khai báo metadata/permission/i18n nhất quán.
4. Các trang giao dịch có dòng chi tiết (PO/SO/GR/Issue/Receipt) được phép có editor chuyên biệt, nhưng **màn danh sách** vẫn dùng bộ chuẩn dưới đây.

**[SOURCE] Component bắt buộc xem xét trước:**

| Nhu cầu | Component/hàm chuẩn | Đường dẫn trong frontend |
|---|---|---|
| Query tìm kiếm/lọc/sắp xếp | `ListQueryControls` | `src/components/lists/ListQueryControls.vue` |
| Khung và trạng thái dữ liệu | `ListShell` | `src/components/lists/ListShell.vue` |
| Bảng | `ListTable` | `src/components/lists/ListTable.vue` |
| Phân trang | `ListPagination` | `src/components/lists/ListPagination.vue` |
| Icon quyền theo từng dòng | `TableRowActions` + `rowAllowedActions` | `src/components/lists/TableRowActions.vue`, `rowActionPolicy.ts` |
| Trạng thái | `TableStatus` + nhãn dịch | `src/components/lists/TableStatus.vue` |
| Xem chi tiết nhanh | `TableDetailModal` | `src/components/lists/TableDetailModal.vue` |
| Workflow / SetState | `BusinessWorkflowAction` | `src/components/lists/BusinessWorkflowAction.vue` |
| Dropdown nhỏ có tìm kiếm | `SearchableSelect` | `src/components/forms/SearchableSelect.vue` |
| Dropdown danh mục lớn | `RemoteSearchableSelect` | `src/components/forms/RemoteSearchableSelect.vue` |
| Chọn nhiều người nhận M11 | `RemoteMultiSearchableSelect` | `src/components/forms/RemoteMultiSearchableSelect.vue` |
| State danh sách | `useListState`, `useQueryState` | `src/composables/` |

### FE-02 — Một loại đối tượng = một bảng danh sách thống nhất [REQUIRED — PROPOSED]

- Với cùng resource, cùng tập trường và cùng ngữ nghĩa truy vấn, **chỉ một bảng**, một Query State, một bộ lọc và một phân trang; **không chia `ACTIVE / DRAFT / HISTORY` thành ba bảng/ba API**.
- Mặc định `Tất cả trạng thái` nghĩa là không gửi `filter[status]`, nhưng chỉ hiển thị **các bản ghi mà PBAC cho phép xem**. Bộ lọc trạng thái lấy từ Lifecycle/resource, không chỉ hardcode bốn mã nếu resource hỗ trợ trạng thái tùy biến.
- Cho phép đổi trạng thái trực tiếp tại dòng; khi hoàn thành thì refresh đúng bảng. Không ép chuyển tab và không làm mất bộ lọc đang dùng, trừ trường hợp create mới cần bỏ bộ lọc để người dùng nhìn thấy bản ghi vừa tạo.
- Tab chỉ dành cho **những nghiệp vụ khác nhau**: hồ sơ, contact/ship-to, giao dịch, nhật ký, audit, tài liệu; không biến cùng một danh sách thành nhiều nguồn dữ liệu vì status.
- **Ngoại lệ [REVIEW]:** History là nhật ký sự kiện bất biến có schema/quyền/truy vấn riêng; có thể dùng tab riêng nếu đó là resource khác thực sự, không phải chỉ filter status `CLOSED`.

**Bài học từ v1.9.21:** Employees/Organizations dùng một Contact list với `filter[status]` tùy chọn, một SQL page. Quy tắc này áp dụng mặc định cho các danh sách SUP/PUR/SAL/INV/FIN về sau.

### FE-03 — Query State chung, không phân trang bằng JavaScript [REQUIRED — PROPOSED]

Hợp đồng UI `ListQuery`:

```ts
// SOURCE: src/components/lists/listTypes.ts
{
  q: string,
  page: number,         // từ 1
  page_size: number,    // mặc định component chung là 15
  sort: string,         // ví dụ: '-id', 'name'
  filter: Record<string, string | readonly string[]>
}
```

- Một tương tác Apply/Search/Filter/Sort/Page chỉ tạo **một request list** đến backend và backend làm toàn bộ lọc/sort/page trên tập dữ liệu được phép xem.
- Bắt đầu tại `page=1` sau thay đổi q, filter, sort hoặc page size. Phân trang giữ metadata trả từ API, không suy ra `total` từ số dòng của trang.
- Khi chưa chọn status, **bỏ hẳn predicate status**; không gửi `ACTIVE` mặc định, không lấy từng trạng thái rồi trộn mảng ở client.
- Các **lựa chọn page size ở FE phải được suy ra từ API resource**: một số catalog P1 cho phép đến `200`, Customer hiện đến `100`; component chung không tự nâng giới hạn backend. Dùng `listPageSizePolicy.ts` cho nhóm 200 và 100, không sao chép literal giữa toolbar/pager. Module giao dịch mới chốt cap riêng trong G2/OpenAPI. Giá trị vượt trần phải bị backend từ chối theo hợp đồng (không phân trang âm thầm).
- Dùng `useListState`/`useQueryState` để phân biệt loading lần đầu, refreshing, empty, empty filtered, error; chỉ commit response mới nhất. Khi refresh lỗi, giữ dữ liệu cũ và báo lỗi — **không hiển thị 0 dòng như thể dữ liệu thật bằng 0**.
- Với lookup chạy khi gõ: debounce, giới hạn page size, chống response cũ ghi đè response mới, invalidate khi đổi tài khoản/scope.

### FE-04 — Trạng thái phải có ý nghĩa và dễ nhận biết [REQUIRED — PROPOSED]

- Có cột `Trạng thái` bằng `TableStatus` **kèm nhãn chữ vi/en** trong bảng quan trọng; không chỉ có chấm màu hoặc icon mà không có ngữ cảnh.
- Mã trạng thái lấy từ Lifecycle của resource. Nếu chưa có bản dịch, hiển thị code an toàn thay vì thông tin suy đoán.
- `DRAFT`, `ACTIVE`, `INACTIVE`, `CLOSED`, `WAITING_APPROVAL` chỉ là ví dụ; **không cố định lifecycle** chung cho mọi domain.
- Chuyển trạng thái bằng canonical Workflow action, **không PATCH trực tiếp cột status**; khi không có transition, hiển thị rõ “Không có bước chuyển được phép”.

### FE-05 — Icon thao tác phản ánh quyền hiệu lực từng dòng [REQUIRED — PROPOSED]

API list/detail của resource có mutation phải trả (nếu resource hỗ trợ):

```json
{
  "id": "42",
  "name": "Ví dụ",
  "status": "DRAFT",
  "allowed_actions": { "edit": true, "setstate": false }
}
```

- Luôn dùng `TableRowActions`, `rowAllowedActions(row)` và `authorization-mode="effective"` cho resource có row hints được backend công bố.
- Chỉ hiện Xem/Sửa/Workflow nếu quyền nền của actor và hint tương ứng cho phép. **Thiếu hint hoặc không phải boolean không bao giờ suy ra TRUE**. Không tự động hiển thị icon mutation từ `status=ACTIVE` hay `hasBasePermission` một mình.
- `view-scope-verified` chỉ được đặt `true` khi API list đã chứng minh VIEW bằng SQL PBAC, không phải do người dùng đang mở trang.
- Với dữ liệu Company/owner-sensitive, hints phải dựa trên đúng **owner/scope của từng dòng**; không tái dùng hint của một dòng khác chỉ vì cùng status.
- Frontend chỉ là affordance. Backend **luôn đánh giá lại** PBAC, Workflow, persisted owner và concurrency khi thao tác.

### FE-06 — Modal/editor dùng chung, không tràn và không làm mất dữ liệu [REQUIRED — PROPOSED]

- Dùng header/footer modal thống nhất ERP, tiêu đề kèm icon, `Đóng/Hủy/Lưu`, loading/busy/disabled, thông báo lỗi và xử lý Esc/focus hợp lý.
- Workflow modal sử dụng chung `BusinessWorkflowAction`; với modal kích hoạt từ `<td>`/bảng có `white-space: nowrap`, dùng `Teleport`/portal để không kế thừa CSS của bảng và không bị clip/z-index. Bắt buộc kiểm tra màn hình rộng và màn hình hẹp; không được có thanh cuộn ngang **của toàn modal** do nội dung không xuống dòng.
- Modal Contact/Location/Customer sử dụng form chung hoặc cùng quy tắc validation; mở chi tiết/view không làm mất trang danh sách, filter hay URL cũ.
- `409` giữ dữ liệu form và hướng dẫn reload/reconcile; `422` chỉ rõ field/business code; `403/404` không làm lộ resource không có quyền; lỗi 5xx không được biến thành thông báo “không có dữ liệu”.
- Nếu thay đổi chưa lưu mà người dùng rời form/chuyển tab, dùng guard cảnh báo (`useUnsavedChanges`). Tránh submit lặp khi request đang chạy.

### FE-07 — Tách rõ Workflow, phê duyệt và thông báo [REQUIRED — PROPOSED]

- **Workflow SetState** = thực hiện transition đã được PBAC + Lifecycle cho phép.
- **M11 người nhận thông báo** = chọn user nhận thông báo/được phân công theo chức năng M11. **Không có nghĩa** được cấp quyền approve hay transition.
- Nếu nghiệp vụ cần người duyệt bắt buộc, tạo Approval Workflow có trạng thái chờ, người có quyền approve/reject và backend enforcement theo thiết kế phê duyệt riêng; không thay đổi nghiệp vụ bằng nhãn UI.
- Các thao tác lưu danh sách người nhận và thực hiện transition có nút riêng, kết quả/lỗi riêng; không tự động coi lưu người nhận là thực hiện transition.

### FE-08 — Searchable dropdown là mặc định [REQUIRED — PROPOSED]

- Danh mục nhỏ đã tải đủ và hợp lệ: `SearchableSelect`.
- Tập lớn/dữ liệu thay đổi theo tham số: `RemoteSearchableSelect` (có q/page, phân trang, debounce, stale-response protection). Không tự lấy 200 dòng rồi tuyên bố đã hỗ trợ tìm kiếm toàn bộ.
- Multi-user của M11: `RemoteMultiSearchableSelect` và ràng buộc số người theo API, **không phải widget chọn người phê duyệt**.
- Với Tỉnh → Phường: chỉ cho tìm Phường theo Tỉnh đã chọn; khi đổi Tỉnh xóa Phường cũ, chặn response chậm và tra cứu theo ID để giữ nhãn lựa chọn khi sửa.
- Danh mục owner Company/Warehouse/Product/Supplier/Customer phải lấy từ lookup **đã lọc theo PBAC và điều kiện nghiệp vụ**; không load danh mục toàn hệ thống rồi lọc quyền phía client.

### FE-09 — API typed, i18n, tiền/tồn kho/thời gian [REQUIRED — PROPOSED]

- Dùng service adapter từ `src/services/`, typed client được generate từ OpenAPI dưới `src/api/generated/`; không gọi fetch thẳng trong mỗi View và không sửa generated SDK thủ công.
- Request/response, lỗi, filter/sort **phải cùng một hợp đồng** với backend/OpenAPI. Khi legacy endpoint dùng filter phẳng còn ListQuery dùng `filter[...]`, chuyển đổi qua adapter đã test; không đổi payload bằng phỏng đoán (bài học lỗi `organizations/labels/resolve`).
- Tất cả nhãn, lỗi, trạng thái có `vi/en`. Không hardcode chuỗi vào component dùng chung.
- Monetary/decimal gửi dưới dạng decimal string, không tính số tiền bằng JS float. Datetime dùng instant ISO + timezone có kiểm soát; format theo user settings chỉ ảnh hưởng hiển thị, không thay đổi nghiệp vụ.
- Khi module liên quan chưa vận hành, hiển thị `Chưa khả dụng` hoặc ghi rõ phụ thuộc; **không hiển thị số 0 giả** cho công nợ/doanh số/balance.

### FE-10 — Hiệu năng, truy cập và khả năng sử dụng [REQUIRED — PROPOSED]

- Tránh request N+1 ở UI. Nhãn Organization/User/Product/Contact sử dụng batch lookup có giới hạn hoặc trả trường nhãn cho page đã cấp quyền.
- Tránh fetch lại cùng lookup trên mỗi dòng. Cache theo tài khoản/scope với invalidation rõ khi đăng xuất/chuyển người dùng/Company, không cache xuyên quyền.
- Tìm kiếm từ xa có debounce và pagination, không download toàn bộ bảng lớn.
- Bắt buộc label/aria-label, focus khi modal mở/đóng, trạng thái loading/empty/error, điều khiển bàn phím, màu không là phương tiện duy nhất thể hiện trạng thái.
- Modal/table kiểm tra ít nhất desktop và mobile; nội dung dài phải wrap/truncate có thể xem chi tiết mà không phá bố cục. Dropdown không bị overlay che.

## 2. Mẫu cấu trúc một màn danh sách

```vue
<!-- Mẫu cấu trúc minh họa; không phải mã copy/paste chạy ngay -->
<ListQueryControls
  :model-value="state.query"
  :filters="filterDefinitions"
  @apply="applyQuery"
  @reset="resetQuery"
/>
<ListShell
  :initial-loading="state.isInitialLoading.value"
  :refreshing="state.isBackgroundRefreshing.value"
  :has-data="state.queryState.hasData.value"
  :empty-dataset="state.isEmptyDataset.value"
  :empty-filtered-result="state.isEmptyFilteredResult.value"
  :error="state.queryState.error.value"
>
  <ListTable :rows="state.rows.value" :columns="columns" :sort="state.query.sort">
    <template #cell-status="{ row }">
      <TableStatus :status="row.status" />
      <span>{{ labelForStatus(row.status) }}</span>
    </template>
    <template #cell-actions="{ row }">
      <TableRowActions
        authorization-mode="effective"
        :view-scope-verified="true"
        :allowed-actions="rowAllowedActions(row)"
        :state-resource="RESOURCE"
        :state-target-id="String(row.id)"
      />
    </template>
  </ListTable>
  <ListPagination
    :page="state.query.page"
    :page-size="state.query.page_size"
    :total="state.total.value"
  />
</ListShell>
```

Để chạy thật phải bổ sung đầy đủ permission props, @view/@edit/@state-changed, @sort-change và @update:page/page-size, và `RESOURCE` là mã đã đăng ký trong PBAC.

## 3. Bộ kiểm thử tối thiểu FE của module mới [REQUIRED — PROPOSED]

**Đủ code không có nghĩa là đủ nghiệm thu.** Mỗi module cần:

- Test ListQuery: default ALL statuses, optional filter, q/sort/page/page_size, reset page, canonical owner filter bất biến.
- Test component: loading → rows → empty/filter-empty → error → retry; race response cũ; quyền TRUE/FALSE/missing; setstate refresh.
- Test modal: mở từ `<td>` không overflow; form giữ draft khi 409/422; hai ngôn ngữ; lookup search + stale-response; keyboard/ARIA.
- Test E2E: hai người dùng có và không có quyền; PBAC cấm; Company khác; đối tượng theo 2+ trạng thái; số dòng meta.total; export nếu có; đường dẫn trực tiếp.
- Test tương thích Employees/Organizations/Product/Customer khi sửa shared component.

**Commands hiện có trên source [SOURCE]:**

```powershell
cd D:\XAMPP\htdocs\erp\frontend
pnpm exec vitest run
pnpm run type-check
pnpm run lint
pnpm run check:imports
pnpm run build
# Khi thay OpenAPI:
pnpm run openapi:lint
pnpm run openapi:generate:check
# E2E theo fixture DEV đã cấu hình:
pnpm run test:e2e
```

## 4. Điều kiện chặn giao diện (No-Go)

Không nghiệm thu module nếu có một trong các trường hợp:

1. Một resource/status bị chia thành 2–3 bảng/trang và client phải trộn dữ liệu để hiển thị.
2. UI chỉ render icon dựa vào quyền base hoặc status, bỏ qua `allowed_actions`.
3. Đếm tổng/phân trang diễn ra trên array lấy về không phải SQL page hợp lệ.
4. Dropdown lớn chỉ tải một phần nhưng tìm kiếm được giới thiệu là tìm toàn hệ thống.
5. Workflow modal bị CSS của table làm tràn/clip hoặc trạng thái được đổi bằng PATCH không theo Workflow.
6. Mỗi row sinh request nhãn/permission riêng (N+1), hoặc gửi dữ liệu chưa được PBAC để frontend lọc.
7. Lint/type-check/build/test phù hợp FAIL mà báo cáo lại ghi PASS; thiếu Browser/HTTP UAT phải ghi `NOT_RUN`.

## 5. Đường dẫn nguồn tham khảo

- `frontend/src/features/people/pages/PeopleEmployeesView.vue`
- `frontend/src/features/organization/pages/OrganizationsView.vue`
- `frontend/src/features/customer/pages/CustomersView.vue`
- `frontend/src/components/contacts/ContactList.vue` (v1.9.21 unified status list)
- `frontend/src/components/lists/` và `frontend/src/components/forms/`
- `frontend/src/composables/useListState.ts`, `useQueryState.ts`
- `frontend/src/services/foundation.ts`, `src/api/generated/`

**Thay đổi văn bản này cần review kiến trúc và được đồng bộ với SQL Standard, OpenAPI, ma trận test trước khi coi là baseline phê duyệt.**


## 6. Bản sửa kỹ thuật v1.1.0 — áp dụng lâu dài

**UI-ARCH-01 — Compatibility over cosmetic uniformity.** Chuẩn hóa hành vi tương đương, không buộc thay đổi route/hợp đồng P1 đang sử dụng chỉ để đồng nhất hình thức. Màn đặc thù giao dịch có thể có nhiều bảng nếu chúng là các *dòng nghiệp vụ khác resource* (SO headers và SO lines, ledger và reconciliation), nhưng không chia cùng loại bản ghi chỉ vì `status`.

**UI-ARCH-02 — Page-size capability contract.** Có 2 dải FE đã thực hiện trong v1.9.23: `STANDARD_PAGE_SIZE_OPTIONS` (đến 200) và `CUSTOMER_PAGE_SIZE_OPTIONS` (đến 100). Phải truyền **cùng một mảng** cho `ListQueryControls` và `ListPagination`. Nếu backend khác chuẩn, mở một danh sách option riêng có bằng chứng OpenAPI; không tăng trần backend chỉ để giống UI.

**UI-ARCH-03 — Owner/hints trustworthy.** `allowed_actions` là *advisory* của backend đã kiểm tra quyền xem bản ghi. Thiếu `allowed_actions` ở resource chưa đăng ký hints không được tự suy đoán sửa/đổi trạng thái. Khi API trả 403/404/409, giữ dữ liệu cũ, hiện thông báo đúng loại; backend quyết định quyền cuối cùng.

**UI-ARCH-04 — Progressive loading.** Với danh mục có quan hệ phụ thuộc (Tỉnh–Phường, Supplier–PO, Warehouse–Inventory), dropdown dùng API tìm theo điều kiện owner/PBAC; tải tới đâu có phân trang tới đó. Các nhãn của trang list phải được trả cùng response hoặc batched, tránh N+1 và tránh tải mọi Company của tổ chức chỉ để hiện một trang.

**UI-ARCH-05 — Test breakage vs code breakage.** Khi đổi kiến trúc P1 có chủ đích phải thay golden-master/source contract bị lỗi thời bằng *assertion bảo mật mạnh hơn*, ghi decision + bằng chứng parity. Không tắt lint, sửa số lượng assertion cho qua hay tự đổi checksum frozen fixtures. Chạy Vitest/build và HTTP/browser riêng.

### Bằng chứng tối thiểu cho reviewer

| Đầu ra | Bằng chứng bắt buộc |
|---|---|
| Một list thống nhất | URL/hình desktop/mobile, số request Network, state filter ALL + từng status |
| Page size | Contract OpenAPI, FE options, kiểm thử 15/100/200 theo từng resource |
| Row action | VIEW-only user và editor user, hints `true/false`, thử POST bị 403 khi không có quyền |
| Lookup | Search khi vượt trang đầu, bỏ response cũ, reset owner/scope |
| Runtime | Test browser/HTTP trên DEV, không lấy Vitest PASS để thay thế |
