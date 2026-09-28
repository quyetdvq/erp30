# Idea Organization & Employee Authorization

## ERP System Definition Document - Version 1.7

## 1. Thông tin tài liệu

**Tên tài liệu:** Idea Organization & Employee Authorization

**Version:** 1.7

**Mục tiêu:** Tài liệu mô tả mô hình Organization Context, Employee,
User Identity, Department, Position, Role, Profile, Policy Rule,
Resource Authorization và Workflow Authorization cho Mini ERP
Multi-Organization.

------------------------------------------------------------------------

# 2. Mục tiêu hệ thống

Hệ thống là Mini ERP Multi-Organization.

Phạm vi:

-   Employee Management
-   User Identity
-   Organization Management
-   Department
-   Position
-   Organization Context
-   Permission
-   Profile
-   Policy Rule
-   Resource Authorization
-   State Based Authorization

Nguyên tắc:

-   Một database dùng chung cho nhiều Organization.
-   Organization Context là phạm vi tổ chức và phạm vi dữ liệu nghiệp
    vụ.
-   Authorization dựa trên Organization Context + Resource + State.

------------------------------------------------------------------------

# 3. Organization Context Model

## 3.1 Organization Context Type

Organization Context Type là danh sách cố định.

Các loại được hỗ trợ:

-   GROUP
-   COMPANY
-   BRANCH
-   PLANT
-   WAREHOUSE

System Admin không được tạo thêm Organization Context Type mới.

## 3.2 Organization Hierarchy

Cấu trúc chuẩn:

    GROUP
     |
     COMPANY
     |
     BRANCH
     |
     PLANT
     |
     WAREHOUSE

Quy tắc: 

-   Một hệ thống có thể có nhiều Group.
-   Cấp trên không thể là con của cấp dưới.
-   Organization không thể là con của chính nó.
-   Không được tạo vòng lặp trong Organization Tree.
-   Không hard-code xử lý nghiệp vụ theo từng loại Organization.

Ví dụ hợp lệ:

    Group A
     |
     Company A
     |
     Warehouse A

Ví dụ không hợp lệ:

    Warehouse
     |
     Company

------------------------------------------------------------------------

# 4. Employee Model

Employee không liên kết trực tiếp với Organization, Department hoặc
Position.

Quan hệ:

    Employee
     |
     Employee Assignment
     |
     Organization Context
     Department
     Position

## 4.1 Employee Assignment

Employee Assignment lưu lịch sử làm việc.

Cấu trúc:

    employee_assignment

    id
    employee_id
    organization_id
    department_id
    position_id

    effective_from
    effective_to
    status

Một Employee có thể có nhiều Assignment theo thời gian.

## 4.2 Primary Organization

Employee có Primary Organization Assignment.

Mục đích:

-   Default UI Context.
-   Thông tin nhân sự mặc định.
-   Tính lương.
-   Các nghiệp vụ cần Organization mặc định.

Primary Organization không thay thế Employee Assignment.

------------------------------------------------------------------------

# 5. User Identity

User phục vụ Authentication.

Quan hệ:

    User
     |
    0..1
     |
    Employee

Employee không bắt buộc phải có User.

User không liên kết Employee không được truy cập Resource ERP.

Ngoại lệ:

System Admin không bắt buộc có Employee.

------------------------------------------------------------------------

# 6. Department và Position

Quan hệ:

    Organization Context
     |
    Department
     |
    Position
     |
    Employee Assignment

Department và Position thuộc Organization Context.

Position:

-   Không sinh Permission.
-   Không tự mapping Role.
-   Chỉ thể hiện cơ cấu tổ chức.

------------------------------------------------------------------------

# 7. Role và Profile

## 7.1 Role

Role là nhóm Permission.

Role không đại diện cho:

-   Job Title.
-   Position.
-   Department.

Role Permission là Permission catalog dùng để tạo Profile.

## 7.2 Profile

Profile là tập Permission hiệu lực.

Công thức:

    Profile =
    Role Permission
    +
    User Override

User Override chỉ hỗ trợ ALLOW addition.

Không hỗ trợ DENY.

