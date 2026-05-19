<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlaylistController extends Controller
{
	public const FAVORITES_PLAYLIST_TITLE = Playlist::FAVORITES_PLAYLIST_TITLE;

	protected function playlistQueryForRequest(Request $request)
	{
		$query = Playlist::query()->with(['user.artist'])->withCount('songs');

		if ($request->user()) {
			$query->withExists([
				'likedByUsers as is_in_library' => fn($likedQuery) => $likedQuery->where('users.uid', $request->user()->uid),
			]);
		}

		return $query;
	}

	protected function favoritesPlaylistQuery(string $userUid)
	{
		return Playlist::query()
			->where('user_uid', $userUid)
			->whereIn('title', Playlist::favoritesTitles());
	}

	protected function resolveFavoritesPlaylist(string $userUid): ?Playlist
	{
		$playlist = $this->favoritesPlaylistQuery($userUid)->first();

		if ($playlist && $playlist->title !== Playlist::FAVORITES_PLAYLIST_TITLE) {
			$playlist->forceFill([
				'title' => Playlist::FAVORITES_PLAYLIST_TITLE,
			])->save();
		}

		return $playlist;
	}

	protected function scopeVisiblePlaylists(Request $request, $query)
    {
        $user = $request->user();

        if (! $user) {
            return $query->where('is_private', false);
        }

        return $query->where(function ($visibilityQuery) use ($user) {
            $visibilityQuery
                ->where('is_private', false)
                ->orWhere('user_uid', $user->uid);
        });
    }

	public function likedSongsState(Request $request)
	{
		$playlist = $this->resolveFavoritesPlaylist($request->user()->uid);

		if (!$playlist) {
			return response()->json([
				'playlist' => null,
				'song_ids' => [],
			]);
		}

		$songIds = DB::table('playlist_song')
			->where('playlist_id', $playlist->id)
			->pluck('song_id');

		return response()->json([
			'playlist' => tap($playlist, fn($item) => $item?->setAttribute('is_in_library', false)),
			'song_ids' => $songIds->values(),
		]);
	}

	public function likeSongToFavorites(Request $request)
	{
		$request->validate([
			'song_id' => 'required|exists:songs,id',
		]);

		$songId = (int) $request->input('song_id');
		$uid = $request->user()->uid;

		$playlist = $this->resolveFavoritesPlaylist($uid);

		if (! $playlist) {
			$playlist = Playlist::create([
				'title' => Playlist::FAVORITES_PLAYLIST_TITLE,
				'user_uid' => $uid,
				'cover_image_path' => null,
				'is_private' => true,
			]);
		}

		$wasAlreadyLiked = $playlist->songs()->where('songs.id', $songId)->exists();

		if (! $wasAlreadyLiked) {
			$playlist->songs()->syncWithoutDetaching([$songId]);
		}

		$playlist->load(['songs' => function ($query) {
			$query->with(['artist', 'album', 'genre']);
		}]);

		$playlist->setAttribute('is_in_library', false);

		return response()->json([
			'playlist' => $playlist,
			'was_already_liked' => $wasAlreadyLiked,
		]);
	}

	public function unlikeSongFromFavorites(Request $request, int $song)
	{
		$songId = $song;
		$uid = $request->user()->uid;

		$playlist = $this->resolveFavoritesPlaylist($uid);

		if (!$playlist) {
			return response()->json([
				'playlist' => null,
				'was_not_liked' => true,
			]);
		}

		$wasLiked = $playlist->songs()->where('songs.id', $songId)->exists();

		if ($wasLiked) {
			$playlist->songs()->detach([$songId]);
		}

		$playlist->load(['songs' => function ($query) {
			$query->with(['artist', 'album', 'genre']);
		}]);

		$playlist?->setAttribute('is_in_library', false);

		return response()->json([
			'playlist' => $playlist,
			'was_not_liked' => ! $wasLiked,
		]);
	}

	public function mine(Request $request)
	{
		$user = $request->user();

		$playlists = $this->playlistQueryForRequest($request)
			->where('user_uid', $user->uid)
			->orderByDesc('updated_at')
			->get();

		return response()->json($playlists);
	}

	public function index(Request $request)
	{
		$playlists = $this->scopeVisiblePlaylists($request, $this->playlistQueryForRequest($request))
			->orderByDesc('updated_at')
			->get();

		return response()->json($playlists);
	}

	public function show(Request $request, $id)
	{
		$playlist = $this->playlistQueryForRequest($request)
			->with(['songs' => function ($query) {
			$query->with(['artist', 'album', 'genre']);
		}])->find($id);

		if (!$playlist) {
			return response()->json(['message' => 'Playlist not found'], 404);
		}

		$user = $request->user();
        $isOwner = $user && $playlist->user_uid === $user->uid;

        if ($playlist->is_private && ! $isOwner) {
            return response()->json(['message' => 'Playlist not found'], 404);
        }

		return response()->json($playlist);
	}

	public function create(Request $request)
	{
		$request->validate([
			'title' => 'required|string|max:255',
			'cover_image_path' => 'nullable|file|mimes:jpeg,jpg,png|max:10240',
			'is_private' => 'nullable|boolean',
		]);

		$coverPath = null;
		if ($request->hasFile('cover_image_path')) {
			$coverPath = $request->file('cover_image_path')->store('playlists', 'public');
		}

		$playlist = Playlist::create([
			'title' => $request->input('title'),
			'user_uid' => $request->user()->uid,
			'cover_image_path' => $coverPath,
			'is_private' => $request->boolean('is_private'),
		]);

		$playlist->setAttribute('is_in_library', false);

		return response()->json($playlist, 201);
	}

	public function update(Request $request, $id)
	{
		$playlist = Playlist::find($id);
		if (!$playlist) {
			return response()->json(['message' => 'Playlist not found'], 404);
		}

		if ($playlist->user_uid !== $request->user()->uid) {
			return response()->json(['message' => 'Forbidden'], 403);
		}

		$request->validate([
			'title' => 'required|string|max:255',
			'cover_image_path' => 'nullable|file|mimes:jpeg,jpg,png|max:10240',
			'is_private' => 'nullable|boolean',
		]);

		$updates = [
            'title' => $request->input('title'),
            'is_private' => $request->boolean('is_private'),
        ];

		if ($request->hasFile('cover_image_path')) {
			if ($playlist->cover_image_path) {
				Storage::disk('public')->delete($playlist->cover_image_path);
			}
			$updates['cover_image_path'] = $request->file('cover_image_path')->store('playlists', 'public');
		}

		$playlist->update($updates);
		$playlist->load(['songs' => function ($query) {
			$query->with(['artist', 'album', 'genre']);
		}]);
		$playlist->setAttribute('is_in_library', false);

		return response()->json($playlist);
	}

	public function delete(Request $request, $id)
	{
		$playlist = Playlist::find($id);
		if (!$playlist) {
			return response()->json(['message' => 'Playlist not found'], 404);
		}

		$user = $request->user();
		$isOwner = $playlist->user_uid === $user->uid;
		$isAdmin = $user->role === 'admin';

		if (! $isOwner && ! $isAdmin) {
			return response()->json(['message' => 'Forbidden'], 403);
		}

		if ($playlist->cover_image_path) {
			Storage::disk('public')->delete($playlist->cover_image_path);
		}
		$playlist->delete();

		return response()->json(null, 204);
	}

	public function attachSong(Request $request, $id)
	{
		$playlist = Playlist::find($id);
		if (!$playlist) {
			return response()->json(['message' => 'Playlist not found'], 404);
		}

		if ($playlist->user_uid !== $request->user()->uid) {
			return response()->json(['message' => 'Forbidden'], 403);
		}

		$request->validate([
			'song_id' => 'required|exists:songs,id',
		]);

		$playlist->songs()->syncWithoutDetaching([$request->input('song_id')]);
		$playlist->load(['songs' => function ($query) {
			$query->with(['artist', 'album', 'genre']);
		}]);
		$playlist->setAttribute(
			'is_in_library',
			$playlist->user_uid !== $request->user()->uid
				? $playlist->likedByUsers()->where('users.uid', $request->user()->uid)->exists()
				: false
		);

		return response()->json($playlist);
	}

	public function detachSong(Request $request, $id, int $song)
	{
		$playlist = Playlist::find($id);
		if (!$playlist) {
			return response()->json(['message' => 'Playlist not found'], 404);
		}

		if ($playlist->user_uid !== $request->user()->uid) {
			return response()->json(['message' => 'Forbidden'], 403);
		}

		$playlist->songs()->detach($song);
		$playlist->load(['songs' => function ($query) {
			$query->with(['artist', 'album', 'genre']);
		}]);
		$playlist->setAttribute(
			'is_in_library',
			$playlist->user_uid !== $request->user()->uid
				? $playlist->likedByUsers()->where('users.uid', $request->user()->uid)->exists()
				: false
		);

		return response()->json($playlist);
	}
}
