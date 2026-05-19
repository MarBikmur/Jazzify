<?php

namespace App\Services\AudioMetadata;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SpotifyLookupService
{
    protected const TOKEN_CACHE_KEY = 'spotify:web-api:client-credentials-token';

    public function lookupTrack(?string $title, ?string $artist = null, ?string $album = null, bool $fetchAudioFeatures = true): array
    {
        $title = trim((string) $title);
        $artist = trim((string) $artist);
        $album = trim((string) $album);

        if ($title === '') {
            return [
                'matched' => false,
                'spotify_used' => false,
                'audio_features_used' => false,
                'warning' => 'Track title is missing, so Spotify lookup was skipped.',
                'track' => null,
            ];
        }

        if (! $this->isConfigured()) {
            return [
                'matched' => false,
                'spotify_used' => false,
                'audio_features_used' => false,
                'warning' => 'Spotify credentials are not configured in mini-spotify_backend/.env.',
                'track' => null,
            ];
        }

        try {
            $token = $this->getAccessToken();
        } catch (\Throwable) {
            return [
                'matched' => false,
                'spotify_used' => false,
                'audio_features_used' => false,
                'warning' => 'Could not authorize with Spotify Web API.',
                'track' => null,
            ];
        }

        $candidate = $this->searchBestTrackCandidate($token, $title, $artist, $album);

        if (! $candidate) {
            return [
                'matched' => false,
                'spotify_used' => false,
                'audio_features_used' => false,
                'warning' => 'Spotify did not return a confident match for this track.',
                'track' => null,
            ];
        }

        $trackId = (string) ($candidate['id'] ?? '');
        $track = $this->fetchTrack($token, $trackId) ?? $candidate;
        $artistId = (string) Arr::get($track, 'artists.0.id', '');
        $artistData = $artistId !== '' ? $this->fetchArtist($token, $artistId) : null;
        $audioFeatures = $fetchAudioFeatures && $trackId !== ''
            ? $this->fetchAudioFeatures($token, $trackId)
            : null;

        return [
            'matched' => true,
            'spotify_used' => true,
            'audio_features_used' => $audioFeatures !== null,
            'warning' => null,
            'track' => [
                'title' => Arr::get($track, 'name'),
                'artist' => Arr::get($track, 'artists.0.name'),
                'album' => Arr::get($track, 'album.name'),
                'genre' => $this->extractGenre($artistData),
                'duration' => $this->millisecondsToSeconds(Arr::get($track, 'duration_ms')),
                'release_date' => $this->normalizeReleaseDate((string) Arr::get($track, 'album.release_date', '')),
                'tempo' => $this->normalizeFloat(Arr::get($audioFeatures, 'tempo'), 2),
                'energy' => $this->normalizeFloat(Arr::get($audioFeatures, 'energy'), 4),
                'danceability' => $this->normalizeFloat(Arr::get($audioFeatures, 'danceability'), 4),
                'valence' => $this->normalizeFloat(Arr::get($audioFeatures, 'valence'), 4),
                'popularity' => $this->normalizeInteger(Arr::get($track, 'popularity')),
                'spotify_track_id' => Arr::get($track, 'id'),
                'spotify_artist_id' => Arr::get($track, 'artists.0.id'),
                'spotify_album_id' => Arr::get($track, 'album.id'),
                'spotify_track_url' => Arr::get($track, 'external_urls.spotify'),
                'spotify_artist_url' => Arr::get($artistData, 'external_urls.spotify') ?: Arr::get($track, 'artists.0.external_urls.spotify'),
                'spotify_album_url' => Arr::get($track, 'album.external_urls.spotify'),
                'spotify_artist_image_url' => Arr::get($artistData, 'images.0.url'),
                'spotify_album_cover_url' => Arr::get($track, 'album.images.0.url'),
                'spotify_artist_followers_count' => $this->normalizeInteger(Arr::get($artistData, 'followers.total')),
            ],
        ];
    }

    protected function isConfigured(): bool
    {
        return trim((string) config('services.spotify.client_id')) !== ''
            && trim((string) config('services.spotify.client_secret')) !== '';
    }

    protected function getAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.spotify.timeout', 15))
                ->withBasicAuth(
                    (string) config('services.spotify.client_id'),
                    (string) config('services.spotify.client_secret'),
                )
                ->post('https://accounts.spotify.com/api/token', [
                    'grant_type' => 'client_credentials',
                ]);

            $response->throw();

