<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('moderator');

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';

$isAdmin = stridebr_has_role('admin');
$isOwner = stridebr_has_role('owner');
$errors = [];

$respondJson = static function (array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    $wantsJson = str_contains(stridebr_lower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || (string) ($_POST['response'] ?? '') === 'json';

    try {
        if ($action === 'bulk_state' || $action === 'mark_read_open') {
            $stateAction = $action === 'mark_read_open'
                ? 'mark_read'
                : trim((string) ($_POST['state_action'] ?? ''));
            $ids = $action === 'mark_read_open'
                ? [trim((string) ($_POST['idfeedback'] ?? ''))]
                : (array) ($_POST['ids'] ?? []);

            $count = stridebr_admin_feedback_apply_state($pdo, $idUsuario, $ids, $stateAction);
            [$oneKey, $otherKey] = match ($stateAction) {
                'mark_unread' => ['admin.feedback.toast_unread.one', 'admin.feedback.toast_unread.other'],
                'mark_resolved' => ['admin.feedback.toast_resolved.one', 'admin.feedback.toast_resolved.other'],
                default => ['admin.feedback.toast_read.one', 'admin.feedback.toast_read.other'],
            };
            $message = stridebr_tn($oneKey, $otherKey, $count, ['count' => $count]);

            if ($wantsJson) {
                $respondJson([
                    'ok' => true,
                    'updated' => $count,
                    'message' => $message,
                    'unread_count' => stridebr_admin_feedback_unread_count($pdo),
                ]);
            }
            stridebr_flash('success', $message);
        } elseif ($action === 'update_detail') {
            $id = trim((string) ($_POST['idfeedback'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'lendo'));
            $priority = trim((string) ($_POST['prioridade'] ?? 'normal'));
            $notes = trim((string) ($_POST['notas_admin'] ?? ''));

            if (preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id) !== 1
                || !in_array($status, ['novo', 'lendo', 'planejado', 'resolvido', 'arquivado'], true)
                || !in_array($priority, ['baixa', 'normal', 'alta'], true)) {
                throw new InvalidArgumentException(stridebr_t('admin.feedback.invalid_values'));
            }

            $stmt = $pdo->prepare('UPDATE feedbacks SET status=:status, prioridade=:priority, notas_admin=:notes, atualizado_em=NOW() WHERE idfeedback=:id');
            $stmt->execute([
                ':status' => $status,
                ':priority' => $priority,
                ':notes' => $notes !== '' ? $notes : null,
                ':id' => $id,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(stridebr_t('admin.feedback.not_found'));
            }
            stridebr_admin_audit($pdo, $idUsuario, 'feedback.update', 'feedback', $id, ['status' => $status, 'prioridade' => $priority]);
            stridebr_flash('success', stridebr_t('admin.feedback.updated'));
        } else {
            throw new InvalidArgumentException(stridebr_t('admin.feedback.invalid_action'));
        }

        $redirect = stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), '/admin/feedback.php');
        header('Location: ' . $redirect, true, 303);
        exit;
    } catch (Throwable $e) {
        $message = $e instanceof RuntimeException || $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : stridebr_t('admin.feedback.update_failed');
        if ($wantsJson) {
            $respondJson(['ok' => false, 'error' => $message], 422);
        }
        $errors[] = $message;
    }
}

$unreadTotal = stridebr_admin_feedback_unread_count($pdo);
$stateRequested = trim((string) ($_GET['state'] ?? ''));
$state = in_array($stateRequested, ['unread', 'read', 'resolved', 'all'], true)
    ? $stateRequested
    : ($unreadTotal > 0 ? 'unread' : 'all');
$q = trim((string) ($_GET['q'] ?? ''));
$type = trim((string) ($_GET['type'] ?? 'all'));
$author = trim((string) ($_GET['author'] ?? 'all'));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;
$viewId = trim((string) ($_GET['view'] ?? ''));

$typeValues = ['bug', 'ux', 'ideia', 'elogio', 'outro'];
if (!in_array($type, array_merge(['all'], $typeValues), true)) {
    $type = 'all';
}
if (!in_array($author, ['all', 'identified', 'anonymous'], true)) {
    $author = 'all';
}

