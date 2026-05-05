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
