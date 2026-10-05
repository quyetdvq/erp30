# Idea Organization & Employee Authorization

## ERP System Definition Document - Phiên bản tổng hợp

## 1. Thông tin tài liệu

**Tên tài liệu:** Idea Organization & Employee Authorization\
**Loại tài liệu:** Tài liệu tổng hợp mô tả về hệ thống: Nhân viên, Chức danh, phòng ban, Organization (Group/Company/Branch/Plant/Warehouse) phân quyền theo Resource và State\
**Mục đích:** Tổng hợp yêu cầu nghiệp vụ, mô hình tổ chức, quản lý nhân viên, định danh người dùng, phân quyền và định hướng kiến trúc hệ thống ERP. Đây là tài liệu trao đổi yêu cầu tổng hợp giữa người sử dụng và người lập trình, bao gồm Business Requirement, Technical Design, Database Concept, Authorization Design và định hướng kiến trúc ERP.

**Version 1.7**

**Trạng thái:** Nội dung chốt trước triển khai, đã đồng bộ với `Mini_ERP_Authorization_Database_Design_v1.2.md`.

**Authority boundary:** Database Design v1.2 là authority cho table, column, relationship, key, index và migration order. Tài liệu này là baseline nghiệp vụ/kiến trúc và không được diễn giải vượt quá Database Design.

------------------------------------------------------------------------

# 2. Mục tiêu hệ thống

Hệ thống định hướng là một Mini ERP Multi-Organization.

Phạm vi tập trung:

-   Employee Management.
-   User Identity.
-   Organization Management.
-   Department.
-   Position.
-   Organization Context.
-   Permission.
-   Profile.
-   Policy Rule.
-   Resource Authorization.
-   State Based Authorization.

Mục tiêu:

-   Quản lý nhiều Organization trong cùng một hệ thống.
-   Quản lý tổ chức theo Organization Context.
-   Quản lý Employee và User Identity.
-   Phân quyền theo Resource + State + Organization Context.
-   Thiết kế đơn giản, dễ mở rộng cho ERP.

Công nghệ:

-   Backend: CodeIgniter 4.
-   Frontend: Vue.
-   Identity: CodeIgniter Shield/Setting.


```
    User
        │
        ▼
    Employee
        │
        ▼
    Employee Assignment
        │
        ├── Organization Context
        ├── Department
        └── Position


    Policy
        ├── Organization Context
        ├── Resource
        ├── State
        ├── Participant
        └── Effect

    Authorization Engine
        ├── Profile
        ├── Resource Context
        ├── Policy Resolver
        └── Decision
```
------------------------------------------------------------------------

# 3. CodeIgniter Package Integration

## 3.1 CodeIgniter/Shield

Shield chịu trách nhiệm:

-   Authentication.
-   User Identity.
-   Session / Token.
-   Base Group.
-   Permission.

Shield trả lời:

    User có quyền thực hiện Action này không?

Ví dụ:

    invoice.view
    invoice.approve
    product.create

Shield không chịu trách nhiệm:

-   Organization Scope.
-   Resource Scope.
-   Workflow State.
-   ERP Business Rule.

Các phần này thuộc ERP Authorization Extension.

------------------------------------------------------------------------

## 3.2 Settings

Settings là Configuration Platform.

Bao gồm:

### System Settings

Ví dụ:

    timezone
    currency
    security.policy

### Organization Settings

Ví dụ:

    Organization.currency
    inventory.allow_negative_stock
    sales.require_approval

### Module Settings

Ví dụ:

    workflow.enable
    notification.enable

------------------------------------------------------------------------

## 3.3 Tasks

Tasks phục vụ Scheduled Job:

-   Generate report.
-   Cleanup log.
-   Synchronization.
-   Daily calculation.

Không thuộc Authorization.

------------------------------------------------------------------------

## 3.4 Queue

Queue xử lý bất đồng bộ.

Ví dụ:

    Invoice Approved
            |
          Queue
            |
     + Send Email
     + Generate PDF
     + Update Accounting

Không thuộc Permission Evaluation.

------------------------------------------------------------------------

## 3.5 Cache

Cache sử dụng cho:

-   Authorization decision.
-   Configuration lookup.
-   Performance optimization.

Ví dụ:

    auth:user:{id}:resource:{type}:permission:{action}

Invalidate khi:

-   Group thay đổi.
-   Permission thay đổi.
-   Policy thay đổi.
-   Employee mapping thay đổi.

------------------------------------------------------------------------

# 4. Organization Model

## 4.1 Organization Context

Organization Context là phạm vi tổ chức dùng trong ERP.

