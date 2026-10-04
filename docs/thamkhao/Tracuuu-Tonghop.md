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