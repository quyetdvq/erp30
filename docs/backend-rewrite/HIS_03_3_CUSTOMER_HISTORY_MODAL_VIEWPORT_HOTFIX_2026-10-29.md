# HIS-03.3 — Customer detail modal layout when viewing Change History

**Date:** 2026-10-29
**Baseline:** `erp_v2.0.0_his03_2_compact_wire_fixed.zip`
**Scope:** frontend-only hotfix; no SQL, PHP, API, PBAC, JSON, Workflow or migration changes.
**Status:** source contract PASS; browser layout / Vitest / type-check / production build require Windows DEV validation.

## Issue observed

Opening `Change history` within the canonical Customer detail modal displays a long, paginated list of History events. The Customer dialog originally only set `max-height` and let its height follow the active tab. Short tabs (e.g. Documents) made the dialog collapse; a long History tab expanded it to the viewport cap. As users switched tabs, the dialog geometry, footer position and retained body scroll position could become inconsistent. Long diff labels could also increase the grid's minimum content width.

The supplied Windows logs confirm the HIS-03.2 backend/Customer offline tests are PASS; the issue is a frontend modal layout concern, not a new `changes_json` serialization defect.

## Fix

1. The existing `CustomerDetailView.vue` remains the **only canonical Customer modal**, whether opened from `/customers` or from `/system/history`.
2. For an **existing** Customer, the modal gets the CSS class `customer-detail-modal--workspace`, with stable height `min(760px, viewport height - 40px)` on desktop. The `New Customer` form is not forced to that fixed workspace height.
3. Header, action toolbar, tabs and footer are non-shrinking flex items. Only `.customer-detail-modal__body` scrolls, with `min-height:0`, `overscroll-behavior:contain`, stable scrollbar gutter, and wrapped content. Switching tabs resets the body's scroll position to the top.
4. At width <=640px the Customer backdrop uses 8px padding and the workspace uses `100dvh - 16px` to avoid overshooting the available mobile viewport.
5. `BusinessHistoryPanel.vue` remains inside the canonical modal's body, without introducing a second scrollbar or duplicating a detail modal. The history grid and rows allow long strings to wrap (`min-width:0`, `overflow-wrap:anywhere`).

## Verification performed in the authoring environment

| Gate | Result | Limit |
|---|---|---|
| Node layout/source contract `scripts/his03-3-customer-history-modal.contract.test.cjs` | **3/3 PASS** | Source contracts; not browser layout measurement |
| `node scripts/check-imports.cjs` | **PASS: 0 import errors** | Static import validation |
| `php tests/history/history_presenter_pure.php` | **21/21 PASS** | Existing backend; no runtime MariaDB |
| `php tests/history/history_policy_pure.php` | **40/40 PASS** | Existing backend; no runtime MariaDB |
| Live Vue/Vitest / Windows browser | **NOT RUN** | No installed frontend node_modules in delivery container |
| Chromium CSS geometry probe | **INCONCLUSIVE** | Browser executable did not complete headless rendering in this environment |

## Windows execution

```powershell
cd D:\XAMPP\htdocs\erp
$patch = ".\DinkERP_HIS03_3_Customer_History_Modal_Layout_Hotfix.patch"
git apply --check $patch
if ($LASTEXITCODE -ne 0) { throw "STOP: baseline mismatch" }
git apply $patch
if ($LASTEXITCODE -ne 0) { throw "STOP: apply failed" }
git diff --check
if ($LASTEXITCODE -ne 0) { throw "STOP: whitespace" }

cd .\frontend
node --test scripts/his03-3-customer-history-modal.contract.test.cjs
pnpm exec vitest run src/features/history/__tests__/businessHistoryPanel.spec.ts src/features/history/__tests__/historyMonitorModal.spec.ts src/features/history/__tests__/historyPresentation.spec.ts
pnpm run type-check
pnpm run check:imports
pnpm run build
```

### Manual UAT (no automated authenticated HTTP)

- Open Customer detail **from `/customers`**, switch Documents / Profile / Change history multiple times. The existing-record dialog's outer size and footer remain stable, and a History list with many events scrolls vertically *inside* the body. The Close button stays visible.
- Open Customer detail **from `/system/history`** by clicking the object icon; repeat the same tab changes. It must use the same Customer modal and preserve permissions.
- Scroll partway through History, click Documents, then click Change history again. It should start at the top, not inherit the previous tab's scroll offset.
- Test a long field name/value, a page of 25 events, viewport <=640px, and keyboard `Esc` / Close. The modal must not exceed the viewport or produce a horizontal page scroll.
- Confirm the New Customer create form and the existing contact detail modal are unchanged.

**No migration, data deletion, restart, or History re-provisioning is needed.**
