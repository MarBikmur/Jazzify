<?php

namespace App\Services;

use App\Services\AudioFeatures\DatasetAudioFeatureLookupService;
use App\Services\AudioMetadata\AudioFileMetadataReader;
use App\Services\AudioMetadata\SpotifyLookupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class UploadedTrackMetadataService
{
    public function __construct(
        protected DatasetAudioFeatureLookupService $datasetAudioFeatureLookupService,
        protected AudioFileMetadataReader $audioFileMetadataReader,
        protected SpotifyLookupService $spotifyLookupService,
    ) {
    }

    public function extract(UploadedFile $file, array $context = []): array
    {
        $filenameParts = $this->parseFilenameParts($file->getClientOriginalName());
        $fallbackTitle = $filenameParts['title'] ?? $this->titleFromFilename($file->getClientOriginalName());
        $fileMetadata = $this->audioFileMetadataReader->read($file->getRealPath());
        $spotifyMergeMode = $this->normalizeSpotifyMergeMode($context['spotify_merge_mode'] ?? null);
        $audioFeatureSource = $this->normalizeAudioFeatureSource($context['audio_features_source'] ?? null);
        $artistUploadMode = $spotifyMergeMode === 'audio_features_only';

        $resolved = array_merge([
            'title' => $fallbackTitle,
            'artist' => null,
            'album' => null,
            'genre' => null,
            'duration' => null,
            'release_date' => null,
            'tempo' => null,
            'energy' => null,
            'danceability' => null,
            'valence' => null,
            'spotify_track_id' => null,
            'spotify_artist_id' => null,
            'spotify_album_id' => null,
            'spotify_track_url' => null,
            'spotify_artist_url' => null,
            'spotify_album_url' => null,
            'spotify_artist_image_url' => null,
            'spotify_album_cover_url' => null,
            'spotify_artist_followers_count' => null,
        ], $fileMetadata);

        $lookupTitle = $artistUploadMode
            ? $this->firstNonEmpty(
                $context['title'] ?? null,
                $resolved['title'] ?? null,
                $fallbackTitle,
            )
            : $this->firstNonEmpty(
                $resolved['title'] ?? null,
                $context['title'] ?? null,
                $fallbackTitle,
            );
        $lookupArtist = $artistUploadMode
            ? $this->firstNonEmpty(
                $context['artist'] ?? null,
                $resolved['artist'] ?? null,
            )
            : $this->firstNonEmpty(
                $resolved['artist'] ?? null,
                $context['artist'] ?? null,
            );
        $lookupAlbum = $artistUploadMode
            ? $this->firstNonEmpty(
                $context['album'] ?? null,
                $resolved['album'] ?? null,
            )
            : $this->firstNonEmpty(
                $resolved['album'] ?? null,
                $context['album'] ?? null,
            );

        $spotify = $this->spotifyLookupService->lookupTrack(
            $lookupTitle,
            $lookupArtist,
            $lookupAlbum,
            $audioFeatureSource !== 'dataset_only',
        );

        if (
            $artistUploadMode
            && ! ($spotify['matched'] ?? false)
            && $this->shouldTryFilenameFallbackLookup($filenameParts, $lookupTitle, $lookupArtist)
        ) {
            $spotify = $this->spotifyLookupService->lookupTrack(
                $filenameParts['title'] ?? null,
                $filenameParts['artist'] ?? null,
                null,
                $audioFeatureSource !== 'dataset_only',
            );
        }

        if (($spotify['matched'] ?? false) && is_array($spotify['track'] ?? null)) {
            $resolved = array_merge(
                $resolved,
                array_filter(
                    $this->filterSpotifyTrackData(
                        $spotify['track'],
                        $spotifyMergeMode,
                    ),
                    fn ($value) => $value !== null && $value !== ''
                ),
            );
        }

        $audioFeaturesUsed = (bool) ($spotify['audio_features_used'] ?? false);
        $datasetFeatures = null;
        $spotifyTrackIdForFeatures = trim((string) (
            $resolved['spotify_track_id']
            ?? Arr::get($spotify, 'track.spotify_track_id')
            ?? ''
        ));

        if (! $audioFeaturesUsed || $this->hasMissingAudioFeatures($resolved)) {
            $datasetFeatures = $this->datasetAudioFeatureLookupService->findBySpotifyTrackId(
                $spotifyTrackIdForFeatures !== '' ? $spotifyTrackIdForFeatures : null,
            );
        }

        if (
            $datasetFeatures === null
            && $audioFeatureSource === 'spotify_then_dataset'
            && $artistUploadMode
            && $this->shouldTryFilenameFallbackLookup($filenameParts, $lookupTitle, $lookupArtist)
        ) {
            $spotifyFromFilename = $this->spotifyLookupService->lookupTrack(
                $filenameParts['title'] ?? null,
                $filenameParts['artist'] ?? null,
                null,
                true,
            );

            if (($spotifyFromFilename['matched'] ?? false) && is_array($spotifyFromFilename['track'] ?? null)) {
                $resolved = array_merge(
                    $resolved,
                    array_filter(
                        $this->filterSpotifyTrackData(
                            $spotifyFromFilename['track'],
                            $spotifyMergeMode,
                        ),
                        fn ($value) => $value !== null && $value !== ''
                    ),
                );

                $audioFeaturesUsed = (bool) ($spotifyFromFilename['audio_features_used'] ?? false);
                $spotifyTrackIdForFeatures = trim((string) (
                    $resolved['spotify_track_id']
                    ?? Arr::get($spotifyFromFilename, 'track.spotify_track_id')
                    ?? ''
                ));

                if (! $audioFeaturesUsed || $this->hasMissingAudioFeatures($resolved)) {
                    $datasetFeatures = $this->datasetAudioFeatureLookupService->findBySpotifyTrackId(
                        $spotifyTrackIdForFeatures !== '' ? $spotifyTrackIdForFeatures : null,
                    );
                }
            }
        }

        if ($datasetFeatures !== null) {
            $resolved = array_merge(
                $resolved,
                array_filter(
                    $datasetFeatures,
                    fn ($value) => $value !== null && $value !== ''
                ),
            );
            $audioFeaturesUsed = true;
        }

        if (array_key_exists('duration', $context) && $context['duration'] !== null && $context['duration'] !== '') {
            $resolved['duration'] = $context['duration'];
        }

        foreach (['title', 'artist', 'album', 'genre'] as $field) {
            if (array_key_exists($field, $context) && $context[$field] !== null && trim((string) $context[$field]) !== '') {
                $resolved[$field] = trim((string) $context[$field]);
            }
        }

        return [
            'resolved' => $resolved,
            'spotify_used' => (bool) ($spotify['spotify_used'] ?? false),
            'audio_features_used' => $audioFeaturesUsed,
            'warning' => $spotify['warning'] ?? null,
        ];
    }

    protected function normalizeSpotifyMergeMode(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['full', 'audio_features_only'], true)
            ? $value
            : 'full';
    }

    protected function normalizeAudioFeatureSource(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['spotify_then_dataset', 'dataset_only'], true)
            ? $value
            : 'spotify_then_dataset';
    }

    protected function filterSpotifyTrackData(array $track, string $spotifyMergeMode): array
    {
        if ($spotifyMergeMode === 'audio_features_only') {
            return array_intersect_key($track, array_flip([
                'tempo',
                'energy',
                'danceability',
                'valence',
                'spotify_track_id',
            ]));
        }

        return $track;
    }

    protected function shouldTryFilenameFallbackLookup(array $filenameParts, ?string $lookupTitle, ?string $lookupArtist): bool
    {
        $filenameTitle = trim((string) ($filenameParts['title'] ?? ''));
        $filenameArtist = trim((string) ($filenameParts['artist'] ?? ''));

        if ($filenameTitle === '') {
            return false;
        }

        $sameTitle = mb_strtolower($filenameTitle) === mb_strtolower(trim((string) $lookupTitle));
        $sameArtist = $filenameArtist === '' || mb_strtolower($filenameArtist) === mb_strtolower(trim((string) $lookupArtist));

        return ! ($sameTitle && $sameArtist);
    }

    protected function hasMissingAudioFeatures(array $resolved): bool
    {
        foreach (['tempo', 'energy', 'danceability', 'valence'] as $field) {
            if (! array_key_exists($field, $resolved) || $resolved[$field] === null || $resolved[$field] === '') {
                return true;
            }
        }

        return false;
    }

    protected function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function titleFromFilename(string $filename): string
    {
        $baseName = trim(pathinfo($filename, PATHINFO_FILENAME));

        if (preg_match('/\[([^\[\]]+)\]/u', $baseName, $matches)) {
            $title = trim((string) ($matches[1] ?? ''));
            $title = preg_replace('/[_\-.]+/u', ' ', $title) ?? $title;

            return trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        }

        return $baseName;
    }

    protected function parseFilenameParts(string $filename): array
    {
        $baseName = trim(pathinfo($filename, PATHINFO_FILENAME));
        $baseName = preg_replace('/(?<=\p{L})_(?=\p{L})/u', "'", $baseName) ?? $baseName;
        $baseName = preg_replace('/\s+/', ' ', $baseName) ?? $baseName;
        $parts = preg_split('/\s+-\s+/u', trim($baseName)) ?: [];

        if (count($parts) >= 3) {
            $source = mb_strtolower(trim((string) ($parts[0] ?? '')));
            if (preg_match('/^(spotidown\.app|spotidown app|spotdown\.app|spotdown app|spotdown|youtube|y2mate|mp3juice)$/u', $source)) {
                array_shift($parts);
            }
        }

        if (count($parts) >= 2) {
            $artist = trim((string) array_pop($parts));
            $title = trim(implode(' - ', $parts));

            return [
                'title' => $title !== '' ? $title : null,
                'artist' => $artist !== '' ? $artist : null,
            ];
        }

        return [
            'title' => $this->titleFromFilename($filename),
            'artist' => null,
        ];
    }
}
