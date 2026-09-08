<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('moderator');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';

$stmt = $pdo->prepare(
    "SELECT f.idfeedback,
            f.idusuario,
            f.anonimo,
            f.tipo,
            f.titulo,
            f.mensagem,
            f.pagina,
            f.status,
            f.prioridade,
            f.notas_admin,
            f.criado_em,
            f.atualizado_em,
            u.nomeusuario,
            u.nome_exibicao,
            u.username,
            CASE WHEN f.anonimo THEN FALSE ELSE f.idusuario = :current_user END AS enviado_por_mim
       FROM feedbacks f
       LEFT JOIN usuarios u ON u.idusuario = f.idusuario
      ORDER BY f.criado_em ASC"
);
$stmt->execute([':current_user' => $idUsuario]);
$rows = $stmt->fetchAll();

$counts = [
    'total' => count($rows),
    'por_tipo' => [],
    'por_status' => [],
    'por_prioridade' => [],
];
$feedbacks = [];

foreach ($rows as $row) {
    $type = (string) $row['tipo'];
    $status = (string) $row['status'];
    $priority = (string) $row['prioridade'];
    $anonymous = stridebr_db_bool($row['anonimo'] ?? false);
    $mine = stridebr_db_bool($row['enviado_por_mim'] ?? false);

    $counts['por_tipo'][$type] = ($counts['por_tipo'][$type] ?? 0) + 1;
    $counts['por_status'][$status] = ($counts['por_status'][$status] ?? 0) + 1;
    $counts['por_prioridade'][$priority] = ($counts['por_prioridade'][$priority] ?? 0) + 1;

    $author = null;
    if (!$anonymous && $row['idusuario'] !== null) {
        $displayName = trim((string) ($row['nome_exibicao'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($row['nomeusuario'] ?? ''));
        }
        $author = [
            'idusuario' => (string) $row['idusuario'],
            'nome' => $displayName !== '' ? $displayName : 'Usuário removido',
            'username' => $row['username'] !== null ? (string) $row['username'] : null,
        ];
    }

    $feedbacks[] = [
        'idfeedback' => (string) $row['idfeedback'],
        'tipo' => $type,
        'titulo' => (string) $row['titulo'],
        'mensagem' => (string) $row['mensagem'],
        'contexto' => $row['pagina'] !== null ? (string) $row['pagina'] : null,
        'status' => $status,
        'prioridade' => $priority,
        'notas_admin' => $row['notas_admin'] !== null ? (string) $row['notas_admin'] : null,
        'anonimo' => $anonymous,
        'autor' => $author,
        'enviado_por_mim' => $mine,
        'criado_em' => (string) $row['criado_em'],
        'atualizado_em' => (string) $row['atualizado_em'],
    ];
}

$payload = [
    'schema' => 'stridebr.feedback-export.v1',
    'exportado_em' => date(DATE_ATOM),
    'exportado_por' => [
        'idusuario' => $idUsuario,
        'nome' => stridebr_display_name(),
        'username' => isset($_SESSION['Username']) && is_string($_SESSION['Username']) && $_SESSION['Username'] !== ''
            ? $_SESSION['Username']
            : null,
    ],
    'privacidade' => 'O arquivo não inclui e-mail, endereço IP nem user-agent. Feedbacks anônimos permanecem sem vínculo com usuário.',
    'resumo' => $counts,
    'feedbacks' => $feedbacks,
];

stridebr_admin_audit(
    $pdo,
    $idUsuario,
    'feedback.export',
    'feedback',
    null,
    ['quantidade' => count($feedbacks)]
);

$filename = 'stridebr-feedbacks-' . date('Y-m-d_H-i-s') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
