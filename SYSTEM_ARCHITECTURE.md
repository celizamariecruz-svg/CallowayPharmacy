# Calloway Pharmacy IMS - System Architecture (Thesis Version)

## 1. Architectural Positioning

This figure is intentionally a deployment/runtime architecture, not a system design decomposition.

- `System Design` explains functional modules and process logic.
- `System Architecture` explains runtime nodes, network boundaries, interfaces, and infrastructure dependencies.

## 2. Deployment Architecture (Primary Thesis Figure)

```mermaid
flowchart LR
    subgraph Z1[Client Zone]
        U1[Admin and Staff Browser]
        U2[Customer Browser]
    end

    subgraph Z2[Application Server Zone]
        W1[Apache or PHP Web Server]
        A1[Calloway PHP Application\nPages + APIs + Auth + Services]
        J1[Scheduled Jobs\nbackup_cron.php\nemail_cron.php]
    end

    subgraph Z3[Data Zone]
        D1[(MySQL or MariaDB)]
        D2[(File Storage\ncache logs backups)]
    end

    subgraph Z4[External Service Zone]
        E1[SMTP Provider]
    end

    U1 -->|HTTPS| W1
    U2 -->|HTTPS| W1
    W1 --> A1

    A1 -->|SQL over TCP 3306| D1
    A1 -->|Read Write| D2
    A1 -->|SMTP 465 or 587| E1

    J1 -->|Scheduled execution| A1
    J1 -->|Database backup read| D1
    J1 -->|Backup and logs write| D2
```

## 3. Runtime Interaction View

```mermaid
sequenceDiagram
    participant B as Browser
    participant P as PHP App
    participant DB as MySQL
    participant FS as File Storage
    participant SMTP as SMTP

    B->>P: Login or API request
    P->>P: Session and role validation
    P->>DB: Query and update business data
    DB-->>P: Result set
    P->>FS: Cache or logs or backup metadata
    P->>SMTP: Send notifications if needed
    P-->>B: HTML or JSON response
```

## 4. Why This Is Different from System Design

This architecture view focuses on non-functional structure:
- Runtime nodes (browser, web server, application process, database, SMTP).
- Trust and network boundaries (client zone, app zone, data zone, external zone).
- Technical interfaces/protocols (HTTPS, SQL/TCP, SMTP).
- Operational workloads (cron execution and backup pipelines).

This avoids repeating module-level flow already covered by your system design figure.

## 5. Concrete Mapping to Your Codebase

- Web entry and routing: `index.php`, `login.php`, `dashboard.php`, `onlineordering.php`, `inventory_management.php`
- API surface: `inventory_api.php`, `notification_api.php`, `api_orders.php`, `api_settings.php`
- Security and auth runtime: `Security.php`, `Auth.php`, `CSRF.php`
- Data access: `db_connection.php`
- Background jobs: `backup_cron.php`, `email_cron.php`
- Service integrations: `email_service.php`, `BackupManager.php`, `CacheManager.php`, `ActivityLogger.php`

## 6. Suggested Thesis Caption

**Figure X. System Architecture of the Calloway Pharmacy IMS.**
The diagram shows deployment nodes, runtime communication paths, and infrastructure dependencies, including web clients, PHP application server, MySQL/MariaDB datastore, file storage, SMTP integration, and scheduled background jobs.
