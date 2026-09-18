<?php
// CLI probe exercises the real web endpoint with the PostgreSQL integration DB.
if (PHP_SAPI !== 'cli' || $argc !== 5 || !preg_match('/test|alpha/', (string) getenv('STRIDEBR_DB_NAME'))) exit(1);
require dirname(__DIR__, 2) . '/src/includes/app.php';
$_SESSION['IdUsuario'] = $argv[1];
$_SESSION['csrf_token'] = 'alpha-participants-csrf';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST = ['id'=>$argv[2], 'user_id'=>$argv[3], 'action'=>'invite', 'csrf_token'=>$argv[4] === 'valid' ? 'alpha-participants-csrf' : 'invalid'];
register_shutdown_function(static function (): void { fwrite(STDERR, 'HTTP_STATUS=' . (http_response_code() ?: 200)); });
require dirname(__DIR__, 2) . '/public/api/atividade-participantes.php';
