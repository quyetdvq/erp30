# NUM-HF05 — Auto Numbering overview read model

Scope: `/configuration/auto-numbering` data transport only. This supersedes
HF04 **without applying HF04**; it is based on `erp_v2.3.0.zip` and is compatible
with the HF03 whitespace-only fix. No database migration or UI redesign.

## Read contract

`GET /api/v1/numbering-series/overview`

```json
{
  "data": {
    "resources": [
      {"id":"2","type":"PRODUCT","name":"Products","domain":"MASTER","status":"ACTIVE",
       "allowed_actions":{"configure_numbering":true}}
    ],
    "series": [
      {"id":"21","resource_id":"2","series_code":"PRD","name":"Product Number",
       "scope_type":"GROUP","pattern":"PRD-{SEQ:6}","reset_policy":"NEVER",
       "generation_mode":"AUTO_REQUIRED","start_number":"1","increment_by":1,
       "is_default":true,"status":"ACTIVE"}
    ]
  },
  "meta": {"request_id":"<server-generated>"}
}
```

The Resource list contains **only** persisted Resources with both effective
`resource.view` and effective `resource.configure_numbering` decisions. The
implementation evaluates UnifiedAccessService for every persisted Resource,
retaining organization scope, State, base permissions, policy DENY and
ABSOLUTE_DENY. No client-supplied IDs and no permission hint can expand access.
The series SQL reader queries only these authorized IDs in batches of 200.
Unknown/cross-Resource/duplicate rows reject the whole overview.

Resource order remains `type ASC, id ASC`; series order remains Resource order,
then `id ASC`. The response is intentionally complete and unpaginated to
preserve the existing local All Resource table filter and pagination. Monitor
payload size before deciding to introduce server pagination in a separate
reviewed change.

## Browser request contract

| Action | Auto Numbering request |
|---|---|
| Enter page | 1 overview GET |
| Filter All / choose Resource / clear filter | 0 |
| Refresh while All selected | 1 overview GET |
| Refresh while Resource selected | 1 existing Resource-scoped series GET |
| Open details | Existing Resource-scoped detail GET |
| Create / edit / toggle / validate | Existing POST/PATCH endpoints unchanged |
| Successful mutation | Merge canonical response into local complete snapshot; no list reload |

The header's Notifications `sync` and `summary` requests are **not** Numbering
requests. HF05 deliberately preserves them; suppressing them based on the
active route would change global notification functionality.

## Deployment and acceptance

1. Deploy backend FIRST; verify authenticated overview GET returns 200 and only
   authorized Resource IDs/series. Keep existing Resource-scoped API-040 routes.
2. Deploy frontend. Generate the typed SDK on a machine with dependencies:
   `cd frontend && pnpm run openapi:generate` (canonical contract was updated).
   HF05's overview reader uses the existing shared Axios transport and does
   not require the generated operation at runtime.
3. Enter Auto Numbering with DevTools Network open, filtered to `/numbering-series`.
   Expect one `GET /api/v1/numbering-series/overview`, **zero per-Resource series
   GETs** on initial load. Other AppShell requests are outside this count.
4. Test All filter, per-Resource filter, clear filter, pagination, create/edit,
   state toggle, View detail, validate-config, Refresh both scopes, and an
   account without configure_numbering permission. Exercise a 403 refresh to
   confirm stale rows are discarded and no retry loop starts.
5. Run static/route/PBAC regression, frontend TypeScript/Vitest/Playwright and
   OpenAPI lint and generation gates. No database reset or migration.

**Rollback:** deploy the previous frontend first (it uses the existing scoped
endpoints), then optionally remove the unused overview backend route. Do not
reverse the order during rollout.
