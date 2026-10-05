---
document_id: "REQ-MINI-ERP-001"
title: "User Requirements - Dinktech Mini ERP"
version: "1.0.0-draft"
status: "draft"
owner: "Chủ sở hữu sản phẩm"
date: "2026-10-04"
---

# User Requirements - Dinktech Mini ERP

## 1. Mục đích tài liệu

Tài liệu này mô tả yêu cầu người dùng cho Dinktech Mini ERP theo hướng đơn giản, thực dụng và có thể triển khai theo từng nhóm nghiệp vụ.

Hệ thống phục vụ **một Group có nhiều Company**. Các master data chính được dùng chung trong Group khi phù hợp; mọi giao dịch nghiệp vụ phải xác định Company sở hữu.

Tài liệu này tập trung vào câu hỏi **"người dùng cần hệ thống làm được gì"**. Các nội dung chi tiết về database schema, API, UI component, deployment architecture và implementation design được xác định ở tài liệu downstream.

Baseline này thay thế định hướng ERP phức tạp trước đây bằng một mô hình Mini ERP, nhưng **giữ PBAC/Policy Engine cho Authorization** vì đây là capability được xác nhận cần thiết.

---

## 2. Phạm vi chức năng

Hệ thống gồm 14 nhóm chức năng:

1. Tổ chức & Cấu trúc doanh nghiệp
2. Nhân sự & Cấu trúc tổ chức
3. Phân quyền & Bảo mật
4. Quản lý tài liệu
5. Product
6. Customer & CRM
7. Pricing & Promotion
8. Supplier
9. Purchase
10. Inventory
11. Sales đa kênh & Vận chuyển
12. Chấm công & Quản lý tiền lương
13. BOM & Quản lý dữ liệu sản phẩm đơn giản
14. Vận hành tài chính

---

## 3. Nguyên tắc nghiệp vụ chung

### UR-CORE-001 - Một Group, nhiều Company
**Priority:** MUST

Hệ thống quản lý một Group duy nhất và nhiều Company thuộc Group đó.

**Acceptance criteria**
- Có thể tạo và quản lý nhiều Company trong cùng Group.
- Mỗi Company có mã, tên, thông tin pháp lý, trạng thái và thông tin liên hệ.
- Group không phải transaction owner trực tiếp.

### UR-CORE-002 - Transaction thuộc đúng một Company
**Priority:** MUST

Mỗi business transaction phải thuộc đúng một Company.

**Acceptance criteria**
- Purchase Order, Sales Order, Inventory Transaction, Shipment, Thu/Chi, Timesheet và Payroll đều xác định được Company sở hữu.
- Transaction của Company A không tự trở thành transaction của Company B.
- Báo cáo toàn Group chỉ là tổng hợp dữ liệu từ các Company mà người dùng có quyền xem.

### UR-CORE-003 - Master data dùng chung trong Group
**Priority:** MUST

Các master data như Product, Customer, Supplier, UOM và một số danh mục nền tảng được dùng chung trong Group khi phù hợp.

**Acceptance criteria**
- Nhiều Company có thể tham chiếu cùng Product/Customer/Supplier.
- Không cần nhân bản master chỉ để Company khác sử dụng.
- Dữ liệu giao dịch vẫn giữ đúng Company ownership.

---

# 4. Tổ chức & Cấu trúc doanh nghiệp

### UR-ORG-001 - Quản lý Company
**Priority:** MUST

Quản lý danh sách Company thuộc Group.

Thông tin tối thiểu:
- Company Code
- Tên Company
- Tên pháp lý
- Mã số thuế
- Địa chỉ
- Thông tin liên hệ
- Trạng thái hoạt động

**Acceptance criteria**
- Không phát sinh giao dịch mới cho Company đã ngừng hoạt động.
- Lịch sử giao dịch của Company ngừng hoạt động vẫn tra cứu được.

### UR-ORG-002 - Quản lý Branch, Location và Warehouse
**Priority:** MUST

Mỗi Company có thể có nhiều Branch, Location và Warehouse.

**Acceptance criteria**
- Mỗi Branch/Location/Warehouse xác định được Company sở hữu.
- Warehouse của Company A không được sử dụng như Warehouse nội bộ của Company B.
- Có thể kích hoạt/ngừng hoạt động từng đơn vị mà không làm mất dữ liệu lịch sử.

