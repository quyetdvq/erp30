# P1-DSP-001 Display Settings Infrastructure Assessment

Status: DONE  
Task: P1-DSP-001  
Risk/profile: MEDIUM / service-standard

## Authority and scope

Validated compiled task context was used with `AUTHORITY_CACHE: VALID`.
Dependencies `P1-ENV-001` and `P1-ORG-002` were DONE. The applicable authority
versions are UR v1.0.14, FRS v1.10, DBD v1.13, Implementation Contract v1.8,
Implementation Plan v1.8, API Design v1.6, UI Design v1.7, and
`07_CHANGE_DISPLAY_SETTING_001_v1.1`.

This task implements only the registry, canonical server context model, and
CodeIgniter Settings adapter. It does not implement Organization inheritance,
mutation authorization/audit writes, API/OpenAPI, frontend formatting/UI, or
`P1-DSP-002+`.

## Settings integration inventory

- CodeIgniter Framework: v4.7.4.
- `codeigniter4/settings`: v2.4.0, installed by Composer and auto-discovered.
- Existing integration: `CodeIgniter\Settings\Settings` with the package
  `DatabaseHandler`; no second Settings store was introduced.
- `Config\Settings` remains the package configuration. No application
  `backend/app/Config/Settings.php` exists.
- Runtime database inventory (`erp`, read-only) contains `dinktech_settings`.
  Package migrations are applied: `CreateSettingsTable`, `AddContextColumn`,
  and `ConvertSqlsrvValueColumn`.
- Physical `dinktech_settings` columns are `id`, `class`, `key`, `value`,
  `type`, `context`, `created_at`, and `updated_at`; the application database
  prefix is `dinktech_`. No application Settings migration was added.

Existing/prototype disposition:

- `BusinessTimezoneProvider` and `BusinessTimezoneResolver` were left
  untouched and remain the existing business-timezone boundary.
- No Display Settings registry or adapter existed before this task.
- The new classes are `DisplaySettingRegistry`, `DisplaySettingsContext`, and
  `CodeIgniterDisplaySettingsStorage` under
  `backend/app/Infrastructure/Settings/`.

## Canonical registry and mapping

The registry contains exactly 15 source-controlled definitions. Every entry
records type, default, validation rule, server scope (`SYSTEM` and
`ORGANIZATION`), local-user-overridable metadata, and an explicit storage key.
Defaults are exactly:

| Canonical key | Default | CI Settings key |
|---|---|---|
| `Display.timezone` | `Asia/Ho_Chi_Minh` | `Display.timezone` |
| `Display.dateFormat` | `DD/MM/YYYY` | `Display.dateFormat` |
| `Display.dateTimeFormat` | `DD/MM/YYYY HH:mm` | `Display.dateTimeFormat` |
| `Display.timeFormat` | `HH:mm` | `Display.timeFormat` |
| `Display.firstDayOfWeek` | `MONDAY` | `Display.firstDayOfWeek` |
| `Display.number.decimalSeparator` | `.` | `Display.number_decimalSeparator` |
| `Display.number.groupSeparator` | `,` | `Display.number_groupSeparator` |
| `Display.quantityDecimals` | `3` | `Display.quantityDecimals` |
| `Display.percentageDecimals` | `3` | `Display.percentageDecimals` |
| `Display.trailingZeros` | `false` | `Display.trailingZeros` |
| `Display.money.decimalSeparator` | `.` | `Display.money_decimalSeparator` |
| `Display.money.groupSeparator` | `,` | `Display.money_groupSeparator` |
| `Display.money.symbolMode` | `SYMBOL` | `Display.money_symbolMode` |
| `Display.money.symbolPosition` | `AUTO` | `Display.money_symbolPosition` |
| `Display.money.decimalsMode` | `CURRENCY_DEFAULT` | `Display.money_decimalsMode` |

CI Settings accepts `Class.property` syntax and splits on dots. The explicit
one-dot mapping above avoids truncating nested canonical keys. The registry
also provides the reverse mapping and rejects unknown canonical or storage
keys, so the adapter cannot bypass the allowlist.

Validation rejects wrong PHP types, invalid IANA timezones, empty/non-single
character separators, negative decimal counts, and malformed token values.
No unresolved enum catalog was invented. Locale, workspace preferences,
Contact defaults/normalization, business timezone, and effective-date settings
are absent. All 15 entries are marked eligible for the future frontend-local
override layer; no server user scope is persisted.

## Context and boundary

`DisplaySettingsContext` accepts only `system` and
`organization:<positive signed-BIGINT id>`. Organization IDs are retained as
canonical decimal strings, including `9223372036854775807`, and are never
coerced through a floating-point or lossy integer path. Zero, negative,
leading-zero, overflow, malformed, `company:<id>`, `user:<id>`, and arbitrary
context types are rejected. No Organization ancestry is queried or accepted
from a caller.

`CodeIgniterDisplaySettingsStorage` validates the registry key and context on
every get/set/forget, passes the canonical context unchanged to CI Settings,
and uses only the existing package persistence. It does not implement effective
resolution, mutation authorization, audit writes, or a cross-request cache.

## Audit and Resource/Action inventory

The approved later mutation sink exists as
`AuthorizationAuditService` + `AuthorizationLogRepository`, persisting to
`dinktech_authorization_logs`. This task adds no audit call or table; later
`P1-DSP-003` must reuse that sink.

`ResourceActionSeeder` currently has no `display_setting` resource or
`display_setting.view/edit` actions. This was inventoried only; seed/PBAC
changes are out of scope for P1-DSP-001.

## Schema and regression evidence

No schema or migration changed. No `display_settings`,
`user_display_settings`, or generic settings table was created. Existing
Business Timezone semantics were not changed: `BusinessTimezoneResolver` still
reads `App.businessTimezone` using its existing `company:<id>`/`group:<id>`
contexts, and Gate 0 timezone regression remains green.

Validation run against `CI_ENVIRONMENT=testing` and `erp_test` where applicable:

| Check | Result |
|---|---|
| `P1Dsp001DisplaySettingsTest.php --no-coverage` | PASS; 11 tests, 100 assertions |
| Gate 0 + CTX-001 + ORG-002 tests | PASS; 18 tests, 47 assertions |
| Full backend PHPUnit `--no-coverage` | PASS; 414 tests, 3754 assertions |
| PHPStan level 8 (`app` + focused test) | PASS; no errors |
| Architecture guard | PASS; 44 application files checked |
| Context cache/module-map/task registry validator | PASS; cache valid, 107 tasks, acyclic graph |
| Ledger validator (pre-closure) | PASS; 79 DONE tasks |
| `git diff --check` | PASS |

## Commit and ledger closure

Implementation commit:

`7de397260c643b78055f9a4a04ff0bc980ee720c`

Subject: `feat(P1-DSP-001): add display settings registry and storage adapter`  
Author: Dinh Quyet  
Timestamp: `2026-10-07T12:16:31+07:00`

Evidence/ledger closure is recorded separately after the implementation commit.
`P1-DSP-002 started: NO`.
