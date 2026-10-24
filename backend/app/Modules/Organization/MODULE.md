# Organization — Module Design Record (Backend Rewrite)

- **MDR state:** IMPLEMENTING. Single business owner for Organization tree; `OrganizationManagement` is not a separate root module.
- **Owned table:** `organizations`. Never grant access using a client-supplied Organization hint instead of a persisted owner/approved parent context.
- **Read:** `OrganizationReadRepository` (including `groupRoots`, `ancestorChain`, tree projection). Implements the factual `Access\Ports\OrganizationFactsPort`; this port does not grant PBAC.
- **Write:** `Mutation/OrganizationMutationController`, `OrganizationMutationService`, `OrganizationMutationRules`, `OrganizationTreeRules`, `OrganizationTransitionRules`.
- **API surface:** `POST /organizations`, `PATCH /organizations/{id}`, `POST /organizations/{id}/actions/set-state`; M2.1 GET/tree/labels remain on the transitional read HTTP adapter `App\Controllers\Api\ReadCatalogController` until a separately approved controller split.
- **Authorization:** checked through Access service for validated parent/create scope or persisted target. Preserve hierarchy, no-cycle, ACTIVE/validity, Province/Ward consistency and Workflow Lifecycle invariants.
- **Transaction owner:** `OrganizationMutationService`; DB locks, audit, commit/rollback remain unchanged by this relocation.
- **Verification:** M5.2 offline + M5.4.1 module ownership tests; live DEV record-PBAC and mutation integration pending.