### UR-ORG-003 - Xem và tổng hợp dữ liệu theo Company
**Priority:** MUST

Người dùng có thể xem dữ liệu theo một hoặc nhiều Company trong phạm vi được PBAC cho phép.

**Acceptance criteria**
- Có thể lọc báo cáo theo Company.
- Có thể tổng hợp nhiều Company khi người dùng có quyền.
- Không hiển thị dữ liệu của Company ngoài authorization scope.

---

# 5. Nhân sự & Cấu trúc tổ chức

### UR-EMP-001 - Quản lý Employee
**Priority:** MUST

Quản lý hồ sơ Employee gồm tối thiểu:
- Employee Code
- Họ tên
- Thông tin cá nhân
- Thông tin liên hệ
- Ngày vào làm
- Trạng thái làm việc
- Quản lý trực tiếp

**Acceptance criteria**
- Employee nghỉ việc không bị xóa khỏi lịch sử.
- Có thể truy lại trạng thái và thông tin từng thời kỳ khi có thay đổi.

### UR-EMP-002 - Quản lý Department và Position
**Priority:** MUST

Quản lý:
- Department
- Cấu trúc Department
- Position/Chức vụ
- Người quản lý Department
- Danh sách Employee

**Acceptance criteria**
- Department thuộc một Company.
- Position có thể được dùng lại giữa nhiều Department/Company khi phù hợp.
- Department hoặc Position không tự tạo Permission.

### UR-EMP-003 - Employee Assignment
**Priority:** MUST

Employee Assignment biểu diễn việc Employee làm việc tại một Company/đơn vị trong một khoảng thời gian.

Assignment có thể gồm:
- Company
- Branch/Location
- Department
- Position
- Effective From/To

**Acceptance criteria**
- Một Employee có thể chuyển Company/Department/Position mà không tạo Employee mới.
- Lịch sử Assignment được giữ lại.
- Assignment đang hiệu lực là nguồn chính để resolve Company/organizational context của Employee cho PBAC.

---

# 6. Phân quyền & Bảo mật

## 6.1 Mô hình Authorization

Authorization sử dụng mô hình:

**User Identity -> Profile -> Permission(Resource/Action) -> Policy Rule -> Authorization Evaluation**

Profile cấp Permission cơ sở. Policy Rule chỉ giới hạn hoặc cho phép sử dụng Permission đã tồn tại trong context cụ thể; Policy không được tạo Permission mới.

### UR-IAM-001 - User Identity
**Priority:** MUST

Quản lý User Identity độc lập với Employee.

Thông tin chính:
- User ID
- Identity Type
- Authentication status
- Access status
- Employee reference khi áp dụng

**Acceptance criteria**
- User Locked/Inactive không thực hiện giao dịch mới.
- User nhân viên có thể liên kết với Employee.
- System Admin/Service Account có thể không liên kết Employee nhưng phải xác định rõ Identity Type.

### UR-IAM-002 - Permission theo Resource/Action
**Priority:** MUST

Permission là đơn vị quyền cơ sở theo cặp:

**Resource + Action**

Action baseline có thể gồm:
- VIEW
- CREATE
- EDIT
- SUBMIT
- APPROVE
- REJECT
- CONFIRM
- CANCEL
- ADJUST
- EXPORT
- ADMINISTER

**Acceptance criteria**
- Quyền VIEW không tự tạo quyền EDIT/APPROVE/EXPORT.
- Mỗi Permission có mã duy nhất và Resource/Action rõ ràng.

### UR-IAM-003 - Profile
**Priority:** MUST

Profile là tập hợp các Permission.

**Acceptance criteria**
- User có thể nhận một hoặc nhiều Profile.
- Permission hiệu lực là union của Permission từ các Profile có hiệu lực.
- Profile không bypass Policy Rule.

### UR-IAM-004 - Business Group
**Priority:** SHOULD

Business Group hỗ trợ gom User hoặc nhóm nghiệp vụ để gán Profile.

**Acceptance criteria**
- Business Group không phải Company.
- Business Group không phải Department.
- Business Group không tự tạo Permission.

### UR-IAM-005 - Policy Rule
**Priority:** MUST

Policy Rule giới hạn Permission theo context.

Baseline condition có thể gồm:
- Company
- Branch/Location/Warehouse
- Resource Type
- Resource State
- Participant
- Department
- Business condition
- Action

