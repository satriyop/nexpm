<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ai_conversation_id', 'role', 'content', 'tool_name', 'tool_payload', 'usage'])]
class AiMessage extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool_payload' => 'array',
            'usage' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AiConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
