# BE-REORG-002: Organization module

## Scope and baseline
Continue the approved modular-monolith roadmap on erp_v1.1.5 plus BE-REORG-001, 001A, FIX01, FIX02 and 001B. The operator baseline reports 674 tests / 6160 assertions and 121 PHPStan diagnostics outside Foundation. This does not assert a local runtime pass.

Move only OrganizationService and OrganizationRepository implementations. Preserve legacy public entrypoints for IAM, HR, Product, Pricing, Numbering, Settings and API-010. No business-rule fixes, schema, migration, dependency, OpenAPI, route, controller, frontend, IAM or generic workflow changes. No PHPStan exclusions, level changes or diagnostic baseline.

## Architecture
- App\Modules\Organization\Application\OrganizationService: existing use cases, audit, hierarchy validation and transactions. Required repository port and database; no legacy class dependency.
- Application\Ports\OrganizationRepositoryInterface: the eight existing repository methods, with explicit array PHPDoc.
- Infrastructure\Persistence\SqlOrganizationRepository: unchanged SQL, payload validation, exception codes and database access.
- Contracts\OrganizationErrors: the thirteen existing service/repository error constants.
- App\Bootstrap\OrganizationFactory: constructs fresh services/repositories without cached context; resolves one default connection per service.
- App\Services\OrganizationService and App\Repositories\OrganizationRepository: explicit final composition wrappers, preserving public signatures, named arguments and constants. No __call or inheritance.

Application remains integrated with the existing BusinessWorkflowWriteBoundary, DomainLockService/MariaDbDomainLockService, MutationContext, Clock, PositiveId and CodeIgniter database interface. These dependencies are explicitly allowed, not an unrestricted Services wildcard. CompanyOwnerResolver and all other IAM adapters stay unchanged. Domain-only rule extraction is not part of this move.

## Invariants
Preserve GROUP uniqueness including inactive roots; the exact parent matrix, error precedence and cycle detection; root-to-leaf ancestor order and nearest COMPANY semantics; date validation and nullable location fields; server-owned audit fields; native/lifecycle fallback and direct state guards. Keep organization-tree lock key, timing, transaction boundaries, exception identity and connection used by each existing caller. An explicitly supplied repository is not rebound to another connection. Ordinary name updates do not gain a tree lock.

Preserve SQL byte-for-byte, repository filter whitelist/order, null semantics, unsupported/required/empty-field errors. Unknown references and persistence errors never turn into success. Existing cross-module authorization/count/pagination behavior stays in its current owners.

## Verification
1. Capture public contracts from the checkpoint BEFORE edits; retain fixture for future regressions.
2. Run unchanged existing suites plus added contract/boundary, characterization, HTTP and lock tests. Characterization has fixed expected values, not only old-wrapper versus new-wrapper equality.
3. Compare canonical business method tokens and SQL to the old implementations, excluding namespace/constant/wiring-only changes.
4. Reuse guarded Foundation runner to execute full PHPUnit once and PHPStan, with an additional Organization JUnit gate (exact cases, no skip/error/failure). Never accept an old XML or empty/filtered suite.
5. Verify patch apply/reverse on the exact checkpoint and compare every file hash.

## Operational safety and limitations
Use only confirmed disposable loopback MySQLi erp_test with existing guard/child-bootstrap target matching. No database commands run in the assistant workspace. The new entrypoint enables an Organization case manifest in the existing runner; its safety/bootstrap/process/lock path and normal Foundation invocation remain intact; no extra migration/reset script. Runtime tests may refresh test tables; no concurrent test processes on the same DB. PHPStan may still exit 1 for the existing 121 diagnostics. Local checks without vendor/DB are explicitly not runtime PHPUnit, PHPStan, MySQL or browser UAT.

## Delivery
One incremental patch after 001B, one command `php tools/test-organization.php --confirm-test-db`, one `test-result.log`. No reapply of older patches, baseline rebuild or manual file overwrites. Reverse only this patch on an otherwise unchanged candidate.

## Implemented test inventory
37 additional cases: 4 boundary, 9 compatibility, 16 database characterization, 5 in-process HTTP, 3 real domain-lock cases. The runtime target is 711 cases if the 674-case checkpoint is otherwise unchanged. Foundation's 9 HTTP and 4 row-lock gate remains required. Organization lock tests cover named-lock contention/release, not arbitrary race freedom or browser/Apache UAT.
