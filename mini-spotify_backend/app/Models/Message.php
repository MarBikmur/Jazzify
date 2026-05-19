<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Message extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_uid',
        'message_type',
        'body',
        'shared_item',
        'edited_at',
    ];

    protected $hidden = [
        'encrypted_body',
    ];

    protected $appends = [
        'body',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
        'shared_item' => 'array',
    ];

    protected function body(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->encrypted_body) {
                    return null;
                }

                return Crypt::decryptString($this->encrypted_body);
            },
            set: fn(string $value) => ['encrypted_body' => Crypt::encryptString($value)],
        );
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_uid', 'uid');
    }
}