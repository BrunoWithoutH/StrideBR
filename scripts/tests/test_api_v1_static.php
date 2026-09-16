<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };

$router = $read('public/api/v1/index.php');
$helpers = $read('src/function/api_v1.php');
$buildHelper = $read('src/function/build_identifier.php');
$migration = $read('src/database/migrations/20260910_api_sessions.sql');
$assert(!str_contains($router, "includes/app.php"), 'API v1 não pode carregar app.php nem iniciar sessão PHP.');
$assert(strpos($router, "if (\$route === 'health')") < strpos($router, "pg_config.php") && strpos($router, "if (\$route === 'meta')") < strpos($router, "pg_config.php"), 'health/meta precisam responder sem depender do PostgreSQL.');
$assert(str_contains($router, 'stridebr_api_build_identifier()') && str_contains($buildHelper, "getenv('STRIDEBR_BUILD')"), 'GET /meta precisa expor build opcional vindo do ambiente de deploy.');
$assert(!str_contains($helpers, 'git rev-parse') && !str_contains($helpers, 'shell_exec') && !str_contains($helpers, 'exec(\'git'), 'API não pode executar Git em runtime para descobrir build.');
$assert(str_contains($read('src/config/pg_config.php'), 'STRIDEBR_API_JSON') && str_contains($read('src/config/pg_config.php'), 'throw $e'), 'Falha de banco da API precisa voltar ao envelope JSON do router.');
$assert(str_contains($router, "'auth/login'") && str_contains($router, "'auth/refresh'") && str_contains($router, "'activities'"), 'Rotas iniciais obrigatórias ausentes.');
$assert(str_contains($helpers, 'HTTP_AUTHORIZATION') && str_contains($helpers, 'function stridebr_api_user'), 'Bearer auth precisa ser centralizada.');
$assert(str_contains($helpers, 'hash(\'sha256\', $token)') && !str_contains($migration, 'access_token TEXT'), 'Tokens não podem ser armazenados em texto puro.');
$assert(str_contains($helpers, "INTERVAL '15 minutes'") && str_contains($helpers, "INTERVAL '30 days'"), 'Expirações de access e refresh ausentes.');
$assert(str_contains($migration, 'ON DELETE CASCADE') && str_contains($migration, 'refresh_token_hash CHAR(64) NOT NULL UNIQUE'), 'Sessões precisam ter integridade e refresh hash único.');
$assert(is_file($root . '/docs/api/openapi.yaml') && is_file($root . '/docs/MOBILE_API.md'), 'Documentação da API mobile ausente.');

$htaccess = $read('public/api/v1/.htaccess');
$docs = $read('docs/MOBILE_API.md');
$openapi = $read('docs/api/openapi.yaml');
$assert(str_contains($router, "if (\$method === 'POST')") && str_contains($router, 'stridebr_api_create_activity'), 'POST /activities precisa estar roteado na API v1.');
$assert(str_contains($helpers, 'function stridebr_api_idempotency_key') && str_contains($helpers, 'mobile-v1\0'), 'Criação mobile precisa de Idempotency-Key estável por usuário.');
$assert(str_contains($helpers, 'gpsWebBuildActivityPayload') && str_contains($helpers, 'atividadeSalvarRegistro'), 'POST /activities deve reutilizar o domínio GPS/atividade existente.');
$assert(str_contains($helpers, 'gpsWebFindExistingRecording') && str_contains($helpers, "'reused' => true"), 'Reenvio idempotente precisa reutilizar a atividade existente.');
$assert(str_contains($helpers, "'origem' => 'gps'") || str_contains($read('src/function/gps_web.php'), "'origem' => 'gps'"), 'Atividade mobile GPS deve manter origem GPS.');
$assert(str_contains($helpers, "origem_provedor = 'stridebr_android'") && str_contains($helpers, "'client_source' => 'app'"), 'Atividade Android precisa ser identificada como GPS do app sem reutilizar a provenance de GPS Web.');
$assert(str_contains($htaccess, 'HTTP_AUTHORIZATION') && str_contains($helpers, 'getallheaders'), 'Authorization precisa sobreviver ao Apache e ter fallback no PHP.');
$assert(str_contains($docs, 'POST /activities') && !str_contains($docs, 'criação/edição e importação ainda não fazem parte'), 'MOBILE_API precisa documentar a criação já implementada.');
$assert(str_contains($openapi, 'GpsActivityCreateRequest') && str_contains($openapi, 'Idempotency-Key'), 'OpenAPI precisa documentar POST /activities e idempotência.');
$assert(str_contains($router, 'stridebr_api_log_failure($e)') && !str_contains($router, "StrideBR API v1 failure: "), 'Falha 500 precisa usar logging estruturado server-side.');
$assert(str_contains($helpers, 'function stridebr_api_request_id') && str_contains($helpers, 'function stridebr_api_pdo_sqlstate') && str_contains($helpers, 'function stridebr_api_log_failure'), 'API precisa gerar request id e registrar SQLSTATE sem expor detalhes ao cliente.');
$assert(str_contains($helpers, "'route=' . \$method . ' ' . \$path") && str_contains($helpers, "'stage=' . stridebr_api_current_stage()") && str_contains($helpers, "'exception=' . get_class(\$exception)") && str_contains($helpers, "'sqlstate=' . (stridebr_api_pdo_sqlstate(\$exception) ?? '-')"), 'Logging precisa incluir route, stage, exception e SQLSTATE.');
$assert(str_contains($helpers, "stridebr_api_set_stage('activity.persist')") && str_contains($helpers, "stridebr_api_set_stage('gps.metadata')") && str_contains($helpers, "stridebr_api_set_stage('streams')") && str_contains($helpers, "stridebr_api_set_stage('laps')"), 'POST /activities precisa marcar os estágios críticos de persistência.');
$assert(str_contains($helpers, "header('X-Request-Id: ' . stridebr_api_request_id())"), 'Resposta da API precisa expor o request id para correlação sem dados sensíveis.');

echo "API v1 static checks passed\n";
