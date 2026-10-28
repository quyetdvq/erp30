# DinkERP Backend v2 — M3.4A.1 OpenAPI Reference Integrity Hotfix

**Scope:** Repair the M3.4A harness reference verification step. No business controller, CSRF/filter, PBAC, mutation route, Frontend, baseline application, database schema, or application data is changed.

## Observed issue on XAMPP (2026-10-23)

`php backend\tests\m34_contract_pure.php` and `php backend\tools\m34_http_parity.php inventory/preflight/negative` stopped with:

```
Pinned baseline OpenAPI SHA-256 does not match v1.2.4 manifest
```

The M3.4A harness compared the installed frozen OpenAPI YAML against the digest declared inside the installed operation manifest; on mismatch it reported one ambiguous message, without identifying the file or both digests. The user-provided log does **not** contain the files' actual hashes, so it is not possible to prove from that log whether the manifest, YAML, line endings, or a wrong-file copy caused the local mismatch. The original files have been independently checked against `erp_v1.2.4.zip`.

## Immutable v1.2.4 reference hashes

| File | Expected SHA-256 (exact file bytes) |
| --- | --- |
| `docs/backend-rewrite/openapi-frozen-v1.2.4.yaml` | `f1736a006c2f4e1f529b22b8434be6995bcfbec8e7252d0af8ddc9d739de1728` |
| `docs/backend-rewrite/openapi-operations-v1.2.4.json` | `5afe430a5189706b573cb8c1edd15fcbcf891ace0bb3fe9501a49154817a16e9` |

The YAML digest was verified byte-for-byte against `erp30-main/contracts/openapi/openapi.yaml` in the original `erp_v1.2.4.zip`. The manifest contains 150 operations and explicitly declares the same source YAML digest.

## Patch details

1. Reinstall exactly the original frozen YAML and matching manifest under `docs/backend-rewrite/` (backup any locally edited copies before applying the overlay). File bytes are untouched from the original pinned reference.
2. `M34Contract::operations()` checks the manifest and YAML **against independent, hard-coded hashes**, not just against each other, and verifies the manifest `source`, `openapi_sha256`, and operations list.
3. If a reference document is **absent**, the harness uses a separately bundled SHA-256-pinned fixture and prints the source to STDERR. If a document **exists but differs**, it fails closed with the offending path, expected digest, and actual digest. The fallback never hides an altered installed file. CRLF changes also fail strict byte-for-byte verification (restore original LF bytes).
4. Adds `backend/tests/m34_reference_pure.php` to exercise good inputs, tampered files, line-ending drift, missing document fallbacks, and corrupted snapshots. The verification is entirely read-only and offline.
5. Ensures a clean full-repository archive contains the empty physical `backend/public/upload/` folder expected by the existing M1 path contract. No uploaded media is changed or overwritten.

## Apply to the user's existing M3.4A installation

Back up any custom edits in the two reference documents, then extract `DinkERP_Backend_v2_M3.4A.1_OpenAPIReference_Hotfix_Overlay.zip` into `D:\XAMPP\htdocs\erp\`, allowing it to update matching files. Do **not** replace `.env`, `frontend`, or `backend_basline`; none of them is contained in the patch.

In PowerShell:

```powershell
cd D:\XAMPP\htdocs\erp
Get-FileHash .\docs\backend-rewrite\openapi-frozen-v1.2.4.yaml -Algorithm SHA256
Get-FileHash .\docs\backend-rewrite\openapi-operations-v1.2.4.json -Algorithm SHA256
php backend\tests\m34_reference_pure.php
php backend\tests\m34_contract_pure.php
php backend\tests\m34_safety_pure.php
php backend\tests\m34_transport_pure.php
php backend\tools\m34_http_parity.php inventory
```

**Expected:** the first two hashes match the values above; the reference tests and M3.4A regression tests pass; `inventory` returns `"count": 29`. `inventory` does not touch MariaDB or issue HTTP requests.

`preflight` and `negative` still require two **actual independent disposable clone databases**, two loopback HTTP servers and a correct local config (`backend\writable\m34\clones.local.json`), as described in `M3_4A_MUTATION_HTTP_CLONE_PARITY_QA.md`. A nonexistent config is a **separate, expected blocker**, not a reference-hash defect. Do not configure clone tests against `api.monmin.net` or production MariaDB.

## Offline QA evidence (this patch)

- Reference-integrity checks: 16/16 assertions PASS (correct, corrupted, CRLF, fallback, missing snapshot).
- M3.4A contract/safety/transport: 15 + 25 + 10 assertions PASS.
- Regression over all PHP pure/fixture suites in Backend v2: 35/35 suites PASS, with the required empty physical upload folder created for the clean extracted repository.
- `backend/app` syntax: 184/184 PHP files PASS.
- Verified baseline Frontend and `backend_basline` file contents unchanged. No actual HTTP/DB clone mutation parity has been performed by this hotfix.

**Release status:** M3.4A.1 reference repair offline PASS; real clone HTTP parity remains PENDING. Keep `DINKERP_M33B_WRITE_MODE=off` in production.
