<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'artist_id',
        'album_id',
        'genre_id',
        'spotify_track_id',
        'audio_path',
        'duration',
        'play_count',
        'tempo',
        'energy',
        'danceability',
        'valence',
        'popularity',
        'release_date',
    ];

    protected $casts = [
        'duration' => 'integer',
        'play_count' => 'integer',
        'tempo' => 'float',
        'energy' => 'float',
        'danceability' => 'float',
        'valence' => 'float',
        'popularity' => 'integer',
        'release_date' => 'date',
    ];

    public static function popularityFromPlayCount(int|float|null $playCount): int
    {
        $plays = max(0.0, (float) ($playCount ?? 0));

        if ($plays <= 0.0) {
            return 0;
        }

        return (int) min(100, round((log10($plays + 1) / 5) * 100));
    }

    public function refreshPopularityFromPlayCount(bool $save = true): int
    {
        $this->popularity = self::popularityFromPlayCount($this->play_count);

        if ($save && $this->isDirty('popularity')) {
            $this->save();
        }

        return (int) $this->popularity;
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_song');
    }
    
    public function comments(): HasMany
    {
        return $this->hasMany(TrackComment::class);
    }
}
