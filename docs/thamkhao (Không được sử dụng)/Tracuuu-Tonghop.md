| Nhóm                       | Lệnh                                         | Mô tả ngắn gọn                                        |
| -------------------------- | -------------------------------------------- | ----------------------------------------------------- |
| **Kiểm tra**               | `git status`                                 | Xem trạng thái Working Tree và Staging Area           |
|                            | `git branch`                                 | Liệt kê các branch local                              |
|                            | `git branch -vv`                             | Xem branch, commit hiện tại, tracking và ahead/behind |
|                            | `git branch -r`                              | Liệt kê các branch trên remote                        |
|                            | `git remote -v`                              | Xem URL của remote                                    |
|                            | `git remote show origin`                     | Xem thông tin chi tiết của remote và tracking         |
| **Lịch sử**                | `git log`                                    | Xem lịch sử commit đầy đủ                             |
|                            | `git log --oneline`                          | Xem lịch sử ngắn gọn                                  |
|                            | `git log --oneline --graph --decorate --all` | Hiển thị cây commit trực quan                         |
|                            | `git show HEAD`                              | Xem commit hiện tại                                   |
|                            | `git show <commit>`                          | Xem nội dung một commit                               |
| **So sánh**                | `git diff`                                   | So sánh Working Tree với commit gần nhất              |
|                            | `git diff --stat`                            | Thống kê số file và số dòng thay đổi                  |
|                            | `git diff --check`                           | Kiểm tra trailing whitespace, conflict marker...      |
|                            | `git diff --cached`                          | Xem phần đã stage                                     |
|                            | `git diff main..HEAD`                        | So sánh branch hiện tại với main                      |
|                            | `git diff branchA..branchB`                  | So sánh hai branch                                    |
| **Commit giữa các branch** | `git log main..HEAD`                         | Các commit chỉ có ở branch hiện tại                   |
|                            | `git log HEAD..main`                         | Các commit chỉ có ở main                              |
|                            | `git log branchA..branchB`                   | Commit có ở B nhưng không có ở A                      |
| **Branch**                 | `git switch main`                            | Chuyển branch                                         |
|                            | `git switch -c feature/login`                | Tạo và chuyển sang branch mới                         |
|                            | `git branch feature/login`                   | Chỉ tạo branch                                        |
|                            | `git branch -m new-name`                     | Đổi tên branch hiện tại                               |
|                            | `git branch -d feature/login`                | Xóa branch đã merge                                   |
|                            | `git branch -D feature/login`                | Ép xóa branch                                         |
| **Stage & Commit**         | `git add file.php`                           | Stage một file                                        |
|                            | `git add .`                                  | Stage toàn bộ thay đổi                                |
|                            | `git restore file.php`                       | Hủy thay đổi của file                                 |
|                            | `git restore --staged file.php`              | Bỏ file khỏi Staging Area                             |
|                            | `git commit -m "..."`                        | Tạo commit                                            |
|                            | `git commit --amend`                         | Sửa commit cuối cùng                                  |
| **Push/Pull**              | `git fetch`                                  | Cập nhật thông tin từ remote (không merge)            |
|                            | `git pull`                                   | Lấy và merge thay đổi từ remote                       |
|                            | `git push`                                   | Đẩy commit lên remote                                 |
|                            | `git push -u origin branch`                  | Push lần đầu và thiết lập tracking                    |
| **Reset**                  | `git reset --soft HEAD~1`                    | Hủy commit, giữ Staging Area                          |
|                            | `git reset --mixed HEAD~1`                   | Hủy commit, bỏ Staging Area nhưng giữ file            |
|                            | `git reset --hard HEAD~1`                    | Hủy commit và xóa luôn thay đổi                       |
| **Cherry-pick**            | `git cherry-pick <commit>`                   | Mang một commit từ branch khác sang                   |
|                            | `git cherry-pick --continue`                 | Tiếp tục sau khi resolve conflict                     |
|                            | `git cherry-pick --abort`                    | Hủy cherry-pick                                       |
| **Merge/Rebase**           | `git merge feature`                          | Gộp branch                                            |
|                            | `git rebase main`                            | Đưa branch hiện tại lên nền mới của main              |
|                            | `git rebase --abort`                         | Hủy rebase                                            |
| **Stash**                  | `git stash`                                  | Cất tạm thay đổi                                      |
|                            | `git stash list`                             | Xem danh sách stash                                   |
|                            | `git stash pop`                              | Lấy lại stash và xóa khỏi danh sách                   |
|                            | `git stash apply`                            | Lấy lại stash nhưng vẫn giữ                           |
| **Khôi phục**              | `git reflog`                                 | Xem lịch sử HEAD để cứu commit                        |
|                            | `git reset --hard HEAD@{n}`                  | Quay về trạng thái trước đó                           |
| **Kiểm tra chất lượng**    | `git diff --check`                           | Kiểm tra lỗi định dạng trước khi commit               |
|                            | `git fsck`                                   | Kiểm tra tính toàn vẹn của repository                 |
|                            | `git gc`                                     | Dọn dẹp và tối ưu repository                          |
| **Backup**                 | `git branch backup/<name>`                   | Tạo branch backup trước thao tác nguy hiểm            |
|                            | `git tag <tag>`                              | Đánh dấu một mốc quan trọng                           |


