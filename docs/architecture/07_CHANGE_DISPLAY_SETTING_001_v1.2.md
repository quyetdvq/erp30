---
document_id: "CHANGE-DISPLAY-SETTING-001"
title: "P1 Display Settings Foundation - Change Control and Codex Handoff"
version: "1.2"
status: "approved-for-implementation"
date: "2026-10-07"
scope: "Display Settings Core Foundation + Early Gate 8 UI Closure"
---

# CHANGE-DISPLAY-SETTING-001 - P1 Display Settings Foundation

## 1. Decision

DinkERP P1 bổ sung `display_setting` theo hai bước: core foundation trước Gate 8 và administrative/self-preference UI ở early Gate 8. Capability này **chỉ phục vụ presentation/UX**.

Hard invariant:

```text
Display Settings != Business Settings
```

Display Settings không được quyết định Authorization, Workflow, Pricing, effective date, Auto Numbering, Import behavior, canonical persistence hoặc authoritative decimal calculation.

## 2. Canonical scope

Server:

```text
SYSTEM
ORGANIZATION
```

Persistence context:

```text
system
organization:<id>
```

Không có server `COMPANY` pseudo-scope và không có server `USER` persistence trong P1.

Effective resolution:

```text
current Organization
-> exact ancestors
-> GROUP
-> SYSTEM
-> registry default
```

## 3. Canonical registry P1

Date/Time:
- `Display.timezone`
- `Display.dateFormat`
- `Display.dateTimeFormat`
- `Display.timeFormat`
- `Display.firstDayOfWeek`

Number:
- `Display.number.decimalSeparator`
- `Display.number.groupSeparator`
- `Display.quantityDecimals`
- `Display.percentageDecimals`
- `Display.trailingZeros`

Money:
- `Display.money.decimalSeparator`
- `Display.money.groupSeparator`
- `Display.money.symbolMode`
- `Display.money.symbolPosition`
- `Display.money.decimalsMode`

Excluded from server registry P1:
- locale;
- contact input normalization/default calling code;
- workspace page size/density/sidebar/tabs/favorites/recent;
- any business timezone/effective-date setting.

## 4. Security

Resource:

```text
display_setting
```

Domain:

```text
ORGANIZATION
```

Actions:

```text
view
edit
```

`view/edit` protect administrative definitions/override/source inspection and mutation. Runtime effective presentation snapshot is authenticated bootstrap data and does not require every ERP User to hold administrative `display_setting.view`; Organization runtime context still enforces safe Organization visibility.

Context mapping:
- SYSTEM -> GROUP;
- ORGANIZATION -> target Organization.

Canonical AuthorizationService/PBAC required. Shield `.can()` is not sufficient.

## 5. Persistence and configuration audit

Reuse CodeIgniter Settings existing integration. Definitions/defaults/validation remain source-controlled.

No new generic/business settings value table is approved by this change.

The implementation inventory confirmed that no approved sink can represent Display Settings mutation audit without violating the Authorization Audit boundary. Therefore v1.2 approves one additive technical table: `configuration_audit_events`.

Canonical audit decision:
- initial producer: Display Settings;
- operation: `SET | RESET`;
- store actor, SYSTEM/ORGANIZATION context, setting key, old/new canonical override value, request correlation and UTC timestamp;
- insert-only runtime evidence;
- Settings mutation + audit inserts use the same transaction;
- idempotent reset of a missing override creates no event;
- `authorization_logs` remains authorization-decision-only and must not receive Display Settings mutation events;
- schema addition is forward-only and is owned by `P1-DSP-003`; no new P1 Task ID is created.

## 6. Frontend

- locale stays `vi`/`en` client preference;
- local user presentation overlay remains browser/workspace-scoped and may override all 15 canonical Display Setting keys;
- common formatter is mandatory for Date/DateTime/Time/Quantity/Percentage/Money;
- canonical API/form value must remain separate from display text;
- `Display.timezone` must never feed business date logic.

## 7. Delivery placement

### Gate 7A - Display Settings Core Foundation

Tasks:

```text
P1-DSP-001 infrastructure/registry/context reconciliation
P1-DSP-002 effective Organization-path resolver
P1-DSP-003 mutation + Authorization + audit
P1-DSP-004 API/OpenAPI/generated client
P1-DSP-005 frontend runtime/formatter/local overlay foundation
```

Gate 8 is allowed to open when `P1-DSP-001..005 = DONE` and Gate 7A is declared `CLOSED`.

### Early Gate 8 - Display Settings UI checkpoint

Execution order:

```text
P1-FE-001 App shell/router/session/error boundary
P1-FE-002 Typed API integration + common query state
P1-DSP-006 Admin Display Settings + My Display Preferences UI
P1-DSP-090 Display Settings regression/E2E closure
P1-FE-003 and remaining feature UI work
```

`P1-DSP-006` depends on `P1-DSP-004`, `P1-DSP-005`, `P1-FE-001`, and `P1-FE-002`. `P1-DSP-090` closes the Display Settings change across backend/API/frontend. `P1-FE-003` and `P1-FE-010..080` must not enter `IN_PROGRESS` before `P1-DSP-090 = DONE`.

The P1 registry remains `107` tasks. The 12 canonical `P1-FE-*` Gate 8 Task IDs remain unchanged; `P1-DSP-006` and `P1-DSP-090` keep their existing DSP Task IDs and are only rescheduled into Gate 8 execution.

## 8. Codex implementation order

1. Read all revised authority docs listed below.
2. Inventory actual repo Settings package/schema, Organization services, AuthorizationService and audit sink.
3. Implement `P1-DSP-001..005` in dependency order and close Gate 7A.
4. Enter Gate 8 and complete `P1-FE-001` + `P1-FE-002`.
5. Implement `P1-DSP-006` using the established shell/router/error boundary and typed API integration; do not create parallel frontend infrastructure.
6. Run `P1-DSP-090` regression/E2E closure.
7. Only after `P1-DSP-090 = DONE`, continue `P1-FE-003` and feature UI work.
8. Do not rewrite applied migration; use forward-only seed/migration if Resource catalog needs `display_setting`.
9. Update OpenAPI and regenerate frontend client in the same API change.

## 9. Required authority set

```text
00_User_Requirements_v1.0.14.md
01_P1_Functional_Requirements_Specification_v1.11.md
02_P1_Database_Design_v1.14.md
03_P1_Implementation_Contract_v1.9.md
04_P1_Implementation_Plan_v1.9.md
05_P1_API_Design_v1.7.md
06_P1_UI_Design_v1.8.md
```

## 10. Stop conditions for Codex

Codex must stop and report instead of inventing behavior if implementation would require:
- server-side USER Display Setting persistence;
- new locale persistence/backend locale profile;
- Display Settings as Business Timezone authority;
- any settings/audit persistence beyond the approved `configuration_audit_events` sink in revised DBD;
- a new Resource/Action beyond `display_setting.view/edit`;
- arbitrary dynamic setting definitions;
- client-supplied Organization ancestry;
- using formatted/rounded display value as authoritative business input.
