---
document_id: "UI-MINI-ERP-P1-001"
title: "P1 UI Design - Dinktech Mini ERP"
version: "1.2"
status: "draft"
owner: "Product / Architecture / Frontend"
date: "2026-10-05"
scope: "P1 UI/UX design by rolling vertical slice"
authority:
  ur: "00_User_Requirements_v1.0.12.md"
  frs: "01_P1_Functional_Requirements_Specification_v1.7.md"
  dbd: "02_P1_Database_Design_v1.10.md"
  implementation_contract: "03_P1_Implementation_Contract_v1.5.md"
  implementation_plan: "04_P1_Implementation_Plan_v1.3.md"
  api_design: "05_P1_API_Design_v1.1.md"
---

# P1 UI Design - Dinktech Mini ERP

## 1. Mục đích

Tài liệu này thiết kế UI/UX P1 để frontend có thể hoàn thiện **cuốn chiếu theo từng vertical slice**.

Mục tiêu:

- xác định information architecture/navigation;
- xác định screen, list/form/detail/action UX;
- map screen với API và Resource Action;
- thống nhất loading/error/empty/permission state;
- không biến frontend thành authorization/business-rule authority;
- cho phép từng slice đi từ `DESIGN_READY -> IMPLEMENTED -> VERIFIED`.

UI implementation target:

```text
http://erp.monmin.net
Vue 3.5.x + TypeScript
Naive UI
Pinia
Vue Router
Vue I18n
Zod
Axios / generated API client
Vitest
Playwright
axe-core/playwright
```



## 1.1 Revision v1.1 - Workspace shell theo visual reference

Revision v1.1 khóa Application Shell theo visual reference do Product Owner cung cấp:

- Top application bar + workspace search + notification/theme/language/user controls.
- Workspace document tabs ở phía trên vùng nội dung; hỗ trợ nhiều màn hình đang mở.
- Sidebar trái có **5 mode tab cố định**: `Menu`, `Yêu thích`, `Mở gần đây`, `Hệ thống`, `Cây cơ cấu tổ chức`.
- Sidebar expanded có ô `Tìm menu`; nội dung panel thay đổi theo mode tab đang chọn.
- Sidebar cho phép resize bằng drag ở cạnh phải.
- Sidebar cho phép collapse thành icon rail như visual reference và expand lại mà không mất active mode.
- Width/collapse/active-mode là workspace preference phía client trong P1; UI Design không tự tạo backend persistence/API mới.
- Main content giữ pattern breadcrumb -> title/subtitle/actions -> filter panel -> table/detail/form như visual reference.

## 1.2 Revision v1.2 - Multilingual workspace

Revision v1.2 khóa UI đa ngôn ngữ:

- baseline locale `vi` và `en`; default/fallback `vi`;
- `vue-i18n` là source cho mọi application-controlled user-facing text;
- language selector trên Top Application Bar đổi locale runtime;
- API client luôn gửi `Accept-Language` theo locale hiện tại;
- workspace/menu/favorite/recent/tab lưu canonical key, không lưu translated label làm identity;
- locale preference là client workspace preference P1, không tạo backend locale table;
- business data do User nhập và machine code không tự dịch.

---

# 2. UI design governance

## 2.1 Screen status

```text
PLANNED
DESIGN_PENDING
DESIGN_READY
IMPLEMENTED
VERIFIED
BLOCKED
```

## 2.2 Rolling rule

Một screen chỉ `DESIGN_READY` khi API dependency tương ứng `CONTRACT_READY` hoặc screen chỉ dùng local/static behavior.

Canonical flow:

```text
FRS screen need
-> API contract ready
-> UI screen spec ready
-> implementation
-> unit/component test
-> Playwright critical flow
-> VERIFIED
```

## 2.3 Frontend authority boundary

Frontend:

- có thể hide/disable action để UX rõ hơn;
- không được coi hide/disable là security enforcement;
- luôn xử lý 401/403/404/409/422 từ backend;
- không tự merge PBAC rules;
- không tự chọn `evaluation_organization_id` để mở rộng quyền;
- không dùng browser timezone làm business DATE authority;
- không dùng JS float cho authoritative price/allocation result.

---

# 3. Visual/component policy

## 3.1 Component library

- **Naive UI** là component system chính cho form/table/modal/drawer/tree/tabs/message/notification.
- Bootstrap dùng giới hạn cho layout/utility nếu codebase hiện tại đã dùng; không xây hai design system cạnh tranh.
- Không thêm component framework mới trong P1 nếu chưa có technical decision.

## 3.2 General style

P1 ưu tiên desktop ERP productivity:

- dense nhưng đọc được;
- table-first cho master/config;
- explicit status tags;
- sticky action bar ở complex detail page;
- destructive/privileged action có confirm;
- keyboard/focus accessible;
- no color-only status meaning.

Exact color palette/branding lấy từ application theme hiện hữu; tài liệu này không khóa brand color mới.

## 3.3 Responsive target

Priority:

1. desktop >= 1280px;
2. laptop/tablet landscape;
3. mobile chỉ cần read/basic action hợp lý nếu layout cho phép.

Complex policy/product/pricing/import configuration không cần mobile-first workflow trong P1.

---

# 4. Application shell

Application Shell của P1 lấy visual baseline từ các screenshot do Product Owner cung cấp. Mục tiêu là tạo một **ERP workspace** có navigation nhanh, multi-tab và sidebar thích nghi theo thói quen người dùng.

