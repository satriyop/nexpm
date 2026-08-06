<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $tool_name
 * @property string|null $token_prefix
 * @property int|null $acting_user_id
 * @property string $status
 * @property int $latency_ms
 * @property array|null $request_summary
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon $created_at
 */
class McpAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'mcp_audit_logs';

    protected $fillable = [
        'tool_name', 'token_prefix', 'acting_user_id',
        'status', 'latency_ms', 'request_summary', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'request_summary' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
