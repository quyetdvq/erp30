# Locations — Module Design Record (Backend Rewrite)

- **MDR state:** IMPLEMENTING. Owns Provinces and Wards (called logical Foundation location master in the 12-root System Architecture Standard); no parallel `FoundationManagement` module.
- **Owned tables:** `provinces`, `wards`. No direct write to Organization/Contact addresses.
- **Read:** `LocationReadRepository`. M2.1 public GET remains at the transitional `App\Controllers\Api\ReadCatalogController` (PBAC applied before paging).
- **Write:** `Mutation/LocationMutationController`, `LocationMutationService`, `LocationMutationRules`, `LocationTransitionRules`.
- **Authorization and invariants:** Access PBAC, assigned Lifecycle, unique codes, Province ACTIVE requirement, existing Organization/Contact reference integrity; write gate remains explicit.
- **Transaction owner:** `LocationMutationService`. API contract and state/error codes are unchanged by M5.4.1.
- **Verification:** M5.1 offline tests; live DEV HTTP/MariaDB mutation and record-PBAC still pending.
