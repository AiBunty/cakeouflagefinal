<?php
declare(strict_types=1);

namespace App\Services;

final class MediaCapabilityService
{
    /** @return array<string,mixed> */
    public static function detect(): array
    {
        $ffmpeg = self::findBinary(['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg']);
        $ffprobe = self::findBinary(['ffprobe', '/usr/bin/ffprobe', '/usr/local/bin/ffprobe']);

        $h264 = $ffmpeg !== null ? self::supportsEncoder($ffmpeg, 'libx264') : false;
        $aac = $ffmpeg !== null ? self::supportsEncoder($ffmpeg, 'aac') : false;
        $webp = $ffmpeg !== null ? self::supportsEncoder($ffmpeg, 'libwebp') : false;

        return [
            'ffmpeg_available' => $ffmpeg !== null,
            'ffprobe_available' => $ffprobe !== null,
            'ffmpeg_binary' => $ffmpeg,
            'ffprobe_binary' => $ffprobe,
            'h264_supported' => $h264,
            'aac_supported' => $aac,
            'webp_supported' => $webp,
            'conversion_enabled' => $ffmpeg !== null && $ffprobe !== null && $h264 && $aac,
            'checked_at' => gmdate('c'),
        ];
    }

    private static function supportsEncoder(string $ffmpegBinary, string $encoder): bool
    {
        $out = [];
        $code = 1;
        @exec(escapeshellcmd($ffmpegBinary) . ' -hide_banner -encoders 2>&1', $out, $code);
        if ($code !== 0 || empty($out)) {
            return false;
        }

        $blob = strtolower(implode("\n", $out));
        return str_contains($blob, strtolower($encoder));
    }

    /** @param array<int,string> $candidates */
    private static function findBinary(array $candidates): ?string
    {
        if (!function_exists('exec') || self::isExecDisabled()) {
            return null;
        }

        foreach ($candidates as $candidate) {
            $out = [];
            $code = 1;
            @exec(escapeshellcmd($candidate) . ' -version 2>&1', $out, $code);
            if ($code === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private static function isExecDisabled(): bool
    {
        $disabled = (string)ini_get('disable_functions');
        if ($disabled === '') {
            return false;
        }

        $list = array_map('trim', explode(',', $disabled));
        return in_array('exec', $list, true);
    }
}
