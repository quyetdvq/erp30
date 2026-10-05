# Business Requirements Specification (BRS)

# Mini ERP Organization & Authorization System

## Version 1.0

## 1. Purpose

Tài liệu BRS mô tả yêu cầu nghiệp vụ cho hệ thống Mini ERP
Multi-Organization.

Phạm vi: - Organization Management. - Employee Management. - Department
/ Position Management. - User Identity. - Permission Management. -
Policy Authorization. - Workflow State Authorization.

## 2. Business Objectives

Hệ thống cần: - Quản lý nhiều Organization trong một database. - Quản lý
dữ liệu theo Organization Context. - Quản lý Employee thông qua Employee
Assignment. - Phân quyền theo Resource, State và Organization. - Tách
Authentication, Authorization và Workflow.

## 3. Organization Requirements

Organization Context gồm các loại cố định:

    GROUP
    COMPANY
    BRANCH
    PLANT
    WAREHOUSE

Cây tổ chức:

    GROUP
     |
     COMPANY
     |
     BRANCH
     |
     PLANT
     |
     WAREHOUSE

Quy tắc: - Hỗ trợ nhiều Group. - Không tạo vòng lặp Organization. -
Không cho Organization làm con của chính nó.

## 4. Employee Requirements

Employee đại diện cho người làm việc.

Employee liên kết Organization thông qua:

    Employee
     |
    Employee Assignment
     |
    Organization
    Department
    Position

Yêu cầu: - Employee có Employee Code duy nhất. - Một Employee có nhiều
Assignment. - Hỗ trợ Primary Assignment. - Có lịch sử Assignment.

## 5. Department and Position

Department: - Là Master độc lập. - Có phân cấp. - Không thuộc
Organization.

Position: - Là Master độc lập. - Có Level. - Không sinh Permission.

## 6. Contact Requirements

Employee và Organization có thể có nhiều Contact.

Contact sử dụng:

    contact_type
    contact_ref_id

Hỗ trợ: - Email. - Phone. - Mobile. - Address. - Province. - Ward.

## 7. Authorization Requirements

Permission được sinh từ:

    Resource
     |
    Resource Action

Ví dụ:

    invoice.approve
    product.edit

Role: - Là nhóm quyền. - Không có Organization Scope. - Không đại diện
Position.

Profile:

    Profile =
    Role Permission
    +
    User Override

User Override chỉ bổ sung Allow Permission.

## 8. Policy Requirements

Policy dùng để giới hạn quyền.

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

## 9. Resource and Workflow

Resource đại diện đối tượng ERP.

Lifecycle:

    Resource
     |
    Lifecycle
     |
    Lifecycle State
     |
    Workflow State

Không hỗ trợ: - Resource Instance Authorization. - Condition Rule. -
Amount Based Rule.

## 10. Authorization Evaluation

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

## 11. Audit

Audit lưu: - User. - Organization. - Resource. - Action. - State. -
Decision. - Policy. - Reason.

## 12. Out of Scope

Không hỗ trợ: - Temporary Permission. - Permission Delegation. - Dynamic
Rule Engine. - Attribute Based Access Control.

## 13. Summary

Mô hình tổng quát:

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
    Policy Restriction
     |
    Authorization Decision
