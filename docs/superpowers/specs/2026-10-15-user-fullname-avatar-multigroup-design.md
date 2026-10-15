---
document_id: "CHANGE-USER-IDENTITY-FULLNAME-MULTIGROUP-001"
title: "ERP v1.1.4 — User Full Name, Avatar và Multi-Group Assignment"
version: "1.2-DRAFT-REVIEW"
status: "CHỜ PHÊ DUYỆT ĐẶC TẢ; CHƯA SỬA CODE SẢN PHẨM"
date: "2026-10-15"
baseline: "ERP v1.1.4 ZIP; người dùng xác nhận vue-tsc đạt, Vitest Employee User Lookup 4/4, Playwright 252/252 sau các patch trước"
supersedes: "ERP_v1.1.4_SECURITY_USER_FULLNAME_MULTI_GROUP_SPEC_v1.1_2026-10-15.md"
related_change_control: "CHANGE-USER-IDENTITY-MUTATION-001"
---

# Đặc tả thiết kế kỹ thuật v1.2 — User Full Name, Avatar & Multi-Group

> **Trạng thái:** Bản thiết kế đề nghị chủ dự án duyệt. Đây **không phải** mã nguồn, migration, patch hoặc chứng nhận kiểm thử tính năng mới. Toàn bộ cấu trúc/endpoint mới bên dưới là **đề xuất triển khai**, không phải chức năng đã có.
>
> **Thay đổi so với v1.1:** **hủy** quy ước ghi Họ và tên vào `auth_identities.name`; **chốt** `users.full_name`, `users.avatar_filename` và thêm nghiệp vụ quản lý file Avatar. Group tiếp tục chọn 0..n, không bắt buộc.

## 0. Quyết định đã chốt và mục tiêu

### 0.1 Quyết định nghiệp vụ

1. Tiếp tục sử dụng duy nhất `POST /api/v1/users` để **tạo** tài khoản và `PATCH /api/v1/users/{id}` để **sửa** thuộc tính tài khoản; không tạo endpoint đăng ký User thứ hai.
2. CodeIgniter Shield tiếp tục quản lý `users`, `auth_identities`, `auth_groups_users`, `auth_permissions_users`, đăng nhập, mật khẩu và session. Mở rộng cấu trúc `users` bằng **migration ứng dụng**, không sửa code/migration vendor.
3. `full_name` thuộc **User**, lưu trong `users.full_name`, **không** ghi vào `auth_identities.name`.
4. Avatar là tùy chọn, lưu **khóa tên tệp** trong `users.avatar_filename`; dữ liệu ảnh nằm ở storage riêng tư, không trong DB.
5. Một User có thể thuộc **0..n** Authorization Groups; không chọn Group vẫn được tạo; không gán nhóm mặc định và không tự cấp direct permissions.
6. **Form Create mới** bắt buộc nhập Họ và tên. **API POST** tiếp tục chấp nhận các request legacy chỉ chứa bốn trường bắt buộc cũ nhằm bảo toàn backward compatibility; tài khoản cũ/legacy được phép `full_name = NULL`.
7. **Không tự động đồng bộ** với Employee/Contact; User, Employee và Contact có vòng đời riêng. Không tạo/sửa Employee khi thay tên hay avatar của User.
8. Quyền `user.create`, `user.edit`, `user.view` và `authorization_group.assign_member` tiếp tục được kiểm soát ở backend qua PBAC; không suy quyền gán Group từ quyền tạo User.
9. Upload/đọc/xóa Avatar dùng các endpoint **tài nguyên ảnh** mới; đây không phải endpoint tạo tài khoản và không làm thay đổi JSON contract hiện tại của `POST /users`.

### 0.2 Thành công được đo bằng

- Create/PATCH lưu và đọc `name` từ `users.full_name`; không tác động tới email identity, password hash, tokens.
- Group được gán đúng từng mục được phép, có rollback nguyên tử khi bất kỳ membership nào lỗi.
- Upload/thay/xóa Avatar an toàn và có thể phục hồi khi storage hoặc API lỗi; không cấp quyền truy cập ảnh trái phép.
- Danh sách `/security/users`, hồ sơ `/account/profile`, dữ liệu `/auth/me`/login và SDK hiển thị thống nhất.
- Tất cả test hiện hữu vẫn đạt; regression tests mới chứng minh các điều kiện vừa bổ sung.

### 0.3 Ngoài phạm vi

Không bổ sung `display_name`, `contact_phone`, địa chỉ, ngày sinh, department/position; không chuyển User thành Employee; không tạo bảng `user_profiles`; không thay group schema; không thêm đăng ký công khai; không đổi giao diện khác không liên quan.

## 1. Đối chiếu source ERP v1.1.4 hiện tại

Đã kiểm tra trực tiếp bản ZIP:

