# Diagram and Figure Guide for IEEE Paper

To make your paper submission-ready for an IEEE conference, you should include at least three key figures. Below are the specific descriptions and structural code (Mermaid) for these diagrams.

## Figure 1: Leave Request Lifecycle (Workflow Diagram)
**Location**: Section IV (Proposed System)
**Description**: Shows the state transitions from application to final approval, highlighting the mandatory substitution step.

```mermaid
graph TD
    A[Faculty: Apply for Leave] --> B{Role: Teaching?}
    B -- Yes --> C[Pending Substitutions]
    B -- No --> D[Pending HOD Approval]
    C --> E{All Peers Accepted?}
    E -- No --> C
    E -- Yes --> D
    D --> F{HOD Approved?}
    F -- No --> G[Rejected/Cancelled]
    F -- Yes --> H[Pending Principal Approval]
    H --> I{Principal Approved?}
    I -- No --> G
    I -- Yes --> J[Approved & PDF Generated]
```

## Figure 2: System Architecture (Component Diagram)
**Location**: Section V (System Architecture)
**Description**: Illustrates the connection between the JS Frontend, PHP API, and MySQL Database.

```mermaid
graph LR
    subgraph "Client Tier (Frontend)"
        A[JS SPA / app.js]
        B[CSS Glassmorphism UI]
    end
    
    subgraph "Application Tier (Backend)"
        C[REST API / leaves.php]
        D[RBAC Middleware / auth_guard.php]
        E[PDF Engine / mPDF]
    end
    
    subgraph "Data Tier"
        F[(MySQL Database)]
    end

    A <-->|JSON / Fetch API| C
    C --> D
    D --> F
    F --> C
    C --> E
```

## Figure 3: Database Schema (ER Diagram)
**Location**: Section VI (Methodology)
**Description**: Shows the relationships between users, departments, leave requests, and substitutions.

```mermaid
erDiagram
    USERS ||--o{ LEAVE_REQUESTS : applies
    USERS }|--|| DEPARTMENTS : belongs_to
    LEAVE_REQUESTS ||--o{ LEAVE_SUBSTITUTIONS : contains
    USERS ||--o{ LEAVE_SUBSTITUTIONS : accepts_as_peer
    LEAVE_REQUESTS ||--o{ NOTIFICATIONS : triggers
```

## Implementation Tips for IEEE Format:
1. **Captions**: Always place figure captions *below* the figure (e.g., *Fig 1. Integrated workflow...*).
2. **Resolution**: If using these Mermaid diagrams, export them as high-resolution PNG or SVG.
3. **Contrast**: Ensure text in diagrams is legible and uses a professional font like Arial or Helvetica.