Organization Context Type là danh sách cố định.

Các loại được hỗ trợ:

-   GROUP
-   COMPANY
-   BRANCH
-   PLANT
-   WAREHOUSE

System Admin không được tạo thêm Organization Context Type mới.



Tất cả đều có cùng bản chất:

    Organization Context

Khác nhau chỉ ở cấp độ trong cây tổ chức.

Ví dụ:

    Group
     |
     Company
     |
     Branch
     |
     Plant
     |
     Warehouse

Organization Context dùng cho:

-   Organization hierarchy.
-   Data scope.
-   Policy inheritance.
-   Business filtering.
-   Permission evaluation.

## 4.2 Organization Hierarchy

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

Không hard-code cấu trúc tổ chức.

------------------------------------------------------------------------

# 5. Employee Model

Employee không liên kết trực tiếp với Organization, Department hoặc Position.

Quan hệ:

    Employee
     |
     Employee Assignment
     |
     Organization Context
     Department
     Position

## 5.1 Employee Assignment

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

## 5.2 Primary Organization

Employee có Primary Organization Assignment.

Mục đích:

-   Default UI Context.
-   Thông tin nhân sự mặc định.
-   Tính lương.
-   Các nghiệp vụ cần Organization mặc định.

Primary Organization không thay thế Employee Assignment.


## 5.3 Ví dụ Employee

Ví dụ:

    Employee A

    Primary Organization:
    Organization A

    Other:
    Organization B
    Organization C

------------------------------------------------------------------------

# 6. User Identity

User là Identity phục vụ Authentication.

Quan hệ:

    Shield User

    0..1

    Employee

Quy tắc:

-   Employee không bắt buộc phải có User.
-   Một User tối đa liên kết với một Employee.
-   User không liên kết Employee không được truy cập Resource ERP.

Ngoại lệ:

-   System Admin không bắt buộc có Employee.

------------------------------------------------------------------------

# 7. Department và Position

Department và Position là master độc lập theo Database Design v1.2.

Department có hierarchy riêng thông qua `parent_id`. Position có `level` và không có `organization_id`. Hai master này không sinh Permission, không tự mapping Group và không được hiểu là các bảng con vật lý của organizations.

Employee chỉ liên kết Department, Position và Organization thông qua
`employee_assignments`.

Position:

-   Không sinh Permission.
-   Không tự mapping Group.
-   Chỉ thể hiện cơ cấu tổ chức.

Department có thể được sử dụng trong Assignment của Employee tại bất kỳ Organization Context nào: Group, Company, Branch, Plant hoặc Warehouse.
Điều này là quan hệ nghiệp vụ qua `employee_assignments`, không phải
`departments.organization_id`.

------------------------------------------------------------------------

# 8. Group và Profile

## 8.1 Group

Group là nhóm Permission.

Group không đại diện cho:

-   Job Title.
-   Position.
-   Department.

Ví dụ:

    Group:
    Accountant Manager

    Permissions:
    invoice.view
    invoice.approve
    invoice.cancel

Group Permission là Permission catalog dùng để tạo Profile.

Group chỉ là tên nhóm quyền giúp System Admin gán Permission nhanh. Group
Permission không tự giới hạn Organization Scope. Organization Scope được xác định thông qua Employee Assignment, Organization Context và Policy Rule.

------------------------------------------------------------------------

## 8.2 Profile

Profile là tập Permission hiệu lực.

Công thức:

    Profile =
    Group Permission
    +
    User Override

User Override chỉ hỗ trợ ALLOW addition.

Không hỗ trợ DENY.

Policy chịu trách nhiệm giới hạn quyền.

Ví dụ:

Group Permission:

    invoice.view

User Override:

    invoice.approve

Profile:

    invoice.view
    invoice.approve

------------------------------------------------------------------------

# 9. Resource Authorization

Resource đại diện cho đối tượng ERP.

Ví dụ:

-   Invoice.
-   Product.
-   Inventory.
-   Employee.
-   Sales Order.

Resource Type:

    Invoice

Resource Instance:

    Invoice #INV00001

Resource Instance chỉ là khái niệm nghiệp vụ minh họa. Database Design v1.2 chỉ triển khai authorization ở Resource Type level; không có
`resource_instance_id`, instance authorization hoặc condition rule.

Permission Catalog:

    resource.action

Ví dụ:

    product.create
    product.edit
    invoice.approve
    invoice.cancel

------------------------------------------------------------------------

## Standard Action

    create
    view
    edit
    delete

## Business Action

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

# 10. Policy Rule

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

