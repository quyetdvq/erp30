# DinkERP Codex Rules

Applies to the repository rooted at `D:\XAMPP\htdocs\erp`.

## Source of truth

Read only the relevant sections needed for the current task, but preserve this authority order:

1. `docs/architecture/00_User_Requirements_v1.0.12.md` — business intent and scope.
2. `docs/architecture/01_P1_Functional_Requirements_Specification_v1.7.md` — functional semantics.
3. `docs/architecture/02_P1_Database_Design_v1.10.md` — persistence/schema invariants.
4. `docs/architecture/03_P1_Implementation_Contract_v1.5.md` — wire/runtime conventions.
5. `docs/architecture/04_P1_Implementation_Plan_v1.3.md` — sequencing, Task IDs, dependencies, quality gates.
6. `docs/architecture/05_P1_API_Design_v1.1.md` — downstream HTTP/API design.
7. `docs/architecture/06_P1_UI_Design_v1.2.md` — downstream UI/UX design.

If sources conflict, do not silently reconcile them. Stop the affected part, identify the conflict, and state the smallest decision/change request needed.


## Documentation folders

- `docs/architecture/` contains the P1 authority/design documents `00` through `06`.
- `docs/implementation/` contains execution guides, repository assessments, task evidence, rollout notes, and other implementation-working artifacts.
- `docs/thamkhao/` contains non-authoritative reference material. Do not treat it as source-of-truth unless a task explicitly asks to consult it.
- Do not move or rename baselined architecture documents during feature work.

## Before editing

- Inspect the current repository structure and existing conventions first.
- Do not invent folders, patterns, abstractions, commands, or dependencies when the repository already has an equivalent convention.
- State the Task ID or concrete outcome and identify relevant source sections.
- Confirm dependencies and whether the task is READY per the Implementation Plan.
- Identify DB, authorization, transaction/concurrency, API/OpenAPI, UI/i18n, and test impact.

For P1 implementation work, resolve Task IDs through
`.codex/skills/dinkerp-implementation/scripts/task-context.php` first and
require a valid authority hash cache before normal source reading; use the
task impact to select scoped validation gates.

## Change-control boundaries

Do not silently:

- change baselined business/functional semantics;
- add tables/columns/indexes/FKs outside DBD v1.10;
- invent Resource Types or Action codes;
- invent public API semantics when API Design marks them pending;
- add translation tables/locale columns for P1 UI localization;
- expand Import targets beyond Product;
- introduce Resource Instance Authorization;
- rewrite an already-applied migration.

Use forward-only migrations.

## Authorization invariants

- Never implement `isSystemAdmin => ALLOW` or equivalent bypass.
- Base Permission is a prerequisite.
- Per-lane precedence: `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`; after Base Permission, `NO_MATCH => ALLOW`.
- Resolve policy inheritance before denied-set intersection.
- Applicable `ABSOLUTE_DENY` must survive all intersections.
- Resource means type/capability, not one resource row per business record.
- Authorization Group participant `GROUP` is not Organization type `GROUP`.
- Existing target derives Evaluation Organization from persisted target/owner.
- Create derives context from canonical owner/parent/scope.
- `organization.create` evaluates at parent Organization.
- `employee_assignment.create/edit` evaluates at Assignment Organization.
- Client-provided evaluation context cannot override authoritative context.
- Primary Assignment supplies default context only when no authoritative target context exists.
- GROUP/ancestor assignments can participate on the exact scope path; sibling/other-Company assignments cannot bleed.

## Database and transaction rules

- Repository handles persistence primitives; services own domain invariants.
- Multi-table invariant mutations must be atomic.
- Use the approved DomainLock/advisory-lock convention for race-sensitive mutations.
- Advisory acquire/release must use the same DB connection/session.
- Retry at most once and only for retry-safe/idempotent operations.
- Do not use binary float for authoritative money, quantity, conversion, or allocation calculations.
- BIGINT remains the technical PK. Auto Numbering generates business identifiers only.

## Auto Numbering

- Use `auto_numbering_series` + `auto_numbering_counters`; do not put counter state in `resources`.
- Scope: GROUP / COMPANY / ORGANIZATION.
- Mode: AUTO_REQUIRED / AUTO_IF_EMPTY.
- Reset: NEVER / YEARLY / MONTHLY / DAILY.
- Allocation and business create must share one DB transaction.
- Rolled-back number may be reused; committed number may not be reused by business logic.
- AUTO_IF_EMPTY manual identifier does not advance the counter.
- Skip occupied generated candidates safely under the same serialization.
- Issued series cannot hard-delete; immutable after issuance: resource, series code, scope type, reset policy.
- Product mapping: API/import `product_code` -> DB `products.code`; max 50; GLOBAL uniqueness.

## Product / pricing / import

- One Product entity; no Variant entity/table in P1.
- Derived roles: SIMPLE / PARENT / CHILD / COMBO.
- SIMPLE/CHILD are leaf transactionable; PARENT is not selectable/transactionable.
- Nested COMBO is allowed but must be acyclic and flatten to SIMPLE/CHILD.
- Pricing: Product + UOM + Company + Effective Time; fallback Company Price List -> Group Price List -> correct-UOM default.
- Product is the only P1 import target.
- Import modes: CREATE_ONLY / UPDATE_ONLY / UPSERT.
- Import authorization is two-gate: `product.import` plus per-row normal mutation authorization.
- Product create dependencies may use `source_key`; persistent target key remains `product_code`.
- Failed import row must leave no partial business mutation or Auto Number allocation.

## API rules

- Prefix `/api/v1`.
- JSON keys are `snake_case`.
- BIGINT and DECIMAL domain values serialize as strings.
- Invisible existing target -> 404; visible target lacking action -> 403.
- Preserve stable machine error codes. Never parse localized messages for logic.
- Public API changes require OpenAPI update, Redocly lint, and regenerated frontend client/types.
- Requests use `Accept-Language: vi|en`; localized responses use `Content-Language` when applicable.

## UI / i18n rules

- Vue UI uses `vue-i18n`; baseline locales are `vi` and `en`, fallback `vi`.
- Store canonical screen/menu/status keys, not translated labels, as identity.
- User-entered business data, identifiers, enums, JSON fields, Resource/Action codes, and import keys are not translated.
- UI permission filtering is UX only; backend remains authority.
- Do not reimplement PBAC or business invariants in the frontend.
- Preserve the approved workspace shell: 5 sidebar modes, resizable/collapsible sidebar, workspace tabs.

## Quality gates

Backend, when applicable:

- PHPUnit passes.
- PHPStan level 8 passes.
- Migration/fresh/forward tests pass.
- Integration/concurrency tests pass.

Frontend, when applicable:

- `vue-tsc` passes.
- ESLint/Oxlint passes.
- Prettier check passes.
- Vitest passes.
- production build passes.
- critical Playwright and accessibility smoke pass.
- `vi`/`en` translation-key parity passes.

## Completion report

Always finish implementation work with:

- Task ID / outcome.
- Files changed.
- Behavior implemented.
- Tests and exact validation commands run, with PASS/FAIL/NOT RUN.
- DB/auth/API/UI impact.
- Any blocker, assumption, or remaining dependency.

Do not claim a check passed if it was not run.


## Git task boundary

- A Task with final status `DONE` must end with a Git commit containing only
  changes belonging to that Task ID.
- Include the Task ID in the commit subject.
- Required quality gates must pass before the commit.
- `PARTIAL`, `BLOCKED`, or `NEEDS_FIX` tasks must not create a completion commit.
- Never stage/revert/commit unrelated pre-existing working-tree changes.
- Never use destructive history operations unless explicitly instructed.
