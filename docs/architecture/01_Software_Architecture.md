# Software Architecture

**Status:** Official implementation design

## 1. Scope

The system is a CodeIgniter 4 backend with a Vue frontend and CodeIgniter
Shield as the identity/authentication authority. It is a one-database,
multi-organization ERP authorization foundation.

## 2. Layers

```text
Presentation (Vue / HTTP)
        |
Application (controllers, request validation, transaction boundary)
        |
Domain services (organization, employee, lifecycle, authorization)
        |
Persistence (models, repositories, query builders)
        |
MySQL/MariaDB shared database
```

Controllers do not resolve policy directly. They call application/domain
services, which load the authenticated identity, employee assignments,
effective profile permissions, resource context and matching policy before
returning a decision.

## 3. Authorities

- Shield owns authentication, users, sessions/tokens and base identity.
- The ERP domain owns Organization Context, Employee, Assignment, Resource,
  Lifecycle, Policy and Authorization Log data.
- The Workflow domain owns state catalog and lifecycle-state mapping.
- Settings owns configuration; it is not an authorization data store.

## 4. Core request path

```text
HTTP request
  -> authenticate with Shield
  -> resolve Employee and active assignments
  -> resolve Organization Context
  -> resolve resource action and workflow state
  -> build effective profile permission
  -> load matching resource-level policy
  -> evaluate effect priority
  -> write required authorization audit
  -> allow or deny
```

## 5. Boundaries

Business tables outside this baseline must carry `organization_id` when they
are organization-scoped. Authorization does not implement own-data,
department-data or group-data scopes; Organization Context is the business
data filter.
