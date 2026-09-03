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
        $error = 'Não foi possível comparar essas atividades agora.';
        error_log('StrideBR compare activities: ' . $e->getMessage());
    }
}
$formatActivity = static function (array $row): string {
    $date = !empty($row['data_inicio']) ? (new DateTimeImmutable((string) $row['data_inicio']))->format('d/m/Y') : '';
    return trim((string) ($row['titulo'] ?? $row['modalidade_nome'] ?? 'Atividade')) . ' · ' . $date;
};
$formatNumber = static fn(float $value, int $digits = 0): string => number_format($value, $digits, ',', '.');
?>
<!DOCTYPE html><html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>"><head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>"><link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/product-insights.css')); ?>"><title>Comparar atividades | StrideBR</title></head><body><div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content product-page">
<header class="product-page-header"><div><h1>Comparar atividades</h1></div><div class="product-toolbar"><a class="product-button-secondary" href="/user/atividades.php">Histórico</a><a class="product-button-secondary" href="/user/progresso.php">Progresso</a></div></header>
<?php if (count($activities) < 2): ?>
<section class="empty-action-state"><h2>Registre mais uma atividade para comparar</h2><a class="product-button" href="/user/atividades.php?new=1">Registrar atividade</a></section>
<?php else: ?>
<section class="insight-section"><form class="compare-form" method="get"><label>Atividade A<select name="a" onchange="this.form.submit()"><?php foreach ($activities as $row): ?><option value="<?php echo stridebr_e((string) $row['idregistro']); ?>"<?php echo (string) $row['idregistro'] === $idA ? ' selected' : ''; ?>><?php echo stridebr_e($formatActivity($row)); ?></option><?php endforeach; ?></select></label><span class="compare-vs">VS.</span><label>Atividade B<select name="b"><?php foreach ($activities as $row): if ((string) $row['idregistro'] === $idA) continue; ?><option value="<?php echo stridebr_e((string) $row['idregistro']); ?>"<?php echo (string) $row['idregistro'] === $idB ? ' selected' : ''; ?>><?php echo stridebr_e($formatActivity($row)); ?></option><?php endforeach; ?></select></label><button class="product-button" type="submit">Comparar</button></form>
<?php if ($suggestions): ?><div class="compare-suggestions" aria-label="Sugestões de comparação"><?php foreach ($suggestions as $suggestion): $row=$suggestion['activity']; ?><a class="compare-suggestion" href="?a=<?php echo rawurlencode($idA); ?>&amp;b=<?php echo rawurlencode((string) $row['idregistro']); ?>"><strong><?php echo stridebr_e((string) $suggestion['label']); ?></strong><span><?php echo stridebr_e($formatActivity($row)); ?></span></a><?php endforeach; ?></div><?php endif; ?>
</section>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endif; ?>
<?php if ($comparison): $a=$comparison['a'];$b=$comparison['b']; ?>
<div class="compare-head"><div class="compare-axis"><span class="insight-muted">Comparação</span></div><article><small>Atividade A</small><strong><?php echo stridebr_e((string) $a['titulo']); ?></strong><span><?php echo stridebr_e((string) $a['modalidade']); ?> · <?php echo stridebr_e((string) $a['data']); ?></span></article><article><small>Atividade B</small><strong><?php echo stridebr_e((string) $b['titulo']); ?></strong><span><?php echo stridebr_e((string) $b['modalidade']); ?> · <?php echo stridebr_e((string) $b['data']); ?></span></article></div>
<section class="insight-section"><header><div><h2>Métricas</h2></div></header><table class="compare-table"><tbody><tr><td>Esforço percebido</td><td><?php echo $a['esforco'] !== null ? (int)$a['esforco'].'/10' : '—'; ?></td><td><?php echo $b['esforco'] !== null ? (int)$b['esforco'].'/10' : '—'; ?></td></tr><?php foreach ($comparison['metricas'] as $metric): ?><tr><td><?php echo stridebr_e((string) $metric['rotulo']); ?></td><td><?php echo stridebr_e((string) $metric['a']); ?></td><td><?php echo stridebr_e((string) $metric['b']); ?></td></tr><?php endforeach; ?><?php if ($a['rota'] || $b['rota']): ?><tr><td>Ganho de elevação da rota</td><td><?php echo isset($a['rota']['ganho_m']) && $a['rota']['ganho_m'] !== null ? $formatNumber((float)$a['rota']['ganho_m']).' m' : '—'; ?></td><td><?php echo isset($b['rota']['ganho_m']) && $b['rota']['ganho_m'] !== null ? $formatNumber((float)$b['rota']['ganho_m']).' m' : '—'; ?></td></tr><?php endif; ?></tbody></table></section>
<?php if ($comparison['forca_a'] || $comparison['forca_b']): $fa=$comparison['forca_a'];$fb=$comparison['forca_b']; ?>
<section class="insight-section"><header><div><h2>Musculação</h2></div></header><div class="strength-compare"><article><h3><?php echo stridebr_e((string)$a['titulo']); ?></h3><div class="strength-summary"><div><small>Séries</small><strong><?php echo (int)($fa['series']??0); ?></strong></div><div><small>Repetições</small><strong><?php echo $formatNumber((float)($fa['repeticoes']??0)); ?></strong></div><div><small>Volume calculável</small><strong><?php echo !empty($fa['volume']) ? $formatNumber((float)$fa['volume']).' kg·rep' : '—'; ?></strong></div><div><small>Maior carga</small><strong><?php echo isset($fa['maior_carga'])&&$fa['maior_carga']!==null?$formatNumber((float)$fa['maior_carga'],1).' kg':'—'; ?></strong></div></div></article><article><h3><?php echo stridebr_e((string)$b['titulo']); ?></h3><div class="strength-summary"><div><small>Séries</small><strong><?php echo (int)($fb['series']??0); ?></strong></div><div><small>Repetições</small><strong><?php echo $formatNumber((float)($fb['repeticoes']??0)); ?></strong></div><div><small>Volume calculável</small><strong><?php echo !empty($fb['volume']) ? $formatNumber((float)$fb['volume']).' kg·rep' : '—'; ?></strong></div><div><small>Maior carga</small><strong><?php echo isset($fb['maior_carga'])&&$fb['maior_carga']!==null?$formatNumber((float)$fb['maior_carga'],1).' kg':'—'; ?></strong></div></div></article></div>
<?php $ea=[];foreach($fa['exercicios']??[] as $row)$ea[stridebr_lower((string)$row['nome'])]=$row;$eb=[];foreach($fb['exercicios']??[] as $row)$eb[stridebr_lower((string)$row['nome'])]=$row;$common=array_intersect(array_keys($ea),array_keys($eb)); if($common): ?><h3 style="margin-top:20px">Exercícios em comum</h3><table class="compare-table"><tbody><?php foreach($common as $key): ?><tr><td><?php echo stridebr_e((string)$ea[$key]['nome']); ?></td><td><?php echo (int)$ea[$key]['series']; ?> séries · <?php echo $formatNumber((float)$ea[$key]['repeticoes']); ?> reps<?php echo $ea[$key]['maior_carga']!==null?' · até '.$formatNumber((float)$ea[$key]['maior_carga'],1).' kg':''; ?></td><td><?php echo (int)$eb[$key]['series']; ?> séries · <?php echo $formatNumber((float)$eb[$key]['repeticoes']); ?> reps<?php echo $eb[$key]['maior_carga']!==null?' · até '.$formatNumber((float)$eb[$key]['maior_carga'],1).' kg':''; ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></section>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</main></div><?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?></body></html>
