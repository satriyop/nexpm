<?php

use App\Http\Middleware\AuthenticateAiMcp;
use App\Mcp\Servers\NexpmOpsServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| AI Routes
|--------------------------------------------------------------------------
|
| Web MCP (agents): POST /mcp/nexpm-ops  with Authorization: Bearer <token>
| Local MCP (dev):  php artisan mcp:start nexpm-ops
|
| See tasks/backlog/mcp1-mcp-server/TASK.md
|
*/

Route::middleware(['throttle:mcp', AuthenticateAiMcp::class])
    ->group(function (): void {
        Mcp::web('/mcp/nexpm-ops', NexpmOpsServer::class);
    });

Mcp::local('nexpm-ops', NexpmOpsServer::class);