# Git Cheat Sheet

> Tổng hợp các lệnh Git thường dùng trong quá trình phát triển phần mềm.

---

# 1. Kiểm tra trạng thái

| Lệnh | Mô tả |
|------|------|
| `git status` | Xem trạng thái Working Tree và Staging Area |
| `git branch` | Liệt kê các branch local |
| `git branch -vv` | Xem branch, commit hiện tại, tracking và ahead/behind |
| `git branch -r` | Liệt kê các branch trên Remote |
| `git remote -v` | Xem URL của Remote |
| `git remote show origin` | Xem thông tin chi tiết của Remote |

---

# 2. Xem lịch sử Commit

| Lệnh | Mô tả |
|------|------|
| `git log` | Xem lịch sử commit đầy đủ |
| `git log --oneline` | Xem lịch sử ngắn gọn |
| `git log --oneline --graph --decorate --all` | Hiển thị cây commit trực quan |
| `git show HEAD` | Xem commit hiện tại |
| `git show <commit>` | Xem nội dung một commit |

---

# 3. So sánh thay đổi (Diff)

| Lệnh | Mô tả |
|------|------|
| `git diff` | So sánh Working Tree với commit gần nhất |
| `git diff --stat` | Thống kê file và số dòng thay đổi |
| `git diff --check` | Kiểm tra trailing whitespace, conflict marker... |
| `git diff --cached` | Xem phần đã Stage |
| `git diff main..HEAD` | So sánh branch hiện tại với main |
| `git diff branchA..branchB` | So sánh hai branch |

---

# 4. So sánh Commit giữa các Branch

| Lệnh | Mô tả |
|------|------|
| `git log main..HEAD` | Commit chỉ có ở branch hiện tại |
| `git log HEAD..main` | Commit chỉ có ở main |
| `git log branchA..branchB` | Commit có ở branchB nhưng không có ở branchA |

---

# 5. Làm việc với Branch

| Lệnh | Mô tả |
|------|------|
| `git switch main` | Chuyển branch |
| `git switch -c feature/login` | Tạo và chuyển sang branch mới |
| `git branch feature/login` | Chỉ tạo branch |
| `git branch -m new-name` | Đổi tên branch hiện tại |
| `git branch -d feature/login` | Xóa branch đã merge |
| `git branch -D feature/login` | Ép xóa branch |

---

# 6. Stage & Commit

| Lệnh | Mô tả |
|------|------|
| `git add file.php` | Stage một file |
| `git add .` | Stage tất cả thay đổi |
| `git restore file.php` | Hủy thay đổi của file |
| `git restore --staged file.php` | Bỏ file khỏi Staging Area |
| `git commit -m "message"` | Tạo commit |
| `git commit --amend` | Sửa commit cuối |

---

# 7. Push / Pull

| Lệnh | Mô tả |
|------|------|
| `git fetch` | Cập nhật thông tin từ Remote (không merge) |
| `git pull` | Lấy và merge thay đổi từ Remote |
| `git push` | Đẩy commit lên Remote |
| `git push -u origin branch-name` | Push lần đầu và thiết lập tracking |

---

# 8. Reset

| Lệnh | Mô tả |
|------|------|
| `git reset --soft HEAD~1` | Hủy commit, giữ Staging Area |
| `git reset --mixed HEAD~1` | Hủy commit, bỏ Stage nhưng giữ file |
| `git reset --hard HEAD~1` | Hủy commit và xóa luôn thay đổi |

> ⚠️ Cẩn thận với `--hard`, dữ liệu có thể mất.

---

# 9. Cherry-pick

