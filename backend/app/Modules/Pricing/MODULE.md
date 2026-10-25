# Pricing — backend rewrite module owner (M7.3)

The `Pricing` domain owns Price List configuration and Product Price List Items.
The entrypoints are `PriceList/PriceListController.php` and
`PriceList/PriceListService.php` under `App\Modules\Pricing`.

## Boundaries

- `GET /api/v1/price-lists`, `GET /api/v1/price-lists/{id}`, and
  `GET /api/v1/price-lists/{id}/items` are authenticated reads.
- Price List VIEW authorization uses `UnifiedAccessService` on persisted
  `ORGANIZATION`-domain organization/state facts; list COUNT and LIMIT are
  scoped in SQL before returning data. This module does not implement a
  generic policy-to-SQL translator and never substitutes an ALLOW JOIN.
- POST/PATCH/State transition and PUT item replacement stay disabled by
  default through the M3 write gate. Successful writes run with advisory
  lock, InnoDB row locks and an all-or-nothing database transaction.
- Product/UOM references are checked through Product's existing read facts;
  no Pricing-specific Product mutation is exposed.
- The frozen OpenAPI contract is authoritative for route inventory. There
  is no migration/schema edit, no new implicit routing and no Frontend edit.

## Open acceptance items

The offline tests establish syntax, source boundaries and deterministic
rules. They **do not** prove HTTP authorization, live MariaDB rollback,
concurrent writers, baseline parity or Pricing performance. Production
write/cutover remains BLOCKED until independent integration evidence exists.
