<?php

namespace App\Models;

use Database\Factories\ChatContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatContact extends Model
{
    /** @use HasFactory<ChatContactFactory> */
    use HasFactory;

    protected $table = 'tbl_chat_contacts';

    protected $fillable = ['chat_message_id', 'name', 'email', 'phone', 'message'];

    protected $hidden = ['id', 'chat_session_id', 'name', 'email', 'phone', 'message'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
