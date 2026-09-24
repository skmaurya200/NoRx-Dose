<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * What a message was understood to be asking for.
     *
     * product_search lists the catalogue; product_detail talks about one
     * product; follow_up continues whatever the last answer was about;
     * website_question is answered from indexed store content; unsupported is
     * everything the store has no business answering.
     */
    public const INTENTS = [
        'greeting', 'product_search', 'product_detail', 'follow_up',
        'website_question', 'unclear', 'unsupported',
    ];

    /**
     * Who actually wrote the row. `sender` is the side of the conversation and
     * cannot say this: a manager's reply is still the assistant side.
     */
    public const AUTHOR_GUEST = 'guest';

    public const AUTHOR_AI = 'ai';

    public const AUTHOR_MANAGER = 'manager';

    protected $table = 'tbl_chat_messages';

    protected $fillable = [
        'message_uuid', 'request_uuid', 'reply_to_id', 'sender', 'authored_by', 'admin_id',
        'message', 'message_type', 'intent', 'ai_model', 'tokens_used', 'metadata',
    ];

    protected $hidden = ['id', 'chat_session_id', 'reply_to_id', 'request_uuid', 'metadata', 'admin_id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'tokens_used' => 'integer'];
    }

    /**
     * The admin who typed this, when a person answered rather than the model.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function reply(): HasOne
    {
        return $this->hasOne(self::class, 'reply_to_id');
    }

    public function productResults(): HasMany
    {
        return $this->hasMany(ChatProductResult::class)->orderBy('position');
    }

    public function contact(): HasOne
    {
        return $this->hasOne(ChatContact::class);
    }
}
