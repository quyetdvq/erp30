# LIVE E2E-LOOKUP-BATCH Security Regression — staging only

This suite runs against a **running frontend and real CodeIgniter backend**.
It includes an authenticated read-only Vue-to-API smoke on both `/products` and `/products/price-lists`.
Unlike the ordinary E2E files, it NEVER mocks `/api/v1` and cannot PASS solely
because a mocked response was pre-filtered. All application requests are
read-only: GET plus the explicitly read-only POST resolve/preview APIs. It does
not migrate or seed any database, create users, mutate policies, or run on
production. It does **not** store browser traces, screenshots or videos.

## Required staging fixtures (provision separately)

Prepare three test accounts with isolated sessions:

- **ADMIN:** explicitly authorized to view all listed test records (no code-level
  SYSTEM_ADMIN bypass). This proves denied IDs really exist.
- **RESTRICTED:** can view the `ALLOWED_PRODUCT_ID` (ACTIVE), but must not view
  `DENIED_PRODUCT_ID` (INACTIVE) under an actual State-aware PBAC rule;
  can view the ACTIVE parent `PARENT_PRODUCT_ID` whose children include the denied
  Product; view one test UOM but not another; view one test Organization and
  Price List but not another due to real Organization Scope/PBAC.
- **PRICE_ONLY:** can view `ALLOWED_PRICE_LIST_ID` but has **no** `product.view`
  Base Permission. This proves `price_list.view` is not a Product disclosure grant.

All IDs must be existing positive BIGINTs and distinct **within each resource type** (different tables may have the same numeric ID).
The denied Product must be a child of the visible PARENT. The parent's relations
should include at least one viewable and one denied child. Restrict the user with
persisted assignments/Policies as in normal application operation; do **not**
assume an org/client-supplied context can grant a user access.

## Run (PowerShell in frontend directory)

Use private environment variables or a locally ignored script, never a tracked
`.env`, test file or screenshot. Example placeholders below **must be replaced**:

```powershell
$env:DINKERP_E2E_STAGING_CONFIRMED = 'YES'
$env:DINKERP_E2E_UI_ORIGIN = 'http://localhost:5173'
$env:DINKERP_E2E_API_ORIGIN = 'http://localhost:8080'

$env:DINKERP_E2E_ADMIN_EMAIL = '<admin-test-login>'
$env:DINKERP_E2E_ADMIN_PASSWORD = '<secret>'
$env:DINKERP_E2E_RESTRICTED_EMAIL = '<restricted-test-login>'
$env:DINKERP_E2E_RESTRICTED_PASSWORD = '<secret>'
$env:DINKERP_E2E_PRICE_ONLY_EMAIL = '<price-only-test-login>'
$env:DINKERP_E2E_PRICE_ONLY_PASSWORD = '<secret>'

$env:DINKERP_E2E_ALLOWED_PRODUCT_ID = '<active-viewable-id>'
$env:DINKERP_E2E_DENIED_PRODUCT_ID = '<inactive-denied-id>'
$env:DINKERP_E2E_PARENT_PRODUCT_ID = '<visible-parent-id>'
$env:DINKERP_E2E_ALLOWED_UOM_ID = '<viewable-uom-id>'
$env:DINKERP_E2E_DENIED_UOM_ID = '<denied-uom-id>'
$env:DINKERP_E2E_ALLOWED_ORG_ID = '<viewable-org-id>'
$env:DINKERP_E2E_DENIED_ORG_ID = '<out-of-scope-org-id>'
$env:DINKERP_E2E_ALLOWED_PRICE_LIST_ID = '<viewable-price-list-id>'
$env:DINKERP_E2E_DENIED_PRICE_LIST_ID = '<out-of-scope-price-list-id>'

pnpm exec playwright test --config=playwright.live.config.ts --reporter=line
```

The **Vite app must be built/configured with** `VITE_API_BASE_URL` pointing to
`DINKERP_E2E_API_ORIGIN`. The live suite aborts browser `/api/v1` requests to
any other origin to avoid accidentally forwarding credentials to production.
It additionally refuses `monmin.net` and all `*.monmin.net` hosts.
Login through the real `/login` Vue form; the suite uses the resulting cookie
jar and CSRF token for authorized read-only POSTs. Never attach authentication
headers or response bodies to CI artifacts. Run Chromium against a staging
DB/host whose intended test fixture is already provisioned.

**Failure interpretation:** An absent credential/fixture, a mismatched backend
origin, or denied record not visible to ADMIN is a **test setup failure**,
not evidence that PBAC works. A leak to a RESTRICTED/PRICE_ONLY account is a
**security regression**. Do not suppress failed tests or remove negative
assertions to make the run green.

When finished, remove the secret environment variables from the PowerShell
session; do not upload full Playwright traces or request bodies.