-   Organization Context trả lời: Rule này được khai báo ở đâu?
-   Resource Type trả lời: Rule áp dụng cho loại đối tượng nào?
-   State trả lời: Rule áp dụng khi đối tượng ở trạng thái nào?
-   Participant trả lời: Rule áp dụng cho ai?
-   Permission trả lời: Cho phép hay từ chối thao tác nào?
-   Effect trả lời: Allow, Deny hay Absolute Deny?

## Participant Type

Participant chỉ là cách xác định Policy Rule được áp dụng cho ai.

Hỗ trợ:

USER 
GROUP (Nhóm phân quền không phải Group trong Organization Context)
POSITION 
DEPARTMENT 
ORGANIZATION

Không sử dụng:

    Group
    Branch
    Plant
    Warehouse

vì Group, Branch, Plant, Warehouse đều là Organization Context.

------------------------------------------------------------------------

## Policy Effect

Policy không tạo Permission mới. Policy chỉ giới hạn Permission đã có trong Profile.

Thứ tự:
```
    ABSOLUTE DENY
            >
          ALLOW
            >
           DENY
```
Không sử dụng Policy Priority..

DENY 
Có thể bị override.

Ví dụ:

Group: invoice.delete ALLOW

Policy: invoice.delete DENY

Kết quả: invoice.delete ALLOW


ABSOLUTE DENY
Không thể override.



Quy tắc mặc định:

    Profile có quyền
    +
    Không bị DENY
    =
    ALLOW

Profile Permission luôn được xem là ALLOW trước khi Policy Restriction đánh giá.

User Override chỉ hỗ trợ ALLOW addition. Không hỗ trợ DENY.


##. Permission

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

# 11. Resource Scope

Resource đại diện cho đối tượng ERP.

Ví dụ:

-   Invoice
-   Product
-   Inventory
-   Employee
-   Sales Order

Authorization hỗ trợ:

-   Resource Type.
-   State.

Resource Instance không thuộc phạm vi triển khai của baseline này.

Chưa hỗ trợ Condition Rule theo giá trị nghiệp vụ.

Ví dụ chưa hỗ trợ:

    Invoice amount > 100000000

Authorization dựa trên:

    Organization Context
    +
    Resource
    +
    State

Không sử dụng:

-   Own Data Scope.
-   Department Data Scope.
-   Group Data Scope.

Organization Context chỉ là bộ lọc dữ liệu nghiệp vụ trong mô hình One Database.

------------------------------------------------------------------------

# 12. Workflow Separation

Authorization và Workflow là hai khái niệm riêng.

Authorization trả lời:

    User có quyền làm không?

Workflow trả lời:

    Resource có được chuyển State không?

Ví dụ:

    DRAFT
     |
    SUBMITTED
     |
    APPROVED
     |
    POSTED

State được sử dụng trong Policy Rule.

State thuộc Workflow Engine.

State Catalog là bảng riêng thuộc System Domain và do System Admin quản lý.

Workflow Engine chịu trách nhiệm quản lý State và State Transition.
Authorization chỉ kiểm tra User có quyền thực hiện Action hoặc
Transition tương ứng hay không.

------------------------------------------------------------------------
ABSOLUTE Deny
# 13. Database Architecture

Hệ thống sử dụng:

    One Database

Tất cả Organization dùng chung database.

Business table bắt buộc tham chiếu Organization Context.

Module được phép chọn mọi Organization Context Level phù hợp với nghiệp vụ.

Ví dụ:

    Invoice

    id
    organization_id
    state
    amount

Khái niệm tổng quát hơn là `organization_id` để tham chiếu Organization
Context Node.

------------------------------------------------------------------------

# 14. UI/UX Organization

Organization hiển thị dạng cây:

        Group
        └── Company
            ├── Branch
            ├── Plant
            └── Warehouse

Department và Position được hiển thị trong context của Organization thông qua Employee Assignment; chúng không phải node con vật lý của organizations.

## 14.1. Khi click Organization Node:

Hiển thị:

-   Policy Rule trực tiếp của node hiện tại.
-   Không copy Policy từ Parent xuống Child.

Policy inheritance chỉ xử lý khi Evaluation.

Policy của Parent Organization Context có thể được kế thừa xuống Child Organization Context.

## 14.2. Khi click Department Node:

Hiển thị:

-   Danh sách Employee thuộc Department.

## 14.3. Khi click Position Node:

Hiển thị:

-   Danh sách Employee thuộc Position.

## 14.4 Organization Filter

Khi hiển thị dữ liệu nghiệp vụ:

-   Chỉ hiển thị Organization Context mà User được phép truy cập.

Ví dụ:

    Invoice List

    [x] Organization A
    [x] Organization B
    [ ] Organization C

