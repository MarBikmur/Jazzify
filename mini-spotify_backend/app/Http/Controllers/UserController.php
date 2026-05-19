<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
	protected function serializePublicUser(User $user, ?User $viewer = null): array
	{
		return [
			'uid' => $user->uid,
			'name' => $user->name,
			'role' => $user->role,
			'avatar_path' => $user->avatar_path,
			'avatar_url' => $user->avatar_url,
			'followers_count' => (int) $user->followers_count,
			'is_following' => (bool) ($user->is_following ?? false),
			'is_me' => $viewer?->uid === $user->uid,
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

	protected function followedUsersQuery(User $user)
	{
		return $user->followedUsers()
			->with('artist.country')
			->orderByDesc('followed_users.created_at');
	}

	public function index()
	{
		$users = User::all();
		return response()->json($users);
	}

	public function show($uid)
	{
		$user = User::find($uid);
		if (!$user) {
			return response()->json(['message' => 'User not found'], 404);
		}
		return response()->json($user);
	}

	public function publicProfile(Request $request, string $uid)
    {
        $viewer = $request->user();
        $user = User::query()
            ->with('artist.country')
            ->withFollowStateForViewer($viewer)
            ->find($uid);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $playlists = Playlist::query()
            ->withCount('songs')
            ->where('user_uid', $user->uid)
			->where('is_private', false)
            ->orderByDesc('updated_at')
            ->get();

		$artists = $user->followedArtists()
            ->with('country')
            ->orderByDesc('liked_artists.created_at')
            ->get();

		$followedUsers = $this->followedUsersQuery($user)
			->get()
			->map(fn (User $followedUser) => $this->serializePublicUser($followedUser, $viewer))
			->values();

        $artists->each(function (Artist $artist) use ($user) {
            $artist->setAttribute('is_in_library', true);
            $artist->setAttribute('is_owner_artist', $artist->user_uid === $user->uid);
        });

        $albums = $user->likedAlbums()
            ->with(['artist', 'songs.genre'])
            ->orderByDesc('liked_albums.created_at')
            ->get();

        $likedPlaylists = $user->likedPlaylists()
			->where('playlists.is_private', false)
            ->withCount('songs')
            ->orderByDesc('liked_playlists.created_at')
            ->get();

        return response()->json([
            'user' => $this->serializePublicUser($user, $viewer),
            'playlists' => $playlists,
            'followed_users' => $followedUsers,
            'artists' => $artists,
            'albums' => $albums,
            'liked_playlists' => $likedPlaylists,
        ]);
    }

	public function create(Request $request)
	{
		$request->validate([
			'name' => 'required|string|max:255',
			'email' => 'required|email|max:255|unique:users,email',
			'password' => 'required|string|min:8',
			'avatar' => 'nullable|file|mimes:jpeg,png,jpg|max:10240',
		]);

		$avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

		$user = User::create([
			'name' => $request->input('name'),
			'email' => $request->input('email'),
			'password' => Hash::make($request->input('password')),
			'avatar_path' => $avatarPath,
		]);

		return response()->json($user, 201);
	}

	public function update(Request $request, $uid)
	{
		$user = User::find($uid);
		if (!$user) {
			return response()->json(['message' => 'User not found'], 404);
		}

		$request->validate([
			'name' => 'sometimes|required|string|max:255',
			'email' => [
				'sometimes','required','email','max:255',
				Rule::unique('users', 'email')->ignore($user->uid, 'uid'),
			],
			'password' => 'sometimes|required|string|min:8',
			'avatar' => 'nullable|file|mimes:jpeg,png,jpg|max:10240',
            'remove_avatar' => 'nullable|boolean',
		]);

		$data = $request->only(['name','email']);
		if ($request->filled('password')) {
			$data['password'] = Hash::make($request->input('password'));
		}

		if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $data['avatar_path'] = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

		$user->update($data);
		return response()->json($user);
	}

	public function delete($uid)
	{
		$user = User::find($uid);
		if (!$user) {
			return response()->json(['message' => 'User not found'], 404);
		}

		if ($user->artists()->exists() || $user->playlists()->exists()) {
			return response()->json([
				'message' => 'Cannot delete a user who still owns artist profiles or playlists',
			], 422);
		}

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

		$user->delete();
		return response()->json(null, 204);
	}
}
