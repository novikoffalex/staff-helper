<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    protected $fillable = [
        'telegram_id',
        'first_name',
        'last_name',
        'username',
        'is_bot',
        'language_code',
        'is_active',
        'last_seen_at',
        'user_context'
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'user_context' => 'array'
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function getLatestMessages(int $limit = 10)
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function updateContext(array $context)
    {
        $this->user_context = array_merge($this->user_context ?? [], $context);
        $this->save();
    }
}