| Nguồn | Hiện trạng | Hệ quả cho thiết kế |
|---|---|---|
| `backend/app/Config/Routes.php` | Có POST `/users`, PATCH `/users/{id}`, GET `/security/users`; chưa có API avatar User | Bổ sung đúng 3 route tài nguyên Avatar bên cạnh, không thay route tài khoản |
| `backend/app/Services/ShieldUserIdentityService.php` | Create allowlist chỉ `username,email,password,state`, PATCH chỉ `username,email,row_version` | Mở rộng allowlist + validate `name`, `group_ids` của POST; PATCH không nhận `group_ids` |
| `backend/app/Config/Auth.php` | `$userProvider` trỏ model Shield vendor | Kế thừa UserModel tại App, đổi provider và kiểm kê các chỗ gọi model vendor trực tiếp |
| `backend/app/Repositories/ShieldSecurityUserRepository.php` | Read model dùng `ai.name AS name`, email từ `ai.secret` | Chuyển `name` sang `u.full_name`; vẫn lấy email từ identity, giữ nhóm/direct permissions |
| `backend/app/Services/AuthorizationGroupService.php` | Có `assignMember(groupId,userId)`, PBAC & ACTIVE checks | Dùng nghiệp vụ thêm từng Group trong transaction; không gọi replace-all |
| `backend/app/Config/AuthGroups.php` | `defaultGroup = 'user'` trong cấu hình Shield; vendor có `UserModel::addToDefaultGroup()` dùng ở luồng registration | **Không giả định** create qua ERP tự gán hay không; integration test xác nhận User được tạo qua POST này thật sự có **0 Group** khi `group_ids` rỗng |
| `backend/app/Services/ProductImageStorage.php` / `ProductImages.php` | Có mẫu upload ảnh sản phẩm, file private trong `WRITEPATH` | Tham khảo pattern an toàn, nhưng **tách** User avatar storage, quyền và error code |
| `frontend/src/features/security/pages/SecurityUsersView.vue` | Form chưa có Họ và tên, avatar hay multi-select Group lúc Create | Bổ sung trường/validation/picker và trạng thái upload |
| `frontend/src/features/account/pages/ProfileView.vue` | Avatar hiện là chữ đầu username, form cá nhân chỉ username/email/password | Hiển thị avatar thật và Họ và tên, có self-service phù hợp |
| `backend/app/Controllers/Api/Auth.php` | `/auth/me`, login, profile chưa trả `name`/`has_avatar` | Mở rộng payload additive, không làm đổi auth/session/permission semantics |
| `contracts/openapi/openapi.yaml` | `UserIdentityCreate`, `UserIdentityPatch`, `SecurityUser` chưa có các thuộc tính mới | Cập nhật contract và regenerate SDK |
| `backend/app/Database/Migrations/*000017*` | Migration ứng dụng mới nhất trong ZIP là `000017` | Đề xuất migration kế tiếp `2026-10-15-000018_*`, kiểm tra lại trước khi áp dụng trên branch mới |

**Giới hạn baseline:** Bản ZIP v1.1.4 không chứa những patch User ID picker đã chạy trên máy Windows; spec dựa trên API/kiến trúc của ZIP và checkpoint Playwright **252/252 theo log người dùng**, không khẳng định có source mới nhất của máy người dùng.

## 2. Data model / migration

### 2.1 Bổ sung vào bảng Shield `users`

| Cột mới | Kiểu đề xuất | NULL | Ý nghĩa |
|---|---|---|---|
| `full_name` | `VARCHAR(255)` | Có | Họ và tên chính thức để hiển thị; không là khóa xác thực |
| `avatar_filename` | `VARCHAR(128)` | Có | Filename ngẫu nhiên do server tạo; không phải URL, đường dẫn hoặc dữ liệu nhị phân |

- Dùng bảng được cấu hình tại `config('Auth')->tables['users']`, có xét prefix kết nối; không hardcode giả định mọi môi trường tên bảng giống nhau.
- Không tạo chỉ mục cho hai cột mới khi chưa có nhu cầu truy vấn và số liệu chứng minh. `full_name` có thể sort/filter trong read model theo cơ chế hiện tại; đánh giá index sau khi kiểm tra kế hoạch query và cardinality trên staging.
- Giá trị `full_name` giữ được Unicode tiếng Việt, được chuẩn hóa cắt khoảng trắng đầu/cuối và validate <=255 ký tự Unicode. Không tạo tên giả từ username/email/Employee.
- `avatar_filename` chỉ chứa tên tệp server-generated định dạng đã được kiểm tra; **không bao giờ** nhận giá trị này trực tiếp từ JSON request.
- Không thêm `avatar_mime` hay `avatar_size` vào bảng `users` ở v1.2: dịch vụ storage xác định định dạng từ tên file/metadata tệp an toàn đã ghi, kiểm chứng nội dung khi ingest. Nếu sau này có nhu cầu thống kê/audit ảnh mới thiết kế thêm metadata.

### 2.2 Migration và dữ liệu tồn tại

