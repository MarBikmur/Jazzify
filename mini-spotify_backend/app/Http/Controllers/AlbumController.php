<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Services\UploadedTrackMetadataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AlbumController extends Controller{
	public function __construct(
		protected UploadedTrackMetadataService $uploadedTrackMetadataService,
	) {
	}

	protected function albumQueryForRequest(Request $request)
	{
		$query = Album::query()->with(['artist', 'songs.genre']);

		if ($request->user()) {
			$query->withExists([
				'likedByUsers as is_in_library' => fn($likedQuery) => $likedQuery->where('users.uid', $request->user()->uid),
			]);
		}

		return $query;
	}

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

	protected function resolveOwnedArtist(Request $request): ?Artist
	{
		$user = $request->user();

		if (!$user) {
			return null;
		}

		return Artist::where('user_uid', $user->uid)->first();
	}

	protected function resolveArtistIdsForScope(Artist $artist)
	{
		if ($artist->user_uid) {
			return Artist::where('user_uid', $artist->user_uid)->pluck('id');
		}

		return collect([$artist->id]);
	}

	protected function normalizeDate(?string $value): ?string
	{
		if (! $value) {
			return null;
		}

		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return $value;
		}

		if (preg_match('/^\d{4}-\d{2}$/', $value)) {
			return $value . '-01';
		}

		if (preg_match('/^\d{4}$/', $value)) {
			return $value . '-01-01';
		}

		return null;
	}

	protected function normalizeFloat(mixed $value): ?float
	{
		if ($value === null || $value === '') {
			return null;
		}

		return (float) $value;
	}

	public function byArtistId(Request $request){
		$artist = $this->resolveOwnedArtist($request);
		
		if(!$artist){
			return response()->json(['message' => 'Artist not found'], 404);
		}
		
		$albums = $this->albumQueryForRequest($request)
			->where('artist_id', $artist->id)
			->latest('id')
			->get();
		return response()->json($albums);
	}

	public function byArtist(Request $request, Artist $artist){
		$artistIds = $this->resolveArtistIdsForScope($artist);

		$albums = $this->albumQueryForRequest($request)
			->whereIn('artist_id', $artistIds)
			->latest('id')
			->get();

		return response()->json($albums);
	}

	public function index(Request $request){
		$albums = $this->albumQueryForRequest($request)->get();
		return response()->json($albums);
	}

	public function latest(Request $request){
		$albums = $this->albumQueryForRequest($request)
			->latest('id')
			->get();

		return response()->json($albums);
	}

	public function create(Request $request){
		$artist = $this->resolveOwnedArtist($request);
		
		if(!$artist){
			return response()->json(['message' => 'Artist not found'], 404);
		}
		else{
			$request->validate([
				'title' => 'required|string|max:255', 
				'cover_image' => 'required|file|mimes:jpeg,jpg,png|max:10240',
				'tracks' => 'required|array|min:1',
				'tracks.*.title' => 'required|string|max:255',
				'tracks.*.genre_id' => 'required|exists:genres,id',
				'tracks.*.audio_file' => 'required|file|mimes:mp3,wav,ogg,flac,aac|max:10240',
				'tracks.*.duration' => 'nullable|numeric|min:0',
			]);

			if($request->hasFile('cover_image')) {
				$imagePath = $request->file('cover_image')->store('albums','public');
			}
			else{
				return response()->json(['message'=>'No album cover'], 404);
			}

			$album = DB::transaction(function () use ($request, $artist, $imagePath) {
				$album = Album::create([
					'title' => $request->input('title'),
					'artist_id' => $artist->id,
					'cover_image_path' => $imagePath,
				]);

				foreach ($request->input('tracks', []) as $index => $track) {
					$audioFile = $request->file("tracks.$index.audio_file");
					if (!$audioFile) {
						continue;
					}

					$audioPath = $audioFile->store('songs', 'public');
					$metadata = $this->uploadedTrackMetadataService->extract($audioFile, [
						'title' => trim((string) ($track['title'] ?? '')),
						'duration' => $this->parseDuration($track['duration'] ?? null),
						'spotify_merge_mode' => 'audio_features_only',
						'audio_features_source' => 'spotify_then_dataset',
					]);
					$resolved = $metadata['resolved'];
					$title = trim((string) ($track['title'] ?? $resolved['title'] ?? ''));

					if ($title === '') {
						$title = trim(pathinfo($audioFile->getClientOriginalName(), PATHINFO_FILENAME));
					}

					Song::create([
						'title' => $title !== '' ? $title : 'Unknown Track',
						'artist_id' => $artist->id,
						'album_id' => $album->id,
						'genre_id' => $track['genre_id'],
						'spotify_track_id' => trim((string) ($resolved['spotify_track_id'] ?? '')) ?: null,
						'audio_path' => $audioPath,
						'duration' => $this->parseDuration($resolved['duration'] ?? null),
						'tempo' => $this->normalizeFloat($resolved['tempo'] ?? null),
						'energy' => $this->normalizeFloat($resolved['energy'] ?? null),
						'danceability' => $this->normalizeFloat($resolved['danceability'] ?? null),
						'valence' => $this->normalizeFloat($resolved['valence'] ?? null),
						'release_date' => $this->normalizeDate($resolved['release_date'] ?? null),
					]);
				}

				return $album;
			});

			return response()->json($album->load('songs'), 201);
		}
	}

	public function update(Request $request, Album $album){
		$user = $request->user();

		if(!$user || $album->artist?->user_uid !== $user->uid){
			return response()->json(['message' => 'Album not found'], 404);
		}

		$request->validate([
			'title' => 'required|string|max:255',
			'cover_image' => 'nullable|file|mimes:jpeg,jpg,png|max:10240',
			'tracks' => 'nullable|array',
			'tracks.*.title' => 'required|string|max:255',
			'tracks.*.genre_id' => 'required|exists:genres,id',
			'tracks.*.audio_file' => 'required|file|mimes:mp3,wav,ogg,flac,aac|max:10240',
			'tracks.*.duration' => 'nullable|numeric|min:0',
		]);

		$payload = [
			'title' => $request->input('title'),
		];

		if($request->hasFile('cover_image')){
			if($album->cover_image_path){
				Storage::disk('public')->delete($album->cover_image_path);
			}

			$payload['cover_image_path'] = $request->file('cover_image')->store('albums','public');
		}
		
		DB::transaction(function () use ($request, $album, $payload) {
			$album->update($payload);

			foreach ($request->input('tracks', []) as $index => $track) {
				$audioFile = $request->file("tracks.$index.audio_file");
				$audioPath = $audioFile?->store('songs', 'public');

				if (!$audioPath) {
					continue;
				}

				$metadata = $this->uploadedTrackMetadataService->extract($audioFile, [
					'title' => trim((string) ($track['title'] ?? '')),
					'duration' => $this->parseDuration($track['duration'] ?? null),
					'spotify_merge_mode' => 'audio_features_only',
					'audio_features_source' => 'spotify_then_dataset',
				]);
				$resolved = $metadata['resolved'];
				$title = trim((string) ($track['title'] ?? $resolved['title'] ?? ''));

				if ($title === '') {
					$title = trim(pathinfo($audioFile->getClientOriginalName(), PATHINFO_FILENAME));
				}

				Song::create([
					'title' => $title !== '' ? $title : 'Unknown Track',
					'artist_id' => $album->artist_id,
					'album_id' => $album->id,
					'genre_id' => $track['genre_id'],
					'spotify_track_id' => trim((string) ($resolved['spotify_track_id'] ?? '')) ?: null,
					'audio_path' => $audioPath,
					'duration' => $this->parseDuration($resolved['duration'] ?? null),
					'tempo' => $this->normalizeFloat($resolved['tempo'] ?? null),
					'energy' => $this->normalizeFloat($resolved['energy'] ?? null),
					'danceability' => $this->normalizeFloat($resolved['danceability'] ?? null),
					'valence' => $this->normalizeFloat($resolved['valence'] ?? null),
					'release_date' => $this->normalizeDate($resolved['release_date'] ?? null),
				]);
			}
		});

		return response()->json($album->fresh()->load(['artist', 'songs.genre']));
	}

	public function showForArtist(Request $request, Artist $artist, Album $album){
		$artistIds = $this->resolveArtistIdsForScope($artist);

		if(!$artistIds->contains($album->artist_id)){
			return response()->json(['message' => 'Album not found'], 404);
		}

		$loadedAlbum = $this->albumQueryForRequest($request)
			->whereKey($album->id)
			->first();

		return response()->json($loadedAlbum);
	}

	public function delete(Request $request, Album $album){
		$user = $request->user();

		if(!$user || $album->artist?->user_uid !== $user->uid){
			return response()->json(['message' => 'Album not found'], 404);
		}

		DB::transaction(function () use ($album) {
			foreach ($album->songs as $song) {
				if ($song->audio_path) {
					Storage::disk('public')->delete($song->audio_path);
				}
			}

			if ($album->cover_image_path) {
				Storage::disk('public')->delete($album->cover_image_path);
			}

			$album->songs()->delete();
			$album->delete();
		});

		return response()->json(['message' => 'Album deleted']);
	}
}
