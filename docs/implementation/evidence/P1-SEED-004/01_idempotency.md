# P1-SEED-004 Evidence: Canonical GROUP Root Bootstrap

Date: 2026-10-05
Repository: `D:\XAMPP\htdocs\erp`
Database: `erp_test` only

## Approved bootstrap

```text
Created root: GROUP_DINKTECH / Dinktech Group / Tập đoàn Dinktech
Type/status: GROUP / ACTIVE
Parent: NULL
Company rows created: 0
```

## Conflict and idempotency matrix

```text
Empty scope -> create exactly one root: PASS
Canonical root rerun -> untouched no-op: PASS
Alternate GROUP root code -> BLOCK, no rename/create: PASS
Multiple GROUP roots -> BLOCK: PASS
GROUP_DINKTECH occupied by incompatible type -> BLOCK: PASS
GROUP_DINKTECH exists outside root position -> BLOCK: PASS
```

No development database reset, migration, or seed was performed.
