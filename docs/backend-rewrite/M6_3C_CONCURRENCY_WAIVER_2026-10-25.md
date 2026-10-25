# M6.3C: explicit DEV concurrency waiver (2026-10-25)

## Operator decision

The operator explicitly elected to **skip the outstanding M6.3C two-writer
CAS/lock-wait test** and reported that the normal Avatar UI workflow was tested
manually through the Frontend on local XAMPP. The previous M6.3B.2 Admin Avatar
upload/replace/delete had a separate operator-reported `PASS_DEV_ADMIN_AVATAR_HTTP_FS_SQL`
with an independent final `m63b1:db-audit 3 EMPTY` PASS.

This is a **WAIVER / NOT VERIFIED** verdict for M6.3C concurrency and live
HTTP forced-storage failure. It is **not** a PASS for CAS, InnoDB locking,
rollback, storage-fault recovery, or production cutover.

## Preserved evidence

- M6.3C offline regression: 110/110 PASS on owner XAMPP before M6.3C.1.
- M6.3C safety: 48/48 PASS after M6.3C.1; storage fixture 15/15 with GD.
- M6.3C race preflight: `PASS_DEV_AVATAR_RACE_PREFLIGHT`.
- M6.3C explicit race attempt: `FAIL_DEV_AVATAR_RACE_QA`, `phase=attest_local_mysql`,
  `sessions=0`, `lock_waits=0`, winner/loser HTTP `NOT_TESTED`; no race HTTP POST observed.
- Diagnostic `--mysql-check`: `FAIL_DEV_AVATAR_MYSQL_ATTEST`,
  `step=users_table_engine`, `code=MYSQL_USERS_ENGINE_NOT_INNODB_OR_UNREADABLE`.
  This code is inconclusive between a non-InnoDB engine and unreadable/incorrect
  metadata: do **not** assume which cause is established without further evidence.
- `php spark m63b1:db-audit 3 EMPTY`: PASS on owner local database `erp`.
- PDO_MYSQL driver: YES.

## Explicit risk acceptance and scope

The operator permits the project to proceed to the next **read-only** milestone
(M6.4A) without forcing another write test or altering the `erp` schema. The
engine/metadata attestation remains an **open technical risk** that must be
resolved as part of any later production release readiness decision. No
`ALTER TABLE`, database reset, avatar metadata overwrite, or simulated PASS is
part of this waiver. Frozen OpenAPI and existing Avatar runtime code remain
unchanged. Production cutover remains BLOCKED pending M9 acceptance.
