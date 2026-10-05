# Mini ERP CodeIgniter 4 Model Relationship & Entity Design v1.1

## Final Review Before Code Implementation

## 1. Design Principles

Mục tiêu:

-   Model chịu trách nhiệm truy cập dữ liệu.
-   Entity đại diện dữ liệu nghiệp vụ.
-   Service xử lý nghiệp vụ phức tạp.
-   Không để Model phụ thuộc chặt vào Entity khác ngoài Foreign Key
    thật.

Các nguyên tắc:

-   Organization Context quản lý phạm vi tổ chức.
-   Employee Assignment là điểm liên kết Employee với Organization.
-   Department và Position là Master độc lập.
-   Contact sử dụng Polymorphic Reference.
-   Role quản lý Permission.
-   Policy giới hạn Permission.
-   Workflow State phục vụ Authorization.

------------------------------------------------------------------------

# 2. Organization Domain

## 2.1 OrganizationModel

Table:

    dinktech_organizations

Quan hệ:

    Organization

     |
     +-- Parent Organization

     |
     +-- Child Organizations

     |
     +-- Employee Assignments

Contact không phải Model Relationship trực tiếp.

Truy cập Contact thông qua ContactService.

Methods:

``` php
getParent($id)

getChildren($id)

getTree()

getEmployees($organizationId)
```

------------------------------------------------------------------------

## 2.2 Organization Entity

Properties:

``` php
id
parent_id
type
code
name
status
active_from
active_to
created_at
updated_at
```

------------------------------------------------------------------------

# 3. Employee Domain

## 3.1 EmployeeModel

Table:

    dinktech_employees

Quan hệ:

    Employee

     |
     +-- Employee Assignments

Contact lấy thông qua ContactService.

Methods:

``` php
getAssignments($employeeId)

getPrimaryAssignment($employeeId)

getContacts($employeeId)
```

------------------------------------------------------------------------

## 3.2 Employee Entity

``` php
id
user_id
employee_code
status
created_at
updated_at
```

Không có:

``` php
contact_id
```

------------------------------------------------------------------------

## 3.3 EmployeeAssignmentModel

Table:

    dinktech_employee_assignments

Quan hệ:

    Employee Assignment

    belongsTo:

    Employee
    Organization
    Department
    Position

Methods:

``` php
getActiveAssignments($employeeId)

getPrimaryAssignment($employeeId)
```

------------------------------------------------------------------------

## 3.4 EmployeeAssignment Entity

``` php
id
employee_id
organization_id
department_id
position_id
is_primary
effective_from
effective_to
status
created_at
updated_at
```

------------------------------------------------------------------------

# 4. Contact Domain

## 4.1 ContactModel

Table:

    dinktech_contacts

Contact sử dụng:

    contact_type
    contact_ref_id

Ví dụ:

    EMPLOYEE
    100

hoặc:

    ORGANIZATION
    10

Không tạo Foreign Key trực tiếp.

Methods:

``` php
findByEntity($type,$id)

create(array $data)

update($id,array $data)

delete($id)
```

------------------------------------------------------------------------

# 5. Department / Position Domain

## 5.1 DepartmentModel

Table:

    dinktech_departments

Quan hệ:

    Department

     |
     +-- Parent Department

     |
     +-- Child Departments

     |
     +-- Employee Assignments

Entity:

``` php
id
parent_id
code
name
description
status
created_at
updated_at
```

------------------------------------------------------------------------

## 5.2 PositionModel

Table:

    dinktech_positions

Entity:

``` php
id
code
name
level
description
status
created_at
updated_at
```

Position không có:

-   Organization Scope.
-   Permission Mapping.

------------------------------------------------------------------------

# 6. Resource Authorization Domain

## 6.1 ResourceModel

Table:

    dinktech_resources

Entity:

``` php
id
lifecycle_id
type
name
domain
status
created_at
updated_at
```

Quan hệ:

    Resource

     |
     +-- Resource Actions

     |
     +-- Lifecycle

------------------------------------------------------------------------

## 6.2 ResourceActionModel

Table:

    dinktech_resource_actions

Entity:

``` php
id
resource_id
code
description
```

Permission Code:

    resource.action

Ví dụ:

    invoice.approve
    product.edit

------------------------------------------------------------------------

# 7. Role Domain

## RoleModel

Table:

    dinktech_roles

Role là Global Permission Group.

Không có Organization Scope.

------------------------------------------------------------------------

## RolePermissionModel

Mapping:

    Role

     |

    Resource Action

------------------------------------------------------------------------

# 8. Lifecycle / Workflow Domain

## LifecycleModel

Table:

    dinktech_lifecycles

Quan hệ:

    Lifecycle

     |
     +-- Lifecycle States

     |
     +-- Resources

------------------------------------------------------------------------

## WorkflowStateModel

Entity:

``` php
id
code
name
description
status
created_at
updated_at
```

------------------------------------------------------------------------

## LifecycleStateModel

Methods:

``` php
getStates($lifecycleId)

isValidState($lifecycleId,$stateId)
```

------------------------------------------------------------------------

# 9. Policy Domain

## PolicyModel

Table:

    dinktech_policies

Entity:

``` php
id
organization_id
resource_id
state_id
participant_type
participant_ref_id
created_by
updated_by
created_at
updated_at
```

Participant:

    USER
    ROLE
    POSITION
    DEPARTMENT
    ORGANIZATION

------------------------------------------------------------------------

## PolicyActionModel

Entity:

``` php
id
policy_id
resource_action_id
effect
```

Effect:

    ALLOW
    DENY
    ABSOLUTE_DENY

------------------------------------------------------------------------

# 10. Authorization Service Architecture

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

# 11. Authorization Flow Final

    1. Authenticate User

            |

    2. Load Employee

            |

    3. Resolve Active Employee Assignment

            |

    4. Resolve Organization Context

            |

    5. Load Role Permission

            |

    6. Generate Profile

            |

    7. Resolve Resource Action

            |

    8. Load Matching Policy

            |

    9. Evaluate Effect

            |

    10. Write Audit Log

            |

    11. Return Decision

------------------------------------------------------------------------

# 12. Final Code Implementation Scope

Sẽ triển khai:

## Models

-   OrganizationModel
-   EmployeeModel
-   EmployeeAssignmentModel
-   DepartmentModel
-   PositionModel
-   ContactModel
-   ResourceModel
-   ResourceActionModel
-   RoleModel
-   PolicyModel
-   PolicyActionModel

## Entities

-   Organization
-   Employee
-   EmployeeAssignment
-   Department
-   Position
-   Resource
-   Policy

## Services

-   ContactService
-   AuthorizationService
-   EmployeeContextResolver
-   ProfileResolver
-   PolicyResolver
-   DecisionEngine
-   AuditLogger
