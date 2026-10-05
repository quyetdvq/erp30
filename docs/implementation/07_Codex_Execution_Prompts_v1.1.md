---
document_id: "CODEX-MINI-ERP-P1-001"
title: "DinkERP P1 - Codex Execution Prompts"
version: "1.1"
status: "working-guide"
date: "2026-10-05"
---

# DinkERP P1 - Codex Execution Prompts

Use these prompts with the repository root set to:

```text
D:\XAMPP\htdocs\erp
```

Keep `AGENTS.md` at repository root. Prefer short task prompts that name the Task ID/outcome; let `AGENTS.md`, the project docs, and the `dinkerp-implementation` skill carry reusable rules.


Repository documentation layout assumed by this revision:

```text
docs/
├── architecture/      # 00..06 source/design documents
├── implementation/    # this prompt guide + assessments/evidence
└── thamkhao/          # non-authoritative references
```

---

# 1. First prompt: repository/runtime assessment

```text
Use the dinkerp-implementation skill.

Execute P1-ENV-001 only: Repository & runtime inventory.

Read AGENTS.md and the current docs/architecture authority files referenced there. Inspect the actual backend and frontend repositories before making any recommendation.

Goals:
- verify actual folder/layout conventions;
- verify CodeIgniter, Shield, Settings, PHP, MariaDB, PHPUnit and PHPStan setup;
- verify frontend Vue/TypeScript/Vite/Vitest/Playwright scripts and structure;
- inventory migrations, seeders, auth/Shield schema integration, API conventions and existing i18n setup;
- identify exact locations for OpenAPI source and generated frontend client;
- identify current decimal-arithmetic mechanism and DB lock/advisory-lock capability;
- compare reality against docs/architecture/04_P1_Implementation_Plan_v1.3.md.

Do not implement product features in this task. Do not refactor unrelated code.

Output:
1. verified repository map;
2. commands that actually work for backend/frontend quality gates;
3. gaps against the implementation plan;
4. blockers/change requests if any;
5. recommendation whether P1-ENV-001 can be marked DONE.
```

---

# 2. Generic Task-ID implementation prompt

Use this for most work after repository assessment.

```text
Use the dinkerp-implementation skill.

Implement Task <TASK_ID> from docs/architecture/04_P1_Implementation_Plan_v1.3.md.

Before editing:
- read AGENTS.md;
- read the exact Task definition and only the relevant UR/FRS/DBD/Implementation Contract/API/UI sections;
- inspect current code and reuse repository conventions;
- give a compact execution plan and task contract (scope, out-of-scope, dependencies, DB/auth/concurrency/API/UI/test impact).

Then implement the task end-to-end.

Requirements:
- do not invent missing requirements or persistence;
- if a source conflict or missing decision blocks correctness, stop that part and mark BLOCKED instead of guessing;
- add/adjust automated tests;
- run all applicable quality gates;
- update OpenAPI and generated frontend types/client if the public API changes;
- do not edit baseline docs unless the task explicitly requests a documentation change.

Finish with files changed, tests/commands and PASS/FAIL/NOT RUN evidence, contract impact, and the next dependency.
```

---

# 3. Vertical-slice prompt

```text
Use the dinkerp-implementation skill.

Implement the next coherent work for <SLICE_NAME> using the rolling vertical-slice model in docs/architecture/04_P1_Implementation_Plan_v1.3.md.

First determine which tasks in this slice are READY from the current repository state. Do not skip unmet dependencies.

For each READY task, work in this order where applicable:
1. schema/repository prerequisite;
2. domain/application service and invariant tests;
3. API contract/OpenAPI;
4. API implementation and contract tests;
5. generated frontend client/types;
6. UI implementation using docs/architecture/06_P1_UI_Design_v1.2.md;
7. Vitest/Playwright/accessibility/i18n checks.

Do not declare the slice VERIFIED until all slice Definition-of-Done gates actually pass.

If the full slice is too large for one run, stop at a clean Task boundary and report the exact next Task ID.
```

