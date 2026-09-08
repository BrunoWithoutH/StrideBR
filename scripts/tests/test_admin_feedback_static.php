<?php
$root = dirname(__DIR__, 2);
$admin = file_get_contents($root . '/src/includes/admin.php');
$feedback = file_get_contents($root . '/public/admin/feedback.php');
$feedbackJs = file_get_contents($root . '/public/assets/js/feedback-admin.js');
$feedbackCss = file_get_contents($root . '/public/assets/css/admin.css');
$header = file_get_contents($root . '/src/layout/header.php');
$export = file_get_contents($root . '/public/admin/feedback-export.php');
$users = file_get_contents($root . '/public/admin/users.php');
$migration = file_get_contents($root . '/src/database/migrations/20260815_alpha_readiness.sql');
$pt = file_get_contents($root . '/src/i18n/pt-BR.php');
$en = file_get_contents($root . '/src/i18n/en.php');
$scripts = file_get_contents($root . '/public/assets/js/scripts.js');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "Falhou: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($migration, "status IN ('novo', 'lendo', 'planejado', 'resolvido', 'arquivado')"), 'schema existente suporta workflow sem migration');
$assert(str_contains($admin, "'novo' => 'unread'") && str_contains($admin, "'resolvido' => 'resolved'"), 'estado operacional deriva do status existente');
$assert(str_contains($admin, "COUNT(*) FROM feedbacks WHERE status = 'novo'"), 'contador unread usa fonte de verdade do feedback');
$assert(str_contains($admin, 'FOR UPDATE'), 'bulk bloqueia registros na transação');
$assert(str_contains($admin, 'beginTransaction') && str_contains($admin, 'rollBack'), 'bulk é transacional');
$assert(str_contains($admin, 'count($ids) > 500'), 'bulk limita lote');
$assert(str_contains($feedback, "stridebr_require_role('moderator')"), 'feedback admin exige permissão no backend');
$assert(str_contains($feedback, 'stridebr_verify_csrf()'), 'mutações validam CSRF');
$assert(str_contains($feedback, "(\$unreadTotal > 0 ? 'unread' : 'all')"), 'filtro padrão prioriza unread quando existe');
$assert(str_contains($feedback, "ORDER BY f.criado_em DESC"), 'lista ordena mais recentes primeiro');
$assert(str_contains($feedback, 'LIMIT {$perPage} OFFSET {$offset}'), 'lista possui paginação server-side');
$assert(str_contains($feedback, "':q' . \$index") && str_contains($feedback, 'emailusuario'), 'busca server-side usa placeholders independentes');
$assert(str_contains($feedback, "\$action === 'mark_read_open'"), 'abrir deliberadamente pode marcar como lido');
$assert(str_contains($feedbackJs, 'selectAll.indeterminate'), 'Select all possui estado indeterminate');
$assert(str_contains($feedbackJs, 'optimisticRows.forEach(row => setRowState'), 'bulk atualiza UI otimisticamente');
$assert(str_contains($feedbackJs, 'snapshot.row.dataset.feedbackState = snapshot.state'), 'bulk possui rollback visual');
$assert(str_contains($feedbackJs, "workflow.value = state === 'unread' ? 'novo' : state === 'resolved' ? 'resolvido' : 'lendo'"), 'auto-read sincroniza workflow interno');
$assert(str_contains($feedbackCss, '.admin-feedback-bulk[hidden]{display:none!important}'), 'hidden da toolbar é estrutural');
$assert(str_contains($feedbackCss, 'env(safe-area-inset-bottom)'), 'bulk/detalhe mobile respeitam safe area');
$assert(str_contains($header, "if (stridebr_has_role('admin'))") && str_contains($header, 'stridebr_admin_feedback_unread_count'), 'resumo administrativo só é calculado para admin+');
$assert(str_contains($header, '/admin/feedback.php?state=unread'), 'resumo do sino aponta para Não lidos');
$assert(!str_contains($header, '$headerUnreadNotifications += $headerAdminUnreadFeedback'), 'feedback admin não incrementa badge pessoal');
$assert(str_contains($export, 'Cache-Control: private, no-store') && str_contains($export, 'X-Robots-Tag: noindex, nofollow, noarchive'), 'export não usa cache público nem indexação');
$assert(str_contains($users, 'idusuario::text ILIKE :q_id') && str_contains($users, 'admin-users-table') && str_contains($users, 'data-label="Cadastro"'), 'Users busca por ID e possui layout mobile estruturado');
$assert(str_contains($pt, "'admin.feedback.mark_unread'") && str_contains($en, "'admin.feedback.mark_unread'"), 'novos textos existem em pt-BR e en');
$assert(str_starts_with($scripts, "const stridebrCommonT") && str_contains(strtok($scripts, "\n"), ';'), 'helper global termina com ponto e vírgula antes do IIFE');

printf("✓ admin feedback static: %d assertions\n", $assertions);
