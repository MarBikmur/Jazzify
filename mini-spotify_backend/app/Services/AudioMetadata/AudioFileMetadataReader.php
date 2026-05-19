<?php

namespace App\Services\AudioMetadata;

use Illuminate\Support\Str;

class AudioFileMetadataReader
{
    public function read(string $path): array
    {
        $result = [
            'title' => null,
            'artist' => null,
            'album' => null,
            'genre' => null,
            'release_date' => null,
            'duration' => null,
            'musicbrainz_recording_id' => null,
            'musicbrainz_artist_id' => null,
            'musicbrainz_release_id' => null,
            'cover' => null,
        ];

        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return $this->applyFilenameFallback($result, $path);
        }

        $audioOffset = 0;
        $header = fread($handle, 10);
        if (strlen($header) === 10 && substr($header, 0, 3) === 'ID3') {
            $versionMajor = ord($header[3]);
            $flags = ord($header[5]);
            $size = $this->decodeSyncSafe(substr($header, 6, 4));
            $audioOffset = 10 + $size;
            $tagBody = $size > 0 ? fread($handle, $size) : '';

            if (($flags & 0x80) !== 0) {
                $tagBody = $this->removeUnsynchronization($tagBody);
            }

            $frames = $this->parseFrames($tagBody, $versionMajor);
            $result = array_merge($result, $frames);
        }

        fclose($handle);

        if ($result['duration'] === null) {
            $result['duration'] = $this->estimateMpegDuration($path, $audioOffset);
        }

