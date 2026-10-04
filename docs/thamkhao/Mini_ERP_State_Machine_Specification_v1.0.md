# Mini ERP State Machine Specification v1.0

## 1. Purpose

Tài liệu mô tả State Machine Model cho Mini ERP.

Mục tiêu: - Xác định vòng đời Resource. - Quản lý State. - Quản lý State
Transition. - Kết hợp Workflow với Authorization.

------------------------------------------------------------------------

# 2. State Machine Concept

    Resource
     |
    Lifecycle
     |
    Lifecycle State
     |
    Workflow State
     |
    State Transition

Resource là đối tượng nghiệp vụ: - Invoice - Sales Order - Purchase
Order - Product

Lifecycle định nghĩa vòng đời của Resource.

Workflow State là trạng thái hiện tại của Resource.

------------------------------------------------------------------------

# 3. State

State đại diện trạng thái của Resource.

Ví dụ:

    DRAFT
    SUBMITTED
    APPROVED
    POSTED
    CANCELLED

Rules: - State phải thuộc Workflow State Catalog. - State phải được
mapping vào Lifecycle. - Một Lifecycle chỉ có một Initial State. - Có
thể có Final State.

------------------------------------------------------------------------

# 4. Lifecycle State Mapping

Quan hệ:

    Lifecycle
     |
    Lifecycle State
     |
    Workflow State

Ví dụ Invoice:

    DRAFT
     |
    SUBMITTED
     |
    APPROVED
     |
    POSTED
     |
    CANCELLED

------------------------------------------------------------------------

# 5. State Transition

Transition mô tả chuyển trạng thái.

Ví dụ:

    DRAFT
     |
    Submit
     |
    SUBMITTED

Invoice Example:

  Current     Action    Next
  ----------- --------- -----------
  DRAFT       submit    SUBMITTED
  SUBMITTED   approve   APPROVED
  SUBMITTED   reject    DRAFT
  APPROVED    post      POSTED
  POSTED      cancel    CANCELLED

------------------------------------------------------------------------

# 6. Transition Rules

-   Source State phải đúng State hiện tại.
-   Target State phải thuộc Lifecycle.
-   Transition phải qua Authorization Check.

Flow:

    User
     |
    Action
     |
    Authorization
     |
    State Transition
     |
    New State

------------------------------------------------------------------------

# 7. Workflow và Authorization

Authorization:

    User có quyền thực hiện Action không?

Workflow:

    Action có làm Resource đổi State không?

Ví dụ:

Invoice:

    SUBMITTED
        |
     approve
        |
    APPROVED

User cần:

    invoice.approve

------------------------------------------------------------------------

# 8. State Based Policy

Policy có thể dựa trên State.

Ví dụ:

    Resource:
    Invoice

    State:
    SUBMITTED

    Participant:
    Accounting Manager

    Action:
    approve

    Effect:
    ALLOW

Ví dụ:

    Resource:
    Invoice

    State:
    POSTED

    Action:
    edit

    Effect:
    DENY

------------------------------------------------------------------------

# 9. Initial / Final State

Initial State: - Trạng thái khi Resource được tạo.

Ví dụ:

    Invoice = DRAFT

Final State: - Trạng thái kết thúc vòng đời.

Ví dụ:

    POSTED
    CANCELLED

------------------------------------------------------------------------

# 10. Default Lifecycle Examples

## Invoice

    DRAFT
    SUBMITTED
    APPROVED
    POSTED
    CANCELLED

## Sales Order

    DRAFT
    CONFIRMED
    PROCESSING
    COMPLETED
    CANCELLED

## Purchase Order

    DRAFT
    SUBMITTED
    APPROVED
    ORDERED
    RECEIVED
    CLOSED

------------------------------------------------------------------------

# 11. Current Scope

Hỗ trợ: - Lifecycle. - Workflow State. - State Based Authorization.

Chưa hỗ trợ: - Parallel Workflow. - Approval Route Engine. - Condition
Transition. - SLA Timer. - Dynamic Rule Engine.

------------------------------------------------------------------------

# 12. Future Extension

Có thể bổ sung:

    workflow_transitions

    id
    lifecycle_id
    from_state_id
    action
    to_state_id
