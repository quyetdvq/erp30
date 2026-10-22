# DinkERP backend — staged architecture baseline (BE-ARCH-001)

**Standard:** `DINKERP-BE-ARCH-001` v1.1.1 — `PROPOSED BASELINE` (not an approved UR/FRS/DBD/API contract). This is the first incremental implementation, **not** a certification that 12 modules are compliant.

## Run without Composer/DB

From repository root:

```powershell
php .\backend\tools\architecture-scan.php
php .\backend\tools\architecture-scan.php --json
php .\backend\tools\test-architecture-conformance.php
php .\backend\tools\test-architecture-organization-tree.php
```

`architecture-scan.php` returns **exit 0** only when the monitored source has no **new** forbidden imports, unapproved cross-module imports, new legacy implementation files, detected direct cross-owner fluent SQL writes, unregistered root modules or module cycles. Exit **2** means a new violation. JSON mode provides `rule`, `path`, `message`, and open verification gates.

**An exit 0 is `PASS_NEW_EDGE_GATE_ONLY`, not `COMPLIANT`.** Entries in `observed-v1.2.2.json` are historically observed reference pairs, **not** approved exceptions. They are recorded at exact `source_file|fully_qualified_target` granularity. A new source file referencing the same old class is blocked; removing an existing reference is allowed and reported. No directory-wide waiver exists. Do not automatically update the observed snapshot to silence a new violation.

## Registers (review status)

- `architecture-policy.json`: 12 allowed root modules; provisional 31-table business/technical ownership ledger extracted from P1 migrations; Product methods used by Pricing are **CANDIDATE_OWNER_REVIEW**, not newly approved public API. The 31 tables exclude framework/Shield tables not defined by these application migrations. The ledger needs DB owner confirmation.
- `observed-v1.2.2.json`: 249 observed code references, 75 pre-existing flat Services/Repositories classes and 0 source-level module cycles. Legacy observations remain subject to review. This is a **snapshot**, not a permission list.
- `compatibility-inventory.json`: 30 `class_alias` references plus 2 Product media wrappers; owner and removal criteria are suggested but full caller inventory and owner approval are still missing. These are **not `LEGACY_APPROVED`** merely because they exist.
- `module-design-inventory.md`: central interim module design record / conformance register. The dedicated `DinkERP_Module_Design_Record_Template_v1.1.1.md` is referenced by Standard but not part of the uploaded sources; do not invent a replacement template or mass-create empty `MODULE.md`.

## Detection boundaries

The scanner uses PHP tokenizer-qualified class names and a deliberately limited direct SQL builder signature (`->table('literal')->...->insert/update/delete`). It can flag specific paths but **cannot** prove public **method** authorization, reflection-driven calls, constructor/runtime recursion, dynamic SQL or which DB connection participates in a transaction. These require reviewer/DB/PBAC/concurrency evidence (`ARCH-01/03/05`). Imports in docblocks/strings are ignored. A class-level approved public entrypoint must have a reviewed method list; scanner currently enforces class and consumer but *not* the method called.

No migration, endpoint, permission, PBAC or transaction owner is modified by BE-ARCH-001. Runtime gates on the supplied ZIP remain `BLOCKED_ENV` because `backend/vendor` and a test MariaDB/Shield stack are unavailable.

## Planned activation milestones

1. Architecture Owner approves baseline and resolves old guard warnings. Until then `PROPOSED`, no automatic blanket waivers.
2. Confirm 31-table ledger against approved DBD, add missing Shield/technical ownership and review direct/dynamic mutations.
3. Complete public **class+method** register for consumers, including Product → Pricing and platform read-only seams. Require runtime non-recursive PBAC adapter test before switching wiring.
4. Run the scanner + its 19 mutation fixtures in CI and publish JSON artifact; confirm its false-positive rate before making it required.
5. Run PHPUnit/MariaDB/Shield/concurrency and compare API parity on affected use cases. Do **not** use static PASS to sign off a write/query change.

### 014C / next slices

The first low-risk code movement is `Api010::tree()` → `Modules/Organization/Application/VisibleOrganizationTreeProjector`. **Only** the already authorized result is transformed; no parent lookup, authorization or pagination changes. Follow with Product SQL list → Product Query Service only after preserving exact COUNT/PBAC/snapshot behavior and collecting runtime benchmark evidence. The COMBO + AUTO transaction risk (`KIR-001`) remains **open** and is not part of this patch.

### BE-REORG-014H — API-050 residual/performance checkpoint

`api050-residual-014h.json` is a **source-observed** 22-operation residual inventory derived from the 014E 26-route manifest after 014G. Its 12 source checks and eight negative fixtures are run with:

```powershell
php .\backend\tools\test-be-reorg-014h-residual.php
php .\backend\tools\bench-be-reorg-014h-php.php --self-test
```

For large synthetic datasets and a guarded authenticated **read-only** local HTTP timing run, see `api050-residual-014h.md`. Synthetic PHP latency **is not** server/MariaDB p95. HTTP timing must confirm an isolated backed-up `erp_test` connection separately; the script does not inspect database configuration. 014H changes no application runtime code and does not authorize SQL/PBAC/Numbering transaction refactors.
