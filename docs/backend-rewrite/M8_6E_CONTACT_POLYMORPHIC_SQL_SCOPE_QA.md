# M8.6E — Contact polymorphic SQL PBAC

Status: **SOURCE ACCEPTED**. HTTP/MariaDB parity and production approval: **BLOCKED**. Base: M8.6D canonical mandatory authorization, 150 frozen routes.

## Design

The old `ContactService::list()` read all `contacts` and used PHP per-row PBAC before `AuthorizedListPage::paginate()`. M8.6E moves the canonical VIEW constraint into SQL **before COUNT and LIMIT/OFFSET**. Both GET list and detail are mandatory SQL; no `enabled`/`disabled`, `legacy`/`shadow`/`enforce` flags. Mutation behavior, HTTP schemas, write gate, DB schema and frontend are unchanged.

### Two separate organization-scoped resources

- **Organization Contact:** `UPPER(TRIM(c.contact_type)) = 'ORGANIZATION'`, `contacts.contact_ref_id` resolves to an existing `organizations.id`; PBAC is `organization_contact.view` with the persisted Organization's **ID** and the **Contact row's State**.
- **Employee Contact:** `UPPER(TRIM(c.contact_type)) = 'EMPLOYEE'`, `contacts.contact_ref_id` resolves to a real Employee. The employee must have **exactly one** UTC-effective primary `ACTIVE` assignment and its `organization_id` must resolve to a real Organization. PBAC is `employee_contact.view`, using the primary assignment's **organization_id** and the **Contact row's State**.
- Independent `(organization_id, Contact State)` slots for each resource are compiled through `UnifiedAccessService`, including `ALLOW`, `DENY`, `ABSOLUTE_DENY` and trusted Organization ancestry. Never infer a generic `contact.view` grant, share permission decisions between the resources or use a client-supplied owner scope.
- A correlated `NOT EXISTS` check rejects a second simultaneous primary assignment, including one from another company or a noncanonical-case status returned by the original People effective-assignment query under the current MariaDB collation. The chosen primary itself must have exact canonical `ACTIVE` status. Missing Employee/Organization, unsupported contact_type, absent/future/expired/inactive assignment never enter scoped COUNT or page.

**Scope cap:** At most 512 trusted Organizations and 512 authorized organization/State slots **per Contact PBAC resource**. Overflow fails closed with `AUTH_SCOPE_COMPILATION_UNSUPPORTED`, never a permissive SELECT. The cap is a temporary safety boundary and not a performance guarantee.

### SQL / response / consistency

`ContactSql` constructs a single owner predicate reused for COUNT, page and detail. User query parsing is restricted to the existing `contact` whitelist: `q` searches name/email/phone/mobile; filter contact_type/contact_ref_id/status; sort name/email/phone/mobile/contact_type/contact_ref_id/status, with `id` tie-breaker. All values are bound; identifiers and prefixes are validated. `SELECT c.*` excludes derived organization_id and assignment metadata.

`ContactService` opens one `REPEATABLE READ` snapshot per GET; transaction ownership is checked (`transDepth`), and it never commits/rolls back a caller-owned write. Returned rows are rechecked via `ContactScope::resolve()` and canonical `canView()`. **Any disagreement fails the whole response**; post-filtering would make authorized total inaccurate. `ContactScope` uses the same captured UTC date as SQL for assignment parity.

Mutations retain prior `visible()`, `assertVisible()`, `decideMutation()`, `decideTransitionAction()`, `FOR UPDATE`, state compare-and-swap, and rollback. They do not call the GET snapshot helper.

### Source gates

```
python backend/tools/verify_routes.py
php backend/tools/test-unit-check.php
php backend/tools/m34b_run.php --offline
php backend/tools/m34d_accept.php --offline
php backend/tests/m86e_contact_sql_pure.php
php backend/tests/m86e_contact_scope_pure.php
php backend/tests/m86e_contact_contract_pure.php
php backend/tools/m86e_accept.php
php backend/tools/m85_scope_audit.php
```

They do not connect to a business DB, send HTTP, execute `EXPLAIN`, or prove actual MariaDB/PBAC parity.

## Required DEV MariaDB/HTTP acceptance — pending

1. Construct known multi-company policies: Org A VIEW allowed, Org B VIEW denied; swap grants; State-dependent ALLOW/DENY/ABSOLUTE_DENY and resource-inactive cases. `data`/`meta.total` must equal independently evaluated canonical per-row PBAC on the same snapshot.
2. Organization-owned Contacts: valid owner, missing owner, unsupported type, whitespace/case normalization, invalid contact_ref_id. Hidden/missing detail must return 404.
3. Employee-owned Contacts: one primary, no primary, secondary-only, inactive, not-yet-effective, expired, two simultaneous primary assignments in one company or different companies, missing Employee or missing primary Organization; include boundary dates (`effective_from/to` inclusive) and UTC day change.
4. Preserve exact Contact DTO (`SELECT c.*`), no synthetic organization_id, and no unauthorized `total` disclosure. Test `page=1,2`, different `page_size`, sort tie-breaker and search/filter equivalence.
5. Real Unicode and NULL behavior, Vietnamese case folding, `%`, `_`, `!` escaping, trailing whitespace, text numeric sorts; compare ID ordering to the existing contractual behavior. Fixture-only tests do **not** prove collation parity.
6. Run `EXPLAIN FORMAT=JSON` on actual COUNT/page/detail with realistic data and inspect indexes, correlated EXISTS scan cost, CPU, network bytes, latency and peak memory. Consider `(contact_type, contact_ref_id, status)` and `(employee_id, is_primary, status, effective_from, effective_to)` only after measuring.
7. Test concurrent employee-assignment changes versus Contact GET; verify snapshot guarantees, no owner leaking and canonical parity failure stops the response.
8. Regression Contact POST/PATCH/set-state under write transactions and negative rollback; verify auth, CSRF and unchanged API/transaction semantics. Independent baseline parity remains pending.

Until this matrix is executed and reviewed, status stays `SOURCE ACCEPTED — PRODUCTION BLOCKED`. No `.env` fallback is available. Rollback is by reverting the code patch, not by setting `disabled`.
