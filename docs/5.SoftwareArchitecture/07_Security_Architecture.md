# Security Architecture

## Authentication

Sử dụng Identity Framework.

Responsibilities:

-   Login
-   Session
-   User Identity

## Authorization

Layers:

    Authentication
          |
    Profile Permission
          |
    Policy Restriction
          |
    Decision

## Security Rules

-   Role không có Organization Scope.
-   Policy kiểm soát quyền theo Organization, Resource, State.
-   Audit lưu các quyết định quan trọng.
