<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist_id',
        'spotify_album_id',
        'cover_image_path',
    ];

    protected $appends = [
        'cover_image_url',
    ];

    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image_path) {
            return null;
        }

        return url('/api/media/' . ltrim($this->cover_image_path, '/'));
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'liked_albums', 'album_id', 'user_uid', 'id', 'uid')
            ->withTimestamps();
    }
}
