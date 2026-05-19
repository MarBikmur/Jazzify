<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Country;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\TrackComment;
use App\Models\User;
use App\Services\AdminSongUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    protected function entityConfig(): array
    {
        return [
            'users' => [
                'model' => User::class,
                'table' => 'users',
                'primary' => 'uid',
                'columns' => ['uid', 'name', 'email', 'role', 'avatar_path', 'followers_count', 'created_at', 'updated_at'],
                'search' => ['name', 'email', 'role'],
                'editable' => ['name', 'email', 'role', 'avatar_path', 'followers_count'],
                'rules' => [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email', 'max:255'],
                    'role' => ['required', Rule::in(['user', 'artist', 'admin'])],
                    'avatar_path' => ['nullable', 'string', 'max:255'],
                    'followers_count' => ['nullable', 'integer', 'min:0'],
                ],
                'public_url' => fn (User $record) => "/users/{$record->uid}",
                'title' => fn (User $record) => $record->name ?: $record->email,
            ],
            'artists' => [
                'model' => Artist::class,
                'table' => 'artists',
                'primary' => 'id',
                'columns' => ['id', 'name', 'user_uid', 'country_id', 'spotify_artist_id', 'image_path', 'followers_count', 'created_at', 'updated_at'],
                'search' => ['name', 'user_uid', 'spotify_artist_id', 'image_path'],
                'editable' => ['name', 'user_uid', 'country_id', 'spotify_artist_id', 'image_path', 'followers_count'],
                'rules' => [
                    'name' => ['required', 'string', 'max:255'],
                    'user_uid' => ['nullable', 'uuid', 'exists:users,uid'],
                    'country_id' => ['nullable', 'integer', 'exists:countries,id'],
                    'spotify_artist_id' => ['nullable', 'string', 'max:64'],
                    'image_path' => ['nullable', 'string', 'max:255'],
                    'followers_count' => ['nullable', 'integer', 'min:0'],
                ],
                'public_url' => fn (Artist $record) => "/albums/{$record->id}",
                'title' => fn (Artist $record) => $record->name,
            ],
            'genres' => [
                'model' => Genre::class,
                'table' => 'genres',
                'primary' => 'id',
                'columns' => ['id', 'name', 'created_at', 'updated_at'],
                'search' => ['name'],
                'editable' => ['name'],
                'rules' => [
                    'name' => ['required', 'string', 'max:255'],
                ],
                'public_url' => fn (Genre $record) => '/genres/' . rawurlencode($record->name),
                'title' => fn (Genre $record) => $record->name,
            ],
            'countries' => [
                'model' => Country::class,
                'table' => 'countries',
                'primary' => 'id',
                'columns' => ['id', 'name', 'created_at', 'updated_at'],
                'search' => ['name'],
                'editable' => ['name'],
                'rules' => [
                    'name' => ['required', 'string', 'max:30'],
                ],
                'public_url' => fn () => null,
                'title' => fn (Country $record) => $record->name,
            ],
            'albums' => [
                'model' => Album::class,
                'table' => 'albums',
                'primary' => 'id',
                'columns' => ['id', 'title', 'artist_id', 'spotify_album_id', 'cover_image_path', 'created_at', 'updated_at'],
                'search' => ['title', 'spotify_album_id', 'cover_image_path'],
                'editable' => ['title', 'artist_id', 'spotify_album_id', 'cover_image_path'],
                'rules' => [
                    'title' => ['required', 'string', 'max:255'],
                    'artist_id' => ['required', 'integer', 'exists:artists,id'],
                    'spotify_album_id' => ['nullable', 'string', 'max:64'],
                    'cover_image_path' => ['nullable', 'string', 'max:255'],
                ],
                'public_url' => fn (Album $record) => $record->artist_id ? "/albums/{$record->artist_id}/{$record->id}" : null,
                'title' => fn (Album $record) => $record->title,
            ],
            'playlists' => [
                'model' => Playlist::class,
                'table' => 'playlists',
                'primary' => 'id',
                'columns' => ['id', 'title', 'user_uid', 'cover_image_path', 'is_private', 'created_at', 'updated_at'],
                'search' => ['title', 'user_uid', 'cover_image_path'],
                'editable' => ['title', 'user_uid', 'cover_image_path', 'is_private'],
                'rules' => [
                    'title' => ['required', 'string', 'max:255'],
                    'user_uid' => ['nullable', 'uuid', 'exists:users,uid'],
                    'cover_image_path' => ['nullable', 'string', 'max:255'],
                    'is_private' => ['required', 'boolean'],
                ],
                'public_url' => fn (Playlist $record) => "/playlists/{$record->id}",
                'title' => fn (Playlist $record) => $record->title,
            ],
            'songs' => [
                'model' => Song::class,
                'table' => 'songs',
                'primary' => 'id',
                'columns' => ['id', 'title', 'artist_id', 'album_id', 'genre_id', 'spotify_track_id', 'audio_path', 'duration', 'play_count', 'tempo', 'energy', 'danceability', 'valence', 'popularity', 'release_date', 'created_at', 'updated_at'],
                'search' => ['title', 'spotify_track_id', 'audio_path'],
                'editable' => ['title', 'artist_id', 'album_id', 'genre_id', 'spotify_track_id', 'audio_path', 'duration', 'play_count', 'tempo', 'energy', 'danceability', 'valence', 'release_date'],
                'rules' => [
                    'title' => ['required', 'string', 'max:255'],
                    'artist_id' => ['required', 'integer', 'exists:artists,id'],
                    'album_id' => ['nullable', 'integer', 'exists:albums,id'],
                    'genre_id' => ['required', 'integer', 'exists:genres,id'],
                    'spotify_track_id' => ['nullable', 'string', 'max:64'],
                    'audio_path' => ['nullable', 'string', 'max:255'],
                    'duration' => ['nullable', 'integer', 'min:0'],
                    'play_count' => ['nullable', 'integer', 'min:0'],
                    'tempo' => ['nullable', 'numeric', 'min:0'],
                    'energy' => ['nullable', 'numeric', 'min:0', 'max:1'],
                    'danceability' => ['nullable', 'numeric', 'min:0', 'max:1'],
                    'valence' => ['nullable', 'numeric', 'min:0', 'max:1'],
                    'popularity' => ['nullable', 'integer', 'min:0', 'max:100'],
                    'release_date' => ['nullable', 'date'],
                ],
                'public_url' => function (Song $record) {
                    if ($record->artist_id && $record->album_id) {
                        return "/albums/{$record->artist_id}/{$record->album_id}";
                    }

                    if ($record->artist_id) {
                        return "/albums/{$record->artist_id}";
                    }

                    return null;
                },
                'title' => fn (Song $record) => $record->title,
            ],
            'comments' => [
                'model' => TrackComment::class,
                'table' => 'track_comments',
                'primary' => 'id',
                'columns' => ['id', 'song_id', 'user_uid', 'body', 'timestamp_seconds', 'created_at', 'updated_at'],
                'search' => ['body', 'user_uid'],
                'editable' => ['song_id', 'user_uid', 'body', 'timestamp_seconds'],
                'rules' => [
                    'song_id' => ['required', 'integer', 'exists:songs,id'],
                    'user_uid' => ['required', 'uuid', 'exists:users,uid'],
                    'body' => ['required', 'string', 'max:250'],
                    'timestamp_seconds' => ['required', 'integer', 'min:0'],
                ],
                'public_url' => function (TrackComment $record) {
                    $record->loadMissing('song');

                    if (! $record->song?->artist_id || ! $record->song?->album_id) {
                        return null;
                    }

                    return "/albums/{$record->song->artist_id}/{$record->song->album_id}";
                },
                'title' => fn (TrackComment $record) => str($record->body)->limit(48)->toString(),
            ],
        ];
    }

    protected function resolveEntity(string $entity): array
    {
        $config = $this->entityConfig()[$entity] ?? null;

        abort_unless($config, 404, 'Entity not supported');

        return $config;
    }

    protected function normalizeValue(string $field, mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        if (in_array($field, ['followers_count', 'artist_id', 'country_id', 'album_id', 'genre_id', 'duration', 'play_count', 'song_id', 'timestamp_seconds', 'popularity'], true)) {
            return $value === null ? null : (int) $value;
        }

        if (in_array($field, ['tempo', 'energy', 'danceability', 'valence'], true)) {
            return $value === null ? null : (float) $value;
        }

        if ($field === 'is_private') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return is_string($value) ? trim($value) : $value;
    }

    protected function visibleColumns(array $config): array
    {
        $availableColumns = Schema::getColumnListing($config['table']);

        return array_values(array_intersect($config['columns'], $availableColumns));
    }

    protected function fieldMeta(string $entity, array $columns, array $editable): array
    {
        return array_map(function (string $column) use ($entity, $editable) {
            $type = 'text';
            $options = null;
            $nullable = false;

            if (str_ends_with($column, '_at') || $column === 'release_date') {
                $type = 'datetime';
            } elseif (in_array($column, ['followers_count', 'artist_id', 'country_id', 'album_id', 'genre_id', 'duration', 'play_count', 'song_id', 'timestamp_seconds', 'id', 'popularity'], true)) {
                $type = 'number';
            } elseif (in_array($column, ['tempo', 'energy', 'danceability', 'valence'], true)) {
                $type = 'decimal';
            } elseif ($column === 'is_private') {
                $type = 'boolean';
            }

            if ($entity === 'songs' && $column === 'genre_id') {
                $type = 'select';
                $options = Genre::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Genre $genre) => [
                        'value' => $genre->id,
                        'label' => $genre->name,
                    ])
                    ->values()
                    ->all();
            }

            return [
                'key' => $column,
                'label' => str($column)->replace('_', ' ')->title()->toString(),
                'type' => $type,
                'editable' => in_array($column, $editable, true),
                'nullable' => $nullable,
                'options' => $options,
            ];
        }, $columns);
    }

    protected function publicUrl(array $config, Model $record): ?string
    {
        return ($config['public_url'])($record);
    }

    protected function recordData(Model $record, array $columns, array $config): array
    {
        $data = [];

        foreach ($columns as $column) {
            $data[$column] = $record->getAttribute($column);
        }

        $data['_primary'] = $record->getAttribute($config['primary']);
        $data['_public_url'] = $this->publicUrl($config, $record);
        $data['_title'] = ($config['title'])($record);

        return $data;
    }

    protected function validationRules(array $config, Model $record, string $entity): array
    {
        $rules = [];

        foreach ($config['editable'] as $field) {
            $fieldRules = $config['rules'][$field] ?? [];
            $rules[$field] = array_merge(['sometimes'], $fieldRules);
        }

        if ($entity === 'users') {
            $rules['email'] = [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($record->getAttribute('uid'), 'uid'),
            ];
        }

        return $rules;
    }

    protected function creationRules(string $entity, array $config): array
    {
        if ($entity === 'users') {
            return [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8'],
                'role' => ['required', Rule::in(['user', 'artist', 'admin'])],
            ];
        }

        $rules = [];

        foreach ($config['editable'] as $field) {
            $fieldRules = $config['rules'][$field] ?? [];
            $rules[$field] = $fieldRules;
        }

        return $rules;
    }

    protected function buildRecordPayload(string $entity, Request $request, array $config): array
    {
        $payload = [];

        foreach ($config['editable'] as $field) {
            if ($request->exists($field)) {
                $payload[$field] = $this->normalizeValue($field, $request->input($field));
            }
        }

        if ($entity === 'users' && $request->exists('password')) {
            $payload['password'] = (string) $request->input('password');
        }

        return $payload;
    }

    protected function validateCrossEntityIntegrity(string $entity, array $payload): void
    {
        if ($entity === 'songs') {
            $artistId = $payload['artist_id'] ?? null;
            $albumId = $payload['album_id'] ?? null;

            if ($artistId !== null && $albumId !== null) {
                $albumBelongsToArtist = Album::query()
                    ->whereKey($albumId)
                    ->where('artist_id', $artistId)
                    ->exists();

                abort_unless($albumBelongsToArtist, 422, 'Album does not belong to the selected artist');
            }
        }
    }

    protected function applyDerivedSongFields(array &$payload, ?Song $record = null): void
    {
        $playCount = array_key_exists('play_count', $payload)
            ? max(0, (int) ($payload['play_count'] ?? 0))
            : max(0, (int) ($record?->play_count ?? 0));

        $payload['play_count'] = $playCount;
        $payload['popularity'] = Song::popularityFromPlayCount($playCount);
    }

    protected function cleanupRecordFiles(string $entity, Model $record): void
    {
        $paths = match ($entity) {
            'users' => [$record->getAttribute('avatar_path')],
            'artists' => [$record->getAttribute('image_path')],
            'albums' => array_merge(
                [$record->getAttribute('cover_image_path')],
                $record->relationLoaded('songs')
                    ? $record->songs->pluck('audio_path')->all()
                    : $record->songs()->pluck('audio_path')->all()
            ),
            'playlists' => [$record->getAttribute('cover_image_path')],
            'songs' => [$record->getAttribute('audio_path')],
            default => [],
        };

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    public function index(Request $request, string $entity)
    {
        $config = $this->resolveEntity($entity);
        $columns = $this->visibleColumns($config);
        $limit = min(max((int) $request->integer('limit', 100), 1), 100);
        $offset = max((int) $request->integer('offset', 0), 0);
        $search = mb_strtolower(trim((string) $request->query('q', '')));

        $modelClass = $config['model'];
        $query = $modelClass::query()->select($columns);

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($config, $search) {
                foreach ($config['search'] as $index => $column) {
                    if ($index === 0) {
                        $searchQuery->whereRaw('LOWER(' . $column . ') LIKE ?', ['%' . $search . '%']);
                    } else {
                        $searchQuery->orWhereRaw('LOWER(' . $column . ') LIKE ?', ['%' . $search . '%']);
                    }
                }
            });
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy($config['primary'])
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn (Model $record) => $this->recordData($record, $columns, $config))
            ->values();

        return response()->json([
            'entity' => $entity,
            'columns' => $columns,
            'rows' => $rows,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total,
                'has_more' => $offset + $limit < $total,
                'search' => $search,
            ],
        ]);
    }

    public function show(string $entity, string $id)
    {
        $config = $this->resolveEntity($entity);
        $columns = $this->visibleColumns($config);

        $modelClass = $config['model'];
        $record = $modelClass::query()->findOrFail($id);

        return response()->json([
            'entity' => $entity,
            'record' => $this->recordData($record, $columns, $config),
            'fields' => $this->fieldMeta($entity, $columns, $config['editable']),
            'primary' => $config['primary'],
            'public_url' => $this->publicUrl($config, $record),
            'title' => ($config['title'])($record),
        ]);
    }

    public function store(Request $request, string $entity)
    {
        $config = $this->resolveEntity($entity);
        $payload = $this->buildRecordPayload($entity, $request, $config);
        $validated = Validator::make($payload, $this->creationRules($entity, $config))->validate();

        if ($entity === 'users') {
            $validated['password'] = Hash::make($validated['password']);
        }

        $this->validateCrossEntityIntegrity($entity, $validated);

        if ($entity === 'songs') {
            $this->applyDerivedSongFields($validated);
        }

        $modelClass = $config['model'];
        $record = $modelClass::query()->create($validated);

        return response()->json([
            'success' => true,
            'primary' => $record->getAttribute($config['primary']),
            'public_url' => $this->publicUrl($config, $record),
            'title' => ($config['title'])($record),
        ], 201);
    }

    public function update(Request $request, string $entity, string $id)
    {
        $config = $this->resolveEntity($entity);

        $modelClass = $config['model'];
        $record = $modelClass::query()->findOrFail($id);

        $payload = $this->buildRecordPayload($entity, $request, $config);

        if ($payload === []) {
            return response()->json(['message' => 'No editable fields supplied'], 422);
        }

        if (
            $entity === 'users'
            && $record->getAttribute('uid') === $request->user()?->uid
            && array_key_exists('role', $payload)
            && $payload['role'] !== 'admin'
        ) {
            return response()->json(['message' => 'You cannot remove your own admin role'], 422);
        }

        $validator = Validator::make($payload, $this->validationRules($config, $record, $entity));
        $validated = $validator->validate();

        $this->validateCrossEntityIntegrity($entity, $validated + $record->only(['artist_id', 'album_id']));

        if ($entity === 'songs') {
            $this->applyDerivedSongFields($validated, $record);
        }

        $record->fill($validated);
        $record->save();

        return $this->show($entity, (string) $record->getAttribute($config['primary']));
    }

    public function destroy(Request $request, string $entity, string $id)
    {
        $config = $this->resolveEntity($entity);

        $modelClass = $config['model'];
        $record = $modelClass::query()->findOrFail($id);

        if ($entity === 'users' && $record->getAttribute('uid') === $request->user()?->uid) {
            return response()->json(['message' => 'You cannot delete your own account'], 422);
        }

        if (
            $entity === 'users'
            && ($record->artists()->exists() || $record->playlists()->exists())
        ) {
            return response()->json([
                'message' => 'Cannot delete a user who still owns artist profiles or playlists',
            ], 422);
        }

        if ($entity === 'albums') {
            $record->loadMissing('songs');
        }

        $this->cleanupRecordFiles($entity, $record);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Record deleted',
        ]);
    }

    public function analyzeSongUpload(Request $request, AdminSongUploadService $uploadService)
    {
        $validated = $request->validate([
            'audio_file' => ['required', 'file', 'mimes:mp3,wav,ogg,flac,aac,m4a', 'max:20480'],
            'duration' => ['nullable', 'numeric', 'min:0'],
        ]);

        $duration = array_key_exists('duration', $validated) ? (int) round((float) $validated['duration']) : null;
        $result = $uploadService->analyze($validated['audio_file'], $duration);
        return response()->json($result);
    }

    public function createSongUpload(Request $request, AdminSongUploadService $uploadService)
    {
        $validated = $request->validate([
            'audio_file' => ['required', 'file', 'mimes:mp3,wav,ogg,flac,aac,m4a', 'max:20480'],
            'title' => ['nullable', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'numeric', 'min:0'],
            'play_count' => ['nullable', 'integer', 'min:0'],
            'tempo' => ['nullable', 'numeric', 'min:0'],
            'energy' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'danceability' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'valence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'release_date' => ['nullable', 'date'],
            'spotify_track_id' => ['nullable', 'string', 'max:64'],
            'spotify_artist_id' => ['nullable', 'string', 'max:64'],
            'spotify_album_id' => ['nullable', 'string', 'max:64'],
            'spotify_track_url' => ['nullable', 'url', 'max:255'],
            'spotify_artist_url' => ['nullable', 'url', 'max:255'],
            'spotify_album_url' => ['nullable', 'url', 'max:255'],
            'spotify_artist_image_url' => ['nullable', 'url', 'max:2048'],
            'spotify_album_cover_url' => ['nullable', 'url', 'max:2048'],
            'spotify_artist_followers_count' => ['nullable', 'integer', 'min:0'],
            'force_reupload' => ['nullable', 'boolean'],
        ]);

        $result = $uploadService->createFromDraft(
            $validated['audio_file'],
            $validated,
            (bool) ($validated['force_reupload'] ?? false),
        );
        return response()->json($result, 201);
    }
}