- Migration **additive** thêm hai cột nullable, không đặt default tên/avatar giả, không chạm các cột Shield hiện có.
- Với User cũ: `full_name=NULL`, `avatar_filename=NULL`; UI dùng username/initials chỉ ở tầng **fallback hiển thị**.
- Không backfill từ `auth_identities.name`: cột đó có ý nghĩa identity/token khác nhau và không là nguồn họ tên đáng tin cậy.
- Không backfill từ Employee hoặc Contact, kể cả nếu có liên kết `user_id`.
- `down()` phải từ chối drop nếu còn bất cứ `full_name` hoặc `avatar_filename` không NULL; bảo vệ dữ liệu và yêu cầu backup/procedure thủ công cho downgrade production.
- Preflight trên bản sao DB: xác minh tên bảng, prefix, collation/utf8mb4, số User hiện tại, thời gian migration và backup đã kiểm tra khôi phục.

### 2.3 Mở rộng Shield đúng cách

- Tạo `App\Models\UserModel` **kế thừa** `CodeIgniter\Shield\Models\UserModel`, mở rộng `$allowedFields` để cho phép `full_name`, `avatar_filename`, giữ cấu hình Entity và event/hook Shield.
- Chuyển `Config\Auth::$userProvider` sang `App\Models\UserModel::class`.
- Rà soát và chuyển các đoạn gọi trực tiếp `model(CodeIgniter\Shield\Models\UserModel::class)` (đặc biệt `ShieldUserIdentityService`, `Controllers\Api\Auth`) sang provider đã cấu hình, đồng nhất connection; không tạo hai UserModel hoạt động song song với hành vi khác nhau.
- **Không chỉnh sửa** file trong `backend/vendor/`. Chạy thử login/logout, email update, set password, reset password, sessions, groups/tokens sau khi đổi provider.
- Xác nhận Shield Entity nhận và lưu được hai field bổ sung theo cách CodeIgniter định nghĩa; không giả định chỉ có `$allowedFields` là đủ cho mọi custom Entity. Kiểm thử đọc/ghi DB thật sẽ khóa lại mapping này.

## 3. UX và validation

### 3.1 `/security/users` — Create

Thứ tự hiển thị đề xuất: **Username → Họ và tên → Email → Password → State → Group → Avatar (tùy chọn)**.

- `name` (label **Họ và tên**): bắt buộc ở Create UI; string trim, không whitespace-only, tối đa 255 Unicode; error ngay trên trường.
- `group_ids`: multi-select searchable/filterable dùng `SearchableSelect` đã có, tìm tên/mã, nhãn `Tên nhóm (code)`; trạng thái mặc định `[]`; nhiều Group được thể hiện bằng chip có thể bỏ riêng.
- Chỉ gợi ý những Group ACTIVE đọc được. Frontend có thể ẩn/disable picker khi không đủ quyền, nhưng backend là nơi quyết định việc gán nhóm cụ thể.
- Nếu Group lookup lỗi: hiển thị Retry, không xóa draft; người có `user.create` vẫn có thể tạo với `group_ids:[]`. Nếu chưa tải Group, không gửi IDs suy đoán.
- Avatar: chọn một tệp tùy chọn, preview cục bộ, xóa lựa chọn trước submit. Không bắt buộc upload. Nếu actor không có quyền sửa avatar trên User vừa tạo, UI phải giải thích rằng có thể bổ sung sau bởi người có quyền hoặc User tự quản lý hồ sơ.
- Save chống double-submit; lỗi 4xx/5xx ở POST giữ draft; chỉ POST thành công mới chuyển sang bước upload Avatar (nếu có file).

**Hai bước xử lý khi Create có Avatar:**

1. `POST /api/v1/users` tạo **User + tên + groups** thành công và trả `id`.
2. `PUT /api/v1/users/{id}/avatar` gửi multipart file (khi actor được phép). Thành công thì cập nhật avatar và refresh list/profile.

Nếu bước 2 lỗi, **User vẫn đã được tạo**. Giao diện phải hiển thị rõ **“Đã tạo User nhưng chưa tải được ảnh; thử tải lại”**, lưu `id` vừa tạo và chỉ retry upload, **tuyệt đối không tự gọi lại POST tạo User**. Không quảng cáo đây là thao tác nguyên tử trên DB+filesystem.

### 3.2 `/security/users` — Edit/Detail

- Edit cho phép thay Họ và tên; không ép tài khoản legacy đang thiếu tên phải nhập tên khi chỉ thay username/email, nhưng nếu có nhập tên thì phải hợp lệ.
- Avatar có preview, upload/thay/xóa với kiểm soát quyền riêng. Thay ảnh thành công phải refresh hiển thị header/detail/list.
- Group cũ được hiển thị ở tab Group; không cho sửa Group bằng `PATCH /users/{id}`. Quản lý membership sau tạo giữ API/quy trình Group hiện có.
- Không để nút upload ảnh hiện enabled nếu backend sẽ luôn từ chối do thiếu quyền đã biết; vẫn phải xử lý 403 do policy target-specific/concurrent state.

### 3.3 `/account/profile` — self-service