$baseWhere = ['1=1'];
$baseParams = [];
if ($q !== '') {
    $needle = '%' . $q . '%';
    $searchFields = [
        'f.idfeedback',
        'f.titulo',
        'f.mensagem',
        "COALESCE(f.pagina,'')",
        "COALESCE(u.nome_exibicao,u.nomeusuario,'')",
        "COALESCE(u.username,'')",
    ];
    if ($isAdmin) {
        $searchFields[] = "COALESCE(u.emailusuario,'')";
    }
    $searchParts = [];
    foreach ($searchFields as $index => $field) {
        $placeholder = ':q' . $index;
        $searchParts[] = $field . ' ILIKE ' . $placeholder;
        $baseParams[$placeholder] = $needle;
    }
    $baseWhere[] = '(' . implode(' OR ', $searchParts) . ')';
}
if ($type !== 'all') {
    $baseWhere[] = 'f.tipo = :type';
    $baseParams[':type'] = $type;
}
if ($author === 'anonymous') {
    $baseWhere[] = 'f.anonimo = TRUE';
} elseif ($author === 'identified') {
    $baseWhere[] = 'f.anonimo = FALSE';
}

$stateSql = static function (string $value): string {
    return match ($value) {
        'unread' => "f.status = 'novo'",
        'resolved' => "f.status = 'resolvido'",
        'read' => "f.status NOT IN ('novo','resolvido')",
        default => '1=1',
    };
};

$fromSql = ' FROM feedbacks f LEFT JOIN usuarios u ON u.idusuario = f.idusuario ';
$baseWhereSql = implode(' AND ', $baseWhere);
$countSql = "SELECT COUNT(*) FILTER (WHERE f.status='novo') AS unread,
                    COUNT(*) FILTER (WHERE f.status='resolvido') AS resolved,
                    COUNT(*) FILTER (WHERE f.status NOT IN ('novo','resolvido')) AS read,
                    COUNT(*) AS total
               {$fromSql}
              WHERE {$baseWhereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($baseParams);
$counts = $countStmt->fetch() ?: ['unread' => 0, 'read' => 0, 'resolved' => 0, 'total' => 0];
foreach ($counts as $key => $value) {
    $counts[$key] = (int) $value;
}

$whereSql = $baseWhereSql . ' AND ' . $stateSql($state);
$totalStmt = $pdo->prepare("SELECT COUNT(*) {$fromSql} WHERE {$whereSql}");
$totalStmt->execute($baseParams);
$total = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = "SELECT f.idfeedback,f.idusuario,f.anonimo,f.tipo,f.titulo,f.mensagem,f.pagina,f.status,f.prioridade,f.criado_em,f.atualizado_em,
                   COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario,'Usuário removido') AS nome_exibicao,u.username
              {$fromSql}
             WHERE {$whereSql}
             ORDER BY f.criado_em DESC
             LIMIT {$perPage} OFFSET {$offset}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($baseParams);
$rows = $listStmt->fetchAll();

$typeLabels = [
    'bug' => stridebr_t('admin.feedback.type_bug'),
    'ux' => stridebr_t('admin.feedback.type_ux'),
    'ideia' => stridebr_t('admin.feedback.type_idea'),
    'elogio' => stridebr_t('admin.feedback.type_praise'),
    'outro' => stridebr_t('admin.feedback.type_other'),
];
$priorityLabels = [
    'alta' => stridebr_t('admin.feedback.priority_high'),
    'normal' => stridebr_t('admin.feedback.priority_normal'),
    'baixa' => stridebr_t('admin.feedback.priority_low'),
];
$workflowLabels = [
    'novo' => stridebr_t('admin.feedback.workflow_new'),
    'lendo' => stridebr_t('admin.feedback.workflow_reading'),
    'planejado' => stridebr_t('admin.feedback.workflow_planned'),
    'resolvido' => stridebr_t('admin.feedback.workflow_resolved'),
    'arquivado' => stridebr_t('admin.feedback.workflow_archived'),
];
$stateLabels = [
    'unread' => stridebr_t('admin.feedback.unread'),
    'all' => stridebr_t('admin.feedback.all'),
    'read' => stridebr_t('admin.feedback.read'),
    'resolved' => stridebr_t('admin.feedback.resolved'),
];
$itemStateLabels = [
    'unread' => stridebr_t('admin.feedback.state_unread'),
    'read' => stridebr_t('admin.feedback.state_read'),
    'resolved' => stridebr_t('admin.feedback.state_resolved'),
];