## 4.1 Tổng thể layout

```text
+----------------------------------------------------------------------------------+
| TOP APPLICATION BAR                                                              |
| Menu toggle | Logo | Workspace Search | Notifications | Theme | Language | User  |
+----------------------+-----------------------------------------------------------+
|                      | WORKSPACE TABS                                             |
| LEFT SIDEBAR         | Dashboard | Organization | Product | ... | +              |
|                      +-----------------------------------------------------------+
|                      | Breadcrumb                                                |
| 5 MODE TABS          | Page title / subtitle                         Page actions |
| Search / panel       |                                                           |
|                      | Filter / Form / Table / Detail                             |
|                      |                                                           |
+----------------------+-----------------------------------------------------------+
```

Desktop shell gồm ba vùng chính:

1. `TopApplicationBar`.
2. `WorkspaceSidebar`.
3. `WorkspaceMain`, bên trong có `WorkspaceTabs` + page content.

## 4.2 Top Application Bar

Visual behavior:

```text
[≡] [Dinktech / ERP Workspace] [Tìm trong workspace................]
                                [Thông báo] [Theme] [Ngôn ngữ] [User]
```

Các element:

- sidebar collapse/expand trigger;
- logo + product name;
- global workspace/command search;
- notification affordance;
- light/dark theme toggle;
- language selector (`Tiếng Việt` / `English`);
- authenticated user menu.

Global search không thay thế filter/search của từng list page.

Command palette có thể được mở từ workspace search/keyboard shortcut nếu implementation hiện tại hỗ trợ; command chỉ điều hướng/chạy action mà User đã được phép thấy/thực hiện.

## 4.3 Workspace Tabs

Ngay dưới Top Application Bar là hàng document/workspace tabs như visual reference.

Behavior:

- `Tổng quan` là tab gốc và mặc định không close.
- Mỗi màn hình business mở thành một workspace tab.
- Nếu mở lại cùng canonical route/entity thì focus tab hiện hữu thay vì tạo duplicate khi có thể xác định deterministic.
- Tab có thể close nếu không phải tab gốc.
- Nút `+` mở command/menu để chọn màn hình mới.
- Nhiều tab vượt chiều rộng thì horizontal-scroll; không wrap thành nhiều hàng.
- Active tab có visual accent rõ.
- Tab title dùng display name của màn hình; detail tab có thể thêm business code/name ngắn.
- Khi tab có form dirty, close/tab navigation phải confirm trước khi discard.
- Tab state là UI workspace state; không tạo backend business persistence chỉ từ requirement này.

Suggested local preference/session keys:

```text
ui.workspace.tabs
ui.workspace.active_tab
```

Exact persistence mechanism phải theo frontend repository hiện tại sau `P1-ENV-001`. Nếu dùng browser storage, không lưu dữ liệu nhạy cảm hoặc full business record trong tab state.

## 4.4 Workspace Sidebar - structure

Sidebar expanded có ba phần:

```text
+----------------------------------+
| Tìm menu                         |
+----------------------------------+
| [Menu] [★] [Clock] [Gear] [Org] |
+----------------------------------+
| Active mode content              |
|                                  |
|                                  |
+----------------------------------+
| Optional footer / scope notice   |
+----------------------------------+
```

Sidebar có **đúng 5 mode tab cố định**:

| Tab | Icon concept | Vietnamese label | Purpose |
|---|---|---|---|
| 1 | menu/list | `Menu` | Cây menu nghiệp vụ chính |
| 2 | star | `Yêu thích` | Các màn hình User đánh dấu |
| 3 | clock | `Mở gần đây` | Lịch sử màn hình gần đây |
| 4 | gear | `Hệ thống` | Menu quản trị người dùng/phân quyền/cấu hình |
| 5 | building/org | `Cơ cấu tổ chức` | Cây Organization shortcut/context navigation |

Thứ tự 5 tab là cố định trong P1 để muscle-memory nhất quán.

## 4.5 Mode 1 - Menu

Mode mặc định.

Nội dung là menu nghiệp vụ dạng hierarchical tree.

P1 menu IA được map từ các capability baseline, ví dụ:

```text
WORKSPACE
  Tổng quan

Dữ liệu chính
  Địa giới hành chính
  Tổ chức và phòng ban
  Nhân viên
  Sản phẩm
  Giá

Thông tin và kiểm soát
  Quản trị thông tin
  Workflow / Lifecycle
  Import
```

Exact wording/grouping có thể được tinh chỉnh trong UI revision mà không đổi Resource/Action semantics.

Rules:

- group có child dùng expand/collapse;
- current route được highlight;
- menu item chỉ được hide theo permission/capability info nếu frontend có source tin cậy;
- backend vẫn là authorization authority;
- search `Tìm menu` filter theo normalized display label/path;
- empty search hiển thị trạng thái “Không tìm thấy menu phù hợp”.

## 4.6 Mode 2 - Yêu thích

Header:

```text
★ Yêu thích                         <count>
```

Behavior:

- User có thể favorite/unfavorite màn hình từ page header hoặc command palette.
- List hiển thị màn hình đã favorite theo thứ tự gần nhất hoặc order UI nhất quán.
- Click item mở/focus workspace tab tương ứng.
- Favorite không cấp thêm permission.
- Item không còn accessible sau permission change không được mở thành công; UI phải refresh/hide theo capability state.

P1 không tạo backend table/API chỉ để lưu favorite. Mặc định đây là client workspace preference nếu codebase chưa có existing preference service.

