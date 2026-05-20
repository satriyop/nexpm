<?php

use App\Models\MachineType;
use App\Models\MainContractor;
use App\Models\Project;
use App\Models\Site;
use App\Models\SiteType;
use App\Services\SiteCsvImportService;
use Illuminate\Support\Facades\File;

function makeSiteCsv(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'sites_');
    File::put($path, $body);

    return $path;
}

test('creates new sites and updates existing ones via site_code upsert', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();

    $evcs = SiteType::factory()->create(['name' => 'EVCS']);
    $machine = MachineType::factory()->create(['name' => '50kVA']);

    Site::factory()->for($project)->create([
        'site_code' => 'SITE-DUP',
        'location_name' => 'Old Name',
    ]);

    $csv = "site_code,location_name,address,province,city,google_map_url,latitude,longitude,site_type,machine_type,bd_pic,ss_wo_number,cable_length_to_panel,charging_station_count\n";
    $csv .= "SITE-DUP,New Name,Jl. Sudirman,DKI Jakarta,Jakarta,https://maps.example.com,-6.2,106.8,EVCS,50kVA,Bob,WO-1,12.5,2\n";
    $csv .= "SITE-NEW,Brand New,Jl. Thamrin,DKI Jakarta,Jakarta,https://maps.example.com,-6.1,106.9,EVCS,50kVA,Alice,WO-2,8.0,1\n";

    $path = makeSiteCsv($csv);

    $result = (new SiteCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(1)
        ->and($result['updated'])->toBe(1)
        ->and($result['errors'])->toBe([]);

    expect(Site::query()->where('site_code', 'SITE-DUP')->first()->location_name)->toBe('New Name')
        ->and(Site::query()->where('site_code', 'SITE-NEW')->first()->site_type_id)->toBe($evcs->id)
        ->and(Site::query()->where('site_code', 'SITE-NEW')->first()->machine_type_id)->toBe($machine->id);

    File::delete($path);
});

test('imports approved_budget and auto-calculates DPP amounts', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();

    $csv = "site_code,location_name,address,province,city,google_map_url,latitude,longitude,site_type,machine_type,bd_pic,ss_wo_number,cable_length_to_panel,charging_station_count,power_kva,approved_budget\n";
    $csv .= "SITE-BUDGET,Mall A,,,,,,,,,,,,,,1000000000\n";

    $path = makeSiteCsv($csv);
    $result = (new SiteCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(1)->and($result['errors'])->toBe([]);

    $site = Site::query()->where('site_code', 'SITE-BUDGET')->first();
    expect($site->approved_budget)->toBe(1_000_000_000)
        ->and($site->dp_35_dpp_amount)->toBe(350_000_000)
        ->and($site->invoice_60_dpp_amount)->toBe(600_000_000)
        ->and($site->invoice_5_dpp_amount)->toBe(50_000_000);

    File::delete($path);
});

test('does not wipe approved_budget when CSV omits the column', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();

    Site::factory()->for($project)->create([
        'site_code' => 'SITE-KEEP',
        'location_name' => 'Old Name',
        'approved_budget' => 750_000_000,
        'dp_35_dpp_amount' => 262_500_000,
    ]);

    // 14-column CSV (no power_kva, no approved_budget)
    $csv = "site_code,location_name,address,province,city,google_map_url,latitude,longitude,site_type,machine_type,bd_pic,ss_wo_number,cable_length_to_panel,charging_station_count\n";
    $csv .= "SITE-KEEP,Updated Name,,,,,,,,,,,,\n";

    $path = makeSiteCsv($csv);
    (new SiteCsvImportService)->import($path, $project->id);

    $site = Site::query()->where('site_code', 'SITE-KEEP')->first();
    expect($site->location_name)->toBe('Updated Name')
        ->and($site->approved_budget)->toBe(750_000_000)
        ->and($site->dp_35_dpp_amount)->toBe(262_500_000);

    File::delete($path);
});

test('does not wipe approved_budget when column is present but empty', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();

    Site::factory()->for($project)->create([
        'site_code' => 'SITE-EMPTY',
        'location_name' => 'Old Name',
        'approved_budget' => 500_000_000,
    ]);

    // 16-column CSV with blank approved_budget cell
    $csv = "site_code,location_name,address,province,city,google_map_url,latitude,longitude,site_type,machine_type,bd_pic,ss_wo_number,cable_length_to_panel,charging_station_count,power_kva,approved_budget\n";
    $csv .= "SITE-EMPTY,Updated Name,,,,,,,,,,,,,, \n";

    $path = makeSiteCsv($csv);
    (new SiteCsvImportService)->import($path, $project->id);

    expect(Site::query()->where('site_code', 'SITE-EMPTY')->first()->approved_budget)
        ->toBe(500_000_000);

    File::delete($path);
});

test('rejects non-numeric approved_budget', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();

    $csv = "site_code,location_name,address,province,city,google_map_url,latitude,longitude,site_type,machine_type,bd_pic,ss_wo_number,cable_length_to_panel,charging_station_count,power_kva,approved_budget\n";
    $csv .= "SITE-BAD,Mall B,,,,,,,,,,,,,,not-a-number\n";

    $path = makeSiteCsv($csv);
    $result = (new SiteCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(0)
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0])->toContain('approved_budget must be a non-negative integer');

    expect(Site::query()->where('site_code', 'SITE-BAD')->exists())->toBeFalse();

    File::delete($path);
});

test('reports errors for missing required fields', function () {
    $contractor = MainContractor::factory()->create();
    $project = Project::factory()->for($contractor, 'mainContractor')->create();

    $csv = "site_code,location_name,address,province,city,google_map_url,latitude,longitude,site_type,machine_type,bd_pic,ss_wo_number,cable_length_to_panel,charging_station_count\n";
    $csv .= ",No code,addr,prov,city,,,,,,,,,\n";
    $csv .= "SITE-X,,addr,prov,city,,,,,,,,,\n";

    $path = makeSiteCsv($csv);

    $result = (new SiteCsvImportService)->import($path, $project->id);

    expect($result['created'])->toBe(0)
        ->and($result['updated'])->toBe(0)
        ->and($result['errors'])->toHaveCount(2);

    File::delete($path);
});
