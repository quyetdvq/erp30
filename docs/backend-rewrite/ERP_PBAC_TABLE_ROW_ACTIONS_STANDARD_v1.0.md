# DinkERP — Quy chuẩn PBAC cho `TableRowActions` và `allowed_actions`

| Thuộc tính | Giá trị |
|---|---|
| **Mã tài liệu** | `ERP-ARCH-PBAC-ROW-001` |
| **Phiên bản** | `1.0` — 25/10/2026 |
| **Trạng thái** | **Quy chuẩn triển khai đã thống nhất về hướng kiến trúc; CHƯA triển khai thay đổi logic** |
| **Đối tượng** | Backend CI4, frontend Vue 3/TypeScript, API contract, QA và reviewer |
| **Phạm vi** | Các nút hành động trên **mỗi bản ghi** ở bảng/danh sách và các màn hình dùng `TableRowActions`; tài nguyên mới trong tương lai |
| **Baseline đã kiểm tra** | ERP v1.5.0 và source tái tạo từ các patch M9.1–M9.3, M10.1 đã trao đổi; Playwright M10.1 **33/33 PASS theo log của người dùng** |
| **Tài liệu cấp trên** | `docs/backend-rewrite/ERP_AUTHORIZATION_SQL_SCOPE_STANDARD_v1.0.md` |
| **Điều không làm ở phiên bản này** | Không sửa code, không chạy mutation/database audit, không thay đổi OpenAPI frozen, không coi tài liệu là nghiệm thu runtime |

> **Quy tắc một câu:** Một action icon chỉ xuất hiện khi **chức năng phù hợp**, **base permission đã sẵn sàng và cho phép**, và **PBAC hiệu lực trên chính bản ghi cho phép**. Việc thực thi luôn được backend kiểm tra lại.

## 1. Kết luận rà soát source và quyết định kiến trúc

### 1.1 Những gì đã tồn tại

1. `backend/app/Modules/Product/Master/ProductReadService.php`: `list()` đã thêm `data[].allowed_actions` ở các dòng Product đã qua SQL VIEW scope và `canView()`; `show()` cũng thêm `allowed_actions` cho Product chi tiết.
2. `backend/app/Modules/Access/Query/EffectiveActionHintCache.php`: tính **`edit` và `setstate`** bằng canonical `UnifiedAccessService::decideMutation('product', ...)` và cache **chỉ trong request** theo State cho Product thuộc MASTER đã được review.
3. `contracts/openapi/openapi.yaml`, schema `Product`: `allowed_actions` đã được khai báo **optional ở cấp Product**, nhưng nếu có phải chứa `edit: boolean`, `setstate: boolean`. Mô tả nói rõ đây là hint, không đảm bảo có transition.
4. `frontend/src/api/generated/types.gen.ts`: kiểu `Product.allowed_actions?` đã chứa `{ edit: boolean; setstate: boolean }`; `frontend/src/api/generated/zod.gen.ts` cũng biết trường này.
5. `frontend/src/features/catalog/pages/ProductsView.vue`: đang tự chuyển `allowed_actions?.edit/setstate` thành `show-edit/show-toggle`; `ProductDetailModal.vue` cũng tự lọc Edit.
6. `frontend/src/components/lists/TableRowActions.vue`: **chưa có prop `allowedActions`**. `can(permission)` hiện trả `true` khi `permission === undefined`, còn permission xác định chỉ được so với `base_permissions` qua `useBasePermission`.
7. Tại snapshot source kiểm tra, có **19 chỗ sử dụng `TableRowActions` trên 18 component**; chỉ hai vị trí thuộc Product trực tiếp dùng `row.allowed_actions` trước khi đưa vào component. `rg allowed_actions backend/app` không cho thấy backend resource khác đã cấp field này như Product.

**Kết luận:** Product là **mẫu backend có thật**, nhưng frontend vẫn phân tán quyết định PBAC vào parent. Hầu hết màn hình còn lại chỉ che icon bằng base permission; cần mở rộng hợp đồng read DTO theo từng resource rồi chuẩn hóa component, **không được giả định tất cả API đã có hints**.

### 1.2 Các tên dễ nhầm — PHẢI phân biệt

