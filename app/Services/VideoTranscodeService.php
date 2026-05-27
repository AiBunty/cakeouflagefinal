<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class VideoTranscodeService
{
    /**
     * @return array{duration_seconds:float|null,resolution:string|null}
     */
    public static function transcodeToMp4(string $sourceAbsolute, string $targetAbsolute, ?string $ffmpegBinary = null): array
    {
        $cap = MediaCapabilityService::detect();
        $ffmpeg = $ffmpegBinary ?? (string)($cap['ffmpeg_binary'] ?? '');
        if ($ffmpeg === '') {
            throw new RuntimeException('FFmpeg is unavailable for media transcoding');
        }

        $targetDir = dirname($targetAbsolute);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        // MP4 uploads may already use canonical .mp4 path; avoid ffmpeg in-place overwrite.
        if ($sourceAbsolute === $targetAbsolute && is_file($targetAbsolute)) {
            return self::probeMetadata($targetAbsolute, (string)($cap['ffprobe_binary'] ?? ''));
        }

        $command = escapeshellcmd($ffmpeg)
            . ' -y -i ' . escapeshellarg($sourceAbsolute)
            . ' -map 0:v:0 -map 0:a?'
            . ' -c:v libx264 -preset fast -crf 26'
            . ' -vf ' . escapeshellarg('scale=1920:-2:force_original_aspect_ratio=decrease')
            . ' -c:a aac -b:a 128k -movflags +faststart '
            . escapeshellarg($targetAbsolute)
            . ' 2>&1';

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($targetAbsolute)) {
            @unlink($targetAbsolute);
            $tail = implode(' | ', array_slice($output, -3));
            throw new RuntimeException('FFmpeg video conversion failed' . ($tail !== '' ? ': ' . $tail : ''));
        }

        return self::probeMetadata($targetAbsolute, (string)($cap['ffprobe_binary'] ?? ''));
    }

    public static function generatePosterWebp(string $videoAbsolute, string $thumbnailAbsolute, ?string $ffmpegBinary = null): void
    {
        $cap = MediaCapabilityService::detect();
        $ffmpeg = $ffmpegBinary ?? (string)($cap['ffmpeg_binary'] ?? '');
        if ($ffmpeg === '') {
            throw new RuntimeException('FFmpeg is unavailable for thumbnail generation');
        }

        $targetDir = dirname($thumbnailAbsolute);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $command = escapeshellcmd($ffmpeg)
            . ' -y -ss 00:00:01 -i ' . escapeshellarg($videoAbsolute)
            . ' -frames:v 1 -vf ' . escapeshellarg('scale=1280:-2:force_original_aspect_ratio=decrease')
            . ' -c:v libwebp -quality 80 '
            . escapeshellarg($thumbnailAbsolute)
            . ' 2>&1';

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($thumbnailAbsolute)) {
            @unlink($thumbnailAbsolute);
            throw new RuntimeException('FFmpeg thumbnail generation failed');
        }
    }

    /**
     * @return array{duration_seconds:float|null,resolution:string|null}
     */
    public static function probeMetadata(string $videoAbsolute, ?string $ffprobeBinary = null): array
    {
        $ffprobe = trim((string)$ffprobeBinary);
        if ($ffprobe === '') {
            $cap = MediaCapabilityService::detect();
            $ffprobe = trim((string)($cap['ffprobe_binary'] ?? ''));
        }

        if ($ffprobe === '') {
            return ['duration_seconds' => null, 'resolution' => null];
        }

        $command = escapeshellcmd($ffprobe)
            . ' -v error -print_format json -show_streams -show_format '
            . escapeshellarg($videoAbsolute)
            . ' 2>&1';

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return ['duration_seconds' => null, 'resolution' => null];
        }

        $json = json_decode(implode("\n", $output), true);
        if (!is_array($json)) {
            return ['duration_seconds' => null, 'resolution' => null];
        }

        $duration = null;
        if (isset($json['format']['duration'])) {
            $duration = (float)$json['format']['duration'];
            if ($duration <= 0) {
                $duration = null;
            }
        }

        $resolution = null;
        $streams = $json['streams'] ?? [];
        if (is_array($streams)) {
            foreach ($streams as $stream) {
                if (!is_array($stream)) {
                    continue;
                }
                if ((string)($stream['codec_type'] ?? '') !== 'video') {
                    continue;
                }
                $w = (int)($stream['width'] ?? 0);
                $h = (int)($stream['height'] ?? 0);
                if ($w > 0 && $h > 0) {
                    $resolution = $w . 'x' . $h;
                }
                break;
            }
        }

        return ['duration_seconds' => $duration, 'resolution' => $resolution];
    }
}
