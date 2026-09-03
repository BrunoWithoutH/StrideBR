<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!stridebr_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'available' => false, 'message' => 'Sessão expirada. Entre novamente.']);
    exit;
}

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

$username = stridebr_lower(trim((string) ($_GET['username'] ?? '')));

if ($username === '') {
    echo json_encode(['valid' => true, 'available' => true]);
    exit;
}

if (!stridebr_username_is_valid($username)) {
    http_response_code(422);
    echo json_encode([
        'valid' => false,
        'available' => false,
        'message' => 'Username inválido ou reservado. Use letras sem acento e números; ponto, hífen ou underline só podem ficar entre caracteres.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(username) = lower(:username) AND idusuario <> :id LIMIT 1');
$stmt->execute([
    ':username' => $username,
    ':id' => stridebr_user_id(),
]);

$available = !$stmt->fetchColumn();
echo json_encode([
    'valid' => true,
    'available' => $available,
    'message' => $available ? 'Disponível.' : 'Esse username já está em uso.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