**Acceptance criteria**
- Policy chỉ được evaluate sau khi User có Permission cơ sở.
- Policy có thể giới hạn cùng một Permission theo Company hoặc trạng thái chứng từ.
- Policy không cấp thêm Permission chưa tồn tại trong Profile.

### UR-IAM-006 - Resolve Company Scope từ Employee Assignment
**Priority:** MUST

Khi Policy cần Company của Actor, hệ thống resolve từ Employee Assignment đang có hiệu lực.

**Acceptance criteria**
- User nhân viên mặc định được xác định Company context từ Assignment.
- Có thể cấp thêm cross-Company scope bằng Policy được cấu hình rõ.
- Quyền ở Company A không tự suy ra quyền ở Company B.

### UR-IAM-007 - Authorization theo loại nghiệp vụ
**Priority:** MUST

Policy có thể giới hạn Permission theo Resource Type.

Ví dụ:
- Purchase Order
- Goods Receipt
- Inventory Adjustment
- Sales Order
- Shipment
- Payroll
- Thu/Chi

**Acceptance criteria**
- Permission trên Purchase Order không tạo quyền trên Sales Order.
- Có thể áp dụng Policy riêng cho từng loại Resource.

### UR-IAM-008 - Authorization theo trạng thái
**Priority:** MUST

Policy có thể giới hạn Action theo Resource State.

Ví dụ:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- PROCESSING
- COMPLETED
- CANCELLED

**Acceptance criteria**
- Có thể cho phép EDIT ở DRAFT nhưng DENY ở APPROVED.
- Có thể cho phép APPROVE chỉ khi PENDING_APPROVAL.
- COMPLETED/CANCELLED có thể bị khóa Action thay đổi theo Policy.

### UR-IAM-009 - Policy Effect và Evaluation precedence
**Priority:** MUST

Policy Rule hỗ trợ:
- ABSOLUTE_DENY
- ALLOW
- DENY

Aggregation precedence:

**ABSOLUTE_DENY > ALLOW > DENY > NO_MATCH**

**Acceptance criteria**
- Không có Permission cơ sở thì kết quả luôn DENY.
- ABSOLUTE_DENY luôn thắng ALLOW/DENY thông thường.
- ALLOW thắng DENY thông thường khi không có ABSOLUTE_DENY.

### UR-IAM-010 - policy_required và NO_MATCH
**Priority:** MUST

Mỗi Permission có thuộc tính `policy_required`.

**Acceptance criteria**
- `policy_required=true` + NO_MATCH => DENY.
- `policy_required=false` + có Permission + NO_MATCH => ALLOW.
- Giá trị `policy_required` là metadata canonical của Permission, không phụ thuộc Profile.

### UR-IAM-011 - Policy Version và hiệu lực
**Priority:** MUST

Policy phải có:
- Policy ID
- Version
- Status
- Effective From
- Effective To khi áp dụng
- Owner
- Rule
- Effect

**Acceptance criteria**
- Có thể xác định Policy version có hiệu lực tại một thời điểm.
- Thay đổi Policy không ghi đè làm mất version trước.

### UR-IAM-012 - Explainable Authorization
**Priority:** MUST

Mỗi quyết định ALLOW/DENY phải có thể giải thích.

**Acceptance criteria**
Có thể truy được tối thiểu:
- User
- Employee
- Company Context
- Profile
- Permission
- Action
- Resource
- Resource State
- Applicable Policy Rule
- Policy Version
- Final Effect
- Final Result

### UR-IAM-013 - Bảo vệ dữ liệu nhạy cảm
**Priority:** MUST

Dữ liệu nhạy cảm gồm tối thiểu:
- Payroll
- Compensation
- Giá vốn
- Tài khoản ngân hàng
- Thu chi
- Thông tin định danh nhân viên

**Acceptance criteria**
- VIEW, EDIT và EXPORT được đánh giá như các Permission độc lập.
- VIEW không tự tạo quyền EXPORT.
- Chỉ cho phép Action khi final Authorization = ALLOW.

---

# 7. Quản lý tài liệu

### UR-DOC-001 - Attachment theo đối tượng nghiệp vụ
**Priority:** MUST

Cho phép đính kèm file vào:
- Product
- Customer
- Supplier
- Employee
- Purchase
- Sales
- Inventory
- Finance
- Payroll

**Acceptance criteria**
- File xác định được đối tượng nghiệp vụ liên quan.
- Quyền truy cập file tuân theo quyền đối tượng nghiệp vụ.

