<?php

namespace App\Services;

use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RecommendationService
{
    public function getRecommendationsForUser(User $user, string $genreName, int $limit = 5): Collection
    {
        $safeLimit = min(max($limit, 1), 5);
        $genre = $this->resolveGenre($genreName);

        if (! $genre) {
            return collect();
        }

        $likedTracks = $this->getLikedTracksForUser($user);
        $likedTrackIds = $likedTracks->pluck('id');
        $userProfile = $this->buildUserProfile($likedTracks);
        $collaborativeScores = $this->getCollaborativeScores($user);

        $candidateQuery = Song::query()
            ->with(['artist', 'album', 'genre'])
            ->where('genre_id', $genre->id);

        if ($likedTrackIds->isNotEmpty()) {
            $candidateQuery->whereNotIn('id', $likedTrackIds->all());
        }

        if (! $this->hasSignal($userProfile) && $collaborativeScores->isEmpty()) {
            return $candidateQuery
                ->orderByDesc('play_count')
                ->orderByDesc('release_date')
                ->limit($safeLimit)
                ->get();
        }

        $candidates = $candidateQuery->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        return $candidates
            ->map(fn (Song $track) => [
                'track' => $track,
                'score' => $this->calculateHybridTrackScore(
                    $userProfile,
                    $track,
                    (float) ($collaborativeScores[$track->id] ?? 0.0),
                ),
            ])
            ->sortByDesc('score')
            ->take($safeLimit)
            ->pluck('track')
            ->values();
    }

    public function buildUserProfile(EloquentCollection $likedTracks): array
    {
        if ($likedTracks->isEmpty()) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $dimensionCount = 4;
        $sums = array_fill(0, $dimensionCount, 0.0);

        foreach ($likedTracks as $track) {
            $vector = $this->normalizeTrackVector($track);

            for ($index = 0; $index < $dimensionCount; $index++) {
                $sums[$index] += $vector[$index];
            }
        }

        $count = max(1, $likedTracks->count());

        return array_map(
            fn (float $value): float => $value / $count,
            $sums,
        );
    }

    public function calculateTrackScore(array $userProfile, Song $candidateTrack): float
    {
        return $this->calculateHybridTrackScore($userProfile, $candidateTrack, 0.0);
    }

    public function jaccardSimilarity(Collection $userLikedIds, Collection $otherUserLikedIds): float
    {
        $uniqueUserLikedIds = $userLikedIds->unique()->values();
        $uniqueOtherUserLikedIds = $otherUserLikedIds->unique()->values();
        $intersectionCount = $uniqueUserLikedIds->intersect($uniqueOtherUserLikedIds)->count();
        $unionCount = $uniqueUserLikedIds->merge($uniqueOtherUserLikedIds)->unique()->count();

        if ($unionCount === 0) {
            return 0.0;
        }

        return $intersectionCount / $unionCount;
    }

    public function getSimilarUsers(User $user): Collection
    {
        $userLikedIds = $this->getLikedTracksForUser($user)->pluck('id')->unique()->values();

        if ($userLikedIds->isEmpty()) {
            return collect();
        }

        $favoritePlaylists = Playlist::query()
            ->where('user_uid', '!=', $user->uid)
            ->whereIn('title', Playlist::favoritesTitles())
            ->whereHas('songs', fn ($query) => $query->whereIn('songs.id', $userLikedIds->all()))
            ->with('songs:id')
            ->get();

        return $favoritePlaylists
            ->groupBy('user_uid')
            ->map(function (Collection $playlists, string $userUid) use ($userLikedIds) {
                $likedTrackIds = $playlists
                    ->flatMap(fn (Playlist $playlist) => $playlist->songs->pluck('id'))
                    ->unique()
                    ->values();

                return [
                    'user_uid' => $userUid,
                    'similarity' => $this->jaccardSimilarity($userLikedIds, $likedTrackIds),
                    'liked_track_ids' => $likedTrackIds,
                ];
            })
            ->filter(fn (array $similarUser) => $similarUser['similarity'] > 0)
            ->sortByDesc('similarity')
            ->values();
    }

    public function getCollaborativeScores(User $user): Collection
    {
        $likedTrackIds = $this->getLikedTracksForUser($user)->pluck('id')->unique()->values();

        if ($likedTrackIds->isEmpty()) {
            return collect();
        }

        $rawScores = $this->getSimilarUsers($user)
            ->reduce(function (Collection $scores, array $similarUser) use ($likedTrackIds) {
                $candidateTrackIds = $similarUser['liked_track_ids']
                    ->diff($likedTrackIds)
                    ->unique()
                    ->values();

                foreach ($candidateTrackIds as $trackId) {
                    $scores[$trackId] = (float) ($scores[$trackId] ?? 0.0) + (float) $similarUser['similarity'];
                }

                return $scores;
            }, collect());

        if ($rawScores->isEmpty()) {
            return collect();
        }

        $maxRawScore = (float) $rawScores->max();

        if ($maxRawScore <= 0.0) {
            return collect();
        }

        return $rawScores->map(
            fn (float $score): float => $score / $maxRawScore,
        );
    }

    public function calculateHybridTrackScore(array $userProfile, Song $candidateTrack, float $collaborativeScore): float
    {
        $candidateVector = $this->normalizeTrackVector($candidateTrack);
        $contentScore = $this->cosineSimilarity($userProfile, $candidateVector);
        $popularityScore = Song::popularityFromPlayCount($candidateTrack->play_count) / 100;
        $freshnessScore = $this->freshnessScore($candidateTrack->release_date);

        return (0.5 * $contentScore)
            + (0.3 * $collaborativeScore)
            + (0.1 * $popularityScore)
            + (0.1 * $freshnessScore);
    }

    public function normalizeTrackVector(Song $track): array
    {
        return [
            (float) ($track->tempo ?? 0),
            (float) ($track->energy ?? 0),
            (float) ($track->danceability ?? 0),
            (float) ($track->valence ?? 0),
        ];
    }

    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $dimensionCount = min(count($vectorA), count($vectorB));

        for ($index = 0; $index < $dimensionCount; $index++) {
            $dotProduct += $vectorA[$index] * $vectorB[$index];
            $normA += $vectorA[$index] ** 2;
            $normB += $vectorB[$index] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    protected function getLikedTracksForUser(User $user): EloquentCollection
    {
        $favoritesPlaylist = Playlist::query()
            ->where('user_uid', $user->uid)
            ->whereIn('title', Playlist::favoritesTitles())
            ->first();

        if (! $favoritesPlaylist) {
            return new EloquentCollection();
        }

        return $favoritesPlaylist->songs()->with(['artist', 'album', 'genre'])->get();
    }

    protected function resolveGenre(string $genreName): ?Genre
    {
        $normalized = mb_strtolower(trim($genreName));

        if ($normalized === '') {
            return null;
        }

        return Genre::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->first();
    }

    protected function hasSignal(array $vector): bool
    {
        foreach ($vector as $value) {
            if ((float) $value !== 0.0) {
                return true;
            }
        }

        return false;
    }

    protected function freshnessScore(mixed $releaseDate): float
    {
        if (! $releaseDate) {
            return 0.0;
        }

        $date = $releaseDate instanceof Carbon ? $releaseDate : Carbon::parse((string) $releaseDate);

        if ($date->isFuture()) {
            return 1.0;
        }

        $ageInYears = max(0.0, $date->diffInDays(now()) / 365.25);

        return 1 / (1 + $ageInYears);
    }
}
