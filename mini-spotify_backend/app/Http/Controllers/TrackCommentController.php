<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Models\TrackComment;
use Illuminate\Http\Request;

class TrackCommentController extends Controller
{
    protected function serializeComment(TrackComment $comment): array
    {
        $comment->loadMissing('user:uid,name,role,avatar_path,followers_count');

        return [
            'id' => $comment->id,
            'song_id' => $comment->song_id,
            'user_uid' => $comment->user_uid,
            'text' => $comment->body,
            'timestamp' => $comment->timestamp_seconds,
            'created_at' => $comment->created_at?->toISOString(),
            'updated_at' => $comment->updated_at?->toISOString(),
            'user' => $comment->user ? [
                'uid' => $comment->user->uid,
                'name' => $comment->user->name,
                'role' => $comment->user->role,
                'avatar_path' => $comment->user->avatar_path,
                'avatar_url' => $comment->user->avatar_url,
                'followers_count' => $comment->user->followers_count,
            ] : null,
        ];
    }

    public function index(Song $song)
    {
        $comments = $song->comments()
            ->with('user:uid,name,role,avatar_path,followers_count')
            ->orderBy('timestamp_seconds')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TrackComment $comment) => $this->serializeComment($comment))
            ->values();

        return response()->json([
            'song_id' => $song->id,
            'comments' => $comments,
        ]);
    }

    public function store(Request $request, Song $song)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:250'],
            'timestamp' => ['required', 'integer', 'min:0'],
        ]);
        
        $trimmedText = trim($validated['text']);

        if ($trimmedText === '') {
            return response()->json([
                'message' => 'Comment cannot be empty',
                'errors' => [
                    'text' => ['Comment cannot be empty'],
                ],
            ], 422);
        }

        $timestamp = (int) $validated['timestamp'];

        if ($song->duration !== null) {
            $timestamp = min($timestamp, max(0, (int) $song->duration));
        }

        $comment = $song->comments()->create([
            'user_uid' => $user->uid,
            'body' => trim($validated['text']),
            'timestamp_seconds' => $timestamp,
        ]);

        return response()->json($this->serializeComment($comment), 201);
    }
    
    public function update(Request $request, Song $song, TrackComment $comment)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        if ($comment->song_id !== $song->id) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        if ($comment->user_uid !== $user->uid && $user->role !== 'admin') {
            return response()->json(['message' => 'You can edit only your own comments'], 403);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:250'],
        ]);

        $trimmedText = trim($validated['text']);

        if ($trimmedText === '') {
            return response()->json([
                'message' => 'Comment cannot be empty',
                'errors' => [
                    'text' => ['Comment cannot be empty'],
                ],
            ], 422);
        }

        $comment->forceFill([
            'body' => $trimmedText,
        ])->save();

        return response()->json($this->serializeComment($comment));
    }

    public function destroy(Request $request, Song $song, TrackComment $comment)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        if ($comment->song_id !== $song->id) {
            return response()->json(['message' => 'Comment not found'], 404);
        }

        if ($comment->user_uid !== $user->uid && $user->role !== 'admin') {
            return response()->json(['message' => 'You can delete only your own comments'], 403);
        }

        $commentId = $comment->id;
        $comment->delete();

        return response()->json([
            'comment_id' => $commentId,
            'song_id' => $song->id,
        ]);
    }
}
