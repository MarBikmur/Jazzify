<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $viewer = $request->user();
        $query = trim((string) $validated['q']);
        $limit = array_key_exists('limit', $validated) ? (int) $validated['limit'] : null;

        if ($query === '') {
            return response()->json([
                'tracks' => [],
                'albums' => [],
                'playlists' => [],
                'artists' => [],
                'users' => [],
                'genres' => [],
            ]);
        }

        $pattern = '%' . mb_strtolower($query) . '%';

        $tracksQuery = Song::query()
            ->with(['artist', 'album', 'genre'])
            ->whereRaw('LOWER(title) LIKE ?', [$pattern])
            ->orderBy('title');

        if ($limit !== null) {
            $tracksQuery->limit($limit);
        }

        $tracks = $tracksQuery->get();

        $albumsQuery = Album::query()
            ->with(['artist'])
            ->where(function ($searchQuery) use ($pattern) {
                $searchQuery
                    ->whereRaw('LOWER(title) LIKE ?', [$pattern])
                    ->orWhereHas('artist', fn ($artistQuery) => $artistQuery->whereRaw('LOWER(name) LIKE ?', [$pattern]));
            })
            ->orderBy('title');

        if ($limit !== null) {
            $albumsQuery->limit($limit);
        }

        $albums = $albumsQuery->get();

        $playlistsQuery = Playlist::query()
            ->withCount('songs')
            ->where(function ($searchQuery) use ($pattern) {
                $searchQuery->whereRaw('LOWER(title) LIKE ?', [$pattern]);
            })
            ->where(function ($visibilityQuery) use ($viewer) {
                $visibilityQuery
                    ->where('is_private', false)
                    ->orWhere('user_uid', $viewer->uid);
            })
            ->orderBy('title');

        if ($limit !== null) {
            $playlistsQuery->limit($limit);
        }

        $playlists = $playlistsQuery->get();

        $artistsQuery = Artist::query()
            ->whereRaw('LOWER(name) LIKE ?', [$pattern])
            ->orderBy('name');

        if ($limit !== null) {
            $artistsQuery->limit($limit);
        }

        $artists = $artistsQuery->get();

        $usersQuery = User::query()
            ->with('artist')
            ->withFollowStateForViewer($viewer)
            ->where(function ($searchQuery) use ($pattern) {
                $searchQuery
                    ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
            })
            ->orderBy('name');

        if ($limit !== null) {
            $usersQuery->limit($limit);
        }

        $users = $usersQuery
            ->get()
            ->map(fn (User $user) => $this->serializePublicUser($user, $viewer))
            ->values();

        $genresQuery = Genre::query()
            ->whereRaw('LOWER(name) LIKE ?', [$pattern])
            ->orderBy('name');

        if ($limit !== null) {
            $genresQuery->limit($limit);
        }

        $genres = $genresQuery->get();

        return response()->json([
            'tracks' => $tracks,
            'albums' => $albums,
            'playlists' => $playlists,
            'artists' => $artists,
            'users' => $users,
            'genres' => $genres,
        ]);
    }

    protected function serializePublicUser(User $user, User $viewer): array
    {
        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'role' => $user->role,
            'avatar_path' => $user->avatar_path,
            'avatar_url' => $user->avatar_url,
            'followers_count' => (int) $user->followers_count,
            'is_following' => (bool) ($user->is_following ?? false),
            'is_me' => $viewer->uid === $user->uid,
            'artist' => $user->artist ? [
                'id' => $user->artist->id,
                'name' => $user->artist->name,
                'user_uid' => $user->artist->user_uid,
                'country_id' => $user->artist->country_id,
                'image_path' => $user->artist->image_path,
                'image_url' => $user->artist->image_url,
                'followers_count' => (int) $user->artist->followers_count,
            ] : null,
        ];
    }
}
