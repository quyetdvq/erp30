# HIS-03.2 — Compact History API wire-shape hotfix

**Baseline:** `erp_v2.0.0_his03_1_history_render_fixed.zip` (HIS-03.1).
**Scope:** Backend HTTP JSON serialization + regression; no frontend, database, migration, PBAC, route or business event changes.

## Root cause (reproduced)

- `HistoryPolicy::diff()` writes redacted changes as `new \stdClass()`, serialized into `changes_json` as `{"name":{}}`.
- `HistoryEventPresenter::compactChanges()` correctly reads the raw JSON object and returns `stdClass` for each redacted field.
- **Bug:** shared `ApiEnvelope::normalize()` recursively converted all objects via `get_object_vars()` into PHP arrays. A zero-property `stdClass` therefore became `[]`, causing the HTTP response to contain `"name":[]` even though the stored JSON had `"name":{}`.
- Strict HIS-POL-002 frontend intentionally rejects an empty JSON array as an invalid change marker; HIS-03.1 displays a safe warning rather than crashing.

## Fix and invariants

- Preserve **only an empty `stdClass` instance** as a JSON object while normalizing API responses; other object normalization, snake_case fields, ID stringification, decimal handling, and list arrays remain unchanged.
- Final API response for both Customer History and System Monitor uses `"field": {}` for redacted changes and `"field": [before, after]` for permissible diffs.
- Do **not** relax frontend schema checks or introduce legacy `{before,after}` / `{changed:true}` compatibility.
- Do **not** erase History rows: existing compact data is already stored correctly (provided that raw `changes_json` in MariaDB contains `{}`). `[]` is a **serialization-layer defect**, not proof that the database contains `[]`.

## Tests

1. `php backend/tests/history/history_presenter_pure.php` now asserts the **complete `ApiEnvelope::success(...)` JSON wire payload**, not only the Presenter result.
2. `php backend/tests/m1_pure.php` asserts `{}` is retained for redacted markers while true empty arrays remain `[]`, tuples remain ordered arrays, and ordinary object-key normalization still works.
3. Run `backend/tools/test-unit.ps1 RUN HIS-UNIT`, `RUN UNIT-STANDARD`, `RUN CUS-UNIT`, `RUN ROUTES`, and `RUN P1-STANDARDS` on Windows. No frontend code was modified, so its prior build remains a separate accepted check; optional `pnpm run type-check` and `pnpm run build` can be rerun.

## DEV/UAT — no data purge or migration

- Apply patch on HIS-03.1 with `git apply --check`, then `git apply`.
- Reload the Customer History tab and System Monitor; inspect the **actual network JSON response**. Redacted changes must appear as `{}` rather than `[]`; the warning should disappear for valid compact records.
- If warnings persist, inspect a specific raw `changes_json` **read-only** in MariaDB before considering any data cleanup:

```sql
SELECT id, resource_type, resource_id, changes_json
FROM dinktech_business_history_events
WHERE id IN (2, 3, 4);
```

- If the database truly contains `[]`, it is malformed HIS-POL-002 data and needs a separately reviewed DEV cleanup/repair. Do not silently treat it as `{}` and do not delete unrelated audit data.
- `resource_code` / `resource_name` can be NULL on the per-Customer History endpoint. The System Monitor joins Customer for those labels. That distinction is unrelated to this bug.

**Signoff scope:** Offline source regression; HTTP/MariaDB UAT remains owned by the DEV operator.