| Tên | Ý nghĩa | Có dùng trực tiếp để quyết định row icon không? |
|---|---|---|
| `base_permissions` | Quyền nền trong actor snapshot, ví dụ `price_list.edit` | **Điều kiện cần**, không đủ |
| `allowed_actions` | **Effective PBAC hint theo một record**, ví dụ `{ edit: false, setstate: true }` | **Có**, cho mutation icon tương ứng |
| `actions` trong `AccessReadService` | Danh sách action của Resource/Policy phục vụ màn hình quản trị | **Không**; không phải quyền hiệu lực của actor |
| `showEdit`, `showToggle` | Ý định và sự phù hợp nghiệp vụ để hiển thị nút | **Có**, nhưng không đủ để cấp quyền |
| Workflow `options` | Danh sách transition hiện còn khả dụng khi mở dialog | **Không đồng nghĩa** `allowed_actions.setstate` |

## 2. Các nguyên tắc BẮT BUỘC (`MUST`)

**AUTH-ROW-01 — Tầng đọc:** Chỉ cung cấp row trong danh sách/detail khi đã qua canonical `VIEW` bằng SQL scope và kiểm tra hậu SQL theo chuẩn `ERP_AUTHORIZATION_SQL_SCOPE_STANDARD_v1.0.md`. Không dựa vào `allowed_actions` để thay thế lọc `VIEW`.

**AUTH-ROW-02 — Tầng hint:** `allowed_actions` phải do backend tính từ **persisted, trusted facts** và canonical PBAC (`UnifiedAccessService::decideMutation`) cho **actor đang xác thực**. Không nhận quyết định quyền, State, organization scope hoặc owner từ body/query client.

**AUTH-ROW-03 — Tầng giao diện:** Một mutation icon ở chế độ mới chỉ hiển thị khi đồng thời có: `showX === true`, `!hiddenByBusinessRule`, permission snapshot `ready`, base permission key tương ứng là `true`, và `allowed_actions[action] === true`. Không có phép OR giữa base và PBAC.

**AUTH-ROW-04 — Fail closed:** `allowed_actions` **thiếu**, `null`, action key thiếu hoặc giá trị không phải boolean `true` ⇒ **ẩn mutation icon trong chế độ PBAC bắt buộc**. Không tự suy ra quyền từ `status=ACTIVE`, quan hệ cha/con, dữ liệu lookup hoặc một actor khác.

**AUTH-ROW-05 — VIEW đặc biệt:** Một dòng lấy từ **canonical VIEW-scoped list/detail** có thể hiện nút View khi `showView` và base `resource.view` hợp lệ; không bắt Product phải có `allowed_actions.view` vì contract frozen chỉ có `edit/setstate`. Nếu dòng không có bằng chứng VIEW-scoped (ví dụ lookup/aggregate chưa review), phải xác minh VIEW ở server trước khi cho mở chi tiết hoặc ẩn View. Detail GET vẫn tự reauthorize.

**AUTH-ROW-06 — Không coi hint là quyền thực thi:** Mọi POST/PATCH/DELETE/set-state, workflow transition và special action phải đọc lại row tin cậy, kiểm tra canonical PBAC và business guards tại mutation endpoint. UI hint có thể cũ sau khi policy/assignment/State thay đổi.

**AUTH-ROW-07 — Không N+1:** Backend tính hint **chỉ cho row trên page đã được phép VIEW**, không gọi thêm một HTTP request cho mỗi icon/row; không lấy toàn catalog để tính permission. Caching nhóm chỉ trong request và chỉ khi chứng minh đầy đủ ngữ cảnh quyết định giống nhau.

**AUTH-ROW-08 — Không thay đổi frozen contract ngầm:** Bảo toàn `contracts/openapi/openapi.yaml` và `docs/backend-rewrite/openapi-frozen-v1.2.4.yaml` theo chính sách baseline hiện hành. Việc thêm hints cho resource khác Product phải được xác định là **extension/additive contract có kiểm soát và được version hóa/duyệt**, cập nhật typing/decoder tương ứng; không tự sửa file frozen hoặc 150 route.

**AUTH-ROW-09 — Không lộ policy internals:** Response public chỉ trả boolean của action được duyệt; không trả policy IDs, participant/group matching, ancestor-chain, `ABSOLUTE_DENY` reason hoặc scope profile keys nội bộ.

**AUTH-ROW-10 — Hành động đặc biệt:** Không suy diễn tất cả nút thành `edit` và `setstate`; map **UI intent → canonical resource.action** phải tường minh và có test, đặc biệt `resource.delete`, `resource.configure_numbering`, Contact đa hình và workflow transitions.

## 3. Chuẩn API / backend DTO

### 3.1 Ví dụ hiện hành: Product (đã tồn tại)

```json
{
  "id": "30",
  "status": "ACTIVE",
  "allowed_actions": {
    "edit": true,
    "setstate": false
  }
}
```

