# BE-REORG-005 — Settings / Display Settings

Physical relocation of the existing Display Settings core into `App\Modules\Settings\Display`.

- No schema/migration/routes/OpenAPI/frontend/IAM/Auto Numbering/business-timezone behavior changes.
- Legacy Settings entrypoints remain explicit composition wrappers.
- Runtime gate: 39 new BE-REORG-005 cases plus the existing 29 Settings cases.
- Acceptance: full PHPUnit passes; PHPStan diagnostics exactly match the inherited 121-diagnostic checkpoint.
