# NexPM — Entity Relationship Diagram

**Last updated:** 2026-05-11

---

## Diagram

```mermaid
erDiagram

    %% ── Configurable lookup tables ──────────────────────────────────

    SITE_TYPES {
        bigint id PK
        string name
        timestamps created_at
        timestamps updated_at
    }

    MACHINE_TYPES {
        bigint id PK
        string name
        timestamps created_at
        timestamps updated_at
    }

    %% ── Tenant & hierarchy ──────────────────────────────────────────

    MAIN_CONTRACTORS {
        bigint id PK
        string name
        string phone
        string email
        string pic
        string logo
        timestamps created_at
        timestamps updated_at
    }

    CLIENTS {
        bigint id PK
        bigint main_contractor_id FK
        string name
        string phone
        string email
        string pic
        string logo
        timestamps created_at
        timestamps updated_at
    }

    PROJECTS {
        bigint id PK
        bigint main_contractor_id FK
        bigint client_id FK
        string name
        date start_date
        date end_date
        decimal budget
        timestamps created_at
        timestamps updated_at
    }

    %% ── Sites (core master data entity) ────────────────────────────

    SITES {
        bigint id PK
        bigint project_id FK
        bigint site_type_id FK
        bigint machine_type_id FK
        string site_code UK
        string location_name
        text address
        string province
        string city
        string google_map_url
        decimal latitude
        decimal longitude
        string bd_pic
        string ss_wo_number
        decimal cable_length_to_panel
        decimal cable_length_panel_to_charger
        int charging_station_count
        string ssr_url
        string nidi_slo_bpujl_url
        string sik_url
        text latest_remark
        date invoice_submission_date
        date dp_35_date
        date invoice_60_submission_date
        date payment_60_date
        date invoice_5_submission_date
        date payment_5_date
        string invoice_url
        timestamps created_at
        timestamps updated_at
    }

    SITE_PHOTOS {
        bigint id PK
        bigint site_id FK
        string path
        timestamps created_at
        timestamps updated_at
    }

    %% ── Sub-Contractors ─────────────────────────────────────────────

    SUBCONTRACTORS {
        bigint id PK
        bigint main_contractor_id FK
        string name
        string phone
        string email
        string pic
        string code UK
        timestamps created_at
        timestamps updated_at
    }

    %% ── Users ───────────────────────────────────────────────────────

    USERS {
        bigint id PK
        bigint main_contractor_id FK "nullable — null = Super Admin"
        bigint subcontractor_id FK "nullable — set for SubCon users"
        string name
        string email UK
        string password
        enum role "super_admin|admin|subcontractor"
        text two_factor_secret
        text two_factor_recovery_codes
        timestamp two_factor_confirmed_at
        timestamps created_at
        timestamps updated_at
    }

    %% ── Assignments ─────────────────────────────────────────────────

    ASSIGNMENTS {
        bigint id PK
        bigint site_id FK
        bigint subcontractor_id FK
        enum activity_type "SURVEY|PLN_CONNECTION|CONSTRUCTION|BAST"
        enum status "PENDING|DROP|VERIFIED|REPORTED|SURVEY|DOCUMENT|CONSTRUCTION|MACHINE_ONSITE|DONE|LIVE|REGISTRATION|BILLING|CONNECTION|KWH_DONE|COMPLETED|REVISION"
        text revision_comment "nullable"
        timestamp verified_at "nullable"
        bigint verified_by FK "nullable → users.id"
        timestamp reported_at "nullable"
        timestamps created_at
        timestamps updated_at
    }

    ASSIGNMENT_AUDIT_LOGS {
        bigint id PK
        bigint assignment_id FK
        bigint user_id FK
        string event
        json payload
        timestamp created_at
    }

    %% ── Assignment data tables (one per activity type) ─────────────

    ASSIGNMENT_SURVEY_DATA {
        bigint id PK
        bigint assignment_id FK UK
        string ss_wo_number
        string surveyor_name
        string pic_location_name
        string pic_location_phone
        string charger_type
        date ss_schedule_date
        string cable_pulling_type
        string power_kva
        string pln_network_type
        text additional_info
        string photo_overall_site
        string photo_parking_evcs
        string photo_access_route
        string photo_pln_network
        string photo_satellite_gmaps
        string file_mockup_3d
        string file_site_plan
        string file_ba_survey
        string parking_slot
        date ss_report_submission_date
        timestamps created_at
        timestamps updated_at
    }

    ASSIGNMENT_PLN_DATA {
        bigint id PK
        bigint assignment_id FK UK
        string pln_status
        date nidi_slo_date_acquired
        string type_rate
        string file_slo
        string file_nidi
        string file_reg
        string file_pk
        date kwh_meter_installation_date
        string id_pelanggan
        text catatan_progres
        date email_bpujl_req_date
        date bpujl_acquired_date
        string foto_kwh
        timestamps created_at
        timestamps updated_at
    }

    ASSIGNMENT_CONSTRUCTION_DATA {
        bigint id PK
        bigint assignment_id FK UK
        string cons_wo_number "admin prerequisite — unlocks assignment"
        string project_status "admin"
        date setup_approval_date "admin"
        date cons_actual_start_date
        date cons_actual_done_date
        string machine_serial_number
        string foto_machine_sn
        text catatan_progres
        date go_live_date_pln
        date go_live_date_pln_pass
        timestamps created_at
        timestamps updated_at
    }

    ASSIGNMENT_CONSTRUCTION_PHOTOS {
        bigint id PK
        bigint assignment_construction_data_id FK
        string path
        timestamps created_at
        timestamps updated_at
    }

    ASSIGNMENT_BAST_DATA {
        bigint id PK
        bigint assignment_id FK UK
        string plant_name
        text plant_address
        string plant_coordinate
        string gmaps_link
        string charger_type
        string sn_unit
        string id_pln
        string sim_provider
        string installation_vendor
        string pic_vendor_contact
        date installation_date
        date commissioning_date
        string customer
        json measurements
        string nomor_simcard
        date go_live_date_pln_pass
        date go_live_date_pln
        timestamps created_at
        timestamps updated_at
    }

    ASSIGNMENT_BAST_PHOTOS {
        bigint id PK
        bigint assignment_bast_data_id FK
        string section
        string checkpoint_key
        string photo_path
        timestamps created_at
        timestamps updated_at
    }

    %% ── Reports ─────────────────────────────────────────────────────

    REPORTS {
        bigint id PK
        string name
        string report_type "SSR|BAST|DAILY"
        bigint exported_by FK "→ users.id"
        timestamps created_at
        timestamps updated_at
    }

    REPORT_ASSIGNMENTS {
        bigint report_id FK
        bigint assignment_id FK
    }

    APP_SETTINGS {
        bigint id PK
        string key UK
        text value
        timestamps created_at
        timestamps updated_at
    }

    %% ── Notifications ───────────────────────────────────────────────

    NOTIFICATIONS {
        uuid id PK
        string type
        string notifiable_type
        bigint notifiable_id
        json data
        timestamp read_at "nullable"
        timestamps created_at
        timestamps updated_at
    }

    %% ── Relationships ───────────────────────────────────────────────

    MAIN_CONTRACTORS ||--o{ CLIENTS : "has many"
    MAIN_CONTRACTORS ||--o{ PROJECTS : "has many"
    MAIN_CONTRACTORS ||--o{ SUBCONTRACTORS : "has many"
    MAIN_CONTRACTORS ||--o{ USERS : "has many"

    CLIENTS ||--o{ PROJECTS : "has many"

    PROJECTS ||--o{ SITES : "has many"

    SITE_TYPES ||--o{ SITES : "classifies"
    MACHINE_TYPES ||--o{ SITES : "classifies"
    SITES ||--o{ SITE_PHOTOS : "has many"
    SITES ||--o{ ASSIGNMENTS : "has many assignments"

    SUBCONTRACTORS ||--o{ ASSIGNMENTS : "assigned to"
    SUBCONTRACTORS ||--|| USERS : "has one user account"
    USERS ||--o{ ASSIGNMENTS : "verifies"
    USERS ||--o{ ASSIGNMENT_AUDIT_LOGS : "creates"

    ASSIGNMENTS ||--o| ASSIGNMENT_SURVEY_DATA : "has one"
    ASSIGNMENTS ||--o| ASSIGNMENT_PLN_DATA : "has one"
    ASSIGNMENTS ||--o| ASSIGNMENT_CONSTRUCTION_DATA : "has one"
    ASSIGNMENTS ||--o| ASSIGNMENT_BAST_DATA : "has one"

    ASSIGNMENT_CONSTRUCTION_DATA ||--o{ ASSIGNMENT_CONSTRUCTION_PHOTOS : "has many"
    ASSIGNMENT_BAST_DATA ||--o{ ASSIGNMENT_BAST_PHOTOS : "has many"
    ASSIGNMENTS ||--o{ ASSIGNMENT_AUDIT_LOGS : "logs"

    USERS ||--o{ REPORTS : "exports"
    REPORTS ||--o{ REPORT_ASSIGNMENTS : "includes"
    ASSIGNMENTS ||--o{ REPORT_ASSIGNMENTS : "included in"
```