Suggested local key:

```text
ui.sidebar.favorites
```

## 4.7 Mode 3 - Mở gần đây

Header:

```text
◷ Mở gần đây                       <count>
```

Behavior:

- track canonical screens gần đây;
- không track transient modal/form internal state;
- deduplicate theo canonical route/entity key;
- newest first;
- giới hạn đề xuất: 20 entries;
- click mở/focus workspace tab;
- history không bypass authorization;
- logout/user switch phải không làm lộ recent data của User trước.

Suggested local key namespace phải gắn authenticated user identity nếu dùng browser storage.

## 4.8 Mode 4 - Hệ thống

Header:

```text
HỆ THỐNG
```

Nhóm canonical P1:

```text
Quản lý người dùng và phân quyền
  Users
  Authorization Groups
  Resources & Actions
  Policies
  Authorization Logs

Cấu hình hệ thống
  Workflow States
  Lifecycles
  Auto Numbering

Khác
```

UI Vietnamese label phải phân biệt rõ **Authorization Group / Nhóm phân quyền** với Organization type `GROUP`.

Mode Hệ thống chỉ là navigation grouping; không có implicit admin permission.

## 4.9 Mode 5 - Cơ cấu tổ chức

Header:

```text
Cây cơ cấu tổ chức
<GROUP display name>
```

Behavior:

- hiển thị tree `GROUP -> COMPANY -> BRANCH/PLANT/WAREHOUSE`;
- click node có thể mở Organization detail hoặc thay navigation context khi màn hình hiện tại cho phép;
- node selection không được override authoritative Evaluation Organization của existing target;
- tree chỉ hiển thị node trong visibility scope;
- có expand/collapse hierarchy;
- optional search/filter trong tree khi Organization lớn;
- Department/Position không xuất hiện như Organization node.

## 4.10 Sidebar resize

Sidebar expanded **cho phép User điều chỉnh chiều rộng bằng drag** tại cạnh phải.

Canonical values P1:

```text
collapsed_width = 72px
default_expanded_width = 320px
min_expanded_width = 260px
max_expanded_width = min(520px, 45vw)
resize_step_keyboard = 16px
```

Behavior:

- drag separator theo pointer/mouse;
- clamp width vào min/max;
- double-click resize handle reset về `320px`;
- resize không làm page content overflow sai; main content dùng remaining width;
- khi viewport thay đổi, stored width được clamp lại theo current max;
- width chỉ áp dụng expanded state; collapsed luôn 72px.

Suggested client preference:

```text
ui.sidebar.width
```

Persistence:

- lưu theo authenticated user/browser workspace preference nếu có cơ chế phù hợp;
- không tạo database column/table/API mới chỉ từ UI requirement;
- nếu local storage dùng chung máy, key phải namespace theo user để tránh leak preference giữa User.

Accessibility:

- resize handle dùng semantic separator khi framework cho phép;
- `aria-orientation="vertical"`;
- keyboard Left/Right thay đổi width theo step;
- Home có thể reset min, End max khi implement;
- focus indicator rõ.

## 4.11 Sidebar collapse / expand

Expanded state:

- hiển thị search;
- hiển thị 5 mode tab theo hàng ngang;
- hiển thị content của active mode.

Collapsed state như visual reference:

```text
+------+
| 🔍   |  optional command/search trigger
| ☰    |  Menu
| ☆    |  Favorites
| ◷    |  Recent
| ⚙    |  System
| ▦    |  Organization
+------+
```

Canonical behavior:

- width = 72px;
- 5 mode icons xếp dọc;
- text label/content panel ẩn;
- active mode icon có selected background/accent;
- hover/focus tooltip hiển thị label;
- click active mode icon khi collapsed **expand sidebar và giữ mode đó**;
- top-level collapse trigger cũng toggle expanded/collapsed;
- collapse không reset width đã resize;
- expand phục hồi `ui.sidebar.width` gần nhất;
- active mode vẫn giữ sau collapse/expand.

Suggested preferences:

```text
ui.sidebar.collapsed
ui.sidebar.active_mode
```

## 4.12 Sidebar search

Expanded:

```text
[Tìm menu...]
```

Collapsed:

- chỉ icon search;
- click mở command/search overlay hoặc tạm expand sidebar theo implementation hiện hữu.

Search scope mặc định:

- Mode `Menu`: menu tree.
- Favorites: favorite list.
- Recent: recent list.
- System: system menu.
- Organization: Organization tree/name/code.

Global top-bar search vẫn độc lập và có thể rộng hơn sidebar search.

## 4.13 Responsive behavior

Desktop >= 1280px:

- expanded sidebar mặc định 320px;
- resizable.

Laptop/tablet landscape:

- clamp max width;
- User có thể collapse.

Viewport hẹp:

- sidebar chuyển overlay/drawer hoặc collapsed rail theo capability của shell;
- không để resized desktop width chiếm phần lớn viewport;
- complex P1 screens vẫn desktop-first.

Exact breakpoint phải khớp current frontend architecture sau `P1-ENV-001`; UI v1.1 không tạo breakpoint framework cạnh tranh.

## 4.14 Workspace shell state model

Suggested state:

```text
sidebar:
  collapsed: boolean
  width: number
  active_mode: MENU | FAVORITES | RECENT | SYSTEM | ORGANIZATION

workspace:
  tabs[]
  active_tab_id

navigation:
  menu_query
  organization_selected_id?
```

