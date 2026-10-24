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
