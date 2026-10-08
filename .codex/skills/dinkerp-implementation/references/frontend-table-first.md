# DinkERP Frontend Presentation Standard — Table First

## 1. Core invariant

For every **List Management** screen, use **Table First**.

The table is the primary content surface of the page.

Canonical page flow:

```text
Page Header
-> Query Controls
-> Top Pagination
-> Data Table
-> Bottom Pagination
```

Do not replace this structure with:

- permanent master-detail layouts;
- card grids;
- dashboard cards;
- permanently expanded record details;
- split-screen detail panels;

unless an approved UI authority explicitly requires a different layout.

A list page exists primarily to:

- find records;
- filter records;
- sort records;
- inspect summary fields;
- perform allowed actions;
- navigate pagination;
- create/import when supported.

Full record details must not occupy permanent space on the list page.

## 2. Applicability

Apply this rule to every screen whose primary responsibility is managing a collection of records.

Examples:

```text
/security/users
/security/authorization-groups
/security/resources
/security/policies
/security/authorization-logs

/configuration/workflow-states
/configuration/lifecycles
/configuration/auto-numbering

/people/departments
/people/positions
/people/employees

/master-data/*
/products/*
/pricing/*
/imports/*
```

Also apply it to new P2/P3 management lists unless a later approved UI contract supersedes this rule.

Do not apply Table First mechanically to:

- dashboards;
- reports whose primary output is a chart;
- wizard workflows;
- transaction-entry forms;
- dedicated detail pages;
- tree-first navigation screens;
- specialized visual editors explicitly approved by authority.

## 3. Canonical List Management layout

Every List Management page must follow this visual order.

```text
+------------------------------------------------------------------+
| Page Title                                  [Import] [New/Create] |
| Optional subtitle / scope information                            |
+------------------------------------------------------------------+

+------------------------------------------------------------------+
| Search | Filters | Sort | Apply | Reset | Refresh                |
+------------------------------------------------------------------+

+------------------------------------------------------------------+
| Page Size                                      Pagination         |
+------------------------------------------------------------------+

+------------------------------------------------------------------+
| Data Table                                                       |
|------------------------------------------------------------------|
| Business Identity | ... | Status | Actions                       |
+------------------------------------------------------------------+

+------------------------------------------------------------------+
| Page Size                                      Pagination         |
+------------------------------------------------------------------+
```

Do not place unrelated configuration panels between query controls and table.

Do not render a selected record's full details directly below the table.

## 4. Page Header rules

### 4.1 Title

Place the canonical page title at the top-left.

Examples:

```text
Users
Policies
Workflow States
Lifecycles
Employees
Products
```

Use i18n translation keys.

Do not hard-code user-facing Vietnamese or English text when the application i18n system already owns that text.

### 4.2 Primary actions

Place primary list-level actions at the top-right.

Typical order:

```text
[Import] [New/Create]
```

`New/Create` is normally the primary action.

Only render an action if the capability exists in the canonical Resource/Action contract.

Examples:

```text
user.create
policy.create
employee.create
product.create
product.import
```

Do not invent buttons because they are common in other screens.

### 4.3 Permission-aware visibility

Use the canonical frontend permission helper.

Example:

```text
useBasePermission()
hasBasePermission()
hasBasePermissionKey()
```

Rules:

```text
missing permission    -> hide/disable conservatively
loading/stale         -> do not invent ALLOW
permission present    -> action may be rendered
backend response      -> final authorization authority
```

A visible button does not mean the frontend has authorized the action.

Never infer permission from:

- System Admin name;
- Authorization Group name;
- Position;
- Department;
- route;
- screen access;
- Resource Domain;
- PBAC rules;
- Policy effects.

Frontend must never evaluate PBAC.

## 5. Query Controls

Place query controls immediately before the table.

Minimum supported structure where applicable:

```text
Search
Filters
Sort
Apply
Reset
Refresh
```

### 5.1 Search

Use the list API's canonical `q` parameter when available.

Search control must:

- have an accessible label;
- support keyboard use;
- preserve current query state appropriately;
- not trigger unauthorized local data searching over hidden records.

Backend remains responsible for authorization-safe search results.

### 5.2 Filters

Only expose filters supported by the canonical API.

Do not invent frontend-only business filters that imply server-side semantics not present in the API contract.

Use canonical values internally.

Example:

```text
ACTIVE
INACTIVE
SYSTEM
MASTER
ORGANIZATION
```

Translate labels only for display.

### 5.3 Sort

Only expose allowlisted API sort fields.

Do not generate arbitrary field names from table columns.

### 5.4 Apply

