# P1-UAT-001 - Business Sign-off Checklist

| Sign-off item | Required reviewer | Evidence required | Status |
|---|---|---|---|
| Environment and deployment boundary | Operations / Release owner | Approved staging/UAT URL, banner, maintenance window | OPEN - not supplied |
| Identity and role mapping | Security owner | Named Shield identities, role/action matrix, expiry | OPEN - not supplied |
| Organization and scoped visibility | Operations / PO | Sanitized screenshots/traces and scoped row/count review | OPEN - no live run |
| Employee and assignment workflows | HR owner | Primary/secondary assignment review and boundary evidence | OPEN - no live run |
| Security, PBAC and negative paths | Security owner | 401/403/404/CSRF evidence and audit review | OPEN - no live run |
| Numbering, Product, UOM and COMBO | Product owner | Generated-code/counter and exact decimal review | OPEN - no live run |
| Pricing and allocation | Finance/Product owner | Company -> Group -> default resolution and allocation reconciliation | OPEN - no live run |
| Product import | Product owner / Operations | CSV/XLSX mode, dependency, failed-row rollback and report review | OPEN - no live run |
| Display Settings and localization | Product owner / Frontend | vi/en screenshots, headers, formatter and inheritance review | OPEN - no live run |
| Defect disposition | Release owner | S1/S2 defect decisions and regression evidence | OPEN - 10 automated failures unresolved |
| Final business acceptance | Product Owner | Named approval with date and scope | NOT REQUESTED / MUST NOT FABRICATE |

No reviewer name, signature, date, or acceptance is inferred from a ledger
status or from an automated test.