- Chỉ ví dụ phần DTO liên quan PBAC; không phải response đầy đủ.
- `allowed_actions.edit` nghĩa là canonical PBAC cho phép **thử `product.edit` trên row hiện tại**.
- `allowed_actions.setstate` nghĩa là canonical PBAC cho phép **thử action `product.setstate`**; không cam kết tồn tại transition hợp lệ.
- `allowed_actions` có thể vắng mặt trên Product ở một số response khác list/detail, như relation preview. Khi vắng mặt, **không được hiện Edit/SetState**; riêng View của row đã được VIEW-scope xác thực vẫn có thể hiện.

### 3.2 Hợp đồng đích cho resource chuyển đổi (CHƯA có trong API)

```json
{
  "id": "202",
  "organization_id": "20",
  "status": "ACTIVE",
  "allowed_actions": {
    "edit": false,
    "setstate": true
  }
}
```

- Ví dụ này mô tả **đích thiết kế** cho resource có hỗ trợ hai action; không khẳng định Price List/Organization hiện đã trả dữ liệu như vậy.
- `allowed_actions` là **object theo row**, không phải map `status → quyền` áp dụng cả trang.
- Mỗi resource chỉ khai báo các action đã có mutation endpoint và đã được phê duyệt. Ví dụ `resource.delete` có thể thêm key `delete`; **không** tự thêm `delete` cho Product.
- Một khi resource đã chuyển sang contract mới: ở **list/detail chuẩn** object phải có đầy đủ boolean của action đã cam kết, kể cả `false`; các response lookup/preview phải khai báo rõ chỉ có VIEW và không có mutation hints.
- Field do backend thêm sau `VIEW` và trước projection/response. Phải bảo đảm ORM/DTO serialization, generated SDK types, runtime validation nếu có, và UI đều giữ được field; không sửa trực tiếp file `*.gen.ts` bằng tay khi codegen là nguồn sinh.

### 3.3 Công thức quyết định backend

```text
FOR EACH persisted row r on authorized page:
  ASSERT canView(resource, r) == true (canonical cross-check)
  FOR EACH reviewed mutation action a supported by resource:
    allowed_actions[a] = Boolean(decideMutation(resource, a, r).allowed)
  END
  DTO[r].allowed_actions = allowed_actions
END
```

**Tiền điều kiện:** `r` chứa đủ thông tin persisted để `ResourceStateRepository` và scope resolver tính State/organization theo canonical contract. Phép tính READ-side hint diễn ra trên snapshot tin cậy và là kết quả tạm thời; nó **không thay thế** khóa record và việc tính lại quyền trong transaction ghi (dù `decideMutation` được tái sử dụng như Product hiện tại). Nếu read projection thiếu facts hoặc resolver fail thì **không mặc định allow**; fail closed theo chính sách service. Có thể dùng `EffectiveActionHintCache` có sẵn cho **Product**; không mở rộng State-only cache sang resource khác khi chưa có chứng minh và tests.

**Không tái sử dụng mutation result:** Một response create/update không mặc nhiên chứa `allowed_actions`; nếu UI cần hints mới phải đọc lại detail đã VIEW-authorize hoặc nhận DTO đã được tính lại bằng đường backend chuẩn. Không tự giữ hint cũ sau State mutation như quyền còn hiệu lực.

### 3.4 Phạm vi và cache cho ba domain

| Domain | Phương thức tính hints | Cấm |
|---|---|---|
| `MASTER` | Trên page đã scope. Có thể request-cache theo State **chỉ cho resource được chứng minh record-independent** như Product hiện tại. | Suy luận rằng mọi MASTER đều giống Product. |
| `ORGANIZATION` | Tính theo organization/owner thực từ DB, ancestry, assignments, State và policy lanes. Cache nếu chữ ký chứa đủ context; nếu không thì per-row trên page. | State-only cache; suy quyền Công ty A cho Công ty B. |
| `SYSTEM` / special | Cần provider/map action riêng được review, tính trên persisted State và các ngoại lệ hệ thống. | Gán `edit/setstate` mặc định cho mọi SYSTEM resource. |

Contact bắt buộc gọi cùng resolver đã dùng trong `ContactService`/`ContactScope` để phân biệt `organization_contact` và `employee_contact`; không có resource PBAC chung tên `contact`. Price List `edit=true` trên row cũ **không** chứng minh quyền chuyển owner sang một organization mới — quyền destination CREATE vẫn được kiểm tra khi submit.

## 4. Chuẩn component `TableRowActions` (đích cần triển khai)

### 4.1 API prop đề xuất (không phải signature code hiện hành)