------------------------------------------------------------------------

# 15. Business Scenario

## Invoice Approval

Yêu cầu:

Accounting Manager được duyệt Invoice đã Submit.

Policy:

    Particpants:
    Position = Accounting Manager

    Resource:
    Invoice

    Permission:
    invoice.approve

    State:
    SUBMITTED

    Effect:
    ALLOW

------------------------------------------------------------------------

## Invoice Editing Restriction

Yêu cầu:

Invoice đã POSTED không được sửa.

Policy:

    Resource:
    Invoice

    Permission:
    invoice.edit

    State:
    POSTED

    Effect:
    DENY

------------------------------------------------------------------------

## Inventory Access

Nhân viên kho quản lý Inventory.

Authorization:

    Organization Context:
    Warehouse WH01

    Resource:
    Inventory

    Permission:
    inventory.view
    inventory.edit

Không cần Warehouse Particpants.

------------------------------------------------------------------------

# 16. Permission Evaluation Algorithm

Luồng đánh giá:

    1. Authenticate User

            |

    2. Load Employee Mapping

            |

    3. Resolve Available Organization Context

            |

    4. Load Group Permission

            |

    5. Load User Override

            |

    6. Generate Profile Permission

            |

    7. Load Resource Context

            |

    8. Load Matching Policy

            |

    9. Evaluate Policy Effect

            |

    10. Audit Decision

            |

    11. Return Final Permission

------------------------------------------------------------------------

# 17. Audit

Audit phục vụ:

-   Debug.
-   Kiểm tra Authorization Decision.

Có thể lưu:

-   User.
-   Resource.
-   Action.
-   State.
-   Decision.
-   Matched Policy.
-   Reason.

------------------------------------------------------------------------

# 18. Default Groups

Default Group:

    Super Admin
    Organization Admin
    Accountant Manager
    Warehouse Manager
    Employee

Organization Admin là User bình thường được cấp thêm quyền quản trị.

------------------------------------------------------------------------

# 12. Organization Scope

Business table bắt buộc có:

    organization_id

organization_id có thể tham chiếu tới bất kỳ Organization Context Type nào.

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

# 19. System Admin

System Admin là Root User.

Có:

-   Toàn bộ Permission.
-   Toàn bộ Organization Context.
-   Bypass Authorization Extension.
-   Bypass Policy Rule.
-   Bypass Workflow Transition.

------------------------------------------------------------------------

# 20. Không hỗ trợ

Hệ thống không hỗ trợ:

-   Delegation Permission.
-   Temporary Permission.
-   Permission effective_from/effective_to.

------------------------------------------------------------------------

# 21. Multi-language

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

# 23. Permission Catalog

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

------------------------------------------------------------------------

# 23. Clarification bổ sung phiên bản 1.6

Phần này bổ sung các quy tắc được xác nhận nhằm loại bỏ các điểm còn mơ
hồ trong thiết kế.

## 23.1 Employee và Organization Context

-   Employee có thể thuộc nhiều Organization Context khác nhau.
-   Employee có thể có nhiều Position tại nhiều Organization Context khác nhau.
-   Quan hệ tổ chức:


```
    Organization Context
            |
        Department
            |
        Position
            |
        Employee

-   Primary Organization Context không bắt buộc là Company.
-   Primary Organization Context có thể là:

```

```
    GROUP
    COMPANY
    BRANCH
    PLANT
    WAREHOUSE
```
## 23.2 Department và Position trong Organization Context

Department và Position là master độc lập, không có `organization_id`.

Khi hiển thị theo Organization Context, hệ thống lấy quan hệ từ
`employee_assignments`:

    Organization Context
            |
    Employee Assignment
        /           \
    Department    Position
            |
        Employee

Department có hierarchy riêng qua `parent_id`; Position có `level`. Không được tạo foreign key hoặc column `organization_id` trong departments hoặc positions chỉ để biểu diễn màn hình cây.

## 23.3 Policy Inheritance

Policy được kế thừa từ Parent Organization Context xuống Child Organization Context.

Tất cả loại Policy đều áp dụng cơ chế inheritance.

Khi Parent và Child cùng có Policy thì không có ưu tiên theo cấp cha/con. Không có ưu tiên Policy chính thức.

Kết quả cuối cùng luôn áp dụng Effect Priority:

    ABSOLUTE DENY
            >
          ALLOW
            >
           DENY

## 23.4 Participant trong Policy

Participant = ORGANIZATION có nghĩa là Policy áp dụng cho tất cả User thuộc Organization Context đó.