| Lệnh | Mô tả |
|------|------|
| `git cherry-pick <commit>` | Mang một commit từ branch khác sang |
| `git cherry-pick --continue` | Tiếp tục sau khi resolve conflict |
| `git cherry-pick --abort` | Hủy Cherry-pick |

---

# 10. Merge / Rebase

| Lệnh | Mô tả |
|------|------|
| `git merge feature/login` | Merge branch |
| `git rebase main` | Rebase lên main mới nhất |
| `git rebase --abort` | Hủy Rebase |

---

# 11. Stash

| Lệnh | Mô tả |
|------|------|
| `git stash` | Cất tạm thay đổi |
| `git stash list` | Danh sách stash |
| `git stash pop` | Lấy lại stash và xóa khỏi danh sách |
| `git stash apply` | Lấy lại stash nhưng vẫn giữ |

---

# 12. Khôi phục (Recovery)

| Lệnh | Mô tả |
|------|------|
| `git reflog` | Nhật ký mọi lần HEAD thay đổi |
| `git reset --hard HEAD@{n}` | Quay lại trạng thái trước đó |

---

# 13. Kiểm tra chất lượng

| Lệnh | Mô tả |
|------|------|
| `git diff --check` | Kiểm tra lỗi khoảng trắng và conflict marker |
| `git fsck` | Kiểm tra tính toàn vẹn Repository |
| `git gc` | Dọn dẹp và tối ưu Repository |

---

# 14. Backup

| Lệnh | Mô tả |
|------|------|
| `git branch backup/<name>` | Tạo branch backup |
| `git tag v1.0.0` | Đánh dấu một mốc quan trọng |

---

# Quy trình Git khuyến nghị

## 1. Trước khi bắt đầu một Task

```bash
git status
git branch -vv
git pull
git log --oneline --decorate -10
```

**Mục đích**

- Kiểm tra Working Tree sạch.
- Kiểm tra đang đứng đúng branch.
- Đồng bộ với GitHub.
- Xem 10 commit gần nhất.

---

## 2. Trong quá trình phát triển

```bash
git status
git diff --stat
git diff
git diff --check
```

**Mục đích**

- Kiểm tra file thay đổi.
- Xem thống kê thay đổi.
- Kiểm tra nội dung thay đổi.
- Kiểm tra lỗi định dạng.

---

## 3. Trước khi Commit

```bash
git status
git diff
git diff --check
git add .
git diff --cached --check
git commit -m "..."
```

**Mục đích**

- Đảm bảo thay đổi đúng.
- Không còn lỗi định dạng.
- Commit sạch.

---

## 4. Trước khi Push

```bash
git status
git branch -vv
git log --oneline @{u}..HEAD
git push
```

**Mục đích**

- Working Tree sạch.
- Branch tracking đúng.
- Xem commit sắp Push.
- Đẩy code lên GitHub.

> Lưu ý: `@{u}` chỉ hoạt động khi branch đã được thiết lập tracking. Với branch mới, hãy dùng:
>
> ```bash
> git push -u origin <branch-name>
> ```

---

## 5. Trước khi giao việc cho Codex

```bash
git status
git branch -vv
git log --oneline --decorate -10
git diff --stat main..HEAD
```

**Mục đích**

- Xác nhận Working Tree sạch.
- Kiểm tra branch và tracking.
- Xem lịch sử commit gần nhất.
- Xem branch hiện tại khác `main` ở những file nào.

---

# Một số lệnh hữu ích khác

| Lệnh | Mô tả |
|------|------|
| `git branch --show-current` | Hiển thị branch hiện tại |
| `git rev-parse HEAD` | Lấy SHA đầy đủ của commit hiện tại |
| `git rev-parse --short HEAD` | Lấy SHA rút gọn |
| `git clean -fd` | Xóa file/thư mục chưa được Git theo dõi |
| `git clean -fdn` | Xem trước những gì sẽ bị xóa |
| `git restore .` | Hủy toàn bộ thay đổi chưa Stage |
| `git restore --source=<commit> file.php` | Khôi phục file từ một commit |
| `git blame file.php` | Xem ai sửa từng dòng trong file |
| `git shortlog -sn` | Thống kê số commit theo tác giả |
| `git describe --tags` | Xác định commit theo tag gần nhất |







# Vue 3 + Vite + pnpm Command Cheat Sheet

## 1. Khởi tạo Project

