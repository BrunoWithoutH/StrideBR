<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };

$router = $read('public/api/v1/index.php');
$helpers = $read('src/function/api_v1.php');
$migration = $read('src/database/migrations/20260910_api_sessions.sql');
$assert(!str_contains($router, "includes/app.php"), 'API v1 não pode carregar app.php nem iniciar sessão PHP.');
$assert(str_contains($router, "'auth/login'") && str_contains($router, "'auth/refresh'") && str_contains($router, "'activities'"), 'Rotas iniciais obrigatórias ausentes.');
$assert(str_contains($helpers, 'HTTP_AUTHORIZATION') && str_contains($helpers, 'function stridebr_api_user'), 'Bearer auth precisa ser centralizada.');
$assert(str_contains($helpers, 'hash(\'sha256\', $token)') && !str_contains($migration, 'access_token TEXT'), 'Tokens não podem ser armazenados em texto puro.');
$assert(str_contains($helpers, "INTERVAL '15 minutes'") && str_contains($helpers, "INTERVAL '30 days'"), 'Expirações de access e refresh ausentes.');
$assert(str_contains($migration, 'ON DELETE CASCADE') && str_contains($migration, 'refresh_token_hash CHAR(64) NOT NULL UNIQUE'), 'Sessões precisam ter integridade e refresh hash único.');
$assert(is_file($root . '/docs/api/openapi.yaml') && is_file($root . '/docs/MOBILE_API.md'), 'Documentação da API mobile ausente.');

echo "API v1 static checks passed\n";