- Hiển thị `full_name` và avatar thật; fallback ký tự đầu từ tên hoặc username khi chưa có avatar.
- Cho phép chủ tài khoản sửa Họ và tên qua **endpoint profile hiện có** `PATCH /api/v1/auth/profile` (mở rộng additive `name` tùy chọn) và tải/xóa avatar của **chính mình** qua API Avatar có đối chiếu user ID server-side.
- Self-service **không** cho sửa Group, trạng thái, direct permissions hoặc avatar của người khác.
- API `/auth/me`, login và profile response bổ sung `name: string|null`, `has_avatar: boolean`; auth store dùng những thuộc tính đó để hiển thị đồng bộ sau login/logout/reload. Không dùng localStorage lưu binary avatar.
- Nếu policy sản phẩm sau này muốn quản trị viên độc quyền thay Họ và tên, phải chốt change-control riêng cho self-service; v1.2 đề xuất **cho phép self-edit name** nhất quán với profile cá nhân hiện có.

## 4. API contract — JSON tạo/sửa User

### 4.1 `POST /api/v1/users` — giữ endpoint, mở rộng field

Ví dụ có hai nhóm:

```json
{
  "username": "nguyenvana",
  "name": "Nguyễn Văn A",
  "email": "vana@example.com",
  "password": "ExamplePassword123!",
  "state": "ACTIVE",
  "group_ids": ["2", "4"]
}
```

- Bốn trường legacy `username,email,password,state` **giữ nguyên required** ở API.
- `name`: **optional trong API**, required trong Create UI v1.2; nếu gửi phải là string non-blank, <=255 Unicode, không nhận `null`; lưu tại `users.full_name`.
- `group_ids`: **optional**; bỏ field hoặc `[]` có nghĩa là không gán nhóm nào; nếu có phải là danh sách các **ID canonical dạng chuỗi số nguyên dương**, không trùng; từ chối `null`, scalar, ID lạ, ID inactive hoặc actor không được phép gán.
- Không nhận `avatar_filename`, URL ảnh, `groups` dạng tên hay file nhị phân trong JSON create.
- Kết quả HTTP 201/envelope giữ theo cấu trúc hiện tại; mở rộng `UserIdentity` bằng `name: string|null`, `has_avatar: boolean`; mọi group hiển thị trong read model phải phản ánh membership **đã commit**.

### 4.2 `PATCH /api/v1/users/{id}` — giữ endpoint

- Giữ `username`, `email`, `row_version`; thêm optional `name`.
- `name` **không có mặt** nghĩa là giữ nguyên; `name` là nonblank string hợp lệ nghĩa là cập nhật; `name:null` hoặc chuỗi toàn whitespace bị từ chối. Không hỗ trợ xóa tên trong phạm vi v1.2.
- `group_ids`, `avatar_filename`, `avatar_url` không được phép trong PATCH để tránh lách quyền membership/file.
- `PATCH` chỉ tên không làm thay email/password; `PATCH` email + tên phải cùng transaction DB.
- **Không** thay logic trạng thái, reset password và trực tiếp gán permissions hiện tại.

### 4.3 Read models và phiên đăng nhập

- `GET /api/v1/security/users`: `name` đọc từ **`users.full_name`**, `email` vẫn từ `auth_identities.secret` của `email_password` với join có guard; thêm `has_avatar: boolean`. Giữ nhóm/permission count như cũ.
- `POST /users`, `PATCH /users/{id}`: `UserIdentity` thêm `name` và `has_avatar`; không trả `avatar_filename` hay đường dẫn storage.
- `GET /api/v1/auth/me`, login, `PATCH /api/v1/auth/profile`: payload `user` thêm `name` và `has_avatar` **mà không thay** `groups`, `base_permissions`, session hoặc token semantics.
- `GET /api/v1/authorization-groups` không thay đổi.
- Đồng bộ OpenAPI request bodies, schemas, response/error definitions và **regenerate** TypeScript SDK/Zod; không chỉnh tay generated sources.

## 5. Avatar APIs — đề xuất thêm tài nguyên ảnh, không thêm Create User

| Method | Route mới | Request/response | Quyền |
|---|---|---|---|
| `GET` | `/api/v1/users/{id}/avatar` | Binary JPEG/PNG/WebP; 404 khi chưa có ảnh | Chính User hoặc `user.view` trên User mục tiêu |
| `PUT` | `/api/v1/users/{id}/avatar` | `multipart/form-data` field `image`; 200 JSON `data: { user_id, has_avatar:true }` | Chính User hoặc `user.edit` trên mục tiêu; chỉ scope Avatar |
| `DELETE` | `/api/v1/users/{id}/avatar` | 200 JSON `data: { user_id, has_avatar:false }` | Chính User hoặc `user.edit` trên mục tiêu; chỉ scope Avatar |

