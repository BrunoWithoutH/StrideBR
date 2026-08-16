<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

function stridebr_admin_audit(PDO $pdo, string $actor, string $action, ?string $type = null, ?string $target = null, array $details = []): void
{
    $stmt = $pdo->prepare('INSERT INTO admin_audit_log (idator, acao, alvo_tipo, alvo_id, detalhes, ip) VALUES (:actor, :action, :type, :target, CAST(:details AS jsonb), CAST(:ip AS inet))');
    $ip = stridebr_client_ip();
    $stmt->bindValue(':actor', $actor);
    $stmt->bindValue(':action', $action);
    $stmt->bindValue(':type', $type, $type === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':target', $target, $target === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':details', json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $stmt->bindValue(':ip', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->execute();
}

function stridebr_admin_can_manage(string $actorRole, string $actorId, array $target, bool $destructive = false): bool
{
    $targetId = (string) ($target['idusuario'] ?? '');
    $targetRole = (string) ($target['papelusuario'] ?? 'user');
    if ($targetId === '' || $targetId === $actorId) {
        return false;
    }
    if ($actorRole === 'owner') {
        return $targetRole !== 'owner';
    }
    if ($destructive || $actorRole !== 'admin') {
        return false;
    }
    return stridebr_role_rank($targetRole) < stridebr_role_rank('admin');
}

function stridebr_admin_nav(string $active): string
{
    $items = [
        'dashboard' => ['/admin/index.php', 'Visão geral'],
        'users' => ['/admin/users.php', 'Usuários'],
        'feedback' => ['/admin/feedback.php', 'Feedback'],
    ];
    $html = '<nav class="admin-subnav" aria-label="Administração">';
    foreach ($items as $key => [$href, $label]) {
        $html .= '<a href="' . stridebr_e($href) . '"' . ($key === $active ? ' class="is-active"' : '') . '>' . stridebr_e($label) . '</a>';
    }
    return $html . '</nav>';
}
