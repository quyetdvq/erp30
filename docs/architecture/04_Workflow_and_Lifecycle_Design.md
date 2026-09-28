# Workflow And Lifecycle Design

**Status:** Official implementation design

## 1. Separation

Workflow answers whether a resource may transition state. Authorization answers
whether the user may perform an action or transition. The two decisions are
separate and must not be collapsed into a permission column.

## 2. Catalogs

- `workflow_states` is the system-wide state catalog.
- `lifecycles` defines a lifecycle scheme and its status.
- `lifecycle_states` maps states to a lifecycle with sequence, initial and final markers.
- `resources.lifecycle_id` selects the lifecycle for a resource type.

## 3. Transition boundary

The workflow service owns legal transition validation. The authorization engine
checks the action permission in the current state. A successful authorization
decision does not itself perform a state transition.

## 4. Audit

Authorization logs must capture state when evaluating state-sensitive actions.
The v1.2 audit scope covers approve, delete and state-change decisions; it does
not require View/List decision logging.