### UR-DOC-002 - Tìm kiếm và version tài liệu
**Priority:** SHOULD

Hỗ trợ:
- Upload/Download
- Search
- Tag/Category
- Version
- Uploaded By/At

**Acceptance criteria**
- Có thể xem version hiện tại và version trước.
- Tài liệu bị thay thế không làm mất lịch sử.

---

# 8. Product

### UR-PRD-001 - Product Master
**Priority:** MUST

Product là Group Master dùng chung cho các Company.

Thông tin tối thiểu:
- Product Code
- SKU
- Barcode
- Tên
- Nhóm
- Loại
- UOM
- Trọng lượng
- Kích thước
- Mô tả
- Giá bán mặc định
- Trạng thái

### UR-PRD-002 - Unit of Measure
**Priority:** MUST

Mỗi Product có một base UOM và có thể khai báo UOM mua/bán khác.

**Acceptance criteria**
- Conversion factor phải lớn hơn 0.
- Giao dịch lưu được UOM nguồn và quantity quy đổi khi áp dụng.

### UR-PRD-003 - Serial Number
**Priority:** MUST

Product có thể cấu hình:
- Không quản lý Serial
- Có quản lý Serial

**Acceptance criteria**
- Serial xác định được Product, Company/Warehouse hiện tại và trạng thái.
- Serial truy được giao dịch nhập/xuất liên quan.
- Một Serial không thể đồng thời tồn tại ở nhiều Warehouse.
- Khi bán/nhập trả Product serial-managed phải xác định Serial tương ứng.

---

# 9. Customer & CRM

### UR-CUS-001 - Customer Master
**Priority:** MUST

Customer dùng chung trong Group.

Thông tin tối thiểu:
- Customer Code
- Tên
- Loại/nhóm Customer
- Điện thoại
- Email
- Tax Code
- Địa chỉ
- Shipping Address
- Contact Person
- Status

### UR-CUS-002 - Customer History
**Priority:** MUST

CRM chỉ cần hồ sơ Customer và lịch sử giao dịch/liên hệ.

**Acceptance criteria**
- Lưu được note/lịch sử liên hệ.
- Xem được Quotation/Sales Order/Shipment/Return/Thu tiền liên quan trong phạm vi quyền.
- Không yêu cầu Lead Management hoặc Opportunity Pipeline.

---

# 10. Pricing & Promotion

### UR-PRC-001 - Price List và Price Resolution
**Priority:** MUST

Hỗ trợ nhiều bảng giá theo:
- Company
- Sales Channel
- Customer Group
- Customer
- Effective Period

Fallback về Product Default Selling Price nếu không có bảng giá phù hợp.

**Acceptance criteria**
- Sales Order truy được nguồn giá đã áp dụng.
- Bảng giá hết hiệu lực không dùng cho giao dịch mới.

### UR-PRC-002 - Promotion
**Priority:** MUST

Hỗ trợ promotion cơ bản:
- Giảm %
- Giảm số tiền
- Giá đặc biệt
- Quantity-based discount
- Tặng Product
- Coupon

**Acceptance criteria**
- Promotion có thời gian hiệu lực.
- Sales Order lưu được promotion/coupon đã áp dụng.
- Không áp dụng promotion ngoài điều kiện cấu hình.

---

# 11. Supplier

### UR-SUP-001 - Supplier Master
**Priority:** MUST

Supplier dùng chung trong Group.

Thông tin tối thiểu:
- Supplier Code
- Tên
- Nhóm
- Contact
- Điện thoại/Email
- Địa chỉ
- Tax Code
- Bank Account
- Payment Term
- Status

### UR-SUP-002 - Supplier History
**Priority:** MUST

Từ Supplier có thể xem lịch sử:
- Purchase Order
- Receiving
- Purchase Return
- Chi/Thanh toán liên quan

---

# 12. Purchase

### UR-PUR-001 - Purchase Request
**Priority:** MUST

Cho phép tạo Purchase Request.

Lifecycle cơ bản:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- REJECTED
- CANCELLED

**Acceptance criteria**
- PR có thể Submit/Approve/Reject theo PBAC.
- PR Approved có thể dùng để tạo Purchase Order.

### UR-PUR-002 - Purchase Order
**Priority:** MUST

