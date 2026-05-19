<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule; 
use Illuminate\Support\Facades\Storage;

class ArtistController extends Controller{
	protected function resolveOwnedArtist(Request $request): ?Artist
	{
		$user = $request->user();

		if (!$user) {
			return null;
		}
	
		return Artist::query()->where('user_uid', $user->uid)->first();
	}

	protected function artistQueryForRequest(Request $request)
	{
		$query = Artist::query()->with(['country', 'user']);

		if ($request->user()) {
			$query->withExists([
				'followers as is_following' => fn($followersQuery) => $followersQuery->where('users.uid', $request->user()->uid),
			]);
		}

		return $query;
	}

	public function index(Request $request){
		$artists = $this->artistQueryForRequest($request)->get(); 
		return response()->json($artists);
	}

	public function me(Request $request){
		$artist = $this->resolveOwnedArtist($request);

		if (!$artist){
			return response()->json(['message' => 'Artist not found'], 404);
		}

		$artist = $this->artistQueryForRequest($request)
			->whereKey($artist->id)
			->first();

		return response()->json($artist);
	}

	public function create(Request $request){

		$user = $request -> user();

		$request->validate([
			'name' => 'required|string|max:255',
			'country_id'=> 'nullable|exists:countries,id',
			'image_path' => 'nullable|file|mimes:jpeg,png,jpg|max:10000',
		]);

		$imagePath = null;
		if ($request->hasFile('image_path')) {
			$imagePath = $request->file('image_path')->store('artists','public');
		}

		$artist = $this->resolveOwnedArtist($request);

		if ($artist) {
			if ($imagePath && $artist->image_path) {
				Storage::disk('public')->delete($artist->image_path);
			}

			$artist->update([
				'name' => trim($request->input('name')),
				'country_id' => $request->input('country_id'),
				'image_path' => $imagePath ?: $artist->image_path,
			]);
		} else {
			$artist = Artist::create([
				'name' => trim($request->input('name')),
				'user_uid' => $user->uid,
				'country_id' => $request->input('country_id'),
				'image_path' => $imagePath,
			]);
		}
		
		$user->role = 'artist';
		$user->save();
		

		$artist = $this->artistQueryForRequest($request)
			->whereKey($artist->id)
			->first();

		return response()->json($artist, 201);
	}

	public function update(Request $request,$id){
		$artist = Artist::find($id);
		
		if (!$artist){
			return response()->json(['message' => 'Artist not found'], 404);
		}
		
        $user = $request->user();
        $isOwner = $user && $artist->user_uid === $user->uid;
        $isAdmin = $user?->role === 'admin';

        if (! $isOwner && ! $isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

		$request->validate([
			'name' => 'required|string|max:255',
			'country_id'=> 'nullable|exists:countries,id',
			'image_path' => 'nullable|file|mimes:jpeg,png,jpg|max:10000',
			'remove_image' => 'nullable|boolean',
		]);

		if ($request->boolean('remove_image') && $artist->image_path) {
            Storage::disk('public')->delete($artist->image_path);
            $artist->image_path = null;
        }

		if ($request->hasFile('image_path')) {
			if ($artist->image_path) {
				Storage::disk('public')->delete($artist->image_path);
			}
			$artist->image_path = $request->file('image_path')->store('artists', 'public');
		}

		$artist->name = trim($request->input('name'));
			$artist->country_id = $request->input('country_id');
			$artist->save();
		
		$artist = $this->artistQueryForRequest($request)
			->whereKey($artist->id)
			->first();

		return response()->json($artist);
	}

	public function updateMe(Request $request){
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$artist = $this->resolveOwnedArtist($request);

		if (!$artist){
			return response()->json(['message' => 'Artist not found'], 404);
		}

		$request->validate([
			'name' => 'required|string|max:255',
			'country_id'=> 'nullable|exists:countries,id',
			'image_path' => 'nullable|file|mimes:jpeg,png,jpg|max:10000',
			'remove_image' => 'nullable|boolean',
		]);

		if ($request->boolean('remove_image') && $artist->image_path) {
			Storage::disk('public')->delete($artist->image_path);
			$artist->image_path = null;
		}

		if ($request->hasFile('image_path')) {
			if ($artist->image_path) {
				Storage::disk('public')->delete($artist->image_path);
			}

			$artist->image_path = $request->file('image_path')->store('artists', 'public');
		}

		$artist->name = trim($request->input('name'));
		$artist->country_id = $request->input('country_id');
		$artist->save();

		$artist = $this->artistQueryForRequest($request)
			->whereKey($artist->id)
			->first();

		return response()->json($artist);
	}

	public function delete(Request $request, $id){
		$artist = Artist::find($id);
		if(!$artist){
			return response()->json(['message' => 'Artist not found'], 404);
		}

		$user = $request->user();
        $isOwner = $user && $artist->user_uid === $user->uid;
        $isAdmin = $user?->role === 'admin';

        if (! $isOwner && ! $isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($artist->image_path) {
            Storage::disk('public')->delete($artist->image_path);
        }

        if ($artist->user && $artist->user->role === 'artist') {
            $artist->user->role = 'user';
            $artist->user->save();
        }

		$artist->delete();
		return response()->json(null, 204);
	}

	public function show(Request $request, $id){
		$artist = $this->artistQueryForRequest($request)
			->find($id);
		if(!$artist){
			return response()->json(['message' => 'Artist not found'], 404);
		}
		return response()->json($artist);
	}
}