Pinia có thể quản lý runtime state; persistence adapter riêng chịu trách nhiệm local/session preference nếu dùng.

Business data không được serialize nguyên record vào sidebar/workspace preference store.

## 4.15 Shell permission behavior

Permission filtering ở sidebar/menu là UX optimization:

```text
known permission/capability -> hide/disable inaccessible entry
unknown permission          -> do not invent ALLOW
backend response            -> final authority
```

System Admin không có special menu bypass.

Footer có thể hiển thị trạng thái như:

```text
Đã lọc theo quyền
```

như visual reference, nhưng đây chỉ là UX indicator.

## 4.16 Shell acceptance criteria

- Có đúng 5 sidebar mode tab theo thứ tự đã khóa.
- Expanded sidebar có `Tìm menu`.
- User resize sidebar trong khoảng min/max.
- Reload/route change giữ width nếu preference persistence đang bật.
- Collapse chuyển về 72px icon rail.
- Expand phục hồi width trước collapse.
- Active mode giữ qua collapse/expand.
- Favorites/Recent không tạo permission.
- Organization tree không override authoritative target context.
- Workspace tabs mở nhiều màn hình, active tab rõ, tab closeable trừ dashboard.
- Dirty form close/tab navigation có confirm.
- Horizontal workspace tabs không wrap.
- Keyboard/focus của 5 mode tab và resize handle hoạt động.
- Không có client workspace preference nào lưu full sensitive business payload.

---

# 4A. Localization / i18n UI contract

## 4A.1 Supported locales

```text
vi = Tiếng Việt (default/fallback)
en = English
```

Language selector ở Top Application Bar hiển thị current locale và cho đổi runtime không reload.

Resolution khi app khởi động:

```text
1. saved authenticated-user/browser workspace preference nếu supported
2. browser preferred language nếu map được vi/en
3. vi
```

Suggested preference key:

```text
ui.locale
```

Nếu dùng shared browser storage, key phải namespace theo authenticated user.

## 4A.2 Vue I18n structure

Dùng semantic translation key, không dùng nguyên câu tiếng Việt làm key.

Suggested domains:

```text
common.*
navigation.*
auth.*
organization.*
employee.*
workflow.*
numbering.*
product.*
pricing.*
import.*
validation.*
error.*
```

Ví dụ:

```text
navigation.overview
navigation.favorites
common.actions.save
common.actions.cancel
product.fields.product_code
validation.required
```

Suggested frontend resources:

```text
src/locales/vi/...
src/locales/en/...
```

Exact physical layout được xác minh theo repository ở `P1-ENV-001`; không tạo parallel i18n architecture nếu project đã có convention.

## 4A.3 Language switch behavior

Khi User đổi locale:

1. update `vue-i18n` current locale;
2. update `<html lang="vi|en">`;
3. persist `ui.locale`;
4. Axios/generated API transport dùng locale mới cho `Accept-Language`;
5. shell/menu/breadcrumb/workspace-tab/form label re-render;
6. không reload business data chỉ để đổi text nếu không cần;
7. server-originated messages của request tiếp theo dùng negotiated locale.

## 4A.4 Canonical identity vs translated display

Phải lưu canonical key trong UI state, không lưu translated label làm identity.

Ví dụ workspace tab:

```text
WRONG:  title = "Sản phẩm" làm identity
RIGHT:  screen_key = "product.list"
        title = t('navigation.products')
```

Áp dụng cho:

- menu;
- favorites;
- recent;
- workspace tabs;
- command palette;
- status/role/effect labels.

Do đó đổi locale cập nhật label của tab/favorite/recent hiện hữu ngay.

## 4A.5 Dữ liệu không dịch

Không tự translate:

```text
Product Name
Organization Name
Employee Name
Descriptions entered by user
product_code / SKU / barcode
Resource/Action code
Policy effect raw code
Workflow state raw code
Import header/key
```

Canonical code được map sang translated display khi có UI vocabulary:

```text
ACTIVE          -> Hoạt động / Active
INACTIVE        -> Ngừng hoạt động / Inactive
ALLOW           -> Cho phép / Allow
DENY            -> Từ chối / Deny
ABSOLUTE_DENY   -> Từ chối tuyệt đối / Absolute deny
COMBO           -> Combo / Combo
```

Raw code vẫn được gửi/nhận API không đổi.

## 4A.6 API integration

Mọi API request gửi:

```http
Accept-Language: <current-locale>
```

Frontend logic:

- xử lý `error.code`/`details[].code` cho program logic;
- `message` chỉ dùng display;
- nếu backend message thiếu translation và fallback `vi`, frontend không tự rewrite machine error outcome;
- `Content-Language` có thể dùng cho diagnostics, không dùng để đổi current UI locale ngược lại.

## 4A.7 Date, number, currency

Locale formatting là presentation only.

- ngày giờ hiển thị có thể theo locale;
- VND/decimal display có thể dùng locale formatter an toàn;
- authoritative decimal string vẫn được giữ;
- browser locale không thay Company business timezone;
- business DATE không được silently timezone-convert vì đổi ngôn ngữ.

## 4A.8 Missing translation policy

Development/test:

- missing key phải log/test failure phù hợp;
- `vi` và `en` key tree phải parity cho P1 screens đã VERIFIED.

Production:

```text
selected locale -> vi fallback -> translation key as last diagnostic fallback
```

Không để blank label/button vì missing translation.

## 4A.9 Accessibility