- Mọi request cần session hợp lệ, CSRF cho PUT/DELETE theo middleware hiện hành, kiểm tra visibility/PBAC theo target ở backend. "Chính User" là so ID **từ session** với `{id}`; không nhận owner từ body. Self-avatar exception **không** trở thành quyền `user.edit` tổng quát.
- Không cung cấp URL public dưới `public/` và không tạo static route bypass authorization. Không để GET private avatar bị cache chia sẻ. Trả `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`, `Content-Disposition: inline`, MIME chuẩn, tránh lộ đường dẫn nội bộ.
- Khi User tồn tại nhưng ảnh null hoặc file mất: `404` có mã lỗi được chuẩn hóa; unauthorized target phải tuân thủ quy tắc 403/404 hiện hành để tránh user enumeration.
- `GET` luôn kiểm tra quyền trước đọc file. `PUT`/`DELETE` kiểm tra quyền **trước** thông báo lỗi MIME/size/content, tránh rò sự tồn tại User.
- Có thể thêm nút retry chỉ cho thao tác Upload sau Create; không cần endpoint `POST /users` thứ hai hay API chèn filename từ client.

### 5.1 Tiêu chuẩn file Avatar (proposed v1.2)

| Tiêu chí | Quy tắc |
|---|---|
| MIME | `image/jpeg`, `image/png`, `image/webp` |
| Dung lượng tối đa | **2 MiB** (2 × 1024 × 1024 bytes) |
| Kích thước ảnh | Dương và không vượt **2048 × 2048 px** |
| Đầu vào bị cấm | SVG, GIF/animation, file rỗng, mismatch MIME/ảnh, executable/polyglot không qua decoder, link/URL từ client |
| Tên file | Random server-generated, ví dụ 40 hex + extension chuẩn; không dùng original filename |
| Storage | `WRITEPATH/uploads/user-avatars/` không public; permission directory/file giới hạn |
| Tải ảnh | Binary sau khi authorize; không có public static URL |
| Xử lý ảnh | Giải mã và **re-encode** bằng image library đã xác minh để loại metadata EXIF/đầu vào không tin cậy. Kiểm tra PHP GD/codec hỗ trợ trong môi trường XAMPP/production trước triển khai; nếu thiếu, không bỏ bước xử lý an toàn chỉ để cho upload chạy |

- Chỉ tin file thật (`finfo`, decoder, kích thước pixel, nội dung), không tin extension hay `Content-Type` người dùng gửi.
- Ngăn path traversal bằng allowlist kiểm tra filename khi **đọc/xóa**; mọi thao tác file được đóng gói trong `UserAvatarStorage` riêng.
- Khi upload: lưu file mới riêng trước; cập nhật `users.avatar_filename` dưới transaction/row-safe update; nếu DB fail thì xóa file mới; sau commit mới cố xóa file cũ. Nếu xóa file cũ thất bại, log kỹ thuật và dọn orphan định kỳ mà không xóa file đang được tham chiếu.
- Khi DELETE: cập nhật DB `avatar_filename=NULL` trước, sau commit mới xóa file cũ; lỗi dọn file không làm phục hồi nhầm metadata.
- Upload đồng thời: dùng row lock hoặc conditional update/expected old filename để tránh đua khiến metadata trỏ tệp đã bị xóa. Có DB integrity tests chứng minh một file có thể đọc sau thao tác cuối cùng.
- Thất bại filesystem và DB không thể rollback nguyên tử tuyệt đối; **orphan cleanup** chỉ chạy với các tệp không còn được bảng User tham chiếu sau khoảng bảo lưu an toàn.
- Không log file contents, tên người dùng, email hoặc metadata EXIF; audit có thể ghi actor ID, target ID, action, timestamp, request ID.

## 6. PBAC và gán nhiều Authorization Groups

### 6.1 Ma trận quyền

| Hành động | Điều kiện cấp quyền |
|---|---|
| Tạo User không có Group | `user.create` |
| Tạo User có 1..n Group | `user.create` **và** `authorization_group.assign_member` trên **từng** Group được chọn |
| Đọc danh sách User | `user.view` theo policy/scope |
| Sửa Họ tên User khác | `user.edit` sau visibility/target check |
| Xem Avatar User khác | `user.view` theo target, hoặc self |
| Thay/xóa Avatar User khác | `user.edit` theo target, hoặc self |
| Sửa profile Họ và tên của chính mình | Session owner qua `/auth/profile` hiện hành; không sửa Group/quyền |
| Sửa Group sau khi tạo | Nghiệp vụ membership Group hiện tại, **không** qua PATCH User |

- Quyền self-avatar/self-profile phải được xử lý cụ thể cho operation đó, không cho payload tùy ý override actor/scope.
- PBAC deny/ABSOLUTE_DENY giữ nguyên; không dựa vào danh sách đã hiển thị ở frontend để cấp quyền.
- Chỉ duyệt `group_ids` từ danh sách canonical và xác minh `ACTIVE`, tồn tại, được nhìn thấy/được phép gán. Không gọi `replaceMembers()`, vì sẽ xóa danh sách thành viên hiện tại của Group.

### 6.2 Quy trình POST nguyên tử trên database

