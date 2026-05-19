<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LikedArtist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_uid',
        'artist_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uid', 'uid');
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
