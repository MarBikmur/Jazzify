<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class MessengerController extends Controller
{
    private const MAX_MESSAGE_LENGTH = 2000;

    private function serializePublicUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('artist');

        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'role' => $user->role,
            'avatar_path' => $user->avatar_path,
            'avatar_url' => $user->avatar_url,
            'followers_count' => (int) $user->followers_count,
            'artist' => $user->artist,
        ];
    }

    public function followedUsers(Request $request)
    {
        $viewer = $request->user();

        $users = $viewer->followedUsers()
            ->with('artist')
            ->orderByDesc('followed_users.created_at')
            ->get();

        $directKeys = $users
            ->mapWithKeys(fn(User $user) => [Conversation::makeDirectKey($viewer->uid, $user->uid) => $user->uid])
            ->all();

        $conversationIdsByKey = empty($directKeys)
            ? collect()
            : Conversation::query()
                ->where('type', 'direct')
                ->whereIn('direct_key', array_keys($directKeys))
                ->pluck('id', 'direct_key');

        $payload = $users->map(function (User $user) use ($viewer, $conversationIdsByKey) {
            $data = $this->serializePublicUser($user);
            $data['conversation_id'] = $conversationIdsByKey->get(Conversation::makeDirectKey($viewer->uid, $user->uid));

            return $data;
        });

        return response()->json($payload->values());
    }

    public function conversations(Request $request)
    {
        $viewer = $request->user();

        $conversations = Conversation::query()
            ->whereHas('participants', fn(Builder $query) => $query->where('user_uid', $viewer->uid))
            ->with([
                'participants.user.artist',
                'latestMessage.sender.artist',
            ])
            ->withCount([
                'messages as unread_count' => function (Builder $query) use ($viewer) {
                    $query
                        ->where('sender_uid', '!=', $viewer->uid)
                        ->whereRaw(
                            "messages.created_at > COALESCE((SELECT cp.last_read_at FROM conversation_participants cp WHERE cp.conversation_id = conversations.id AND cp.user_uid = ? LIMIT 1), TIMESTAMP '1970-01-01 00:00:00')",
                            [$viewer->uid]
                        );
                },
            ])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get();

        $payload = $conversations->map(fn(Conversation $conversation) => $this->serializeConversation($conversation, $viewer->uid));

        return response()->json($payload->values());
    }

    public function openDirect(Request $request, string $uid)
    {
        $viewer = $request->user();

        if ($viewer->uid === $uid) {
            return response()->json(['message' => 'You cannot start a conversation with yourself'], 422);
        }

        $targetUser = User::query()->with('artist')->whereKey($uid)->first();
        if (! $targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $conversation = DB::transaction(function () use ($viewer, $targetUser) {
            $directKey = Conversation::makeDirectKey($viewer->uid, $targetUser->uid);

            $conversation = Conversation::query()->firstOrCreate(
                ['direct_key' => $directKey],
                ['type' => 'direct']
            );

            ConversationParticipant::query()->firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_uid' => $viewer->uid,
            ]);

            ConversationParticipant::query()->firstOrCreate([
                'conversation_id' => $conversation->id,
                'user_uid' => $targetUser->uid,
            ]);

            return $conversation;
        });

        $conversation->load([
            'participants.user.artist',
            'latestMessage.sender.artist',
        ]);

        return response()->json($this->serializeConversation($conversation, $viewer->uid));
    }

    public function messages(Request $request, int $conversationId)
    {
        $viewer = $request->user();
        $limit = max(1, min(100, (int) $request->integer('limit', 50)));

        $conversation = $this->conversationForUserOrFail($conversationId, $viewer->uid);

        $messages = $conversation->messages()
            ->with('sender.artist')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn(Message $message) => $this->serializeMessage($message));

        return response()->json([
            'conversation' => $this->serializeConversation($conversation->load(['participants.user.artist', 'latestMessage.sender.artist']), $viewer->uid),
            'messages' => $messages,
        ]);
    }

    public function sendMessage(Request $request, int $conversationId)
    {
        $viewer = $request->user();

        $request->validate([
            'body' => 'nullable|string|max:' . self::MAX_MESSAGE_LENGTH,
            'share.type' => 'nullable|string|in:track,album,playlist,artist',
            'share.id' => 'nullable',
        ]);

        $body = trim((string) $request->input('body'));
        $sharePayload = $this->resolveSharePayload($request->input('share'));

        if ($body === '' && $sharePayload === null) {
            return response()->json(['message' => 'Message cannot be empty'], 422);
        }

        $conversation = $this->conversationForUserOrFail($conversationId, $viewer->uid);

        $message = DB::transaction(function () use ($conversation, $viewer, $body, $sharePayload) {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_uid' => $viewer->uid,
                'body' => $body,
                'message_type' => $sharePayload ? 'share' : 'text',
                'shared_item' => $sharePayload,
            ]);

            $timestamp = now();

            $conversation->forceFill([
                'last_message_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->save();

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_uid', $viewer->uid)
                ->update([
                    'last_read_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

            return $message;
        });

        $message->load('sender.artist');

        return response()->json($this->serializeMessage($message), 201);
    }

    public function markRead(Request $request, int $conversationId)
    {
        $viewer = $request->user();
        $conversation = $this->conversationForUserOrFail($conversationId, $viewer->uid);

        $timestamp = now();

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_uid', $viewer->uid)
            ->update([
                'last_read_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'last_read_at' => $timestamp,
        ]);
    }

    public function updateMessage(Request $request, int $messageId)
    {
        $viewer = $request->user();

        $validated = $request->validate([
            'body' => 'required|string|max:' . self::MAX_MESSAGE_LENGTH,
        ]);

        $body = trim((string) $validated['body']);

        if ($body === '') {
            return response()->json(['message' => 'Message cannot be empty'], 422);
        }

        $message = Message::query()
            ->whereKey($messageId)
            ->where('sender_uid', $viewer->uid)
            ->first();

        abort_unless($message, 404, 'Message not found');

        if ($message->message_type !== 'text') {
            return response()->json(['message' => 'Only text messages can be edited'], 422);
        }

        $message->forceFill([
            'body' => $body,
            'edited_at' => now(),
        ])->save();

        $message->load('sender.artist');

        return response()->json($this->serializeMessage($message));
    }

    public function deleteMessage(Request $request, int $messageId)
    {
        $viewer = $request->user();

        $message = Message::query()
            ->whereKey($messageId)
            ->where('sender_uid', $viewer->uid)
            ->first();

        abort_unless($message, 404, 'Message not found');

        $conversationId = $message->conversation_id;
        $deletedMessageCreatedAt = $message->created_at;

        DB::transaction(function () use ($message, $conversationId, $deletedMessageCreatedAt) {
            $message->delete();

            $latestMessage = Message::query()
                ->where('conversation_id', $conversationId)
                ->latest('created_at')
                ->latest('id')
                ->first();

            if ($latestMessage && $latestMessage->created_at?->equalTo($deletedMessageCreatedAt)) {
                return;
            }

            $conversation = Conversation::query()->find($conversationId);

            if (! $conversation) {
                return;
            }

            $conversation->forceFill([
                'last_message_at' => $latestMessage?->created_at,
                'updated_at' => now(),
            ])->save();
        });

        return response()->json([
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
        ]);
    }

    public function stream(Request $request)
    {
        $viewer = $request->user();
        $cursor = $this->parseStreamCursor($request->query('cursor'));

        return response()->stream(function () use ($viewer, $cursor) {
            ignore_user_abort(true);

            $startedAt = microtime(true);
            $currentCursor = $cursor;

            while (! connection_aborted() && (microtime(true) - $startedAt) < 25) {
                $event = $this->pullStreamEvent($viewer->uid, $currentCursor);

                if ($event !== null) {
                    $currentCursor = CarbonImmutable::parse($event['cursor']);
                    $this->writeSseEvent('message.created', $event);
                    @ob_flush();
                    flush();

                    return;
                }

                $this->writeSseEvent('ping', [
                    'cursor' => optional($currentCursor)->toISOString(),
                ]);
                @ob_flush();
                flush();

                usleep(1000000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function conversationForUserOrFail(int $conversationId, string $userUid): Conversation
    {
        $conversation = Conversation::query()
            ->whereKey($conversationId)
            ->whereHas('participants', fn(Builder $query) => $query->where('user_uid', $userUid))
            ->first();

        abort_unless($conversation, 404, 'Conversation not found');

        return $conversation;
    }

    private function parseStreamCursor(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function pullStreamEvent(string $viewerUid, ?CarbonImmutable $cursor): ?array
    {
        $messages = Message::query()
            ->select(['messages.id', 'messages.conversation_id', 'messages.created_at'])
            ->join('conversation_participants as cp', 'cp.conversation_id', '=', 'messages.conversation_id')
            ->where('cp.user_uid', $viewerUid)
            ->when($cursor, fn(Builder $query) => $query->where('messages.created_at', '>', $cursor))
            ->orderBy('messages.created_at')
            ->limit(50)
            ->get();

        if ($messages->isEmpty()) {
            return null;
        }

        $latestMessage = $messages->last();

        return [
            'cursor' => optional($latestMessage?->created_at)->toISOString(),
            'conversation_ids' => $messages->pluck('conversation_id')->unique()->values()->all(),
            'message_ids' => $messages->pluck('id')->values()->all(),
        ];
    }

    private function writeSseEvent(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }

    private function resolveSharePayload(mixed $share): ?array
    {
        if (! is_array($share)) {
            return null;
        }

        $type = isset($share['type']) ? (string) $share['type'] : '';
        $id = isset($share['id']) ? (int) $share['id'] : 0;

        if ($type === '' || $id <= 0) {
            return null;
        }

        return match ($type) {
            'track' => $this->resolveTrackSharePayload($id),
            'album' => $this->resolveAlbumSharePayload($id),
            'artist' => $this->resolveArtistSharePayload($id),
            'playlist' => $this->resolvePlaylistSharePayload($id),
            default => null,
        };
    }

    private function resolveTrackSharePayload(int $trackId): array
    {
        $track = Song::query()
            ->with(['artist', 'album'])
            ->find($trackId);

        abort_unless($track && $track->artist, 404, 'Track not found');

        return [
            'type' => 'track',
            'id' => $track->id,
            'title' => $track->title,
            'subtitle' => $track->artist->name,
            'duration' => $track->duration,
            'artist_id' => $track->artist->id,
            'artist_name' => $track->artist->name,
            'album_id' => $track->album?->id,
            'album_title' => $track->album?->title,
            'image_url' => $track->album?->cover_image_url,
            'route' => $track->album
                ? "/albums/{$track->artist->id}/{$track->album->id}?autoplay=1&track={$track->id}"
                : null,
        ];
    }

    private function resolveAlbumSharePayload(int $albumId): array
    {
        $album = Album::query()
            ->with(['artist'])
            ->withCount('songs')
            ->find($albumId);

        abort_unless($album && $album->artist, 404, 'Album not found');

        return [
            'type' => 'album',
            'id' => $album->id,
            'title' => $album->title,
            'subtitle' => $album->artist->name . ' • ' . $album->songs_count . ' tracks',
            'artist_id' => $album->artist->id,
            'artist_name' => $album->artist->name,
            'image_url' => $album->cover_image_url,
            'route' => "/albums/{$album->artist->id}/{$album->id}",
        ];
    }

    private function resolveArtistSharePayload(int $artistId): array
    {
        $artist = Artist::query()
            ->withCount('albums')
            ->find($artistId);

        abort_unless($artist, 404, 'Artist not found');

        return [
            'type' => 'artist',
            'id' => $artist->id,
            'title' => $artist->name,
            'subtitle' => $artist->albums_count . ' albums',
            'image_url' => $artist->image_url,
            'route' => "/albums/{$artist->id}",
        ];
    }

    private function resolvePlaylistSharePayload(int $playlistId): array
    {
        $playlist = Playlist::query()
            ->with(['user'])
            ->withCount('songs')
            ->find($playlistId);

        abort_unless($playlist && $playlist->user, 404, 'Playlist not found');
        abort_if($playlist->is_private, 422, 'Private playlists cannot be shared');

        return [
            'type' => 'playlist',
            'id' => $playlist->id,
            'title' => $playlist->title,
            'subtitle' => $playlist->user->name . ' • ' . $playlist->songs_count . ' tracks',
            'owner_uid' => $playlist->user->uid,
            'owner_name' => $playlist->user->name,
            'image_url' => $this->mediaUrl($playlist->cover_image_path),
            'route' => "/playlists/{$playlist->id}",
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return URL::to('/api/media/' . ltrim($path, '/'));
    }

    private function serializeConversation(Conversation $conversation, string $viewerUid): array
    {
        $participants = $conversation->participants;
        $currentParticipant = $participants->firstWhere('user_uid', $viewerUid);
        $otherParticipant = $participants->first(fn(ConversationParticipant $participant) => $participant->user_uid !== $viewerUid);

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'last_message_at' => optional($conversation->last_message_at)->toISOString(),
            'created_at' => optional($conversation->created_at)->toISOString(),
            'updated_at' => optional($conversation->updated_at)->toISOString(),
            'last_read_at' => optional($currentParticipant?->last_read_at)->toISOString(),
            'unread_count' => (int) ($conversation->unread_count ?? 0),
            'has_unread' => (int) ($conversation->unread_count ?? 0) > 0,
            'other_user' => $this->serializePublicUser($otherParticipant?->user),
            'last_message' => $conversation->latestMessage ? $this->serializeMessage($conversation->latestMessage) : null,
        ];
    }

    private function serializeMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_uid' => $message->sender_uid,
            'body' => $message->body,
            'message_type' => $message->message_type,
            'shared_item' => $message->shared_item,
            'created_at' => optional($message->created_at)->toISOString(),
            'updated_at' => optional($message->updated_at)->toISOString(),
            'edited_at' => optional($message->edited_at)->toISOString(),
            'sender' => $this->serializePublicUser($message->sender),
        ];
    }
}
