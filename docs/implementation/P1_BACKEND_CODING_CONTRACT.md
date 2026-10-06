# P1 Backend Coding Contract

This is a non-authoritative implementation convention. Business meaning and
schema remain owned by the current UR, FRS, DBD, Implementation Contract,
Implementation Plan, API Design, and UI Design baselines.

## Protected operation path

Every protected HTTP, CLI, import, or background entrypoint follows:

```text
entrypoint -> Application use case -> trusted context -> Authorization
           -> Domain service -> Repository
```

Controllers and adapters do not call repositories for protected business
operations. Authorization is an application boundary; this contract does not
implement or bypass the P1 authorization service.

## Layer ownership

- Repositories own persistence queries, row/advisory-lock primitives, and
  database error translation only.
- Application/domain services own business invariants and transaction
  orchestration. They do not issue table queries directly.
- Domain services are locale-independent. Stable error codes are canonical;
  CodeIgniter Language resolves backend presentation text at the boundary.

## Operation context and audit

An operation receives one trusted context containing the Shield actor (or an
explicit system/bootstrap null actor), request ID where applicable, and one
`EvaluationTime` captured from the shared `Clock`. Authoritative timestamps are
UTC. Caller payloads cannot set `created_by`, `updated_by`, `created_at`, or
`updated_at`; application infrastructure stamps them.

## Persistence and concurrency

- A failed query is an infrastructure/persistence failure, never an empty
  result or not-found result.
- Partial updates validate the merged current-plus-patch candidate before
  persisting only the requested patch.
- Lock timeout/deadlock preserves `CONCURRENT_MODIFICATION`; no unbounded
  retry is allowed, and a release failure cannot mask an earlier domain error.
- Positive IDs use the shared canonical normalizer; `is_numeric()` is not an
  ID validation mechanism.

## Timezone resolution

Business timezone lookup accepts explicit Company and optional GROUP context.
It does not infer that an arbitrary Organization ID is both a Company and a
GROUP. Unresolved context remains unresolved rather than being guessed.

## Modular Product/Pricing boundary

New Gate 5+ Product business/domain code MUST live under
`App\\Modules\\Product\\...`. New Pricing business/domain code MUST live under
`App\\Modules\\Pricing\\...`.

Do not add future Product or Pricing classes to flat `App\\Services` or
`App\\Repositories` locations unless the class is genuinely shared
infrastructure. Existing Gate 0-4 classes may remain in their current locations;
this rule does not require a repository-wide cleanup.

Product MUST NOT depend on `App\\Modules\\Pricing\\...`. Pricing may consume
Product only through an approved stable public Product contract/read port and
must not depend directly on concrete Product repository implementations.

Structural Combo composition and expansion belong to Product; monetary Combo
allocation belongs to Pricing. Generic Auto Numbering remains shared and the
ProductIdentifierAdapter is Product-owned. Import remains a separate boundary.
