# DinkERP P1 Project Invariants

Consult the current project docs for complete wording. These are compact guardrails, not replacements for authority sources.

## Authority and change control

- Never silently change a baselined UR/FRS/DBD/Implementation Contract semantic.
- Never add persistence outside the current Database Design without approved additive design/change control.
- Never invent a Resource Type, Action code, endpoint contract, enum, or business rule to make coding easier.
- Migrations are forward-only; do not rewrite an applied migration.
- API `CONTRACT_PENDING` is a stop/out-of-scope condition, not permission to fill gaps.

## Approved module ownership

- Product root: `App\Modules\Product`; owns Product Group, UOM, Product master/roles, COMBO structure/flattening, numbering integration, and Product-specific Import orchestration.
- Pricing root: `App\Modules\Pricing`; owns Price List, Pricing resolution, and monetary COMBO allocation.
- Dependency direction: Pricing may depend on Product; Product must not depend on Pricing.
- Do not introduce deeper Application/Domain/Infrastructure layering inside Product/Pricing unless an approved architecture decision reopens it.
- Shared import mechanics/persistence live in `App\Infrastructure\Import`; Product-specific import behavior lives in `App\Modules\Product\Import`.
- Product Import may depend on shared Import infrastructure; shared Import infrastructure must not depend on Product or any business module.
- Do not create `App\Modules\Import` before a second approved import target establishes real shared business ownership.

## Authorization / PBAC

- System Admin has no bypass and no `isSystemAdmin => ALLOW` branch.
- Base Permission is mandatory before PBAC evaluation.
- Per-lane precedence is `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`, with `NO_MATCH => ALLOW` after Base Permission exists.
- Resolve inheritance before denied-set intersection.
- Applicable `ABSOLUTE_DENY` must survive every intersection.
- Resource means securable type/capability, not one Resource row per business record.
- No Resource Instance Authorization in P1.
- Authorization Group `GROUP` participant is not Organization type `GROUP`.

## Resource Domain / Evaluation Organization

- Resolve persisted Resource metadata/domain before authorization context. Canonical domains are only `SYSTEM`, `MASTER`, `ORGANIZATION`; `COMPANY` is not a Resource Domain.
- `resources.domain` is required security metadata; canonical existing Resource domain is immutable in normal P1 runtime.
- `SYSTEM` and `MASTER` always evaluate at Organization `GROUP`.
- `ORGANIZATION` must resolve through the explicit registered resolver for that Resource using persisted target/owner or canonical create owner/parent/scope. Missing resolver/context fails closed.
- Primary Assignment may supply Default Actor Organization Context only for a use-case explicitly allowing default business context; it never completes or overrides Resource authorization scope.
- `organization.create`: evaluate at Parent Organization.
- Employee Assignment create: request `organization_id`; existing target: persisted Assignment `organization_id`; list: each persisted row context.
- Client context/filter is a hint/selector only; it never widens or overrides authoritative context.
- Company summary authorization never substitutes for concrete Organization authorization.

## Employee assignments

- Eligible assignment is effective and located at the Evaluation Organization or an ancestor on its exact scope path.
- GROUP assignment can flow to descendants.
- Sibling/other-Company assignment cannot bleed into the current path.
- Primary Assignment is not the only authorization lane.


## Employee Structure / P1-API-020

- Department, Position, and Employee are Group-shared `MASTER` Resources; their protected API operations evaluate at `GROUP`.
- Department owns its own hierarchy only: no Organization ownership; reject self-parent/direct-or-indirect cycle.
- Position has no Department/Organization ownership. Department/Position inactive records are not selectable for new Assignment references but historical references remain.
- Employee Organization/Department/Position relationships exist only through Employee Assignment. Assignment-derived filters/projections never change Employee authorization scope.
- Employee Assignment is `ORGANIZATION` scoped by `organization_id`; authorization/data-scope filtering occurs before list totals and pagination.
- Primary Assignment overlap remains transaction/concurrency protected. Secondary Assignments remain valid authorization lanes when eligible.
- Employee `gender` is the DEC-P1-001 nullable canonical catalog `MALE`, `FEMALE`, `OTHER`; `NULL` means not specified. Exact export media type remains source-open/`CONTRACT_PENDING`.

## Database / transactions / boundaries

- Repository handles persistence; services own business invariants and authorization placement.
- Controllers/adapters parse/invoke/map; they do not query repositories directly or reimplement business rules.
- Multi-table invariant mutations are atomic.
- Use the approved DomainLock/advisory-lock convention for race-sensitive domain changes.
- Advisory acquire/release must use the same DB connection/session and finite timeout.
- Retry at most once and only for retry-safe/idempotent use cases.
- BIGINT IDs remain technical primary keys; business identifiers are separate.

## Auto Numbering