```ts
type RowMutationAction =
  | 'edit' | 'setstate' | 'delete'
  | 'assign_member' | 'assign_permission' | 'reset_password'
  | 'configure_numbering'

type AllowedActions = Partial<Record<RowMutationAction, boolean>>
type AuthorizationMode = 'effective' | 'legacy-base-only'

type RowActionPolicy = {
  authorizationMode: AuthorizationMode
  allowedActions?: AllowedActions | null
  // Required for displayed intents; map can vary by resource:
  viewPermission?: string
  editPermission?: string
  togglePermission?: string
  deletePermission?: string
  // Optional mapping UI intent -> canonical action code for special resources:
  actionCodes?: Partial<Record<'edit' | 'toggle' | 'delete', RowMutationAction>>
  viewScopeVerified?: boolean
}
```

`showView/showEdit/showToggle/showDelete` tiếp tục thể hiện **ý định UI và điều kiện nghiệp vụ**; không còn là nơi parent phải tự nhúng `row.allowed_actions?.edit`. `active`, `busy`, `stateTargetId`, `stateResource` và labels giữ nguyên ý nghĩa hiện có.

### 4.2 Quy tắc hiển thị chính xác

```ts
// Pseudo-code để chốt semantics; chưa phải code được implement.
function canMutation(intent, row, props, auth): boolean {
  if (!props.show[intent] || !props.businessApplicable[intent]) return false
  const permissionKey = props.permissionFor[intent]
  if (!permissionKey || !auth.snapshotReady || !auth.hasBasePermissionKey(permissionKey)) return false
  if (props.authorizationMode === 'effective') {
    const canonicalAction = props.actionCodes[intent] ?? defaultActionCode(intent)
    return row.allowed_actions?.[canonicalAction] === true
  }
  return props.legacyExplicitlyAllowed === true // tạm thời, có registry
}

function canView(props, auth): boolean {
  return props.showView && props.viewScopeVerified === true &&
    !!props.viewPermission && auth.snapshotReady &&
    auth.hasBasePermissionKey(props.viewPermission)
}
```

- **`showX=false`** ⇒ icon ẩn, dù backend `allowed_actions.X=true`.
- **Không có `permissionKey`** ở chế độ strict ⇒ ẩn, không còn `permission === undefined → true`.
- **Không có `allowedActions`** ở chế độ strict ⇒ ẩn **mutation**, không mặc định ẩn View nếu VIEW đã verified.
- **`busy=true`** ⇒ những icon đang đủ quyền được **disable**, không được vô hiệu hóa/hiển thị sai bằng hack CSS.
- **Không có action-specific hint hợp lệ** ⇒ ẩn, không gọi API permission per-row để “điền” quyền.
- Phân biệt `stateTargetId` có giá trị (workflow dialog với server options) và toggle trực tiếp không có `stateTargetId` (native state mutation). **Cả hai vẫn cần canonical `setstate=true`**; click state không vượt qua reauthorization ở backend.

### 4.3 Ví dụ API gọi mới cho `/products`

```vue
<!-- Minh họa contract sau M10.2; hiện tại chưa áp dụng -->
<TableRowActions
  authorization-mode="effective"
  :allowed-actions="entry.product.allowed_actions"
  :view-scope-verified="true"
  :show-view="true"
  :show-edit="true"
  :show-toggle="true"
  view-permission="product.view"
  edit-permission="product.edit"
  toggle-permission="product.setstate"
  state-resource="product"
  :state-target-id="String(entry.product.id)"
  :active="entry.product.status === 'ACTIVE'"
  @view="openDetail(entry.product)"
  @edit="router.push(`/products/${entry.product.id}`)"
  @state-changed="afterWorkflowChange"
/>
```

**Lưu ý:** Với Product child lấy từ relation preview, `allowed_actions` có thể thiếu; giữ View có kiểm soát và load một Product detail có VIEW-authorization **khi người dùng chủ động bấm** trước khi mở/mở các mutation tùy trường hợp. Không gán `allowed_actions` của parent cho child.

### 4.4 Chế độ chuyển tiếp và tương thích

1. Ban đầu bổ sung `authorizationMode='effective'` theo hướng **opt-in**; legacy hiện có vẫn hoạt động trong lúc backend chưa trả hint.
2. Mọi call-site được duyệt phải khai báo rõ mode. **Không thêm call-site mới** ở mode `legacy-base-only`; nếu buộc phải dùng phải có exception review/expiry trong migration registry.
3. Khi một resource có hint đầy đủ và tests PASS, chuyển toàn bộ call-site của resource sang `effective`; xóa logic `:show-edit="row.allowed_actions?.edit === true"` lặp ở parent.
4. Sau khi hết 19 vị trí và các nested actions thuộc phạm vi triển khai, thay default thành **strict effective**, xóa fallback legacy. Không triển khai default strict ngay khi API hiện tại chưa đủ coverage: có thể làm biến mất hàng loạt icon đang dùng.
5. `authorizationMode` và `viewScopeVerified` là đề xuất interface; khi coding có thể đặt tên khác nhưng **không được thay đổi semantics**.