`Apply` executes the current search/filter/sort model.

Do not mutate canonical form values into display-formatted values before query submission.

### 5.5 Reset

Reset query controls to the screen's canonical defaults.

Typical result:

```text
q = empty
filters = defaults
sort = default
page = 1
```

Do not reset unrelated workspace preferences.

### 5.6 Refresh

Refresh the current query using the current:

```text
search
filters
sort
page
page_size
```

Refresh must not silently reset filter state.

Use a consistent refresh icon and accessible label.

## 6. Pagination

### 6.1 Placement

For normal management lists, provide navigation adjacent to the table.

Preferred layout:

```text
Top:
Page Size                         Pagination

Table

Bottom:
Page Size                         Pagination
```

For very small/static lists, duplicate top/bottom controls may be omitted only when shared component policy explicitly allows it.

### 6.2 Server-side authority

Use backend pagination metadata.

Do not calculate authoritative `total` from locally visible rows.

Canonical source:

```text
meta.page
meta.page_size
meta.total
```

### 6.3 State behavior

Changing search/filter should normally reset page to `1`.

Changing page size should ensure the resulting page remains valid.

Do not discard current filters during pagination.

## 7. Table rules

### 7.1 Table is primary

The table must occupy the main content area of List Management screens.

Avoid excessive decorative content above the table.

### 7.2 Column ordering

Use this conceptual ordering:

```text
Business identity
-> primary description
-> scope/type/context
-> relevant operational fields
-> Status
-> Actions
```

Example:

```text
| Code | Name | Domain | Lifecycle | Action Count | Status | Actions |
```

Do not expose every database field merely because it exists.

List columns should support scanning and record identification.

### 7.3 Business identity

Prefer:

```text
Code
Name
Username
Email
Employee Code
Series Code
Resource Type
```

over internal database IDs.

BIGINT IDs may be used for technical references but should not normally be the primary visible identity.

### 7.4 Status column

If the entity has canonical Status:

```text
Status MUST be immediately before Actions.
```

Example:

```text
| Code | Name | Description | Status | Actions |
```

Never place another ordinary data column after Status.

Use a shared status display component or pattern.

Do not communicate status using color alone.

Display translated labels, but preserve canonical raw values internally.

### 7.5 Actions column

`Actions` MUST:

- be the final column;
- be right aligned;
- use consistent width/alignment;
- use shared action controls;
- remain visually distinct from business data.

Canonical structure:

```text
... | Status | Actions
```

Never place Actions in the middle of a table.

### 7.6 Row clicking

Do not make the entire row perform an ambiguous mutation.

If row click opens `View`, that behavior must be consistent across the application and must not conflict with:

- selection;
- checkbox;
- inline action;
- keyboard navigation.

Explicit Actions remain the primary action mechanism.

### 7.7 No permanent detail expansion

Do not use permanent expandable rows to display a complete record form/detail unless approved for that specific screen.

For detailed inspection, open:

```text
Modal
Drawer
Side Panel
```

## 8. Record Actions

Typical row actions:

```text
View
Edit
History
Permissions
Activate
Deactivate
Lock
Reset Password
Delete
Other canonical domain actions
```

Only expose actions that exist in approved authority/API contracts.

Do not automatically provide CRUD actions to every resource.

Examples:

```text
authorization_log
    -> View only

user
    -> actions according to user.* authority

policy
    -> View/Edit/Delete according to contract

workflow_state
    -> View/Edit/Set State
```

Use permission-aware visibility for every protected action.

## 9. Action presentation

For a small number of actions, use visible icon buttons when they remain understandable and accessible.

For many actions, use:

```text
primary/common action
+
overflow menu
```

Every icon-only action must have:

- tooltip;
- accessible name / aria-label;
- keyboard focus;
- consistent icon meaning.

Do not rely on icon shape alone where ambiguity exists.

Destructive or security-sensitive actions must be clearly differentiated and require confirmation when the operation warrants it.

## 10. Record detail presentation

Full record details must be opened only after explicit user action.

Presentation preference:

```text
1. Modal
2. Drawer
3. Side Panel
4. Dedicated detail page only when complexity requires it
```

### 10.1 Modal

Prefer Modal for:

- create forms;
- simple/medium edit forms;
- record preview;
- confirmation;
- small permission/configuration operations.

### 10.2 Drawer

Use Drawer when:

- form is too large for a normal Modal;
- more vertical space is required;
- user should retain table context;
- related tabs need more space.

### 10.3 Side Panel

Use Side Panel only when repeated record inspection is a primary workflow.

Example:

```text
click row A -> inspect
click row B -> inspect
click row C -> inspect
```

Do not introduce Side Panel simply for visual variety.

### 10.4 Dedicated page

Use a dedicated detail page when the record contains substantial multi-section workflows.

Examples may include:

```text
complex Product editor
large Organization editor
Import Job investigation
```

The list page itself must still remain Table First.

## 11. Modal standard

All feature Modals must follow the same structural contract.

```text
Modal
├── Header
│   ├── Title
│   └── Close Button
├── Body
│   └── Content/Form
└── Footer
    ├── Secondary actions
    └── Primary action
```

### 11.1 Header

Header must have:

- title aligned consistently;
- standard typography;
- standard close button;
- no random feature-specific header layout.

Suggested semantic title patterns:

```text
Create User
Edit User
View Policy
Reset Password
Change Status
```

### 11.2 Body

Use shared spacing tokens.

Do not manually invent margins per feature when Design System spacing exists.

Body must support:

- validation messages;
- loading;
- read-only fields;
- grouped form sections;
- scrolling where necessary.

Avoid multiple nested scroll containers.

### 11.3 Footer

Use a shared footer layout.

Canonical order:

```text
[Cancel / Close]                    [Save / Confirm]
```

Primary action is normally on the right.

Destructive confirmations must follow the application's destructive-action convention.

Buttons must share consistent:

- height;
- padding;
- typography;
- gap;
- placement.

Do not reverse Save/Cancel order between screens.

## 12. Form rules

### 12.1 Canonical values

Form state must store canonical domain/API values.

Correct:

```text
API canonical value
-> form model canonical value
-> formatter / translated label
-> display
```

Forbidden:

```text
API value
-> formatted/localized string
-> form state
-> send formatted string back to API
```

Examples:

```text
BIGINT       -> string
DECIMAL      -> decimal string
enum         -> canonical enum code
date         -> canonical API date
datetime     -> canonical UTC instant
```

### 12.2 Read-only fields

Clearly distinguish:

```text
editable
read-only
immutable
derived
```

Do not fake disabled editable controls when a read-only display component communicates the contract better.

### 12.3 Validation

Frontend validation is UX only.

Backend remains authoritative.

Map backend errors:

```text
details[].field -> field error
global error    -> form/global alert
```

Support canonical:

```text
400
403
404
409
422
```

Do not parse localized `error.message` to determine program behavior.

Use `error.code`.

## 13. Create flow

Default List Management create flow:

```text
User clicks New/Create
-> Modal/Drawer opens
-> user edits canonical form
-> submit
-> backend validates/authorizes
-> success
-> close form
-> refresh table
-> preserve meaningful list/query context
```

Do not navigate away from the list for a simple create operation unless complexity requires a dedicated page.

## 14. Edit flow

Default flow:

```text
Actions -> Edit
-> load canonical record if needed
-> Modal/Drawer
-> edit
-> save
-> refresh affected row/list
```

Do not display a permanent edit form below the table.

On `409`:

- keep user-entered values;
- show canonical conflict message;
- offer reload/reconcile where appropriate.

On `403`:

- show permission error;
- do not infer that the permission snapshot is authoritative.

On `404`:

- follow common not-found/no-visibility UX.

## 15. View flow

Default flow:

```text
Actions -> View
-> Modal/Drawer
```

Use read-only presentation.

Do not use editable form controls solely to display values unless the shared component intentionally supports read-only mode.

## 16. Status mutation

Status change must be an explicit action.

Examples:

```text
Activate
Deactivate
Close
Lock
Unlock
```

Do not allow status mutation merely by clicking the displayed Status tag unless an explicit UI pattern has been approved.

For sensitive state changes:

```text
action
-> confirmation
-> backend mutation
-> refresh row/list
```

Status rendering remains read-only in the table.

## 17. Security-sensitive actions

Examples:

```text
Reset Password
Direct Permission change
Authorization Group membership
Policy mutation
Delete
```

Requirements:

- use canonical Resource Action;
- use permission-aware UI filtering;
- require explicit user action;
- show confirmation where appropriate;
- never expose secret material after mutation;
- backend remains final authorization authority.

Do not hide security decisions inside generic `Edit` if the approved authority defines a separate action.

## 18. Permission management presentation

For permission-related record details, prefer tabs inside Modal/Drawer/detail surface.

Example:

```text
User
├── Overview
├── Authorization Groups
└── Direct Permissions
```

Do not expand full permission matrices permanently beneath the User list table.

Mutation must use canonical backend APIs.

Frontend must not:

- calculate PBAC;
- merge policy effects;
- infer Effective Profile authorization outcome;
- treat Direct Permission as final authorization.

