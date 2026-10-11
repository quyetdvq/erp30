---
document_id: "CHANGE-DISPLAY-SETTING-001"
title: "P1 Display Settings Foundation - Change Control and Codex Handoff"
version: "1.0"
status: "approved-for-implementation"
date: "2026-10-07"
scope: "Pre-Gate-8 Display Settings Foundation"
---

# CHANGE-DISPLAY-SETTING-001 - P1 Display Settings Foundation

## 1. Decision

DinkERP P1 bổ sung `display_setting` trước Gate 8 Frontend P1. Capability này **chỉ phục vụ presentation/UX**.

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

## 5. Persistence

Reuse CodeIgniter Settings existing integration. Definitions/defaults/validation remain source-controlled.

No new generic/business settings table is approved by this change.

Codex must first inspect the repository for the existing general mutation audit sink. If no approved audit sink exists, stop `P1-DSP-003` as BLOCKED and report the exact gap; do not create an undocumented table.

## 6. Frontend

- locale stays `vi`/`en` client preference;
- local user presentation overlay remains browser/workspace-scoped and may override all 15 canonical Display Setting keys;
- common formatter is mandatory for Date/DateTime/Time/Quantity/Percentage/Money;
- canonical API/form value must remain separate from display text;
- `Display.timezone` must never feed business date logic.

## 7. Delivery gate

New mandatory gate:

```text
Gate 7A - Display Settings Foundation
```

Tasks:

```text
P1-DSP-001 infrastructure/registry/context reconciliation
P1-DSP-002 effective Organization-path resolver
P1-DSP-003 mutation + Authorization + audit
P1-DSP-004 API/OpenAPI/generated client
P1-DSP-005 frontend runtime/formatter/local overlay
P1-DSP-006 admin Display Settings + My Display Preferences UI
P1-DSP-090 regression/E2E closure
```

Gate 8 is not allowed to open until Gate 7A is CLOSED.

## 8. Codex implementation order

1. Read all revised authority docs listed below.
2. Inventory actual repo Settings package/schema, Organization services, AuthorizationService and audit sink.
3. Implement `P1-DSP-001` only within approved boundaries.
4. Continue tasks in dependency order.
5. Do not rewrite applied migration; use forward-only seed/migration if Resource catalog needs `display_setting`.
6. Update OpenAPI and regenerate frontend client in same API change.
7. Run Gate 7A regression catalog before marking closure.

## 9. Required authority set

```text
00_User_Requirements_v1.0.14.md
01_P1_Functional_Requirements_Specification_v1.10.md
02_P1_Database_Design_v1.13.md
03_P1_Implementation_Contract_v1.8.md
04_P1_Implementation_Plan_v1.7.md
05_P1_API_Design_v1.5.md
06_P1_UI_Design_v1.6.md
```

## 10. Stop conditions for Codex

Codex must stop and report instead of inventing behavior if implementation would require:
- server-side USER Display Setting persistence;
- new locale persistence/backend locale profile;
- Display Settings as Business Timezone authority;
- a new generic settings/audit table not in revised DBD;
- a new Resource/Action beyond `display_setting.view/edit`;
- arbitrary dynamic setting definitions;
- client-supplied Organization ancestry;
- using formatted/rounded display value as authoritative business input.