Policy chịu trách nhiệm giới hạn quyền.

------------------------------------------------------------------------

# 8. Permission

Permission lưu dạng:

    resource.action

Ví dụ:

    invoice.view
    invoice.approve
    product.create

Permission Catalog do System Admin quản lý.

System Admin có thể:

-   Tạo Permission.
-   Xóa Permission.
-   Chỉnh sửa mô tả Permission.

------------------------------------------------------------------------

# 9. Policy Rule

Policy là lớp Restriction.

Nguyên tắc:

-   Policy không tạo Permission mới.
-   Policy không cấp thêm quyền ngoài Profile.
-   Policy chỉ giới hạn Permission đã có trong Profile.

Cấu trúc:

    Organization Context
    +
    Resource Type
    +
    State
    +
    Participant
    +
    Permission
    +
    Effect

Participant:

-   USER
-   ROLE
-   POSITION
-   DEPARTMENT
-   ORGANIZATION

Effect:

    ABSOLUTE DENY
            >
          ALLOW
            >
           DENY

Không sử dụng Policy Priority.

------------------------------------------------------------------------

# 10. Resource Authorization

Resource đại diện cho đối tượng ERP.

Ví dụ:

-   Invoice
-   Product
-   Inventory
-   Employee
-   Sales Order

Authorization hỗ trợ:

-   Resource Type.
-   Resource Instance.
-   State.

Chưa hỗ trợ Condition Rule theo giá trị nghiệp vụ.

Ví dụ chưa hỗ trợ:

    Invoice amount > 100000000

------------------------------------------------------------------------

# 11. Workflow Separation

Authorization và Workflow là hai hệ thống riêng.

Authorization trả lời:

    User có quyền thực hiện Action không?

Workflow trả lời:

    Resource có được chuyển State không?

State thuộc Workflow Engine.

State Catalog là bảng riêng thuộc System Domain.

Chỉ System Admin quản lý State Catalog.

------------------------------------------------------------------------

# 12. Organization Scope

Business table bắt buộc có:

    organization_id

organization_id có thể tham chiếu tới bất kỳ Organization Context Type
nào.

Module tự xác định Organization Level phù hợp.

Ví dụ:

Invoice có thể thuộc:

-   Company
-   Branch
-   Warehouse

Inventory có thể thuộc:

-   Warehouse
-   Plant
-   Company

------------------------------------------------------------------------

# 13. System Admin

System Admin là Root User.

Có:

-   Toàn bộ Permission.
-   Toàn bộ Organization Context.
-   Bypass Authorization Extension.
-   Bypass Policy Rule.
-   Bypass Workflow Transition.

------------------------------------------------------------------------

# 14. Không hỗ trợ

Hệ thống không hỗ trợ:

-   Delegation Permission.
-   Temporary Permission.
-   Permission effective_from/effective_to.

------------------------------------------------------------------------

# 15. Multi-language

Các Entity chính hỗ trợ:

-   Code.
-   Multi-language Name.

Áp dụng cho:

-   Organization.
-   Department.
-   Position.
-   Resource.
-   Permission Description.

------------------------------------------------------------------------

# 16. Design Principles

1.  Organization Context là phạm vi tổ chức.
2.  Group, Company, Branch, Plant, Warehouse là Organization Context cố
    định.
3.  Employee liên kết Organization thông qua Employee Assignment.
4.  Employee Assignment có quản lý lịch sử.
5.  Position không phải Permission.
6.  Role chỉ là nhóm quyền.
7.  Profile = Role Permission + User Override.
8.  Policy chỉ hạn chế Profile.
9.  Authorization dựa trên Organization Context + Resource + State.
10. Workflow và Authorization tách biệt.
11. Một database phục vụ nhiều Organization.
12. Thiết kế hướng tới ERP mở rộng.

------------------------------------------------------------------------

# 24. Database Design Clarification Added

## 24.1 Organization Context Database

Organization Context Type is fixed:

    GROUP
    COMPANY
    BRANCH
    PLANT
    WAREHOUSE

A system can contain multiple Group.

