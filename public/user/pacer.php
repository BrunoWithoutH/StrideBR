<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_stream_service.php';
require_once dirname(__DIR__, 2) . '/src/function/pacer_service.php';

function stridebr_pacer_web_seconds(string $value): ?float
{
    $value = trim($value);
    if ($value === '') return null;
    if (is_numeric($value)) return (float) $value;
    $parts = array_map('intval', explode(':', $value));
    if (count($parts) === 2) return $parts[0] * 60 + $parts[1];
    if (count($parts) === 3) return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
    return null;
}

function stridebr_pacer_web_time(float $seconds): string
{
    $total = max(0, (int) round($seconds));
    $h = intdiv($total, 3600);
    $m = intdiv($total % 3600, 60);
    $s = $total % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
}

function stridebr_pacer_web_payload(array $source): array
{
    $strategy = trim((string) ($source['strategy'] ?? 'even'));
    $distanceKm = isset($source['target_distance_km']) ? (float) str_replace(',', '.', (string) $source['target_distance_km']) : 0.0;
    $targetTime = stridebr_pacer_web_seconds((string) ($source['target_time'] ?? ''));
    $payload = [
        'name' => trim((string) ($source['name'] ?? '')),
        'sport' => trim((string) ($source['sport'] ?? '')),
        'target_distance_m' => $distanceKm * 1000,
        'target_time_s' => $targetTime,
        'goal_mode' => trim((string) ($source['goal_mode'] ?? 'target_time')),
        'clock_mode' => trim((string) ($source['clock_mode'] ?? 'auto')),
        'strategy' => $strategy,
        'tolerance_s_per_km' => (float) ($source['tolerance_s_per_km'] ?? 10),
        'constraints' => [
            'progression_percent' => (float) ($source['progression_percent'] ?? 8),
            'segment_distance_m' => (float) ($source['segment_distance_m'] ?? 2000),
        ],
        'guidance_rules' => pacerDefaultRules(),
        'status' => 'active',
    ];
    $floor = trim((string) ($source['heart_rate_floor_bpm'] ?? ''));
    $ceiling = trim((string) ($source['heart_rate_ceiling_bpm'] ?? ''));
    if ($floor !== '') $payload['constraints']['heart_rate_floor_bpm'] = (int) $floor;
    if ($ceiling !== '') $payload['constraints']['heart_rate_ceiling_bpm'] = (int) $ceiling;
    if ($strategy === 'custom') {
        $payload['segments'] = [];
        foreach ((array) ($source['segments'] ?? []) as $segment) {
            if (!is_array($segment)) continue;
            $start = (float) str_replace(',', '.', (string) ($segment['start_km'] ?? '0')) * 1000;
            $end = (float) str_replace(',', '.', (string) ($segment['end_km'] ?? '0')) * 1000;
            $pace = stridebr_pacer_web_seconds((string) ($segment['pace'] ?? ''));
            $tolerance = trim((string) ($segment['tolerance'] ?? ''));
            $hrFloor = trim((string) ($segment['hr_floor'] ?? ''));
            $hrCeiling = trim((string) ($segment['hr_ceiling'] ?? ''));
            $payload['segments'][] = [
                'basis' => 'distance',
                'start_distance_m' => $start,
                'end_distance_m' => $end,
                'target_pace_s_per_km' => $pace,
                'tolerance_s_per_km' => $tolerance === '' ? $payload['tolerance_s_per_km'] : (float) $tolerance,
                'heart_rate_floor_bpm' => $hrFloor === '' ? null : (int) $hrFloor,
                'heart_rate_ceiling_bpm' => $hrCeiling === '' ? null : (int) $hrCeiling,
            ];
        }
    }
    return $payload;
}

