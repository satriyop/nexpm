import type { User } from './auth';

export type ActivityType =
    | 'SURVEY'
    | 'PLN_CONNECTION'
    | 'CONSTRUCTION'
    | 'BAST';

export type AssignmentStatus =
    // Shared
    | 'PENDING'
    | 'DROP'
    | 'VERIFIED'
    | 'REPORTED'
    // Survey
    | 'SURVEY'
    | 'DOCUMENT'
    // Construction
    | 'CONSTRUCTION'
    | 'MACHINE_ONSITE'
    | 'DONE'
    | 'LIVE'
    // PLN
    | 'REGISTRATION'
    | 'BILLING'
    | 'CONNECTION'
    | 'KWH_DONE'
    // BAST
    | 'COMPLETED'
    | 'REVISION';

export interface SiteType {
    id: number;
    name: string;
}

export interface MainContractor {
    id: number;
    name: string;
}

export interface Project {
    id: number;
    name: string;
    main_contractor: MainContractor | null;
}

export interface Site {
    id: number;
    site_code: string;
    location_name: string;
    address: string;
    province: string;
    city: string;
    google_map_url: string | null;
    site_type: SiteType | null;
    project: Project | null;
}

export interface Subcontractor {
    id: number;
    name: string;
    code: string;
}

export interface AssignmentSurveyData {
    id: number;
    assignment_id: number;
    updated_at: string | null;
    ss_wo_number: string | null;
    surveyor_name: string | null;
    pic_location_name: string | null;
    pic_location_phone: string | null;
    charger_type: string | null;
    ss_schedule_date: string | null;
    cable_pulling_type: string | null;
    power_kva: string | null;
    pln_network_type: string | null;
    additional_info: string | null;
    photo_overall_site: string | null;
    photo_parking_evcs: string | null;
    photo_access_route: string | null;
    photo_pln_network: string | null;
    photo_satellite_gmaps: string | null;
    file_mockup_3d: string | null;
    file_site_plan: string | null;
    file_ba_survey: string | null;
    parking_slot: string | null;
    ss_report_submission_date: string | null;
}

export interface AssignmentPlnData {
    id: number;
    assignment_id: number;
    updated_at: string | null;
    pln_status: string | null;
    nidi_slo_date_acquired: string | null;
    type_rate: string | null;
    file_slo: string | null;
    file_nidi: string | null;
    file_reg: string | null;
    file_pk: string | null;
    kwh_meter_installation_date: string | null;
    id_pelanggan: string | null;
    catatan_progres: string | null;
    email_bpujl_req_date: string | null;
    bpujl_acquired_date: string | null;
    foto_kwh: string | null;
}

export interface AssignmentConstructionPhoto {
    id: number;
    path: string;
}

export interface AssignmentConstructionData {
    id: number;
    assignment_id: number;
    updated_at: string | null;
    cons_wo_number: string | null;
    project_status: string | null;
    setup_approval_date: string | null;
    cons_actual_start_date: string | null;
    cons_actual_done_date: string | null;
    machine_serial_number: string | null;
    foto_machine_sn: string | null;
    catatan_progres: string | null;
    go_live_date_pln: string | null;
    go_live_date_pln_pass: string | null;
    construction_photos: AssignmentConstructionPhoto[];
}

export interface AssignmentBastPhoto {
    id: number;
    assignment_bast_data_id: number;
    section: string;
    checkpoint_key: string;
    photo_path: string;
}

export interface AssignmentBastData {
    id: number;
    assignment_id: number;
    updated_at: string | null;
    plant_name: string | null;
    plant_address: string | null;
    plant_coordinate: string | null;
    gmaps_link: string | null;
    charger_type: string | null;
    sn_unit: string | null;
    id_pln: string | null;
    sim_provider: string | null;
    installation_vendor: string | null;
    pic_vendor_contact: string | null;
    installation_date: string | null;
    commissioning_date: string | null;
    customer: string | null;
    measurements: Record<string, string | null> | null;
    nomor_simcard: string | null;
    go_live_date_pln_pass: string | null;
    go_live_date_pln: string | null;
    bast_photos: AssignmentBastPhoto[];
}

export interface Assignment {
    id: number;
    site_id: number;
    subcontractor_id: number;
    activity_type: ActivityType;
    status: AssignmentStatus;
    revision_comment: string | null;
    verified_at: string | null;
    reported_at: string | null;
    updated_at: string | null;
    site: Site;
    subcontractor: Subcontractor;
    verified_by: User | null;
    survey_data: AssignmentSurveyData | null;
    pln_data: AssignmentPlnData | null;
    construction_data: AssignmentConstructionData | null;
    bast_data: AssignmentBastData | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
    first_page_url: string | null;
    last_page_url: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
}
