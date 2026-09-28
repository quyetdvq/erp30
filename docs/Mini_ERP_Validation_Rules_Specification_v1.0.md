# Mini ERP Validation Rules Specification v1.0

## 1. Purpose

Tài liệu mô tả Validation Rules cho Mini ERP Organization &
Authorization.

Mục tiêu: - Đảm bảo dữ liệu hợp lệ. - Đảm bảo Business Rule được tuân
thủ. - Ngăn dữ liệu sai trong các module.

------------------------------------------------------------------------

# 2. Organization Validation

## VR-ORG-001 Organization Type

Allowed:

    GROUP
    COMPANY
    BRANCH
    PLANT
    WAREHOUSE

Không cho phép loại Organization khác.

## VR-ORG-002 Organization Tree

Rules: - Organization không là con của chính nó. - Không tạo vòng lặp. -
Parent phải tồn tại.

## VR-ORG-003 Organization Code

Rules: - Bắt buộc. - Unique toàn hệ thống.

## VR-ORG-004 Organization Status

Values:

    ACTIVE
    INACTIVE
    CLOSED

ACTIVE: - Cho phép nghiệp vụ mới.

INACTIVE: - Chỉ xem lịch sử.

CLOSED: - Chỉ phục vụ lịch sử/báo cáo.

------------------------------------------------------------------------

# 3. Employee Validation

## VR-EMP-001 Employee Code

Rules: - Bắt buộc. - Unique.

## VR-EMP-002 User Mapping

Rules: - Employee có thể không có User. - Một User tối đa liên kết một
Employee.

------------------------------------------------------------------------

# 4. Employee Assignment Validation

## VR-ASG-001 Assignment

Bắt buộc: - Employee. - Organization. - Department. - Position.

## VR-ASG-002 Multiple Assignment

Rules: - Một Employee có nhiều Assignment. - Có thể nhiều Assignment
active.

## VR-ASG-003 Primary Assignment

Rules: - Một Employee chỉ có một Primary Assignment tại một thời điểm.

## VR-ASG-004 History

Rules: - effective_from \<= effective_to. - Không xóa lịch sử.

------------------------------------------------------------------------

# 5. Department / Position Validation

## Department

Rules: - Là Master độc lập. - Có hierarchy. - Không thuộc Organization.

## Position

Rules: - Có level. - Level là số nguyên. - Không sinh Permission. -
Không tự mapping Role.

------------------------------------------------------------------------

# 6. Contact Validation

Contact sử dụng:

    contact_type
    contact_ref_id

Rules: - Không tạo FK trực tiếp cho contact_ref_id. - Entity phải thuộc
loại hỗ trợ.

Supported:

    EMPLOYEE
    ORGANIZATION

------------------------------------------------------------------------

# 7. Resource Validation

## Resource

Rules: - Resource Type unique. - Resource phải có Domain.

## Resource Action

Rules: - Action Code unique trong Resource.

Permission format:

    resource.action

Ví dụ:

    invoice.approve
    product.edit

------------------------------------------------------------------------

# 8. Lifecycle / Workflow Validation

Rules:

-   Lifecycle State phải thuộc Lifecycle.
-   Workflow State phải tồn tại.
-   Một Lifecycle chỉ có một Initial State.

Quan hệ:

    Resource
     |
    Lifecycle
     |
    Lifecycle State
     |
    Workflow State

------------------------------------------------------------------------

# 9. Role Validation

Rules: - Role Name bắt buộc. - Role Name unique. - Role không có
Organization Scope. - Role không thay thế Position.

------------------------------------------------------------------------

# 10. Policy Validation

Policy bắt buộc:

-   Organization.
-   Resource.
-   State.
-   Participant.

Participant:

    USER
    ROLE
    POSITION
    DEPARTMENT
    ORGANIZATION

Effect:

    ALLOW
    DENY
    ABSOLUTE_DENY

Priority:

    ABSOLUTE_DENY
    >
    ALLOW
    >
    DENY

Không hỗ trợ: - Resource Instance Authorization. - Condition Rule.

------------------------------------------------------------------------

# 11. Authorization Validation

Flow:

    User
     |
    Employee
     |
    Employee Assignment
     |
    Organization Context
     |
    Profile Permission
     |
    Policy Evaluation

Rules: - User phải có Permission trong Profile. - Policy chỉ
Restriction. - Organization Context phải xác định được.

------------------------------------------------------------------------

# 12. Audit Validation

Audit bắt buộc lưu:

-   User.
-   Organization.
-   Resource.
-   Action.
-   Decision.
-   Reason.

Log: - Approve. - Delete. - State Change.

Không bắt buộc: - View. - List.

------------------------------------------------------------------------

# 13. Out of Scope

Không hỗ trợ:

-   Resource Instance Authorization.
-   Dynamic Rule.
-   Amount Based Rule.
-   Temporary Permission.
-   Permission Delegation.
