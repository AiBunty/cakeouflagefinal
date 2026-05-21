<?php
declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string, string> */
    private static $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            self::$vars[$key] = $value;
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            $value = $_ENV[$key];
            return is_scalar($value) ? (string)$value : $default;
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string)$value;
        }

        if (defined($key)) {
            $constantValue = constant($key);
            return is_scalar($constantValue) ? (string)$constantValue : $default;
        }

        return $default;
    }
}
