# Product module ownership — M6.1

One canonical Product business root per System Architecture Standard v1.1.1 and M5.4.1.

- `Catalog/`: Product Group hierarchy and Product UOM master (ten frozen API-050 operations).
- `Master/`: M6.2A protected Product Master GET list/detail and M6.2B guarded Product mutations (CREATE/PATCH/set-state), with full-catalog derived role, validated public-upload URLs, PBAC-first pagination, lifecycle checks and one Product mutation transaction.
- **Not implemented yet**: Product selector/resolution/lookups, Avatar media, independent Combo editing, UOM conversion sets or Import. Do not create fake HTTP routes or duplicate `ProductManagement` roots.
- All authorization delegates to Access PBAC, and State to the canonical Workflow Lifecycle definition. No client-supplied policy scope/state grants.
- Product Group edits use one server-side hierarchy mutex plus locked SQL graph; duplicate codes are also enforced by DB UNIQUE(code).
- Product UOM relies on persisted UNIQUE(code) and transaction rollback, never on a client lock.
- Frozen `ProductGroup` and `ProductUomMaster` EN/VI catalogs remain unchanged; ApiResponder maps stable domain codes to their translation keys.
- PATCH rejects direct status edits; status changes require the reviewed set-state transition and both PBAC checks.

- `Shared/Media/PublicImageStorage` (shared physical storage) uses `backend/public/upload/YYYY/` with the frozen `YYYYMMDDproduct|avatar{7 Unix digits}` naming rule; Product owns image metadata and future authorized upload/delete operations. No media mutation routes are opened by M6.2A.1.

## M6.2B Product Master writes

`Master/ProductMutation*` owns `POST /products`, `PATCH /products/{id}` and
`POST /products/{id}/actions/set-state`. The write gate is explicit;
`UnifiedAccessService` authorizes persisted rows and server-owned initial states.
One physical MariaDB transaction covers Product, GROUP numbering counter and
COMBO composition, serialized by a Product advisory lock. CLIENT values never
provide status, persisted ownership or evaluation organization for PBAC.

Important boundaries: GROUP-scoped Product numbering is supported, as is timeless
ORGANIZATION-scoped numbering resolved to the unique authoritative GROUP root.
COMPANY-scoped numbering and contextual ORGANIZATION numbering fail closed
until an authoritative scope resolver is integrated. Transaction history has
no persisted provider in the current v1.2.4 schema; existing Price List/UOM/
COMBO usage is guarded, and the historical transaction lock must be extended
when transaction tables are introduced. No placeholder media/lookup routes.

M6.2B.4 adds **DEV-only QA** for two independently authenticated HTTP AUTO Product issuers and COMBO counter rollback, gated on an already configured ACTIVE GROUP default series. No additional Product API operations or owner modules. See `docs/backend-rewrite/M6_2B_4_NUMBERING_COMBO_ATOMICITY_QA.md`; a source/offline pass does not establish the DEV HTTP verdict.

## M6.3A Product Representative Media

`Media/ProductImageController` and `Media/ProductImageService` own precisely
`POST /products/{id}/image` and `DELETE /products/{id}/image`. Media uses the
existing shared GD-backed `PublicImageStorage`, writing only validated filename
metadata to `products` and static bytes to `backend/public/upload/YYYY/`.
`product.edit` on the locked persisted row, `product.view`, Shield login and
the standard mutation gate are required. Operations share Product Master's
MariaDB advisory lock; filesystem cleanup occurs only after SQL commit.
Legacy private filenames are never exposed or deleted automatically. The
Product image GET route remains intentionally absent.
