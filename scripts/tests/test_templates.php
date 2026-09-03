<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $content = (string) file_get_contents($file->getPathname());
    preg_match_all('/<\s*(\/?)form\b/i', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $depth = 0;
    foreach ($matches as $match) {
        $closing = $match[1][0] === '/';
        if ($closing) {
            $depth = max(0, $depth - 1);
            continue;
        }
        $depth++;
        if ($depth > 1) {
            $before = substr($content, 0, $match[0][1]);
            $line = substr_count($before, "\n") + 1;
            $errors[] = str_replace($root . '/', '', $file->getPathname()) . ':' . $line . ' contém <form> aninhado.';
            break;
        }
    }
}

$security = (string) @file_get_contents($root . '/public/.htaccess');
foreach (['Options -Indexes', 'Strict-Transport-Security', 'Content-Security-Policy', 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy'] as $needle) {
    if (!str_contains($security, $needle)) $errors[] = 'public/.htaccess não contém ' . $needle . '.';
}
$uploads = (string) @file_get_contents($root . '/public/uploads/.htaccess');
if (!str_contains($uploads, 'Options -Indexes') || !str_contains($uploads, 'php|phtml|phar')) {
    $errors[] = 'public/uploads/.htaccess não contém as proteções esperadas.';
}

if ($errors !== []) {
    foreach ($errors as $error) fwrite(STDERR, $error . PHP_EOL);
    exit(1);
}

echo "✓ templates/security static\n";
