# Mini ERP Authorization Database Design v1.2

## Full Table Specification - Final Review Before Migration

------------------------------------------------------------------------

# 1. Database Architecture

## 1.1 Architecture Principle

Hệ thống sử dụng:

-   One Database.
-   Multiple Organization.
-   Shared Database Schema.

Nguyên tắc:

-   Organization Context là phạm vi dữ liệu nghiệp vụ.
-   Department và Position là Master độc lập.
-   Employee Assignment là điểm liên kết giữa Employee và Organization.
-   Role quản lý nhóm quyền.
-   Policy chỉ giới hạn Permission.
-   Workflow State phục vụ Authorization theo trạng thái.

------------------------------------------------------------------------

# 2. Location Domain

## 2.1 provinces

Mục đích:

Lưu danh mục tỉnh/thành phố.

Columns:

  Column       Type           Description
  ------------ -------------- ---------------
  id           bigint         Primary Key
  code         varchar(20)    Unique Code
  name         varchar(255)   Province Name
  status       varchar(20)    Status
  created_at   datetime       
  updated_at   datetime       

Index:

-   PRIMARY KEY(id)
-   UNIQUE(code)
-   INDEX(name)

------------------------------------------------------------------------

## 2.2 wards

Mục đích:

Lưu danh mục phường/xã.

Columns:

  Column        Type           Description
  ------------- -------------- --------------------
  id            bigint         Primary Key
  province_id   bigint         Province Reference
  code          varchar(20)    Unique Code
  name          varchar(255)   Ward Name
  name_full     varchar(255)   Ward Name + Province Name
  status        varchar(20)    Status
  created_at    datetime       
  updated_at    datetime       

Relationship:

    Province
        |
        +-- Ward

------------------------------------------------------------------------

# 3. Contact Domain

## 3.1 contacts

Contact sử dụng mô hình Polymorphic Reference.

Mục đích:

-   Một Employee có thể có nhiều Contact.
-   Một Organization có thể có nhiều Contact.
-   Có thể mở rộng cho Customer, Supplier sau này.

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

Columns:

  Column           Type           Description
  ---------------- -------------- ------------------------
  id               bigint         Primary Key
  contact_type     varchar(50)    Entity Type
  contact_ref_id   bigint         Entity Reference ID
  description      text           Description ex: Head Office, person
  status           varchar(20)    ACTIVE/INACTIVE/CLOSED
  name             varchar(255)   Contact Name
  email            varchar(255)   Email
  phone            varchar(50)    Phone
  mobile           varchar(50)    Mobile
  address          text           Address
  province_id      bigint         Province Reference
  ward_id          bigint         Ward Reference
  province_name    varchar(255)   Snapshot Province Name
  ward_name        varchar(255)   Snapshot Ward Name
  created_at       datetime       
  updated_at       datetime       

Index:

    INDEX(contact_type, contact_ref_id)
    INDEX(email)
    INDEX(phone)
    INDEX(mobile)
    INDEX(province_id)
    INDEX(ward_id)

------------------------------------------------------------------------

# 4. Organization Domain

## 4.1 organizations

Organization Context.

Organization Type cố định:

    GROUP
    COMPANY
    BRANCH
    PLANT
    WAREHOUSE

Columns:

  Column        Type           Description
  ------------- -------------- ------------------------
  id            bigint         Primary Key
  parent_id     bigint         Parent Organization
  type          varchar(30)    Organization Type
  code          varchar(50)    Unique Code
  name          varchar(255)   Organization Name
  description   text           Description of Organization
  status        varchar(20)    ACTIVE/INACTIVE/CLOSED
  active_from   date           
  active_to     date           
  created_at    datetime       
  updated_at    datetime       

Index:

    UNIQUE(code)
    INDEX(parent_id)
    INDEX(type)
    INDEX(status)

------------------------------------------------------------------------

# 5. Employee Domain

## 5.1 departments

Department là Master độc lập.

Có phân cấp.

Columns:

  Column        Type
  ------------- --------------
  id            bigint
  parent_id     bigint
  code          varchar(50)
  name          varchar(255)
  description   text
  status        varchar(20)    ACTIVE/INACTIVE/CLOSED
  created_at    datetime
  updated_at    datetime

Index:

    UNIQUE(code)
    INDEX(parent_id)

------------------------------------------------------------------------

## 5.2 positions

Position là Master độc lập.

Có Level.

Columns:

  Column        Type
  ------------- --------------
  id            bigint
  code          varchar(50)
  name          varchar(255)
  level         int
  description   text
  status        varchar(20)    ACTIVE/INACTIVE/CLOSED
  created_at    datetime
  updated_at    datetime

Index:

    UNIQUE(code)

------------------------------------------------------------------------

## 5.3 employees

Employee Master.

Columns:

  Column          Type
  --------------- -------------
  id              bigint
  user_id         bigint
  employee_code   varchar(50)
  employee_name   varchar(50)
  description     text           Description of Organization
  status          varchar(20)    ACTIVE/INACTIVE
  created_at      datetime
  updated_at      datetime

Rule:

-   employee_code unique.
-   Một User tối đa liên kết một Employee.

Contact liên kết:

    contact_type = EMPLOYEE
    contact_ref_id = employees.id

------------------------------------------------------------------------

## 5.4 employee_assignments

Liên kết Employee với Organization.