| Lệnh | Mô tả |
|------|------|
| `pnpm create vue` | Tạo project Vue mới |
| `npm create vue@latest` | Tạo project Vue bằng npm |
| `pnpm create vue@latest` | Tạo project với phiên bản Vue CLI mới nhất |

---

## 2. Quản lý Package

| Lệnh | Mô tả |
|------|------|
| `pnpm install` | Cài đặt toàn bộ package |
| `pnpm add <package>` | Cài package mới |
| `pnpm add -D <package>` | Cài package cho môi trường phát triển |
| `pnpm remove <package>` | Gỡ package |
| `pnpm update` | Cập nhật package |
| `pnpm outdated` | Kiểm tra package có phiên bản mới |
| `pnpm why <package>` | Xem package được cài bởi dependency nào |
| `pnpm ls --depth=0` | Liệt kê package cấp cao nhất |

---

## 3. Development

| Lệnh | Mô tả |
|------|------|
| `pnpm dev` | Chạy Development Server |
| `pnpm dev --host` | Cho phép truy cập từ mạng LAN |
| `pnpm preview` | Xem bản build Production |

---

## 4. Build

| Lệnh | Mô tả |
|------|------|
| `pnpm build` | Build project Production |
| `pnpm exec vite build` | Build trực tiếp bằng Vite |
| `pnpm exec vite preview` | Preview bản build |

---

## 5. TypeScript

| Lệnh | Mô tả |
|------|------|
| `pnpm exec vue-tsc --noEmit` | Kiểm tra lỗi TypeScript |
| `pnpm exec vue-tsc --noEmit -p tsconfig.app.json` | Kiểm tra theo tsconfig cụ thể |
| `pnpm exec vue-tsc --version` | Xem phiên bản vue-tsc |

---

## 6. Vite

| Lệnh | Mô tả |
|------|------|
| `pnpm exec vite` | Chạy Vite trực tiếp |
| `pnpm exec vite --version` | Xem phiên bản Vite |
| `pnpm exec vite build` | Build bằng Vite |
| `pnpm exec vite preview` | Preview Production |

---

## 7. Vitest

| Lệnh | Mô tả |
|------|------|
| `pnpm test:unit` | Chạy Unit Test |
| `pnpm vitest` | Chạy Vitest ở chế độ Watch |
| `pnpm vitest run` | Chạy Unit Test một lần |
| `pnpm vitest --coverage` | Sinh báo cáo Coverage |

---

## 8. Playwright

| Lệnh | Mô tả |
|------|------|
| `pnpm exec playwright install` | Cài browser |
| `pnpm test:e2e` | Chạy E2E Test |
| `pnpm exec playwright test` | Chạy Playwright |
| `pnpm exec playwright test --ui` | Chạy giao diện UI |
| `pnpm exec playwright test --debug` | Debug Test |
| `pnpm exec playwright codegen` | Sinh mã test tự động |

---

## 9. ESLint

| Lệnh | Mô tả |
|------|------|
| `pnpm lint` | Kiểm tra lỗi ESLint |
| `pnpm lint --fix` | Tự động sửa lỗi |

---

## 10. Prettier

| Lệnh | Mô tả |
|------|------|
| `pnpm format` | Format toàn bộ project |
| `pnpm exec prettier --write .` | Format tất cả file |
| `pnpm exec prettier --check .` | Kiểm tra định dạng |

---

## 11. OpenAPI

| Lệnh | Mô tả |
|------|------|
| `pnpm exec openapi-ts` | Sinh TypeScript Client từ OpenAPI |

---

## 12. Redocly

| Lệnh | Mô tả |
|------|------|
| `pnpm exec redocly lint openapi.yaml` | Kiểm tra OpenAPI Spec |

---

## 13. Kiểm tra môi trường

| Lệnh | Mô tả |
|------|------|
| `node -v` | Xem phiên bản Node.js |
| `npm -v` | Xem phiên bản npm |
| `pnpm -v` | Xem phiên bản pnpm |

---

## 14. Dọn dẹp và cài lại

### Windows CMD

| Lệnh | Mô tả |
|------|------|
| `rmdir /s /q node_modules` | Xóa node_modules |
| `del pnpm-lock.yaml` | Xóa file lock |
| `pnpm install` | Cài lại package |

### PowerShell

| Lệnh | Mô tả |
|------|------|
| `Remove-Item node_modules -Recurse -Force` | Xóa node_modules |
| `Remove-Item pnpm-lock.yaml` | Xóa file lock |
| `pnpm install` | Cài lại package |

---

## 15. Quy trình kiểm tra dự án

