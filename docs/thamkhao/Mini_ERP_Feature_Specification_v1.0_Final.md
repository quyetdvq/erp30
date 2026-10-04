# Mini ERP Feature Specification

## Version 1.0

# 1. Document Purpose

Tài liệu mô tả các Feature của hệ thống Mini ERP Organization &
Authorization.

Nguồn: - Business Requirements Specification. - Organization &
Authorization Design.

Mục tiêu: - Mô tả phạm vi chức năng. - Xác định hành vi hệ thống. - Là
cơ sở cho Functional Specification và Development.

------------------------------------------------------------------------

# 2. Feature Overview

Các nhóm Feature:

1.  Organization Management
2.  Employee Management
3.  Department Management
4.  Position Management
5.  Contact Management
6.  User Identity Management
7.  Resource Management
8.  Role Management
9.  Profile Permission Management
10. Policy Authorization Management
11. Lifecycle & Workflow Management
12. Authorization Evaluation
13. Audit Management

------------------------------------------------------------------------

# 3. Feature: Organization Management

## Description

Quản lý cây Organization Context trong ERP.

## Supported Types

    GROUP
    COMPANY
    BRANCH
    PLANT
    WAREHOUSE

## Functions

-   Create Organization.
-   Update Organization.
-   View Organization Tree.
-   Change Organization Status.

## Rules

-   Hỗ trợ nhiều Group.
-   Organization có Parent.
-   Không tạo vòng lặp.
-   Không cho Organization làm con của chính nó.

------------------------------------------------------------------------

# 4. Feature: Employee Management

## Employee Master

Functions:

-   Create Employee.
-   Update Employee.
-   View Employee.
-   Link User Account.

Rules:

-   Employee Code unique.
-   Employee có thể không có User.

------------------------------------------------------------------------

## Employee Assignment

Purpose:

Xác định Employee làm việc ở đâu trong hệ thống.

Structure:

    Employee
     |
    Employee Assignment
     |
    Organization
    Department
    Position

Functions:

-   Assign Employee.
-   Change Department.
-   Change Position.
-   Set Primary Assignment.
-   View Assignment History.

Rules:

-   Một Employee có nhiều Assignment.
-   Có thể có nhiều Assignment active.

------------------------------------------------------------------------

# 5. Feature: Department Management

## Description

Quản lý Department Master.

Functions:

-   Create Department.
-   Update Department.
-   View Department Tree.

Rules:

-   Department độc lập Organization.
-   Department hỗ trợ hierarchy.

------------------------------------------------------------------------

# 6. Feature: Position Management

## Description

Quản lý Position Master.

Functions:

-   Create Position.
-   Update Position.
-   Manage Position Level.

Rules:

-   Position độc lập Organization.
-   Position không tạo Permission.
-   Position không tự tạo Role.

------------------------------------------------------------------------

# 7. Feature: Contact Management

## Description

Quản lý Contact dùng chung.

Supported Entity:

-   Employee.
-   Organization.

Functions:

-   Add Contact.
-   Update Contact.
-   Delete Contact.
-   Manage Address.

Mapping:

    contact_type
    contact_ref_id

------------------------------------------------------------------------

# 8. Feature: Resource Management

## Description

Quản lý Resource được phân quyền.

Examples:

-   Invoice.
-   Product.
-   Order.
-   Employee.

Functions:

-   Create Resource.
-   Define Resource Action.
-   Assign Lifecycle.

------------------------------------------------------------------------

# 9. Feature: Permission Management

Permission được tạo từ Resource Action.

Format:

    resource.action

Examples:

    invoice.approve
    product.edit

Default Actions:

    view
    create
    edit
    delete
    submit
    approve
    reject
    cancel
    post
    close
    export
    print
    setstate

------------------------------------------------------------------------

# 10. Feature: Role Management

## Description

Role là nhóm quyền.

Functions:

-   Create Role.
-   Assign Permission.
-   Manage Role Permission.

Rules:

-   Role không có Organization Scope.
-   Role không thay thế Position.

------------------------------------------------------------------------

# 11. Feature: Profile Permission

## Description

Xác định quyền hiệu lực của User.

Formula:

    Profile =
    Role Permission
    +
    User Override

Rules:

-   User Override chỉ bổ sung Allow Permission.
-   Policy xử lý Restriction.

------------------------------------------------------------------------

# 12. Feature: Policy Management

## Description

Quản lý quy tắc giới hạn quyền.

Policy Structure:

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

    ALLOW
    DENY
    ABSOLUTE_DENY

Priority:

    ABSOLUTE_DENY
    >
    ALLOW
    >
    DENY

------------------------------------------------------------------------

# 13. Feature: Lifecycle & Workflow

## Description

Quản lý trạng thái nghiệp vụ của Resource.

Structure:

    Resource
     |
    Lifecycle
     |
    Lifecycle State
     |
    Workflow State

Functions:

-   Define Lifecycle.
-   Define State.
-   Map State to Lifecycle.

------------------------------------------------------------------------

# 14. Feature: Authorization Evaluation

## Description

Kiểm tra User có quyền thực hiện Action.

Flow:

    Authenticate User

    ↓

    Load Employee

    ↓

    Resolve Assignment

    ↓

    Resolve Organization Context

    ↓

    Load Profile

    ↓

    Load Resource Action

    ↓

    Load Policy

    ↓

    Evaluate Effect

    ↓

    Audit

    ↓

    Return Decision

------------------------------------------------------------------------

# 15. Feature: Audit

## Description

Lưu lịch sử quyết định Authorization.

Logged:

-   User.
-   Organization.
-   Resource.
-   Action.
-   State.
-   Decision.
-   Policy.
-   Reason.

Không bắt buộc log: - View. - List.

------------------------------------------------------------------------

# 16. Out of Scope

Không hỗ trợ:

-   Resource Instance Authorization.
-   Condition Rule.
-   Amount Based Rule.
-   Temporary Permission.
-   Permission Delegation.
-   ABAC.

------------------------------------------------------------------------

# 17. Implementation Priority

## Phase 1

-   Organization.
-   Employee.
-   Department.
-   Position.
-   Contact.
-   User Identity.

## Phase 2

-   Resource.
-   Role.
-   Permission.
-   Policy.

## Phase 3

-   Authorization Engine.
-   Audit.
-   Workflow Integration.
