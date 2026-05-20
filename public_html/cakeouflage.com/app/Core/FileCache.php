<?php
declare(strict_types=1);

namespace App\Core;

final class FileCache
{
    private static function cacheDir(): string
    {
        $dir = __DIR__ . '/../../storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function pathFor(string $key): string
    {
        return self::cacheDir() . '/' . sha1($key) . '.json';
    }

    /** @return array<string,mixed>|list<mixed>|null */
    public static function get(string $key, int $ttlSeconds)
    {
        $path = self::pathFor($key);
        if (!is_file($path)) {
            return null;
        }

        $mtime = (int)@filemtime($path);
        if ($mtime <= 0 || (time() - $mtime) > $ttlSeconds) {
            return null;
        }

        $json = @file_get_contents($path);
        if ($json === false || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string,mixed>|list<mixed> $value */
    public static function set(string $key, array $value): void
    {
        $path = self::pathFor($key);
        @file_put_contents($path, json_encode($value, JSON_UNESCAPED_SLASHES));
    }

    public static function forget(string $key): void
    {
        $path = self::pathFor($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