$buildUrl = static function (array $replace = []) use ($state, $q, $type, $author, $page): string {
    $params = ['state' => $state, 'q' => $q, 'type' => $type, 'author' => $author, 'page' => $page];
    foreach ($replace as $key => $value) {
        if ($value === null || $value === '' || ($value === 'all' && in_array($key, ['type', 'author'], true))) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    if (($params['q'] ?? '') === '') {
        unset($params['q']);
    }
    if (($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }
    $query = http_build_query($params);
    return '/admin/feedback.php' . ($query !== '' ? '?' . $query : '');
};

$detail = null;
$detailNav = ['previous' => null, 'next' => null];
if ($viewId !== '' && preg_match('/^[A-Za-z0-9_-]{6,32}$/', $viewId) === 1) {
    $detailStmt = $pdo->prepare("SELECT f.*, COALESCE(NULLIF(u.nome_exibicao,''),u.nomeusuario,'Usuário removido') AS nome_exibicao,u.username,u.emailusuario {$fromSql} WHERE f.idfeedback=:view LIMIT 1");
    $detailStmt->execute([':view' => $viewId]);
    $detail = $detailStmt->fetch() ?: null;

    if ($detail !== null) {
        $navSql = "WITH filtered AS (
                       SELECT f.idfeedback,
                              lag(f.idfeedback) OVER (ORDER BY f.criado_em DESC) AS previous_id,
                              lead(f.idfeedback) OVER (ORDER BY f.criado_em DESC) AS next_id
                         {$fromSql}
                        WHERE {$whereSql}
                   ) SELECT previous_id,next_id FROM filtered WHERE idfeedback=:view";
        $navParams = $baseParams;
        $navParams[':view'] = $viewId;
        $navStmt = $pdo->prepare($navSql);
        $navStmt->execute($navParams);
        $nav = $navStmt->fetch();
        if ($nav) {
            $detailNav['previous'] = $nav['previous_id'] ?: null;
            $detailNav['next'] = $nav['next_id'] ?: null;
        }
    }
}

$returnTo = $buildUrl();
$flashes = stridebr_take_flashes();
?>
<!doctype html>
<html lang="<?php echo stridebr_e(stridebr_html_lang()); ?>">
<head>
    <?php echo stridebr_ui_boot_script(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/admin.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('admin.feedback.page_title')); ?></title>
</head>
<body class="admin-body">
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="admin-shell" data-feedback-admin data-current-state="<?php echo stridebr_e($state); ?>" data-csrf="<?php echo stridebr_e(stridebr_csrf_token()); ?>">
    <?php echo stridebr_admin_nav('feedback'); ?>
    <div class="admin-heading">
        <div><span class="eyebrow">Administração</span><h1><?php echo stridebr_e(stridebr_t('admin.feedback.title')); ?></h1></div>
        <a class="secondary-action compact" href="/admin/feedback-export.php"><?php echo stridebr_e(stridebr_t('admin.feedback.export')); ?></a>
    </div>

    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

    <section class="admin-feedback-filters" aria-label="<?php echo stridebr_e(stridebr_t('admin.feedback.filters')); ?>">
        <nav class="admin-feedback-state-tabs" aria-label="<?php echo stridebr_e(stridebr_t('admin.feedback.status_filter')); ?>">
            <?php foreach (['unread', 'all', 'read', 'resolved'] as $tab): ?>
                <a href="<?php echo stridebr_e($buildUrl(['state' => $tab, 'page' => 1, 'view' => null])); ?>" class="<?php echo $tab === $state ? 'is-active' : ''; ?>"<?php echo $tab === $state ? ' aria-current="page"' : ''; ?> data-state-tab="<?php echo stridebr_e($tab); ?>">
                    <span><?php echo stridebr_e($stateLabels[$tab]); ?></span><b data-state-count="<?php echo stridebr_e($tab); ?>"><?php echo (int) $counts[$tab === 'all' ? 'total' : $tab]; ?></b>
                </a>
            <?php endforeach; ?>
        </nav>

        <form class="admin-feedback-search-form" method="get">
            <input type="hidden" name="state" value="<?php echo stridebr_e($state); ?>">
            <label><span><?php echo stridebr_e(stridebr_t('admin.feedback.search')); ?></span><input name="q" type="search" value="<?php echo stridebr_e($q); ?>" placeholder="<?php echo stridebr_e(stridebr_t('admin.feedback.search_placeholder')); ?>"></label>
            <label><span><?php echo stridebr_e(stridebr_t('admin.feedback.type')); ?></span><select name="type"><option value="all"><?php echo stridebr_e(stridebr_t('admin.feedback.all_types')); ?></option><?php foreach ($typeLabels as $value => $label): ?><option value="<?php echo stridebr_e($value); ?>"<?php echo $type === $value ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?></select></label>
            <label><span><?php echo stridebr_e(stridebr_t('admin.feedback.author')); ?></span><select name="author"><option value="all"><?php echo stridebr_e(stridebr_t('admin.feedback.all_authors')); ?></option><option value="identified"<?php echo $author === 'identified' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('admin.feedback.identified')); ?></option><option value="anonymous"<?php echo $author === 'anonymous' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('admin.feedback.anonymous')); ?></option></select></label>
            <button class="secondary-action compact" type="submit"><?php echo stridebr_e(stridebr_t('admin.feedback.apply_filters')); ?></button>
            <?php if ($q !== '' || $type !== 'all' || $author !== 'all'): ?><a class="ghost-action compact" href="/admin/feedback.php?state=<?php echo rawurlencode($state); ?>"><?php echo stridebr_e(stridebr_t('admin.feedback.clear')); ?></a><?php endif; ?>
        </form>
    </section>

    <section class="admin-feedback-list-panel">
        <div class="admin-feedback-list-head">
            <label class="admin-feedback-select-all"><input type="checkbox" data-feedback-select-all><span><?php echo stridebr_e(stridebr_t('admin.feedback.select_page')); ?></span></label>
            <span><?php echo stridebr_e(stridebr_tn('admin.feedback.results.one', 'admin.feedback.results.other', $total, ['count' => $total])); ?></span>
        </div>

        <div class="admin-feedback-bulk" hidden data-feedback-bulk>
            <strong data-feedback-selection-count>0</strong><span><?php echo stridebr_e(stridebr_t('admin.feedback.selected')); ?></span>
            <div>
                <button type="button" class="secondary-action compact" data-feedback-bulk-action="mark_read"><?php echo stridebr_e(stridebr_t('admin.feedback.mark_read')); ?></button>
                <button type="button" class="secondary-action compact" data-feedback-bulk-action="mark_unread"><?php echo stridebr_e(stridebr_t('admin.feedback.mark_unread')); ?></button>
                <button type="button" class="secondary-action compact" data-feedback-bulk-action="mark_resolved"><?php echo stridebr_e(stridebr_t('admin.feedback.mark_resolved')); ?></button>
            </div>
        </div>

        <?php if ($rows === []): ?>
            <div class="admin-feedback-empty"><strong><?php echo stridebr_e($state === 'unread' ? stridebr_t('admin.feedback.empty_unread') : ($state === 'all' ? stridebr_t('admin.feedback.empty_all') : stridebr_t('admin.feedback.empty_filtered'))); ?></strong><?php if ($state !== 'unread' && $state !== 'all'): ?><span><?php echo stridebr_e(stridebr_t('admin.feedback.empty_filtered')); ?></span><?php endif; ?></div>
        <?php else: ?>
            <div class="admin-feedback-table">
                <?php foreach ($rows as $feedback): $semantic = stridebr_admin_feedback_state((string) $feedback['status']); ?>
                    <article class="admin-feedback-row is-<?php echo stridebr_e($semantic); ?>" data-feedback-row data-feedback-id="<?php echo stridebr_e($feedback['idfeedback']); ?>" data-feedback-state="<?php echo stridebr_e($semantic); ?>">
                        <div class="admin-feedback-check"><input type="checkbox" data-feedback-select value="<?php echo stridebr_e($feedback['idfeedback']); ?>" aria-label="<?php echo stridebr_e(stridebr_t('admin.feedback.select_item', ['title' => (string) $feedback['titulo']])); ?>"></div>
                        <div class="admin-feedback-main">
                            <div class="admin-feedback-line1"><span class="status-pill feedback-state-<?php echo stridebr_e($semantic); ?>" data-feedback-state-label><?php echo stridebr_e($itemStateLabels[$semantic] ?? $stateLabels[$semantic]); ?></span><span class="status-pill feedback-type-pill feedback-type-<?php echo stridebr_e($feedback['tipo']); ?>"><?php echo stridebr_e($typeLabels[$feedback['tipo']] ?? $feedback['tipo']); ?></span><strong><?php echo stridebr_e($feedback['titulo']); ?></strong></div>
                            <p><?php echo stridebr_e(stridebr_admin_excerpt((string) $feedback['mensagem'], 180)); ?></p>
                            <div class="admin-feedback-meta">
                                <span><?php echo stridebr_db_bool($feedback['anonimo']) ? stridebr_e(stridebr_t('admin.feedback.anonymous')) : stridebr_e(stridebr_person_name_for_display((string) $feedback['nome_exibicao'], (string) ($feedback['username'] ?? ''), stridebr_t('common.user'), 60)); ?></span>
                                <?php if (!stridebr_db_bool($feedback['anonimo']) && $feedback['username']): ?><span>@<?php echo stridebr_e($feedback['username']); ?></span><?php endif; ?>
                                <time datetime="<?php echo stridebr_e((string) $feedback['criado_em']); ?>" title="<?php echo stridebr_e(stridebr_format_datetime_short((string) $feedback['criado_em'])); ?>"><?php echo stridebr_e(stridebr_format_datetime_short((string) $feedback['criado_em'])); ?></time>
                                <?php if (trim((string) ($feedback['pagina'] ?? '')) !== ''): ?><span class="admin-feedback-origin"><?php echo stridebr_e((string) $feedback['pagina']); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="admin-feedback-row-side"><span class="priority-pill priority-<?php echo stridebr_e($feedback['prioridade']); ?>"><?php echo stridebr_e($priorityLabels[$feedback['prioridade']] ?? $feedback['prioridade']); ?></span><a class="secondary-action compact" href="<?php echo stridebr_e($buildUrl(['view' => $feedback['idfeedback']])); ?>"><?php echo stridebr_e(stridebr_t('admin.feedback.open')); ?></a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?><nav class="admin-pagination" aria-label="<?php echo stridebr_e(stridebr_t('admin.feedback.pagination')); ?>"><?php if ($page > 1): ?><a class="secondary-action compact" href="<?php echo stridebr_e($buildUrl(['page' => $page - 1])); ?>">← <?php echo stridebr_e(stridebr_t('common.previous', [], 'Anterior')); ?></a><?php endif; ?><span><?php echo stridebr_e(stridebr_t('admin.feedback.page_of', ['page' => $page, 'pages' => $totalPages])); ?></span><?php if ($page < $totalPages): ?><a class="secondary-action compact" href="<?php echo stridebr_e($buildUrl(['page' => $page + 1])); ?>"><?php echo stridebr_e(stridebr_t('common.next', [], 'Próxima')); ?> →</a><?php endif; ?></nav><?php endif; ?>
    </section>

    <?php if ($detail !== null): $detailSemantic = stridebr_admin_feedback_state((string) $detail['status']); ?>
        <section class="admin-feedback-detail-backdrop" data-feedback-detail>
            <article class="admin-feedback-detail" role="dialog" aria-modal="true" aria-labelledby="feedback-detail-title" data-feedback-open-id="<?php echo stridebr_e($detail['idfeedback']); ?>" data-feedback-open-state="<?php echo stridebr_e($detailSemantic); ?>">
                <header class="admin-feedback-detail-head"><a class="context-back-button" href="<?php echo stridebr_e($returnTo); ?>">← <?php echo stridebr_e(stridebr_t('admin.feedback.back')); ?></a><div class="admin-feedback-detail-nav"><?php if ($detailNav['previous']): ?><a href="<?php echo stridebr_e($buildUrl(['view' => $detailNav['previous']])); ?>">← <?php echo stridebr_e(stridebr_t('common.previous', [], 'Anterior')); ?></a><?php endif; ?><?php if ($detailNav['next']): ?><a href="<?php echo stridebr_e($buildUrl(['view' => $detailNav['next']])); ?>"><?php echo stridebr_e(stridebr_t('common.next', [], 'Próxima')); ?> →</a><?php endif; ?></div></header>
                <div class="admin-feedback-detail-content">
                    <div class="admin-feedback-detail-primary">
                        <div class="admin-feedback-detail-status"><span class="status-pill feedback-state-<?php echo stridebr_e($detailSemantic); ?>" data-feedback-detail-state><?php echo stridebr_e($itemStateLabels[$detailSemantic] ?? $stateLabels[$detailSemantic]); ?></span><span class="status-pill feedback-type-pill feedback-type-<?php echo stridebr_e($detail['tipo']); ?>"><?php echo stridebr_e($typeLabels[$detail['tipo']] ?? $detail['tipo']); ?></span><span class="priority-pill priority-<?php echo stridebr_e($detail['prioridade']); ?>"><?php echo stridebr_e($priorityLabels[$detail['prioridade']] ?? $detail['prioridade']); ?></span></div>
                        <h2 id="feedback-detail-title"><?php echo stridebr_e($detail['titulo']); ?></h2>
                        <div class="admin-feedback-detail-author"><strong><?php echo stridebr_db_bool($detail['anonimo']) ? stridebr_e(stridebr_t('admin.feedback.anonymous')) : stridebr_e(stridebr_person_name_for_display((string) $detail['nome_exibicao'], (string) ($detail['username'] ?? ''), stridebr_t('common.user'), 60)); ?></strong><?php if (!stridebr_db_bool($detail['anonimo']) && $detail['username']): ?><span>@<?php echo stridebr_e($detail['username']); ?></span><?php endif; ?><time><?php echo stridebr_e(stridebr_format_datetime_short((string) $detail['criado_em'])); ?></time></div>
                        <div class="admin-feedback-full-message"><?php echo nl2br(stridebr_e((string) $detail['mensagem'])); ?></div>
                    </div>
                    <aside class="admin-feedback-detail-meta">
                        <?php if (trim((string) ($detail['pagina'] ?? '')) !== ''): ?><div><span><?php echo stridebr_e(stridebr_t('admin.feedback.origin')); ?></span><strong><?php echo stridebr_e((string) $detail['pagina']); ?></strong></div><?php endif; ?>
                        <div><span><?php echo stridebr_e(stridebr_t('admin.feedback.received_at')); ?></span><strong><?php echo stridebr_e(stridebr_format_datetime_short((string) $detail['criado_em'])); ?></strong></div>
                        <div><span><?php echo stridebr_e(stridebr_t('admin.feedback.updated_at')); ?></span><strong><?php echo stridebr_e(stridebr_format_datetime_short((string) $detail['atualizado_em'])); ?></strong></div>
                        <?php if ($isAdmin && trim((string) ($detail['user_agent'] ?? '')) !== ''): ?><details><summary><?php echo stridebr_e(stridebr_t('admin.feedback.technical_context')); ?></summary><code><?php echo stridebr_e((string) $detail['user_agent']); ?></code><?php if ($isOwner && $detail['ip'] !== null): ?><code><?php echo stridebr_e((string) $detail['ip']); ?></code><?php endif; ?></details><?php endif; ?>
                    </aside>
                </div>
                <div class="admin-feedback-detail-actions"><button type="button" class="secondary-action compact" data-feedback-single-action="mark_read"><?php echo stridebr_e(stridebr_t('admin.feedback.mark_read')); ?></button><button type="button" class="secondary-action compact" data-feedback-single-action="mark_unread"><?php echo stridebr_e(stridebr_t('admin.feedback.mark_unread')); ?></button><button type="button" class="secondary-action compact" data-feedback-single-action="mark_resolved"><?php echo stridebr_e(stridebr_t('admin.feedback.mark_resolved')); ?></button></div>
                <form method="post" class="admin-feedback-detail-form">
                    <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="update_detail"><input type="hidden" name="idfeedback" value="<?php echo stridebr_e($detail['idfeedback']); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($buildUrl(['view' => $detail['idfeedback']])); ?>">
                    <label><?php echo stridebr_e(stridebr_t('admin.feedback.priority')); ?><select name="prioridade"><?php foreach ($priorityLabels as $value => $label): ?><option value="<?php echo stridebr_e($value); ?>"<?php echo $detail['prioridade'] === $value ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?></select></label>
                    <label class="admin-feedback-notes"><?php echo stridebr_e(stridebr_t('admin.feedback.internal_notes')); ?><textarea name="notas_admin" rows="3" maxlength="5000"><?php echo stridebr_e((string) ($detail['notas_admin'] ?? '')); ?></textarea></label>
                    <details class="admin-feedback-workflow"><summary><?php echo stridebr_e(stridebr_t('admin.feedback.advanced_workflow')); ?></summary><label><?php echo stridebr_e(stridebr_t('admin.feedback.workflow_status')); ?><select name="status"><?php foreach ($workflowLabels as $value => $label): ?><option value="<?php echo stridebr_e($value); ?>"<?php echo $detail['status'] === $value ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?></select></label></details>
                    <button class="primary-action" type="submit"><?php echo stridebr_e(stridebr_t('admin.feedback.save')); ?></button>
                </form>
            </article>
        </section>
    <?php endif; ?>
</div></main></div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/feedback-admin.js')); ?>" defer></script>
</body></html>