Participant ROLE chỉ là cách xác định các User thuộc Group đó được áp dụng Policy.

Group vẫn đồng thời có thể sử dụng trong Profile để quản lý Permission nhanh.

## 23.5 Resource Authorization Boundary

Theo Database Design v1.2, authorization chỉ đánh giá ở:

-   Resource Type.
-   Workflow State.

Resource Instance Authorization không được triển khai trong baseline này.
Không có `resource_instance_id` và không hỗ trợ Condition Rule theo giá trị nghiệp vụ như amount hoặc value limitation. Đây là giới hạn thiết kế, không phải tùy chọn cấu hình runtime.

## 23.6 Permission Evaluation Default Rule

Quy tắc mặc định:

    Profile có quyền
    +
    Không bị DENY
    =
    ALLOW

Nếu không có Policy Match thì quyền trong Profile vẫn được phép thực hiện.

## 23.7 System Admin và Workflow

System Admin là Root User.

System Admin:

-   Có toàn bộ Permission.
-   Có toàn bộ Organization Context.
-   Bypass toàn bộ Policy Rule.
-   Bypass Workflow và State Transition.

## 23.8 Organization Context Status

Organization Context có quản lý trạng thái hoạt động.

Có thể lưu các thông tin:

    status
    active_from
    active_to

Mục đích hỗ trợ quản lý Organization Context đã ngừng hoạt động nhưng vẫn giữ dữ liệu lịch sử.

## 23.9 Resource Visibility

Không sử dụng các Data Scope riêng như:

-   Own Data Scope.
-   Department Data Scope.
-   Group Data Scope.

Quyền truy cập Resource được xác định thông qua Employee Mapping.

Luồng xác định:

    User
     |
    Employee
     |
    Organization Context
     |
    Resource Access

User chưa liên kết với Employee sẽ không xem được bất kỳ Resource nào.

## 23.10 Default Group

Default Group là dữ liệu khởi tạo (Seeder).

Các Group mặc định phục vụ khởi tạo hệ thống và có thể được quản lý theo thiết kế Permission Management.

## 23.11 Mục tiêu tài liệu

Tài liệu này là tài liệu tổng hợp giữa Business Requirement, Technical Direction, Database Concept và Authorization Concept.

Mục tiêu chính:

-   Giúp người sử dụng mô tả mong muốn về hệ thống.
-   Giúp BA và lập trình viên hiểu thống nhất chức năng cần xây dựng.
-   Làm nền tảng trao đổi trước khi triển khai các tài liệu kỹ thuật chuyên sâu riêng biệt.

Các tài liệu chi tiết như Database Design, Authorization Engine Design, UI Design hoặc ERP Architecture sẽ được xây dựng riêng khi cần.

# 24. Clarification bổ sung phiên bản 1.7

## Organization Context

-   Organization Context Type là cố định.
-   Không tạo thêm Type mới.
-   Hỗ trợ nhiều Group trong một hệ thống.
-   Organization Tree không cho phép vòng lặp hoặc cấp dưới làm cấp trên.

## Employee Assignment

-   Employee liên kết Organization thông qua Employee Assignment.
-   Employee Assignment có lịch sử bằng effective_from và effective_to.

## Authorization

-   Profile = Group Permission + User Override.
-   User Override chỉ bổ sung Allow Permission.
-   Policy không cấp thêm quyền.
-   Policy Effect:


```
    ABSOLUTE DENY
            >
          ALLOW
            >
           DENY
```
## Resource Authorization

-   Hỗ trợ Resource Type.
-   Không hỗ trợ Resource Instance Authorization.
-   Không hỗ trợ Condition Rule theo giá trị.

## Workflow

-   State thuộc Workflow Engine.
-   State Catalog thuộc System Domain.

## Permission Management

-   System Admin quản lý Permission Catalog.
-   Không hỗ trợ Delegation Permission.
-   Không hỗ trợ Temporary Permission.

------------------------------------------------------------------------

# 25. Design Principles

1.  Organization Context là phạm vi tổ chức.
2.  Group, Company, Branch, Plant, Warehouse đều là Organization Context.
3.  Position không phải Permission.
4.  Group Position chỉ là nhóm quyền quản trị.
5.  Profile = Group Permission + User Override.
6.  Policy chỉ hạn chế Profile. Policy không cấp thêm quyền.
7.  Authorization dựa trên Organization Context + Resource + State.
8.  Business Data thuộc một Organization Context, có tính chất kế thừa 2 chiều trong sơ đồ cây tổ chức.
9.  Workflow và Authorization tách biệt.
10. Mô hình phải đơn giản, dễ kiểm soát và mở rộng cho ERP.
