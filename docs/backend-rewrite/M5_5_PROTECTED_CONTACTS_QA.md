# M5.5 — Protected Contacts CRUD (Backend Rewrite)

**State:** SOURCE/OFFLINE ACCEPTED (pending XAMPP DEV HTTP/MariaDB/record PBAC). Applies after M5.4.1 module consolidation.

## Contract and owner

- Source authority: frozen `openapi-frozen-v1.2.4.yaml`, `openapi-operations-v1.2.4.json`, baseline `Contact/Application/ContactService`, `ContactAuthorizationRegistry`, and `EmployeeContactScopeResolver`.
- Architecture owner: **one `Modules/Contact/` root**, per System Architecture Standard v1.1.1 §3 and the M5.4.1 ownership decision. No `ContactsManagement` root or write methods in `People`/`Organization`.
- Five exact operations previously absent from runtime route set:

| Method | Path | Operation ID |
| --- | --- | --- |
| GET | `/api/v1/contacts` | `listContacts` |
| POST | `/api/v1/contacts` | `createContact` |
| GET | `/api/v1/contacts/{id}` | `getContact` |
| PATCH | `/api/v1/contacts/{id}` | `updateContact` |
| POST | `/api/v1/contacts/{id}/actions/set-state` | `setContactState` |

- Source inventory **105 + 5 = 110/150**. The prior footprints 84/90/93/99/105 are still asserted independently. No schema migration, Frontend, `.env`, Apache, baseline, or contract file change.

## Authorization and lifecycle rules

- Owner is immutable; `contact_type` can only be `ORGANIZATION` or `EMPLOYEE`. `contact_ref_id` is the persisted ID of that owner. An arbitrary `contact`, user-provided `organization_id` or caller-provided authorization resource is **never** permitted.
- Owner type selects `organization_contact` or `employee_contact`. For Organization owner, PBAC organization scope comes from the actual Organization. For Employee owner, the People read port must find that Employee and **exactly one canonical ACTIVE effective primary assignment on the UTC business date**. Ambiguous or missing primary scope fails closed.
- Both resources must exist in the persisted Resource/Action catalog, have correct ORGANIZATION domain, and bind a configured Lifecycle. CREATE uses the authoritative server initial State. No default all-States/ACTIVE or global bypass when a Lifecycle is absent.
- GET list checks VIEW per persisted row before filtering/pagination and before computing totals; hidden/invalid owner rows do not leak. GET detail/response after mutation must pass VIEW or the mutation rolls back.
- PATCH rejects status and ownership changes, including no-op values. POST set-state checks a unique active `setstate` transition, PBAC `setstate`, and the resolved transition action. The update uses a status predicate and row lock within a transaction.
- SQL address references must be valid and Ward must match Province when both selected. Server controls audit fields. Database failures are mapped to safe localized API errors rather than exposing SQL diagnostics.
- Baseline Contact dictionaries en/vi remain **byte-for-byte unchanged**. Two new bilingual runtime diagnostics live in `ApiRuntime.php`; `error.code` stays language-independent.

## Local source checks

Run from `backend/`:

```powershell
.\tools\test-unit.ps1 CHECK
.\tools\test-unit.ps1 RUN
.\tools\test-unit.ps1 RUN ACCEPTANCE
```

Expected: **79/79** offline suites, **6/6** M3.4D source gates, M5.5 `105 existing + 5 new = 110/150`, plus the existing M3/M4/M5.1–5.4 acceptance gates. No DB/HTTP operations are performed by these three commands.

## DEV HTTP/PBAC checklist (operator environment, no clone required)

Use the existing `https://api.monmin.net` rewrite VirtualHost and its DEV MariaDB configuration; never send test mutations to production. Keep session authentication and CSRF, and use the browser/API client with a permitted developer actor. Check EN and VI by changing `Accept-Language`.

1. `GET /api/v1/contacts` and `GET /api/v1/contacts/{existing_id}`: confirm JSON `200`, paged total only of VIEW-visible contacts, and an actor without Contact owner permission cannot read a hidden contact (404 for detail).
2. `POST /api/v1/contacts` with an existing Organization owner, e.g. `{"contact_type":"ORGANIZATION","contact_ref_id":"<valid_org_id>","name":"DEV QA contact"}`. Expect 201 only if actor has corresponding `organization_contact.create` **and** VIEW plus authorized scope. Capture new ID.
3. `PATCH /api/v1/contacts/{new_id}` change name, then attempt `contact_ref_id`, `contact_type`, and `status` patches. First should return 200 for a permitted actor; the others must reject and not change SQL.
4. `POST /api/v1/contacts/{new_id}/actions/set-state` with `{"status":"<approved_destination>"}` after verifying an actual configured transition. A forbidden/ambiguous transition must not change the row.
5. Repeat creation on an Employee with one current primary assignment; try an employee without primary or with ambiguous primaries, confirm a rejection and zero inserted contacts.
6. Check unauthorized, missing CSRF, read-only actor, and owner belonging to another Organization. Compare response code/message and Content-Language for `en`/`vi`.
7. Verify `contacts.created_by`, `created_at`, `updated_by`, `updated_at`, and that both successes and rejected writes have the expected persistence behavior. Use only disposable DEV records.

## Remaining proof and debt

- **Not run here:** authenticated Apache HTTP, MariaDB rollback, current actor PBAC permissions, baseline successful-write parity, concurrency with assignment transfers.
- Listing currently loads Contacts before per-row PBAC filtering (same safety ordering as existing M2.1 compatibility read); optimize via owner-aware SQL buckets if the dataset becomes large, without changing the semantics.
- Assignment scope is read from a consistent operation-time query, but concurrent People assignment mutation vs Contact mutation has not been stress-tested.
- Previous M5.1–M5.4 DEV mutation acceptance also remains separate and is not silently inherited from these source checks.
