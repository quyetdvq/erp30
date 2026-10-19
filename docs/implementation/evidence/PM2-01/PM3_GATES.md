# PM-3 entry plan and acceptance gates (NO implementation in this package)

**Start condition fulfilled:** PM-2/01 source/test manifest MATCH, Pilot 64/64 PASS, Regression 1,112/1,112 PASS. **Start authorization is for testing/review only**, not production deployment.

| Gate | Work package and concrete evidence expected | Current position |
| --- | --- | --- |
| PM3-G0 — Freeze source + reproducibility | Retain raw regression log/XML/discovery/target attestations under token `878bc456...`; record user's `git rev-parse HEAD`, `git status --short`, and reviewed source manifest; do not alter historical manifests | **PARTIAL**: user `result.json`, terminal PHPUnit summary, previous Pilot console available; raw regression XML/logs not attached |
| PM3-G1 — Transport multipart | Wire-level HTTP POST `multipart/form-data` with field **`image`**: valid PNG/JPEG/WebP, malformed body, forged metadata fields, missing field, PHP upload error, >3 MiB, 5000px dimension; verify status/JSON and no partial writes | **OPEN** |
| PM3-G2 — CSRF in secure browser/session | Authenticated browser with valid CSRF success; missing/invalid token rejected without mutation; cookies, CSRF regeneration and unauthorized access; compare real network requests/response headers | **OPEN** |
| PM3-G3 — Binary/private storage | Authenticated GET returns exact bytes, correct MIME/private cache headers; denied GET leaks neither binary nor private path; physical filenames stay private under `WRITEPATH/uploads/product-images`; path traversal is rejected | **PARTIAL** characterization covers CI4 simulated route and storage, wire proof missing |
| PM3-G4 — PBAC/state + mutation failure | No base permission, DENY, ABSOLUTE_DENY, persisted state mismatch, and post-upload edit allowed/view denied; test DB write false/throws, cleanup new file while keeping old; remove metadata before old-file cleanup; verify outer transaction rollback caveat | **PARTIAL** PHPUnit behavior tests exist; negative wire/integration matrix and independent review needed |
| PM3-G5 — Actor/time and shared dependency | Confirm injected DB/actor/operation clock follow old contract, `updated_at` remains process-local `Y-m-d H:i:s`, `updated_by` captures real Shield user; review direct `date()` and `auth()->user()` in metadata repo and decide no-drift collaborator extraction or documented accepted exception for pattern criterion | **OPEN architecture decision**; do NOT change timestamp semantics within an unreviewed structural patch |
| PM3-G6 — API/schema/compatibility diff | Capture exact route + OpenAPI and migration/schema before/after; verify GET/POST/DELETE image, 26 Api050 operations, named constructor arguments, injected catalog/storage consumer inventory | **PARTIAL** internal tests; independent before/after artifact pending |
| PM3-G7 — Static analysis + independent review | Run PHPStan and compare each diagnostic against PM-2 baseline; attach code and security review sign-off. No blanket suppressions; 115 Pilot errors are not a clean result | **OPEN** |
| PM3-G8 — Full regression, deploy/rollback rehearsal | Guarded `erp_test` only, Pilot + 1,112 inherited cases plus new PM-3 cases, JUnit exact identity, 26/26 reviewed hashes or new approved manifest, DB target attestation, rollback instructions, staged deployment plan (no production action) | **OPEN** |

## Recommended implementation sequence

Begin with **PM-3/01: evidence completeness + HTTP multipart/CSRF tests**, using the same disposable `erp_test` guard. This can add targeted tests without changing Product Media source. In parallel, request an architecture disposition on metadata actor/time: keep old semantics exactly or approve a separate behavior change with explicit acceptance. Proceed to PM-3/02 independent review, static analysis comparison, deployment checkpoint and release decision only after PM-3/01 evidence is available.

Do **not** silently promote the pilot to the canonical pattern for other modules, remove legacy facades or start broad Api030/Api050 refactor at PM-3 entry. All unfinished gates remain explicit even if PHPUnit stays 100% PASS.
