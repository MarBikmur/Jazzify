<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class TrackStreamController extends Controller
{
    private const CHUNK_SIZE = 8192;

    public function streamUrl(Request $request, Song $song)
    {
        if (!$this->canAccessTrack($request, $song)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$song->audio_path || !Storage::disk('public')->exists($song->audio_path)) {
            return response()->json(['message' => 'Audio file not found'], 404);
        }

        $song->increment('play_count');
        $song->refresh();
        $song->refreshPopularityFromPlayCount();

        return response()->json([
            'stream_url' => URL::temporarySignedRoute(
                'tracks.stream',
                now()->addMinutes(30),
                ['song' => $song->id]
            ),
            'play_count' => $song->play_count,
            'popularity' => $song->popularity,
        ]);
    }

    public function stream(Request $request, Song $song)
    {
        if (!$song->audio_path || !Storage::disk('public')->exists($song->audio_path)) {
            return response()->json(['message' => 'Audio file not found'], 404);
        }

        $path = Storage::disk('public')->path($song->audio_path);
        $size = filesize($path);

        if ($size === false || $size <= 0) {
            return response()->json(['message' => 'Audio file not readable'], 404);
        }

        $mimeType = Storage::disk('public')->mimeType($song->audio_path) ?: 'application/octet-stream';
        $start = 0;
        $end = $size - 1;
        $status = 200;

        $rangeHeader = $request->header('Range');

        if ($rangeHeader) {
            $range = $this->parseRange($rangeHeader, $size);

            if (!$range) {
                return response('', 416, [
                    'Accept-Ranges' => 'bytes',
                    'Content-Range' => "bytes */{$size}",
                ]);
            }

            [$start, $end] = $range;
            $status = 206;
        }

        $length = $end - $start + 1;
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $length,
            'Content-Disposition' => 'inline; filename="' . basename($song->audio_path) . '"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Access-Control-Expose-Headers' => 'Accept-Ranges, Content-Length, Content-Range',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($path, $start, $length) {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;
            }

            fseek($handle, $start);
            $bytesLeft = $length;

            while ($bytesLeft > 0 && !feof($handle)) {
                $readLength = min(self::CHUNK_SIZE, $bytesLeft);
                $buffer = fread($handle, $readLength);

                if ($buffer === false) {
                    break;
                }

                echo $buffer;
                flush();

                $bytesLeft -= strlen($buffer);
            }

            fclose($handle);
        }, $status, $headers);
    }

    private function parseRange(string $rangeHeader, int $size): ?array
    {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
            return null;
        }

        $startValue = $matches[1];
        $endValue = $matches[2];

        if ($startValue === '' && $endValue === '') {
            return null;
        }

        if ($startValue === '') {
            $suffixLength = (int) $endValue;

            if ($suffixLength <= 0) {
                return null;
            }

            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            $start = (int) $startValue;
            $end = $endValue === '' ? $size - 1 : (int) $endValue;
        }

        if ($start < 0 || $end < $start || $start >= $size) {
            return null;
        }

        return [$start, min($end, $size - 1)];
    }

    private function canAccessTrack(Request $request, Song $song): bool
    {
        $user = $request->user();

        return (bool) $user;
    }
}
