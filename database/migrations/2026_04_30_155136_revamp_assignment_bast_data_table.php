<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_bast_data', function (Blueprint $table) {
            $table->dropColumn(['nomor_simcard', 'go_live_date_pln_pass', 'go_live_date_pln', 'catatan_progres']);

            $table->string('plant_name')->nullable()->after('assignment_id');
            $table->text('plant_address')->nullable()->after('plant_name');
            $table->string('plant_coordinate')->nullable()->after('plant_address');
            $table->string('gmaps_link')->nullable()->after('plant_coordinate');
            $table->string('charger_type')->nullable()->after('gmaps_link');
            $table->string('sn_unit')->nullable()->after('charger_type');
            $table->string('id_pln')->nullable()->after('sn_unit');
            $table->string('sim_provider')->nullable()->after('id_pln');
            $table->string('installation_vendor')->nullable()->after('sim_provider');
            $table->string('pic_vendor_contact')->nullable()->after('installation_vendor');
            $table->date('installation_date')->nullable()->after('pic_vendor_contact');
            $table->date('commissioning_date')->nullable()->after('installation_date');
            $table->string('customer')->nullable()->after('commissioning_date');
            $table->json('measurements')->nullable()->after('customer');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_bast_data', function (Blueprint $table) {
            $table->dropColumn([
                'plant_name', 'plant_address', 'plant_coordinate', 'gmaps_link',
                'charger_type', 'sn_unit', 'id_pln', 'sim_provider',
                'installation_vendor', 'pic_vendor_contact', 'installation_date',
                'commissioning_date', 'customer', 'measurements',
            ]);

            $table->string('nomor_simcard')->nullable();
            $table->date('go_live_date_pln_pass')->nullable();
            $table->date('go_live_date_pln')->nullable();
            $table->text('catatan_progres')->nullable();
        });
    }
};