## 5. Ma trận icon và hành vi bảo vệ

| Base key | Row hint cho action | `showX` | Auth snapshot | Kết quả |
|---|---|---|---|---|
| Có | `true` | true | ready | **Hiện** (disable nếu busy) |
| Có | `false` | true | ready | **Ẩn** |
| Không | `true` | true | ready | **Ẩn** |
| Không | `false` | true | ready | **Ẩn** |
| Có | Không có object/key | true | ready | **Ẩn** ở mode effective |
| Có | `true` | false | ready | **Ẩn** |
| Có | `true` | true | loading / stale / unavailable | **Ẩn** |
| Không có mapping permission | `true` | true | ready | **Ẩn** ở mode effective |

**View** có ma trận riêng: VIEW-scoped verified row + base `*.view` + `showView` + permission snapshot ready. Không giả định `allowed_actions.view` tồn tại trong Product v1.2.4.

## 6. Các ngoại lệ phải giữ nguyên ngữ nghĩa

1. **Product MASTER:** `EffectiveActionHintCache` hiện chỉ được review cho `product`; tối ưu per-State hợp lệ theo rule hiện hành. Inline preview child có VIEW nhưng có thể không có action hints; không được thừa hưởng parent hints.
2. **Contact đa hình:** tính PBAC từ `ContactScope::resolve` dựa trên owner persisted, map `organization_contact.*` hoặc `employee_contact.*`. Không tạo giả resource `contact.edit`.
3. **Employee Assignment:** thuộc `ORGANIZATION`, có State `NATIVE`, quyền có thể khác theo assignment/organization. Các hàng assignment trong Employee Detail cũng phải được cấp hint đúng row; không dùng quyền `employee.edit` thay `employee_assignment.edit`.
4. **Price List:** thuộc `ORGANIZATION`, hai công ty cùng State có thể có `edit` khác nhau; check destination scope khi move owner, vì hint trên row không đại diện cho quyền CREATE nơi đến.
5. **Organization / Province / Ward:** LocationView dùng hai resource `province` và `ward` tùy ngữ cảnh. Organization thật thuộc `ORGANIZATION`, không lấy quyền `province` hay một biến `isWards` để suy quyền Organization.
6. **User, Authorization Group, Resource, Policy, Lifecycle, Workflow State:** nhiều resource thuộc `SYSTEM` hoặc `NATIVE` State. Với `resource.setstate`, canonical engine có recovery exception riêng nhưng vẫn kiểm tra base permission; không thêm UI bypass.
7. **Resource Delete:** chỉ hiện nếu backend có action `resource.delete` hợp lệ và `allowed_actions.delete === true`; vẫn phải có confirm dialog và reauthorize khi thực thi.
8. **Auto Numbering:** ba icon View/Edit/Toggle hiện đang dùng cùng key `resource.configure_numbering`. Đây là **mapping đặc biệt**, không được tự đổi thành `auto_numbering.view/edit/setstate`; cần canonical hint/mapping `configure_numbering` cho record Resource thực tế được cấu hình.
9. **Import Job:** row hiện chỉ có View; chưa cần thêm mutation `allowed_actions` để triển khai chuẩn này. Tôn trọng read scope và `import_job.view`.
10. **Workflow:** `allowed_actions.setstate=true` nghĩa là được thử mở workflow action; danh sách transition thực tế do `getBusinessWorkflowOptions` lấy khi bấm, không có lời hứa rằng `options.length>0`. Direct toggle của tài nguyên `NATIVE` vẫn dùng endpoint/handler tương ứng.
11. **`actions` relation của Policy/Resource:** không dùng relation này làm PBAC hint hoặc đánh đồng với `allowed_actions`.

## 7. Kiểm kê 19 vị trí hiện tại và chiến lược chuyển đổi

Đây là **kiểm kê source** (không có nghĩa các backend tương ứng đã có `allowed_actions`). Đường dẫn tương đối tính từ `frontend/src/`.