PO gồm tối thiểu:
- Company
- Supplier
- Product
- Quantity
- Unit Price
- Discount
- Tax khi áp dụng
- Expected Receipt Date
- Receiving Warehouse
- Payment Term
- Status

**Acceptance criteria**
- PO tuân theo PBAC theo Company/Resource/State/Action.
- PO giữ lịch sử phê duyệt và thay đổi quan trọng.

### UR-PUR-003 - Receiving
**Priority:** MUST

Cho phép nhận hàng một phần hoặc nhiều lần.

**Acceptance criteria**
- Official Receipt làm tăng Inventory.
- PO cập nhật Received Quantity.
- Product serial-managed bắt buộc ghi Serial khi nhận.
- Không nhận vào Warehouse thuộc Company khác PO.

### UR-PUR-004 - Purchase Return
**Priority:** MUST

Cho phép trả hàng Supplier.

**Acceptance criteria**
- Purchase Return làm giảm tồn.
- Serial-managed item phải xác định đúng Serial trả.
- Có thể truy về Receipt/PO nguồn.

---

# 13. Inventory

### UR-INV-001 - Inventory Balance
**Priority:** MUST

Theo dõi tồn theo:

**Company + Warehouse + Product**

và Serial khi áp dụng.

**Acceptance criteria**
- Có thể xem On-hand theo từng Warehouse.
- Có thể xem tồn Group bằng tổng hợp Company được phép.
- Không trộn tồn giữa các Company.

### UR-INV-002 - Inventory Movement
**Priority:** MUST

Hỗ trợ:
- Purchase Receipt
- Sales Issue
- Transfer
- Adjustment
- Purchase Return
- Sales Return

Mỗi movement lưu:
- Company
- Warehouse
- Product
- Quantity
- Serial khi áp dụng
- Source Document
- User
- Timestamp

### UR-INV-003 - Warehouse Transfer
**Priority:** MUST

Cho phép chuyển hàng giữa Warehouse trong cùng Company.

**Acceptance criteria**
- Source và destination Warehouse phải cùng Company.
- Cross-Company movement không được xử lý như internal warehouse transfer.

### UR-INV-004 - Stock Count và Adjustment
**Priority:** MUST

Hỗ trợ:
- Tạo Stock Count
- Ghi nhận actual quantity
- So sánh system quantity
- Xác nhận variance
- Tạo Inventory Adjustment

**Acceptance criteria**
- Adjustment chỉ được ghi nhận theo PBAC.
- Có thể truy từ Adjustment về Stock Count và người xác nhận.

---

# 14. Sales đa kênh & Vận chuyển

### UR-SAL-001 - Sales Order
**Priority:** MUST

Sales Order gồm:
- Company
- Customer
- Sales Channel
- Product
- Quantity
- Price
- Promotion/Discount
- Shipping Fee
- Warehouse
- Delivery Address
- Payment Method
- Status

### UR-SAL-002 - Shopee Integration
**Priority:** MUST

Hệ thống nhận và quản lý đơn từ Shopee.

**Acceptance criteria**
- Lưu Shopee Order ID.
- Mapping SKU/Product.
- Tạo hoặc liên kết ERP Sales Order.
- Đồng bộ trạng thái cần thiết.
- Lỗi mapping/sync phải được ghi nhận để xử lý.

### UR-SAL-003 - TikTok Shop Integration
**Priority:** MUST

Hệ thống nhận và quản lý đơn từ TikTok Shop.

**Acceptance criteria**
- Lưu TikTok Shop Order ID.
- Mapping SKU/Product.
- Tạo hoặc liên kết ERP Sales Order.
- Đồng bộ trạng thái cần thiết.
- Lỗi mapping/sync phải được ghi nhận để xử lý.

### UR-SAL-004 - Inventory dùng chung cho đa kênh
**Priority:** MUST

Shopee và TikTok Shop sử dụng Inventory của ERP làm nguồn tồn vận hành.

**Acceptance criteria**
- Không tạo kho logic riêng cạnh tranh chỉ vì khác sales channel.
- Order phải kiểm tra availability theo Company/Warehouse phù hợp.
- Việc reserve/issue stock phải phản ánh vào Inventory chung.

### UR-SAL-005 - Shipment và Carrier
**Priority:** MUST

Shipment quản lý:
- Carrier
- Service
- Tracking Number
- Shipping Fee
- COD Amount
- Delivery Status

