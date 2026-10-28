# ERP v1.4.0 — M8.5 Authorization SQL Scope Delivery

**Cơ sở:** source M8 150/150 API đã được áp dụng tuần tự; patch M8.5 incremental trên M8.

## Deliverables

1. Access/Query: AuthorizedSqlScope, AuthorizationSqlScopeCompiler, ResourceScopeProvider và EffectiveActionHintCache.
2. Product/Master: ProductScopeProvider, ProductReadSql, bounded DTO projection và SQL-scoped ProductReadService.
3. Pricing: PriceListScopeProvider read-only, chưa chuyển traffic production; đa công ty phải duyệt theo tổ chức/State.
4. M8.5 static acceptance, 4 fixture/pure suites, source-only read inventory và hướng dẫn QA.
5. Không thay đổi OpenAPI, Frontend, database schema, mutation logic hoặc baseline.

## Source status

- M8 routes: **150/150**, no new routes.
- Pure/fixture regression: **135/135 PASS**.
- Architecture acceptance: **6/6 PASS**.
- M8.5 SQL/PBAC tests: **23/23 PASS**, Product SQL: **46/46 PASS**, DTO fixture: **17/17 PASS**, transaction fixture: **12/12 PASS**.
- Authenticated HTTP/MariaDB, true multi-company PBAC parity, EXPLAIN, unicode sorting/search parity: **NOT_TESTED**.
- Production: **BLOCKED**.

## Safeguards

- Compile VIEW from canonical PBAC, scope before COUNT & page, no unscoped fallback.
- JOIN Parent may return its label **only** if Parent is VIEW-authorized. Hidden Parent → Child name only.
- `allowed_actions` retains per-row response shape; Product MASTER hints cached per State only within request. Multi-company hints remain context-specific.
- Pricing provider bounded to 512 organizations/slots and remains opt-in until verified; old pricing list path unchanged.

## XAMPP

Apply the patch from `D:\XAMPP\htdocs\erp` using `git -c core.autocrlf=true apply --check` then `git -c core.autocrlf=true apply`.
Run `python backend\tools\verify_routes.py`, `php backend\tools\m85_accept.php`, `php backend\tools\m85_scope_audit.php`, then from backend `php tests\m85_...` and `tools\test-unit.ps1`.

See `docs/backend-rewrite/M8_5_AUTH_SQL_SCOPE_IMPLEMENTATION_QA.md` for negative cases, backlog inventory and live acceptance gates.

### Transaction compatibility guard (đã bổ sung)
- Product Master `create/update/setstate` gọi `ProductReadService::show()` trong transaction ghi trước commit. M8.5 chỉ cho detail **mượn transaction khỏe mạnh của caller**, không can thiệp BEGIN/COMMIT/ROLLBACK; `list` vẫn buộc sở hữu snapshot riêng.
- `m85_product_snapshot_fixture.php`: 12/12 assertion PASS offline, không DB, để chống regression transaction của Product mutation.
- Parent visibility metadata không được thiếu: Product CHILD có `__parent_visible` null/missing phải lỗi fail-closed.
