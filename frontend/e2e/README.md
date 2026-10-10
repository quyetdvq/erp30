# Playwright E2E follow-up (localhost, pnpm)

Báo cáo mới: 132 test, 121 PASS, 11 FAIL. `fe001-shell.spec.ts`: 15/15 PASS, KHÔNG thay đổi.

File được sửa:

- `frontend/e2e/fe070-pricing-combo.spec.ts`: xử lý `request.postDataJSON()` có thể là `{ items: [...] }` hoặc mảng; đồng thời cập nhật CORS mock.
- `frontend/e2e/vue.spec.ts`: giới hạn Axe vào `#app`, không quét nút Vue DevTools ngoài app khi chạy dev localhost.

## Áp dụng

Ở thư mục gốc ERP:

```powershell
git apply --check .\DinkERP_Playwright_2Files.patch
git apply .\DinkERP_Playwright_2Files.patch
```

Hoặc chép trực tiếp 2 file trong `frontend/e2e/` vào cùng đường dẫn của dự án. ĐỪNG chép đè file `fe001-shell.spec.ts` đang hoạt động.

## Kiểm tra sau áp dụng

```powershell
cd frontend
pnpm exec prettier --write e2e/fe070-pricing-combo.spec.ts e2e/vue.spec.ts
pnpm run format:check
pnpm run type-check
pnpm exec playwright test e2e/fe070-pricing-combo.spec.ts e2e/vue.spec.ts --reporter=line
```

## Thu thập trace cho 5 timeout còn lại

```powershell
pnpm exec playwright test e2e/fe030-security.spec.ts e2e/fe040-workflow.spec.ts e2e/fe080-product-import.spec.ts --project=firefox --trace=retain-on-failure --reporter=line
pnpm exec playwright test e2e/fe030-security.spec.ts --project=webkit --trace=retain-on-failure --reporter=line
```

Trace nếu sinh ra ở `test-results/` có thể chứa nội dung phiên browser/mock; kiểm tra trước khi chia sẻ.

Lưu ý: chỉ xác minh patch áp dụng vào snapshot Batch 1; CHƯA chạy Playwright trong môi trường này.
