<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'telegram_message_id',
        'message_type',
        'message_content',
        'ai_response',
        'message_metadata',
        'processed_at'
    ];

    protected $casts = [
        'message_metadata' => 'array',
        'processed_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class, 'telegram_user_id');
    }
}
