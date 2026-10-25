# Import module owner - M8 shared framework

`App\Modules\Import` owns the import pipeline, versioned template manifest,
bounded CSV/XLSX readers, reusable adapter interface, job orchestration,
row results, and SQL-filtered job pagination. It does not write Product,
Pricing, People, or other business tables directly.

Each business owner supplies an `ImportAdapter` for its own template,
transactional tables, row mutations, PBAC and job read scope. M8 provides
the first adapter under `App\Modules\Product\Import` and four frozen API-060
routes. A future adapter can reuse core pipeline without changing its state
machine or persistence logic. An adapter must still supply trustworthy
scope compilation for its module and run existing Application Services.

CSV/XLSX assets under frontend/public/import-templates are generated from the
same versioned manifest used by the backend parser. The shared Vue wizard
accepts module-specific labels, template IDs and start handlers. There is
no dynamic download endpoint or unreviewed API route.

A job is bounded and synchronous (max 1000 rows, 2 MiB), with per-row service
transactions and an immediately persisted result journal; the entire job is
NOT atomic. A FAILED job may contain committed business rows. No automatic
retry or implicit rollback is permitted. InnoDB assertions and the default-off
write gate protect mutations. Live HTTP/DB performance, recovery and parallel
imports remain unverified; production cutover BLOCKED.