$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    stridebr_verify_csrf();
    try {
        $action = trim((string) ($_POST['action'] ?? ''));
        if ($action === 'save') {
            $id = trim((string) ($_POST['idplan'] ?? '')) ?: null;
            $payload = stridebr_pacer_web_payload($_POST);
            if ($id !== null) {
                $existing = pacerPlanGet($pdo, $idUsuario, $id);
                if ($existing === []) throw new InvalidArgumentException('Estratégia não encontrada.');
                $unchangedGeneratedShape = $payload['strategy'] !== 'custom'
                    && empty($_POST['generated_options_changed'])
                    && (string) $existing['sport']['id'] === (string) $payload['sport']
                    && abs((float) $existing['target_distance_m'] - (float) $payload['target_distance_m']) <= 0.01
                    && abs((float) $existing['target_time_s'] - (float) $payload['target_time_s']) <= 0.01
                    && (string) $existing['strategy'] === (string) $payload['strategy']
                    && abs((float) $existing['default_tolerance_s_per_km'] - (float) $payload['tolerance_s_per_km']) <= 0.01;
                if ($unchangedGeneratedShape) {
                    $payload['_preserve_segments'] = true;
                    $payload['segments'] = $existing['segments'];
                }
            }
            $saved = pacerPlanSave($pdo, $idUsuario, $payload, $id);
            stridebr_flash('success', 'Estratégia de pace salva.');
            header('Location: /user/pacer.php?edit=' . rawurlencode((string) $saved['id']));
            exit;
        }
        if ($action === 'archive') {
            pacerPlanArchive($pdo, $idUsuario, trim((string) ($_POST['idplan'] ?? '')));
            stridebr_flash('success', 'Estratégia arquivada.');
            header('Location: /user/pacer.php');
            exit;
        }
        if ($action === 'duplicate') {
            $source = pacerPlanGet($pdo, $idUsuario, trim((string) ($_POST['idplan'] ?? '')));
            if ($source === []) throw new InvalidArgumentException('Estratégia não encontrada.');
            $payload = [
                'name' => $source['name'] . ' (cópia)',
                'sport' => $source['sport']['id'],
                'target_distance_m' => $source['target_distance_m'],
                'target_time_s' => $source['target_time_s'],
                'goal_mode' => $source['goal_mode'] ?? ($source['guidance_rules']['goal_mode'] ?? 'target_time'),
                'clock_mode' => $source['clock_mode'] ?? ($source['guidance_rules']['clock_mode'] ?? 'auto'),
                'strategy' => 'custom',
                'tolerance_s_per_km' => $source['default_tolerance_s_per_km'],
                'segments' => $source['segments'],
                'guidance_rules' => $source['guidance_rules'],
                'status' => 'active',
            ];
            $copy = pacerPlanSave($pdo, $idUsuario, $payload);
            stridebr_flash('success', 'Estratégia duplicada.');
            header('Location: /user/pacer.php?edit=' . rawurlencode((string) $copy['id']));
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }
}

