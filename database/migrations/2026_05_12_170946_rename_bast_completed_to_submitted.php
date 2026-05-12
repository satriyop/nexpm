<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE assignments SET status = 'SUBMITTED' WHERE status = 'COMPLETED'");
    }

    public function down(): void
    {
        DB::statement("UPDATE assignments SET status = 'COMPLETED' WHERE status = 'SUBMITTED'");
    }
};