---

## Key Design Decisions

### 1. Activity Data in Separate Tables
Each activity type (Survey, PLN, Construction, BAST) stores its form data in a dedicated table (`assignment_survey_data`, `assignment_pln_data`, etc.), linked 1-to-1 with `assignments`.

**Why:** BAST and the other activities have distinct data shapes. Merging all activity fields into one table would create a sparse table. Separate tables let each activity schema evolve independently without impacting others.

### 2. MainContractor as Tenant
Tenant ownership is anchored on `main_contractor_id`. Projects, Clients, Subcontractors, and Users carry it directly; Sites inherit tenant scope through their Project. Queries are scoped to the authenticated user's Main Contractor, with Super Admin allowed cross-tenant access.

**Why:** Super Admin manages nex, VGT, and future Main Contractors in one system — but each company's data must stay isolated.

### 3. Configurable Type Tables
`site_types` and `machine_types` are database-driven lookup tables, not hardcoded enums.

**Why:** Site and machine classifications need to be adjusted without a code deployment. `subcontractor_types` has been removed in the current implementation.

### 4. Assignment Status as Enum on Assignments Table
Status lives directly on the `assignments` row rather than in a separate status-history table.

**Why:** Current screens mostly need the current state, while `assignment_audit_logs` records major admin events such as verification, revision, drop, restore, reassignment, and edits.

### 5. Construction Assignment Prerequisite Lock
The `cons_wo_number` field in `assignment_construction_data` being null means the assignment is locked for the sub-con. Admin fills it first.

**Why:** Construction cannot begin without a Work Order number issued by the admin — this is a real-world dependency encoded in the schema.

### 6. Activity Scope is Implicit, Not Configured
A site's activity scope is defined by which assignments exist on it — not by a configuration field on Project or MainContractor. A site may have 1–4 assignments covering any combination of activity types. This means "site complete" = all existing active assignments are verified or reported, not all 4 types.

The current implementation scopes selectable subcontractors by Main Contractor. It does not enforce activity compatibility by subcontractor type because that table/column no longer exists.

### 7. Reports via Junction Table
`report_assignments` is a many-to-many join between a Report and the Assignments it includes.

**Why:** Admin selects specific verified assignments per export. One assignment could theoretically appear in multiple historical reports.
