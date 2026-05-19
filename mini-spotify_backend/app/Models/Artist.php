<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Artist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_uid',
        'country_id',
        'spotify_artist_id',
        'image_path',
        'followers_count',
    ];

    protected $appends = [
        'image_url',
    ];

    protected $casts = [
        'followers_count' => 'integer',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return URL::to('/api/media/' . ltrim($this->image_path, '/'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uid', 'uid');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'liked_artists', 'artist_id', 'user_uid', 'id', 'uid')
            ->withTimestamps();
    }
}
