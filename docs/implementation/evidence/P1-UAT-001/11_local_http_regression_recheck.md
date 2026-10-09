# P1-UAT-001 - Local HTTP Regression Recheck

## Scope

- Date: 2026-10-09
- Repository HEAD: `3889e7c8aa8307e3154536057957211fd337ba5e`
- Purpose: isolate browser/transport regressions on localhost before relying on
  the UAT HTTPS host.
- This is engineering support evidence, not business sign-off and not release
  authorization.

## Runtime setup

The backend was started temporarily on `http://127.0.0.1:8080` with an ignored
local `.env` override for HTTP base URL, disabled secure redirect/CSP upgrade,
localhost CORS, and non-secure local cookies. The frontend was served over
`http://127.0.0.1:5173` with `VITE_API_BASE_URL=http://127.0.0.1:8080`.

After the check, the backend `.env` was restored to its original HTTPS UAT
values. No local runtime setting was committed and no UAT/production host was
downgraded.

## Observed results

| Check | Result |
|---|---|
| `GET http://127.0.0.1:8080/api/v1/health` | PASS; HTTP 200 JSON health response |
| Authorized login using the supplied UAT test identity | PASS; browser navigated from `/login` to `/` |
| Dashboard identity/session rendering | PASS; workspace displayed the authenticated username |
| Chromium support profile after CSRF fixture repair | PASS; 42 passed, 0 failed |

## Boundary

The local login proves the HTTP development topology and session bootstrap can
work without TLS bypass. It does not prove UAT certificate trust, production
CORS, role coverage, mutation rollback, business acceptance, reviewer approval,
or SYS-007 closure.
