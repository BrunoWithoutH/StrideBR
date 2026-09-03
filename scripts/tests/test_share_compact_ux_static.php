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

$assert(str_contains($page, 'value="portrait" data-share-format') && str_contains($page, 'value="compact" data-share-format'), 'compartilhamento precisa oferecer 4:5 e compacto.');
$assert(str_contains($js, "compact: {id: 'compact', label: 'Compacto vertical', width: 1080, height: 1350}"), 'compacto precisa ter dimensão própria.');
$assert(str_contains($js, "format: 'story'") && str_contains($js, "content: shareHasRoute(data) ? 'route' : 'sport'"), 'atividade deve abrir em Story por padrão e trocar rota por modalidade quando não houver trajeto.');
$assert(str_contains($js, "applySharePreset(shareHasRoute(shareData) ? SHARE_PRESET_FALLBACK : 'stats'"), 'redefinir atividade sem rota deve manter card de dados.');
$assert(str_contains($js, '} else if (hasSportVisual) {') && str_contains($js, 'strokeOnly = false') && str_contains($js, 'dimension * .014'), 'atividade compacta sem rota precisa usar Sporticon em contorno fino e colapsar o bloco quando a modalidade não estiver visível.');
$assert(str_contains($js, "label === 'codigo'") && str_contains($js, 'metricas: rawShareMetrics.filter(metric => !isSharePrivateMetric(metric))'), 'código do treino não pode entrar no card compartilhado.');
$assert(str_contains($js, 'const lines = wrapCanvasText(context, focus') && str_contains($js, 'let resolvedFontSize = fontSize') && str_contains($js, 'const textLimit = maxWidth * .72'), 'título deve reduzir de forma segura no rótulo superior e foco deve quebrar linha sem estourar o card.');
$assert(str_contains($page, 'class="activity-share-more-menu"') && str_contains($page, 'data-export-route-png hidden'), 'exportar rota deve ficar no menu secundário de três pontos.');
$assert(!str_contains($page, 'data-share-master-switch'), 'seletor de resumo/trecho deve ficar fora da UI até os layouts de múltiplas rotas serem fechados.');
$assert(str_contains($css, 'compartilhamento mobile fullscreen + bottom sheet') && str_contains($css, 'height:100dvh!important') && str_contains($css, 'height:min(82dvh,760px)!important'), 'compartilhamento mobile deve ocupar a tela e usar bottom sheet para personalização.');
$assert(str_contains($css, '.activity-share-preview-shell.is-compact canvas[data-share-canvas]{aspect-ratio:4/5}') && str_contains($css, '.activity-share-format-rail{'), 'preview compacto precisa ser retrato 4:5 e manter seletor visual vertical.');
$assert(str_contains($js, 'const drawCompactShareCardSurface = async') && str_contains($js, "const isWide = format === 'compactWide'"), 'compacto precisa ter composição própria e elementos maiores no transparente.');
$assert(!str_contains($js, 'shareRoundedRect(context, x, y, tileWidth, tileHeight, tileRadius)') && str_contains($js, 'const visualTop = metricBottom'), 'compacto deve remover tiles e aproximar a rota das métricas.');
$assert(str_contains($js, 'const metricColumns = isWide ? Math.min(2, Math.max(1, metrics.length)) : 1') && str_contains($js, "return isWide ? 2 : 1"), 'horizontal deve usar grade 2 × 2 e vertical deve ordenar as quatro métricas em uma coluna.');
$assert(str_contains($js, "disallowCompact: true") && str_contains($js, "const mapCompatible = singleEditor && activeShareContentId === 'route' && hasRoute && !compact"), 'mapa deve ficar restrito aos formatos não compactos.');
$assert(str_contains($js, "const columns = story ? 2 : compact ? Math.min(2, Math.max(1, selected.length))"), 'trechos no compacto retrato não devem usar grade paisagem de três colunas.');
$assert(str_contains($ui, '.workout-library-page { width: min(1260px, calc(100% - 28px)); max-width: none; padding: 24px 0 54px; }'), 'Biblioteca precisa usar o espaçamento vertical padrão do app.');
$assert(str_contains($ui, '.activities-page { padding-top: 24px; }') && str_contains($ui, '.draft-exercise-page,') && str_contains($ui, '.model-exercise-page { width: min(1220px, calc(100% - 28px));'), 'páginas de trabalho precisam manter topo e gutters consistentes.');
$assert((str_contains($header, "stridebr_t('nav.progress')") || str_contains($header, '>Progresso</a>')) && !preg_match('/href="\/user\/metas\.php"[^>]*>Metas<\/a>/', $header), 'Metas não deve ser item principal no desktop.');
$assert((str_contains($footer, "stridebr_t('nav.progress')") || str_contains($footer, '<span>Progresso</span>')) && !str_contains($footer, '<span>Exercícios</span>'), 'mobile principal deve usar Progresso no lugar de Exercícios.');
$assert(str_contains($footer, '/user/biblioteca.php?tab=treinos') && str_contains($footer, '>Exercícios</a>'), 'Biblioteca e Exercícios devem pertencer à área de Treinos/Mais.');
$assert(str_contains($libraryWorkouts, 'workout-library-tabs') && str_contains($libraryExercises, 'workout-library-tabs') && str_contains($libraryWorkouts, '>Treinos</a>') && str_contains($libraryWorkouts, '>Exercícios</a>'), 'Biblioteca precisa manter tabs Treinos e Exercícios.');
$assert(substr_count($authFiles, 'class="auth-modern-body"') === 4 && substr_count($authFiles, 'class="auth-modern-card"') === 4, 'fluxos secundários de autenticação devem usar o shell visual novo.');
$assert(!str_contains($authFiles, 'class="auth-layout"') && !str_contains($authFiles, 'class="form verification-form"'), 'fluxos secundários não devem manter o layout antigo.');

printf("✓ share compact/nav/auth static: %d assertions\n", $checks);