Carrier integration hỗ trợ:
- Lấy phí giao hàng
- Tạo vận đơn
- Nhận tracking number
- In label
- Tracking status
- Hủy vận đơn
- Nhận delivery status
- Nhận COD status/data khi carrier hỗ trợ

### UR-SAL-006 - COD Reconciliation
**Priority:** MUST

Theo từng Shipment xác định:
- COD phải thu
- COD carrier báo đã thu
- Shipping fee
- Other fee
- Net amount carrier phải chuyển
- Amount thực nhận
- Difference
- Reconciliation status

**Acceptance criteria**
- Có thể lọc các shipment chưa đối soát.
- Chênh lệch phải xác định được amount và trạng thái xử lý.
- Khoản COD đã đối soát có thể tạo/liên kết giao dịch Thu.

### UR-SAL-007 - Sales Return
**Priority:** MUST

Hỗ trợ:
- Return Request
- Receive Returned Goods
- Serial validation khi áp dụng
- Inventory update
- Refund/Thu-Chi reference khi áp dụng

---

# 15. Chấm công & Quản lý tiền lương

Phạm vi HCM/Payroll giữ lại các capability nghiệp vụ chính của baseline trước, nhưng được viết lại theo cấu trúc Mini ERP.

### UR-HRP-001 - Hồ sơ HR, hợp đồng và Work Calendar
**Priority:** MUST

Quản lý dữ liệu HR mở rộng của Employee:
- Hợp đồng
- Thuộc tính nhân sự
- Thuộc tính tiền lương
- Effective period

Employee Work Calendar gồm:
- Ca/lịch làm việc chuẩn
- Số giờ chuẩn/ngày
- Quy tắc quy đổi giờ sang ngày công
- Ngày nghỉ tuần
- Ngày nghỉ theo HR policy

**Acceptance criteria**
- Lịch sử hợp đồng/điều chuyển/trạng thái được bảo toàn.
- Mỗi ngày xác định được working day, weekly off hoặc HR holiday.
- Standard working hours/days của kỳ resolve được từ calendar có hiệu lực.

### UR-HRP-002 - Attendance
**Priority:** MUST

Nhân viên có thể ghi nhận:
- Working date
- Working hours
- Work description

**Acceptance criteria**
- Giữ Created/Updated By/At.
- Điều chỉnh sau thời hạn hoặc sau khi kỳ khóa phải được kiểm soát.
- Giờ nhân viên tự ghi không tự động trở thành overtime.

### UR-HRP-003 - Leave
**Priority:** MUST

Nhân viên có thể gửi yêu cầu nghỉ theo ngày hoặc phần ngày.

Thông tin:
- Leave type
- Period
- Reason
- Status
- Approver

**Acceptance criteria**
- Approved Leave tự động phản ánh vào Timesheet.
- Rejected/Cancelled leave không tính là nghỉ hợp lệ.
- Có thể quản lý leave balance, paid/unpaid leave và carry-forward theo policy.
- Điều chỉnh sau duyệt giữ lịch sử.

### UR-HRP-004 - Overtime
**Priority:** MUST

Quản lý Overtime theo:
- Type
- Hours
- Rate/Factor
- Limit
- Approval

**Acceptance criteria**
- Chỉ approved overtime được tính vào Timesheet/Payroll.
- Giờ Attendance vượt chuẩn không tự động trở thành approved overtime.
- OT truy được ngày, loại, hệ số và approver.

### UR-HRP-005 - Monthly Timesheet
**Priority:** MUST

Tổng hợp bảng công tháng từ:
- Work Calendar
- Attendance
- Approved Leave
- Approved Overtime
- Approved Adjustment

Bảng công thể hiện tối thiểu:
- Days present
- Working hours
- Converted working days
- Standard working days
- Paid leave
- Unpaid leave
- Approved overtime
- Calendar holidays/off days
- Missing attendance days

**Acceptance criteria**
- Ngày nghỉ hợp lệ không bị xem là missing attendance.
- Days present và converted working days là hai chỉ tiêu riêng.
- Chỉ Timesheet đã khóa được dùng làm nguồn chính thức cho Payroll.

### UR-HRP-006 - Compensation
**Priority:** MUST

Quản lý:
- Salary
- Allowance
- Bonus
- Penalty
- Deduction
- Other earning/deduction

Mỗi thành phần có thời gian hiệu lực và lịch sử thay đổi.

### UR-HRP-007 - Payroll Lifecycle
**Priority:** MUST

