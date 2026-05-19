<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserFollowController extends Controller
{
    protected function serializeUser(User $user): array
    {
        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'role' => $user->role,
            'avatar_path' => $user->avatar_path,
            'avatar_url' => $user->avatar_url,
            'followers_count' => (int) $user->followers_count,
            'is_following' => (bool) ($user->is_following ?? false),
            'artist' => $user->artist,
        ];
    }

    protected function userQueryForRequest(Request $request)
    {
        return User::query()
            ->with('artist')
            ->withFollowStateForViewer($request->user());
    }

    public function index(Request $request)
    {
        $users = $this->userQueryForRequest($request)
            ->where('uid', '!=', $request->user()->uid)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->serializeUser($user))
            ->values();

        return response()->json($users);
    }

    public function store(Request $request, string $uid)
    {
        $viewer = $request->user();

        if ($viewer->uid === $uid) {
            return response()->json(['message' => 'You cannot follow yourself'], 422);
        }

        DB::transaction(function () use ($viewer, $uid) {
            $followedUser = User::query()->whereKey($uid)->lockForUpdate()->firstOrFail();

            $relation = DB::table('followed_users')
                ->where('follower_uid', $viewer->uid)
                ->where('followed_uid', $followedUser->uid)
                ->first();

            if ($relation) {
                return;
            }

            DB::table('followed_users')->insert([
                'follower_uid' => $viewer->uid,
                'followed_uid' => $followedUser->uid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $followedUser->followers_count = max(0, (int) $followedUser->followers_count) + 1;
            $followedUser->save();
        });

        $user = $this->userQueryForRequest($request)->find($uid);

        return response()->json($user ? $this->serializeUser($user) : null);
    }

    public function destroy(Request $request, string $uid)
    {
        $viewer = $request->user();

        if ($viewer->uid === $uid) {
            return response()->json(['message' => 'You cannot unfollow yourself'], 422);
        }

        DB::transaction(function () use ($viewer, $uid) {
            $followedUser = User::query()->whereKey($uid)->lockForUpdate()->firstOrFail();

            $deleted = DB::table('followed_users')
                ->where('follower_uid', $viewer->uid)
                ->where('followed_uid', $followedUser->uid)
                ->delete();

            if (! $deleted) {
                return;
            }

            $followedUser->followers_count = max(0, (int) $followedUser->followers_count - 1);
            $followedUser->save();
        });

        $user = $this->userQueryForRequest($request)->find($uid);

        return response()->json($user ? $this->serializeUser($user) : null);
    }
}
