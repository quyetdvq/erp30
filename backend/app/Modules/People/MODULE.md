# People — Module Design Record (Backend Rewrite)

- **MDR state:** IMPLEMENTING. Single business owner for Department, Position, Employee and Employee Assignment. Equivalent logical ownership to `EmployeeStructure` in the 12-root System Architecture Standard; current v1.2.4 Backend Rewrite uses the **People** runtime name, not a second EmployeeStructure root.
- **Owned tables:** `departments`, `positions`, `employees`, `employee_assignments`. Shield user account/identity belongs to Identity, not People; Contact master belongs to future Contacts owner.
- **Read:** `PeopleReadRepository` for factual employee, department, position and effective assignment reads; implements `Access\Ports\PeopleFactsPort` (scope facts only, no authorization decision).
- **Employee/Assignment mutations:** `Mutation/PeopleMutationController`, `PeopleMutationService`, `PeopleMutationRules`, `PeopleTransitionRules`.
- **Department/Position mutations:** `Structure/Mutation/StructureMutationController`, `StructureMutationService`, `StructureMutationRules`; Structure is a **capability inside People**, not a new root module.
- **Authorization:** centralized Access PBAC with persisted Organization scopes and Lifecycle/Native State as applicable. Employee manager cycles, Department parent cycles, interval validity and primary-assignment overlap remain protected.
- **Transaction owner:** individual People/Structure mutation services; existing row locks/rollback remain unchanged.
- **Read HTTP:** existing M2.1 endpoints stay on `App\Controllers\Api\ReadCatalogController` as a reviewed compatibility adapter; future owner-specific read controllers require their own parity tests.
- **Verification:** M5.3/M5.4 offline + M5.4.1 ownership; live DEV employee/assignment/structure HTTP and concurrency still pending.