        return $this->applyFilenameFallback($result, $path);
    }

    protected function parseFrames(string $body, int $versionMajor): array
    {
        $offset = 0;
        $length = strlen($body);
        $result = [];

        while ($offset < $length) {
            if ($versionMajor === 2) {
                $frameId = substr($body, $offset, 3);
                $frameSizeBytes = substr($body, $offset + 3, 3);
                if (trim($frameId, "\0") === '' || strlen($frameSizeBytes) < 3) {
                    break;
                }
                $frameSize = unpack('N', "\0" . $frameSizeBytes)[1] ?? 0;
                $frameHeaderLength = 6;
            } else {
                $frameId = substr($body, $offset, 4);
                $frameSizeBytes = substr($body, $offset + 4, 4);
                if (trim($frameId, "\0") === '' || strlen($frameSizeBytes) < 4) {
                    break;
                }
                $frameSize = $versionMajor === 4
                    ? $this->decodeSyncSafe($frameSizeBytes)
                    : (unpack('N', $frameSizeBytes)[1] ?? 0);
                $frameHeaderLength = 10;
            }

            if ($frameSize <= 0) {
                break;
            }

            $payload = substr($body, $offset + $frameHeaderLength, $frameSize);
            $this->applyFrame($result, $frameId, $payload, $versionMajor);
            $offset += $frameHeaderLength + $frameSize;
        }

        return $result;
    }

    protected function applyFrame(array &$result, string $frameId, string $payload, int $versionMajor): void
    {
        $textMap = [
            'TIT2' => 'title',
            'TT2' => 'title',
            'TPE1' => 'artist',
            'TP1' => 'artist',
            'TALB' => 'album',
            'TAL' => 'album',
            'TCON' => 'genre',
            'TCO' => 'genre',
            'TDRC' => 'release_date',
            'TYER' => 'release_date',
            'TYE' => 'release_date',
            'TLEN' => 'duration',
            'TLE' => 'duration',
        ];

        if (isset($textMap[$frameId])) {
            $value = $this->decodeTextFrame($payload);
            if ($value !== null && $value !== '') {
                $result[$textMap[$frameId]] = match ($textMap[$frameId]) {
                    'duration' => $this->normalizeDuration($value),
                    'release_date' => $this->normalizeDate($value),
                    default => $this->cleanupText($value),
                };
            }
            return;
        }

        if (in_array($frameId, ['TXXX', 'TXX'], true)) {
            $pair = $this->decodeUserTextFrame($payload);
            if (! $pair) {
                return;
            }

            $description = Str::lower($pair['description']);
            $value = $this->cleanupText($pair['value']);

            if (str_contains($description, 'musicbrainz') && str_contains($description, 'recording')) {
                $result['musicbrainz_recording_id'] = $value;
            } elseif (str_contains($description, 'musicbrainz') && str_contains($description, 'artist')) {
                $result['musicbrainz_artist_id'] = $value;
            } elseif (str_contains($description, 'musicbrainz') && (str_contains($description, 'album') || str_contains($description, 'release'))) {
                $result['musicbrainz_release_id'] = $value;
            }
            return;
        }

        if (in_array($frameId, ['APIC', 'PIC'], true) && ! isset($result['cover'])) {
            $cover = $this->decodeCoverFrame($payload, $frameId === 'PIC');
            if ($cover) {
                $result['cover'] = $cover;
            }
        }
    }

    protected function decodeTextFrame(string $payload): ?string
    {
        if ($payload === '') {
            return null;
        }

        $encoding = ord($payload[0]);
        $text = substr($payload, 1);

        return $this->decodeEncodedString($text, $encoding);
    }

    protected function decodeUserTextFrame(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $encoding = ord($payload[0]);
        $content = substr($payload, 1);
        $separator = in_array($encoding, [1, 2], true) ? "\0\0" : "\0";

        $parts = explode($separator, $content, 2);
        if (count($parts) < 2) {
            return null;
        }

        return [
            'description' => $this->decodeEncodedString($parts[0], $encoding) ?? '',
            'value' => $this->decodeEncodedString($parts[1], $encoding) ?? '',
        ];
    }

    protected function decodeCoverFrame(string $payload, bool $isV22): ?array
    {
        if ($payload === '') {
            return null;
        }

        $encoding = ord($payload[0]);
        $cursor = 1;

        if ($isV22) {
            $mime = $this->mapV22ImageFormat(substr($payload, $cursor, 3));
            $cursor += 3;
        } else {
            $end = strpos($payload, "\0", $cursor);
            if ($end === false) {
                return null;
            }
            $mime = substr($payload, $cursor, $end - $cursor);
            $cursor = $end + 1;
        }

        $cursor += 1;
        $separator = in_array($encoding, [1, 2], true) ? "\0\0" : "\0";
        $descriptionEnd = strpos($payload, $separator, $cursor);
        if ($descriptionEnd === false) {
            return null;
        }

        $cursor = $descriptionEnd + strlen($separator);
        $imageData = substr($payload, $cursor);

        if ($imageData === '') {
            return null;
        }

        return [
            'mime' => $mime ?: 'image/jpeg',
            'bytes' => $imageData,
        ];
    }

    protected function decodeEncodedString(string $value, int $encoding): ?string
    {
        $value = match ($encoding) {
            1 => $this->stripUtf16Bom($value),
            2 => mb_convert_encoding($value, 'UTF-8', 'UTF-16BE'),
            3 => $value,
            default => mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1'),
        };

        $value = str_replace("\0", '', $value);
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    protected function stripUtf16Bom(string $value): string
    {
        if (str_starts_with($value, "\xFF\xFE")) {
            return mb_convert_encoding(substr($value, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($value, "\xFE\xFF")) {
            return mb_convert_encoding(substr($value, 2), 'UTF-8', 'UTF-16BE');
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-16');
    }

    protected function normalizeDuration(string $value): ?int
    {
        $numeric = (int) preg_replace('/[^\d]/', '', $value);
        if ($numeric <= 0) {
            return null;
        }

        return $numeric > 1000 ? (int) round($numeric / 1000) : $numeric;
    }

    protected function normalizeDate(string $value): ?string
    {
        if (preg_match('/\d{4}-\d{2}-\d{2}/', $value, $matches)) {
            return $matches[0];
        }

        if (preg_match('/\d{4}/', $value, $matches)) {
            return $matches[0] . '-01-01';
        }

        return null;
    }

    protected function cleanupText(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        return trim($value, " \t\n\r\0\x0B/");
    }

    protected function applyFilenameFallback(array $result, string $path): array
    {
        $fallback = $this->normalizeFilenameText(pathinfo($path, PATHINFO_FILENAME));

        if ($fallback !== '') {
            $parts = preg_split('/\s+-\s+/u', $fallback) ?: [];
            if (count($parts) >= 3) {
                $sourcePrefix = Str::lower(trim((string) ($parts[0] ?? '')));
                if (preg_match('/^(spotidown|spotidown app|spotdown|spotdown app|youtube|y2mate|mp3juice)$/u', $sourcePrefix)) {
                    array_shift($parts);
                    $fallback = implode(' - ', $parts);
                }
            }
        }

        if (! $result['title'] && $fallback !== '') {
            $result['title'] = $fallback;
        }

        if (! $result['artist'] && $fallback !== '') {
            $parts = preg_split('/\s+-\s+/u', $fallback) ?: [];
            if (count($parts) >= 2) {
                $result['title'] = trim((string) ($parts[0] ?? $result['title']));
                $result['artist'] = trim((string) end($parts));
            }
        }

        return $result;
    }

    protected function normalizeFilenameText(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/(?<=\p{L})_(?=\p{L})/u', "'", $value) ?? $value;
        $value = preg_replace('/[_\-.]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+-\s+/u', ' - ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = preg_replace('/[_\-.]?(spotdown\.org|youtube|y2mate|mp3juice|official|lyrics?|audio|video|hd|hq|explicit|clean)$/iu', '', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B-");
    }

    protected function decodeSyncSafe(string $bytes): int
    {
        $bytes = str_split($bytes);

        return ((ord($bytes[0] ?? "\0") & 0x7f) << 21)
            | ((ord($bytes[1] ?? "\0") & 0x7f) << 14)
            | ((ord($bytes[2] ?? "\0") & 0x7f) << 7)
            | (ord($bytes[3] ?? "\0") & 0x7f);
    }

    protected function removeUnsynchronization(string $value): string
    {
        return str_replace("\xFF\x00", "\xFF", $value);
    }

    protected function mapV22ImageFormat(string $format): string
    {
        return match (strtoupper($format)) {
            'PNG' => 'image/png',
            'GIF' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    protected function estimateMpegDuration(string $path, int $audioOffset = 0): ?int
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return null;
        }

        if ($audioOffset > 0) {
            fseek($handle, $audioOffset);
        }

        $header = null;
        while (! feof($handle)) {
            $chunk = fread($handle, 4);
            if (strlen($chunk) < 4) {
                break;
            }

            $bits = unpack('N', $chunk)[1] ?? 0;
            if ($this->isValidMpegHeader($bits)) {
                $header = $bits;
                break;
            }

            fseek($handle, -3, SEEK_CUR);
        }

        fclose($handle);

        if ($header === null) {
            return null;
        }

        $bitrate = $this->extractBitrate($header);
        if (! $bitrate) {
            return null;
        }

        $fileSize = @filesize($path);
        if (! is_int($fileSize) || $fileSize <= $audioOffset) {
            return null;
        }

        return (int) max(0, round((($fileSize - $audioOffset) * 8) / ($bitrate * 1000)));
    }

    protected function isValidMpegHeader(int $header): bool
    {
        if (($header & 0xFFE00000) !== 0xFFE00000) {
            return false;
        }

        $versionBits = ($header >> 19) & 0b11;
        $layerBits = ($header >> 17) & 0b11;
        $bitrateIndex = ($header >> 12) & 0b1111;
        $sampleRateIndex = ($header >> 10) & 0b11;

        return $versionBits !== 0b01
            && $layerBits !== 0b00
            && $bitrateIndex !== 0b0000
            && $bitrateIndex !== 0b1111
            && $sampleRateIndex !== 0b11;
    }

    protected function extractBitrate(int $header): ?int
    {
        $versionBits = ($header >> 19) & 0b11;
        $layerBits = ($header >> 17) & 0b11;
        $bitrateIndex = ($header >> 12) & 0b1111;

        $version = match ($versionBits) {
            0b11 => '1',
            0b10 => '2',
            0b00 => '2.5',
            default => null,
        };

        $layer = match ($layerBits) {
            0b11 => '1',
            0b10 => '2',
            0b01 => '3',
            default => null,
        };

        if (! $version || ! $layer) {
            return null;
        }

        $tables = [
            '1' => [
                '1' => [null, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
                '2' => [null, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
                '3' => [null, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
            ],
            '2' => [
                '1' => [null, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
                '2' => [null, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
                '3' => [null, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            ],
            '2.5' => [
                '1' => [null, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
                '2' => [null, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
                '3' => [null, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            ],
        ];

        return $tables[$version][$layer][$bitrateIndex] ?? null;
    }
}