Payroll sử dụng:
- Locked Monthly Timesheet
- Effective Compensation
- Approved Adjustments

Lifecycle:
- Calculate
- Review
- Approve
- Lock

Kết quả tối thiểu:
- Gross
- Allowance/Bonus
- Deduction
- Net Pay

**Acceptance criteria**
- Payroll không tính trực tiếp từ raw attendance khi Timesheet chưa khóa.
- Chỉ locked payroll period được xem là số liệu lương chính thức.
- Có thể trace từ Payroll về Timesheet và căn cứ liên quan.

### UR-HRP-008 - Payroll Adjustment
**Priority:** MUST

Hỗ trợ:
- Retroactive adjustment
- Back pay
- Termination
- Company/Unit transfer
- Off-cycle payroll

**Acceptance criteria**
- Adjustment liên kết kỳ gốc.
- Không sửa ngược raw attendance.
- Adjustment phải qua PBAC/approval phù hợp.

### UR-HRP-009 - Labor Cost Allocation
**Priority:** SHOULD

Cho phép phân bổ approved labor cost theo:
- Department
- Project
- Other supported business target

Capability này là tùy chọn và không phải prerequisite để Payroll hoạt động.

---

# 16. BOM & Quản lý dữ liệu sản phẩm đơn giản

### UR-BOM-001 - BOM
**Priority:** MUST

Một Product có thể có BOM.

BOM gồm:
- Finished Product
- Material/Component
- Required Quantity
- UOM
- Waste Rate khi áp dụng

### UR-BOM-002 - BOM Version
**Priority:** MUST

Hỗ trợ nhiều phiên bản BOM.

Thông tin:
- Version
- Effective Date
- Status

**Acceptance criteria**
- Tại một thời điểm xác định được BOM mặc định.
- Version cũ vẫn tra cứu được.

### UR-BOM-003 - Material Requirement
**Priority:** MUST

Hệ thống tính nhu cầu vật tư theo BOM và số lượng thành phẩm cần tính.

**Acceptance criteria**
- Kết quả chỉ ra quantity từng component.
- Waste Rate được áp dụng khi có.
- Có thể truy lại BOM version đã dùng.

### UR-BOM-004 - Estimated Finished Product Cost
**Priority:** MUST

Tính giá thành dự kiến từ:
- BOM
- Material Quantity
- Material Cost/Price hiện hành

**Acceptance criteria**
- Kết quả thể hiện cost từng component và tổng estimated product cost.
- Có thể biết thời điểm và nguồn cost đã dùng.
- Đây là management estimate, không phải production accounting.

### UR-BOM-005 - Manufacturing ngoài phạm vi
**Priority:** MUST

Baseline không yêu cầu:
- Production Order
- Routing
- Work Center
- MRP
- Capacity Planning
- Production Scheduling
- WIP
- Engineering BOM
- PLM

---

# 17. Vận hành tài chính

Finance trong Mini ERP chỉ phục vụ **tổng hợp Thu/Chi vận hành**, không phải hệ thống kế toán đầy đủ.

### UR-FIN-001 - Ghi nhận Thu/Chi
**Priority:** MUST

Mỗi giao dịch gồm:
- Company
- Date
- Receipt/Expense Type
- Amount
- Cash/Bank Account
- Related Customer/Supplier/Employee khi có
- Description
- Source Reference
- Attachment
- Recorded By

### UR-FIN-002 - Danh mục Thu/Chi
**Priority:** MUST

Cho phép cấu hình category như:
- Thu bán hàng
- Thu COD
- Thu khác
- Thanh toán Supplier
- Chi vận chuyển
- Chi lương
- Chi văn phòng
- Chi khác

### UR-FIN-003 - Cash và Bank Account
**Priority:** MUST

Quản lý danh sách:
- Cash Fund
- Bank Account

**Acceptance criteria**
- Mỗi account thuộc một Company.
- User chỉ thao tác account trong PBAC scope.

### UR-FIN-004 - Tổng hợp Thu/Chi
**Priority:** MUST

Báo cáo:
- Total Receipt
- Total Expense
- Receipt - Expense
- Opening Balance
- Closing Balance

Filter:
- Company
- Date Range
- Category
- Cash/Bank Account
- Customer
- Supplier

### UR-FIN-005 - Liên kết nguồn giao dịch
**Priority:** MUST

