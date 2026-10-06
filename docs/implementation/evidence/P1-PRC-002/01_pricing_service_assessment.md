# P1-PRC-002 PricingService Assessment

Status: DONE

## Scope and module boundary

`App\\Modules\\Pricing\\Resolution\\PricingService` is a read-only internal
business service. It adds no controller, route, OpenAPI/client, permission,
authorization audit, resolution persistence, cache/history table, lock, or
Product-to-Pricing reverse dependency.

## Resolution contract

- Input is Product ID, UOM ID, Company Organization ID, and one trusted
  `EvaluationTime`; omitted time captures the injected `Clock` exactly once.
- The supplied Organization must itself be type `COMPANY`. Its authoritative
  GROUP root comes from `OrganizationService::resolveAncestorChain`; client
  scope/group hints are not accepted.
- Product existence, active status, selectability, and SIMPLE/CHILD/COMBO role
  are resolved through `ProductRoleService`. PARENT and inactive Products are
  rejected.
- SIMPLE/CHILD selling UOM validation delegates to `ProductUomService`.
  COMBO accepts only its active base UOM.

## Candidate query and fallback

`PriceListRepository::findBestMatchingPrice()` is a persistence-only primitive.
It joins exact `product_id` and `uom_id`, filters `ACTIVE` headers using
`effective_from <= evaluation_time` and
`effective_to IS NULL OR evaluation_time < effective_to`, and orders by
`priority DESC`, `effective_from DESC`, `price_list_id ASC`.

`PricingService` performs separate Company then GROUP queries, so Company scope
always wins regardless of priority and an unmatched Company header still falls
through to GROUP. Inactive, future, and expired lists are ignored.

## Defaults and result

- SIMPLE/CHILD base and COMBO base use `products.price`.
- SIMPLE/CHILD alternate UOM uses that exact conversion's configured `price`;
  no factor-derived price or base-price substitution is performed.
- Missing, null, malformed, or otherwise invalid required prices fail closed
  with `PRICING_NO_VALID_PRICE`; explicit zero remains valid.
- Result includes normalized integer IDs, exact requested UOM, `unit_price`,
  `price_source` (`PRICE_LIST_COMPANY`, `PRICE_LIST_GROUP`, or
  `PRODUCT_DEFAULT`), and selected `price_list_id` or null.

## Validation evidence

- Focused P1-PRC-002: PASS, 6 tests / 35 assertions.
- P1-PRC-001: PASS, 5 tests / 73 assertions.
- P1-UOM-001: PASS, 6 tests / 69 assertions.
- P1-PRD-004: PASS, 4 tests / 29 assertions.
- P1-PRD-003: PASS, 6 tests / 44 assertions.
- DB-070: PASS, 2 tests / 103 assertions.
- DB-060: PASS, 2 tests / 293 assertions.
- Mandatory authorization regression: PASS, 9 tests / 68 assertions.
- Full backend PHPUnit `--no-coverage`: PASS, 275 tests / 2,911 assertions.
- PHPStan level 8 application: PASS; architecture guard: PASS; context cache and
  ledger validators: PASS; scoped task-file `git diff --check`: PASS. The
  repository-wide diff check reports only pre-existing trailing whitespace in
  the unrelated `docs/thamkhao/.../Tracuuu-Tonghop.md` edit, which was not
  modified or staged.

No schema, API/OpenAPI, UI/i18n, Import, COMBO expansion/allocation, or
authorization behavior was added. The pre-existing unrelated working-tree edit
under `docs/thamkhao` was preserved and not staged.
