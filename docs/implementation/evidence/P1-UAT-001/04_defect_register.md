# P1-UAT-001 - Defect Register

## Classification

Severity uses `S1` release blocker, `S2` major business workflow, `S3`
non-blocking, and `ENV` execution control blocker. No application defect was
fixed in this task; the ten automated failures were test-fixture defects and
were repaired by adding the existing CSRF bootstrap mock. The earlier browser
transport observation is marked resolved only because the UAT environment was
updated externally. No production source was changed.

| ID | Severity | Type | Reproduction | Expected | Actual | Affected requirement | Evidence | Disposition |
|---|---|---|---|---|---|---|---|---|
| UAT-ENV-001 | S1 | ENV | Open the live SPA and submit the supplied login in the in-app Chromium browser | Approved UAT SPA/API transport permits authenticated browser use | Earlier run rejected the machine-local `mkcert` CA with `ERR_CERT_AUTHORITY_INVALID`; after the UAT environment update, the same browser loaded `/products`, authenticated `quyetdv`, and completed logout/re-login | Contract transport/security; UI 4A | `08_live_api_uat_evidence.md`; `09_live_browser_uat_recheck.md` | RESOLVED on recheck; retain certificate-chain monitoring and verify deployment certificate independently |
| UAT-ENV-002 | S2 | GOVERNANCE | Execute business role scenarios beyond `quyetdv` | Named role identities and business reviewers exist for each acceptance lane | Only `quyetdv`/`system_admin` was supplied; no business reviewer/sign-off assignments exist | FRS IAM/PBAC; Plan section 17 | `02_test_data_and_role_setup_guide.md` | BLOCKED; Security/Release must assign reviewers and identities |
| UAT-ENV-003 | S1 | ENV | Attempt to create fixture data and test failed-row rollback | Isolated, reversible synthetic fixtures and cleanup window exist | No approved fixture/reset/rollback plan exists; no live writes attempted | FRS organization/product/import/numbering | `02_test_data_and_role_setup_guide.md` | BLOCKED; Operations/Product must approve data plan |
| UAT-ENV-004 | S1 | GOVERNANCE | Reconcile SYS-007 evidence before authorizing UAT | One approved, internally consistent production-readiness closure exists | `03_approved_parameter_recheck.md` says DONE, but `02_production_readiness_decision_closure.md` retains blocked/open release rows | Plan v1.10 section 17; SYS-007 closure | `03_automated_execution_evidence.md` and SYS-007 evidence directory | BLOCKED; Release/Security/Operations must resolve the record |
| UAT-ENV-005 | S1 | DEPLOYMENT | Request `GET /` on the deployed UAT/API host | Framework bootstrap can instantiate the configured cache handler and reach the route | User-supplied log reported `FileHandler` unable to write `/home/u493345855/domains/monmin.net/public_html/api/writable/cache/`; after the UAT update the authenticated SPA loaded and navigated successfully, but host-level directory permissions were not independently inspected | Deployment cache/bootstrap; Contract transport | User-supplied pasted log; `09_live_browser_uat_recheck.md` | NEEDS_VERIFY; Operations must confirm directory existence and PHP-FPM write/delete access |
| AUTO-UAT-001..010 | S1/S2 | AUTOMATED | Run the affected Chromium cases with the repository mock API | Each mutation/login flow reaches its mocked endpoint and preserves CSRF behavior | Before repair, all ten stopped at CSRF bootstrap because fixtures did not mock `/api/v1/auth/csrf`; after adding the route, the full Chromium profile passed 42/42 | Same requirements listed above | `03_automated_execution_evidence.md` | RESOLVED as test-fixture defect; no application defect or security bypass |

The ten `AUTO-UAT` entries are closed as a single test-fixture repair. They do
not constitute live business acceptance and do not change the outstanding UAT
reviewer, fixture-governance, SYS-007, or deployment blockers.