## 19. History presentation

`History` must be opened from record Actions when the capability exists.

Prefer:

```text
Modal
Drawer
```

History is read-only unless an approved capability explicitly allows changes.

Do not render historical rows permanently under every table row.

## 20. Loading behavior

### 20.1 Initial loading

Use table-compatible loading state:

- skeleton;
- table loading overlay;
- standard shared loading component.

Do not replace the whole page with unrelated loading UI when only table data is loading.

### 20.2 Refresh loading

When refreshing an existing list:

- preserve current table/query context when practical;
- avoid unnecessary layout jumps;
- show loading state clearly.

### 20.3 Mutation loading

While a mutation is running:

- disable duplicate submit;
- show progress on the initiating control;
- keep form state intact until success.

## 21. Empty states

Distinguish:

```text
No data exists
```

from:

```text
No results match current filters
```

For no data:

- may show Create action if authorized.

For no search/filter result:

- offer Reset Filters where appropriate.

Do not use an empty state that obscures table structure unnecessarily.

## 22. Error states

Use shared application error handling.

List errors should not destroy query state.

For recoverable fetch failure:

```text
show error
+
allow retry/refresh
```

Do not silently render an empty table for a failed request.

## 23. i18n

All application-controlled text must use canonical i18n resources.

Include:

- page title;
- buttons;
- actions;
- status labels;
- modal titles;
- confirmation messages;
- table column headers;
- empty states;
- validation messages owned by frontend;
- accessible labels/tooltips.

Do not localize:

- raw Resource code;
- Action code;
- API enum values in payloads;
- business identifiers.

Translate only display labels.

## 24. Display Settings integration

Use shared presentation formatters where applicable.

Use:

```text
usePresentationSettings()
useFormatters()
```

for:

```text
Date
DateTime
Time
Quantity
Percentage
Money
```

Do not implement per-feature:

```text
toLocaleString()
custom decimal separator logic
custom timezone conversion
custom money rounding
```

Canonical values must remain unchanged.

## 25. Accessibility

Every Table First screen must support keyboard/focus accessibility.

Minimum rules:

- search has accessible name;
- filter controls have labels;
- icon-only buttons have `aria-label`;
- Actions menu keyboard accessible;
- modal focus is trapped appropriately;
- modal opening moves focus correctly;
- modal closing restores focus appropriately;
- Close/Cancel/Save reachable by keyboard;
- status is not color-only;
- pagination controls have accessible names;
- table actions have understandable labels;
- destructive confirmation clearly describes consequence.

Critical flows must be eligible for axe/Playwright accessibility smoke tests.

## 26. Responsive behavior

Desktop ERP productivity is primary.

For smaller widths:

- preserve table as primary surface;
- allow horizontal table scroll where appropriate;
- collapse secondary query controls into filter drawer if needed;
- never move full detail permanently below every row;
- action column must remain discoverable;
- Modal may become larger/full-screen only using shared responsive behavior.

Do not convert the management table into unrelated cards solely for mobile responsiveness unless approved.

## 27. Shared components

Before implementing a new UI pattern, inspect existing shared components.

Prefer reuse for:

```text
DataTable
QueryToolbar
SearchInput
FilterDrawer
Pagination
PageSizeSelector
StatusTag
RowActions
StandardModal
StandardDrawer
ConfirmationModal
FormActions
EmptyState
ErrorState
```

If a reusable component already covers the requirement:

```text
reuse it
```

Do not build a feature-specific duplicate.

If a missing shared primitive affects several screens, extend/create the shared primitive rather than copy-pasting implementations.

## 28. Prohibited patterns

### 28.1 Permanent master-detail list

Do NOT implement:

```text
Table
+
permanent selected record detail underneath
```

unless explicitly approved.

### 28.2 Actions before Status

Wrong:

```text
| Code | Name | Actions | Status |
```

Correct:

```text
| Code | Name | Status | Actions |
```

### 28.3 Columns after Actions

Wrong:

```text
| Code | Actions | Updated At |
```

Actions must be final.

### 28.4 Feature-specific Modal conventions

Do not vary:

- footer order;
- button dimensions;
- spacing;
- close placement;

without Design System authority.

### 28.5 Frontend authorization

Never implement:

```text
if systemAdmin -> allow
if group == "ADMIN" -> allow
if policy says ALLOW -> allow
```

Frontend presentation filtering is not authorization.

### 28.6 Localized canonical values

Never submit display strings such as:

```text
"1.234,50"
"08/10/2026"
"Hoạt động"
```

where API expects:

