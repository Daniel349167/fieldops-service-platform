# FieldOps API

Production-oriented Laravel 12 API for the FieldOps service-order platform. It powers a Vue operations console and an offline-capable Android client with scoped access tokens, role-based authorization, optimistic concurrency, idempotent mutations, evidence metadata, audit events, and cursor-based incremental synchronization.

## API contract

The complete OpenAPI 3.1 contract is available at [`docs/openapi.yaml`](docs/openapi.yaml). It documents all 16 routes under `/api/v1`, request and response envelopes, Sanctum bearer authentication, validation constraints, error codes, idempotency behavior, optimistic versions, pagination, and offline sync cursors.

The contract can be imported directly into Swagger UI, Redoc, Insomnia, Bruno, or Postman. The source of truth for behavior remains the Laravel routes, controllers, requests, resources, and automated tests; the contract mirrors those implementations and does not describe speculative endpoints.

## Requirements

- PHP 8.2+
- Composer 2.7+
- SQLite 3 for local development and tests, or PostgreSQL for deployment

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create `database/database.sqlite`:

```bash
touch database/database.sqlite
```

PowerShell equivalent:

```powershell
New-Item database/database.sqlite -ItemType File
```

Then migrate, seed, and start the API:

```bash
php artisan migrate --seed
php artisan serve
```

The base URL is `http://127.0.0.1:8000/api/v1`. Set `CORS_ALLOWED_ORIGINS` to a comma-separated list of trusted web-client origins.

### Demo accounts

The idempotent seeder creates these local/demo users:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `admin@fieldops.test` | `FieldOps2026!` |
| Technician | `tecnico@fieldops.test` | `FieldOps2026!` |
| Technician | `tecnico2@fieldops.test` | `FieldOps2026!` |

These credentials are intentionally public and must not be used outside local or disposable demo environments.

## First authenticated request

Issue a token:

```bash
curl --request POST http://127.0.0.1:8000/api/v1/auth/login \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "email": "admin@fieldops.test",
    "password": "FieldOps2026!",
    "device_name": "local-dashboard"
  }'
```

Use `data.token` as a bearer token:

```bash
curl http://127.0.0.1:8000/api/v1/dashboard \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <token>"
```

All JSON success responses use a `data` envelope. Paginated collections additionally expose Laravel `links` and `meta` objects. Failures expose a stable `code` and human-readable `message`; field validation and conflict context may also include `errors`.

## Authorization model

Login tokens expire after 30 days by default (`SANCTUM_EXPIRATION`) and contain explicit abilities.

| Capability | Administrator | Assigned technician |
| --- | :---: | :---: |
| View dashboard and orders | Yes | Assigned orders only |
| List technicians | Yes | No |
| Create, edit, assign, or delete orders | Yes | No |
| Advance execution status | Yes | Yes |
| Add evidence | Yes | Yes |
| Delete evidence | Yes | Own uploads only |
| Incremental sync | All records | Assigned records only |

Administrator tokens contain `work-orders:read`, `work-orders:write`, `work-orders:execute`, `users:read`, and `sync:read`. Technician tokens contain `work-orders:read`, `work-orders:execute`, and `sync:read`.

Authorization is enforced twice: token abilities gate route groups, then policies enforce role, assignment, and evidence ownership. A technician cannot infer or retrieve another technician's work through list, detail, timeline, evidence, or sync endpoints.

## Route inventory

Every route below is relative to `/api/v1`.

| Method | Route | Access | Purpose |
| --- | --- | --- | --- |
| `POST` | `/auth/login` | Public | Issue a scoped Sanctum token |
| `GET` | `/auth/me` | Authenticated | Return the current user |
| `POST` | `/auth/logout` | Authenticated | Revoke the current token |
| `GET` | `/dashboard` | Read ability | Role-scoped operational summary |
| `GET` | `/technicians` | Administrator | List technician accounts |
| `GET` | `/work-orders` | Read ability | Filter and paginate visible orders |
| `POST` | `/work-orders` | Administrator | Create an order |
| `GET` | `/work-orders/{workOrder}` | Authorized viewer | Read an active or soft-deleted order |
| `PATCH` | `/work-orders/{workOrder}` | Administrator | Edit or reassign an order |
| `DELETE` | `/work-orders/{workOrder}` | Administrator | Soft-delete an order and its evidence |
| `POST` | `/work-orders/{workOrder}/transition` | Authorized executor | Apply a state transition |
| `GET` | `/work-orders/{workOrder}/evidences` | Authorized viewer | Paginate evidence metadata |
| `POST` | `/work-orders/{workOrder}/evidences` | Authorized executor | Register evidence metadata |
| `DELETE` | `/work-orders/{workOrder}/evidences/{evidence}` | Owner or administrator | Soft-delete evidence |
| `GET` | `/work-orders/{workOrder}/timeline` | Authorized viewer | Read the audit timeline |
| `GET` | `/sync` | Sync ability | Pull incremental offline changes |

