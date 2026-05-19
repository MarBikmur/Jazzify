<?php

namespace App\Services\AudioFeatures;

class DatasetAudioFeatureLookupService
{
    public function findBySpotifyTrackId(?string $spotifyTrackId): ?array
    {
        $spotifyTrackId = trim((string) $spotifyTrackId);

        if ($spotifyTrackId === '') {
            return null;
        }

        $path = storage_path('app/private/datasets/audio-features/tracks_features.csv');
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return null;
        }

        try {
            $header = fgetcsv($handle);
            if (! is_array($header) || $header === []) {
                return null;
            }

            $indexes = $this->resolveIndexes($header);
            if ($indexes === null) {
                return null;
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (! is_array($row) || $row === []) {
                    continue;
                }

                $rowTrackId = trim((string) ($row[$indexes['id']] ?? ''));
                if ($rowTrackId !== $spotifyTrackId) {
                    continue;
                }

                return [
                    'tempo' => $this->normalizeFloat($row[$indexes['tempo']] ?? null, 2),
                    'energy' => $this->normalizeFloat($row[$indexes['energy']] ?? null, 4),
                    'danceability' => $this->normalizeFloat($row[$indexes['danceability']] ?? null, 4),
                    'valence' => $this->normalizeFloat($row[$indexes['valence']] ?? null, 4),
                ];
            }

            return null;
        } finally {
            fclose($handle);
        }
    }

    protected function resolveIndexes(array $header): ?array
    {
        $normalized = array_map(
            fn ($value) => trim((string) $value),
            $header,
        );

        $required = [
            'id',
            'tempo',
            'energy',
            'danceability',
            'valence',
        ];

        $indexes = [];

        foreach ($required as $column) {
            $index = array_search($column, $normalized, true);
            if ($index === false) {
                return null;
            }

            $indexes[$column] = $index;
        }

        return $indexes;
    }

    protected function normalizeFloat(mixed $value, int $scale): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, $scale);
    }
}
