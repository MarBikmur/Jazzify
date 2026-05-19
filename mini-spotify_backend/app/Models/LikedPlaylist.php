<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LikedPlaylist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_uid',
        'playlist_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uid', 'uid');
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }
}
