# System Design Figure (Research Paper)

```mermaid
flowchart TB
    %% Users
    A[Admin Users]:::user
    B[Staff Users]:::user
    C[Customer Users]:::user

    %% Presentation
    subgraph UI[Presentation Layer]
        D[Admin Dashboard UI]
        E[Staff POS / Inventory UI]
        F[Customer Portal UI]
    end

    %% App Core
    subgraph APP[Application Layer - PHP]
        G[Routing & Controllers\n(PHP Pages + API Endpoints)]
        H[Authentication & Authorization\nSession + Role Permissions + CSRF]
        I[Business Modules\nOrders, Inventory, Medicine Locator, Reports]
        J[Notification Engine]
    end

    %% Data/Infra
    subgraph DATA[Data & Infrastructure Layer]
        K[(MySQL / MariaDB)]
        L[(File Storage\nBackups, Logs, Exports)]
    end

    %% Background + Integrations
    subgraph BG[Background Jobs & Integrations]
        M[Scheduler / Cron Jobs\nbackup_cron.php, email_cron.php]
        N[Email Service / SMTP]
    end

    A --> D
    B --> E
    C --> F

    D --> G
    E --> G
    F --> G

    G --> H
    H --> I
    I --> J

    I --> K
    I --> L
    J --> K

    M --> I
    M --> L
    M --> N

    I --> N

    classDef user fill:#fef3c7,stroke:#d97706,stroke-width:1px,color:#111827;
    classDef layer fill:#eff6ff,stroke:#2563eb,stroke-width:1px,color:#111827;
```

## Figure Caption (suggested)
**Figure X.** High-level system design of the Calloway Pharmacy platform showing user groups, presentation layer, PHP application services, data/infrastructure components, and background integrations.