| # | Component/vị trí | Resource/intent hiện có | Backend hint hiện tại | Hướng chuyển đổi |
|---:|---|---|---|---|
| 1 | `features/catalog/pages/ProductsView.vue` | `product`: View/Edit/SetState | **Có** | Chuyển logic hint từ parent vào component; giữ preview child fail-closed. |
| 2 | `components/products/ProductDetailModal.vue` | `product` child: View/Edit | **Có nếu detail/list; thiếu ở preview** | Cho View có scope, hide Edit nếu hint thiếu. |
| 3 | `features/catalog/pages/ProductGroupsView.vue` | `product_group`: View/Edit/SetState | Chưa | Thêm hints cho list/detail Group; xem xét request-local cache phù hợp MASTER. |
| 4 | `features/catalog/pages/ProductUomsView.vue` | `product_uom`: View/Edit/SetState | Chưa | Như Group, không tự thêm keys ngoài contract. |
| 5 | `features/catalog/pages/PriceListsView.vue` | `price_list`: View/Edit/SetState | Chưa | Hints theo organization+State/actor, không theo State đơn lẻ. |
| 6 | `features/organization/pages/LocationsView.vue` | `province`/`ward`: View/Edit/SetState | Chưa | Phân resource đúng từng loại, review SQL scope/State. |
| 7 | `features/people/pages/PeopleDepartmentsView.vue` | `department`: View/Edit/SetState | Chưa | Thêm hints sau scoped page trong People READ. |
| 8 | `features/people/pages/PeopleEmployeesView.vue` (main list) | `employee`: View/Edit/SetState | Chưa | Thêm hints vào employee list/detail, bảo toàn M9.1 page loading. |
| 9 | `features/people/pages/PeopleEmployeesView.vue` (assignment tab) | `employee_assignment`: Edit/SetState | Chưa | ORGANIZATION; hints cho assignment cụ thể, có native toggle. |
| 10 | `features/people/pages/PeoplePositionsView.vue` | `position`: View/Edit/SetState | Chưa | Như Department. |
| 11 | `components/contacts/ContactList.vue` | `organization_contact`/`employee_contact` | Chưa | Backend resolve owner rồi trả hints đúng resource/row. |
| 12 | `features/product-import/pages/ProductImportJobsView.vue` | `import_job`: View | Không cần mutation hint | Giữ VIEW-scoped và base permission; không ép tạo action không sử dụng. |
| 13 | `features/security/pages/SecurityAuthorizationGroupsView.vue` | `authorization_group`: View/Edit/SetState | Chưa | NATIVE/SYSTEM; review row-level facts. |
| 14 | `features/security/pages/SecurityPoliciesView.vue` | `policy`: View/Edit/SetState | Chưa | ORGANIZATION; phân biệt `actions` relation và hint. |
| 15 | `features/security/pages/SecurityResourcesView.vue` | `resource`: View/Edit/SetState/Delete | Chưa | Bổ sung `delete` nếu contract mutation đã có; recovery setstate vẫn canonical. |
| 16 | `features/security/pages/SecurityUsersView.vue` | `user`: View/Edit/SetState | Chưa | SYSTEM/NATIVE, kiểm tra self/managed-user rules. |
| 17 | `features/workflow/pages/AutoNumberingView.vue` | `resource.configure_numbering` gắn với 3 intent | Chưa | Mapping intent riêng, không suy `auto_numbering.*`. |
| 18 | `features/workflow/pages/LifecyclesView.vue` | `lifecycle`: View/Edit/SetState | Chưa | SYSTEM/NATIVE; không reuse MASTER cache mặc định. |
| 19 | `features/workflow/pages/WorkflowStatesView.vue` | `workflow_state`: Edit/SetState | Chưa | SYSTEM/NATIVE, giữ `show-view=false` theo giao diện hiện tại. |

**Phạm vi chưa bao gồm:** icon viết trực tiếp, action toolbar, Create, Save, Reset Password, Assign Group/Permission, menu bulk actions và các component khác **không dùng** `TableRowActions`. Những điểm này cần inventory riêng, không tuyên bố đã chuẩn hóa chỉ vì 19 vị trí PASS.

## 8. Lộ trình phát triển M10.2 — theo gate, không sửa đồng loạt