$requestedStatus = strtolower(trim((string) ($_GET['status'] ?? 'active')));
$status = in_array($requestedStatus, ['active', 'archived'], true) ? $requestedStatus : 'active';
$alternateStatus = $status === 'active' ? 'archived' : 'active';
$plans = pacerPlanList($pdo, $idUsuario, ['status' => $status]);
$hasAnyPlans = $plans !== [] || pacerPlanList($pdo, $idUsuario, ['status' => $alternateStatus]) !== [];
$editId = trim((string) ($_GET['edit'] ?? ''));
$editing = $editId !== '' ? pacerPlanGet($pdo, $idUsuario, $editId) : [];
$sportsStmt = $pdo->prepare("SELECT idmodalidade,slug,nome FROM modalidades WHERE ativo=TRUE AND metrica_derivada='pace_km' AND (idusuario IS NULL OR idusuario=:user) ORDER BY CASE WHEN idusuario IS NULL THEN 0 ELSE 1 END,nome");
$sportsStmt->execute([':user' => $idUsuario]);
$sports = $sportsStmt->fetchAll();
$csrf = stridebr_csrf_token();
$defaultSport = (string) ($editing['sport']['id'] ?? ($sports[0]['idmodalidade'] ?? ''));
$editingSegments = is_array($editing['segments'] ?? null) ? $editing['segments'] : [];
$editingProgression = (float) ($editingSegments[0]['instruction_metadata']['generated_progression_percent'] ?? 8);
$editingSegmentDistance = $editingSegments !== [] ? (float) (($editingSegments[0]['end_distance_m'] ?? 2000) - ($editingSegments[0]['start_distance_m'] ?? 0)) : 2000.0;
$editingHrFloor = $editingSegments[0]['heart_rate_floor_bpm'] ?? '';
$editingHrCeiling = $editingSegments[0]['heart_rate_ceiling_bpm'] ?? '';
$editingGoalMode = (string) ($editing['goal_mode'] ?? ($editing['guidance_rules']['goal_mode'] ?? 'target_time'));
$editingClockMode = (string) ($editing['clock_mode'] ?? ($editing['guidance_rules']['clock_mode'] ?? 'auto'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/pacer-web.css')); ?>">
    <title>Pacer | StrideBR</title>
</head>
<body>
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content pacer-page" data-pacer-page data-csrf="<?php echo stridebr_e($csrf); ?>">
    <header class="pacer-head"><div><span>Treino</span><h1>Pacer</h1><p>Estratégias de ritmo para treinos e provas.</p></div><a class="secondary-button" href="/user/cronogramatreinos.php">Treinos</a></header>
    <?php foreach ($errors as $error): ?><div class="pacer-error" role="alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
    <div class="pacer-layout<?php echo !$hasAnyPlans ? ' is-first-use' : ''; ?>">
        <?php if ($hasAnyPlans): ?><section class="pacer-library">
            <div class="pacer-toolbar"><div><strong>Estratégias</strong><span><?php echo count($plans); ?></span></div><div><a class="<?php echo $status === 'active' ? 'is-active' : ''; ?>" href="/user/pacer.php">Ativas</a><a class="<?php echo $status === 'archived' ? 'is-active' : ''; ?>" href="/user/pacer.php?status=archived">Arquivadas</a></div></div>
            <?php if ($plans === []): ?><div class="pacer-empty"><strong>Nenhuma estratégia aqui.</strong><span>Crie uma estratégia para corrida ou outra modalidade baseada em pace.</span></div><?php else: ?><div class="pacer-list"><?php foreach ($plans as $plan): ?><article class="pacer-card<?php echo $editId === $plan['id'] ? ' is-active' : ''; ?>"><a href="/user/pacer.php?edit=<?php echo rawurlencode($plan['id']); ?><?php echo $status === 'archived' ? '&status=archived' : ''; ?>"><span><?php echo stridebr_e($plan['sport']['name']); ?> · <?php echo stridebr_e(number_format($plan['target_distance_m'] / 1000, 2, ',', '.')); ?> km</span><strong><?php echo stridebr_e($plan['name']); ?></strong><small><?php echo stridebr_e(stridebr_pacer_web_time($plan['target_time_s'])); ?> · <?php echo stridebr_e(stridebr_pacer_web_time($plan['target_average_pace_s_per_km'])); ?>/km · <?php echo stridebr_e(str_replace('_', ' ', $plan['strategy'])); ?></small></a><div><?php if ($plan['status'] === 'active'): ?><form method="post"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="idplan" value="<?php echo stridebr_e($plan['id']); ?>"><button type="submit">Duplicar</button></form><form method="post" data-confirm="Arquivar esta estratégia?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="archive"><input type="hidden" name="idplan" value="<?php echo stridebr_e($plan['id']); ?>"><button type="submit">Arquivar</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?>
        </section><?php endif; ?>
        <section class="pacer-editor">
            <form method="post" data-pacer-form>
                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="generated_options_changed" value="" data-generated-options-changed><input type="hidden" name="idplan" value="<?php echo stridebr_e((string) ($editing['id'] ?? '')); ?>">
                <div class="pacer-editor-head"><div><span><?php echo $editing ? 'Editar estratégia' : 'Nova estratégia'; ?></span><strong><?php echo stridebr_e((string) ($editing['name'] ?? (!$hasAnyPlans ? 'Defina distância, tempo e estratégia' : 'Plano de pace'))); ?></strong></div><?php if ($editing): ?><a href="/user/pacer.php">Novo</a><?php endif; ?></div>
                <div class="pacer-fields">
                    <label class="is-wide"><span>Nome</span><input type="text" name="name" maxlength="120" value="<?php echo stridebr_e((string) ($editing['name'] ?? '')); ?>" placeholder="Ex.: 10 km · 52:00"></label>
                    <label><span>Modalidade</span><select name="sport" required data-pacer-sport><?php foreach ($sports as $sport): ?><option value="<?php echo stridebr_e((string) $sport['idmodalidade']); ?>"<?php echo $defaultSport === (string) $sport['idmodalidade'] ? ' selected' : ''; ?>><?php echo stridebr_e((string) $sport['nome']); ?></option><?php endforeach; ?></select></label>
                    <label><span>Distância</span><div class="pacer-input-unit"><input type="number" name="target_distance_km" step="0.01" min="0.2" max="500" required value="<?php echo $editing ? stridebr_e(rtrim(rtrim(number_format($editing['target_distance_m'] / 1000, 3, '.', ''), '0'), '.')) : '10'; ?>"><b>km</b></div></label>
                    <fieldset class="pacer-goal is-wide"><legend>Objetivo</legend><label><input type="radio" name="goal_mode" value="target_time"<?php echo $editingGoalMode !== 'best_effort' ? ' checked' : ''; ?>><span><b>Atingir uma marca</b><small>Execute o plano para chegar perto do tempo definido.</small></span></label><label><input type="radio" name="goal_mode" value="best_effort"<?php echo $editingGoalMode === 'best_effort' ? ' checked' : ''; ?>><span><b>Buscar o melhor tempo</b><small>Use uma referência realista para orientar o plano e fechar mais forte quando houver margem.</small></span></label></fieldset>
                    <label><span data-target-time-label><?php echo $editingGoalMode === 'best_effort' ? 'Tempo de referência' : 'Tempo-alvo'; ?></span><input type="text" name="target_time" inputmode="numeric" required value="<?php echo $editing ? stridebr_e(stridebr_pacer_web_time($editing['target_time_s'])) : '52:00'; ?>" placeholder="52:00"></label>
                    <label><span>Estratégia</span><select name="strategy" data-pacer-strategy><option value="even"<?php echo ($editing['strategy'] ?? '') === 'even' ? ' selected' : ''; ?>>Ritmo constante</option><option value="negative_split"<?php echo ($editing['strategy'] ?? 'negative_split') === 'negative_split' ? ' selected' : ''; ?>>Negative split</option><option value="custom"<?php echo ($editing['strategy'] ?? '') === 'custom' ? ' selected' : ''; ?>>Custom</option><option value="positive_split"<?php echo ($editing['strategy'] ?? '') === 'positive_split' ? ' selected' : ''; ?>>Positive split</option></select></label>
                </div>
                <details class="pacer-advanced"><summary>Opções avançadas</summary><div class="pacer-generation-fields" data-generated-options>
                    <label><span>Tolerância</span><div class="pacer-input-unit"><input type="number" name="tolerance_s_per_km" min="0" max="600" step="1" value="<?php echo stridebr_e((string) ($editing['default_tolerance_s_per_km'] ?? 10)); ?>"><b>s/km</b></div></label>
                    <label data-progressive-option><span>Progressão</span><div class="pacer-input-unit"><input type="number" name="progression_percent" min="0.1" max="30" step="0.5" value="<?php echo stridebr_e((string) $editingProgression); ?>"><b>%</b></div></label>
                    <label data-progressive-option><span>Tamanho do segmento</span><div class="pacer-input-unit"><input type="number" name="segment_distance_m" min="200" step="100" value="<?php echo stridebr_e((string) $editingSegmentDistance); ?>"><b>m</b></div></label>
                    <label data-global-hr><span>FC mínima opcional</span><div class="pacer-input-unit"><input type="number" name="heart_rate_floor_bpm" min="20" max="260" value="<?php echo stridebr_e((string) $editingHrFloor); ?>"><b>bpm</b></div></label>
                    <label data-global-hr><span>FC máxima opcional</span><div class="pacer-input-unit"><input type="number" name="heart_rate_ceiling_bpm" min="20" max="260" value="<?php echo stridebr_e((string) $editingHrCeiling); ?>"><b>bpm</b></div></label>
                    <label><span>Relógio</span><select name="clock_mode"><option value="auto"<?php echo $editingClockMode === 'auto' ? ' selected' : ''; ?>>Automático</option><option value="elapsed"<?php echo $editingClockMode === 'elapsed' ? ' selected' : ''; ?>>Tempo decorrido</option><option value="moving"<?php echo $editingClockMode === 'moving' ? ' selected' : ''; ?>>Tempo em movimento</option></select></label>
                </div></details>
                <div class="pacer-custom" data-custom-segments hidden><div class="pacer-section-head"><div><span>Segmentos</span><strong>Distância e alvo</strong></div><button type="button" class="secondary-button" data-add-pacer-segment>Adicionar segmento</button></div><div data-pacer-segments><?php foreach ($editingSegments as $index => $segment): ?><div class="pacer-segment-row" data-pacer-segment><input type="number" step="0.1" name="segments[<?php echo $index; ?>][start_km]" value="<?php echo stridebr_e((string) ($segment['start_distance_m'] / 1000)); ?>" aria-label="Início em km"><input type="number" step="0.1" name="segments[<?php echo $index; ?>][end_km]" value="<?php echo stridebr_e((string) ($segment['end_distance_m'] / 1000)); ?>" aria-label="Fim em km"><input type="text" name="segments[<?php echo $index; ?>][pace]" value="<?php echo stridebr_e(stridebr_pacer_web_time((float) $segment['target_pace_s_per_km'])); ?>" aria-label="Pace alvo"><input type="number" name="segments[<?php echo $index; ?>][tolerance]" value="<?php echo stridebr_e((string) $segment['tolerance_s_per_km']); ?>" aria-label="Tolerância"><input type="number" name="segments[<?php echo $index; ?>][hr_floor]" value="<?php echo stridebr_e((string) ($segment['heart_rate_floor_bpm'] ?? '')); ?>" aria-label="FC mínima"><input type="number" name="segments[<?php echo $index; ?>][hr_ceiling]" value="<?php echo stridebr_e((string) ($segment['heart_rate_ceiling_bpm'] ?? '')); ?>" aria-label="FC máxima"><button type="button" data-remove-pacer-segment aria-label="Remover segmento">×</button></div><?php endforeach; ?></div></div>
                <section class="pacer-preview" data-pacer-preview><div class="pacer-preview-empty"><strong>Prévia da estratégia</strong><span>Preencha distância e tempo-alvo.</span></div></section>
                <section class="pacer-final-explainer"><strong>Fase final</strong><span>Se você estiver no alvo ou melhor, o Pacer pode liberar um final progressivo.</span><span data-best-effort-explainer<?php echo $editingGoalMode === 'best_effort' ? '' : ' hidden'; ?>>Oportunidades de marca aparecem somente quando o ritmo necessário for plausível.</span></section>
                <p class="pacer-note">Limites de frequência cardíaca são regras configuradas por você ou seu treinador. Não são recomendação médica.</p>
                <div class="pacer-actions"><a class="secondary-button" href="/user/pacer.php">Cancelar</a><button type="submit" class="primary-button">Salvar</button></div>
            </form>
        </section>
    </div>
</main>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/pacer-web.js')); ?>"></script>
</body>
</html>
