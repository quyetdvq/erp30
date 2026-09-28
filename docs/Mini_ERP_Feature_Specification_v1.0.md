# Mini ERP Feature Specification v1.0

## 1. Purpose

Tài liệu mô tả Feature của hệ thống Mini ERP Organization &
Authorization.

Phạm vi: - Organization Management - Employee Management - Department &
Position Management - User Identity - Resource Permission - Role -
Policy Authorization - Lifecycle / Workflow - Authorization Evaluation -
Audit

------------------------------------------------------------------------

# 2. Organization Management

## Feature: Organization Tree

Mục tiêu: Quản lý cây tổ chức ERP.

Organization Type:

    GROUP
    COMPANY
    BRANCH
    PLANT
    WAREHOUSE

Chức năng: - Tạo Organization. - Sửa Organization. - Xem Organization
Tree. - Đóng Organization.

Business Rules: - Hỗ trợ nhiều Group. - Organization có Parent. - Không
tạo vòng lặp. - Không tạo Organization làm con của chính nó.

------------------------------------------------------------------------

# 3. Employee Management

## Feature: Employee Master

Chức năng: - Tạo Employee. - Cập nhật Employee. - Liên kết User Account.

Rules: - Employee Code unique. - Employee có thể không có User.

## Feature: Employee Assignment

Quan hệ:

    Employee
     |
    Employee Assignment
     |
    Organization
    Department
    Position

Chức năng: - Gán Employee vào Organization. - Gán Department. - Gán
Position. - Chọn Primary Assignment. - Xem lịch sử.

Rules: - Một Employee có nhiều Assignment. - Có thể nhiều Assignment
active.

------------------------------------------------------------------------

# 4. Department Management

Department là Master độc lập.

Chức năng: - CRUD Department. - Xem Department Tree.

Rules: - Không thuộc Organization. - Có phân cấp.

------------------------------------------------------------------------

# 5. Position Management

Position là Master độc lập.

Chức năng: - CRUD Position. - Quản lý Level.

Rules: - Không thuộc Organization. - Không sinh Permission.

------------------------------------------------------------------------

# 6. Contact Management

Employee và Organization có thể có nhiều Contact.

Mapping:

    contact_type
    contact_ref_id

Chức năng: - Quản lý email. - Quản lý phone. - Quản lý address.

------------------------------------------------------------------------

# 7. Resource Management

Resource đại diện đối tượng ERP.

Ví dụ: - Invoice - Product - Order - Employee

Resource Action tạo Permission.

Format:

    resource.action

Ví dụ:

    invoice.approve
    product.edit

------------------------------------------------------------------------

# 8. Role Management

Role là nhóm quyền.

Chức năng: - Tạo Role. - Gán Permission. - Quản lý Role Permission.

Rules: - Role không có Organization Scope. - Role không thay thế
Position.

------------------------------------------------------------------------

# 9. Profile Permission

Công thức:

    Profile =
    Role Permission
    +
    User Override

User Override chỉ bổ sung Allow Permission.

------------------------------------------------------------------------

# 10. Policy Management

Policy giới hạn quyền.

Cấu trúc:

    Organization
    +
    Resource
    +
    State
    +
    Participant
    +
    Action
    +
    Effect

Participant:

    USER
    ROLE
    POSITION
    DEPARTMENT
    ORGANIZATION

Effect:

    ABSOLUTE_DENY
            >
          ALLOW
            >
           DENY

------------------------------------------------------------------------

# 11. Lifecycle / Workflow

Quan hệ:

    Resource
     |
    Lifecycle
     |
    Lifecycle State
     |
    Workflow State

Mục đích: - Quản lý trạng thái nghiệp vụ. - Dùng State trong
Authorization.

------------------------------------------------------------------------

# 12. Authorization Evaluation

Flow:

    Authenticate User

    ↓

    Load Employee

    ↓

    Resolve Employee Assignment

    ↓

    Resolve Organization Context

    ↓

    Load Profile Permission

    ↓

    Resolve Resource Action

    ↓

    Load Policy

    ↓

    Evaluate Effect

    ↓

    Audit Decision

------------------------------------------------------------------------

# 13. Audit

Lưu:

-   User.
-   Organization.
-   Resource.
-   Action.
-   State.
-   Decision.
-   Policy.
-   Reason.

Log: - Approve. - Delete. - State Change.

------------------------------------------------------------------------

# 14. Out of Scope

Không hỗ trợ:

-   Resource Instance Authorization.
-   Condition Rule.
-   Amount Rule.
-   Temporary Permission.
-   Permission Delegation.
-   ABAC.

------------------------------------------------------------------------

# 15. Implementation Priority

Phase 1: - Organization. - Employee. - Department. - Position. - User.

Phase 2: - Resource. - Role. - Permission. - Policy.

Phase 3: - Authorization Engine. - Audit. - Workflow Integration.
