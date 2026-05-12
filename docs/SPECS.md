# NexPM — Product Specification

**Application:** NexPM  
**Owner:** PT Nusantara Energi Khatulistiwa (nex)  
**Client context:** vGreen (https://vgreencharge.id) EV Charging Station rollout  
**Last updated:** 2026-05-13

---

## 1. Overview

NexPM is a project management web application that coordinates the rollout of EV Charging Stations (EVCS) and Battery Swap Stations (BSS) for clients such as vGreen. The system connects Main Contractors, their Sub-Contractors, and Client stakeholders through a structured assignment and verification workflow.

**Core flow:**

1. Super Admin imports site master data via CSV and completes additional masterdata fields through the admin UI.
2. Admin reviews and completes site masterdata (WO numbers, cable specs, invoice tracking, etc.).
3. Sub-Contractors log in and fill their assigned activity forms (Survey, PLN Connection, Construction, BAST), except admin-owned site/master fields such as Survey power are read-only.
4. The system advances each assignment through activity-specific progress statuses as required fields are saved. BAST assignments advance to `SUBMITTED` only via an explicit "Submit for Review" action by the Sub-Contractor.
5. Admin verifies eligible assignments, sends BAST revisions when needed, and archives or restores assignments that leave scope.
6. Admin exports SSR PDF, BAST COMM-TEST XLSX/ZIP, or Daily Monitoring XLSX reports.

---

## 2. Architecture

### Multi-Tenant Model

**MainContractor is the tenant.** Each Main Contractor (e.g., nex, VGT) has fully isolated data: their own Admins, Sub-Contractors, Projects, Clients, and Sites.

- A Client (e.g., vGreen) can be associated with multiple Main Contractors.
- Super Admin has cross-tenant visibility and management access.
- All users share a single login page — role determines scope and access.

---

## 3. User Roles

| Role                    | Description                                                                                                                                                                                                                                                                                                                                               |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Super Admin**         | System owner. Manages all Main Contractors. Uploads Site master data CSV and Sub-Con assignment CSV. Creates Admin accounts. Edits all site masterdata fields including invoice/payment columns.                                                                                                                                                          |
| **Admin**               | Scoped to one Main Contractor. Manages day-to-day operations: completes and edits site masterdata fields, monitors assignment progress, fills Construction prerequisites, verifies eligible activities, sends BAST revision requests, edits sub-con data directly, generates reports, creates Sub-Contractor accounts, and edits invoice/payment columns. |
| **Sub-Contractor User** | One account per Sub-Contractor company. Logs in via web app to fill assigned activity forms. Site/master fields managed by Admin or Super Admin, such as power kVA, are visible but read-only. All field workers at a company share the same account.                                                                                                  |
| **Client**              | No portal access in current implementation. Receives exported report files sent manually by Admin.                                                                                                                                                                                                                                                        |

---

## 4. Data Model

### 4.1 Configurable Master Types

| Entity      | Default Values         | Notes                                                                                                                                                           |
| ----------- | ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SiteType    | EVCS, BSS              | Database lookup table used to classify sites and split Daily Monitoring sheets. Current UI uses these values but does not include a management screen for them. |
| MachineType | Seeded machine options | Database lookup table used by site master data. Current UI uses these values but does not include a management screen for them.                                 |

`SubContractorType` was removed from the current implementation. Subcontractors are linked to one or more Main Contractors through `main_contractor_subcontractor` and assigned by `subcontractor_id`. Activity compatibility is not currently enforced by subcontractor type.

### 4.2 Core Entities

#### MainContractor

| Field | Type                           |
| ----- | ------------------------------ |
| name  | string                         |
| phone | string                         |
| email | string                         |
| pic   | string (Person In Charge name) |

#### Client

| Field              | Type                |
| ------------------ | ------------------- |
| name               | string              |
| phone              | string              |
| email              | string              |
| pic                | string              |

Clients are linked to one or more Main Contractors through `client_main_contractor`. A Project still belongs to one Client and one Main Contractor.

#### Project

| Field              | Type                |
| ------------------ | ------------------- |
| name               | string              |
| start_date         | date                |
| end_date           | date                |
| budget             | decimal             |
| client_id          | FK → Client         |
| main_contractor_id | FK → MainContractor |

#### Site

The core master data entity. One site = one physical charging station location. **Site is the single source of truth** for all non-activity information. Sub-Contractor activity forms reference and populate fields that feed back into the daily report.

Masterdata fields marked **[Admin]** are filled by Admin or Super Admin via the Site Edit UI or CSV import. Fields marked **[Subcon]** are populated from activity form submissions.

| Field                         | Type             | Who fills               | Notes                                     |
| ----------------------------- | ---------------- | ----------------------- | ----------------------------------------- |
| site_code                     | string (unique)  | Admin/CSV               | Unique key used for CSV upsert            |
| project_id                    | FK → Project     | Admin/CSV               |                                           |
| location_name                 | string           | Admin/CSV               | Project / Location Name                   |
| address                       | text             | Admin/CSV               | Full address                              |
| province                      | string           | Admin/CSV               |                                           |
| city                          | string           | Admin/CSV               |                                           |
| google_map_url                | string           | Admin/CSV               |                                           |
| latitude                      | decimal          | Admin/CSV               | GPS                                       |
| longitude                     | decimal          | Admin/CSV               | GPS                                       |
| site_type_id                  | FK → SiteType    | Admin/CSV               | EVCS, BSS, etc.                           |
| machine_type_id               | FK → MachineType | Admin/CSV               |                                           |
| bd_pic                        | string           | **[Admin]**             | vGreen-side Business Development PIC      |
| ss_wo_number                  | string           | **[Admin]**             | Site Survey Work Order number             |
| cable_length_to_panel         | decimal          | **[Admin]**             | Cable length: kWh meter to panel (meters) |
| cable_length_panel_to_charger | decimal          | **[Admin]**             | Cable length: panel to charger (meters)   |
| charging_station_count        | integer          | **[Admin]**             | Number of charging units at this site     |
| ssr_url                       | string           | **[Admin]**             | SS Report & Quotation URL                 |
| nidi_slo_bpujl_url            | string           | **[Admin]**             | NIDI SLO / BPUJL document URL             |
| sik_url                       | string           | **[Admin]**             | SIK document URL                          |
| power_kva                     | string           | **[Admin]**             | Survey power / daya source of truth       |
| latest_remark                 | text             | **[Admin]**             | Free-form notes / latest status remark    |
| invoice_submission_date       | date             | **[Admin/Super Admin]** | Tanggal Pengajuan Invoice (DP)            |
| dp_35_date                    | date             | **[Admin/Super Admin]** | DP 35% payment received date              |
| invoice_60_submission_date    | date             | **[Admin/Super Admin]** | Tanggal Pengajuan Invoice (60%)           |
| payment_60_date               | date             | **[Admin/Super Admin]** | 60% payment received date                 |
| invoice_5_submission_date     | date             | **[Admin/Super Admin]** | Tanggal Pengajuan Invoice (5% final)      |
| payment_5_date                | date             | **[Admin/Super Admin]** | 5% final payment received date            |
| invoice_url                   | string           | **[Admin/Super Admin]** | Invoice document URL                      |
| photos                        | file uploads     | Admin/CSV               | Site photos                               |

#### SubContractor

| Field              | Type                |
| ------------------ | ------------------- |
| name               | string              |
| phone              | string              |
| email              | string              |
| pic                | string              |
| code               | string (unique)     |

Subcontractors are linked to one or more Main Contractors through `main_contractor_subcontractor`. A single Subcontractor can serve multiple Main Contractors, but an Assignment still points to exactly one Subcontractor.

#### Assignment

The core work unit: one Site × one Activity × one SubContractor.

| Field                     | Type                    | Notes                                                                                                                                                           |
| ------------------------- | ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| site_id                   | FK → Site               |                                                                                                                                                                 |
| activity_type             | enum                    | SURVEY, PLN_CONNECTION, CONSTRUCTION, BAST                                                                                                                      |
| subcontractor_id          | FK → SubContractor      |                                                                                                                                                                 |
| status                    | enum                    | PENDING, DROP, VERIFIED, REPORTED, SURVEY, DOCUMENT, CONSTRUCTION, MACHINE_ONSITE, DONE, LIVE, REGISTRATION, BILLING, CONNECTION, KWH_DONE, SUBMITTED, REVISION |
| revision_comment          | text                    | Filled by Admin when sending back for revision                                                                                                                  |
| verified_at / verified_by | timestamp / FK users.id | Filled when Admin verifies                                                                                                                                      |
| reported_at               | timestamp               | Filled when included in a generated report                                                                                                                      |

---

## 5. Assignment Workflow

### 5.1 Status Flow (per activity, independently)

Assignment status is stored on `assignments.status`. The current implementation uses activity-specific progress statuses instead of a single generic `COMPLETED` state for all activities.

**Shared statuses**

```
PENDING
DROP       (archived / removed from active scope)
VERIFIED   (approved by Admin)
REPORTED   (included in a generated report)
```

**Survey**

```
PENDING
  └─► SURVEY     (SS schedule date saved)
        └─► DOCUMENT  (all required Survey fields/photos saved)
              └─► VERIFIED
                    └─► REPORTED
```

**Construction**

```
PENDING
  └─► CONSTRUCTION     (actual start date saved)
        └─► MACHINE_ONSITE  (machine serial number and machine SN photo saved)
              └─► DONE      (actual done date saved)
                    └─► LIVE (PLN go-live date saved)
```

Construction currently progresses through operational statuses but is not included in `AssignmentStatus::verifiableStatuses()`. Only `DOCUMENT`, BAST `SUBMITTED`, `LIVE`, and `KWH_DONE` are verifiable in code.

**PLN Connection**

```
PENDING
  └─► REGISTRATION (SLO, NIDI, and registration files saved)
        └─► BILLING    (BPUJL request email date saved)
              └─► CONNECTION (BPUJL acquired date saved)
                    └─► KWH_DONE (type rate, ID pelanggan, kWh install date, and kWh photo saved)
```

PLN currently progresses through operational statuses but is not included in `AssignmentStatus::verifiableStatuses()`.

**BAST**

```
PENDING
  └─► SUBMITTED  (Sub-contractor explicitly clicks "Submit for Review")
        ├─► VERIFIED
        │     └─► REPORTED
        └─► REVISION   (Admin sends comment + revision note)
              └─► SUBMITTED  (Sub-contractor clicks "Submit for Review" again)
```

Statuses are forward-only for activity progress. Clearing a required field after a status advances does not roll the assignment back. Admin-locked statuses (`VERIFIED`, `REPORTED`, `DROP`, `REVISION`) are not overwritten automatically by the observer. BAST status advances only via the explicit **Submit for Review** action — the observer never auto-advances BAST status. Photos and form data continue to save immediately as uploaded.

- Verification is **per assignment**, not per site.
- A site is considered complete when all of its existing active assignments are verified/reported, not when all four activity types exist.
- Admin can edit sub-con data directly before verifying.
- Report generation requires selected assignments to be `VERIFIED`; included assignments are marked `REPORTED`.

### 5.2 Negative / Exception Flows

| Flow                                  | Current behavior                                                                                                  |
| ------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Assignment leaves scope               | Admin can mark it `DROP`. Dropped assignments are hidden from active dashboards/lists unless filtered.            |
| Assignment returns to scope           | Admin can restore a `DROP` assignment, which resets status to `PENDING`.                                          |
| Wrong subcontractor                   | Admin can reassign. Existing activity data is deleted and the assignment is reset to `PENDING`.                   |
| Pending assignment created by mistake | Admin can delete only assignments that are still `PENDING`.                                                       |
| Verified or reported assignment       | Subcontractor edits are blocked. Admin actions are limited by controller rules.                                   |
| BAST revision                         | Only BAST assignments in `SUBMITTED` can be sent to `REVISION`. The revision comment is stored on the assignment. |

### 5.3 Activity Assignment Rules

| Activity       | Special Rule                                                                                                                                                    |
| -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| SURVEY         | Can be assigned to any subcontractor linked to the site's Main Contractor.                                                                                      |
| PLN_CONNECTION | Can be assigned to any subcontractor linked to the site's Main Contractor.                                                                                      |
| CONSTRUCTION   | Sub-con form is locked until Admin fills **Cons WO Number**. `Project Status` and `Setup Approval Date` are stored as admin fields but do not control the lock. |
| BAST           | Can be assigned to any subcontractor linked to the site's Main Contractor.                                                                                      |

**Activity scope is defined implicitly by which assignments exist on each site.** A site may have any combination of 1–4 assignments — not all activity types are required. The absence of a PLN assignment for a site means PLN is simply not in scope for that site (e.g., the main contractor was not engaged for PLN work on that site).

This means:

- A site is considered **fully complete** when all of its _existing active_ assignments are verified/reported (not when all 4 activity types are present).
- The dashboard Activity Pipeline matrix only shows activity/status counts that exist in the filtered dataset.
- There is no explicit "scope configuration" — the assignment creation UI and assignments CSV upload implicitly define which activities apply to each site.

---

## 6. Activity Form Fields

### 6.1 SURVEY

The Survey assignment advances to `DOCUMENT` when all required Survey fields/photos are present.

| Field                                  | Input Type   |
| -------------------------------------- | ------------ |
| Nama Surveyor                          | text         |
| Nama PIC Lokasi                        | text         |
| No. HP PIC Lokasi                      | text         |
| Jenis Charger (BSS / EVCS)             | text         |
| SS Date — Schedule with Landlord       | date         |
| Cable Pulling Type                     | text         |
| Power / Daya (kVA)                     | read-only text from Site |
| Tipe Jaringan PLN (1 phase / 3 phase)  | text         |
| Parking Slot                           | text         |
| Foto tampak keseluruhan site           | photo upload |
| Foto lahan parkir EVCS atau lokasi BSS | photo upload |
| Foto Jalur Akses Menuju Lokasi         | photo upload |
| Foto Jaringan PLN Terdekat             | photo upload |
| Foto Satelit GMaps                     | photo upload |
| Tambahan Informasi Lain-Lain           | textarea     |
| Mock Up 3D                             | file upload  |
| Site Plan                              | file upload  |
| BA Survey                              | file upload  |
| SS Report Submission Date              | date         |

Power / Daya (kVA) is read-only in the Survey form. Admin or Super Admin manages it on the Site masterdata screen (`sites.power_kva`). When Admin or Subcontractor saves Survey data, the backend copies the current site value into `assignment_survey_data.power_kva` for historical/report compatibility and ignores any submitted/tampered `power_kva` value.

Required for `DOCUMENT`: surveyor name, PIC location name, PIC location phone, charger type, SS schedule date, cable pulling type, power kVA, PLN network type, parking slot, and the five photo fields. Because power kVA is required for `DOCUMENT`, Admin/Super Admin must fill `sites.power_kva` before the Survey can be considered complete. The document uploads and additional info are stored and can appear in SSR output but are not part of the `isComplete()` check.

### 6.2 PLN CONNECTION

The PLN assignment advances through `REGISTRATION`, `BILLING`, `CONNECTION`, and `KWH_DONE` based on progressively saved fields.

| Field                           | Input Type                                         |
| ------------------------------- | -------------------------------------------------- |
| PLN Status                      | select (DONE KWH, NOT YET REGISTERED, WAITING KWH) |
| NIDI SLO Date Acquired          | date                                               |
| Type Rate                       | text                                               |
| File SLO                        | file upload                                        |
| File NIDI                       | file upload                                        |
| File Reg                        | file upload                                        |
| File PK                         | file upload                                        |
| Email BPUJL Request Date        | date                                               |
| BPUJL Acquired Date             | date                                               |
| kWh Meter PLN Installation Date | date                                               |
| Foto kWh                        | photo upload                                       |
| ID PELANGGAN (ID PLN)           | text                                               |
| Catatan Progres                 | textarea                                           |

Required for the final `KWH_DONE` completion check: File SLO, File NIDI, File Reg, File PK, Email BPUJL Request Date, BPUJL Acquired Date, Type Rate, ID Pelanggan, kWh Meter Installation Date, and Foto kWh.

### 6.3 CONSTRUCTION (mixed Admin prerequisite + Construction Sub-Con)

**Admin prerequisite fields — assignment is LOCKED for sub-con until these are filled:**

| Field               | Input Type    |
| ------------------- | ------------- |
| Cons WO Number      | text          |
| Project Status      | select / text |
| Setup Approval Date | date          |

Only Cons WO Number controls the current lock check. Project Status and Setup Approval Date are admin-managed report fields.

**Sub-con fields:**

| Field                      | Input Type             |
| -------------------------- | ---------------------- |
| Cons Actual Start Date     | date                   |
| Cons Actual Done Date      | date                   |
| Machine SN (Serial Number) | text                   |
| Foto Machine SN            | photo upload           |
| Catatan Progres            | textarea               |
| Foto Progres               | multiple photo uploads |
| Go LIVE Date (PLN bypass)  | date                   |
| Go LIVE Date (PLN)         | date                   |

Current status progression uses these triggers: actual start date → `CONSTRUCTION`, machine serial number plus Foto Machine SN → `MACHINE_ONSITE`, actual done date → `DONE`, Go LIVE Date PLN → `LIVE`.

### 6.4 BAST

Same form for both EVCS and BSS sites. Output is labeled by site charging type.

**Plant Information (read-only — derived from relationships):** Plant name, address, coordinate, Google Maps link, charger type, SN unit, ID PLN, installation vendor, PIC vendor contact, installation date, customer, Go LIVE Date (PLN bypass), Go LIVE Date (PLN). These are resolved from `sites`, `projects`, `main_contractors`, and the sibling `assignment_construction_data` record. They are **not stored** in `assignment_bast_data`.

**Editable fields (stored in `assignment_bast_data`):**

| Field                               | Input Type        |
| ----------------------------------- | ----------------- |
| Provider SIM Card (sim_provider)    | text              |
| Nomor SIM Card                      | text              |
| Commissioning Date                  | date              |
| Measurements (electrical readings)  | JSON / structured |
| Installation verification photos    | photo upload      |

**Submission:** There is no auto-advance. The Sub-contractor explicitly clicks **Submit for Review** to move the assignment to `SUBMITTED`. This button is visible when the assignment is in `PENDING` or `REVISION` state. Photos upload immediately on file selection. The admin can also upload/delete BAST checkpoint photos directly from the admin view.

---

## 7. Site Masterdata Management

### 7.1 Overview

The Site Masterdata Edit UI is the primary interface for admins to complete and maintain non-activity site information. It is the **single source of truth** for all fields that are not filled by Sub-Contractors through activity forms.

### 7.2 Access

- **Super Admin:** Full access to all sites across all Main Contractors.
- **Admin:** Access to sites within their Main Contractor's projects only.
- Entry point: Project detail page → Sites list → Edit (pencil icon per site row).

### 7.3 Form Sections

**Section 1 — Basic Info** (mostly populated via CSV, editable here):

- Location Name, Address, Province, City, Google Map URL, BD PIC

**Section 2 — Survey & Technical Info**:

- SS WO Number, Cable Length to Panel, Cable Length Panel to Charger, Charging Station Count, SSR URL, Parking Slot, Power / Daya (kVA)

**Section 3 — Permits & Legal**:

- NIDI SLO / BPUJL URL, SIK URL

**Section 4 — Notes**:

- Latest Remark / Notes

**Section 5 — Invoice & Payment Tracking** (Admin + Super Admin):

- Invoice Submission Date (DP), DP 35% Date, Invoice 60% Submission Date, 60% Payment Date, Invoice 5% Submission Date, 5% Payment Date, Invoice URL

---

## 8. CSV Uploads

### 8.1 Site Master Data CSV

- **Who uploads:** Super Admin
- **Scope:** Per project
- **Behavior:** UPSERT — update existing site if `site_code` matches, otherwise create new
- **Columns:** Core site fields (site_code, location_name, address, province, city, google_map_url, latitude, longitude, site_type, machine_type)
- **Unique key:** `site_code`
- **Note:** Extended masterdata fields (bd_pic, cable lengths, invoice dates, etc.) are completed via the Site Masterdata Edit UI after CSV import.

### 8.2 Sub-Contractor Assignment CSV

- **Who uploads:** Super Admin or Admin
- **Behavior:** UPSERT by Site + Activity — update existing assignment if match found, otherwise create new Assignment
- **Columns:**

| Column             | Description                                   |
| ------------------ | --------------------------------------------- |
| site_code          | Matches Site entity unique code               |
| activity_type      | SURVEY / PLN_CONNECTION / CONSTRUCTION / BAST |
| subcontractor_code | Matches SubContractor unique identifier       |

The imported subcontractor must be linked to the imported site's project Main Contractor. Blank `subcontractor_code` rows are skipped so a template can be partially filled, and a later import can fill the other activities for the same site.

There is no parallel subcontractor assignment for the same Site + Activity. If an imported row targets an existing Site + Activity with a different subcontractor, the assignment's `subcontractor_id` is updated and the import result returns a warning showing the old and new subcontractor. CSV reassignment preserves existing activity data and status; use the assignment reassign action in the UI when the intent is to reset activity data to `PENDING`.

---

## 9. Reports

### 9.1 Report Types

- **SSR (Site Survey Report):** One PDF per verified Survey assignment. A single selected assignment downloads as PDF; multiple selected assignments download as a ZIP of PDFs.
- **BAST Report:** One COMM-TEST XLSX per verified BAST assignment. A single selected assignment downloads as XLSX; multiple selected assignments download as a ZIP of XLSX files.
- **Daily Monitoring Report:** One row per **site** (not per assignment). Combines all activity data horizontally. Split into two sheets by site type: BSS and EVCS.

### 9.2 Daily Monitoring Report Format

The Daily Report follows the reference XLSX format with 44 base columns (A–AR) covering the full project lifecycle per site. Additional fields tagged `daily_extra` in activity field registries are appended after the base columns.

| Col | Field                               | Source                                              |
| --- | ----------------------------------- | --------------------------------------------------- |
| A   | No                                  | Row number                                          |
| B   | EPC Name                            | main_contractors.name                               |
| C   | Charging Type                       | site_types.name (EVCS/BSS)                          |
| D   | Project Status                      | assignment_construction_data.project_status         |
| E   | PLN Status                          | assignment_pln_data.pln_status                      |
| F   | Project / Location Name [Site Code] | sites.location_name + site_code                     |
| G   | Address                             | sites.address                                       |
| H   | Google Map URL                      | sites.google_map_url                                |
| I   | Province                            | sites.province                                      |
| J   | City                                | sites.city                                          |
| K   | BD PIC                              | sites.bd_pic                                        |
| L   | SS WO Number                        | sites.ss_wo_number                                  |
| M   | SS Date (Schedule w/ Landlord)      | assignment_survey_data.ss_schedule_date             |
| N   | SS Report Submission Date           | assignment_survey_data.ss_report_submission_date    |
| O   | Cable Length (kWh to Panel)         | sites.cable_length_to_panel                         |
| P   | Cable Length (Panel to Charger)     | sites.cable_length_panel_to_charger                 |
| Q   | Cable Pulling Type                  | assignment_survey_data.cable_pulling_type           |
| R   | Power (Daya kVA)                    | sites.power_kva, mirrored to assignment_survey_data.power_kva on Survey save |
| S   | SSR URL                             | sites.ssr_url                                       |
| T   | Parking Slot                        | assignment_survey_data.parking_slot                 |
| U   | Charging Station Count              | sites.charging_station_count                        |
| V   | Setup Approval Date                 | assignment_construction_data.setup_approval_date    |
| W   | Cons WO Number                      | assignment_construction_data.cons_wo_number         |
| X   | Cons Actual Start Date              | assignment_construction_data.cons_actual_start_date |
| Y   | Cons Actual Done Date               | assignment_construction_data.cons_actual_done_date  |
| Z   | NIDI SLO Date Acquired              | assignment_pln_data.nidi_slo_date_acquired          |
| AA  | BPUJL Date Acquired                 | assignment_pln_data.bpujl_acquired_date             |
| AB  | NIDI SLO / BPUJL URL                | sites.nidi_slo_bpujl_url                            |
| AC  | SIK URL                             | sites.sik_url                                       |
| AD  | Type Rate                           | assignment_pln_data.type_rate                       |
| AE  | kWh Meter PLN Installation Date     | assignment_pln_data.kwh_meter_installation_date     |
| AF  | Machine SN (Serial Number)          | assignment_construction_data.machine_serial_number  |
| AG  | ID PELANGGAN (ID PLN)               | assignment_pln_data.id_pelanggan                    |
| AH  | Nomor SIM Card                      | assignment_bast_data.nomor_simcard                  |
| AI  | Go LIVE Date (PLN bypass)           | assignment_construction_data.go_live_date_pln_pass  |
| AJ  | Go LIVE Date (PLN)                  | assignment_construction_data.go_live_date_pln       |
| AK  | Latest Remark / Notes               | sites.latest_remark                                 |
| AL  | Tanggal Pengajuan Invoice (DP)      | sites.invoice_submission_date                       |
| AM  | DP 35% Date                         | sites.dp_35_date                                    |
| AN  | Tanggal Pengajuan Invoice (60%)     | sites.invoice_60_submission_date                    |
| AO  | 60% Payment Date                    | sites.payment_60_date                               |
| AP  | Tanggal Pengajuan Invoice (5%)      | sites.invoice_5_submission_date                     |
| AQ  | 5% Payment Date                     | sites.payment_5_date                                |
| AR  | Invoice URL                         | sites.invoice_url                                   |

### 9.3 Report Generation

- **Format:** SSR = PDF/ZIP, BAST = XLSX/ZIP, Daily Monitoring = XLSX
- **Who generates:** Admin
- **Scope selection:** Admin picks verified assignments to include per export
- **Delivery:** Admin downloads and sends manually — no in-app client delivery
- **Status update:** Assignments included in an export → status changes to REPORTED
- **SSR side effect:** Survey data `ss_report_submission_date` is set to the current date when an SSR report is generated.

---

## 10. Dashboard

**Admin dashboard includes:**

- Project summary cards: total sites, breakdown by status (pending / completed / verified / reported)
- Per-project breakdown table
- Recent activity feed

---

## 11. Notifications

- **Channel:** In-app only (notification bell)
- **Trigger events:**
    - Sub-con completes an assignment (→ Admin notified)
    - Admin verifies an assignment (→ Sub-con notified)
    - Admin sends revision (→ Sub-con notified)
    - Report generated (→ logged)

---

## 12. Build Phases

### Phase 1 — Foundation

Auth, user management, configurable types, master data entities (MainContractor, Client, Project, Site, SubContractor).

### Phase 2 — Assignment System

Assignment creation via CSV upload, Sub-Con input forms for Survey, PLN Connection, Construction. Auto-complete logic. Revision workflow.

### Phase 3 — BAST + Reporting

BAST form, admin verification dashboard, Site Masterdata Edit UI, and report generation/export (SSR PDF, BAST XLSX, Daily Monitoring XLSX).

---

## 13. Tech Stack

| Layer    | Technology            |
| -------- | --------------------- |
| Backend  | Laravel 13, PHP 8.4   |
| Frontend | Vue 3 + Inertia.js v3 |
| Styling  | Tailwind CSS v4       |
| Auth     | Laravel Fortify       |
| Testing  | Pest v4, Pest Browser, Playwright |
| Routing  | Laravel Wayfinder     |

### 13.1 Test Coverage Notes

- Feature tests cover the Survey workflow end to end: Admin/Subcontractor upload request, backend file storage, power kVA tamper protection, automatic `DOCUMENT` status transition, Admin verification, SSR report creation, and PDF download.
- Pest Browser + Playwright are installed for browser smoke coverage. Browser tests currently validate page rendering, survey upload file selection UI, and JavaScript errors.
- Pest Browser's internal Laravel HTTP server does not currently hydrate multipart file uploads into Laravel's uploaded files bag, so browser tests do not assert backend file persistence. File persistence is covered by feature tests instead.
