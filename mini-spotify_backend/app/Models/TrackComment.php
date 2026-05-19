<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'song_id',
        'user_uid',
        'body',
        'timestamp_seconds',
    ];

    protected $casts = [
        'song_id' => 'integer',
        'timestamp_seconds' => 'integer',
    ];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uid', 'uid');
    }
}