| Gate | Việc làm | Tiêu chí để đóng |
|---|---|---|
| **M10.2A — Contracts & fixtures** | Chốt action registry, resource/domain/owner mapping, response field extension plan, typing, tests pure. | Product contract không đổi; 19 call-site có migration owner; đặc biệt Contact/Auto Numbering được review. |
| **M10.2B — Backend action hints** | Lần lượt thêm `allowed_actions` vào các resource list/detail đủ điều kiện; dùng canonical PBAC, hậu SQL page, bounded evaluation. | Hints đúng `true/false`, có tests cross-scope, không N+1, không sửa frozen spec. |
| **M10.2C — Frontend shared component** | Thêm `authorizationMode`, `allowedActions`, explicit permission/action mapping và helper pure; legacy chỉ để di trú. | Truth table PASS; không có missing permission => allow; native/workflow hoạt động. |
| **M10.2D — Migrate theo nhóm** | Product trước, rồi MASTER, ORGANIZATION, SYSTEM/special; chuyển từng màn hình sau khi API tương ứng có hint. | Không mất icon hợp lệ, không xuất hiện icon sai quyền; 19 vị trí được kiểm soát bằng registry. |
| **M10.2E — Regression & finalize** | Typecheck, unit, API fixture, 3-browser smoke cho màn hình đại diện; audit không còn fallback ngoài exceptions được duyệt. | Regressions hiện có (M9.1, M10.1) giữ PASS; browser behavior đúng, route count 150/150, no permission N+1. |

**Định hướng ưu tiên:** Không mở rộng thêm toàn bộ backend trong cùng một patch lớn. Chốt M10.2B cho từng resource + fixture trước, rồi chuyển UI resource đó. Dừng rollout khi xuất hiện regression phân quyền; không “chữa” bằng cách đặt `showEdit=true` hoặc bỏ qua PBAC.

## 9. Kiểm thử tối thiểu, đủ mạnh và dễ chạy

### 9.1 Backend

- Product list/detail tiếp tục trả chính xác hai boolean, tương thích frozen OpenAPI; preview không thừa hưởng hints của parent.
- Với resource mới: actor có base `edit` nhưng policy DENY; actor có VIEW nhưng không EDIT; actor có EDIT ở Công ty A nhưng không ở B dù cùng State; State ACTIVE/INACTIVE/DRAFT; policy precedence theo canonical engine, gồm ABSOLUTE_DENY.
- Contact `ORGANIZATION` vs `EMPLOYEE` cùng ID/tên nhưng khác resource; Employee Assignment có scope khác với Employee; `resource.delete` và `configure_numbering` kiểm tra đúng action.
- Không `allowed_actions` cho row không được VIEW; số `COUNT`/`page_size` không thay đổi do hint calculation; query/decision count bounded theo page.

### 9.2 Frontend `TableRowActions`

- Test bảng chân trị ở mục 5 cho mỗi mutation icon.
- `allowedActions=undefined/null/partial` → hide mutation trong effective mode; `viewScopeVerified` quyết định View riêng.
- `base_permissions` snapshot loading/stale/unavailable → fail-closed; `busy` → disable, không phát emit action.
- Product child preview chỉ View; click lấy authorized detail khi thực sự cần, không fetch trước cho mọi row.
- Native toggle vs workflow dialog vẫn chọn đúng handler; `allowed_actions.setstate=true` không đồng nghĩa có transition.
- Contact permission key thay đổi đúng theo loại owner; Auto Numbering dùng đúng `resource.configure_numbering`.

### 9.3 Regression và performance

- Sau một mutation thành công, refresh/replace row DTO/hints theo quyền mới; sau HTTP 401/403, invalidate quyền/hints phù hợp, không tiếp tục hiển thị icon dựa vào response cũ.
- Thao tác chuyển trang, đổi filter hoặc đổi tài khoản không được dùng hints của row/page/session cũ; bảo toàn chống stale response đã có ở M9.1/M10.1.
- Không thêm request per-row/per-icon ở lần mở danh sách. Đếm network calls trước/sau, chỉ nhận hiệu năng PASS bằng số liệu thực tế, không suy từ source.
- Chạy những test liên quan + `pnpm run type-check`, `pnpm build`, `python backend/tools/verify_routes.py`; không bắt buộc chạy lại bộ concurrency/DB audit đã được miễn trừ chỉ để nghiệm thu UI này.

## 10. Quy tắc xử lý lỗi & thu hồi quyền

1. **Hint tại thời điểm T** là advisory. Quyền có thể đổi tại T+1. Nếu mutation bị 403/409 hoặc business guard từ chối, UI giữ trạng thái dữ liệu nhất quán, hiển thị lỗi phù hợp, làm mới row/hints qua API VIEW-scoped khi cần; không tự retry mutation.
2. **Auth snapshot stale/unavailable**: không dùng base permission cũ làm lý do hiển thị icon. Refresh qua cơ chế auth hiện có; tránh request vô hạn.
3. **Hint builder gặp facts không đáng tin**: backend fail closed (hoặc trả lỗi có kiểm soát theo contract), không ghi `true` mặc định, không lộ lý do policy chi tiết cho client.
4. **Cùng actor nhưng công ty khác**: dù State giống nhau, không chia sẻ hints qua cache chỉ theo State; nếu không chứng minh được context tương đương thì per-record trên page.
5. **Workflow updates**: sau khi đổi State, hints cũ không còn được coi là có hiệu lực; UI cập nhật đúng row từ kết quả server/refresh.

