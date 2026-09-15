<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$runEntrypoint = static function (string $uri) use ($root): array {
    $command = sprintf(
        'cd %s && REQUEST_URI=%s REQUEST_METHOD=GET %s public/api/v1/index.php 2>&1',
        escapeshellarg($root),
        escapeshellarg($uri),
        escapeshellarg(PHP_BINARY)
    );
    exec($command, $lines, $status);
    return [$status, implode("\n", $lines)];
};

[$healthStatus, $healthOutput] = $runEntrypoint('/api/v1/health');
$assert($healthStatus === 0, 'GET /api/v1/health deve carregar o entrypoint real sem fatal.');
$health = json_decode($healthOutput, true);
$assert(is_array($health), 'GET /api/v1/health deve responder JSON.');
$assert(($health['data']['status'] ?? null) === 'ok', 'GET /api/v1/health deve responder status=ok.');
$assert(($health['data']['api_version'] ?? null) === 'v1', 'GET /api/v1/health deve anunciar api_version=v1.');

[$metaStatus, $metaOutput] = $runEntrypoint('/api/v1/meta');
$assert($metaStatus === 0, 'GET /api/v1/meta deve carregar o entrypoint real sem PostgreSQL obrigatório.');
$meta = json_decode($metaOutput, true);
$assert(is_array($meta), 'GET /api/v1/meta deve responder JSON.');
$assert(($meta['data']['api_version'] ?? null) === 'v1', 'GET /api/v1/meta deve anunciar api_version=v1.');
$assert(array_key_exists('server_time', $meta['data'] ?? []), 'GET /api/v1/meta deve expor server_time.');


$command = sprintf(
    'cd %s && %s -r %s 2>&1',
    escapeshellarg($root),
    escapeshellarg(PHP_BINARY),
    escapeshellarg('require "src/includes/environment.php"; require "src/function/api_v1.php"; echo session_status();')
);
exec($command, $sessionLines, $sessionStatus);
$assert($sessionStatus === 0, 'Carregar api_v1.php deve concluir sem fatal.');
$assert(trim(implode("\n", $sessionLines)) === (string) PHP_SESSION_NONE, 'Bootstrap da API v1 não pode iniciar sessão PHP.');

$command = sprintf(
    'cd %s && %s -r %s 2>&1',
    escapeshellarg($root),
    escapeshellarg(PHP_BINARY),
    escapeshellarg('require "src/includes/environment.php"; require "src/function/api_v1.php"; foreach (get_included_files() as $file) { if (str_ends_with($file, "/src/includes/app.php")) { echo "app.php"; } }')
);
exec($command, $includedLines, $includedStatus);
$assert($includedStatus === 0 && trim(implode("\n", $includedLines)) === '', 'Árvore de dependências da API v1 não pode incluir app.php.');

$activityModel = (string) file_get_contents($root . '/src/function/atividade_modelo.php');
$marketingService = (string) file_get_contents($root . '/src/function/marketing_service.php');
$api = (string) file_get_contents($root . '/src/function/api_v1.php');
$assert(str_contains($activityModel, "require_once __DIR__ . '/marketing_service.php';"), 'atividade_modelo deve depender do serviço de marketing neutro.');
$assert(!str_contains($activityModel, "require_once __DIR__ . '/marketing.php';"), 'atividade_modelo não pode puxar o bootstrap browser de marketing.');
$assert(!str_contains($marketingService, 'includes/app.php'), 'marketing_service não pode carregar app.php.');
$assert(!str_contains($marketingService, 'session_start') && !str_contains($marketingService, 'stridebr_start_session'), 'marketing_service precisa permanecer neutro de sessão/browser.');
$assert(str_contains($api, 'app.php: app.php starts the browser session') || str_contains($api, 'app.php starts the browser session'), 'api_v1 deve manter explícita a separação do bootstrap browser.');

echo "✓ API v1 real bootstrap static: {$assertions} assertions\n";