Organization uses one tree:

    GROUP
     |
     COMPANY
     |
     BRANCH
     |
     PLANT
     |
     WAREHOUSE

Rules:

-   Parent cannot become child of lower level.
-   Organization cannot be child of itself.
-   No circular reference.

------------------------------------------------------------------------

# 25. Employee Model Update

Employee does not directly belong to Organization, Department or
Position.

The relationship is through Employee Assignment.

    Employee
     |
    Employee Assignment
     |
    Organization Context
    Department
    Position

Employee Assignment supports:

-   Multiple active assignments.
-   Assignment history.

Table concept:

    employee_assignments

    employee_id
    organization_id
    department_id
    position_id

    is_primary

    effective_from
    effective_to
    status

Primary assignment is stored in:

    employee_assignments.is_primary

------------------------------------------------------------------------

# 26. Department and Position Update

## Department

Department is an independent Master Data.

It is not owned by Organization.

Supports hierarchy:

    Department
     |
     +-- Child Department

Example:

    Finance
     |
     Accounting
     |
     Treasury

------------------------------------------------------------------------

## Position

Position is an independent Master Data.

It does not have Organization Scope.

Position contains:

    level

Position does not generate Permission.

------------------------------------------------------------------------

# 27. Contact Model Update

Contact is changed from one-to-one to one-to-many.

Relationship:

    Employee
        |
        | 1:N
        |
    Contacts


    Organization
        |
        | 1:N
        |
    Contacts

Contact table:

    contacts

    id

    contact_type
    contact_ref_id

    name
    email
    phone
    mobile
    address

    province_id
    ward_id

    province_name
    ward_name

contact_type examples:

    EMPLOYEE
    ORGANIZATION

Contact uses polymorphic reference.

No direct foreign key from contact_ref_id.

------------------------------------------------------------------------

# 28. Resource Authorization Update

Resource Instance Authorization is removed.

The system only supports Resource Level Authorization.

Authorization scope:

    Organization Context
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

Not supported:

-   Resource Instance Authorization.
-   Condition Rule.
-   Amount based rule.
-   Attribute based access control.

------------------------------------------------------------------------

# 29. Resource and Permission Model

Permission Catalog is generated from Resource Action.

No separate Permission table.

Relationship:

    Resource
     |
    Resource Action
     |
    Permission Code

Format:

    resource.action

Examples:

    invoice.approve
    product.edit

Default actions:

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

# 30. Lifecycle and Workflow Update

Lifecycle is separated from Workflow State.

Relationship:

    Resource
     |
    Lifecycle
     |
    Lifecycle State Mapping
     |
    Workflow State

Tables:

    lifecycles

    workflow_states

    lifecycle_states

Purpose:

-   Lifecycle defines allowed states for Resource.
-   Workflow State defines current state used by Workflow Engine.
-   Policy evaluates permission based on State.

------------------------------------------------------------------------

# 31. Policy Rule Update

Policy model:

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

Effect priority:

    ABSOLUTE_DENY
            >
          ALLOW
            >
           DENY

Policy inheritance:

-   Parent Organization Policy can apply to Child Organization.
-   Policy data is not copied.

------------------------------------------------------------------------

# 32. Authorization Engine Update

Final flow:

    Authenticate User

            |

    Load Employee

            |

    Resolve Employee Assignment

            |

    Resolve Organization Context

            |

    Load Profile Permission

            |

    Resolve Resource Action

            |

    Load Matching Policy

            |

    Evaluate Effect

            |

    Audit Decision

            |

    Return Permission Decision

Architecture:

    AuthorizationService

     |
     +-- EmployeeContextResolver

     |
     +-- ProfileResolver

     |
     +-- PolicyResolver

     |
     +-- DecisionEngine

     |
     +-- AuditLogger

------------------------------------------------------------------------

# 33. Implementation Decisions

Confirmed:

-   One Database Multiple Organization.
-   CodeIgniter Shield manages Authentication and Base Permission.
-   Role has no Organization Scope.
-   User Override only adds Allow Permission.
-   Policy only restricts Profile Permission.
-   No Resource Instance Authorization.
-   No Temporary Permission.
-   No Delegation Permission.
