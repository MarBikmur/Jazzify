<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    use HasFactory;

    public const FAVORITES_PLAYLIST_TITLE = 'Liked Songs';
    public const LEGACY_FAVORITES_PLAYLIST_TITLE = 'Любимые песни';

    protected $fillable = [
        'title',
        'user_uid',
        'cover_image_path',
        'is_private',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    protected $appends = [
        'cover_image_url',
    ];

    public static function favoritesTitles(): array
    {
        return [
            self::FAVORITES_PLAYLIST_TITLE,
            self::LEGACY_FAVORITES_PLAYLIST_TITLE,
        ];
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return url('/api/media/' . ltrim($this->cover_image_path, '/'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uid', 'uid');
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'playlist_song');
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'liked_playlists', 'playlist_id', 'user_uid', 'id', 'uid')
            ->withTimestamps();
    }
}
