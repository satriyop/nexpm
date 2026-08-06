<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class McpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        require base_path('routes/ai.php');
    }
}
