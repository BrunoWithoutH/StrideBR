<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Share compact UX static failed: {$message}\n");
        exit(1);
    }
};

$page = $read('public/user/atividades.php');
$js = $read('public/assets/js/atividades.js');
$css = $read('public/assets/css/atividades.css');
$sharingCss = $read('public/assets/css/activity-sharing.css');
$ui = $read('public/assets/css/ui-refresh.css');
$header = $read('src/layout/header.php');
$footer = $read('src/layout/footer.php');
$library = $read('public/user/biblioteca.php');
$libraryWorkouts = $library;
$libraryExercises = $library;
$authFiles = implode("\n", array_map($read, [
    'public/forgot-password.php',
    'public/reset-password.php',
    'public/resend-verification.php',
    'public/verify-email.php',
]));

$assert(str_contains($page, 'value="story" data-share-format') && str_contains($page, 'value="portrait" data-share-format') && str_contains($page, 'value="square" data-share-format') && !str_contains($page, 'value="compact" data-share-format') && str_contains($page, 'value="compact" data-share-composition'), 'formatos devem ser Story/Retrato/Quadrado e Compacto deve ser composição.');
$assert(str_contains($js, "let activeShareCompositionId = 'standard'") && str_contains($js, "configuration.composition === 'compact'") && str_contains($js, "const isWide = format === 'square'"), 'Compacto precisa ser composição adaptativa, não formato próprio.');
$assert(str_contains($js, "format: 'story'") && str_contains($js, "content: hasRoute && !sessionContext ? 'route' : 'sport'"), 'atividade deve abrir em Story por padrão e trocar rota por modalidade quando não houver trajeto.');
$assert(str_contains($js, "activeShareContentId = hasRoute && !segmented ? 'route' : 'sport'") && str_contains($js, "activeSharePresetId = SHARE_PRESET_FALLBACK"), 'redefinir deve manter card de dados e rota apenas quando aplicável.');
$assert(str_contains($js, '} else if (hasSportVisual) {') && str_contains($js, 'strokeOnly = false') && str_contains($js, 'dimension * .014'), 'atividade compacta sem rota precisa usar Sporticon em contorno fino e colapsar o bloco quando a modalidade não estiver visível.');
$assert(str_contains($js, "normalizeShareMetricLabel(tr('activity.code'))") && str_contains($js, "normalizeShareMetricLabel(tr('activity.focus'))") && str_contains($js, 'metricas: shareMetrics'), 'código do treino não pode entrar no card compartilhado.');
$assert(str_contains($js, 'const lines = wrapCanvasText(context, focus') && str_contains($js, 'let resolvedFontSize = fontSize') && str_contains($js, 'const textLimit = maxWidth * .72'), 'título deve reduzir de forma segura no rótulo superior e foco deve quebrar linha sem estourar o card.');
$assert(str_contains($page, 'class="activity-share-more-menu"') && str_contains($page, 'data-export-route-png hidden'), 'exportar rota deve ficar no menu secundário de três pontos.');
$assert(str_contains($page, 'data-share-master-switch') && str_contains($page, 'data-share-scope="session"') && str_contains($page, 'data-share-scope="single_segment"') && str_contains($page, 'data-share-scope="multiple_segments"'), 'editor deve oferecer escopo Sessão/Um trecho/Vários trechos sem wizard anterior.');
$assert(str_contains($sharingCss, 'width: 100vw') && str_contains($sharingCss, 'height: 100dvh') && str_contains($sharingCss, 'max-height: min(82dvh, 720px)'), 'compartilhamento mobile deve ocupar a tela e usar bottom sheet para personalização.');
$assert(str_contains($sharingCss, '.activity-share-format-rail') && str_contains($page, 'data-share-composition-options'), 'seletor de formato e composição precisam permanecer separados.');
$assert(str_contains($js, 'const drawCompactShareCardSurface = async') && str_contains($js, "const isWide = format === 'square'") && str_contains($js, "const layoutHeight = format === 'story' ? Math.min(height, 1350) : height"), 'Compacto precisa ter composição própria: vertical em Story/Retrato e grade em Quadrado sem esticar Story.');
$assert(!str_contains($js, 'shareRoundedRect(context, x, y, tileWidth, tileHeight, tileRadius)') && str_contains($js, 'const visualTop = metricBottom'), 'compacto deve remover tiles e aproximar a rota das métricas.');
$assert(str_contains($js, 'const metricLayout = shareMetricLayout(metrics.length, side, contentWidth, metricTop, metricRowHeight)') && str_contains($js, 'const metricRows = shareMetricRowCount(metrics.length)'), 'compactos devem usar a composição dinâmica de 1–4 métricas.');
$assert(str_contains($js, "supportsMap: false") && str_contains($js, "const hidden = candidate.id === 'map' && !mapCompatible") && str_contains($js, 'card.hidden = hidden'), 'fundo de mapa deve desaparecer na composição Compacto.');
$assert(str_contains($js, "const layoutTop = Math.max(0, (height - layoutHeight) / 2)"), 'Story + Compacto deve centralizar a composição vertical sem esticar o conteúdo.');
$assert(str_contains($ui, '.workout-library-page { width: min(1260px, calc(100% - 28px)); max-width: none; padding: 24px 0 54px; }'), 'Biblioteca precisa usar o espaçamento vertical padrão do app.');
$assert(str_contains($ui, '.activities-page { padding-top: 24px; }') && str_contains($ui, '.draft-exercise-page,') && str_contains($ui, '.model-exercise-page { width: min(1220px, calc(100% - 28px));'), 'páginas de trabalho precisam manter topo e gutters consistentes.');
$assert((str_contains($header, "stridebr_t('nav.progress')") || str_contains($header, '>Progresso</a>')) && !preg_match('/href="\/user\/metas\.php"[^>]*>Metas<\/a>/', $header), 'Metas não deve ser item principal no desktop.');
$assert((str_contains($footer, "stridebr_t('nav.progress')") || str_contains($footer, '<span>Progresso</span>')) && !str_contains($footer, '<span>Exercícios</span>'), 'mobile principal deve usar Progresso no lugar de Exercícios.');
$assert(str_contains($footer, '/user/biblioteca.php?tab=treinos') && str_contains($footer, "stridebr_t('nav.library_exercises')"), 'Biblioteca e Exercícios devem pertencer à área de Treinos/Mais.');
$assert(str_contains($libraryWorkouts, 'workout-library-tabs') && str_contains($libraryExercises, 'workout-library-tabs') && str_contains($libraryWorkouts, "stridebr_t('common.workouts')") && str_contains($libraryWorkouts, "stridebr_t('common.exercises')"), 'Biblioteca precisa manter tabs Treinos e Exercícios.');
$assert(substr_count($authFiles, 'class="auth-modern-body"') === 4 && substr_count($authFiles, 'class="auth-modern-card"') === 4, 'fluxos secundários de autenticação devem usar o shell visual novo.');
$assert(!str_contains($authFiles, 'class="auth-layout"') && !str_contains($authFiles, 'class="form verification-form"'), 'fluxos secundários não devem manter o layout antigo.');

printf("✓ share compact/nav/auth static: %d assertions\n", $checks);
