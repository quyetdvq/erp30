# M3.2 Access/PBAC Runtime Adapter

The M3.2 implementation lives under `app/Modules/Access` and is safe-by-default:

- Existing HTTP read endpoints use `ReadAccessBridge` in **legacy** mode by default.
- Run `php tests/m32_runtime_pure.php` and `php tests/m32_baseline_lanes_pure.php` for offline tests.
- Run `php spark m3:audit` and `php spark m32:verify` for read-only MariaDB checks.
- Optional `DINKERP_M32_READ_MODE=shadow` compares new/old decisions while responding with old decisions.
- Do **not** enable `enforce` before runtime and baseline HTTP parity, negative tests and Frontend E2E.

See `../docs/backend-rewrite/M3_2_IMPLEMENTATION_AND_QA.md` for operational steps.
