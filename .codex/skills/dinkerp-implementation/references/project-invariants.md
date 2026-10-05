# DinkERP P1 Project Invariants

Consult the current project docs for complete wording. These are guardrails, not replacements for the sources.

## Authority and change control

- Never silently change a baselined UR/FRS/DBD/Implementation Contract semantic.
- Never add persistence outside the current Database Design without approved additive design/change control.
- Never invent a Resource Type, Action code, API contract, enum, or business rule to make coding easier.
- Migrations are forward-only; do not rewrite an applied migration.

## Authorization / PBAC

- System Admin has no bypass and no `isSystemAdmin => ALLOW` branch.
- Base Permission is mandatory before PBAC evaluation.
- Per-lane precedence is `ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH`, with `NO_MATCH => ALLOW` after Base Permission exists.
- Resolve inheritance before denied-set intersection.
- Applicable `ABSOLUTE_DENY` must survive every intersection.
- Resource means securable type/capability, not one Resource row per business record.
- No Resource Instance Authorization in P1.
- Authorization Group `GROUP` participant is not Organization type `GROUP`.

## Evaluation Organization

- Existing target: derive authoritative context from persisted target/owner.
- Create: derive from canonical owner/parent/scope in request.
- Primary Assignment supplies default context only when no authoritative target context exists.
- `organization.create`: evaluate at Parent Organization.
- `employee_assignment.create/edit`: evaluate at Assignment Organization.
- Group-level shared/admin resources may evaluate at Organization `GROUP`.
- Client context is a hint/selector only; mismatch with authoritative context is rejected.
- Company summary authorization is never a substitute for concrete Organization authorization.

## Employee assignments

- Eligible assignment is effective and located at the Evaluation Organization or an ancestor on its exact scope path.
- GROUP assignment can flow to descendants.
- Sibling/other-Company assignment cannot bleed into the current path.
- Primary Assignment is not the only authorization lane.

## Database / transactions

- Repository handles persistence; services own business invariants.
- Multi-table invariant mutations are atomic.
- Use the approved DomainLock/advisory-lock convention for race-sensitive domain changes.
- Advisory acquire/release must use the same DB connection/session.
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

## Product / pricing / COMBO

- One Product entity; no Variant entity/table in P1.
- Derived roles: SIMPLE, PARENT, CHILD, COMBO.
- SIMPLE/CHILD are leaf transactionable; PARENT is not selectable/transactionable.
- COMBO is selectable composition/template and must flatten to SIMPLE/CHILD for operational processing.
- Nested COMBO allowed but graph must be acyclic.
- Pricing resolution is Product + UOM + Company + Effective Time with fallback Company Price List -> Group Price List -> correct-UOM default.
- COMBO only prices at Base UOM.
- Never use binary float for authoritative money/quantity/allocation arithmetic.

## Product Import

- P1 import target baseline is PRODUCT only.
- Modes: CREATE_ONLY / UPDATE_ONLY / UPSERT.
- Start gate requires `product.import`; each logical row still needs normal create/edit/setstate authorization.
- Product import source-local identity is `source_key`; persistent target key is `product_code -> products.code`.
- Dependency order in source file must not matter; detect cycles.
- Row mutation is atomic; failed row rolls back its Auto Number allocation.

## API transport

- Prefix `/api/v1`.
- JSON keys snake_case.
- BIGINT and DECIMAL domain values are JSON strings.
- Invisible existing target -> 404; visible but denied action -> 403.
- Preserve stable machine error codes; never drive client logic from localized messages.
- Public API change requires OpenAPI update and regenerated frontend client/types.

## Localization

- Supported locales: `vi`, `en`; default/fallback `vi`.
- Frontend application text uses `vue-i18n`.
- API sends `Accept-Language`; CodeIgniter negotiates language; localized response uses `Content-Language` when applicable.
- Machine codes, JSON field names, enum values, Resource/Action codes, identifiers, import keys, and user-entered business data are not translated.
- Locale must not affect authorization, business timezone, Evaluation Organization, or persistence semantics.
- Do not create translation tables/locale columns for P1 UI localization.
