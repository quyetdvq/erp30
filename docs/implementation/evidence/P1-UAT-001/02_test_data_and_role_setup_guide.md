# P1-UAT-001 - Test Data and Role Setup Guide

## Status

This is a controlled setup plan, not a credential or seed script. The user has
authorized the current host as UAT and supplied the `quyetdv` identity. API
read/negative checks are therefore authorized; browser UI execution remains
blocked by the live certificate/CORS path, and business fixture mutation still
requires an approved reset/rollback window. No production or development `erp`
mutation is authorized by this artifact.

## Required approvals before execution

1. Environment name, base URL, database isolation boundary, and maintenance window.
2. Security-approved identities for each role below, with MFA/session policy and expiry.
3. Product Owner approval of fixture values and expected business results.
4. Reversible fixture load and cleanup/restore method; no real customer/employee data.
5. Screenshot/trace/log sanitization owner and evidence retention location.

## Role matrix

| Alias | Business role | Minimum capabilities to verify | Negative counterpart |
|---|---|---|---|
| `UAT-SEC-ADMIN` | Security administrator | Shield User state, group membership, direct permission, authorization group, resource, policy, audit | `UAT-SEC-READ` without mutation actions |
| `UAT-ORG-ADMIN` | Organization administrator | Organization hierarchy, contacts, scoped visibility, safe edits | `UAT-ORG-A` limited to Company A |
| `UAT-HR` | HR administrator | Employee, manager, Department, Position, Primary/Secondary Assignment | `UAT-HR-READ` view only |
| `UAT-WF-ADMIN` | Workflow/configuration administrator | Workflow state, lifecycle, transition, audit | `UAT-WF-READ` without mutation |
| `UAT-PRODUCT` | Product master operator | Product roles, Product Group, UOM, conversion, numbering | `UAT-PRODUCT-READ` view only |
| `UAT-PRICING` | Pricing operator | Price List, effective time, COMBO allocation | `UAT-PRICING-READ` view only |
| `UAT-IMPORT` | Product import operator | Product import start gate and row-level create/edit/setstate | `UAT-IMPORT-READ` view only |
| `UAT-DISPLAY` | Display/settings administrator | Display Settings view/edit and locale presentation checks | `UAT-DISPLAY-READ` view only |
| `UAT-AUDITOR` | Business/security reviewer | Read-only evidence, audit, reports, scoped totals | No mutation capability |

The identities must be created and mutated through Shield-only administration.
Do not copy production passwords, tokens, cookies, or personal data into this
repository or evidence directory.

## Fixture plan

| Fixture set | Contents | Isolation and cleanup |
|---|---|---|
| Organization | One GROUP; Company A/B; Branch A1; Warehouse A1W; unrelated Company C | Prefix all codes with `UAT-20261009`; delete/restore only through approved cleanup |
| People | Employee E1 without User; E2 with User; Department D1; Position P1; active/inactive manager candidates; primary/secondary assignments | Use synthetic names/emails; verify row counts before and after |
| Authorization | Group permission, direct ALLOW, policy ALLOW/DENY/ABSOLUTE_DENY, sibling Company policy | Record only IDs/codes, not secrets; remove fixture policies after evidence |
| Numbering | Product series for GROUP and Company A with AUTO_REQUIRED/AUTO_IF_EMPTY and yearly reset | Use dedicated series prefix; confirm counters before/after each case |
| Product/UOM | SIMPLE S1, Parent P1, Child C1, Product Groups G1/G2, base PCS and alternate BOX | Use synthetic codes; no existing business code updates |
| COMBO/Pricing | Combo B1 with S1/C1 and nested B2; Company and Group price lists; boundary effective times | Freeze one evaluation instant per scenario; capture exact decimal strings |
| Import | CSV/XLSX valid, CREATE_ONLY, UPDATE_ONLY, UPSERT, source-key dependency, one invalid row, one cycle | Hash files; retain sanitized copies only; rollback failed rows and numbering |
| Display/i18n | System defaults, Organization override, sibling Organization, vi/en requests | Restore settings after each case; locale is client preference only |

## Execution controls

- Verify the URL and environment banner before login; abort on an unknown host.
- Verify the actor alias and capability snapshot before every mutation.
- Use one scenario's fixture prefix per run; do not reuse a committed business identifier.
- Capture request ID, stable error code, sanitized screenshot, and trace for each negative path.
- Reconcile database/report counts after imports and numbering; never infer success from a toast alone.
- Sign-off is recorded only by the named business reviewer in the checklist, not by the test operator.