- language selector có accessible name;
- `<html lang>` cập nhật runtime;
- icon tooltip/aria-label lấy từ i18n;
- translated string không được cắt/truncate làm mất critical meaning;
- layout phải chịu được English text dài hơn Vietnamese ở button/menu/form label.

## 4A.10 Localization acceptance criteria

- Default first-use là `vi` nếu browser/preference không map được.
- User đổi `Tiếng Việt <-> English` không reload app.
- Sidebar 5 mode tab, command palette, workspace tabs, breadcrumb, forms và common errors đổi text.
- Favorites/Recent/Workspace tabs đổi translated label vì lưu canonical key.
- Reload giữ locale preference.
- Axios/API layer gửi đúng `Accept-Language`.
- `error.code` xử lý giống nhau ở hai locale.
- Locale switch không đổi Organization Context, timezone, permission hay business record.
- Vitest kiểm tra key parity `vi`/`en`.
- Playwright smoke chạy ít nhất một critical flow ở `vi` và một flow ở `en`.

---

# 5. Common page patterns

## 5.1 Master list page

Standard structure:

```text
Page title
Primary action: Create (if allowed)

Search q
Filter chips / filter drawer
Sort
Status filter
Export (if allowed)

Data table
Pagination
```

Rules:

- default `page_size=50`;
- loading state must not unnecessarily clear existing data;
- empty state distinguishes "no data" vs "no result for filter";
- total comes from backend-authorized result;
- export uses same active filters where supported.

## 5.2 Simple master form

Use modal/drawer for low-complexity master:

```text
Province
Ward
Department
Position
Product Group
Product UOM
Workflow State
```

Use full page for complex entity:

```text
Organization
Employee
Authorization Group
Policy
Lifecycle
Auto Numbering
Product
Price List
Import Job
```

If repository already has a strong UI convention, preserve it unless it conflicts with accessibility/usability.

## 5.3 Detail page

Common sections:

```text
Header: identity + status + actions
Main information
Related tabs
Audit metadata
```

Audit metadata read-only.

## 5.4 Form state

- dirty-form warning before route leave;
- submit disabled while request in flight or local prerequisites clearly invalid;
- backend validation error maps to field when `details.field` exists;
- unknown/global error appears at top of form;
- 409 keeps user input and offers reload/reconcile;
- 403 shows permission error;
- 404 existing target shows generic not-found/no-access.

---

# 6. Common table behavior

Columns prioritize:

- business code;
- display name;
- scope/context;
- status;
- effective period when relevant;
- actions.

Do not use BIGINT internal `id` as primary user-facing identity unless necessary for support/debug.

Common row actions:

```text
View
Edit
Set Active/Inactive
Delete only when canonical Resource supports delete
```

---

# 7. Common selectors

## 7.1 Organization selector

- tree selector where hierarchy matters;
- display `code - name`;
- type badge GROUP/COMPANY/BRANCH/PLANT/WAREHOUSE;
- inactive not selectable for new mutation;
- parent matrix prefiltered for UX, backend remains authority.

## 7.2 Product selector

Display:

```text
product_code | full_name | role | base_uom
```

PARENT excluded from operational selection.

## 7.3 Employee/User selector

Search only through authorized API result. Hidden identities must not leak in autocomplete/count.

---

# 8. Slice 1 UI - Foundation / Organization / Contact

Status: `PLANNED`

## UI-P1-LOC-001 Provinces

Route:

```text
/settings/locations/provinces
```

API:

```text
/provinces*
```

List columns:

```text
Code
Name
Status
Updated At
Actions
```

Filters:

```text
q
status
```

Form:

```text
Code *
Name *
Status *
```

Actions map:

```text
Create -> province.create
Edit -> province.edit
Set state -> province.setstate
Export -> province.export
```

## UI-P1-LOC-002 Wards

Route:

```text
/settings/locations/wards
```

Columns:

```text
Code
Name
Full Name
Province
Status
Actions
```

Form:

```text
Province *
Code *
Name *
Full Name
Status *
```

Ward selector/filter depends on Province.

## UI-P1-ORG-001 Organization Tree

Route:

```text
/organizations
```

Layout:

```text
Left: organization tree
Right: selected node summary/detail
```

Tree node:

```text
[type badge] CODE - Name
status indicator
```

Header actions:

```text
Create Child
Edit
Change Status
```

Create Child form dynamically restricts type using parent matrix.

Company-only fields appear only when type = COMPANY.

Tabs:

```text
Overview
Contacts
Audit
```

Contacts tab supports active/history view and Add/Edit/Change Status.

Critical UX:

- creating Company under GROUP visually uses selected parent;
- WAREHOUSE never offers child-create;
- re-parent warns about hierarchy impact;
- no raw client `evaluation_organization_id` field exposed as authority.

---

# 9. Slice 2 UI - Employee Structure

Status: `PLANNED`

## UI-P1-EMP-001 Departments

Route:

```text
/people/departments
```

Tree/list:

```text
Code
Name
Parent
Status
Actions
```

No Company owner field.

## UI-P1-EMP-002 Positions

Route:

```text
/people/positions
```

Columns:

```text
Code
Name
Level
Status
Actions
```

No Department/Organization owner field.

## UI-P1-EMP-003 Employees

Route:

```text
/people/employees
```

Columns:

```text
Employee Code
Name
User
Primary Organization
Primary Department
Primary Position
Manager
Status
Actions
```

Filters:

```text
q
status
organization
department
position
```

Employee form sections:

```text
Identity
  Employee Code *
  Full Name *
  User mapping
  Gender
  Date of Birth
  Place of Origin

Employment
  Hire Date
  Direct Manager
  Status
  Description

Contacts
```

`Gender` control:

- UI control type: select;
- options: `DESIGN_PENDING` until `DEC-P1-001`;
- frontend must not hard-code an enum before decision.

## UI-P1-EMP-004 Employee Detail

Tabs:

```text
Overview
Assignments
Contacts
Audit
```

Assignments table:

```text
Organization
Department
Position
Primary
Effective From
Effective To
Status
Actions
```

Assignment form:

```text
Organization *
Department
Position
Primary
Effective From *
Effective To
Status
Description
```

Local overlap hint is optional; backend overlap validation is authoritative.

Secondary Assignment must not be visually described as "ignored for authorization".

---

# 10. Slice 3 UI - Security / Authorization / Workflow

Status: `PLANNED`

## UI-P1-IAM-001 Users

Route:

```text
/security/users
```

Read/list:

```text
User identity
Employee mapping
Identity/access status
Authorization Groups count
Direct Permissions count
```

Create/edit identity form remains `DESIGN_PENDING` until Shield canonical write contract is confirmed.

User detail tabs:

```text
Overview
Authorization Groups
Direct Permissions
```

Direct Permissions editor:

- searchable Resource/Action matrix;
- direct permissions are ALLOW additions only;
- display note that Policy/ABSOLUTE_DENY still applies;
- save requires `user.assign_permission`.

## UI-P1-IAM-002 Authorization Groups

Route:

```text
/security/authorization-groups
```

Columns:

```text
Code
Name
Status
Member Count
Permission Count
Actions
```

Tabs:

```text
Overview
Members
Permissions
```

Members edit requires `authorization_group.assign_member`.

Permission matrix:

```text
Resource
Action columns
checkbox per canonical action
```

Save requires `authorization_group.assign_permission`.

Use label **Nhóm phân quyền** to avoid confusion with Organization GROUP.

## UI-P1-IAM-003 Resources & Actions

Route:

```text
/security/resources
```

Columns:

```text
Type
Name
Domain
Lifecycle
Action Count
```

Tabs:

```text
Resource
Actions
Auto Numbering
```

## UI-P1-PBAC-001 Policies

Route:

```text
/security/policies
```

Columns:

```text
Organization Scope
Resource
Workflow State
Participant Type
Participant
Actions/Effects summary
Updated At
Actions
```

Editor:

```text
Organization *
Resource *
Workflow State (All States allowed)
Participant Type *
Participant *
Action/effect rows *
```

Effect choices:

```text
ABSOLUTE_DENY
ALLOW
DENY
```

Helper text must not imply Policy grants missing Base Permission.

Participant source:

```text
USER -> Users
GROUP -> Authorization Groups
POSITION -> Positions
DEPARTMENT -> Departments
ORGANIZATION -> Organization tree
```

No `ROLE` option.

## UI-P1-AUD-001 Authorization Logs

Route:

```text
/security/authorization-logs
```

Read-only table:

```text
Time
User
Organization
Resource
Action
Decision
Reason/Policy
Request ID
```

Provide copy Request ID.

## UI-P1-WF-001 Workflow States

Route:

```text
/configuration/workflow-states
```

Simple master.

## UI-P1-WF-002 Lifecycles

Route:

```text
/configuration/lifecycles
```

Editor tabs:

```text
Overview
States
Transitions
```

States:

```text
State
Sequence
Initial
Final
```

Transitions:

```text
From State
Action
To State
Status
```

UI prevents duplicate from/action locally; backend remains authority.

---

# 11. Slice 4 UI - Auto Numbering

Status: `PLANNED`

## UI-P1-NUM-001 Auto Numbering Administration

Route:

```text
/configuration/auto-numbering
```

Alternative entry: Resource detail -> Auto Numbering.

List:

```text
Resource Type
Series Code
Name
Scope
Pattern
Reset
Generation Mode
Default
Status
Issued?
```

Form:

```text
Resource *
Series Code *
Name *
Scope *
Pattern *
Reset Policy *
Generation Mode *
Start Number *
Increment By *
Default
Status
```

After first committed issuance, fields become read-only:

```text
Resource
Series Code
Scope
Reset Policy
```

No Delete for issued series; only Inactivate.

## UI-P1-NUM-002 Pattern Builder

Token insertion:

```text
SEQ
YYYY
YY
MM
DD
COMPANY_CODE
ORG_CODE
RESOURCE
```

Sequence token has width input.

Validation panel:

```text
Syntax
Field length
Uniqueness namespace compatibility
Period discriminator
Scope discriminator
Timezone prerequisite
```

Validate button calls non-consuming API.

Do not call real allocator to preview next number.

If illustrative sample is shown:

```text
Sample only - does not reserve a number
```

---

# 12. Slice 5 UI - Product / UOM

Status: `PLANNED`

## UI-P1-PRD-001 Product Groups

Route:

```text
/products/groups
```

Simple list/form.

## UI-P1-UOM-001 UOM Master

Route:

```text
/products/uoms
```

Simple list/form.

## UI-P1-PRD-002 Product List

Route:

```text
/products
```

Columns:

```text
Product Code
Full Name
SKU
Type
Role
Product Group
Base UOM
Default Price
Serial
Status
Actions
```

Filters:

```text
q
status
product_type
role (only if API supports)
product_group_id
base_uom_id
```

Role badge:

```text
SIMPLE
PARENT
CHILD
COMBO
```

## UI-P1-PRD-003 Product Editor

Routes:

```text
/products/new
/products/:id
```

Sections:

```text
Identity
Classification
UOM & Price
Packaging
Operational
Relationships
Audit
```

### STANDARD root

```text
Product Name
Parent Product = blank
```

### CHILD

```text
Parent Product *
Product Child Name *
Product Name hidden/not editable
```

### COMBO

```text
Product Name *
Base UOM *
Default Price *
Serial Management forced false
UOM Conversion tab hidden
Combo Components tab enabled
```

### PARENT

Derived role; user does not choose PARENT directly.

## UI decision - CHILD display separator

P1 UI v1.0 chooses:

```text
Parent Product Name + " - " + Product Child Name
```

Example:

```text
Áo Polo - Đen
```

This is display-only:

- no `full_name` persistence;
- canonical data remains Parent Name + Child Name;
- later visual separator change does not require schema migration.

This resolves UI-side `DEC-P1-003` for v1.0.

## UI-P1-UOM-002 Product UOM Conversions

Product detail tab:

```text
UOM
Conversion Factor
Usage
Selling Price
Packaging
Status
```

Hidden for COMBO/PARENT.

## UI-P1-CMB-001 Combo Components

COMBO product tab:

```text
Component Product
Role
Quantity (Base UOM)
Base UOM
Actions
```

Selector excludes PARENT and current product for UX; backend validates graph/cycle.

---

# 13. Slice 6 UI - Pricing / COMBO

Status: `PLANNED`

## UI-P1-PRC-001 Price Lists

Route:

```text
/products/price-lists
```

Columns:

```text
Code
Name
Scope Organization
Effective From
Effective To
Priority
Status
Actions
```

Editor:

```text
Code *
Name *
Organization Scope * (GROUP or COMPANY)
Effective From *
Effective To
Priority *
Status *
Description
```

Items tab:

```text
Product
UOM
Price
```

UX filtering:

- PARENT unavailable;
- COMBO only Base UOM;
- PURCHASE-only Alternate UOM excluded;
- price remains decimal-string safe.

## UI-P1-PRC-002 Price Resolution Tester

Route:

```text
/products/price-lists/resolve
```

Only `DESIGN_READY` when `/price-resolutions` API is `CONTRACT_READY`.

Inputs:

```text
Product
UOM
Company
Evaluation Time
```

Output:

```text
Final Unit Price
Price Source
Price List
```

Administrative diagnostic only; no persistence.

## UI-P1-CMB-002 COMBO Allocation Preview

Status: `DESIGN_PENDING`.

Reason: authoritative preview API is not baseline-required.

Frontend must not implement authoritative allocation using JS float.

---

# 14. Slice 7 UI - Product Import

Status: `PLANNED`

## UI-P1-IMP-001 Product Import Wizard

Route:

```text
/products/import
```

Step 1:

```text
File
Mode:
  CREATE_ONLY
  UPDATE_ONLY
  UPSERT
```

Default: `CREATE_ONLY`.

Pre-submit help:

- CSV/XLSX accepted;
- canonical template columns;
- `source_key` explanation;
- `product_code` may be blank on create with AUTO_REQUIRED;
- `__NULL__` semantics;
- XLSX formulas rejected.

Client-side validation is convenience only.

## UI-P1-IMP-002 Import Jobs

Route:

```text
/products/import-jobs
```

Columns:

```text
Created At
File
Mode
Status
Total
Success
Error
Actor
Actions
```

## UI-P1-IMP-003 Import Job Detail

Route:

```text
/products/import-jobs/:id
```

Summary:

```text
Status
Mode
Total
Success
Error
Source File
Hash
```

Rows:

```text
Original Row Number
Record Type
Source Key
Entity Key / Generated Product Code
Result
Error Code
Error Message
```

Filters:

```text
result
record_type
q/entity key
```

Export report requires `import_job.export`.

---

# 15. Error UX contract

## 15.1 401

Follow existing Shield frontend auth flow; preserve intended route when safe.

## 15.2 403

Display:

```text
Bạn không có quyền thực hiện thao tác này.
Request ID: ...
```

Do not rewrite as validation error.

## 15.3 404

Generic:

```text
Không tìm thấy dữ liệu hoặc bạn không có quyền xem dữ liệu này.
```

Do not reveal which case.

## 15.4 409

- preserve unsaved form;
- show conflict message;
- provide Reload/Reconcile when appropriate;
- do not auto-retry unsafe mutation.

## 15.5 422

Map `error.details[].field` to field.

Global semantic error appears at top.

## 15.6 500

Generic unexpected error + Request ID. No stack/SQL/internal path.

---

# 16. Permission-aware UI

This document does not create a new permission-summary endpoint.

Rules:

- if permission info is available from existing session/backend contract, hide/disable inaccessible action;
- if not available, render conservatively and handle backend denial;
- client state never grants permission;
- System Admin has no special bypass UI.

---

# 17. Loading / empty / stale state

List state:

```text
initial loading
refresh loading
empty dataset
empty filtered result
error
loaded
```

Mutation state:

```text
idle
submitting
success
validation error
conflict
authorization error
unexpected error
```

Avoid blank page on background refresh.

---

# 18. Accessibility baseline

Critical P1 flows:

- labels associated with controls;
- validation messages readable/announced;
- keyboard access for table actions/dialogs;
- focus restored after modal/drawer;
- no color-only status meaning;
- icon-only button has accessible name;
- destructive confirmation has explicit action/cancel;
- reasonable keyboard behavior for tree controls.