## 11. Checklist bắt buộc khi thêm module/bảng mới

- [ ] Khai báo resource canonical, domain (`SYSTEM`/`MASTER`/`ORGANIZATION`) và nguồn persisted State/owner.
- [ ] Liệt kê action nghiệp vụ thực sự hỗ trợ (`view`, `edit`, `setstate`, `delete`, special...), ánh xạ UI intent ↔ canonical permission key.
- [ ] List/detail đã `VIEW`-scoped đúng trước COUNT/page và cross-check canonical; không lộ row/hint bị deny.
- [ ] Backend tính `allowed_actions` từ canonical PBAC trên từng row của page; action keys boolean đúng contract; không dùng input client làm trusted facts.
- [ ] Hints không được cache qua request/session khi chưa có versioned invalidation; ORGANIZATION không cache State-only.
- [ ] Contract extension đã được review theo frozen OpenAPI policy; typings/SDK/decoder cập nhật qua quy trình sinh code, không chỉnh tay generated types tùy tiện.
- [ ] Frontend sử dụng `TableRowActions` với permission mapping tường minh, `authorizationMode='effective'` và `allowedActions` từ đúng row.
- [ ] Missing hint/permission → fail closed; View có bằng chứng VIEW-scope; server reauthorize mọi mutation.
- [ ] Có tests cho: base true/PBAC false, base false/PBAC true, multi-company same State, changed permission, no N+1, stale response.
- [ ] Ghi nhật ký quyết định/exception, test liên quan, màn hình QA; không gắn nhãn runtime PASS nếu chưa chạy.

## 12. Tiêu chuẩn review/acceptance và quản lý thay đổi

- **MUST:** đúng nội dung mục 2–5, 7–11; action map minh bạch; không nới rộng quyền khi lỗi/thiếu dữ liệu.
- **SHOULD:** tái sử dụng pure helper cho visibility, tests theo contract, audit script liệt kê `<TableRowActions>` mới và chặn `legacy-base-only` cho module mới.
- **MAY:** tối ưu batch/caching khi có fixture chứng minh tương đương và phép đo thực tế; không đổi API để giảm vài boolean mỗi row.
- Khi thêm resource mới hoặc thay action, cập nhật **registry resource/action**, migration inventory và test matrix trong **cùng PR**. Nếu đổi semantics `allowed_actions` phải tạo tài liệu/contract version mới, không sửa nghĩa ngầm.
- **Owner được đề xuất:** Backend Access/PBAC (canonical evaluator), Backend module owner (trusted projection), Frontend shared components (visibility), QA (contract/E2E), reviewer kiến trúc (OpenAPI và domain exception).

### Tham chiếu mã nguồn chính

```text
backend/app/Modules/Access/UnifiedAccessService.php
backend/app/Modules/Access/Query/EffectiveActionHintCache.php
backend/app/Modules/Product/Master/ProductReadService.php
backend/app/Modules/Contact/ContactService.php
backend/app/Modules/People/Read/PeopleMasterReadService.php
backend/app/Modules/Access/Management/AccessReadService.php
frontend/src/components/lists/TableRowActions.vue
frontend/src/components/lists/BusinessWorkflowAction.vue
frontend/src/composables/useBasePermission.ts
frontend/src/features/catalog/pages/ProductsView.vue
frontend/src/components/products/ProductDetailModal.vue
frontend/src/components/contacts/ContactList.vue
contracts/openapi/openapi.yaml
frontend/src/api/generated/types.gen.ts
frontend/src/api/generated/zod.gen.ts
docs/backend-rewrite/ERP_AUTHORIZATION_SQL_SCOPE_STANDARD_v1.0.md
```

**Kết luận quy chuẩn:** `VIEW-scoped row` + `base_permissions` + **canonical `allowed_actions` theo record** + điều kiện nghiệp vụ. `TableRowActions` là nơi quyết định icon cuối cùng, nhưng mutation endpoint mới là nơi xác thực quyền thi hành. Đây là tài liệu chuẩn để triển khai từng phần **M10.2**, không phải báo cáo rằng M10.2 đã được áp dụng.
