<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\LikedAlbum;
use App\Models\LikedArtist;
use App\Models\LikedPlaylist;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryController extends Controller
{
    protected function serializeLibraryUser(User $user, User $viewer): array
    {
        $user->loadMissing('artist.country');

        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'role' => $user->role,
            'avatar_path' => $user->avatar_path,
            'avatar_url' => $user->avatar_url,
            'followers_count' => (int) $user->followers_count,
            'is_following' => true,
            'is_me' => $viewer->uid === $user->uid,
            'artist' => $user->artist ? [
                'id' => $user->artist->id,
                'name' => $user->artist->name,
                'user_uid' => $user->artist->user_uid,
                'country_id' => $user->artist->country_id,
                'image_path' => $user->artist->image_path,
                'image_url' => $user->artist->image_url,
                'followers_count' => (int) $user->artist->followers_count,
                'country' => $user->artist->country,
            ] : null,
        ];
    }

    public function albums(Request $request)
    {
        $albums = $request->user()
            ->likedAlbums()
            ->with(['artist', 'songs.genre'])
            ->orderByDesc('liked_albums.created_at')
            ->get();

        $albums->each(fn(Album $album) => $album->setAttribute('is_in_library', true));

        return response()->json($albums);
    }

    public function storeAlbum(Request $request, Album $album)
    {
        if ($album->artist?->user_uid && $album->artist->user_uid === $request->user()->uid) {
            return response()->json(['message' => 'You cannot save your own album'], 422);
        }

        LikedAlbum::firstOrCreate([
            'user_uid' => $request->user()->uid,
            'album_id' => $album->id,
        ]);

        $album->load(['artist', 'songs.genre']);
        $album->setAttribute('is_in_library', true);

        return response()->json($album);
    }

    public function destroyAlbum(Request $request, Album $album)
    {
        LikedAlbum::query()
            ->where('user_uid', $request->user()->uid)
            ->where('album_id', $album->id)
            ->delete();

        $album->load(['artist', 'songs.genre']);
        $album->setAttribute('is_in_library', false);

        return response()->json($album);
    }

    public function playlists(Request $request)
    {
        $playlists = $request->user()
            ->likedPlaylists()
            ->where('playlists.is_private', false)
            ->withCount('songs')
            ->orderByDesc('liked_playlists.created_at')
            ->get();

        $playlists->each(fn(Playlist $playlist) => $playlist->setAttribute('is_in_library', true));

        return response()->json($playlists);
    }

    public function storePlaylist(Request $request, Playlist $playlist)
    {
        if ($playlist->user_uid === $request->user()->uid) {
            return response()->json(['message' => 'You cannot save your own playlist'], 422);
        }

        
        if ($playlist->is_private) {
            return response()->json(['message' => 'Private playlists cannot be saved'], 422);
        }

        LikedPlaylist::firstOrCreate([
            'user_uid' => $request->user()->uid,
            'playlist_id' => $playlist->id,
        ]);

        $playlist->loadCount('songs');
        $playlist->setAttribute('is_in_library', true);

        return response()->json($playlist);
    }

    public function destroyPlaylist(Request $request, Playlist $playlist)
    {
        LikedPlaylist::query()
            ->where('user_uid', $request->user()->uid)
            ->where('playlist_id', $playlist->id)
            ->delete();

        $playlist->loadCount('songs');
        $playlist->setAttribute('is_in_library', false);

        return response()->json($playlist);
    }

    public function artists(Request $request)
    {
        $artists = $request->user()
            ->followedArtists()
            ->with('country')
            ->orderByDesc('liked_artists.created_at')
            ->get();

        $artists->each(function (Artist $artist) {
            $artist->setAttribute('is_following', true);
            $artist->setAttribute('is_in_library', true);
        });

        return response()->json($artists);
    }

    public function users(Request $request)
    {
        $viewer = $request->user();

        $users = $viewer->followedUsers()
            ->with('artist.country')
            ->orderByDesc('followed_users.created_at')
            ->get()
            ->map(fn (User $user) => $this->serializeLibraryUser($user, $viewer))
            ->values();

        return response()->json($users);
    }

    public function storeArtist(Request $request, Artist $artist)
    {
        if ($artist->user_uid && $artist->user_uid === $request->user()->uid) {
            return response()->json(['message' => 'You cannot follow your own artist profile'], 422);
        }

        DB::transaction(function () use ($request, $artist) {
            $lockedArtist = Artist::query()->whereKey($artist->id)->lockForUpdate()->firstOrFail();

            $relation = LikedArtist::query()
                ->where('user_uid', $request->user()->uid)
                ->where('artist_id', $lockedArtist->id)
                ->first();

            if ($relation) {
                return;
            }

            LikedArtist::create([
                'user_uid' => $request->user()->uid,
                'artist_id' => $lockedArtist->id,
            ]);

            $lockedArtist->followers_count = max(0, (int) $lockedArtist->followers_count) + 1;
            $lockedArtist->save();
        });

        $artist->refresh();
        $artist->load('country');
        $artist->setAttribute('is_following', true);
        $artist->setAttribute('is_in_library', true);

        return response()->json($artist);
    }

    public function destroyArtist(Request $request, Artist $artist)
    {
        DB::transaction(function () use ($request, $artist) {
            $lockedArtist = Artist::query()->whereKey($artist->id)->lockForUpdate()->firstOrFail();

            $relation = LikedArtist::query()
                ->where('user_uid', $request->user()->uid)
                ->where('artist_id', $lockedArtist->id)
                ->first();

            if (!$relation) {
                return;
            }

            $relation->delete();

            $lockedArtist->followers_count = max(0, (int) $lockedArtist->followers_count - 1);
            $lockedArtist->save();
        });

        $artist->refresh();
        $artist->load('country');
        $artist->setAttribute('is_following', false);
        $artist->setAttribute('is_in_library', false);

        return response()->json($artist);
    }
}
