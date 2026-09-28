# Component Diagram

    +----------------+
    | User Interface |
    +----------------+
            |
            v
    +----------------+
    | Controller     |
    +----------------+
            |
            v
    +----------------------+
    | AuthorizationService |
    +----------------------+
            |
     +------+------+
     |             |
     v             v
    Profile     Policy
    Resolver    Resolver

            |
            v

    Decision Engine

            |
            v

    Audit Logger
