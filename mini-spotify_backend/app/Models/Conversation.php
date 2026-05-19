<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'direct_key',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants', 'conversation_id', 'user_uid', 'id', 'uid')
            ->withPivot(['last_read_at', 'archived_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public static function makeDirectKey(string $firstUserUid, string $secondUserUid): string
    {
        $uids = [$firstUserUid, $secondUserUid];
        sort($uids, SORT_STRING);

        return implode(':', $uids);
    }
}