1. Validate payload và quyền `user.create`, tính uniqueness, password policy, canonical Group IDs.
2. Nếu Group không rỗng, preflight từng Group (visible, ACTIVE, `assign_member`), nhưng **vẫn recheck phù hợp trong transaction** để chống thay đổi trạng thái đồng thời.
3. Mở DB transaction dùng **một connection** nhất quán cho Shield `UserModel`, auth identity và Group adapter/repository.
4. Tạo Shield User (`username`, email/password identity, state), lưu `users.full_name` nếu payload có `name`.
5. Lần lượt gán membership với `AuthorizationGroupService::assignMember()` (không replace-all), đảm bảo state/quyền từng Group vẫn hợp lệ.
6. Nếu `group_ids=[]`/bỏ field, khẳng định **không có membership được thêm** từ pipeline ERP và kiểm tra thực tế hiệu ứng `AuthGroups.defaultGroup` của Shield; không gọi `addToDefaultGroup()` trong create path này. Nếu mọi thao tác hoàn tất thì commit, trả HTTP 201; nếu bất kỳ bước nào lỗi thì rollback **User + identity + full_name + tất cả group memberships**. Không để lại tài khoản dở dang.
7. Avatar là **operation riêng sau commit** và không nằm trong giao dịch này.

**Điều kiện bắt buộc phải chứng minh:** `ShieldUserIdentityService::transaction()` hiện mở transaction trên `$this->db`, trong khi create hiện gọi `model(UserModel::class)`. Cần integration test bảo đảm các model và adapter sử dụng cùng connection thực. Nếu không, phải sửa dependency wiring trước khi coi rollback là an toàn. Không được chỉ test bằng mock.

### 6.3 Quy trình PATCH, tranh chấp và client cũ

- PATCH chỉ đổi `name` được phép, không thay `secret`, `secret2`, token, groups hay permission.
- PATCH đổi `email` và `name` trong transaction; cả hai update rollback cùng nhau nếu lỗi.
- Legacy client POST không gửi `name`/`group_ids` vẫn tạo được User thiếu tên/chưa có Group; user cũ chưa có tên vẫn được sửa trường khác.
- Duplicate username/email trong concurrent create được unique constraints + Shield quyết định; API trả mã lỗi 409 phù hợp mapper.
- Hai thao tác đồng thời không được tự ý ghi đè ảnh hoặc thay Group ngoài phạm vi requested.

## 7. Validation và error contract

| Trường hợp | Status đề xuất | Hành vi |
|---|---|---|
| Chưa đăng nhập | 401 | Không đọc/ghi Avatar/User |
| Không có `user.create` hoặc quyền Group | 403 | Không tạo tài khoản, không có partial DB success |
| Group/User không tồn tại hoặc không visible | 404/422 theo mapper hiện hành | Không rò thông tin bị ẩn |
| Họ và tên trống/toàn whitespace/quá 255 | 422 | Lỗi gắn vào `name` |
| `group_ids` null/scalar/duplicate/inactive/invalid | 422 (hoặc 403 khi denied) | Không gán một phần |
| Username/email trùng | 409 | Không tạo User trùng |
| Không đúng MIME/file > 2 MiB/kích thước không phù hợp | 422 | Không sửa metadata Avatar hiện có |
| User có avatar_filename nhưng file thực tế mất | 404 | Không trả đường dẫn nội bộ |
| Lỗi storage hoặc database | 500 theo mapper | Dọn file mới/orphan đúng; không leak PHP exception |
| User tạo thành công nhưng upload Avatar lỗi | POST 201 đã hoàn tất, PUT riêng lỗi | UI nêu rõ User đã tạo, chỉ retry upload |

Mã lỗi `USER_AVATAR_INVALID_SIZE`, `USER_AVATAR_INVALID_TYPE`, `USER_AVATAR_INVALID_CONTENT`, `USER_AVATAR_NOT_FOUND`, `USER_AVATAR_STORAGE_FAILED` ở đây là **mã mới đề xuất**, phải kiểm tra tránh trùng và bổ sung mapper/i18n/OpenAPI. Tất cả response dùng chuẩn `ApiTransport` hiện tại; lỗi authorization không tiết lộ nhóm/tài khoản ngoài phạm vi được nhìn thấy.

## 8. Phạm vi file dự kiến thay đổi khi được duyệt triển khai

### Backend/Database

- `backend/app/Database/Migrations/2026-10-15-000018_AddShieldUserDisplayFields.php` (tên đề xuất, tránh trùng version thực tế): thêm 2 cột nullable vào Shield users.
- `backend/app/Models/UserModel.php` (mới): kế thừa Shield UserModel, mở rộng `$allowedFields`.
- `backend/app/Config/Auth.php`: đổi `$userProvider`.
- `backend/app/Services/ShieldUserIdentityService.php`: POST/PATCH fields, normalizations, cùng-connection transaction, gán nhiều Group, response `name`/`has_avatar`.
- `backend/app/Repositories/ShieldSecurityUserRepository.php`: `u.full_name AS name`, `has_avatar` từ cột metadata; giữ email identity join.
- `backend/app/Controllers/Api/Auth.php`: `/auth/me`, login, `/auth/profile` hỗ trợ name/has_avatar; dùng provider mới.
- `backend/app/Config/Routes.php`: 3 route User Avatar; thêm controller chuyên dụng (dự kiến `Controllers/Api/UserAvatars.php`).
- Service/repository tách biệt cho ảnh, ví dụ `UserAvatarService.php`, `UserAvatarStorage.php`; tái sử dụng hướng kiểm tra file Product nhưng **không** dùng Product permission.
- `AuthorizationGroupService.php` và `ShieldIdentityAdapter.php`: tái sử dụng không làm đổi semantics; chỉ chỉnh dependency wiring nếu cần kiểm chứng transaction.
- `ApiErrorMapper`/bản dịch backend tương ứng mã lỗi mới, các integration tests.

