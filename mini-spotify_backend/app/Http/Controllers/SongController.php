<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SongController extends Controller{

	protected function parseDuration(mixed $value): ?int
	{
		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		$duration = (int) round((float) $value);

		return $duration >= 0 ? $duration : null;
	}
    
	public function index()
	{
		$songs = Song::query()
			->with(['artist', 'album', 'genre'])
			->orderBy('title')
			->get();

		return response()->json($songs);
	}

	public function create(Request $request){
		$user = $request->user();

		if (!$user) {
			return response()->json(['message' => 'Unauthenticated'], 401);
		}

		$request->validate([
			'title' => 'required|string|max:255',
			'artist_id' => 'required|exists:artists,id',
			'album_id' => 'nullable|exists:albums,id',
			'genre_id' => 'required|exists:genres,id',
			'audio_path' => 'required|file|mimes:mp3,wav,ogg,flac,aac|min:10|max:10240',
			'duration' => 'nullable|numeric|min:0',
		]);

		$artist = Artist::query()->findOrFail($request->input('artist_id'));
		$isAdmin = $user->role === 'admin';

		if (! $isAdmin && $artist->user_uid !== $user->uid) {
			return response()->json(['message' => 'Forbidden'], 403);
		}

		$albumId = $request->input('album_id');
		if ($albumId) {
			$albumBelongsToArtist = Album::query()
				->whereKey($albumId)
				->where('artist_id', $artist->id)
				->exists();

			if (! $albumBelongsToArtist) {
				return response()->json(['message' => 'Album does not belong to the selected artist'], 422);
			}
		}

		$path = $request->file('audio_path')->store('songs', 'public');

		$song = Song::create([
			'title' => $request->input('title'),
			'artist_id' => $request->input('artist_id'),
			'album_id' => $request->input('album_id'),
			'genre_id' => $request->input('genre_id'),
			'audio_path' => $path,
			'duration' => $this->parseDuration($request->input('duration')),
		]);

		return response()->json($song, 201);
	}

	public function update(Request $request, $id){
		$song = Song::find($id);
		if (!$song) {
			return response()->json(['message' => 'Song not found'], 404);
		}

		$user = $request->user();
		$isAdmin = $user?->role === 'admin';

		if (! $user || (! $isAdmin && $song->artist?->user_uid !== $user->uid)) {
			return response()->json(['message' => 'Forbidden'], 403);
		}
	
		$request->validate([
			'title' => 'required|string|max:255',
			'artist_id' => 'required|exists:artists,id',
			'album_id' => 'nullable|exists:albums,id', 
			'genre_id' => 'nullable|exists:genres,id', 
			'audio_path' => 'nullable|file|mimes:mp3,wav,ogg,flac,aac|max:10240',
			'duration' => 'nullable|numeric|min:0',
		]);

		$artist = Artist::query()->findOrFail($request->input('artist_id'));

		if (! $isAdmin && $artist->user_uid !== $user->uid) {
			return response()->json(['message' => 'Forbidden'], 403);
		}

		$albumId = $request->input('album_id');
		if ($albumId) {
			$albumBelongsToArtist = Album::query()
				->whereKey($albumId)
				->where('artist_id', $artist->id)
				->exists();

			if (! $albumBelongsToArtist) {
				return response()->json(['message' => 'Album does not belong to the selected artist'], 422);
			}
		}
	
		if ($request->hasFile('audio_path')) {
			if ($song->audio_path) {
				Storage::disk('public')->delete($song->audio_path);
			}
			$song->audio_path = $request->file('audio_path')->store('songs', 'public');
		}
	
		$payload = $request->only(['title', 'artist_id', 'album_id', 'genre_id']);

		if ($request->exists('duration')) {
			$payload['duration'] = $this->parseDuration($request->input('duration'));
		}

		$song->update($payload);
	
		return response()->json($song);
	}

	public function delete($id){
		$song = Song::find($id);
		if(!$song){
			return response()->json(['message' => 'Song not found'], 404);
		}
		$song->delete();
		return response()->json(null, 204);
	}

	public function destroyOwned(Request $request, Song $song){
		$user = $request->user();

		if(!$user || $song->artist?->user_uid !== $user->uid){
			return response()->json(['message' => 'Song not found'], 404);
		}

		if ($song->audio_path) {
			Storage::disk('public')->delete($song->audio_path);
		}

		$song->delete();
		return response()->json(null, 204);
	}

	public function show($id){
		$song = Song::with('artist', 'album', 'genre')->find($id);
		if(!$song){
			return response()->json(['message' => 'Song not found'], 404);
		}
		return response()->json($song);
	}
}
