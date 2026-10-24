# Contact — Module Design Record (Backend Rewrite)

- **Owner:** `Contact`, exactly one root. Corresponds to Contact in System Architecture Standard v1.1.1; never `ContactsManagement` or a writable `Contact` submodule under Organization/People.
- **Tables:** `contacts` (write). Trusted owner facts are read-only via Organization persistence and `AccessReadAdapters::people()` (PeopleFactsPort); no writes to People or Organization tables.
- **Owner invariant:** `contact_type` is immutable (`ORGANIZATION`/`EMPLOYEE` only); PBAC resource is respectively `organization_contact`/`employee_contact`. Legacy `contact` is not an authorization resource. Scope is derived server-side from persisted Organization or unique effective primary Employee Assignment, never the client.
- **HTTP:** ContactController owns two GET and three mutations, all pinned to API-010. GET per-row VIEW precedes search/filter/page totals. POST/PATCH require authenticated actor and existing DEV write gate; edit also requires persisted VIEW, PBAC and owner immutability.
- **State:** initial status from configured Lifecycle for selected resource; PATCH cannot change status. POST set-state checks one approved `setstate` transition and PBAC including the actual transition action.
- **Transaction:** mutation service owns Contact writes, row locking on PATCH/set-state, created_by/updated_by from authenticated actor, rollback on failure.
- **Locales:** stable `CONTACT_*` codes map through baseline en/vi Contact dictionaries. No exception trace exposed to API responses.
- **Limitations:** live PBAC/HTTP/SQL and contention with concurrent People assignment changes remain unverified; full production acceptance is not claimed. Existing 105-route source footprint must remain intact.
