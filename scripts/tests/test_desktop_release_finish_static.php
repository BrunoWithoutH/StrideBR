<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$migration = $read('src/database/migrations/20260903_v1_rc.sql');
$model = $read('src/function/atividade_modelo.php');
$presenter = $read('src/function/atividade_presenter.php');
$dashboard = $read('src/function/dashboard.php');
$insights = $read('src/function/activity_insights.php');
$cronograma = $read('src/function/cronograma.php');
$exchange = $read('src/function/activity_file_exchange.php');
$accountData = $read('src/function/account_data.php');
$trainer = $read('public/user/treinador.php');
$settings = $read('public/user/settings.php');
$activitiesPage = $read('public/user/atividades.php');
$exchangePage = $read('public/user/importar-exportar.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$activityDelete = $read('src/function/apagaratividade.php');
$exerciseLibrary = $read('public/user/biblioteca.php');
$exerciseModelJs = $read('public/assets/js/exercicios-modelo.js');
$exerciseDraftJs = $read('public/assets/js/exercicios-rascunho.js');
$trainerJs = $read('public/assets/js/trainer.js');
$scriptsJs = $read('public/assets/js/scripts.js');
$cronogramasJs = $read('public/assets/js/cronogramas.js');
$agendaJs = $read('public/assets/js/agenda-mensal.js');
$notifications = $read('src/function/notificacoes.php');
$cronogramasPage = $read('public/user/cronogramatreinos.php');
$exerciseSchedule = $read('public/user/exercicioscronograma.php');
$occurrenceApi = $read('public/api/cronograma-ocorrencias.php');
$quickCreateApi = $read('public/api/cronograma-quick-create.php');
$analytics = $read('src/function/product_analytics.php');
$progress = $read('public/user/progresso.php');
$compare = $read('public/user/comparar-atividades.php');
$header = $read('src/layout/header.php');
$footer = $read('src/layout/footer.php');
$landing = $read('public/index.php');
$terms = $read('public/pages/legal/terms.php');
$privacy = $read('public/pages/legal/privacy.php');
$cookies = $read('public/pages/legal/cookies.php');
$faq = $read('public/pages/help/faq.php');
$roadmap = $read('public/pages/extras/roadmap.php');
$changelog = $read('public/pages/extras/changelog.php');
$about = $read('public/pages/about/about.php');
$supportProject = $read('public/pages/about/support-project.php');
$env = $read('.env.example');

$checks = [
    'migration soft delete e privacidade de rota' => str_contains($migration, 'excluido_em TIMESTAMPTZ') && str_contains($migration, 'ocultar_inicio_m INTEGER') && str_contains($migration, 'ocultar_fim_m INTEGER'),
    'migration notificações analytics e sync' => str_contains($migration, 'CREATE TABLE IF NOT EXISTS notificacoes') && str_contains($migration, 'CREATE TABLE IF NOT EXISTS eventos_produto') && str_contains($migration, 'synced_schedules.enabled'),
    'atividade usa soft delete e restauração de 30 minutos' => str_contains($model, 'SET excluido_em = NOW()') && str_contains($model, "NOW() - INTERVAL '30 minutes'"),
    'atividade ownership ignora excluídas' => str_contains($model, 'idusuario = :usuario AND excluido_em IS NULL'),
    'apresentação de atividades ignora excluídas' => str_contains($presenter, 'ra.excluido_em IS NULL'),
    'dashboard ignora atividades excluídas' => substr_count($dashboard, 'excluido_em IS NULL') >= 6,
    'insights ignoram atividades excluídas' => substr_count($insights, 'excluido_em IS NULL') >= 5,
    'cronograma ignora atividades excluídas na conciliação e histórico' => str_contains($cronograma, 'idtreino_cronograma = :id AND excluido_em IS NULL') && str_contains($cronograma, 'AND excluido_em IS NULL') && str_contains($cronograma, 'AND idtreino_cronograma IS NOT NULL'),
    'importação ignora atividades excluídas' => substr_count($exchange, 'excluido_em IS NULL') >= 3,
    'exportação completa da conta preserva janela de restauração' => str_contains($accountData, 'FROM registros_atividade ra LEFT JOIN modalidades') && !str_contains($accountData, 'FROM registros_atividade ra LEFT JOIN modalidades m ON m.idmodalidade=ra.idmodalidade WHERE ra.idusuario=:usuario AND ra.excluido_em IS NULL'),
    'privacidade padrão configurável' => str_contains($settings, 'name="activity_visibility"') && str_contains($settings, 'name="hide_route_start_m"') && str_contains($settings, 'name="hide_route_end_m"'),
    'formulário de atividade usa smart defaults de privacidade' => str_contains($activitiesPage, '$activityDefaults') && str_contains($activitiesPage, '$formHideRouteStart') && str_contains($activitiesPage, '$formHideRouteEnd'),
    'rota privada é cortada apenas na versão compartilhável' => str_contains($activitiesJs, 'const routeCoordinatesForSharing') && str_contains($activitiesJs, 'trimRouteStart') && str_contains($activitiesJs, 'readShareConfiguration') && str_contains($activitiesJs, 'const data = override.data || shareData') && str_contains($activitiesJs, 'routeCoordinatesForSharing(data)'),
    'progresso mostra consistência volume tendência e comparação sem perguntas decorativas' => str_contains($progress, 'progress-consistency-title') && str_contains($progress, 'progress-volume-title') && str_contains($progress, 'progress-trend-title') && str_contains($progress, 'progress-comparison-list') && !str_contains($progress, "progress.question_consistency"),
    'comparador tem default contextual e sugestões' => str_contains($compare, 'activityInsightsDefaultB') && str_contains($compare, 'activityInsightsSuggestions') && str_contains($insights, "'kind' => 'recent'") && str_contains($insights, "'kind' => 'route'") && str_contains($insights, "'kind' => 'distance'"),
    'comparação de musculação separa métricas relevantes' => str_contains($compare, "stridebr_t('compare.volume')") && str_contains($compare, "stridebr_t('compare.max_load')") && str_contains($compare, "stridebr_t('compare.common_exercises')"),
    'progresso fica na navegação e comparar é contextual' => str_contains($activitiesPage, 'activity-detail-compare') && str_contains($activitiesPage, '/user/comparar-atividades.php') && str_contains($activitiesJs, '/user/comparar-atividades.php?a=') && str_contains($footer, "stridebr_t('nav.progress')") && str_contains($footer, '/user/comparar-atividades.php') && str_contains($header, '/user/progresso.php') && !str_contains($activitiesPage, 'activity-toolbar-progress'),
    'notificações de cronograma sincronizado existem' => str_contains($notifications, 'function notificacaoCronogramaSincronizadoAlterado') && str_contains($notifications, "tipo = 'sincronizado'") && str_contains($notifications, "status = 'aceito'"),
    'mudanças principais de cronograma notificam sincronizados' => substr_count($cronogramasPage, 'notificacaoCronogramaSincronizadoAlterado') >= 7 && str_contains($exerciseSchedule, 'notificacaoCronogramaSincronizadoAlterado') && str_contains($occurrenceApi, 'notificacaoCronogramaSincronizadoAlterado') && str_contains($quickCreateApi, 'notificacaoCronogramaSincronizadoAlterado'),
    'painel do treinador respeita permissões' => str_contains($trainer, "pode_ver_atividades") && str_contains($trainer, "pode_ver_cronograma") && str_contains($trainer, "stridebr_t('trainer.no_activity_permission')") && str_contains($trainer, "if (stridebr_db_bool(\$selectedLink['pode_ver_atividades'] ?? false))"),
    'analytics tem opt-out e allowlist' => str_contains($settings, 'name="product_analytics"') && str_contains($settings, "stridebr_t('settings.product_analytics_help')") && str_contains($analytics, "'activity_compared'") && str_contains($analytics, "'dashboard_customized'") && str_contains($analytics, 'if (!in_array($nome, $allowedNames, true)) return;'),
    'wrapper de POST injeta chave idempotente' => str_contains($scriptsJs, 'const ensureIdempotency') && str_contains($scriptsJs, "body instanceof FormData || body instanceof URLSearchParams") && str_contains($scriptsJs, "body.set('_idempotency_key', requestKey())"),
    'formulários POST dinâmicos recebem chave idempotente' => str_contains($scriptsJs, "input.name = '_idempotency_key'") && str_contains($scriptsJs, "String(form.method || 'get').toLowerCase() !== 'post'"),
    'AJAX de cronograma preserva URLSearchParams para idempotência' => !str_contains($cronogramasJs, 'body:payload.toString()') && !str_contains($cronogramasJs, 'body:body.toString()') && str_contains($cronogramasJs, 'body: payload') && str_contains($cronogramasJs, 'body:body'),
    'agenda AJAX passa corpo estruturado ao wrapper' => str_contains($agendaJs, "(window.StrideBRNet?.fetch || fetch)('/api/cronograma-ocorrencias.php'") && str_contains($agendaJs, 'body,'),
    'ações destrutivas de atividade têm chave e desfazer' => substr_count($activitiesJs, "_idempotency_key: requestKey()") >= 2 && str_contains($activitiesJs, "body.set('_idempotency_key', requestKey())") && str_contains($activitiesJs, "StrideBRUI.undo(tr('activity.deleted')") && str_contains($activitiesJs, '/api/atividades-restaurar.php'),
    'desfazer também cobre exclusão por formulário' => str_contains($activityDelete, '$_SESSION[\'activity_undo\']') && str_contains($activitiesPage, 'name="action" value="restore_activity"') && str_contains($activitiesPage, 'ui-server-undo'),
    'remoção de exercício oferece desfazer' => str_contains($cronograma, 'function cronogramaRestaurarExercicioPessoal') && str_contains($exerciseLibrary, 'value="restore_exercise"') && str_contains($exerciseModelJs, "t('library.saved_exercise_removed'") && str_contains($exerciseDraftJs, "t('draft.exercise_removed'") && str_contains($trainerJs, "t('trainer.exercise_removed_undo'"),
    'apoio financeiro é opt-in e só aparece quando configurado' => str_contains($env, 'STRIDEBR_DONATION_ENABLED=0') && str_contains($footer, 'stridebr_donation_enabled()') && str_contains($supportProject, 'stridebr_donation_enabled()') && str_contains($supportProject, 'stridebr_donation_pix_key()') && str_contains($supportProject, 'stridebr_donation_url()'),
    'landing apresenta planejamento registro e comparação' => str_contains($landing, 'Planeje. Treine.') && str_contains($landing, 'Compare com você mesmo'),
    'legais atualizadas para a RC' => str_contains($terms, '30 de agosto de 2026') && str_contains($privacy, '9 de setembro de 2026') && str_contains($privacy, 'Analytics de produto') && str_contains($privacy, 'Aquisição e campanhas') && str_contains($privacy, 'trechos privados') && str_contains($cookies, 'Atribuição de campanhas') && str_contains($cookies, 'desligada por padrão'),
    'páginas públicas refletem estado atual' => str_contains($faq, 'cronograma sincronizado') && str_contains($roadmap, 'GPS') && str_contains($changelog, 'Release Candidate') && str_contains($about, 'gamificado'),
    'versões legais do ambiente estão alinhadas' => str_contains($env, 'STRIDEBR_TERMS_VERSION=2026-08-30-1') && str_contains($env, 'STRIDEBR_PRIVACY_VERSION=2026-09-01-1'),
    'empty states úteis existem nas áreas novas' => str_contains($progress, "stridebr_t('progress.empty_title')") && str_contains($progress, "stridebr_t('progress.log_activity')") && str_contains($activitiesPage, 'activity-empty-actions') && str_contains($activitiesPage, '/user/gravar-atividade.php') && str_contains($exchangePage, "stridebr_t('progress.log_activity')") && str_contains($trainer, "stridebr_t('trainer.no_active_schedules')"),
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "Falhas no fechamento desktop/RC:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo '✓ desktop release finish static: ' . count($checks) . " assertions\n";