---

# 4. Migration/schema prompt

```text
Use the dinkerp-implementation skill.

Implement <DB_TASK_ID> only.

Read the relevant DBD v1.10 table definitions/invariants and the migration dependency order before editing.
Inspect existing migrations first.

Rules:
- forward-only migration;
- do not rewrite applied migrations;
- preserve exact PK/FK/unique/index/nullability semantics;
- do not add convenience columns/tables outside DBD;
- seeders must be idempotent;
- test fresh migration and forward migration where applicable;
- add a final DB guard for race-sensitive uniqueness.

Do not implement service/API/UI behavior beyond what is necessary to validate the schema task.
```

---

# 5. Authorization/PBAC prompt

```text
Use the dinkerp-implementation skill.

Implement/review <AUTH_TASK_ID>.

Treat Authorization as security-critical.
Read the exact PBAC sections in FRS v1.7 and Implementation Contract v1.5 before editing.

Explicitly verify in code/tests:
- no System Admin bypass;
- Base Permission prerequisite;
- authoritative Evaluation Organization resolution;
- GROUP -> exact descendant path inheritance;
- Primary vs Secondary Assignment semantics;
- per-lane QT1 precedence;
- ABSOLUTE_DENY propagation before intersection;
- denied-set intersection across valid lanes;
- sibling/other-Company isolation;
- invisible target 404 vs visible-denied 403.

Use table-driven tests for the mandatory authorization scenarios. Do not weaken a rule just to make an existing test pass.
```

---

# 6. API endpoint prompt

```text
Use the dinkerp-implementation skill.

Implement API contract and runtime for <ENDPOINT_OR_API_TASK>.

Sources:
- docs/architecture/05_P1_API_Design_v1.1.md for route/request/response/auth contract;
- upstream FRS/DBD/Implementation Contract for semantics.

Before coding, confirm the endpoint is CONTRACT_READY. If it is CONTRACT_PENDING, stop and report the missing decision.

Implement:
- controller/adapter only as thin transport layer;
- service invocation at the correct authorization/use-case boundary;
- snake_case JSON;
- BIGINT/DECIMAL string serialization;
- request_id propagation;
- canonical 400/401/403/404/409/422 behavior;
- Accept-Language/Content-Language localized human-readable message behavior;
- stable machine error codes;
- OpenAPI update + Redocly lint + frontend code generation;
- contract/integration tests including negative authorization/disclosure paths.
```

---

# 7. UI screen prompt

```text
Use the dinkerp-implementation skill.

Implement UI screen <UI_TASK_OR_SCREEN> from docs/architecture/06_P1_UI_Design_v1.2.md.

First confirm its API dependency is CONTRACT_READY/implemented enough for the requested work.
Inspect the existing frontend shell/components/stores before creating new abstractions.

Requirements:
- use existing Naive UI/repository patterns;
- use semantic vue-i18n keys for every application-controlled user-facing string;
- support vi/en without reload;
- never use translated labels as canonical identity;
- treat permission visibility as UX only;
- handle 401/403/404/409/422/500 correctly;
- preserve decimal strings for authoritative values;
- do not duplicate backend business/PBAC rules;
- add Vitest tests and critical Playwright/accessibility coverage;
- run vue-tsc, lint, format check and build.

If this is shell work, preserve the approved 5-mode resizable/collapsible sidebar and workspace-tab behavior.
```

---

# 8. Auto Numbering prompt

```text
Use the dinkerp-implementation skill.

Implement/review <NUMBERING_TASK_ID>.

Before editing, read the Auto Numbering sections in FRS v1.7, DBD v1.10, Implementation Contract v1.5 and API/UI Design as applicable.

Mandatory correctness cases:
- multiple series with max one active default;
- scope GROUP/COMPANY/ORGANIZATION;
- reset period/timezone resolution;
- uniqueness-namespace validation;
- AUTO_REQUIRED vs AUTO_IF_EMPTY;
- manual identifier does not advance counter;
- occupied generated candidate safely advances sequence;
- existing counter row lock;
- first-counter race serialization;
- counter update and business create in same transaction;
- rollback reuses uncommitted number;
- committed number not reused;
- issued-series immutability and no hard delete;
- Product alias product_code maps to products.code.

Add real concurrency/integration tests against MariaDB where required; unit mocks alone are not sufficient for locking behavior.
```

