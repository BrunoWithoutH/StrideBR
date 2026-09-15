<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_insights.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';

$activities = activityInsightsList($pdo, $idUsuario);
$idA = trim((string) ($_GET['a'] ?? ($activities[0]['idregistro'] ?? '')));
if ($idA !== '' && !array_filter($activities, static fn(array $row): bool => (string) $row['idregistro'] === $idA)) $idA = (string) ($activities[0]['idregistro'] ?? '');
$idB = trim((string) ($_GET['b'] ?? ''));
if ($idA !== '' && ($idB === '' || $idB === $idA)) $idB = activityInsightsDefaultB($pdo, $idUsuario, $activities, $idA);
if ($idB !== '' && !array_filter($activities, static fn(array $row): bool => (string) $row['idregistro'] === $idB)) $idB = activityInsightsDefaultB($pdo, $idUsuario, $activities, $idA);
$suggestions = $idA !== '' ? activityInsightsSuggestions($pdo, $idUsuario, $activities, $idA) : [];
$comparison = null;
$error = '';
if ($idA !== '' && $idB !== '' && $idA !== $idB) {
    try {
        $comparison = activityInsightsCompare($pdo, $idUsuario, $idA, $idB);
        productAnalyticsRegistrar($pdo, $idUsuario, 'activity_compared', ['same_sport' => ($comparison['a']['modalidade_slug'] ?? '') === ($comparison['b']['modalidade_slug'] ?? '')]);
    } catch (Throwable $e) {
        $error = stridebr_t('compare.error');
        error_log('StrideBR compare activities: ' . $e->getMessage());
    }
}
$formatActivity = static function (array $row): string {
    $date = !empty($row['data_inicio']) ? stridebr_format_date((string) $row['data_inicio']) : '';
    $slug = (string) ($row['modalidade_slug'] ?? '');
    $title = trim((string) ($row['titulo'] ?? ''));
    if ($title !== '') $title = stridebr_present_activity_title($title, $slug);
    else $title = stridebr_sport_name($slug, (string) ($row['modalidade_nome'] ?? stridebr_t('compare.activity_fallback')));
    return $title . ' · ' . $date;
};
$formatNumber = static fn(float $value, int $digits = 0): string => stridebr_format_number($value, $digits);
$formatPerformance = static function (mixed $value, ?array $analysis): string {
    if (!is_numeric($value)) return '—';
    $unit = (string) ($analysis['pacing']['unit'] ?? '');
    $number = (float) $value;
    if (in_array($unit, ['s_per_km', 's_per_100m'], true)) {
        $seconds = max(0, (int) round($number));
        return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT) . ($unit === 's_per_100m' ? '/100m' : '/km');
    }
    if ($unit === 'km_h') return stridebr_format_number($number, 1) . ' km/h';
    return stridebr_format_number($number, 1);
};
?>
<!DOCTYPE html><html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>"><head>
<?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
<link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>">
<link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/activity-comparison-v2.css')); ?>">
<link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
<title><?php echo stridebr_e(stridebr_t('compare.page_title')); ?> | StrideBR</title>
</head><body><div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content product-page comparison-v2">
<header class="product-page-header"><div><h1><?php echo stridebr_e(stridebr_t('common.compare_activities')); ?></h1><p>Compare execução, ritmo, splits e resposta ao esforço sem reduzir a atividade a uma métrica final.</p></div><div class="product-toolbar"><a class="product-button-secondary" href="/user/atividades.php">Histórico</a><a class="product-button-secondary" href="/user/progresso.php">Progresso</a></div></header>
<?php if (count($activities) < 2): ?>
<section class="empty-action-state"><h2><?php echo stridebr_e(stridebr_t('compare.need_more')); ?></h2><a class="product-button" href="/user/atividades.php?new=1">Registrar atividade</a></section>
<?php else: ?>
<section class="insight-section"><form class="compare-form" method="get"><label>Atividade A<select name="a" onchange="this.form.submit()"><?php foreach ($activities as $row): ?><option value="<?php echo stridebr_e((string) $row['idregistro']); ?>"<?php echo (string) $row['idregistro'] === $idA ? ' selected' : ''; ?>><?php echo stridebr_e($formatActivity($row)); ?></option><?php endforeach; ?></select></label><span class="compare-vs">×</span><label>Atividade B<select name="b"><?php foreach ($activities as $row): if ((string) $row['idregistro'] === $idA) continue; ?><option value="<?php echo stridebr_e((string) $row['idregistro']); ?>"<?php echo (string) $row['idregistro'] === $idB ? ' selected' : ''; ?>><?php echo stridebr_e($formatActivity($row)); ?></option><?php endforeach; ?></select></label><button class="product-button" type="submit">Comparar</button></form>
<?php if ($suggestions): ?><div class="compare-suggestions" aria-label="Sugestões"><?php foreach ($suggestions as $suggestion): $row=$suggestion['activity']; ?><a class="compare-suggestion" href="?a=<?php echo rawurlencode($idA); ?>&amp;b=<?php echo rawurlencode((string) $row['idregistro']); ?>"><strong><?php echo stridebr_e((string) $suggestion['label']); ?></strong><span><?php echo stridebr_e($formatActivity($row)); ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endif; ?>
<?php if ($comparison): $a=$comparison['a'];$b=$comparison['b'];$analysisA=$comparison['analysis_a']??null;$analysisB=$comparison['analysis_b']??null; ?>
<div class="compare-head"><div class="compare-axis"><span class="insight-muted">Comparação</span></div><article><small>Atividade A</small><strong><?php echo stridebr_e((string) $a['titulo']); ?></strong><span><?php echo stridebr_e(stridebr_sport_name((string) ($a['modalidade_slug'] ?? ''), (string) $a['modalidade'])); ?> · <?php echo stridebr_e((string) $a['data']); ?></span></article><article><small>Atividade B</small><strong><?php echo stridebr_e((string) $b['titulo']); ?></strong><span><?php echo stridebr_e(stridebr_sport_name((string) ($b['modalidade_slug'] ?? ''), (string) $b['modalidade'])); ?> · <?php echo stridebr_e((string) $b['data']); ?></span></article></div>
<section class="insight-section"><header><div><h2>Resumo</h2></div></header><table class="compare-table"><tbody><tr><td>Esforço percebido</td><td><?php echo $a['esforco'] !== null ? (int)$a['esforco'].'/10' : '—'; ?></td><td><?php echo $b['esforco'] !== null ? (int)$b['esforco'].'/10' : '—'; ?></td></tr><?php foreach ($comparison['metricas'] as $metric): ?><tr><td><?php echo stridebr_e((string) $metric['rotulo']); ?></td><td><?php echo stridebr_e((string) $metric['a']); ?></td><td><?php echo stridebr_e((string) $metric['b']); ?></td></tr><?php endforeach; ?><?php if ($a['rota'] || $b['rota']): ?><tr><td>Elevação positiva</td><td><?php echo isset($a['rota']['ganho_m']) && $a['rota']['ganho_m'] !== null ? $formatNumber((float)$a['rota']['ganho_m']).' m' : '—'; ?></td><td><?php echo isset($b['rota']['ganho_m']) && $b['rota']['ganho_m'] !== null ? $formatNumber((float)$b['rota']['ganho_m']).' m' : '—'; ?></td></tr><?php endif; ?><tr><td>Equipamento</td><td><?php echo stridebr_e(implode(', ', array_column($a['equipamentos'] ?? [], 'nome')) ?: '—'); ?></td><td><?php echo stridebr_e(implode(', ', array_column($b['equipamentos'] ?? [], 'nome')) ?: '—'); ?></td></tr></tbody></table></section>
<?php if (!empty($comparison['streams_a']['samples']) || !empty($comparison['streams_b']['samples'])): ?>
<section class="insight-section comparison-chart-section"><header><div><h2>Curvas</h2><p>Sobreposição por distância. Gaps permanecem separados.</p></div><div class="comparison-metric-picker" data-comparison-metrics></div></header><div class="comparison-chart" data-comparison-chart role="img" aria-label="Comparação gráfica das duas atividades"></div><div class="comparison-chart-legend"><span><i class="is-a"></i>Atividade A</span><span><i class="is-b"></i>Atividade B</span></div></section>
<?php endif; ?>
<?php $splitsA=$comparison['splits_a']['data']??[];$splitsB=$comparison['splits_b']['data']??[];$splitCount=max(count($splitsA),count($splitsB)); if($splitCount>0): ?>
<section class="insight-section"><header><div><h2>Splits de 1 km</h2><p>Tempo, ritmo e FC comparados por trecho.</p></div></header><div class="compare-table-scroll"><table class="compare-table compare-splits"><thead><tr><th>KM</th><th>A</th><th>B</th><th>Diferença</th><th>FC A</th><th>FC B</th></tr></thead><tbody><?php for($i=0;$i<$splitCount;$i++): $sa=$splitsA[$i]??null;$sb=$splitsB[$i]??null;$ta=is_array($sa)&&is_numeric($sa['moving_duration_s']??null)?(float)$sa['moving_duration_s']:null;$tb=is_array($sb)&&is_numeric($sb['moving_duration_s']??null)?(float)$sb['moving_duration_s']:null; ?><tr><td><?php echo $i+1; ?><?php echo !empty($sa['partial'])||!empty($sb['partial'])?'*':''; ?></td><td><?php echo $sa ? stridebr_e($formatPerformance($sa['pace_s_per_km']??($sa['speed_kmh']??null), ['pacing'=>['unit'=>isset($sa['pace_s_per_km'])?'s_per_km':'km_h']])) : '—'; ?></td><td><?php echo $sb ? stridebr_e($formatPerformance($sb['pace_s_per_km']??($sb['speed_kmh']??null), ['pacing'=>['unit'=>isset($sb['pace_s_per_km'])?'s_per_km':'km_h']])) : '—'; ?></td><td><?php echo $ta!==null&&$tb!==null ? stridebr_e(($tb-$ta>=0?'+':'').stridebr_format_number($tb-$ta,1).' s') : '—'; ?></td><td><?php echo is_numeric($sa['heart_rate_avg_bpm']??null)?(int)round((float)$sa['heart_rate_avg_bpm']).' bpm':'—'; ?></td><td><?php echo is_numeric($sb['heart_rate_avg_bpm']??null)?(int)round((float)$sb['heart_rate_avg_bpm']).' bpm':'—'; ?></td></tr><?php endfor; ?></tbody></table></div></section>
<?php endif; ?>
<?php if (($analysisA['pacing']??null)||($analysisB['pacing']??null)||($analysisA['heart_rate']??null)||($analysisB['heart_rate']??null)): ?>
<section class="insight-section"><header><div><h2>Análise</h2><p>Comparação objetiva. Nenhuma atividade é tratada como vencedora.</p></div></header><table class="compare-table"><tbody><tr><td>Padrão</td><td><?php echo stridebr_e(str_replace('_',' ',(string)($analysisA['pacing']['pattern']??'—'))); ?></td><td><?php echo stridebr_e(str_replace('_',' ',(string)($analysisB['pacing']['pattern']??'—'))); ?></td></tr><tr><td>Primeira metade</td><td><?php echo stridebr_e($formatPerformance($analysisA['pacing']['first_half']??null,$analysisA)); ?></td><td><?php echo stridebr_e($formatPerformance($analysisB['pacing']['first_half']??null,$analysisB)); ?></td></tr><tr><td>Segunda metade</td><td><?php echo stridebr_e($formatPerformance($analysisA['pacing']['second_half']??null,$analysisA)); ?></td><td><?php echo stridebr_e($formatPerformance($analysisB['pacing']['second_half']??null,$analysisB)); ?></td></tr><tr><td>Variabilidade</td><td><?php echo is_numeric($analysisA['pacing']['variability_percent']??null)?stridebr_e(stridebr_format_number((float)$analysisA['pacing']['variability_percent'],1).'%'):'—'; ?></td><td><?php echo is_numeric($analysisB['pacing']['variability_percent']??null)?stridebr_e(stridebr_format_number((float)$analysisB['pacing']['variability_percent'],1).'%'):'—'; ?></td></tr><tr><td>Últimos 10%</td><td><?php echo stridebr_e($formatPerformance($analysisA['pacing']['finish']['last_10_percent']??null,$analysisA)); ?></td><td><?php echo stridebr_e($formatPerformance($analysisB['pacing']['finish']['last_10_percent']??null,$analysisB)); ?></td></tr><tr><td>FC média</td><td><?php echo is_numeric($analysisA['heart_rate']['average_bpm']??null)?(int)round((float)$analysisA['heart_rate']['average_bpm']).' bpm':'—'; ?></td><td><?php echo is_numeric($analysisB['heart_rate']['average_bpm']??null)?(int)round((float)$analysisB['heart_rate']['average_bpm']).' bpm':'—'; ?></td></tr><tr><td>Cardiac drift</td><td><?php echo is_numeric($analysisA['heart_rate']['decoupling']['decoupling_percent']??null)?stridebr_e(stridebr_format_number((float)$analysisA['heart_rate']['decoupling']['decoupling_percent'],1).'%'):'—'; ?></td><td><?php echo is_numeric($analysisB['heart_rate']['decoupling']['decoupling_percent']??null)?stridebr_e(stridebr_format_number((float)$analysisB['heart_rate']['decoupling']['decoupling_percent'],1).'%'):'—'; ?></td></tr></tbody></table></section>
<?php endif; ?>
<?php if ($comparison['forca_a'] || $comparison['forca_b']): $fa=$comparison['forca_a'];$fb=$comparison['forca_b']; ?>
<section class="insight-section"><header><div><h2><?php echo stridebr_e(stridebr_t('sport.musculacao')); ?></h2></div></header><div class="strength-compare"><article><h3><?php echo stridebr_e((string)$a['titulo']); ?></h3><div class="strength-summary"><div><small><?php echo stridebr_e(stridebr_t('compare.series')); ?></small><strong><?php echo (int)($fa['series']??0); ?></strong></div><div><small><?php echo stridebr_e(stridebr_t('compare.repetitions')); ?></small><strong><?php echo $formatNumber((float)($fa['repeticoes']??0)); ?></strong></div><div><small><?php echo stridebr_e(stridebr_t('compare.volume')); ?></small><strong><?php echo !empty($fa['volume']) ? $formatNumber((float)$fa['volume']).' kg·rep' : '—'; ?></strong></div><div><small><?php echo stridebr_e(stridebr_t('compare.max_load')); ?></small><strong><?php echo isset($fa['maior_carga'])&&$fa['maior_carga']!==null?$formatNumber((float)$fa['maior_carga'],1).' kg':'—'; ?></strong></div></div></article><article><h3><?php echo stridebr_e((string)$b['titulo']); ?></h3><div class="strength-summary"><div><small><?php echo stridebr_e(stridebr_t('compare.series')); ?></small><strong><?php echo (int)($fb['series']??0); ?></strong></div><div><small><?php echo stridebr_e(stridebr_t('compare.repetitions')); ?></small><strong><?php echo $formatNumber((float)($fb['repeticoes']??0)); ?></strong></div><div><small><?php echo stridebr_e(stridebr_t('compare.volume')); ?></small><strong><?php echo !empty($fb['volume']) ? $formatNumber((float)$fb['volume']).' kg·rep' : '—'; ?></strong></div><div><small><?php echo stridebr_e(stridebr_t('compare.max_load')); ?></small><strong><?php echo isset($fb['maior_carga'])&&$fb['maior_carga']!==null?$formatNumber((float)$fb['maior_carga'],1).' kg':'—'; ?></strong></div></div></article></div>
<?php $ea=[];foreach($fa['exercicios']??[] as $row)$ea[stridebr_lower((string)$row['nome'])]=$row;$eb=[];foreach($fb['exercicios']??[] as $row)$eb[stridebr_lower((string)$row['nome'])]=$row;$common=array_intersect(array_keys($ea),array_keys($eb)); if($common): ?><h3 class="compare-common-heading"><?php echo stridebr_e(stridebr_t('compare.common_exercises')); ?></h3><table class="compare-table"><tbody><?php foreach($common as $key): ?><tr><td><?php echo stridebr_e((string)$ea[$key]['nome']); ?></td><td><?php echo stridebr_e(stridebr_t('compare.exercise_line', ['sets' => (int) $ea[$key]['series'], 'reps' => $formatNumber((float) $ea[$key]['repeticoes'])])); ?><?php echo $ea[$key]['maior_carga'] !== null ? stridebr_e(stridebr_t('compare.up_to_load', ['load' => $formatNumber((float) $ea[$key]['maior_carga'], 1)])) : ''; ?></td><td><?php echo stridebr_e(stridebr_t('compare.exercise_line', ['sets' => (int) $eb[$key]['series'], 'reps' => $formatNumber((float) $eb[$key]['repeticoes'])])); ?><?php echo $eb[$key]['maior_carga'] !== null ? stridebr_e(stridebr_t('compare.up_to_load', ['load' => $formatNumber((float) $eb[$key]['maior_carga'], 1)])) : ''; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php endif; ?>
<script type="application/json" id="activity-comparison-v2-data"><?php echo json_encode(['a'=>['title'=>$a['titulo'],'streams'=>$comparison['streams_a']], 'b'=>['title'=>$b['titulo'],'streams'=>$comparison['streams_b']]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
<?php endif; ?>
<?php endif; ?>
</main></div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-comparison-v2.js')); ?>"></script>
</body></html>
