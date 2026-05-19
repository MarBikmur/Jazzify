<?php

namespace App\Services;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Song;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminSongUploadService
{
    public function __construct(
        protected UploadedTrackMetadataService $uploadedTrackMetadataService,
    ) {
    }

    public function analyze(UploadedFile $file, ?int $clientDuration = null): array
    {
        $metadata = $this->uploadedTrackMetadataService->extract($file, [
            'duration' => $clientDuration,
        ]);
        $resolved = $metadata['resolved'];

        $draft = [
            'title' => trim((string) ($resolved['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))),
            'artist' => trim((string) ($resolved['artist'] ?? 'Unknown Artist')),
            'album' => trim((string) ($resolved['album'] ?? $resolved['title'] ?? 'Unknown Album')),
            'genre' => trim((string) ($resolved['genre'] ?? 'Unknown')),
            'duration' => $resolved['duration'] ?? null,
            'release_date' => $this->normalizeDate($resolved['release_date'] ?? null),
            'tempo' => $this->normalizeFloat($resolved['tempo'] ?? null),
            'energy' => $this->normalizeFloat($resolved['energy'] ?? null),
            'danceability' => $this->normalizeFloat($resolved['danceability'] ?? null),
            'valence' => $this->normalizeFloat($resolved['valence'] ?? null),
            'spotify_track_id' => trim((string) ($resolved['spotify_track_id'] ?? '')) ?: null,
            'spotify_artist_id' => trim((string) ($resolved['spotify_artist_id'] ?? '')) ?: null,
            'spotify_album_id' => trim((string) ($resolved['spotify_album_id'] ?? '')) ?: null,
            'spotify_track_url' => trim((string) ($resolved['spotify_track_url'] ?? '')) ?: null,
            'spotify_artist_url' => trim((string) ($resolved['spotify_artist_url'] ?? '')) ?: null,
            'spotify_album_url' => trim((string) ($resolved['spotify_album_url'] ?? '')) ?: null,
            'spotify_artist_image_url' => trim((string) ($resolved['spotify_artist_image_url'] ?? '')) ?: null,
            'spotify_album_cover_url' => trim((string) ($resolved['spotify_album_cover_url'] ?? '')) ?: null,
            'spotify_artist_followers_count' => $this->normalizeInteger($resolved['spotify_artist_followers_count'] ?? null),
        ];

        $warnings = [];
        if (! empty($metadata['warning'])) {
            $warnings[] = (string) $metadata['warning'];
        }

        $duplicate = $this->findDuplicateSong($draft);
        $draftPlayCount = (int) ($duplicate?->play_count ?? 0);
        $draft['play_count'] = $draftPlayCount;
        $draft['popularity'] = Song::popularityFromPlayCount($draftPlayCount);

        return [
            'draft' => $draft,
            'spotify_used' => (bool) ($metadata['spotify_used'] ?? false),
            'audio_features_used' => (bool) ($metadata['audio_features_used'] ?? false),
            'warnings' => $warnings,
            'duplicate' => $duplicate ? [
                'id' => $duplicate->id,
                'title' => $duplicate->title,
                'artist' => $duplicate->artist?->name,
                'album' => $duplicate->album?->title,
                'public_url' => $duplicate->artist_id && $duplicate->album_id ? "/albums/{$duplicate->artist_id}/{$duplicate->album_id}" : null,
            ] : null,
        ];
    }

    public function createFromDraft(UploadedFile $file, array $draft, bool $forceReupload = false): array
    {
        $duplicate = $this->findDuplicateSong($draft);
        if ($duplicate && ! $forceReupload) {
            abort(422, 'This track already exists in the catalog');
        }

        return DB::transaction(function () use ($file, $draft, $duplicate, $forceReupload) {
            $genre = $this->resolveGenre($draft['genre'] ?? null);
            $artist = $this->resolveArtist($draft, $forceReupload);
            $album = $this->resolveAlbum($draft, $artist, $forceReupload);
            $audioPath = $file->store('songs', 'public');
            $requestedPlayCount = $this->normalizeInteger($draft['play_count'] ?? null);
            $playCount = $requestedPlayCount
                ?? ($duplicate && $forceReupload
                    ? max(0, (int) ($duplicate->play_count ?? 0))
                    : 0);
            $resolvedTitle = trim((string) ($draft['title'] ?? ''));

            if ($resolvedTitle === '') {
                $resolvedTitle = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            }

            if ($resolvedTitle === '') {
                $resolvedTitle = 'Unknown Track';
            }

            $this->syncArtistImageFromRemote($artist, $draft['spotify_artist_image_url'] ?? null, $forceReupload);
            $this->syncAlbumCoverFromRemote($album, $draft['spotify_album_cover_url'] ?? null, $forceReupload);

            $payload = [
                'title' => $resolvedTitle,
                'artist_id' => $artist->id,
                'album_id' => $album->id,
                'genre_id' => $genre->id,
                'spotify_track_id' => trim((string) ($draft['spotify_track_id'] ?? '')) ?: null,
                'audio_path' => $audioPath,
                'duration' => $this->normalizeInteger($draft['duration'] ?? null),
                'play_count' => $playCount,
                'tempo' => $this->normalizeFloat($draft['tempo'] ?? null),
                'energy' => $this->normalizeFloat($draft['energy'] ?? null),
                'danceability' => $this->normalizeFloat($draft['danceability'] ?? null),
                'valence' => $this->normalizeFloat($draft['valence'] ?? null),
                'popularity' => Song::popularityFromPlayCount($playCount),
                'release_date' => $this->normalizeDate($draft['release_date'] ?? null),
            ];

            if ($duplicate && $forceReupload) {
                $oldAudioPath = $duplicate->audio_path;
                $duplicate->fill($payload);
                $duplicate->save();

                if ($oldAudioPath && $oldAudioPath !== $audioPath) {
                    Storage::disk('public')->delete($oldAudioPath);
                }

                $song = $duplicate->fresh(['artist', 'album', 'genre']);
            } else {
                $song = Song::query()->create($payload)->load(['artist', 'album', 'genre']);
            }

            return [
                'song' => $song,
                'artist' => $artist,
                'album' => $album,
                'genre' => $genre,
                'draft' => $draft,
                'public_url' => $artist->id && $album->id ? "/albums/{$artist->id}/{$album->id}" : null,
                'title' => $song->title,
                'reuploaded' => (bool) ($duplicate && $forceReupload),
            ];
        });
    }

    protected function findDuplicateSong(array $draft): ?Song
    {
        $spotifyTrackId = trim((string) ($draft['spotify_track_id'] ?? ''));
        if ($spotifyTrackId !== '') {
            $bySpotify = Song::query()
                ->with(['artist', 'album'])
                ->where('spotify_track_id', $spotifyTrackId)
                ->first();

            if ($bySpotify) {
                return $bySpotify;
            }
        }

        $title = Str::lower(trim((string) ($draft['title'] ?? '')));
        $artist = Str::lower(trim((string) ($draft['artist'] ?? '')));
        $album = Str::lower(trim((string) ($draft['album'] ?? '')));

        if ($title === '' || $artist === '') {
            return null;
        }

        return Song::query()
            ->with(['artist', 'album'])
            ->whereRaw('LOWER(title) = ?', [$title])
            ->whereHas('artist', fn ($query) => $query->whereRaw('LOWER(name) = ?', [$artist]))
            ->when($album !== '', fn ($query) => $query->whereHas('album', fn ($albumQuery) => $albumQuery->whereRaw('LOWER(title) = ?', [$album])))
            ->first();
    }

    protected function resolveGenre(?string $name): Genre
    {
        $name = trim((string) $name);
        $name = $name !== '' ? $name : 'Unknown';

        return Genre::query()->firstOrCreate(['name' => $name], ['name' => $name]);
    }

    protected function resolveArtist(array $draft, bool $forceRefresh = false): Artist
    {
        $name = trim((string) ($draft['artist'] ?? 'Unknown Artist'));
        $name = $name !== '' ? $name : 'Unknown Artist';
        $spotifyArtistId = trim((string) ($draft['spotify_artist_id'] ?? ''));
        $spotifyFollowersCount = $this->normalizeInteger($draft['spotify_artist_followers_count'] ?? null);

        $artist = null;

        if ($spotifyArtistId !== '') {
            $artist = Artist::query()->where('spotify_artist_id', $spotifyArtistId)->first();
        }

        if (! $artist) {
            $artist = Artist::query()->firstOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'user_uid' => null,
                    'country_id' => null,
                    'spotify_artist_id' => $spotifyArtistId !== '' ? $spotifyArtistId : null,
                    'image_path' => null,
                    'followers_count' => 0,
                ],
            );
        }

        if (($forceRefresh || ! $artist->spotify_artist_id) && $spotifyArtistId !== '') {
            $artist->spotify_artist_id = $spotifyArtistId;
        }

        if (($forceRefresh || ($artist->followers_count ?? 0) <= 0) && $spotifyFollowersCount !== null) {
            $artist->followers_count = $spotifyFollowersCount;
        }

        if ($forceRefresh && $artist->name !== $name && $name !== '') {
            $artist->name = $name;
        }

        if ($artist->isDirty()) {
            $artist->save();
        }

        return $artist;
    }

    protected function resolveAlbum(array $draft, Artist $artist, bool $forceRefresh = false): Album
    {
        $title = trim((string) ($draft['album'] ?? $draft['title'] ?? 'Unknown Album'));
        $title = $title !== '' ? $title : 'Unknown Album';
        $spotifyAlbumId = trim((string) ($draft['spotify_album_id'] ?? ''));

        $album = null;

        if ($spotifyAlbumId !== '') {
            $album = Album::query()
                ->where('artist_id', $artist->id)
                ->where('spotify_album_id', $spotifyAlbumId)
                ->first();
        }

        if (! $album) {
            $album = Album::query()->firstOrCreate(
                [
                    'artist_id' => $artist->id,
                    'title' => $title,
                ],
                [
                    'artist_id' => $artist->id,
                    'title' => $title,
                    'spotify_album_id' => $spotifyAlbumId !== '' ? $spotifyAlbumId : null,
                    'cover_image_path' => null,
                ],
            );
        }

        if (($forceRefresh || ! $album->spotify_album_id) && $spotifyAlbumId !== '') {
            $album->spotify_album_id = $spotifyAlbumId;
        }

        if ($forceRefresh && $album->title !== $title && $title !== '') {
            $album->title = $title;
        }

        if ($album->isDirty()) {
            $album->save();
        }

        return $album;
    }

    protected function syncArtistImageFromRemote(Artist $artist, ?string $url, bool $forceRefresh = false): void
    {
        if (! $url) {
            return;
        }

        if ($forceRefresh && $artist->image_path) {
            Storage::disk('public')->delete($artist->image_path);
            $artist->image_path = null;
        }

        if ($artist->image_path) {
            return;
        }

        $downloaded = $this->downloadRemoteImage($url, 'artists', $artist->name ?: 'artist');
        if (! $downloaded) {
            return;
        }

        $artist->image_path = $downloaded;
        $artist->save();
    }

    protected function syncAlbumCoverFromRemote(Album $album, ?string $url, bool $forceRefresh = false): void
    {
        if (! $url) {
            return;
        }

        if ($forceRefresh && $album->cover_image_path) {
            Storage::disk('public')->delete($album->cover_image_path);
            $album->cover_image_path = null;
        }

        if ($album->cover_image_path) {
            return;
        }

        $downloaded = $this->downloadRemoteImage($url, 'albums', $album->title ?: 'album');
        if (! $downloaded) {
            return;
        }

        $album->cover_image_path = $downloaded;
        $album->save();
    }

    protected function downloadRemoteImage(string $url, string $directory, string $name): ?string
    {
        try {
            $response = Http::timeout((int) config('services.spotify.timeout', 15))->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->storeBinaryImage(
            $directory,
            $name,
            $response->body(),
            $response->header('Content-Type'),
            $url,
        );
    }

    protected function storeBinaryImage(string $directory, string $name, string $bytes, ?string $mime = null, ?string $sourceUrl = null): string
    {
        $extension = $this->detectImageExtension($mime, $sourceUrl);
        $filename = $directory . '/' . Str::slug($name ?: $directory) . '-' . Str::random(10) . '.' . $extension;
        Storage::disk('public')->put($filename, $bytes);

        return $filename;
    }

    protected function detectImageExtension(?string $mime = null, ?string $sourceUrl = null): string
    {
        $mime = Str::lower(trim((string) $mime));

        if ($mime !== '') {
            return match (true) {
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'gif') => 'gif',
                str_contains($mime, 'webp') => 'webp',
                default => 'jpg',
            };
        }

        $path = parse_url((string) $sourceUrl, PHP_URL_PATH);
        $extension = Str::lower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
            ? ($extension === 'jpeg' ? 'jpg' : $extension)
            : 'jpg';
    }

    protected function normalizeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value . '-01';
        }

        if (preg_match('/^\d{4}$/', $value)) {
            return $value . '-01-01';
        }

        return null;
    }

    protected function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) round((float) $value));
    }

    protected function normalizeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

}