---

# 9. Product Import prompt

```text
Use the dinkerp-implementation skill.

Implement/review <IMPORT_TASK_ID> for Product import only.

Preserve:
- CREATE_ONLY / UPDATE_ONLY / UPSERT;
- strict CSV UTF-8/BOM/header rules;
- XLSX first nonempty sheet and formula-cell rejection;
- unknown header => whole-file invalid template;
- blank vs __NULL__ semantics;
- source_key dependency graph for creates;
- persistent product_code for existing-target lookup;
- Parent/Child/UOM/COMBO order independence and COMBO cycle detection;
- Gate 1 product.import and Gate 2 per-row mutation authorization;
- row-level atomicity and original row number;
- failed row rolls back Auto Number allocation.

Do not enable import for Employee/Province/Ward or other resources.
```

---

# 10. i18n prompt

```text
Use the dinkerp-implementation skill.

Implement/review the P1 localization foundation.

Backend:
- supported locales vi/en, fallback vi;
- CodeIgniter language negotiation from Accept-Language;
- one resolved locale for the request;
- stable machine error/detail codes across locales;
- localize human-readable error/validation messages only;
- set Content-Language where applicable;
- keep JSON media representation canonical.

Frontend:
- vue-i18n semantic keys;
- runtime language switch without reload;
- update html lang;
- send Accept-Language on all API requests;
- store canonical screen/menu keys, not translated labels;
- key parity vi/en tests;
- no translation of user-entered business data, identifiers, enums/raw codes or import keys.

Do not add locale/translation persistence tables.
```

---

# 11. Code-review prompt before merge

```text
Use the dinkerp-implementation skill in REVIEW mode.

Review the current diff for Task <TASK_ID>. Do not modify files yet.

Audit against:
- AGENTS.md;
- task source traceability;
- relevant baseline docs;
- security/authorization invariants;
- DB/migration constraints;
- transaction/concurrency behavior;
- API/OpenAPI compatibility;
- UI/i18n behavior;
- automated tests and quality gates.

Report findings ordered by severity:
1. correctness/security/data-integrity blockers;
2. contract/schema drift;
3. missing tests;
4. maintainability issues that materially affect this task.

For each finding, cite file + line and explain the violated project rule. Do not list cosmetic preferences unless they create a real project risk.

End with APPROVE / REQUEST_CHANGES / BLOCKED and the exact commands that still need to pass.
```

---

# 12. Fix-only prompt after review/CI failure

```text
Use the dinkerp-implementation skill.

Fix only the failures listed below for Task <TASK_ID>:
<PASTE FAILURES>

Inspect root cause before changing code. Preserve current approved behavior and do not relax tests, PHPStan, lint rules, DB constraints, authorization rules or API contracts simply to make CI green.

After fixes, rerun the narrow failing checks first, then the full applicable Task quality gates. Report exact commands and results.
```

---

# 13. Recommended first execution sequence

After placing `AGENTS.md` in repo root and installing/enabling the Skill:

```text
1. Prompt #1  -> P1-ENV-001
2. Resolve environment gaps
3. P1-ENV-002 / 003 / 004 and P1-INF tasks
4. P1-DB-001
5. Start Slice 1 using Prompt #3
6. Use Prompt #11 before every merge of a security/schema/concurrency-sensitive task
```

The most important rule is: **give Codex a Task ID/outcome, not the entire project in every prompt**. The permanent rules belong in `AGENTS.md`; the repeatable workflow belongs in the Skill; task-specific intent belongs in the prompt.
