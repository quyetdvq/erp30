# P1-PRD-002 Product UOM Master Assessment

Status: DONE

## Scope and boundary

The shared Product UOM master catalog is implemented under:

- `App\\Modules\\Product\\Uom\\ProductUomRepository`
- `App\\Modules\\Product\\Uom\\ProductUomMasterService`

`ProductUomService` remains reserved for P1-UOM-001. This task implements only
the reusable `product_uoms` catalog: code, name, nullable description, status,
trusted audit fields, normal historical lookup, and the active-reference
selector `requireActiveProductUom()`. It does not implement Product roles,
Base/Alternate UOM conversion, Product UOM conversion rows, Pricing, COMBO, or
Product master behavior.

No new class was added under flat `App\\Services` or `App\\Repositories`.
The Product module has no Pricing dependency.

## Behavior implemented

- Persistence-only repository primitives: find by id/code, active lookup,
  deterministic list, insert, mutable update, and status update.
- Positive BIGINT id normalization and typed persistence/duplicate/update errors.
- Create validation for required and bounded code/name/description and
  `ACTIVE`/`INACTIVE` status.
- Global `product_uoms.code` uniqueness remains database-enforced and is mapped
  to a stable duplicate domain error.
- Edit permits code/name/description only; status requires `setState()` and
  `product_uom.setstate`.
- Create/edit/setstate use `product_uom.create`, `product_uom.edit`, and
  `product_uom.setstate` at the canonical GROUP evaluation organization.
- Client evaluation-organization hints cannot override GROUP context.
- OperationContext supplies trusted UTC audit identity/time; caller audit fields
  are ignored. Setstate authorization audit is persisted through AUTH-004.
- Inactivation does not cascade into Products, Product UOM conversions, or
  Pricing records. Historical lookup still resolves inactive rows, while
  `requireActiveProductUom()` rejects them with a typed inactive-reference
  error.

## Validation evidence

- Focused UOM suite: PASS, 10 tests / 129 assertions.
- P1-PRD-001 ProductGroup regression: PASS, 9 tests / 50 assertions.
- P1-DB-060 Product/UOM/COMBO schema regression: PASS, 2 tests / 293 assertions.
- Authorization regressions (`P1AuthorizationMandatoryRegressionTest`,
  `P1Auth002AuthorizationServiceTest`, `P1Auth005SecurityAdministrationTest`):
  PASS, 28 tests / 170 assertions.
- Full backend PHPUnit `--no-coverage`: PASS, 248 tests / 2661 assertions.
- PHPStan level 8 application and focused UOM analysis: PASS.
- Architecture guard, context-cache/module-map validator, ledger validator, and
  `git diff --check`: PASS.

## Contract impact

No migration, schema, API/OpenAPI, UI/i18n architecture, Pricing, Import, or
business-semantic changes were made. The existing `product_uoms` table and
Resource/Action catalog are used unchanged. P1 progress becomes `51/100` and
Gate 5 becomes `2/11`; P1-PRD-003 and all later Product/UOM/Pricing/COMBO/Import
tasks remain untouched.