            return (string) $response->json('access_token');
        });
    }

    protected function spotifyRequest(string $token)
    {
        return Http::acceptJson()
            ->timeout((int) config('services.spotify.timeout', 15))
            ->withToken($token)
            ->baseUrl('https://api.spotify.com/v1');
    }

    protected function searchBestTrackCandidate(string $token, string $title, string $artist = '', string $album = ''): ?array
    {
        $queries = array_values(array_unique(array_filter([
            $this->buildSearchQuery($title, $artist, $album),
            $this->buildSearchQuery($title, $artist, null),
            $this->buildSearchQuery($title, null, $album),
            $this->buildSearchQuery($title, null, null),
        ])));

        $candidates = [];

        foreach ($queries as $query) {
            $response = $this->spotifyRequest($token)->get('/search', [
                'q' => $query,
                'type' => 'track',
                'limit' => 10,
                'market' => config('services.spotify.market', 'US'),
            ]);

            if (! $response->successful()) {
                continue;
            }

            foreach ($response->json('tracks.items', []) as $item) {
                $id = (string) ($item['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $candidates[$id] = $item;
            }
        }

        if ($candidates === []) {
            return null;
        }

        uasort($candidates, function (array $left, array $right) use ($title, $artist, $album) {
            return $this->candidateScore($right, $title, $artist, $album) <=> $this->candidateScore($left, $title, $artist, $album);
        });

        return reset($candidates) ?: null;
    }

    protected function fetchTrack(string $token, string $trackId): ?array
    {
        if ($trackId === '') {
            return null;
        }

        $response = $this->spotifyRequest($token)->get("/tracks/{$trackId}", [
            'market' => config('services.spotify.market', 'US'),
        ]);

        return $response->successful() ? $response->json() : null;
    }

    protected function fetchArtist(string $token, string $artistId): ?array
    {
        if ($artistId === '') {
            return null;
        }

        $response = $this->spotifyRequest($token)->get("/artists/{$artistId}");

        return $response->successful() ? $response->json() : null;
    }

    protected function fetchAudioFeatures(string $token, string $trackId): ?array
    {
        if ($trackId === '') {
            return null;
        }

        $response = $this->spotifyRequest($token)->get("/audio-features/{$trackId}");

        return $response->successful() ? $response->json() : null;
    }

    protected function buildSearchQuery(?string $title, ?string $artist = null, ?string $album = null): ?string
    {
        $title = trim((string) $title);
        $artist = trim((string) $artist);
        $album = trim((string) $album);

        if ($title === '') {
            return null;
        }

        $parts = ['track:"' . str_replace('"', '', $title) . '"'];

        if ($artist !== '') {
            $parts[] = 'artist:"' . str_replace('"', '', $artist) . '"';
        }

        if ($album !== '') {
            $parts[] = 'album:"' . str_replace('"', '', $album) . '"';
        }

        return implode(' ', $parts);
    }

    protected function candidateScore(array $candidate, string $title, string $artist = '', string $album = ''): float
    {
        $score = 0.0;

        $score += 100 * $this->stringSimilarity($title, (string) ($candidate['name'] ?? ''));
        $score += 40 * $this->stringSimilarity($artist, (string) Arr::get($candidate, 'artists.0.name', ''));
        $score += 25 * $this->stringSimilarity($album, (string) Arr::get($candidate, 'album.name', ''));
        $score += ((float) ($candidate['popularity'] ?? 0)) / 10;

        return $score;
    }

    protected function stringSimilarity(string $expected, string $actual): float
    {
        $expected = $this->normalizeSearchString($expected);
        $actual = $this->normalizeSearchString($actual);

        if ($expected === '' || $actual === '') {
            return 0.0;
        }

        if ($expected === $actual) {
            return 1.0;
        }

        if (str_contains($actual, $expected) || str_contains($expected, $actual)) {
            return 0.92;
        }

        similar_text($expected, $actual, $percent);

        return max(0.0, min(1.0, $percent / 100));
    }

    protected function normalizeSearchString(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9а-яё ]/iu', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    protected function extractGenre(?array $artistData): ?string
    {
        $genres = Arr::get($artistData, 'genres', []);
        if (! is_array($genres) || $genres === []) {
            return null;
        }

        $genre = trim((string) ($genres[0] ?? ''));

        return $genre !== '' ? Str::title($genre) : null;
    }

    protected function normalizeReleaseDate(string $value): ?string
    {
        $value = trim($value);

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

    protected function millisecondsToSeconds(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) round(((float) $value) / 1000));
    }

    protected function normalizeFloat(mixed $value, int $scale): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, $scale);
    }

    protected function normalizeInteger(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) round((float) $value));
    }
}
