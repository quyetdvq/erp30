# CURRENT NEXT WORK - M6.3B.2 + QA consolidation (2026-10-24)

ERP v1.4.0 is at **129/150 frozen HTTP operations**, not at M3. Current source tests are 107/107 offline suites and M3.4D 6/6 static architecture gates. **Live XAMPP delegated Avatar acceptance is still open.**

1. **Now: M6.3B.2.** On owner XAMPP, inspect User 3 metadata with SELECT-only queries and review `writable/m63b1` / `writable/m63b2` reports; do not remove existing Avatar. Select a separate disposable avatar-empty target, active and without base `user.edit`, plus distinct delegated admin. Run DB-pinned preflight. Run `--apply` only after explicit authorization and non-production DB confirmation; review sanitized output and final independent SQL/Apache assertions.
2. **Then: targeted M5/M6 runtime debt.** Capture authorized/limited/denied actor HTTP+SQL+PBAC, `en`/`vi`, rollback, scope/no-leak total, media/numbering fault cases and independent baseline parity per `M6_QA_CONSOLIDATION_2026-10-24.md`.
3. **Then: M6.4A** Product options/resolve/UOM-labels (3 operations, 132/150). Preserve frozen schema and row PBAC before lookup filtering/counting.
4. **M7:** UOM conversions (2, 134), Combo list/replace (2, 136), Price Lists (7, 143), remaining Product relation/detail/sales-UOM resolves (3, 146).
5. **M8:** Product Import jobs/rows (4, 150). **M9:** final full acceptance, release rehearsal and rollback (no new operations).

Important current gate commands (from repo root):

```powershell
python backend\tools\verify_baseline.py --v1-root .
python backend\tools\verify_layout.py
python backend\tools\verify_routes.py
php backend\tools\test-unit-check.php
php backend\tools\m34b_run.php --offline
php backend\tools\m34d_accept.php
php backend\tools\m63b_accept.php
```

Scripts above do not require a database and do not run Avatar mutation tests. `m34d_accept.php` writes only a local acceptance report. Do not treat source PASS as production or live HTTP acceptance. Details: `M6_3B_2_ADMIN_DELEGATION_AVATAR_QA.md`.

---

## Archived M3 plan (2026-10-23; no longer the current next milestone)

# Next milestone - M3 Access/PBAC with canonical State model

## Accepted runtime baseline (owner confirmation, 2026-10-23)

- M0/M1 Identity/CSRF/profile/session: accepted on owner's XAMPP.
- M2 read models: 39/39 assertions and `php spark m2:verify` PASS on owner's DB.
- M2.1.1 read routes: route tests 15/15, PBAC/list pure 36/36 and `php spark m21:verify` PASS; owner confirms Frontend read-route tests OK.
- No change requested to Frontend or to the accepted M1-M2.1.1 runtime code in this documentation patch.

## Mandatory M3 order

1. Inspect actual Resource catalog, State/Lifecycle assignments, existing policies and code baseline.
2. Implement a single State-model classifier from [`RESOURCE_STATE_AND_LIFECYCLE_RULES.md`](RESOURCE_STATE_AND_LIFECYCLE_RULES.md). Exactly 10 independent Resources: eight Native State, two All States only; all others Lifecycle-managed.
3. Build shared authorization decision use cases: Base Permission, actor/scope facts, PBAC branches, State matching and `ABSOLUTE_DENY` precedence. State comes from persisted trustworthy data.
4. Use the classifier in Access and Workflow, with no Lifecycle Transitions for the ten independent Resources. Preserve existing public API contracts.
5. Add audit, state-scoped policy tests, scope/labels/total tests, and real-account differential tests against `backend_basline`.
6. Only then enable mutation routes. M4 implements Lifecycle Transitions for managed Resources using the same classifier, not a second interpretation.

**Important:** Existing baseline has a temporary compatibility path for business Resources lacking `lifecycle_id`. M3 must audit missing associations and review corrections; it must not quietly treat them as Native or silently modify DB/policies.

Paths remain: `backend/` new code; `backend_basline/` reference; `backend/public/upload/` uploaded files; `frontend/` unchanged.

## M3.2 progress (code delivered; runtime parity pending)

- Runtime Actor/Policy Reader, Unified VIEW Access Service and `ReadAccessBridge` added; legacy by default, opt-in shadow.
- `m32:verify` selects users/rows on actual DB and compares accepted M2.1.1 VIEW gate vs M3.2; command is read-only.
- Offline 22 fixture assertions, 1,024 cases against baseline policy-lane implementation; complete HTTP baseline parity still pending.
- Next gate: run `m32:verify`, enable shadow for Frontend/negative test only after it passes. Do not enable enforce or open CRUD yet.
- Implementation and QA: `M3_2_IMPLEMENTATION_AND_QA.md`.