Use `@axe-core/playwright` in critical flows.

---

# 19. Frontend state/data policy

## 19.1 Pinia

Use shared store for genuinely shared/session state. Do not mirror the whole backend database into global state.

## 19.2 Zod

Zod can validate transport/form shape, but backend remains business authority.

## 19.3 Decimal values

Keep money/quantity strings through transport/form model where possible.

Do not use JS Number for authoritative calculations.

---

# 20. Navigation and route guard policy

Route guard:

- handles authentication;
- may use known capability for UX;
- does not calculate PBAC;
- does not resolve policy precedence;
- does not special-case System Admin;
- does not override target organization context.

Deep links follow API canonical 404/403 behavior.

---

# 21. Slice UI test matrix

## Application Shell

```text
5 sidebar mode tabs đúng thứ tự
Menu search filters current mode
Resize clamp min/max
Double-click reset width
Collapse -> 72px icon rail
Expand restores previous width
Active mode persists across collapse
Favorites cannot bypass permission
Recent list isolated per user
Organization mode only shows visible tree nodes
Workspace tab opens/focuses/deduplicates
Dirty tab close requires confirmation
Horizontal tabs scroll and do not wrap
Keyboard navigation for mode tabs / resize handle
Language switch rerenders shell/menu/tab labels
Locale persists after reload
API requests carry current Accept-Language
```

## Slice 1

```text
Organization tree authorized scope
Create Company under GROUP
Reject invalid child type
Contact add/edit/history
Ward filtered by Province
```

## Slice 2

```text
Employee create/edit
Manager self/cycle error mapping
Primary Assignment overlap error mapping
Secondary Assignment visible but not mislabeled
```

## Slice 3

```text
Authorization Group member edit
Permission matrix edit
Direct User Permission edit
Policy participant switching
ABSOLUTE_DENY selectable
Authorization Log read-only
Lifecycle duplicate transition error
```

## Slice 4

```text
Numbering config validation
Immutable fields lock after issuance
No delete for issued series
Sample does not consume counter
```

## Slice 5

```text
Product role-dependent fields
Child full name uses " - "
COMBO hides alternate UOM
PARENT operational actions not shown
```

## Slice 6

```text
Price List scope GROUP/COMPANY only
COMBO UOM locked to Base
Price source display
Combo cycle error mapping
```

## Slice 7

```text
Import default CREATE_ONLY
CSV/XLSX upload
Job result counts
Generated Product Code shown
Failed row retains original row number
```

---

# 22. API/UI dependency matrix

| UI Screen | API dependency |
|---|---|
| Provinces | `/provinces*` |
| Wards | `/wards*` |
| Organization Tree | `/organizations*`, `/organizations/tree` |
| Contacts | `/contacts*` |
| Departments | `/departments*` |
| Positions | `/positions*` |
| Employees | `/employees*` |
| Assignments | `/employee-assignments*` |
| Users | `/users*`, `/users/{id}/permissions` |
| Authorization Groups | `/authorization-groups*` |
| Resources | `/resources*` |
| Policies | `/policies*` |
| Authorization Logs | `/authorization-logs*` |
| Workflow States | `/workflow-states*` |
| Lifecycles | `/lifecycles*` |
| Auto Numbering | `/resources/{id}/numbering-series*` |
| Product Groups | `/product-groups*` |
| UOM | `/product-uoms*` |
| Products | `/products*` |
| Product UOM | `/products/{id}/uom-conversions` |
| Combo Components | `/products/{id}/combo-items` |
| Price Lists | `/price-lists*` |
| Price Resolution Tester | `/price-resolutions` |
| Product Import | `/products/import-jobs`, `/import-jobs*` |

---

# 23. Design-pending items

| Item | UI behavior |
|---|---|
| Employee gender catalog | select exists but options pending `DEC-P1-001` |
| Shield User create/edit fields | form blocked until API contract ready |
| COMBO authoritative preview | blocked until preview endpoint approved if needed |
| Export exact file format | action placement ready; format waits API |
| Production retention | Operations decision; no new UI semantics |

CHILD separator is resolved for UI v1.0 as `" - "` and remains display-only.

---

# 24. UI slice Definition of Done

A UI slice is `VERIFIED` when:

```text
[ ] corresponding API contract VERIFIED
[ ] route/navigation implemented
[ ] permission-aware action UX implemented
[ ] list/filter/sort/page implemented as applicable
[ ] create/edit/detail/state flows pass
[ ] backend validation mapped
[ ] 401/403/404/409/422/500 handled
[ ] vue-tsc pass
[ ] eslint/oxlint pass
[ ] prettier check pass
[ ] vitest pass
[ ] production build pass
[ ] Playwright critical flow pass
[ ] axe smoke pass
[ ] no frontend-only business-rule divergence
```

---

# 25. Baseline promotion criteria

`06_P1_UI_Design_v1.0.md` may move from `draft` to `baseline` when:

- repository UI shell/current component conventions verified by `P1-ENV-001`;
- routing strategy confirmed against existing frontend;
- Slice 1 API contract is `CONTRACT_READY`;
- design-pending items are explicitly accepted as non-blocking or resolved;
- Product/Architecture/Frontend agree this document is source-of-truth for P1 screen behavior.

After baseline, visual/layout-only changes may bump UI Design without changing upstream artifacts if they do not alter business/API semantics.
