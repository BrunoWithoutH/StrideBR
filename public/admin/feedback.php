<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('moderator');

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();

    try {
        $id = trim((string) ($_POST['idfeedback'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'novo');
        $priority = (string) ($_POST['prioridade'] ?? 'normal');
        $notes = trim((string) ($_POST['notas_admin'] ?? ''));

        if (!in_array($status, ['novo', 'lendo', 'planejado', 'resolvido', 'arquivado'], true)
            || !in_array($priority, ['baixa', 'normal', 'alta'], true)) {
            throw new InvalidArgumentException('Valores inválidos.');
        }

        $stmt = $pdo->prepare(
            'UPDATE feedbacks
                SET status = :status,
                    prioridade = :prioridade,
                    notas_admin = :notas,
                    atualizado_em = NOW()
              WHERE idfeedback = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':prioridade' => $priority,
            ':notas' => $notes !== '' ? $notes : null,
            ':id' => $id,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Feedback não encontrado.');
        }

        stridebr_admin_audit(
            $pdo,
            $idUsuario,
            'feedback.update',
            'feedback',
            $id,
            ['status' => $status, 'prioridade' => $priority]
        );

        stridebr_flash('success', 'Feedback atualizado.');
        header('Location: /admin/feedback.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof RuntimeException || $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Não foi possível atualizar.';
    }
}

$stmt = $pdo->prepare(
    "SELECT f.*,
            CASE
                WHEN f.anonimo THEN 'Anônimo'
                ELSE COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario, 'Usuário removido')
            END AS nome_exibicao,
            CASE WHEN f.anonimo THEN NULL ELSE u.username END AS username,
            CASE WHEN f.anonimo THEN FALSE ELSE f.idusuario = :current_user END AS enviado_por_mim
       FROM feedbacks f
       LEFT JOIN usuarios u ON u.idusuario = f.idusuario
      ORDER BY CASE f.prioridade WHEN 'alta' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,
               f.criado_em DESC
      LIMIT 500"
);
$stmt->execute([':current_user' => $idUsuario]);
$rows = $stmt->fetchAll();

$typeLabels = [
    'bug' => 'Bugs',
    'ux' => 'Interface / UX',
    'ideia' => 'Ideias',
    'elogio' => 'Elogios',
    'outro' => 'Outros',
];
$typeBadgeLabels = [
    'bug' => 'Bug',
    'ux' => 'Interface / UX',
    'ideia' => 'Ideia',
    'elogio' => 'Elogio',
    'outro' => 'Outro',
];
$statusLabels = [
    'novo' => 'Novos',
    'lendo' => 'Lendo',
    'planejado' => 'Planejados',
    'resolvido' => 'Resolvidos',
    'arquivado' => 'Arquivados',
];
$statusBadgeLabels = [
    'novo' => 'Novo',
    'lendo' => 'Lendo',
    'planejado' => 'Planejado',
    'resolvido' => 'Resolvido',
    'arquivado' => 'Arquivado',
];
$priorityLabels = [
    'alta' => 'Alta',
    'normal' => 'Normal',
    'baixa' => 'Baixa',
];
$typeCounts = array_fill_keys(array_keys($typeLabels), 0);
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
$priorityCounts = array_fill_keys(array_keys($priorityLabels), 0);

foreach ($rows as $feedback) {
    $type = (string) ($feedback['tipo'] ?? 'outro');
    $status = (string) ($feedback['status'] ?? 'novo');
    $priority = (string) ($feedback['prioridade'] ?? 'normal');
    if (array_key_exists($type, $typeCounts)) {
        $typeCounts[$type]++;
    }
    if (array_key_exists($status, $statusCounts)) {
        $statusCounts[$status]++;
    }
    if (array_key_exists($priority, $priorityCounts)) {
        $priorityCounts[$priority]++;
    }
}

$flashes = stridebr_take_flashes();
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Feedback | StrideBR Admin</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="admin-body">
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="admin-shell" data-feedback-admin>
            <?php echo stridebr_admin_nav('feedback'); ?>

            <div class="admin-heading feedback-admin-heading">
                <div>
                    <span class="eyebrow">Feedback</span>
                    <h1>Feedback</h1>
                    <p>Fila de bugs, ideias e observações dos testers.</p>
                </div>
                <a class="feedback-export-action" href="/admin/feedback-export.php">Exportar feedbacks</a>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type']); ?>"><?php echo stridebr_e($flash['message']); ?></div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <section class="feedback-filter-panel" aria-label="Filtros de feedback">
                <div class="feedback-filter-toolbar">
                    <label class="feedback-search-field">
                        <span>Buscar</span>
                        <input type="search" placeholder="Título, texto, usuário ou contexto" autocomplete="off" data-feedback-search>
                    </label>
                    <label class="feedback-priority-filter">
                        <span>Prioridade</span>
                        <select data-feedback-priority>
                            <option value="all">Todas (<?php echo count($rows); ?>)</option>
                            <?php foreach ($priorityLabels as $value => $label): ?>
                                <option value="<?php echo stridebr_e($value); ?>"><?php echo stridebr_e($label); ?> (<?php echo $priorityCounts[$value]; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="feedback-reset-filter" type="button" data-feedback-reset>Limpar</button>
                </div>

                <div class="feedback-filter-group" data-feedback-filter-group="type">
                    <span class="feedback-filter-label">Tipo</span>
                    <div class="feedback-filter-chips">
                        <button type="button" class="feedback-filter-chip is-active" data-feedback-filter="type" data-feedback-value="all" aria-pressed="true">Todos <b><?php echo count($rows); ?></b></button>
                        <?php foreach ($typeLabels as $value => $label): ?>
                            <button type="button" class="feedback-filter-chip feedback-type-<?php echo stridebr_e($value); ?>" data-feedback-filter="type" data-feedback-value="<?php echo stridebr_e($value); ?>" aria-pressed="false"><?php echo stridebr_e($label); ?> <b><?php echo $typeCounts[$value]; ?></b></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="feedback-filter-group" data-feedback-filter-group="status">
                    <span class="feedback-filter-label">Status</span>
                    <div class="feedback-filter-chips">
                        <button type="button" class="feedback-filter-chip is-active" data-feedback-filter="status" data-feedback-value="all" aria-pressed="true">Todos <b><?php echo count($rows); ?></b></button>
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <button type="button" class="feedback-filter-chip" data-feedback-filter="status" data-feedback-value="<?php echo stridebr_e($value); ?>" aria-pressed="false"><?php echo stridebr_e($label); ?> <b><?php echo $statusCounts[$value]; ?></b></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="feedback-filter-result" aria-live="polite">
                    Mostrando <strong data-feedback-visible-count><?php echo count($rows); ?></strong> de <?php echo count($rows); ?> feedbacks
                </div>
            </section>

            <div class="feedback-admin-list">
                <?php if ($rows === []): ?>
                    <section class="content-card"><p>Nenhum feedback recebido ainda.</p></section>
                <?php endif; ?>

                <section class="content-card feedback-filter-empty" hidden data-feedback-empty>
                    <strong>Nenhum feedback encontrado.</strong>
                    <p>Tente mudar as tags ou limpar a busca.</p>
                </section>

                <?php foreach ($rows as $feedback): ?>
                    <?php
                    $authorSearch = implode(' ', array_filter([
                        (string) ($feedback['nome_exibicao'] ?? ''),
                        (string) ($feedback['username'] ?? ''),
                        (string) ($feedback['titulo'] ?? ''),
                        (string) ($feedback['mensagem'] ?? ''),
                        (string) ($feedback['pagina'] ?? ''),
                    ]));
                    ?>
                    <article
                        class="admin-card feedback-admin-card"
                        data-feedback-card
                        data-feedback-type="<?php echo stridebr_e($feedback['tipo']); ?>"
                        data-feedback-status="<?php echo stridebr_e($feedback['status']); ?>"
                        data-feedback-priority="<?php echo stridebr_e($feedback['prioridade']); ?>"
                        data-feedback-search-text="<?php echo stridebr_e($authorSearch); ?>"
                    >
                        <div class="feedback-admin-top">
                            <div>
                                <div class="feedback-admin-badges">
                                    <span class="status-pill feedback-type-pill feedback-type-<?php echo stridebr_e($feedback['tipo']); ?>"><?php echo stridebr_e($typeBadgeLabels[$feedback['tipo']] ?? $feedback['tipo']); ?></span>
                                    <span class="status-pill"><?php echo stridebr_e($statusBadgeLabels[$feedback['status']] ?? $feedback['status']); ?></span>
                                    <?php if (stridebr_db_bool($feedback['anonimo'] ?? false)): ?>
                                        <span class="status-pill">anônimo</span>
                                    <?php elseif (stridebr_db_bool($feedback['enviado_por_mim'] ?? false)): ?>
                                        <span class="status-pill feedback-mine-pill">você</span>
                                    <?php endif; ?>
                                </div>
                                <h2><?php echo stridebr_e($feedback['titulo']); ?></h2>
                                <small>
                                    <?php echo stridebr_e(stridebr_person_name_for_display((string)$feedback['nome_exibicao'], (string)($feedback['username'] ?? ''), 'Usuário', 60)); ?>
                                    <?php echo $feedback['username'] ? ' · @' . stridebr_e($feedback['username']) : ''; ?>
                                    · <?php echo stridebr_e($feedback['criado_em']); ?>
                                </small>
                            </div>
                            <span class="priority-pill priority-<?php echo stridebr_e($feedback['prioridade']); ?>"><?php echo stridebr_e($priorityLabels[$feedback['prioridade']] ?? $feedback['prioridade']); ?></span>
                        </div>

                        <p class="feedback-message"><?php echo nl2br(stridebr_e($feedback['mensagem'])); ?></p>

                        <?php if ($feedback['pagina']): ?>
                            <p class="feedback-context"><strong>Contexto:</strong> <?php echo stridebr_e($feedback['pagina']); ?></p>
                        <?php endif; ?>

                        <form method="post" class="feedback-admin-form">
                            <?php echo stridebr_csrf_field(); ?>
                            <input type="hidden" name="idfeedback" value="<?php echo stridebr_e($feedback['idfeedback']); ?>">

                            <label>Status
                                <select name="status">
                                    <?php foreach ($statusBadgeLabels as $status => $label): ?>
                                        <option value="<?php echo $status; ?>"<?php echo $feedback['status'] === $status ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>Prioridade
                                <select name="prioridade">
                                    <?php foreach ($priorityLabels as $priority => $label): ?>
                                        <option value="<?php echo $priority; ?>"<?php echo $feedback['prioridade'] === $priority ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="feedback-notes">Notas internas
                                <textarea name="notas_admin" rows="2" maxlength="5000"><?php echo stridebr_e($feedback['notas_admin'] ?? ''); ?></textarea>
                            </label>

                            <button class="primary-action">Salvar</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/feedback-admin.js')); ?>" defer></script>
</body>
</html>
