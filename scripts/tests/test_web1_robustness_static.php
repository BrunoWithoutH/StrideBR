<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$seed = $read('src/database/stridebr_seed.sql');
$activitiesCss = $read('public/assets/css/atividades.css');
$share = $read('public/assets/js/atividades.js');
$sw = $read('public/sw.js');

$muscleFields = [];
if (preg_match_all("/\\('f_musc_[^']+',\\s*'md_musculacao',\\s*'([^']+)',\\s*'([^']+)'/", $seed, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) $muscleFields[] = strtolower((string) $match[2]);
}

$checks = [
    'resumo de atividades some apenas no breakpoint de telefone' => str_contains($activitiesCss, '@media (max-width: 639px)') && str_contains($activitiesCss, '.activity-history-summary { display: none; }'),
    'musculação usa modelo de sessão sem múltiplas unidades' => str_contains($seed, "('md_musculacao', 'm_musculacao', 'Sessão de musculação', 'basico', 'Registro geral de uma sessão de musculação.', 'sessao', 'Sessão', FALSE"),
    'musculação não recebe rota no seed padrão' => !preg_match("/lower\\(slug\\).*musculacao/s", substr($seed, (int) strpos($seed, 'SET permite_rota = TRUE'), 500)),
    'campos base da musculação são duração intensidade e observações' => in_array('duracao', $muscleFields, true) && in_array('intensidade', $muscleFields, true) && in_array('observacoes', $muscleFields, true),
    'modelo base da musculação não injeta distância ritmo ou elevação' => !array_intersect($muscleFields, ['distancia', 'ritmo', 'pace', 'elevacao', 'desnivel']),
    'share usa fonte única de trechos compartilháveis' => str_contains($share, 'const shareableSegmentsForData') && str_contains($share, 'shareableSegmentsForData(data)'),
    'elemento modalidade/nenhum não pode manter mapa ou rota residual' => str_contains($share, "showMapBase: Boolean(content === 'route'") && str_contains($share, "showRoute: Boolean(content === 'route'"),
    'service worker usa cache novo para renovar assets PWA' => str_contains($sw, "const STATIC_CACHE = 'stridebr-static-v3-'"),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas na robustez final Web 1.0:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
printf("✓ Web 1.0 robustness static: %d assertions\n", count($checks));
