<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    protected $primaryKey = 'uid';
    public $incrementing = false;
    protected $keyType = 'string';
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_path',
        'followers_count',
    ];

    protected $hidden = [
        'password',
    ];

    protected $appends = [
        'avatar_url',
    ];
    protected function casts(): array{
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'followers_count' => 'integer',
        ];
    }

    protected static function boot(){
        parent::boot();

        static::creating(function($model){
            if(empty($model->{$model->getKeyName()})){
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) {
            return null;
        }

        return URL::to('/api/media/' . ltrim($this->avatar_path, '/'));
    }

    public function artist(): HasOne
    {
        return $this->hasOne(Artist::class, 'user_uid', 'uid');
    }

    public function artists(): HasMany
    {
        return $this->hasMany(Artist::class, 'user_uid', 'uid');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class, 'user_uid', 'uid');
    }

    public function trackComments(): HasMany
    {
        return $this->hasMany(TrackComment::class, 'user_uid', 'uid');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'followed_users', 'followed_uid', 'follower_uid', 'uid', 'uid')
            ->withTimestamps();
    }

    public function followedUsers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'followed_users', 'follower_uid', 'followed_uid', 'uid', 'uid')
            ->withTimestamps();
    }

    public function scopeWithFollowStateForViewer(Builder $query, ?self $viewer): Builder
    {
        $query->select('users.*');

        if (! $viewer) {
            return $query->selectRaw('0 as is_following');
        }

        return $query->selectSub(
            DB::table('followed_users')
                ->selectRaw('1')
                ->whereColumn('followed_users.followed_uid', 'users.uid')
                ->where('followed_users.follower_uid', $viewer->uid)
                ->limit(1),
            'is_following'
        );
    }

    public function likedAlbums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'liked_albums', 'user_uid', 'album_id', 'uid', 'id')
            ->withTimestamps();
    }

    public function likedPlaylists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'liked_playlists', 'user_uid', 'playlist_id', 'uid', 'id')
            ->withTimestamps();
    }

    public function followedArtists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'liked_artists', 'user_uid', 'artist_id', 'uid', 'id')
            ->withTimestamps();
    }
    public function isArtist(): bool
    {
        if ($this->relationLoaded('artist')) {
            return $this->artist !== null;
        }

        return $this->artist()->exists();
    }
}