| Bước | Lệnh |
|------|------|
| 1 | `pnpm install` |
| 2 | `pnpm ls --depth=0` |
| 3 | `pnpm exec vue-tsc --noEmit -p tsconfig.app.json` |
| 4 | `pnpm lint` |
| 5 | `pnpm test:unit` |
| 6 | `pnpm exec playwright install` |
| 7 | `pnpm test:e2e` |
| 8 | `pnpm build` |
| 9 | `pnpm preview` |

---

## 16. Một số lệnh hữu ích

| Lệnh | Mô tả |
|------|------|
| `pnpm dlx` | Chạy package mà không cần cài |
| `pnpm store prune` | Dọn dẹp cache pnpm |
| `pnpm cache delete` | Xóa cache pnpm |
| `pnpm doctor` | Kiểm tra môi trường pnpm (nếu hỗ trợ) |
| `pnpm dedupe` | Gộp dependency trùng lặp |



# Nền tảng

## environment_binding:
  project_root_windows: 'D:\XAMPP\htdocs\erp'
  docs_root_relative: 'docs/'
  backend_root_relative: 'backend/'
  frontend_root_relative: 'frontend/'
  backend_url: 'http://api.monmin.net'
  frontend_url: 'http://erp.monmin.net'
  backend_runtime: 'XAMPP/Apache + CodeIgniter already running'
  php_runtime: '8.3.x'
  database_engine: 'MariaDB 10.x'
  phpstan_level: 8


## Backend

```text
PHP runtime/source target: 8.3.x
CodeIgniter 4.7.x
CodeIgniter Shield
CodeIgniter Settings
MariaDB 10.4.32
PHPUnit
PHPStan
CodeIgniter PHPStan extension
PhpSpreadsheet
Smalot PdfParser

```

## Frontend

```
dependencies:
├── @vueuse/core@14.4.0
├── axios@1.20.0
├── bootstrap@5.3.8
├── chart.js@4.5.1
├── dayjs@1.11.23
├── naive-ui@2.45.3
├── pinia@4.0.3
├── vue@3.5.42
├── vue-chartjs@5.3.4
├── vue-i18n@11.4.12
├── vue-router@5.3.1
├── zod@4.6.5

devDependencies:
├── @axe-core/playwright@4.13.0
├── @hey-api/openapi-ts@0.99.0
├── @playwright/test@1.63.0
├── @redocly/cli@2.53.0
├── @tsconfig/node24@24.0.5
├── @types/jsdom@30.0.0
├── @types/node@24.13.4
├── @vitejs/plugin-vue@6.0.9
├── @vitest/eslint-plugin@1.6.27
├── @vue/eslint-config-typescript@14.9.0
├── @vue/test-utils@2.5.0
├── @vue/tsconfig@0.9.1
├── eslint@10.10.0
├── eslint-config-prettier@10.1.8
├── eslint-plugin-oxlint@1.82.0
├── eslint-plugin-playwright@2.12.0
├── eslint-plugin-vue@10.11.1
├── jiti@2.7.0
├── jsdom@30.1.1
├── npm-run-all2@9.0.3
├── oxlint@1.82.0
├── prettier@3.9.6
├── typescript@6.0.3
├── vite@8.3.0
├── vite-plugin-vue-devtools@8.2.1
├── vitest@4.1.11
├── vue-eslint-parser@10.4.1
└── vue-tsc@3.3.11
```




Bạn hãy đọc lại: 00_User_Requirements_v1.0.6-draft.md; 01_P1_Functional_Requirements_Specification_v1.0-draft.md và 02_P1_Database_Design_v1.3.md xem đã đủ để bắt đầu code chưa?

===========
Tôi viết lại quy tắc như này (đã kể đến sự kế thừa)
Nếu 1 user thộc nhiều Department Và nhiều Position trong nhiều Organization khác nhau.

Ta sẽ phân chia phạm vi cho từng công ty.

Trong mỗi công ty ta xét như sau:

QT1: ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH; NO_MATCH => ALLOW

1. Xét các phân quyền được gán riêng cho user (trong phạm vi COMPANY) theo QT1 ==> KQ[user] = những quyền bị cấm
2. Xét quyền được gán theo Position và Department (trong phạm vi từng Department) theo QT1 ==> KQ[Departments] = những quyền bị cấm
3. KQ[Organization] = Giao của (KQ[user] và các KQ[Departments]) Ghi chú Organization = [BRANCH, PLANT, WAREHOUSE]
4. KQ[COMPANY] = Giao (KQ[Organization1], KQ[Organization2] ...)

