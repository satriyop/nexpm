<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('dp_35_inv_number')->nullable()->after('dp_35_date');
            $table->decimal('dp_35_dpp_amount', 15, 2)->nullable()->after('dp_35_inv_number');
            $table->string('invoice_60_inv_number')->nullable()->after('payment_60_date');
            $table->decimal('invoice_60_dpp_amount', 15, 2)->nullable()->after('invoice_60_inv_number');
            $table->string('invoice_5_inv_number')->nullable()->after('payment_5_date');
            $table->decimal('invoice_5_dpp_amount', 15, 2)->nullable()->after('invoice_5_inv_number');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'dp_35_inv_number', 'dp_35_dpp_amount',
                'invoice_60_inv_number', 'invoice_60_dpp_amount',
                'invoice_5_inv_number', 'invoice_5_dpp_amount',
            ]);
        });
    }
};
