<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\AssignmentBastData;
use App\Models\AssignmentBastPhoto;
use App\Models\AssignmentConstructionData;
use App\Models\AssignmentConstructionPhoto;
use App\Models\AssignmentPlnData;
use App\Models\AssignmentSurveyData;
use App\Models\Client;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Report;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $evcsTypeId = DB::table('site_types')->where('name', 'EVCS')->value('id');
        $bssTypeId = DB::table('site_types')->where('name', 'BSS')->value('id');

        // ── Users ──────────────────────────────────────────────────────────────
        $mc = MainContractor::firstOrCreate(
            ['name' => 'PT Nusantara Energi Khatulistiwa'],
            ['phone' => '+62 21 555 0100', 'email' => 'admin@nex.co.id', 'pic' => 'Budi Santoso'],
        );

        User::firstOrCreate(
            ['email' => 'superadmin@test.com'],
            [
                'name' => 'Super Admin Test',
                'password' => 'password',
                'role' => Role::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@nex.com'],
            [
                'name' => 'Admin NEX',
                'password' => 'password',
                'role' => Role::Admin,
                'main_contractor_id' => $mc->id,
                'email_verified_at' => now(),
            ],
        );

        // ── Client & Project ───────────────────────────────────────────────────
        $client = Client::firstOrCreate(
            ['name' => 'vGreen Indonesia', 'main_contractor_id' => $mc->id],
            ['pic' => 'Andi Wijaya', 'phone' => '+62 812 000 0001', 'email' => 'andi@vgreen.id'],
        );

        $project = Project::firstOrCreate(
            ['name' => 'EVCS Rollout Phase 1', 'main_contractor_id' => $mc->id],
            [
                'client_id' => $client->id,
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
                'budget' => 5_000_000_000,
            ],
        );

        // ── Subcontractors ────────────────────────────────────────────────────
        $scCons1 = Subcontractor::firstOrCreate(
            ['code' => 'SC-CONS-01'],
            [
                'name' => 'PT Bangun Karya Mandiri',
                'main_contractor_id' => $mc->id,
                'phone' => '+62 811 000 0001',
                'email' => 'construction1@test.com',
                'pic' => 'Dodi Prasetyo',
            ],
        );

        $scCons2 = Subcontractor::firstOrCreate(
            ['code' => 'SC-CONS-02'],
            [
                'name' => 'PT Multi Teknik Mandiri',
                'main_contractor_id' => $mc->id,
                'phone' => '+62 811 000 0002',
                'email' => 'construction2@test.com',
                'pic' => 'Irfan Hakim',
            ],
        );

        $scPln = Subcontractor::firstOrCreate(
            ['code' => 'SC-PLN-01'],
            [
                'name' => 'CV Listrik Prima',
                'main_contractor_id' => $mc->id,
                'phone' => '+62 811 000 0003',
                'email' => 'pln@test.com',
                'pic' => 'Hendra Kurniawan',
            ],
        );

        foreach ([
            ['email' => 'construction1@test.com', 'name' => 'User Construction 1', 'sc' => $scCons1],
            ['email' => 'construction2@test.com', 'name' => 'User Construction 2', 'sc' => $scCons2],
            ['email' => 'pln@test.com',            'name' => 'User PLN',            'sc' => $scPln],
        ] as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => 'password',
                    'role' => Role::Subcontractor,
                    'subcontractor_id' => $u['sc']->id,
                    'email_verified_at' => now(),
                ],
            );
        }

        // ── Scenario matrix ───────────────────────────────────────────────────
        //
        // Each scenario maps a site to a target status per activity type.
        //
        // Status legend (per-activity system):
        //   REPORTED  – full lifecycle done; linked to a Report record
        //   VERIFIED  – admin verified; awaiting report export
        //   DOCUMENT  – survey subcon fully submitted; awaiting admin verification
        //   KWH_DONE  – PLN subcon fully submitted; awaiting admin verification
        //   LIVE      – construction subcon fully submitted; awaiting admin verification
        //   COMPLETED – BAST subcon fully submitted; awaiting admin verification
        //   REVISION  – admin sent BAST back with comment (BAST only); subcon must re-submit
        //   PENDING   – assigned but subcon hasn't submitted yet
        //              (two sub-variants: "WO set" for construction, "no data" otherwise)
        //
        // Special cases:
        //   TEST-BSS-003 Construction → PENDING with WO already set (prerequisite unlocked)
        //   TEST-BSS-004             → only Survey assigned (others not scoped for this site)

        $scenarios = [
            [
                'site_code' => 'TEST-EVCS-001',
                'site_type_id' => $evcsTypeId,
                'location' => 'Mall Taman Anggrek',
                'address' => 'Jl. Letjen S. Parman Kav. 21',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Barat',
                'cons_subcon' => $scCons1,
                'pln_subcon' => $scPln,
                'create_report' => true,
                'statuses' => [
                    ActivityType::Survey->value => AssignmentStatus::Reported,
                    ActivityType::PlnConnection->value => AssignmentStatus::Reported,
                    ActivityType::Construction->value => AssignmentStatus::Reported,
                    ActivityType::Bast->value => AssignmentStatus::Reported,
                ],
            ],
            [
                'site_code' => 'TEST-EVCS-002',
                'site_type_id' => $evcsTypeId,
                'location' => 'Grand Indonesia',
                'address' => 'Jl. M.H. Thamrin No. 1',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Pusat',
                'cons_subcon' => $scCons2,
                'pln_subcon' => $scPln,
                'create_report' => false,
                'statuses' => [
                    ActivityType::Survey->value => AssignmentStatus::Verified,
                    ActivityType::PlnConnection->value => AssignmentStatus::Verified,
                    ActivityType::Construction->value => AssignmentStatus::Verified,
                    ActivityType::Bast->value => AssignmentStatus::Verified,
                ],
            ],
            [
                // "Fully submitted — awaiting admin verification" scenario.
                // Each activity uses its own terminal subcon status.
                'site_code' => 'TEST-BSS-001',
                'site_type_id' => $bssTypeId,
                'location' => 'SPBU Fatmawati',
                'address' => 'Jl. RS Fatmawati No. 35',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Selatan',
                'cons_subcon' => $scCons1,
                'pln_subcon' => $scPln,
                'create_report' => false,
                'statuses' => [
                    ActivityType::Survey->value => AssignmentStatus::Document,
                    ActivityType::PlnConnection->value => AssignmentStatus::KwhDone,
                    ActivityType::Construction->value => AssignmentStatus::Live,
                    ActivityType::Bast->value => AssignmentStatus::Completed,
                ],
            ],
            [
                // "BAST in revision, other activities at their subcon terminal status" scenario.
                'site_code' => 'TEST-BSS-002',
                'site_type_id' => $bssTypeId,
                'location' => 'SPBU Kelapa Gading',
                'address' => 'Jl. Kelapa Gading Raya No. 10',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Utara',
                'cons_subcon' => $scCons2,
                'pln_subcon' => $scPln,
                'create_report' => false,
                'statuses' => [
                    ActivityType::Survey->value => AssignmentStatus::Document,
                    ActivityType::PlnConnection->value => AssignmentStatus::KwhDone,
                    ActivityType::Construction->value => AssignmentStatus::Live,
                    ActivityType::Bast->value => AssignmentStatus::Revision,
                ],
            ],
            [
                'site_code' => 'TEST-BSS-003',
                'site_type_id' => $bssTypeId,
                'location' => 'SPBU Senayan',
                'address' => 'Jl. Asia Afrika No. 8',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Selatan',
                'cons_subcon' => $scCons1,
                'pln_subcon' => $scPln,
                'create_report' => false,
                'statuses' => [
                    // Survey/PLN/BAST: assigned but no data yet
                    ActivityType::Survey->value => AssignmentStatus::Pending,
                    ActivityType::PlnConnection->value => AssignmentStatus::Pending,
                    // Construction: WO set by admin (prerequisite unlocked) but no completion
                    ActivityType::Construction->value => AssignmentStatus::Pending,
                    ActivityType::Bast->value => AssignmentStatus::Pending,
                ],
                'construction_wo_only' => true,
            ],
            [
                'site_code' => 'TEST-BSS-004',
                'site_type_id' => $bssTypeId,
                'location' => 'SPBU Cawang',
                'address' => 'Jl. D.I. Panjaitan No. 1',
                'province' => 'DKI Jakarta',
                'city' => 'Jakarta Timur',
                'cons_subcon' => $scCons2,
                'pln_subcon' => $scPln,
                'create_report' => false,
                // Only survey scoped — PLN/Construction/BAST not assigned yet
                'statuses' => [
                    ActivityType::Survey->value => AssignmentStatus::Pending,
                ],
            ],
        ];

        // REVISION is BAST-only in the new per-activity status system.
        $revisionComments = [
            ActivityType::Bast->value => 'Foto grounding cable route tidak terlihat jelas. Harap ambil ulang dari sudut berbeda.',
        ];

        foreach ($scenarios as $siteIdx => $scenario) {
            $n = $siteIdx + 1;

            $site = Site::firstOrCreate(
                ['site_code' => $scenario['site_code']],
                [
                    'project_id' => $project->id,
                    'site_type_id' => $scenario['site_type_id'],
                    'location_name' => $scenario['location'],
                    'address' => $scenario['address'],
                    'province' => $scenario['province'],
                    'city' => $scenario['city'],
                ],
            );

            $seededAssignments = [];

            foreach ($scenario['statuses'] as $activityTypeValue => $targetStatus) {
                $activityType = ActivityType::from($activityTypeValue);

                $subconId = $activityType === ActivityType::PlnConnection
                    ? $scenario['pln_subcon']->id
                    : $scenario['cons_subcon']->id;

                $assignment = Assignment::firstOrCreate(
                    ['site_id' => $site->id, 'activity_type' => $activityType],
                    ['subcontractor_id' => $subconId, 'status' => AssignmentStatus::Pending],
                );

                $isWoOnly = ($scenario['construction_wo_only'] ?? false)
                    && $activityType === ActivityType::Construction;

                // Seed activity data for every status except bare PENDING (no-data) and WO-only (handled separately)
                $seedFullData = $targetStatus !== AssignmentStatus::Pending || $isWoOnly;

                if ($seedFullData) {
                    match ($activityType) {
                        ActivityType::Survey => $this->seedSurveyData($assignment, $n, $scenario),

                        ActivityType::PlnConnection => $this->seedPlnData($assignment, $n),

                        ActivityType::Construction => $this->seedConstructionData($assignment, $n, $isWoOnly),

                        ActivityType::Bast => $this->seedBastData($assignment, $n, $scenario, $targetStatus),
                    };
                }

                // Force the target status, bypassing observers so seed data doesn't trigger side-effects
                if ($assignment->status !== $targetStatus) {
                    $assignment->status = $targetStatus;

                    if (in_array($targetStatus, [AssignmentStatus::Verified, AssignmentStatus::Reported], true)) {
                        $assignment->verified_by = $adminUser->id;
                        $assignment->verified_at = now()->subDays(max(1, 6 - $siteIdx));
                    }

                    if ($targetStatus === AssignmentStatus::Reported) {
                        $assignment->reported_at = now()->subDays(max(1, 4 - $siteIdx));
                    }

                    if ($targetStatus === AssignmentStatus::Revision) {
                        $assignment->revision_comment = $revisionComments[$activityTypeValue];
                    }

                    $assignment->saveQuietly();
                }

                $seededAssignments[] = $assignment;
            }

            // Link REPORTED assignments to a Report record
            if ($scenario['create_report']) {
                $report = Report::firstOrCreate(
                    ['name' => 'BAST Report — '.$scenario['location']],
                    [
                        'report_type' => 'BAST',
                        'exported_by' => $adminUser->id,
                    ],
                );
                $report->assignments()->syncWithoutDetaching(
                    collect($seededAssignments)->pluck('id')->all(),
                );
            }
        }

        // ── NEX exhaustive combination sites (CMB-) ───────────────────────────
        $this->seedComboSites(
            prefix: 'CMB',
            project: $project,
            adminUser: $adminUser,
            consSubcons: [$scCons1, $scCons2],
            plnSubcon: $scPln,
            evcsTypeId: $evcsTypeId,
            bssTypeId: $bssTypeId,
            nOffset: 1000,
        );

        // ── Sigmatec main contractor ───────────────────────────────────────────
        $mcSgt = MainContractor::firstOrCreate(
            ['name' => 'PT Sigmatec Energi Nusantara'],
            ['phone' => '+62 21 555 0200', 'email' => 'admin@sigmatec.co.id', 'pic' => 'Eka Prasetya'],
        );

        $adminSgt = User::firstOrCreate(
            ['email' => 'admin@sigmatec.com'],
            [
                'name' => 'Admin Sigmatec',
                'password' => 'password',
                'role' => Role::Admin,
                'main_contractor_id' => $mcSgt->id,
                'email_verified_at' => now(),
            ],
        );

        $clientSgt = Client::firstOrCreate(
            ['name' => 'PLN Mobile', 'main_contractor_id' => $mcSgt->id],
            ['pic' => 'Rudi Setiawan', 'phone' => '+62 812 000 0099', 'email' => 'rudi@plnmobile.id'],
        );

        $projectSgt = Project::firstOrCreate(
            ['name' => 'BSS Rollout Phase 1', 'main_contractor_id' => $mcSgt->id],
            [
                'client_id' => $clientSgt->id,
                'start_date' => '2025-03-01',
                'end_date' => '2026-02-28',
                'budget' => 8_000_000_000,
            ],
        );

        $scConsSgt1 = Subcontractor::firstOrCreate(
            ['code' => 'SGT-CONS-01'],
            [
                'name' => 'PT Graha Teknik Sentosa',
                'main_contractor_id' => $mcSgt->id,
                'phone' => '+62 811 000 0011',
                'email' => 'sgt-construction1@test.com',
                'pic' => 'Agus Salim',
            ],
        );

        $scConsSgt2 = Subcontractor::firstOrCreate(
            ['code' => 'SGT-CONS-02'],
            [
                'name' => 'CV Karya Prima Utama',
                'main_contractor_id' => $mcSgt->id,
                'phone' => '+62 811 000 0012',
                'email' => 'sgt-construction2@test.com',
                'pic' => 'Wahyu Nugroho',
            ],
        );

        $scPlnSgt = Subcontractor::firstOrCreate(
            ['code' => 'SGT-PLN-01'],
            [
                'name' => 'PT Daya Listrik Abadi',
                'main_contractor_id' => $mcSgt->id,
                'phone' => '+62 811 000 0013',
                'email' => 'sgt-pln@test.com',
                'pic' => 'Bambang Sutrisno',
            ],
        );

        foreach ([
            ['email' => 'sgt-construction1@test.com', 'name' => 'SGT Construction 1', 'sc' => $scConsSgt1],
            ['email' => 'sgt-construction2@test.com', 'name' => 'SGT Construction 2', 'sc' => $scConsSgt2],
            ['email' => 'sgt-pln@test.com',            'name' => 'SGT PLN',            'sc' => $scPlnSgt],
        ] as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => 'password',
                    'role' => Role::Subcontractor,
                    'subcontractor_id' => $u['sc']->id,
                    'email_verified_at' => now(),
                ],
            );
        }

        // ── Sigmatec exhaustive combination sites (SGT-) ──────────────────────
        $this->seedComboSites(
            prefix: 'SGT',
            project: $projectSgt,
            adminUser: $adminSgt,
            consSubcons: [$scConsSgt1, $scConsSgt2],
            plnSubcon: $scPlnSgt,
            evcsTypeId: $evcsTypeId,
            bssTypeId: $bssTypeId,
            nOffset: 10000,
        );
    }

    /**
     * Generates the full exhaustive Cartesian combination of activity subsets × status tuples
     * for a given contractor's project.
     *
     * Site code: {prefix}-{activity_chars}-{status_chars}
     * Total: 1,295 sites per contractor
     *
     * @param  Subcontractor[]  $consSubcons
     */
    private function seedComboSites(
        string $prefix,
        Project $project,
        User $adminUser,
        array $consSubcons,
        Subcontractor $plnSubcon,
        int $evcsTypeId,
        int $bssTypeId,
        int $nOffset,
    ): void {
        $activityChar = [
            ActivityType::Survey->value => 'S',
            ActivityType::Construction->value => 'K',
            ActivityType::PlnConnection->value => 'L',
            ActivityType::Bast->value => 'B',
        ];

        // Representative status set for combo generation (keeps site count manageable).
        // These 5 cover: not started, activity-specific completion trigger (Document for survey),
        // BAST revision, admin verified, and final reported state.
        $statusChar = [
            AssignmentStatus::Pending->value => 'P',
            AssignmentStatus::Document->value => 'D',
            AssignmentStatus::Revision->value => 'X',
            AssignmentStatus::Verified->value => 'V',
            AssignmentStatus::Reported->value => 'R',
        ];

        $cities = [
            ['city' => 'Jakarta Pusat',   'province' => 'DKI Jakarta'],
            ['city' => 'Jakarta Selatan', 'province' => 'DKI Jakarta'],
            ['city' => 'Jakarta Utara',   'province' => 'DKI Jakarta'],
            ['city' => 'Jakarta Barat',   'province' => 'DKI Jakarta'],
            ['city' => 'Jakarta Timur',   'province' => 'DKI Jakarta'],
            ['city' => 'Tangerang',       'province' => 'Banten'],
            ['city' => 'Bekasi',          'province' => 'Jawa Barat'],
            ['city' => 'Depok',           'province' => 'Jawa Barat'],
            ['city' => 'Bogor',           'province' => 'Jawa Barat'],
            ['city' => 'Karawang',        'province' => 'Jawa Barat'],
        ];

        $siteTypeIds = [$evcsTypeId, $bssTypeId];
        $comboIndex = 0;

        foreach ($this->activitySubsets() as $activitySubset) {
            foreach ($this->statusTuples(count($activitySubset)) as $statusTuple) {
                $actCode = implode('', array_map(fn ($a) => $activityChar[$a->value], $activitySubset));
                $statusCode = implode('', array_map(fn ($s) => $statusChar[$s->value], $statusTuple));
                $siteCode = "{$prefix}-{$actCode}-{$statusCode}";

                $n = $nOffset + $comboIndex;
                $cityEntry = $cities[$comboIndex % count($cities)];
                $siteTypeId = $siteTypeIds[$comboIndex % 2];
                $consSubcon = $consSubcons[$comboIndex % count($consSubcons)];

                $site = Site::firstOrCreate(
                    ['site_code' => $siteCode],
                    [
                        'project_id' => $project->id,
                        'site_type_id' => $siteTypeId,
                        'location_name' => "Lokasi {$siteCode}",
                        'address' => "Jl. Test No. {$n}",
                        'province' => $cityEntry['province'],
                        'city' => $cityEntry['city'],
                    ],
                );

                foreach (array_map(null, $activitySubset, $statusTuple) as [$activityType, $targetStatus]) {
                    $subconId = $activityType === ActivityType::PlnConnection
                        ? $plnSubcon->id
                        : $consSubcon->id;

                    $assignment = Assignment::firstOrCreate(
                        ['site_id' => $site->id, 'activity_type' => $activityType],
                        ['subcontractor_id' => $subconId, 'status' => AssignmentStatus::Pending],
                    );

                    if ($targetStatus !== AssignmentStatus::Pending) {
                        $this->seedActivityData($assignment, $activityType, $targetStatus, $n, $siteTypeId, $evcsTypeId, $consSubcon, $plnSubcon);
                    }

                    $this->forceStatus($assignment, $targetStatus, $adminUser);
                }

                $comboIndex++;
            }
        }
    }

    /**
     * Returns all non-empty subsets of the 4 activity types (15 subsets),
     * enumerated via bit-mask in canonical order S→K→L→B.
     *
     * @return ActivityType[][]
     */
    private function activitySubsets(): array
    {
        $canonical = [
            ActivityType::Survey,
            ActivityType::Construction,
            ActivityType::PlnConnection,
            ActivityType::Bast,
        ];

        $subsets = [];
        $total = count($canonical);

        for ($mask = 1; $mask < (1 << $total); $mask++) {
            $subset = [];
            for ($bit = 0; $bit < $total; $bit++) {
                if ($mask & (1 << $bit)) {
                    $subset[] = $canonical[$bit];
                }
            }
            $subsets[] = $subset;
        }

        return $subsets;
    }

    /**
     * Returns every k-tuple of the representative statuses (5^k tuples total).
     * Uses Document as the "subcon fully submitted" marker to keep site count identical.
     *
     * @return AssignmentStatus[][]
     */
    private function statusTuples(int $k): array
    {
        $statuses = [
            AssignmentStatus::Pending,
            AssignmentStatus::Document,
            AssignmentStatus::Revision,
            AssignmentStatus::Verified,
            AssignmentStatus::Reported,
        ];

        $tuples = [[]];

        for ($i = 0; $i < $k; $i++) {
            $next = [];
            foreach ($tuples as $tuple) {
                foreach ($statuses as $status) {
                    $next[] = [...$tuple, $status];
                }
            }
            $tuples = $next;
        }

        return $tuples;
    }

    /**
     * Seeds the activity-specific data record for non-PENDING statuses.
     */
    private function seedActivityData(
        Assignment $assignment,
        ActivityType $activityType,
        AssignmentStatus $targetStatus,
        int $n,
        int $siteTypeId,
        int $evcsTypeId,
        Subcontractor $consSubcon,
        Subcontractor $scPln,
    ): void {
        $isEvcs = $siteTypeId === $evcsTypeId;

        match ($activityType) {
            ActivityType::Survey => AssignmentSurveyData::firstOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'ss_wo_number' => 'SS-WO-'.str_pad($n, 6, '0', STR_PAD_LEFT),
                    'surveyor_name' => 'Surveyor CMB-'.$n,
                    'pic_location_name' => 'PIC Lokasi '.$n,
                    'pic_location_phone' => '+628'.str_pad($n, 9, '0', STR_PAD_LEFT),
                    'charger_type' => $isEvcs ? 'EVCS 22kW' : 'BSS-500',
                    'ss_schedule_date' => now()->subDays(5)->format('Y-m-d'),
                    'cable_pulling_type' => 'New Power',
                    'power_kva' => '22kVA',
                    'pln_network_type' => '3 Phase',
                    'parking_slot' => 'CMB-'.$n,
                    'photo_overall_site' => 'https://picsum.photos/seed/cmb'.$n.'a/800/600',
                    'photo_parking_evcs' => 'https://picsum.photos/seed/cmb'.$n.'b/800/600',
                    'photo_access_route' => 'https://picsum.photos/seed/cmb'.$n.'c/800/600',
                    'photo_pln_network' => 'https://picsum.photos/seed/cmb'.$n.'d/800/600',
                    'photo_satellite_gmaps' => 'https://picsum.photos/seed/cmb'.$n.'e/800/600',
                ],
            ),

            ActivityType::PlnConnection => $this->seedPlnData($assignment, $n),

            ActivityType::Construction => $this->seedConstructionData($assignment, $n, false),

            ActivityType::Bast => $this->seedBastData(
                $assignment, $n,
                [
                    'location' => 'Lokasi CMB-'.$n,
                    'address' => 'Jl. Test No. '.$n,
                    'cons_subcon' => $consSubcon,
                    'site_type_id' => $siteTypeId,
                ],
                $targetStatus,
            ),
        };
    }

    /**
     * Forces the assignment to the target status, writing timestamps where required.
     * Uses saveQuietly() to bypass observers.
     */
    private function forceStatus(Assignment $assignment, AssignmentStatus $targetStatus, User $adminUser): void
    {
        if ($assignment->status === $targetStatus) {
            return;
        }

        $assignment->status = $targetStatus;

        if (in_array($targetStatus, [AssignmentStatus::Verified, AssignmentStatus::Reported], true)) {
            $assignment->verified_by = $adminUser->id;
            $assignment->verified_at = now()->subDays(2);
        }

        if ($targetStatus === AssignmentStatus::Reported) {
            $assignment->reported_at = now()->subDays(1);
        }

        if ($targetStatus === AssignmentStatus::Revision) {
            $assignment->revision_comment = 'Data perlu diperbaiki. Mohon cek kembali sebelum re-submit.';
        }

        $assignment->saveQuietly();
    }

    private function generatePlaceholderImage(string $label, string $directory): string
    {
        $width = 400;
        $height = 300;

        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 30, 90, 80);
        $text = imagecolorallocate($img, 255, 255, 255);
        $border = imagecolorallocate($img, 60, 140, 120);

        imagefill($img, 0, 0, $bg);
        imagerectangle($img, 2, 2, $width - 3, $height - 3, $border);

        $lines = str_split($label, 28);
        $lineH = 18;
        $startY = (int) ($height / 2) - (int) (count($lines) * $lineH / 2);

        foreach ($lines as $i => $line) {
            $lineWidth = strlen($line) * imagefontwidth(4);
            $x = (int) (($width - $lineWidth) / 2);
            imagestring($img, 4, $x, $startY + $i * $lineH, $line, $text);
        }

        imagestring($img, 1, 5, $height - 14, 'SEED DATA', $border);

        $dir = storage_path('app/public/'.$directory);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $directory.'/'.Str::uuid().'.jpg';
        imagejpeg($img, storage_path('app/public/'.$filename), 85);
        imagedestroy($img);

        return $filename;
    }

    private function seedSurveyData(Assignment $assignment, int $n, array $scenario): void
    {
        $isEvcs = $scenario['site_type_id'] === DB::table('site_types')->where('name', 'EVCS')->value('id');

        AssignmentSurveyData::firstOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'ss_wo_number' => 'SS-WO-2026-'.str_pad($n, 4, '0', STR_PAD_LEFT),
                'surveyor_name' => 'Budi Prasetyo '.$n,
                'pic_location_name' => 'Manager '.$scenario['location'],
                'pic_location_phone' => '+6281'.str_pad($n * 11_111_111, 8, '0', STR_PAD_LEFT),
                'charger_type' => $isEvcs ? 'EVCS 22kW' : 'BSS-500',
                'ss_schedule_date' => now()->subDays(5)->format('Y-m-d'),
                'cable_pulling_type' => 'New Power',
                'power_kva' => '22kVA',
                'pln_network_type' => '3 Phase',
                'parking_slot' => 'B'.$n.'-0'.$n,
                'photo_overall_site' => $this->generatePlaceholderImage('Tampak Keseluruhan Site', 'survey'),
                'photo_parking_evcs' => $this->generatePlaceholderImage('Lahan Parkir EVCS / BSS', 'survey'),
                'photo_access_route' => $this->generatePlaceholderImage('Jalur Akses Menuju Lokasi', 'survey'),
                'photo_pln_network' => $this->generatePlaceholderImage('Jaringan PLN Terdekat', 'survey'),
                'photo_satellite_gmaps' => $this->generatePlaceholderImage('Satelit GMaps', 'survey'),
                'additional_info' => 'Lokasi mudah diakses. Parkir tersedia untuk kendaraan besar.',
            ],
        );
    }

    /**
     * @param  bool  $woOnly  True for the in-progress edge-case: WO is set but subcon hasn't submitted completion data yet.
     */
    private function seedConstructionData(Assignment $assignment, int $n, bool $woOnly): void
    {
        if ($woOnly) {
            AssignmentConstructionData::firstOrCreate(
                ['assignment_id' => $assignment->id],
                [
                    'cons_wo_number' => 'WO-2026-00'.$n,
                    'project_status' => 'On Progress',
                ],
            );

            return;
        }

        $data = AssignmentConstructionData::firstOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'cons_wo_number' => 'WO-2026-00'.$n,
                'project_status' => 'Completed',
                'setup_approval_date' => now()->subDays(10)->format('Y-m-d'),
                'cons_actual_start_date' => now()->subDays(7)->format('Y-m-d'),
                'cons_actual_done_date' => now()->subDays(2)->format('Y-m-d'),
                'machine_serial_number' => 'EVCS-SN-'.str_pad($n * 1000, 6, '0', STR_PAD_LEFT),
                'foto_machine_sn' => $this->generatePlaceholderImage('Machine SN Photo', 'construction'),
                'go_live_date_pln' => now()->subDays(1)->format('Y-m-d'),
                'go_live_date_pln_pass' => now()->format('Y-m-d'),
                'catatan_progres' => 'Instalasi selesai. Unit berfungsi normal. Telah dilakukan pengujian awal.',
            ],
        );

        // Two progress photos so isComplete() (count > 0) is satisfied
        if (! AssignmentConstructionPhoto::where('assignment_construction_data_id', $data->id)->exists()) {
            foreach (['Sebelum Instalasi', 'Sesudah Instalasi'] as $stage) {
                AssignmentConstructionPhoto::create([
                    'assignment_construction_data_id' => $data->id,
                    'path' => $this->generatePlaceholderImage('Foto '.$stage, 'construction'),
                ]);
            }
        }
    }

    private function seedPlnData(Assignment $assignment, int $n): void
    {
        AssignmentPlnData::firstOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'pln_status' => 'DONE KWH',
                'nidi_slo_date_acquired' => now()->subDays(5)->format('Y-m-d'),
                'type_rate' => '22 kVA',
                'file_slo' => 'https://placehold.co/600x800/png?text=SLO-'.$n,
                'file_nidi' => 'https://placehold.co/600x800/png?text=NIDI-'.$n,
                'file_reg' => 'https://placehold.co/600x800/png?text=REG-'.$n,
                'email_bpujl_req_date' => now()->subDays(15)->format('Y-m-d'),
                'bpujl_acquired_date' => now()->subDays(10)->format('Y-m-d'),
                'kwh_meter_installation_date' => now()->subDays(3)->format('Y-m-d'),
                'id_pelanggan' => 'PLN-'.str_pad($n * 100000, 6, '0', STR_PAD_LEFT),
                'foto_kwh' => $this->generatePlaceholderImage('Foto KWH Meter', 'pln'),
                'catatan_progres' => 'Proses sambungan PLN berjalan sesuai rencana.',
            ],
        );
    }

    /**
     * For COMPLETED/VERIFIED/REPORTED/REVISION statuses the BAST form was submitted,
     * so we must also create all 9 required checkpoint photos — otherwise isComplete() returns false
     * and the UI would show an inconsistent state.
     */
    private function seedBastData(Assignment $assignment, int $n, array $scenario, AssignmentStatus $targetStatus): void
    {
        $isEvcs = $scenario['site_type_id'] === DB::table('site_types')->where('name', 'EVCS')->value('id');

        $bastData = AssignmentBastData::firstOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'plant_name' => $scenario['location'],
                'plant_address' => $scenario['address'],
                'plant_coordinate' => '-6.'.str_pad($n * 111_111, 6, '0').', 106.'.str_pad($n * 111_111, 6, '0'),
                'gmaps_link' => 'https://maps.google.com/?q=-6,106',
                'charger_type' => $isEvcs ? 'EVCS 22kW' : 'BSS-500',
                'sn_unit' => 'SN-'.str_pad($n * 100_000, 6, '0', STR_PAD_LEFT),
                'id_pln' => 'IDP-'.str_pad($n * 100_000, 6, '0', STR_PAD_LEFT),
                'sim_provider' => 'Telkomsel',
                'installation_vendor' => $scenario['cons_subcon']->name,
                'pic_vendor_contact' => $scenario['cons_subcon']->phone,
                'installation_date' => now()->subDays(3)->format('Y-m-d'),
                'commissioning_date' => now()->subDays(1)->format('Y-m-d'),
                'customer' => 'vGreen Indonesia',
                'nomor_simcard' => '0811'.str_pad($n * 111_111, 9, '0', STR_PAD_LEFT),
                'go_live_date_pln_pass' => now()->subDays(2)->format('Y-m-d'),
                'go_live_date_pln' => now()->subDays(1)->format('Y-m-d'),
            ],
        );

        // Create all required checkpoint photos for any submitted status
        if ($targetStatus !== AssignmentStatus::Pending) {
            $checkpointSections = [
                'device_front_view_open' => 'device',
                'device_front_view_close' => 'device',
                'sim_kartu_perdana' => 'sim_card',
                'sim_installed_sim_card' => 'sim_card',
                'grounding_rod_connection' => 'grounding',
                'grounding_cable_route' => 'grounding',
                'kwh_kwh_meter' => 'kwh_meter',
                'ac_front_view_open' => 'ac_panel',
                'cable_spec' => 'cables',
            ];

            foreach (AssignmentBastData::REQUIRED_CHECKPOINTS as $checkpointKey) {
                AssignmentBastPhoto::firstOrCreate(
                    [
                        'assignment_bast_data_id' => $bastData->id,
                        'checkpoint_key' => $checkpointKey,
                    ],
                    [
                        'section' => $checkpointSections[$checkpointKey] ?? 'device',
                        'photo_path' => $this->generatePlaceholderImage($checkpointKey, 'bast'),
                    ],
                );
            }
        }
    }
}