- Configuration/counters live in `auto_numbering_series` and `auto_numbering_counters` linked to Resource Type.
- Generated business identifier never replaces `BIGINT id`.
- Scope: GROUP / COMPANY / ORGANIZATION.
- Mode: AUTO_REQUIRED / AUTO_IF_EMPTY.
- Reset: NEVER / YEARLY / MONTHLY / DAILY.
- Generation and business create share one DB transaction.
- Rolled-back allocation may be reused; committed business number must not be reused.
- AUTO_IF_EMPTY manual identifier does not consume the counter.
- Skip occupied generated candidates under the same serialization.
- Issued series cannot hard-delete; immutable after issuance: resource, series code, scope type, reset policy.
- Product identifier maps transport `product_code` to database `products.code`, max 50, GLOBAL uniqueness namespace.

## Product Group hierarchy

- Product Group is a GROUP-level shared master using nullable `parent_id` adjacency list.
- Depth is arbitrary; do not impose a hard maximum.
- Reject self-parent and direct/indirect cycles; valid reparenting is allowed.
- Product Group code is globally unique in Group.
- No persisted descendant status cascade.
- A new Product may target a group at any level only when target and all ancestors are ACTIVE.
- Historical Product references remain readable when a group/path becomes inactive.
- Do not auto-null/reassign/move Product references and do not hard-delete Product Group in P1.
- Hierarchy mutation uses DomainLock key `product-group-tree`, same connection, finite timeout, lock before cycle validation, and release in `finally`.

## Product / pricing / COMBO

- One Product entity; no Variant entity/table in P1.
- Derived roles: SIMPLE, PARENT, CHILD, COMBO. Product Parent/Child depth is max 1; do not confuse this with arbitrary-depth Product Group hierarchy.
- SIMPLE/CHILD are leaf transactionable; PARENT is not selectable/transactionable.
- COMBO is selectable composition/template; nested COMBO is allowed only when acyclic and operational processing flattens to SIMPLE/CHILD leaves.
- Pricing resolution is Product + UOM + Company + Effective Time with fallback Company Price List -> Group Price List -> correct-UOM default.
- COMBO only prices at Base UOM.
- COMBO allocation resolves each root price once; nested COMBO prices are ignored during flatten/allocation.
- Allocation weight is leaf quantity × leaf Base-UOM `products.price`; leaf Price Lists do not affect weights.
- Total weight <= 0 is rejected; final monetary values round to 2 decimals with deterministic residual assignment.
- Same leaf across roots aggregates amount and quantity; final unit price is total amount / total quantity using decimal-safe arithmetic.
- Never use binary float for authoritative money/quantity/allocation arithmetic.

## Product Import

- P1 import target baseline is PRODUCT only.
- Shared technical reader/persistence stays Product-agnostic in `App\Infrastructure\Import`; Product template/model/mutation/auth stays in `App\Modules\Product\Import`.
- CSV/XLSX converge on format-neutral `ImportDocument` / `ImportRow` semantics.
- Modes: CREATE_ONLY / UPDATE_ONLY / UPSERT.
- Start gate requires `product.import` at authoritative GROUP; each logical mutation still needs `product.create`, `product.edit`, and `product.setstate` when applicable.
- Product import source-local identity is `source_key`; persistent target key is `product_code -> products.code`; `source_key` is never persisted to Product.
- Dependency order in source file must not matter; detect source/deep COMBO cycles and preserve original row number.
- Row mutation is atomic; failed row rolls back its Product/UOM/COMBO mutation and Auto Number allocation.
- Job lifecycle: PENDING -> PROCESSING -> COMPLETED / COMPLETED_WITH_ERRORS; fatal job-level failure -> FAILED.
- Row statuses are SUCCESS / ERROR / SKIPPED. Generated Product code is persisted as row `entity_key` on successful generated create.
- Reconcile counts from persisted row results while locking `import_jobs` `FOR UPDATE`; SKIPPED contributes to total only.
- Same source hash is integrity/duplicate-warning data, not automatic idempotent success.
- Do not persist raw source row payload merely for import logging.

## API transport and Gate 7

- Prefix `/api/v1`; JSON keys snake_case.
- BIGINT and DECIMAL domain values are JSON strings.
- Invisible existing target -> 404; visible but denied action -> 403.
- Preserve stable machine error codes; never drive client logic from localized messages.
- Every protected endpoint declares Resource/Action, authoritative Evaluation Organization source, and visibility rule.
- Collection authorization/data-scope filtering happens before `meta.total`; query/search/filter/sort fields are allowlisted.
- Public API changes update OpenAPI in the same task, Redocly lint must pass, and typed frontend artifacts are regenerated; generated files are not hand-edited.
- Only implement `CONTRACT_READY` surfaces. The v1.8 API Design §19 and approved Change 10 now provide the Shield User write/state/reset-password design; implementation must be verified in the real repository. Remaining source-open items include public Price Resolution authorization, optional COMBO preview endpoints, and exact export media type.

## Localization

- Supported locales: `vi`, `en`; default/fallback `vi`.
- Frontend application text uses `vue-i18n`.
- API sends/accepts locale headers according to contract; CodeIgniter negotiates human-readable language.
- Machine codes, JSON field names, enum values, Resource/Action codes, identifiers, import keys, and user-entered business data are not translated.
- Locale must not affect authorization, business timezone, Evaluation Organization, or persistence semantics.
- Do not create translation tables/locale columns for P1 UI localization.
