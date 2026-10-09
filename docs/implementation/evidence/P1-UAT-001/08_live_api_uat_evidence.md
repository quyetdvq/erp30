# P1-UAT-001 - Live API UAT Evidence

## Authorized run

- Date: 2026-10-09
- Environment: current UAT hosts `http://erp.monmin.net` and `https://api.monmin.net`
- Authorization: user explicitly authorized UAT testing and supplied `quyetdv`; password and session material are not recorded.
- Observed identity: Shield user `quyetdv`, numeric id `1`, `ACTIVE`, group `system_admin`.
- Transport note: `erp.monmin.net`, `erp2.monmin.net`, and `api.monmin.net` serve the same certificate. Its SAN includes all three hostnames, and validity is 2026-10-09 through 2029-01-09. The issuer is the machine-local `mkcert development CA`, so the in-app Chromium browser reports `ERR_CERT_AUTHORITY_INVALID`; this is a trust-chain problem, not a hostname or expiry problem. Edge can work when its Windows profile trusts that local CA. No browser TLS bypass was used.
- Data safety: read-only endpoint checks, negative checks, logout, and a same-value profile PATCH only. No business fixture create/update/delete/import was performed.

## Live endpoint results

| Check | Actual result | Evidence boundary |
|---|---|---|
| CSRF bootstrap | PASS; `GET /api/v1/auth/csrf` returned `200` and a token header | Token value intentionally omitted |
| Login | PASS; `POST /api/v1/auth/login` returned `200` for `quyetdv` | Password omitted; session cookie not retained in evidence |
| Auth/me | PASS; `GET /api/v1/auth/me` returned `200` with active user and Base Permission snapshot | Identity fields sanitized to role/active state |
| Organization tree | PASS; `GET /api/v1/organizations/tree` returned `200`, 6 rows | Read-only tree visibility only; hierarchy mutation not exercised |
| Security users | PASS; `GET /api/v1/security/users?page=1&page_size=2` returned `200`, total 2 | No user names/emails recorded |
| Resources/actions | PASS; `GET /api/v1/resources?include=actions&page=1&page_size=2` returned `200`, total 21, 2 rows | Confirms approved nested action representation for visible rows |
| Products | PASS; `GET /api/v1/products?page=1&page_size=2` returned `200`, total 1,452, 2 rows | Read-only list; no Product mutation |
| Product Groups | PASS; `GET /api/v1/product-groups?page=1&page_size=2` returned `200`, total 17, 2 rows | Read-only list |
| Product UOMs | PASS; `GET /api/v1/product-uoms?page=1&page_size=2` returned `200`, total 13, 2 rows | Read-only list |
| Price Lists | PASS; `GET /api/v1/price-lists?page=1&page_size=2` returned `200`, total 1, 1 row | Read-only list; no pricing mutation |
| Display runtime | PASS; `GET /api/v1/display-settings/runtime` returned `200` | Presentation snapshot only |
| Import jobs | PASS; `GET /api/v1/import-jobs?page=1&page_size=2` returned `200`, total 3, 2 rows | Existing jobs only; no file upload or import start |
| Authorization logs | PASS; `GET /api/v1/authorization-logs?page=1&page_size=2` returned `200`, total 1, 1 row | No sensitive log payload recorded |
| API locale negotiation | PASS; `GET /api/v1/auth/me` with `Accept-Language: en` returned `200` and `Content-Language: en` | Business values unchanged; UI not exercised |
| Unsupported locale fallback | PASS; `GET /api/v1/auth/me` with `Accept-Language: fr` returned `200` and `Content-Language: vi` | Stable response semantics observed |
| Invalid CSRF negative | PASS; same-value profile PATCH with deliberately invalid token returned `403 FORBIDDEN` | No business mutation occurred |
| Same-value profile mutation | PASS; profile PATCH with current username/email and fresh token returned `200` | Safe no-op-equivalent mutation; no password change |
| Invisible Product target | PASS; `GET /api/v1/products/999999999999999999` returned `404 PRODUCT_NOT_FOUND` | Information-boundary check |
| Invisible Organization target | PASS; `GET /api/v1/organizations/999999999999999999` returned `404 RESOURCE_NOT_FOUND` | Information-boundary check |
| Logout/session lifecycle | PASS; logout returned `200`; subsequent `/auth/me` returned `401 UNAUTHENTICATED` | Session was ended deliberately after checks |

## UI limitation

The live SPA at `http://erp.monmin.net/login` accepted the page but its login
request displayed the generic failure state because the browser cannot call the
HTTPS API while the local mkcert CA is untrusted in that browser profile. Direct
API login succeeded with the same supplied identity. `https://erp2.monmin.net`
was not used because the browser reported `ERR_CERT_AUTHORITY_INVALID`; bypassing
that safety barrier was not authorized. Therefore this initial run did not
claim browser forms, tables, mutation feedback, screenshots/traces, or
language-switching UI.

## Follow-up environment recheck

After the UAT environment was updated, the same in-app browser successfully
loaded the authenticated SPA, navigated Products/Organization/System screens,
switched `vi`/`en`, and completed logout/re-login. Those observations supersede
the transport limitation for the current session and are recorded in
`09_live_browser_uat_recheck.md`. They do not retroactively add business
mutation, role-negative, fixture-rollback, or reviewer-sign-off evidence.

