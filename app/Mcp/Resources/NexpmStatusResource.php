<?php

namespace App\Mcp\Resources;

use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('NexPM system status: assignment counts by status, recent activity window.')]
class NexpmStatusResource extends Resource
{
    public function uri(): string
    {
        return 'nexpm://status';
    }

    public function name(): string
    {
        return 'NexPM System Status';
    }

    public function mimeType(): string
    {
        return 'application/json';
    }

    public function content(): string
    {
        $stats = DB::table('assignments')
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status => (int) $row->count])
            ->all();

        $status = match (true) {
            empty($stats) => 'initialized',
            default => 'operational',
        };

        return json_encode([
            'status' => $status,
            'generated_at' => now()->toIso8601String(),
            'assignments_total' => (int) array_sum($stats),
            'assignments_by_status' => $stats,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