The evidence creation route registers metadata for an object already stored by the client or storage workflow. It does not receive multipart file bytes.

## Lifecycle and optimistic concurrency

The declared state machine is:

```text
pending -> assigned -> en_route -> in_progress -> completed
   |          |            |            |
   +----------+------------+------------+----> cancelled
```

Administrators may use the declared rollback transitions (`assigned -> pending`, `en_route -> assigned`, and `in_progress -> en_route`) and cancellation transitions. Technicians may only advance their assigned work through:

```text
assigned -> en_route -> in_progress -> completed
```

Operational invariants:

- Assigning a technician moves a pending order to `assigned`.
- Unassigning an `assigned` order moves it back to `pending`.
- An `en_route` or `in_progress` order cannot be left unassigned.
- Completing an order requires at least one evidence record.
- Cancelling an order requires a note.
- Evidence cannot be added to or removed from `completed` or `cancelled` orders.
- Deleting an order soft-deletes its evidence and emits versioned tombstones.

Every mutable order and evidence carries a `version`. Send the last version read by the client in update, transition, and delete payloads. A successful mutation increments the version. A stale request returns:

```json
{
  "message": "The work order was modified by another client. Refresh and retry.",
  "code": "version_conflict",
  "errors": {
    "expected": 2,
    "current": 3
  }
}
```

## Idempotent POST mutations

These routes accept an optional `Idempotency-Key` header:

- `POST /work-orders`
- `POST /work-orders/{workOrder}/transition`
- `POST /work-orders/{workOrder}/evidences`

Keys are scoped per authenticated user, limited to 100 characters, and retained for 24 hours. Field order in the JSON object does not affect request identity. Repeating the same key with the same method, path, and payload replays the original response and adds:

```http
Idempotency-Replayed: true
```

Reusing a key for another request returns `409 idempotency_conflict`. A concurrent duplicate still being processed returns `409 idempotency_in_progress`.

Example:

```bash
curl --request POST http://127.0.0.1:8000/api/v1/work-orders/<work-order-ulid>/transition \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer <token>" \
  --header "Idempotency-Key: android-transition-01J2H9B5" \
  --data '{"to_status":"en_route","version":2,"note":"Leaving the workshop."}'
```

## Offline synchronization

Start a synchronization window with an ISO-8601 lower boundary:

```text
GET /api/v1/sync?since=2026-07-10T12:00:00Z&limit=200
```

The result separates live `work_orders`, live `evidences`, and versioned `tombstones`. `meta.sync_at` freezes the upper boundary for the entire window. If `meta.has_more` is `true`, continue with the opaque cursor:

```text
GET /api/v1/sync?cursor=<meta.next_cursor>&limit=200
```

Do not decode, edit, or combine the cursor with a new `since` value. The cursor records the global position across work orders, evidences, and access revocations, preventing gaps and duplicates when several resource types share timestamps.

When the final page returns `has_more: false`, persist `meta.next_since` as the `since` value for the next synchronization cycle. Soft deletion returns tombstones with reason `deleted`; reassigning an order returns a work-order tombstone with reason `access_revoked` to the previous technician so the mobile database can remove stale data.

## Stable error codes

Common codes include:

| HTTP | Code | Meaning |
| --- | --- | --- |
| `401` | `unauthenticated` | Missing, invalid, expired, or revoked token |
| `401` | `invalid_credentials` | Login credentials rejected |
| `403` | `forbidden` | Ability or policy denied the operation |
| `403` | `forbidden_status_transition` | Technician attempted a non-forward transition |
| `404` | `not_found` | Resource or nested resource not found |
| `409` | `version_conflict` | Client version is stale |
| `409` | `idempotency_conflict` | Key was used for a different request |
| `409` | `idempotency_in_progress` | Identical request is already executing |
| `422` | `validation_failed` | Request fields are invalid |
| `422` | `invalid_status_transition` | State-machine edge is not allowed |
| `422` | `technician_required` | Transition to assigned lacks an assignee |
| `422` | `evidence_required` | Completion lacks evidence |
| `422` | `cancellation_note_required` | Cancellation lacks a note |
| `422` | `work_order_closed` | Evidence was added to a closed order |
| `422` | `closed_order_evidence_locked` | Closed-order evidence is immutable |
| `429` | `rate_limited` | Login throttle exceeded |

See the OpenAPI contract for endpoint-specific responses and examples.

## Quality checks

```bash
php artisan test
vendor/bin/pint --test
composer validate --strict
composer audit --locked
```

The test environment uses in-memory SQLite and covers authentication, token scopes, role isolation, query filters, state transitions, evidence ownership, idempotency, optimistic locking, tombstones, global cursor pagination, reassignment revocation, and idempotent demo seeding.

Validate the OpenAPI document with a standards-aware linter, for example:

```bash
npx --yes @redocly/cli lint docs/openapi.yaml
```
