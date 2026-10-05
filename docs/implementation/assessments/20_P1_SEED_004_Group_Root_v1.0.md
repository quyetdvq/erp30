---
task_id: "P1-SEED-004"
title: "Canonical GROUP root bootstrap"
version: "1.0"
date: "2026-10-05"
repository: "D:\\XAMPP\\htdocs\\erp"
status: "DONE"
---

# P1-SEED-004 Canonical GROUP root bootstrap

## Scope and authority

This task seeds only the canonical Organization `GROUP` root. It does not seed
Company rows, change architecture baselines, modify migrations, or begin
P1-DB-090/downstream work.

Authority consulted:

- FRS-P1-ORG-001, including the one-GROUP-root acceptance rule;
- DBD v1.10 section 8.1, including the Organization root and nullable fields;
- Implementation Contract v1.5 sections 5 and 19;
- Implementation Plan v1.3 P1-SEED-004;
- AGENTS.md and the Gate 1 HIGH-risk `seed` validation profile.

## Product Owner decision

The approved canonical root is:

```text
code=GROUP_DINKTECH
name=Dinktech Group
description=Tập đoàn Dinktech
type=GROUP
status=ACTIVE
parent_id=NULL
legal_name=NULL, tax_code=NULL, address=NULL
province_id=NULL, ward_id=NULL
active_from=NULL, active_to=NULL
created_by=NULL, updated_by=NULL
```

Bootstrap creates this row only when no GROUP root exists. One matching root is
an untouched idempotent no-op. An alternate root, multiple roots, incompatible
canonical code, or canonical code outside the root position is a BLOCK conflict;
the seeder never renames or silently overwrites existing business data.

No separate `docs/implementation/decisions/` directory or decision convention
exists, so this assessment records the approved decision without modifying the
architecture baseline.

## Validation

Validation runs against `erp_test` only. Development database `erp` is not
reset, migrated, or seeded.

| Check | Result |
|---|---|
| Focused GROUP-root PHPUnit | PASS; 6 tests, 30 assertions |
| Full PHPUnit | PASS; 56 tests, 1700 assertions |
| PHPStan level 8 | PASS; 77 files |
| Empty-scope creation and no Company seed | PASS |
| Canonical rerun/no-op preservation | PASS |
| Alternate, multiple, incompatible, and non-root conflicts | PASS |
| `git diff --check` | PASS |
