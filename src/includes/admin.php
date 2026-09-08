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
        'dashboard' => ['/admin/index.php', stridebr_t('admin.nav.overview', [], 'Visão geral')],
        'feedback' => ['/admin/feedback.php', stridebr_t('admin.nav.feedback', [], 'Feedback')],
    ];
    if (stridebr_has_role('admin')) {
        $items = [
            'dashboard' => ['/admin/index.php', stridebr_t('admin.nav.overview', [], 'Visão geral')],
            'feedback' => ['/admin/feedback.php', stridebr_t('admin.nav.feedback', [], 'Feedback')],
            'users' => ['/admin/users.php', stridebr_t('admin.nav.users', [], 'Usuários')],
            'events' => ['/admin/events.php', stridebr_t('admin.nav.events', [], 'Eventos')],
            'diagnostics' => ['/admin/diagnostics.php', stridebr_t('admin.nav.diagnostics', [], 'Diagnóstico')],
        ];
    }
    $html = '<nav class="admin-subnav" aria-label="Administração">';
    foreach ($items as $key => [$href, $label]) {
        $html .= '<a href="' . stridebr_e($href) . '"' . ($key === $active ? ' class="is-active" aria-current="page"' : '') . '>' . stridebr_e($label) . '</a>';
    }
    return $html . '</nav>';
}

function stridebr_admin_feedback_state(string $status): string
{
    return match ($status) {
        'novo' => 'unread',
        'resolvido' => 'resolved',
        default => 'read',
    };
}

function stridebr_admin_feedback_status_for_action(string $action): ?string
{
    return match ($action) {
        'mark_read' => 'lendo',
        'mark_unread' => 'novo',
        'mark_resolved' => 'resolvido',
        default => null,
    };
}

function stridebr_admin_feedback_unread_count(PDO $pdo): int
{
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM feedbacks WHERE status = 'novo'")->fetchColumn();
    } catch (PDOException $e) {
        if (in_array($e->getCode(), ['42P01', '42703'], true)) {
            return 0;
        }
        throw $e;
    }
}

function stridebr_admin_feedback_apply_state(PDO $pdo, string $actorId, array $ids, string $action): int
{
    $status = stridebr_admin_feedback_status_for_action($action);
    if ($status === null) {
        throw new InvalidArgumentException(stridebr_t('admin.feedback.invalid_action', [], 'Ação de feedback inválida.'));
    }

    $ids = array_values(array_unique(array_filter(
        array_map(static fn($id): string => trim((string) $id), $ids),
        static fn(string $id): bool => $id !== ''
    )));
    if ($ids === [] || count($ids) > 500) {
        throw new InvalidArgumentException(stridebr_t('admin.feedback.invalid_selection', [], 'Seleção de feedback inválida.'));
    }
    foreach ($ids as $id) {
        if (preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id) !== 1) {
            throw new InvalidArgumentException(stridebr_t('admin.feedback.invalid_id', [], 'ID de feedback inválido.'));
        }
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->beginTransaction();
    try {
        $existing = $pdo->prepare("SELECT idfeedback FROM feedbacks WHERE idfeedback IN ({$placeholders}) FOR UPDATE");
        $existing->execute($ids);
        $found = array_map('strval', $existing->fetchAll(PDO::FETCH_COLUMN));
        if (count($found) !== count($ids)) {
            throw new InvalidArgumentException(stridebr_t('admin.feedback.invalid_ids', [], 'Um ou mais feedbacks não existem.'));
        }

        $params = array_merge([$status], $ids);
        $stmt = $pdo->prepare("UPDATE feedbacks SET status = ?, atualizado_em = NOW() WHERE idfeedback IN ({$placeholders})");
        $stmt->execute($params);
        $count = $stmt->rowCount();

        foreach ($ids as $id) {
            stridebr_admin_audit($pdo, $actorId, 'feedback.' . $action, 'feedback', $id, ['status' => $status]);
        }
        $pdo->commit();
        return $count;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function stridebr_admin_excerpt(string $text, int $limit = 180): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    if ($text === '' || stridebr_length($text) <= $limit) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return rtrim(mb_substr($text, 0, max(1, $limit - 1), 'UTF-8')) . '…';
    }
    return rtrim(substr($text, 0, max(1, $limit - 1))) . '…';
}