### Frontend/API contract

- `frontend/src/features/security/pages/SecurityUsersView.vue`: Create/Edit/Detail name, Group picker 0..n, Avatar preview/upload state.
- `frontend/src/features/account/pages/ProfileView.vue`, `frontend/src/services/auth.ts`, `frontend/src/stores/auth.ts`: full name & avatar self-service/sync.
- `frontend/src/services/security.ts`: request/response mapping; service upload/get/delete Avatar riêng (nếu cần).
- `frontend/src/i18n/vi.ts`, `frontend/src/i18n/en.ts`: labels, help text, errors.
- `contracts/openapi/openapi.yaml`: POST/PATCH schemas + 3 Avatar binary operations + AuthProfile schema + SecurityUser/UserIdentity/AuthUser schemas.
- `frontend/src/api/generated/*`: **tự sinh lại** bằng script cấu hình `openapi-ts.config.ts`, không sửa tay.
- `backend/tests/*`, `frontend/src/**/__tests__/*`, `frontend/e2e/fe030-security.spec.ts`, các E2E Auth/Profile/Avatar liên quan.
- As-built/change-control docs sau khi triển khai; không chỉnh sửa tài liệu hiện có ngoài phạm vi quyết định khi chưa chốt.

## 9. Test plan và tiêu chí nghiệm thu

### 9.1 Migration/Shield/PHPUnit

1. Migration lên trên bản copy DB có User hiện có; hai cột NULL; rollback chỉ cho khi không còn dữ liệu mới.
2. App UserModel tạo/đọc/sửa `full_name`,`avatar_filename` an toàn, không mất hook Identity của Shield.
3. POST legacy bốn trường vẫn 201, `name:null`, `has_avatar:false`, không có Group; không yêu cầu `assign_member`. Assert DB `auth_groups_users` không có membership mặc định **mặc dù** `Config\AuthGroups::$defaultGroup = 'user'`; nếu xuất hiện thì sửa create path cho đúng quyết định no-default, không âm thầm đổi cấu hình registration của toàn hệ thống.
4. POST `name` + 0 Group và POST với hai ACTIVE Group gán đúng, không xóa membership người khác.
5. Một Group không tồn tại/INACTIVE/actor denied → không có User/identity/membership dở dang.
6. Inject failure ở lần gán membership thứ hai → DB rollback thật qua **cùng connection**.
7. PATCH chỉ name, PATCH email+name, legacy PATCH không name, group_ids bị từ chối; email/password hash/token không bị thay khi sửa tên.
8. `/auth/me`, login, profile, logout, reset password, set-state, permission snapshot vẫn hoạt động khi đổi UserModel.
9. Read list filter/sort/pagination theo `name` từ `u.full_name`, không bị nhân bản User bởi identity JOIN.
10. User/Employee/Contact không sinh ra hoạt động đồng bộ ngoài ý muốn.

### 9.2 Avatar backend/security

1. PUT JPEG/PNG/WebP chuẩn (bao gồm ảnh thật có metadata) → lưu file riêng tư, key ngẫu nhiên; GET chỉ cho self/authorized viewer; DELETE chỉ cho self/authorized editor.
2. Không upload được SVG/GIF/text, file rỗng, >2 MiB, >2048×2048, đổi extension giả, file ảnh hỏng hoặc decoder lỗi.
3. Unauthorized request bị chặn trước khi đọc/ghi; private/no-store/nosniff headers đúng; không lộ avatar_filename trên JSON.
4. Upload/replace/delete bảo toàn file cũ nếu DB update fail; xóa file mới nếu lỗi; kiểm tra orphan cleanup không xóa file còn reference.
5. Race giữa hai upload hoặc upload/delete không để DB tham chiếu file đã bị xóa.
6. Ảnh chưa có/mất file trả 404 thống nhất; tự đổi avatar không mở quyền sửa User khác.
7. MIME/magic bytes/re-encode loại EXIF; kiểm tra GD/codec trên XAMPP và staging/production.

### 9.3 Frontend/Vitest/Playwright

1. Họ và tên bắt buộc ở Create UI, không bắt buộc ở legacy PATCH thiếu name; Unicode giữ nguyên.
2. Multi-group tìm theo tên/code qua nhiều trang, có thể chọn hai và bỏ hết, create không Group vẫn được.
3. Thiếu group permission, lookup lỗi, API 401/403/409/422 vẫn có trạng thái hữu ích, không mất draft.
4. Create có avatar chạy POST rồi PUT, không gọi POST lại khi PUT thất bại; hiển thị User đã tạo rõ ràng.
5. Edit/Detail hiển thị `name`, fallback avatar; thay/xóa ảnh; self-profile đúng dữ liệu auth store.
6. Accessibility: avatar alt/name, multi-select keyboard, dialog focus/ARIA; không làm hỏng component modal chuẩn.
7. Full regression trên Chromium/Firefox/WebKit; chạy `vue-tsc --noEmit`, Vitest, PHP lint/PHPUnit và contract tests.