Thu/Chi có thể liên kết với:
- Sales Order
- Shipment/COD
- Purchase Order
- Supplier
- Customer
- Payroll

Liên kết phục vụ traceability/tổng hợp, không tạo double-entry accounting.

### UR-FIN-006 - Finance ngoài phạm vi
**Priority:** MUST

Baseline không yêu cầu:
- General Ledger
- Full Chart of Accounts
- Double-entry Accounting
- Journal Entry
- Trial Balance
- Balance Sheet
- Statutory P&L
- Full AR Ledger
- Full AP Ledger
- Financial Close
- Tax Accounting
- Consolidation Accounting

---

# 18. Yêu cầu dùng chung

### UR-COM-001 - Trạng thái chứng từ
**Priority:** MUST

Các chứng từ quan trọng có lifecycle rõ ràng.

Baseline state có thể gồm:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- PROCESSING
- COMPLETED
- REJECTED
- CANCELLED

Module có thể có state riêng nhưng phải xác định được business meaning.

### UR-COM-002 - Approval
**Priority:** MUST

Approval được kiểm soát bằng PBAC dựa trên:
- Permission
- Company
- Resource Type
- Resource State
- Participant/Department/business condition khi áp dụng

Không yêu cầu một enterprise Workflow Engine tổng quát trong baseline này.

### UR-COM-003 - History/Audit cơ bản
**Priority:** MUST

Các dữ liệu quan trọng lưu:
- Created By/At
- Updated By/At

Các Action quan trọng phải có history:
- Approve
- Reject
- Cancel
- Inventory Adjustment
- Payroll Lock
- Permission/Profile/Policy Change
- Thu/Chi adjustment

### UR-COM-004 - Search/Filter/Export
**Priority:** MUST

Các màn hình danh sách chính hỗ trợ:
- Search
- Filter
- Sort
- Pagination
- Export khi PBAC cho phép

### UR-COM-005 - Import
**Priority:** SHOULD

Master data chính hỗ trợ import Excel/CSV:
- Product
- Customer
- Supplier
- Employee

**Acceptance criteria**
- Import report chỉ rõ success/error theo từng dòng.
- Không ghi nhận dòng lỗi như dữ liệu hợp lệ.

---

# 19. Luồng nghiệp vụ end-to-end

## 19.1 Purchase
Supplier  
-> Purchase Request  
-> Purchase Order  
-> Receiving  
-> Inventory  
-> Chi tiền khi phát sinh

## 19.2 Marketplace Sales
Shopee / TikTok Shop  
-> ERP Sales Order  
-> Pricing / Promotion  
-> Inventory  
-> Shipment  
-> Carrier  
-> Delivery  
-> COD  
-> COD Reconciliation  
-> Thu tiền

## 19.3 Direct Sales
Customer  
-> Sales Order  
-> Inventory  
-> Shipment  
-> Thu tiền

## 19.4 Inventory
Purchase Receipt  
-> Stock  
-> Transfer / Adjustment  
-> Sales Issue  
-> Return

## 19.5 HR & Payroll
Employee  
-> Work Calendar  
-> Attendance / Leave / Overtime  
-> Monthly Timesheet  
-> Timesheet Lock  
-> Payroll  
-> Payroll Approval  
-> Payroll Lock  
-> Chi lương

## 19.6 BOM Costing
Product  
-> BOM  
-> Material Requirement  
-> Material Cost  
-> Estimated Finished Product Cost

---

# 20. Ngoài phạm vi baseline

Baseline này không bao gồm:
- Multi-Group
- Intercompany automation
- PLM
- Engineering Change Management
- MRP
- Production Order
- Routing/Work Center
- Capacity Planning
- Production Scheduling
- Advanced CRM Lead/Opportunity
- Full statutory accounting
- General Ledger
- Enterprise Workflow Engine tổng quát
- Enterprise Data Governance framework
- Enterprise BI/Data Warehouse
- AI anomaly detection

PBAC/Policy Engine **nằm trong phạm vi baseline** và là cơ chế Authorization chuẩn của hệ thống.

---

# 21. Ghi chú kiểm soát phạm vi

- Tài liệu này chưa chia P1/P2/P3.
- Phase Planning chỉ được lập sau khi User Requirements được review và approved.
- Functional Requirements được phân rã từ từng `UR-*` sau khi scope được khóa.
- Không thêm capability mới vào FRS nếu không có requirement tương ứng trong baseline hoặc change request được phê duyệt.
