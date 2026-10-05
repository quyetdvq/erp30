---
document_id: "UI-MINI-ERP-P1-001"
title: "P1 UI Design - Dinktech Mini ERP"
version: "1.0"
status: "draft"
owner: "Product / Architecture / Frontend"
date: "2026-10-05"
scope: "P1 UI/UX design by rolling vertical slice"
authority:
  ur: "00_User_Requirements_v1.0.11.md"
  frs: "01_P1_Functional_Requirements_Specification_v1.6.md"
  dbd: "02_P1_Database_Design_v1.9.md"
  implementation_contract: "03_P1_Implementation_Contract_v1.4.md"
  implementation_plan: "04_P1_Implementation_Plan_v1.1.md"
  api_design: "05_P1_API_Design_v1.0.md"
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

## 4.1 Layout

```text
+-------------------------------------------------------------+
| Top Bar: App | Working Context | Search? | User             |
+----------------------+--------------------------------------+
| Left Navigation      | Page Header                          |
|                      | Breadcrumb / Title / Actions         |
|                      +--------------------------------------+
|                      | Main Content                         |
|                      |                                      |
+----------------------+--------------------------------------+
```

## 4.2 Top bar

Elements:

- application name;
- Working Context indicator;
- authenticated user menu;
- optional support/debug Request ID copy affordance.

### Working Context

Working Context may show default Organization Context resolved from Primary Assignment, but:

- it is a selector/hint only where feature allows context selection;
- it does not override authoritative target context;
- Group-scoped resources show `GROUP`;
- a persisted target opens using target context regardless of header selection;
- context mismatch error from backend must be surfaced, not silently retried with another context.

## 4.3 Left navigation

Proposed P1 IA:

```text
Foundation
  Administrative Locations
    Provinces / Cities
    Wards / Communes
  Organizations

People
  Employees
  Departments
  Positions

Security
  Users
  Authorization Groups
  Resources & Actions
  Policies
  Authorization Logs

Configuration
  Workflow States
  Lifecycles
  Auto Numbering

Products
  Product Groups
  UOM
  Products
  Price Lists
  Product Import
```

Menu visibility may use known permission/capability info for UX, but backend remains authority.

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
