# FieldOps architecture

## Context

FieldOps separates the work of technicians from the work of operations staff while keeping one source of truth for orders, evidence metadata and audit events.

```mermaid
flowchart LR
    Technician(["Field technician\nintermittent connectivity"])
    Supervisor(["Operations supervisor"])
    FieldOps["FieldOps platform\norders and traceability"]
    Identity["Scoped device credentials\nSanctum tokens"]

    Technician -->|"uses Android app"| FieldOps
    Supervisor -->|"uses web console"| FieldOps
    FieldOps -->|"issues and revokes"| Identity
```

## Containers

```mermaid
flowchart TB
    subgraph Devices[Client devices]
        Mobile["Android app\nKotlin + Compose"]
        Room[("Room database\norders + mutation queue")]
        Web["Operations console\nVue 3 + TypeScript"]
        Mobile <--> Room
    end

    subgraph Platform[Application platform]
        Api["REST API\nLaravel 12 + Sanctum"]
        Policies["Policies + state machine\nRBAC and invariants"]
        Db[("PostgreSQL 16\nsource of truth")]
        Api --> Policies
        Policies --> Db
    end

    Web -->|"Bearer token · JSON"| Api
    Room -. "authenticated bootstrap\npush queued transitions" .-> Api
```

| Container | Owns | Does not own |
| --- | --- | --- |
| Android app | UI state, offline cache, queued mutations, connectivity feedback | Global authorization or canonical order state |
| Admin console | Operational projections, search/filter state, accessible interaction | Persistence or business invariants |
| Laravel API | Authentication, authorization, transitions, idempotency, sync protocol | Device-local offline state |
| PostgreSQL | Canonical users, orders, evidence metadata, events and idempotency records | Presentation logic |

## Domain model

```mermaid
erDiagram
    USER ||--o{ WORK_ORDER : "is assigned"
    USER ||--o{ EVIDENCE : uploads
    WORK_ORDER ||--o{ EVIDENCE : contains
    WORK_ORDER ||--o{ WORK_ORDER_EVENT : records
    USER ||--o{ WORK_ORDER_EVENT : causes
    USER ||--o{ PERSONAL_ACCESS_TOKEN : owns

    WORK_ORDER {
        uuid id
        string status
        string priority
        integer version
        timestamp updated_at
        timestamp deleted_at
    }
    EVIDENCE {
        uuid id
        integer version
        timestamp deleted_at
    }
    WORK_ORDER_EVENT {
        uuid id
        string type
        json payload
        timestamp created_at
    }
```

Orders advance through a declared state machine. A technician can progress an assigned order through `assigned → en_route → in_progress → completed`. Completion requires evidence. Cancellation requires a note. Closed orders make evidence immutable.

## Consistency decisions

### Scoped authorization

Sanctum tokens carry explicit abilities. Policies then restrict technicians to assigned orders while administrators can assign and manage the global workload. Both checks are required: possession of a token alone does not grant access to every record.

### Optimistic concurrency

Mutable orders and evidences expose a `version`. A write based on a stale version fails with `409 version_conflict`, allowing the client to refresh instead of silently overwriting another actor's work.

### Idempotent commands

Mutating `POST` operations accept `Idempotency-Key`. The API stores the request fingerprint and original response. Retrying the same command replays that response; using the key for a different payload fails with `409 idempotency_conflict`.

### Incremental synchronization contract

The API exposes the following cursor protocol for clients that need incremental pulls. The Android portfolio client currently uses an authenticated bootstrap plus a versioned outbound queue; it does not yet persist this server cursor.

```mermaid
sequenceDiagram
    participant App as Android / Room
    participant API as Laravel API
    participant DB as PostgreSQL

    App->>API: GET /sync?since=t0&limit=200
    API->>DB: Read changes up to a frozen high-water mark
    DB-->>API: Orders, evidences and revocations
    API-->>App: data + next_cursor + has_more
    App->>App: Apply records and tombstones transactionally
    loop while has_more
        App->>API: GET /sync?cursor=opaque
        API-->>App: Next globally ordered page
    end
    App->>App: Persist final cursor only after success
```

The opaque cursor freezes an upper boundary and provides a global order across record types. Soft deletions are returned as tombstones. Reassignment emits `access_revoked` so a previous technician can remove data that is no longer authorized.

### Offline command queue

The Android repository writes user intent to Room first and places a mutation in a persistent queue. Network recovery triggers delivery. UI state therefore survives process recreation and intermittent connectivity. The current demo simulates evidence metadata rather than capturing or uploading a physical photo.

## Deployment views

### Local integration environment

`compose.yaml` starts three services:

1. PostgreSQL 16 with a persistent named volume.
2. Laravel on PHP 8.3, with migrations and optional idempotent demo seeding at startup.
3. A production Vite build served by nginx.

The web container defaults to explicit demo mode so it is immediately usable. The API still runs against PostgreSQL and can be exercised independently. API mode is opt-in because a personal token must never be embedded in a public JavaScript bundle.

### Public demo

GitHub Pages hosts only the static Vue demo. It has no secrets, uses fictitious in-memory data and labels the experience as a demo. Laravel and PostgreSQL are intentionally not deployed by the Pages workflow.

## CI/CD

```mermaid
flowchart LR
    Change["Push / pull request"] --> Verify
    Verify --> PHP["PHP: audit + Pint + tests + PostgreSQL migration"]
    Verify --> JS["Vue: lint + tests + production build"]
    Verify --> Android["Android: unit tests + lint + APK"]
    Main["Push to main"] --> Pages["Build explicit demo"]
    Pages --> Public["GitHub Pages"]
```

The Android job uploads the debug APK as a temporary workflow artifact. A distributable release can attach a clearly labeled demo APK separately.

## Security boundaries

- Demo credentials and the Compose application key are for local use only.
- Production must inject secrets through a secret manager, terminate TLS and set an exact CORS allowlist.
- Tokens must be stored with platform-appropriate protected storage; the web demo does not persist a real token.
- Evidence storage needs content-type validation, malware controls, private object storage and signed access URLs before real deployment.
- Rate limiting exists for login; production should also add perimeter monitoring, centralized logs and backup/restore tests.

## Current mobile integration boundary

The Android client is compatible with Laravel's Sanctum login, `/api/v1/work-orders` envelope and versioned transition command. It persists the bearer session, performs an authenticated bootstrap, and retries queued transitions with stable idempotency keys. The backend's cursor-based `GET /api/v1/sync` remains available but is not consumed by this mobile version. CameraX, binary evidence upload and production-grade encrypted token storage are also outside this portfolio release.
