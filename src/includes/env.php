<?php

declare(strict_types=1);

if (!defined('STRIDEBR_ENV_BOOTSTRAPPED')) {
    define('STRIDEBR_ENV_BOOTSTRAPPED', true);

    $projectRoot = dirname(__DIR__, 2);
    $configuredEnvFile = getenv('STRIDEBR_ENV_FILE');
    $envFile = is_string($configuredEnvFile) && trim($configuredEnvFile) !== ''
        ? trim($configuredEnvFile)
        : $projectRoot . '/.env';

    if (is_file($envFile) && is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                if (str_starts_with($line, 'export ')) $line = trim(substr($line, 7));
                $separator = strpos($line, '=');
                if ($separator === false) continue;

                $key = trim(substr($line, 0, $separator));
                if (!preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) continue;
                if (getenv($key) !== false) continue;

                $value = trim(substr($line, $separator + 1));
                $length = strlen($value);
                if ($length >= 2) {
                    $first = $value[0];
                    $last = $value[$length - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $value = substr($value, 1, -1);
                        if ($first === '"') {
                            $value = str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $value);
                        }
                    }
                }

                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        define('STRIDEBR_ENV_FILE_LOADED', $envFile);
    } else {
        define('STRIDEBR_ENV_FILE_LOADED', '');
    }
}