### 9.4 Definition of Done

- Chỉ có **một** API Create User; `POST /users`/`PATCH /users/{id}` giữ tương thích client cũ.
- Họ và tên được ghi/đọc từ `users.full_name` **không đụng `auth_identities.name`**.
- Avatar metadata ở `users.avatar_filename`, file private dưới WRITEPATH, phân quyền đọc/ghi/sở hữu rõ; không lộ storage filename qua API.
- User/identity/groups nguyên tử ở DB; Avatar hai bước với lỗi/retry minh bạch, không gây duplicate User.
- Nhóm 0..n tùy chọn, không cấp quyền mặc định, không có write vượt PBAC/replace-all membership.
- User và Employee/Contact độc lập; không phát sinh dữ liệu nhân sự khi tạo tài khoản.
- OpenAPI, generated SDK, i18n, backend và frontend khớp contract; không có lỗi regression sau khi triển khai.
- **Checkpoint 252/252 Playwright là log người dùng cho baseline trước tính năng**; chưa có kết quả test cho spec mới và không được khẳng định đạt trước khi chạy.

## 10. Kế hoạch triển khai và rollback ở mức nguyên tắc (chưa phải implementation plan)

1. **Preflight:** backup + staging DB copy; xác minh Shield v1.4.1, GD/codec hỗ trợ JPEG/PNG/WebP, writable private storage, Git baseline đang chứa các patch đã chạy 252/252.
2. **Database & model:** migration nullable; app UserModel/provider; verify login/logout/password/profile trên DB thật trước thay đổi API business.
3. **Service + authorization:** mở rộng POST/PATCH, group assignment transaction, tests rollback thực.
4. **Avatar storage/API:** xử lý ảnh, quyền self/admin, lifecycle của file; test race/failure injection.
5. **OpenAPI/SDK:** bổ sung schemas, endpoints, lỗi; regenerate TypeScript/Zod và check diff.
6. **Frontend:** Create/Edit/Detail/Account Profile, multi-group picker, preview/retry, i18n/a11y.
7. **Regression:** PHP lint, PHPUnit, contract checks, `vue-tsc`, Vitest, Playwright toàn bộ; ghi số test thực tế.
8. **Release:** thử trên staging; kiểm tra backup/restore, quyền storage, CSRF, CORS; rollout theo quy trình ERP.
9. **Rollback:** rollback application có kiểm soát; giữ nguyên hai cột nullable và file đã upload để tránh mất dữ liệu; migration `down()` chỉ dùng khi cả hai cột không còn dữ liệu; orphan files được dọn sau khi không còn reference và hết thời gian bảo lưu.

## 11. Các giả định thiết kế cần người duyệt biết

1. V1.2 **đề xuất cho chủ tài khoản tự sửa Họ và tên và avatar** trong `/account/profile`. Nếu chủ dự án muốn chỉ quản trị viên có quyền sửa, cần quyết định lại ma trận ở §3.3/§5/§6 trước triển khai.
2. Upload Avatar trên Create là **hai request nối tiếp**. Trường hợp actor có `user.create` nhưng không có `user.edit` trên User vừa tạo, thao tác POST vẫn được phép; bước upload không được vượt PBAC. User có thể thêm ảnh sau qua self-profile.
3. Kho ảnh mặc định là private filesystem cục bộ dưới `WRITEPATH`. Nếu sau này scale nhiều app servers hoặc object storage, cần thiết kế adapter/storage chung; không giả định local filesystem được chia sẻ.
4. Hệ thống cần image decoder/re-encoder có hỗ trợ các format đã chọn. Phải kiểm tra extension môi trường trước triển khai; không âm thầm bỏ kiểm tra file vì thiếu thư viện.
5. Chưa xác nhận migration `000018` có bị branch trên máy người dùng chiếm hay không; số cuối phải kiểm tra lúc triển khai.
6. Shield vendor v1.4.1 tách `addToDefaultGroup()` khỏi `save()` trong UserModel, nhưng ERP vẫn phải khóa bằng test DB vì có cấu hình `AuthGroups.defaultGroup = user` và có thể tồn tại callback/custom registration khác. Tham chiếu đối chiếu: https://raw.githubusercontent.com/codeigniter4/shield/v1.4.1/src/Models/UserModel.php

---

**Yêu cầu phê duyệt:** Chủ dự án xem đặc tả v1.2 và xác nhận hoặc yêu cầu điều chỉnh. Sau khi đặc tả được phê duyệt, lập **implementation plan riêng**, trình duyệt kế hoạch và phương thức thực hiện trước khi sửa code. **Không có thay đổi code nào được cho phép bởi tài liệu dự thảo này.**
