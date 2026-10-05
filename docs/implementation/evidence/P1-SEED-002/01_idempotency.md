# P1-SEED-002 Evidence: Workflow State Seed

Date: 2026-10-05
Repository: `D:\\XAMPP\\htdocs\\erp`
Database: `erp_test` only

## Bootstrap and repeatability

`WorkflowStateSeeder` was executed twice against the isolated test database.

```text
Run #1: PASS; 5 rows
Run #2: PASS; 5 rows
Canonical codes: DRAFT, PENDING_APPROVAL, APPROVED, REJECTED, CANCELLED
One row per code: PASS
Initial name = code: PASS
No duplicate rows after rerun: PASS
Code catalog stable: PASS
```

The regression test then changed the `DRAFT` display name and reran the
seeder. The row remained identified by `code = DRAFT`, the changed display
name was preserved, and no duplicate was created. This proves `code`, not
`name`, is the bootstrap/application identity boundary.

No extra workflow states, lifecycles, lifecycle-state mappings, or lifecycle
transitions were created.