Columns:

  Column            Type
  ----------------- -------------
  id                bigint
  employee_id       bigint
  organization_id   bigint
  department_id     bigint
  position_id       bigint
  is_primary        boolean
  effective_from    date
  effective_to      date
  description       text    Description of Organization
  status            varchar(20)
  created_at        datetime
  updated_at        datetime

Rule:

-   Employee có nhiều Assignment.
-   Có thể nhiều Assignment active.
-   Một Assignment Primary tại một thời điểm.

------------------------------------------------------------------------

# 6. Resource Authorization Domain

## 6.1 resources

Resource đại diện đối tượng ERP.

Columns:

  Column         Type
  -------------- --------------
  id             bigint
  lifecycle_id   bigint
  type           varchar(100)
  name           varchar(255)
  domain         varchar(100)
  created_at     datetime
  updated_at     datetime

Index:

    UNIQUE(type)

------------------------------------------------------------------------

## 6.2 resource_actions

Permission sinh từ Resource Action.

Columns:

  Column        Type
  ------------- --------------
  id            bigint
  resource_id   bigint
  code          varchar(50)
  description   varchar(255)

Index:

    UNIQUE(resource_id, code)

Default Action:

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

Permission format:

    resource.action

Ví dụ:

    invoice.approve
    product.edit

------------------------------------------------------------------------

# 7. Group Domain

## groups

Columns:

  Column        Type
  ------------- --------------
  id            bigint
  name          varchar(100)
  description   text
  status        varchar(20)    ACTIVE/INACTIVE
  created_at    datetime
  updated_at    datetime

------------------------------------------------------------------------

## group_permissions

Columns:

  Column               Type
  -------------------- --------
  group_id              bigint
  resource_action_id   bigint

Role không có Organization Scope.

------------------------------------------------------------------------

# 8. Lifecycle / Workflow Domain

## 8.1 lifecycles

Columns:

  Column        Type
  ------------- --------------
  id            bigint
  code          varchar(50)
  name          varchar(255)
  description   text
  status        varchar(20)
  created_at    datetime
  updated_at    datetime

------------------------------------------------------------------------

## 8.2 workflow_states

State Catalog của Workflow Engine.

Columns:

  Column        Type
  ------------- --------------
  id            bigint
  code          varchar(50)
  name          varchar(255)
  description   text
  status        varchar(20)
  created_at    datetime
  updated_at    datetime

Index:

    UNIQUE(code)

------------------------------------------------------------------------

## 8.3 lifecycle_states

Mapping Lifecycle với Workflow State.

Columns:

  Column         Type
  -------------- ----------
  id             bigint
  lifecycle_id   bigint
  state_id       bigint
  sequence       int
  is_initial     boolean
  is_final       boolean
  created_at     datetime

Index:

    UNIQUE(lifecycle_id, state_id)

------------------------------------------------------------------------

# 9. Policy Domain

## 9.1 policies

Policy áp dụng ở Resource Type Level.

Không hỗ trợ Resource Instance Authorization.

Columns:

  Column               Type
  -------------------- -------------
  id                   bigint
  organization_id      bigint
  resource_id          bigint
  state_id             bigint
  participant_type     varchar(30)
  participant_ref_id   bigint
  created_by           bigint
  updated_by           bigint
  created_at           datetime
  updated_at           datetime

Participant:

    USER
    GROUP
    POSITION
    DEPARTMENT
    ORGANIZATION

------------------------------------------------------------------------

## 9.2 policy_actions

Columns:

  Column               Type
  -------------------- -------------
  id                   bigint
  policy_id            bigint
  resource_action_id   bigint
  effect               varchar(30)

Effect:

    ALLOW
    DENY
    ABSOLUTE_DENY

------------------------------------------------------------------------

# 10. Audit Domain

## authorization_logs

Columns:

  Column              Type
  ------------------- --------------
  id                  bigint
  user_id             bigint
  organization_id     bigint
  resource_id         bigint
  action              varchar(50)
  state_id            bigint
  decision            varchar(30)
  matched_policy_id   bigint
  reason              text
  request_id          varchar(100)
  created_at          datetime

Log:

-   Approve.
-   Delete.
-   State Change.

Không log View/List.

------------------------------------------------------------------------

# 11. Final Relationship

    provinces
        |
    wards
        |
    contacts


    organizations


    departments
        |
        |
    employee_assignments
        |
        |
    employees


    positions


    resources
        |
    resource_actions


    groups
        |
    group_permissions
        |
    resource_actions


    lifecycles
        |
    lifecycle_states
        |
    workflow_states


    policies
        |
    policy_actions
        |
    resource_actions


    authorization_logs

------------------------------------------------------------------------

# 12. Migration Order

    001 provinces
    002 wards
    003 contacts

    004 organizations

    005 departments
    006 positions
    007 employees
    008 employee_assignments

    009 lifecycles
    010 workflow_states
    011 lifecycle_states

    012 resources
    013 resource_actions

    014 groups
    015 group_permissions

    016 policies
    017 policy_actions

    018 authorization_logs

------------------------------------------------------------------------

# 13. Confirmed Design Decisions

-   Không có Resource Instance Authorization.
-   Không có Condition Rule.
-   Không có Permission Table riêng.
-   Permission lấy từ Resource Action.
-   Department/Position là Master độc lập.
-   Contact hỗ trợ nhiều bản ghi.
-   Lifecycle tách khỏi Workflow State.
-   Policy chỉ Resource Level.
