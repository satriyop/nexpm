# NexPM — Product Specification

**Application:** NexPM  
**Owner:** PT Nusantara Energi Khatulistiwa (nex)  
**Client context:** vGreen (https://vgreencharge.id) EV Charging Station rollout  
**Last updated:** 2026-05-01

---

## 1. Overview

NexPM is a project management web application that coordinates the rollout of EV Charging Stations (EVCS) and Battery Swap Stations (BSS) for clients such as vGreen. The system connects Main Contractors, their Sub-Contractors, and Client stakeholders through a structured assignment and verification workflow.

**Core flow:**
1. Super Admin imports site master data via CSV and completes additional masterdata fields through the admin UI.
2. Admin reviews and completes site masterdata (WO numbers, cable specs, invoice tracking, etc.).
3. Sub-Contractors log in and fill their assigned activity forms (Survey, PLN Connection, Construction, BAST).
4. Admin verifies each completed activity, sends revisions when needed.
5. Admin exports a per-site XLS report to the client.

---

## 2. Architecture

### Multi-Tenant Model
**MainContractor is the tenant.** Each Main Contractor (e.g., nex, VGT) has fully isolated data: their own Admins, Sub-Contractors, Projects, Clients, and Sites.

- A Client (e.g., vGreen) can be associated with multiple Main Contractors.
- Super Admin has cross-tenant visibility and management access.
- All users share a single login page — role determines scope and access.

---

## 3. User Roles

| Role | Description |
|------|-------------|
| **Super Admin** | System owner. Manages all Main Contractors. Configures Sub-Contractor types, Site types, Machine types. Uploads Site master data CSV and Sub-Con assignment CSV. Creates Admin accounts. Edits all site masterdata fields including invoice/payment columns. |
| **Admin** | Scoped to one Main Contractor. Manages day-to-day operations: completes and edits site masterdata fields, monitors assignment progress, fills Construction prerequisites (WO Number, Project Status, Setup Approval Date), verifies completed activities, sends revision requests, edits sub-con data directly, generates XLS reports. Creates Sub-Contractor accounts. Edits invoice/payment columns. |
| **Sub-Contractor User** | One account per Sub-Contractor company. Logs in via web app to fill assigned activity forms. All field workers at a company share the same account. |
| **Client** | No portal access in current scope. Receives exported XLS report files sent manually by Admin. |

---

## 4. Data Model

### 4.1 Configurable Master Types (managed by Super Admin)

| Entity | Default Values | Notes |
|--------|---------------|-------|
| SubContractorType | Construction, PLN | More types can be added. Each type stores which activity types it handles. |
| SiteType | EVCS, BSS | More types can be added |
| MachineType | (to be defined) | Selectable options for charging machine |

**SubContractorType → Activity mapping (default):**

| SubContractorType | Handles Activities |
|-------------------|--------------------|
| Construction | SURVEY, CONSTRUCTION, BAST |
| PLN | PLN_CONNECTION |

This mapping is stored in `subcontractor_types.activity_types` (JSON). It governs which sub-contractors can be assigned to each activity — the system rejects assignments where the sub-con's type does not handle that activity (both via CSV import and the reassign UI).

### 4.2 Core Entities

#### MainContractor
| Field | Type |
|-------|------|
| name | string |
| phone | string |
| email | string |
| pic | string (Person In Charge name) |

#### Client
| Field | Type |
|-------|------|
| name | string |
| phone | string |
| email | string |
| pic | string |
| main_contractor_id | FK → MainContractor |

#### Project
| Field | Type |
|-------|------|
| name | string |
| start_date | date |
| end_date | date |
| budget | decimal |
| client_id | FK → Client |
| main_contractor_id | FK → MainContractor |

#### Site
The core master data entity. One site = one physical charging station location. **Site is the single source of truth** for all non-activity information. Sub-Contractor activity forms reference and populate fields that feed back into the daily report.

Masterdata fields marked **[Admin]** are filled by Admin or Super Admin via the Site Edit UI or CSV import. Fields marked **[Subcon]** are populated from activity form submissions.

| Field | Type | Who fills | Notes |
|-------|------|-----------|-------|
| site_code | string (unique) | Admin/CSV | Unique key used for CSV upsert |
| project_id | FK → Project | Admin/CSV | |
| location_name | string | Admin/CSV | Project / Location Name |
| address | text | Admin/CSV | Full address |
| province | string | Admin/CSV | |
| city | string | Admin/CSV | |
| google_map_url | string | Admin/CSV | |
| latitude | decimal | Admin/CSV | GPS |
| longitude | decimal | Admin/CSV | GPS |
| site_type_id | FK → SiteType | Admin/CSV | EVCS, BSS, etc. |
| machine_type_id | FK → MachineType | Admin/CSV | |
| bd_pic | string | **[Admin]** | vGreen-side Business Development PIC |
| ss_wo_number | string | **[Admin]** | Site Survey Work Order number |
| cable_length_to_panel | decimal | **[Admin]** | Cable length: kWh meter to panel (meters) |
| cable_length_panel_to_charger | decimal | **[Admin]** | Cable length: panel to charger (meters) |
| charging_station_count | integer | **[Admin]** | Number of charging units at this site |
| ss_report_submission_date | date | **[Admin]** | Date SS report was submitted to client |
| ssr_url | string | **[Admin]** | SS Report & Quotation URL |
| bpujl_date_acquired | date | **[Admin]** | BPUJL permit date |
| nidi_slo_bpujl_url | string | **[Admin]** | NIDI SLO / BPUJL document URL |
| sik_url | string | **[Admin]** | SIK document URL |
| latest_remark | text | **[Admin]** | Free-form notes / latest status remark |
| invoice_submission_date | date | **[Admin/Super Admin]** | Tanggal Pengajuan Invoice (DP) |
| dp_35_date | date | **[Admin/Super Admin]** | DP 35% payment received date |
| invoice_60_submission_date | date | **[Admin/Super Admin]** | Tanggal Pengajuan Invoice (60%) |
| payment_60_date | date | **[Admin/Super Admin]** | 60% payment received date |
| invoice_5_submission_date | date | **[Admin/Super Admin]** | Tanggal Pengajuan Invoice (5% final) |
| payment_5_date | date | **[Admin/Super Admin]** | 5% final payment received date |
| invoice_url | string | **[Admin/Super Admin]** | Invoice document URL |
| photos | file uploads | Admin/CSV | Site photos |

#### SubContractor
| Field | Type |
|-------|------|
| name | string |
| phone | string |
| email | string |
| pic | string |
| subcontractor_type_id | FK → SubContractorType |
| main_contractor_id | FK → MainContractor |

#### Assignment
The core work unit: one Site × one Activity × one SubContractor.

| Field | Type | Notes |
|-------|------|-------|
| site_id | FK → Site | |
| activity_type | enum | SURVEY, PLN_CONNECTION, CONSTRUCTION, BAST |
| subcontractor_id | FK → SubContractor | |
| status | enum | PENDING, COMPLETED, REVISION, VERIFIED, REPORTED |
| revision_comment | text | Filled by Admin when sending back for revision |

---

## 5. Assignment Workflow

### 5.1 Status Flow (per activity, independently)

```
PENDING
  └─► COMPLETED   (automatic — triggers when all required fields are filled by sub-con)
        ├─► VERIFIED   (Admin approves this activity)
        └─► REVISION   (Admin rejects with comment → sub-con notified)
              └─► COMPLETED  (sub-con re-fills after seeing comment)
                    └─► VERIFIED
                          └─► REPORTED  (activity included in an XLS export)
```

- Verification is **per activity**, not per site.
- A site is considered "fully complete" only when all 4 activity assignments are VERIFIED.
- Admin can either verify directly OR edit the sub-con's data themselves before verifying.

### 5.2 Activity → Sub-Contractor Type

| Activity | Sub-Con Type | Special Rule |
|----------|-------------|--------------|
| SURVEY | Construction | None |
| PLN_CONNECTION | PLN | None |
| CONSTRUCTION | Construction | Admin must fill **Cons WO Number**, **Project Status**, and **Setup Approval Date** first — assignment is locked for sub-con until this is done |
| BAST | Construction | None |

**Activity scope is defined implicitly by which assignments exist on each site.** A site may have any combination of 1–4 assignments — not all activity types are required. The absence of a PLN assignment for a site means PLN is simply not in scope for that site (e.g., the main contractor was not engaged for PLN work on that site).

This means:
- A site is considered **fully complete** when all of its *existing* assignments are VERIFIED (not when all 4 activity types are VERIFIED).
- The dashboard Activity Pipeline matrix only shows rows for activity types that have at least one assignment — rows with zero assignments across all visible sites are hidden to avoid confusion.
- There is no explicit "scope configuration" — the assignments CSV upload implicitly defines which activities apply to each site.

---

## 6. Activity Form Fields

### 6.1 SURVEY (filled by Construction Sub-Con)
All fields required for auto-COMPLETED trigger.

| Field | Input Type |
|-------|-----------|
| Nama Surveyor | text |
| Nama PIC Lokasi | text |
| No. HP PIC Lokasi | text |
| Jenis Charger (BSS / EVCS) | select |
| SS Date — Schedule with Landlord | date |
| Cable Pulling Type | select |
| Power / Daya (kVA) | select |
| Tipe Jaringan PLN (1 phase / 3 phase) | select |
| Tambahan Informasi Lain-Lain | textarea |
| Foto tampak keseluruhan site | photo upload |
| Foto lahan parkir EVCS atau lokasi BSS | photo upload |
| Foto Lahan dari sudut pandang lain | photo upload |
| Foto Jaringan PLN Terdekat | photo upload |
| Foto Satelit GMaps | photo upload |
| Mock Up 3D | file upload |
| BA Survey | file upload |
| Parking Slot | text |

### 6.2 PLN CONNECTION (filled by PLN Sub-Con)
All fields required for auto-COMPLETED trigger.

| Field | Input Type |
|-------|-----------|
| PLN Status | select (DONE KWH, NOT YET REGISTERED, WAITING KWH) |
| NIDI SLO Date Acquired | date |
| Type Rate | text |
| File SLO | file upload |
| File NIDI | file upload |
| File Reg | file upload |
| kWh Meter PLN Installation Date | date |
| ID PELANGGAN (ID PLN) | text |
| Catatan Progres | textarea |

### 6.3 CONSTRUCTION (mixed Admin prerequisite + Construction Sub-Con)

**Admin prerequisite fields — assignment is LOCKED for sub-con until these are filled:**

| Field | Input Type |
|-------|-----------|
| Cons WO Number | text |
| Project Status | select / text |
| Setup Approval Date | date |

**Sub-con fields (all required for auto-COMPLETED trigger):**

| Field | Input Type |
|-------|-----------|
| Cons Actual Start Date | date |
| Cons Actual Done Date | date |
| Machine SN (Serial Number) | text |
| Catatan Progres | textarea |
| Foto Progres | multiple photo uploads |

### 6.4 BAST (filled by Construction Sub-Con)
Same form for both EVCS and BSS sites. Output is labeled by site charging type.
~50 photo checkpoints — all required for auto-COMPLETED trigger.

| Field | Input Type |
|-------|-----------|
| Nomor SIM Card | text |
| Go LIVE Date (PLN bypass) | date |
| Go LIVE Date (PLN) | date |
| Plant name, address, coordinate, gmaps link | text / url |
| Charger type, SN unit, ID PLN | text |
| SIM provider, installation vendor, PIC vendor contact | text |
| Installation date, commissioning date | date |
| Customer | text |
| Measurements (electrical readings) | JSON / structured |
| ~50 installation verification photo checkpoints | photo upload |

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
- SS WO Number, Cable Length to Panel, Cable Length Panel to Charger, Charging Station Count, SS Report Submission Date, SSR URL, Parking Slot

**Section 3 — Permits & Legal**:
- BPUJL Date Acquired, NIDI SLO / BPUJL URL, SIK URL

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
- **Behavior:** UPSERT — update existing assignment if match found, otherwise create new Assignment
- **Columns:**

| Column | Description |
|--------|-------------|
| site_code | Matches Site entity unique code |
| activity_type | SURVEY / PLN_CONNECTION / CONSTRUCTION / BAST |
| subcontractor_code | Matches SubContractor unique identifier |

---

## 9. Reports

### 9.1 Report Types
- **SSR (Site Survey Report):** One row per verified Survey assignment. Survey activity data.
- **BAST Report:** One row per verified BAST assignment. Exports the COMM-TEST XLSX per site (single file or ZIP for multiple).
- **Daily Monitoring Report:** One row per **site** (not per assignment). Combines all activity data horizontally. Split into two sheets by site type: BSS and EVCS.

### 9.2 Daily Monitoring Report Format
The Daily Report follows the reference XLSX format with 44 columns (A–AR) covering the full project lifecycle per site:

| Col | Field | Source |
|-----|-------|--------|
| A | No | Row number |
| B | EPC Name | main_contractors.name |
| C | Charging Type | site_types.name (EVCS/BSS) |
| D | Project Status | assignment_construction_data.project_status |
| E | PLN Status | assignment_pln_data.pln_status |
| F | Project / Location Name [Site Code] | sites.location_name + site_code |
| G | Address | sites.address |
| H | Google Map URL | sites.google_map_url |
| I | Province | sites.province |
| J | City | sites.city |
| K | BD PIC | sites.bd_pic |
| L | SS WO Number | sites.ss_wo_number |
| M | SS Date (Schedule w/ Landlord) | assignment_survey_data.ss_schedule_date |
| N | SS Report Submission Date | sites.ss_report_submission_date |
| O | Cable Length (kWh to Panel) | sites.cable_length_to_panel |
| P | Cable Length (Panel to Charger) | sites.cable_length_panel_to_charger |
| Q | Cable Pulling Type | assignment_survey_data.cable_pulling_type |
| R | Power (Daya kVA) | assignment_survey_data.power_kva |
| S | SSR URL | sites.ssr_url |
| T | Parking Slot | assignment_survey_data.parking_slot |
| U | Charging Station Count | sites.charging_station_count |
| V | Setup Approval Date | assignment_construction_data.setup_approval_date |
| W | Cons WO Number | assignment_construction_data.cons_wo_number |
| X | Cons Actual Start Date | assignment_construction_data.cons_actual_start_date |
| Y | Cons Actual Done Date | assignment_construction_data.cons_actual_done_date |
| Z | NIDI SLO Date Acquired | assignment_pln_data.nidi_slo_date_acquired |
| AA | BPUJL Date Acquired | sites.bpujl_date_acquired |
| AB | NIDI SLO / BPUJL URL | sites.nidi_slo_bpujl_url |
| AC | SIK URL | sites.sik_url |
| AD | Type Rate | assignment_pln_data.type_rate |
| AE | kWh Meter PLN Installation Date | assignment_pln_data.kwh_meter_installation_date |
| AF | Machine SN (Serial Number) | assignment_construction_data.machine_serial_number |
| AG | ID PELANGGAN (ID PLN) | assignment_pln_data.id_pelanggan |
| AH | Nomor SIM Card | assignment_bast_data.nomor_simcard |
| AI | Go LIVE Date (PLN bypass) | assignment_bast_data.go_live_date_pln_pass |
| AJ | Go LIVE Date (PLN) | assignment_bast_data.go_live_date_pln |
| AK | Latest Remark / Notes | sites.latest_remark |
| AL | Tanggal Pengajuan Invoice (DP) | sites.invoice_submission_date |
| AM | DP 35% Date | sites.dp_35_date |
| AN | Tanggal Pengajuan Invoice (60%) | sites.invoice_60_submission_date |
| AO | 60% Payment Date | sites.payment_60_date |
| AP | Tanggal Pengajuan Invoice (5%) | sites.invoice_5_submission_date |
| AQ | 5% Payment Date | sites.payment_5_date |
| AR | Invoice URL | sites.invoice_url |

### 9.3 Report Generation
- **Format:** XLSX (Excel)
- **Who generates:** Admin
- **Scope selection:** Admin picks which verified sites/assignments to include per export
- **Delivery:** Admin downloads and sends manually — no in-app client delivery
- **Status update:** Assignments included in an export → status changes to REPORTED

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
BAST form, admin verification dashboard, Site Masterdata Edit UI, XLS report generation and export (SSR, BAST, Daily Monitoring).

---

## 13. Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | Vue 3 + Inertia.js v3 |
| Styling | Tailwind CSS v4 |
| Auth | Laravel Fortify |
| Testing | Pest v4 |
| Routing | Laravel Wayfinder |
