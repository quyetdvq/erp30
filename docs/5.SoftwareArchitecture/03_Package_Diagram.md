# Package Diagram

    app
     |
     +-- Controllers
     |
     +-- Services
     |      |
     |      +-- AuthorizationService
     |      +-- PolicyResolver
     |      +-- DecisionEngine
     |
     +-- Models
     |
     +-- Entities
     |
     +-- Database
            |
            +-- Migrations

Package dependency:

Controllers depend on Services.

Services depend on Models.

Models depend on Database.