```text
"1234.50"
"2026-10-08"
"ACTIVE"
```

## 29. Implementation decision algorithm

When implementing any management screen, follow this sequence.

### Step 1 — Classify the screen

Ask:

```text
Is the primary purpose managing a collection of records?
```

If YES -> Table First applies.

### Step 2 — Resolve authority

Identify:

```text
Resource
Actions
API routes
list fields
filters
sort fields
status
permissions
```

Do not design unsupported actions.

### Step 3 — Build list shell

Implement:

```text
Header
Query Controls
Pagination
Table
Pagination
```

### Step 4 — Define columns

Choose only fields needed to identify/manage the record.

Ensure:

```text
Status -> second-last
Actions -> last/right-aligned
```

### Step 5 — Map record actions

For each action determine:

```text
permission key
API
presentation surface
confirmation requirement
error behavior
```

### Step 6 — Choose detail surface

Default:

```text
Modal
```

Use Drawer/Side Panel only when justified by information density or workflow.

### Step 7 — Reuse shared primitives

Do not duplicate existing table/modal/query components.

### Step 8 — Implement states

Cover:

```text
loading
empty
filtered-empty
error
permission denied
mutation pending
success
conflict
```

### Step 9 — Test

Validate structural and behavioral invariants.

## 30. Mandatory acceptance checklist

Every new or modified List Management screen must satisfy:

```text
[ ] Table is the primary content surface.
[ ] Page header appears before query controls.
[ ] Create/New appears only when canonical capability exists.
[ ] Import appears only when canonical capability exists.
[ ] Search/filter/sort follow backend contract.
[ ] Apply/Reset/Refresh use consistent shared pattern.
[ ] Pagination/page size uses backend metadata.
[ ] Table columns prioritize business identity.
[ ] Status is immediately before Actions.
[ ] Actions is final column.
[ ] Actions is right aligned.
[ ] Record details are not permanently rendered on the list page.
[ ] View/Edit/History/Permission open an approved detail surface.
[ ] Modal is preferred for normal record interaction.
[ ] Shared Modal structure is used.
[ ] Modal footer action order is consistent.
[ ] Canonical values remain canonical in form state.
[ ] Permission-aware controls use the shared Base Permission helper.
[ ] Frontend does not evaluate PBAC.
[ ] 403/404/409/422 are handled.
[ ] i18n keys exist for vi/en.
[ ] icon-only controls have accessible names.
[ ] keyboard/focus behavior is valid.
[ ] shared formatters are used for presentation values.
[ ] shared components are reused where available.
[ ] no unapproved card/master-detail alternative was introduced.
```

## 31. Testing requirements

For a changed List Management screen, add or update tests for applicable behavior.

### Unit/component

Test at minimum:

```text
column ordering
Status before Actions
Actions last
permission-aware action visibility
query Reset behavior
canonical form values
modal open/close
mutation error handling
```

### Playwright

Critical management flows should cover:

```text
load list
search/filter
pagination
open View/Edit
save or perform relevant mutation
403/404/409/422 behavior where applicable
keyboard/modal behavior
```

### Accessibility

Run axe on critical list/form interaction.

## 32. Review rules

When reviewing an existing screen, explicitly report deviations from Table First.

Classify findings:

```text
BLOCKING
    violates authority/security/data correctness

REQUIRED UI FIX
    violates Table First invariant or shared presentation standard

CONSISTENCY FIX
    spacing/component/order differs from shared convention

OPTIONAL
    cosmetic enhancement not required by this skill
```

Do not silently refactor unrelated screens unless the current task explicitly includes them.

## 33. Closure reporting

When a frontend task changes a List Management screen, closure evidence should include:

```text
Screens changed
Table First compliance
Column order
Modal/Drawer surfaces
Permission-aware actions
Query/pagination behavior
Unit/component tests
Playwright
Accessibility
Lint/type/build
Known pre-existing issues
```

If any mandatory Table First invariant is intentionally not followed, closure must identify the approved authority that permits the exception.

## 34. Compact invariant for implementation prompts

When another task prompt references this skill, the following shorthand is sufficient:

```text
Apply Table First.

For every List Management screen:
Header -> Query Controls -> Pagination -> Table -> Pagination.

Table is primary.
Status is second-last.
Actions is last and right-aligned.
No permanent record-detail panel on list pages.
Open View/Edit/History/Permission through Modal by default,
Drawer/Side Panel only when justified.
Reuse shared query/table/modal components.
Use permission-aware UX only; backend remains authorization authority.
Keep API/form values canonical.
Follow shared i18n, formatter, accessibility and error conventions.
```
