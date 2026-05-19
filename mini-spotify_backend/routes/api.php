<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\SongController;
use App\Http\Controllers\ArtistController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\TrackStreamController;
use App\Http\Controllers\UserFollowController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TrackCommentController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\SearchController;
use App\Models\Artist;

Route::get('/media/{path}', function (string $path) {
    abort_if(str_contains($path, '..'), 403);
    abort_if(str_starts_with($path, 'songs/'), 403);
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path);
})->where('path', '.*');

Route::get('/tracks/{song}/stream', [TrackStreamController::class, 'stream'])
    ->middleware('signed')
    ->name('tracks.stream');

Route::middleware('auth:sanctum')->get('/tracks/{song}/stream-url', [TrackStreamController::class, 'streamUrl']);

Route::middleware('auth:sanctum')->get('/users/me', fn(Request $request) => $request->user());

Route::middleware('auth:sanctum')->get('/profiles/{uid}', [UserController::class, 'publicProfile']);

Route::middleware('auth:sanctum')->prefix('social/users')->group(function () {
    Route::get('/', [UserFollowController::class, 'index']);
    Route::post('/{uid}', [UserFollowController::class, 'store']);
    Route::delete('/{uid}', [UserFollowController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->prefix('messenger')->group(function () {
    Route::get('/followed-users', [MessengerController::class, 'followedUsers']);
    Route::get('/conversations', [MessengerController::class, 'conversations']);
    Route::get('/stream', [MessengerController::class, 'stream']);
    Route::post('/conversations/direct/{uid}', [MessengerController::class, 'openDirect']);
    Route::get('/conversations/{conversationId}/messages', [MessengerController::class, 'messages'])->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/messages', [MessengerController::class, 'sendMessage'])->whereNumber('conversationId');
    Route::patch('/conversations/{conversationId}/read', [MessengerController::class, 'markRead'])->whereNumber('conversationId');
    Route::patch('/messages/{messageId}', [MessengerController::class, 'updateMessage'])->whereNumber('messageId');
    Route::delete('/messages/{messageId}', [MessengerController::class, 'deleteMessage'])->whereNumber('messageId');
});

Route::prefix('users')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'create']);
        Route::delete('/{uid}', [UserController::class, 'delete']);
        Route::put('/{uid}', [UserController::class, 'update']);
        Route::get('/{uid}', [UserController::class, 'show']);
    });

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::delete('/sessions', [AuthController::class, 'logOutEverywhere']);
    Route::delete('/session', [AuthController::class, 'logOutCurrent']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

Route::middleware('auth:sanctum')->prefix('countries')->group(function(){
    Route::get('/', [CountryController::class, 'index']);
});

Route::middleware(['auth:sanctum','role:admin'])->prefix('countries')->group(function(){
    Route::post('/', [CountryController::class, 'create']);
});

Route::middleware('auth:sanctum')->prefix('artists')->group(function(){
    Route::get('/', [ArtistController::class, 'index']);
    Route::get('/me', [ArtistController::class, 'me']);
    Route::post('/me', [ArtistController::class, 'updateMe']);
    Route::post('/', [ArtistController::class, 'create']);
    Route::get('/{id}', [ArtistController::class, 'show']);
    Route::put('/{id}', [ArtistController::class, 'update']);
    Route::delete('/{id}', [ArtistController::class, 'delete']);
});

Route::middleware('auth:sanctum')->get('/artists/me/albums', [AlbumController::class, 'byArtistId']);

Route::middleware('auth:sanctum')->prefix('artists/{artist}/albums')->group(function(){
    Route::get('/', [AlbumController::class, 'byArtist']);
    Route::get('/{album}', [AlbumController::class, 'showForArtist']);
});

Route::middleware('auth:sanctum')->prefix('albums')->group(function(){
    Route::get('/', [AlbumController::class, 'index']);
    Route::get('/latest', [AlbumController::class, 'latest']);
    Route::post('/', [AlbumController::class, 'create']);
    Route::post('/{album}', [AlbumController::class, 'update']);
    Route::delete('/{album}', [AlbumController::class, 'delete']);
});

Route::middleware('auth:sanctum')->prefix('songs')->group(function(){
    Route::get('/{song}/comments', [TrackCommentController::class, 'index']);
    Route::post('/{song}/comments', [TrackCommentController::class, 'store']);
    Route::patch('/{song}/comments/{comment}', [TrackCommentController::class, 'update']);
    Route::delete('/{song}/comments/{comment}', [TrackCommentController::class, 'destroy']);
    Route::post('/', [SongController::class, 'create']);
    Route::delete('/{song}', [SongController::class, 'destroyOwned']);
});

Route::middleware('auth:sanctum')->prefix('genres')->group(function(){
    Route::get('/', [GenreController::class, 'index']);
    Route::get('/used', [GenreController::class, 'usedInSongs']);
});

Route::middleware('auth:sanctum')->get('/songs', [SongController::class, 'index']);
Route::middleware('auth:sanctum')->get('/recommendations', [RecommendationController::class, 'index']);
Route::middleware('auth:sanctum')->get('/search', [SearchController::class, 'index']);

Route::middleware('auth:sanctum')->prefix('playlists')->group(function () {
    Route::get('/', [PlaylistController::class, 'index']);
    Route::get('/liked-songs', [PlaylistController::class, 'likedSongsState']);
    Route::post('/liked-songs/songs', [PlaylistController::class, 'likeSongToFavorites']);
    Route::delete('/liked-songs/songs/{song}', [PlaylistController::class, 'unlikeSongFromFavorites'])->whereNumber('song');
    Route::get('/mine', [PlaylistController::class, 'mine']);
    Route::get('/{id}', [PlaylistController::class, 'show'])->whereNumber('id');
    Route::post('/', [PlaylistController::class, 'create']);
    Route::post('/{id}', [PlaylistController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}', [PlaylistController::class, 'delete'])->whereNumber('id');
    Route::post('/{id}/songs', [PlaylistController::class, 'attachSong'])->whereNumber('id');
    Route::delete('/{id}/songs/{song}', [PlaylistController::class, 'detachSong'])->whereNumber('id')->whereNumber('song');
});

Route::middleware('auth:sanctum')->prefix('library')->group(function () {
    Route::get('/albums', [LibraryController::class, 'albums']);
    Route::post('/albums/{album}', [LibraryController::class, 'storeAlbum']);
    Route::delete('/albums/{album}', [LibraryController::class, 'destroyAlbum']);

    Route::get('/playlists', [LibraryController::class, 'playlists']);
    Route::post('/playlists/{playlist}', [LibraryController::class, 'storePlaylist']);
    Route::delete('/playlists/{playlist}', [LibraryController::class, 'destroyPlaylist']);

    Route::get('/artists', [LibraryController::class, 'artists']);
    Route::post('/artists/{artist}', [LibraryController::class, 'storeArtist']);
    Route::delete('/artists/{artist}', [LibraryController::class, 'destroyArtist']);

    Route::get('/users', [LibraryController::class, 'users']);
    Route::post('/users/{uid}', [UserFollowController::class, 'store']);
    Route::delete('/users/{uid}', [UserFollowController::class, 'destroy']);
});

Route::middleware(['auth:sanctum','role:admin'])->prefix('genres')->group(function(){
    Route::post('/', [GenreController::class, 'create']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::post('/songs/upload/analyze', [AdminController::class, 'analyzeSongUpload']);
    Route::post('/songs/upload', [AdminController::class, 'createSongUpload']);
    Route::post('/{entity}', [AdminController::class, 'store']);
    Route::get('/{entity}', [AdminController::class, 'index']);
    Route::get('/{entity}/{id}', [AdminController::class, 'show']);
    Route::put('/{entity}/{id}', [AdminController::class, 'update']);
    Route::delete('/{entity}/{id}', [AdminController::class, 'destroy']);
});
