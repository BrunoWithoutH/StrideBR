<?php

declare(strict_types=1);

function stridebr_build_identifier(): ?string
{
    $configured = trim((string) (getenv('STRIDEBR_BUILD') ?: ''));
    if ($configured !== '') return substr($configured, 0, 80);

    $allowFile = strtolower(trim((string) (getenv('STRIDEBR_BUILD_FILE_FALLBACK') ?: '')));
    if (in_array($allowFile, ['1', 'true', 'yes', 'on'], true)) {
        $file = dirname(__DIR__, 2) . '/.stridebr-build';
        if (is_file($file)) {
            $value = trim((string) @file_get_contents($file));
            if ($value !== '' && strlen($value) <= 80) return $value;
        }
    }
    return null;
}
