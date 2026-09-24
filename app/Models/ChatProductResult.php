<?php

namespace App\Models;

use Database\Factories\ChatProductResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatProductResult extends Model
{
    /** @use HasFactory<ChatProductResultFactory> */
    use HasFactory;

    protected $table = 'tbl_chat_product_results';

    protected $fillable = ['product_id', 'position', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'position' => 'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
