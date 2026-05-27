<?php

declare(strict_types=1);

if (!function_exists('normalizeDietaryMode')) {
    function normalizeDietaryMode(?string $value, string $default = 'veg_only'): string
    {
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['veg_only', 'veg_nonveg'], true) ? $normalized : $default;
    }
}

if (!function_exists('getDietaryMode')) {
    function getDietaryMode($connection = null, string $default = 'veg_only'): string
    {
        $resolvedConnection = $connection;

        if ($resolvedConnection === null && class_exists('\\App\\Core\\Database')) {
            try {
                $resolvedConnection = \App\Core\Database::getConnection();
            } catch (\Throwable $e) {
                $resolvedConnection = null;
            }
        }

        try {
            if ($resolvedConnection instanceof \PDO) {
                $stmt = $resolvedConnection->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
                $stmt->execute(['setting_key' => 'store_food_mode']);
                $value = $stmt->fetchColumn();
                return normalizeDietaryMode(is_string($value) ? $value : null, $default);
            }

            if ($resolvedConnection instanceof \mysqli) {
                $stmt = $resolvedConnection->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
                if ($stmt) {
                    $key = 'store_food_mode';
                    $stmt->bind_param('s', $key);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result ? $result->fetch_assoc() : null;
                    return normalizeDietaryMode(is_array($row) ? (string)($row['setting_value'] ?? '') : null, $default);
                }
            }
        } catch (\Throwable $e) {
            return $default;
        }

        return $default;
    }
}

if (!function_exists('getDietaryTypeOptions')) {
    function getDietaryTypeOptions($modeOrConnection = null): array
    {
        $mode = is_string($modeOrConnection)
            ? normalizeDietaryMode($modeOrConnection)
            : getDietaryMode($modeOrConnection);

        return $mode === 'veg_nonveg' ? ['veg', 'nonveg'] : ['veg'];
    }
}

if (!function_exists('normalizeDietaryType')) {
    function normalizeDietaryType(?string $dietaryType, $modeOrConnection = null): string
    {
        $options = getDietaryTypeOptions($modeOrConnection);
        $normalized = strtolower(trim((string)$dietaryType));
        return in_array($normalized, $options, true) ? $normalized : $options[0];
    }
}

if (!function_exists('dietaryTypeToIsVeg')) {
    function dietaryTypeToIsVeg(?string $dietaryType): int
    {
        return normalizeDietaryType($dietaryType, 'veg_nonveg') === 'nonveg' ? 0 : 1;
    }
}