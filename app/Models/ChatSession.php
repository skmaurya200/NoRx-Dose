<?php

namespace App\Models;

use Database\Factories\ChatSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatSession extends Model
{
    /** @use HasFactory<ChatSessionFactory> */
    use HasFactory;

    protected $table = 'tbl_chat_sessions';

    /**
     * A conversation the model is answering, or one a person has taken over.
     */
    public const MODE_AI = 'ai';

    public const MODE_MANUAL = 'manual';

    public const MODES = [self::MODE_AI, self::MODE_MANUAL];

    protected $fillable = [
        'session_uuid', 'owner_hash', 'reply_mode', 'context', 'last_message_at', 'unread_count',
    ];

    /**
     * owner_hash is the only thing standing between one guest's conversation
     * and another's, so it never leaves the server - and neither does the
     * numeric id the panel addresses rows by.
     */
    protected $hidden = ['id', 'owner_hash', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }

    /**
     * True while a person is handling this conversation, which is the one
     * condition under which the model must not answer.
     */
    public function isManual(): bool
    {
        return $this->reply_mode === self::MODE_MANUAL;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ChatContact::class);
    }

    /**
     * The most recent message, for the panel's conversation list. A relation
     * rather than a query per row, so the list is one extra query and not one
     * per conversation.
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    /**
     * The visitor's contact details, when they left any. The newest wins: a
     * guest who filled the form twice corrected themselves.
     */
    public function latestContact(): HasOne
    {
        return $this->hasOne(ChatContact::class)->latestOfMany();
    }
}