Luật: Khi xét quyền Position và Department phải xét đến sự kế thừa từ các Organization phía trên.
Tương tự quyền trong mỗi Organization cũng phải xét đến sự kế thừa từ COMPANY

```
FOR EACH COMPANY:

    FOR EACH Organization O:

        1. Load Policy:
           COMPANY
           + ancestors of O
           + O

        2. Tính KQ[user, O]
           theo QT1 + inheritance

        3. Với từng Department/Position assignment phù hợp O:
           tính KQ[assignment, O]
           theo QT1 + inheritance

        4. KQ[O]
           =
           INTERSECTION(
               KQ[user, O],
               all KQ[assignment, O]
           )

    5. KQ[COMPANY]
       =
       INTERSECTION(
           all KQ[Organization]
       )
```
===========	   


Tôi đề nghị đi theo thứ tự sau:
1. Tạo 04_P1_Implementation_Plan_v1.0.md và Dev Backlog. Đây nên là artifact tiếp theo. Mỗi task phải trace được về UR -> FRS -> DBD -> Implementation Contract -> Test. Không đưa thêm business rule mới vào task. Nên chia work package thành Foundation/Schema, Authorization, Workflow, Auto Numbering, Product/UOM/Pricing/Combo, Import, API, UI.
2. Bắt đầu từ migration/schema + bootstrap seed. Implementation Contract đã khóa thứ tự đầu tiên là Migration / schema rồi Resource + Action + Workflow bootstrap seed.  DBD cũng đã khóa migration dependency order bắt đầu từ Shield prerequisites → Foundation → Organization/Contact → Employee structure → các domain tiếp theo.  Tôi khuyên milestone đầu tiên phải là fresh DB migrate được + existing DB forward migrate được + seed chạy idempotent.
3. Triển khai service theo dependency đã khóa, không làm UI trước:
   OrganizationService → EmployeeAssignmentService → AuthorizationService → LifecycleService → AutoNumberService → ProductRoleService → ProductUomService → PricingService → ComboExpansionService → ComboAllocationService → ImportService → API → UI. Đây chính là order đã được baseline.
4. Viết test song song với từng service, đặc biệt Authorization trước. Không nên đợi cuối P1 mới viết test. Các test quan trọng nhất ở giai đoạn đầu là: GROUP hierarchy, Primary/Secondary Assignment, System Admin no-bypass, denied-set intersection, inherited ABSOLUTE_DENY, authoritative Evaluation Organization, concurrent Auto Numbering, rollback counter, Product Import + generated code. Definition of Done yêu cầu automated tests bao phủ cả FRS Acceptance Test Catalog và contract tests.
5. Chia implementation thành các gate có thể nghiệm thu độc lập. Tôi đề nghị:
   Gate 1 Foundation DB, Gate 2 Authorization, Gate 3 Workflow + Auto Numbering, Gate 4 Product/UOM/Pricing/Combo, Gate 5 Product Import, Gate 6 API, Gate 7 UI/UAT. Không cho Gate sau “bypass tạm” invariant của Gate trước.
6. Dùng Definition of Done hiện tại làm release checklist P1. Đặc biệt phải chứng minh không có System Admin bypass, context không bị client override, seed idempotent, Auto Number atomic/concurrency-safe, Product product_code -> products.code đúng mapping, Import không bypass authorization và không tạo persistence ngoài DBD v1.9.
Nếu chọn đúng một việc để làm ngay, tôi chọn tạo 04_P1_Implementation_Plan_v1.0.md + backlog kỹ thuật chi tiết. Sau đó ticket đầu tiên mới là migration/schema.
Cấu trúc mỗi Dev Task tôi khuyên dùng:


** Sales Order lifecycle

```
Sales Order
│
├── lifecycle_state
│     DRAFT / CONFIRMED / PROCESSING / COMPLETED / CANCELLED
│
├── fulfillment summary
│     UNFULFILLED / PARTIALLY_FULFILLED / FULFILLED
│
├── payment summary
│     UNPAID / PARTIALLY_PAID / PAID / ...
│
├── Shipments [0..N]
│     └── delivery_status
│
├── COD Reconciliations [0..N]
│     └── reconciliation_status
│
├── Sales Returns [0..N]
│     └── return lifecycle
│
└── External Channel reference
      └── external_status / status history
```
Các file nằm trong mục “Nguồn” của project DinkERP.