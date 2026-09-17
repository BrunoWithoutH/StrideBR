<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/layout/ads.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

$errors = [];
$editId = trim((string) ($_GET['edit'] ?? $_POST['id'] ?? ''));
$viewId = trim((string) ($_GET['view'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    try {
        if ($action === 'toggle') {
            $id = trim((string) ($_POST['id'] ?? ''));
            $ativo = (string) ($_POST['ativo'] ?? '0') === '1';
            if (!atividadeDefinirEquipamentoAtivo($pdo, $idUsuario, $id, $ativo)) {
                throw new InvalidArgumentException(stridebr_t('equipment.error.not_found'));
            }
            stridebr_flash('success', $ativo ? stridebr_t('equipment.flash.reactivated') : stridebr_t('equipment.flash.archived'));
        } else {
            atividadeSalvarEquipamento($pdo, $idUsuario, $_POST, $editId !== '' ? $editId : null);
            stridebr_flash('success', $editId !== '' ? stridebr_t('equipment.flash.updated') : stridebr_t('equipment.flash.added'));
        }
        header('Location: /user/equipamentos.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : stridebr_t('equipment.error.save');
        if (!$e instanceof InvalidArgumentException) error_log($e->getMessage());
    }
}

$equipamentos = atividadeListarEquipamentos($pdo, $idUsuario, false);
$editando = null;
if ($editId !== '') {
    foreach ($equipamentos as $equipamento) {
        if ((string) $equipamento['idequipamento'] === $editId) {
            $editando = $equipamento;
            break;
        }
    }
}

$categorias = [
    'tenis' => stridebr_t('equipment.category.shoes'),
    'bicicleta' => stridebr_t('equipment.category.bike'),
    'raquete' => stridebr_t('equipment.category.racket'),
    'relogio' => stridebr_t('equipment.category.watch'),
    'protecao' => stridebr_t('equipment.category.protection'),
    'outro' => stridebr_t('equipment.category.other'),
];
$equipmentHistoryFilters = ['q' => trim((string) ($_GET['eq_q'] ?? '')), 'from' => trim((string) ($_GET['eq_from'] ?? '')), 'to' => trim((string) ($_GET['eq_to'] ?? ''))];
$detalheEquipamento = $viewId !== '' ? atividadeDetalheEquipamento($pdo, $idUsuario, $viewId, 100, $equipmentHistoryFilters) : [];
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/atividades.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('equipment.page_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content activities-page equipment-page">
        <header class="activities-toolbar">
            <div class="activities-toolbar-title"><h1><?php echo stridebr_e(stridebr_t('activity.equipment')); ?></h1></div>
            <div class="activities-toolbar-actions"><a class="activity-toolbar-link" data-safe-back href="/user/atividades.php">← <?php echo stridebr_e(stridebr_t('equipment.back_activities')); ?></a></div>
        </header>

        <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?> activity-alert"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
        <?php foreach ($errors as $error): ?><div class="alert alert-danger activity-alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

        <div class="equipment-layout">
            <section class="equipment-panel">
                <div class="equipment-panel-heading"><h2><?php echo stridebr_e($editando ? stridebr_t('equipment.edit') : stridebr_t('equipment.add')); ?></h2></div>
                <form method="POST" class="equipment-form" id="equipment-form">
                    <?php echo stridebr_csrf_field(); ?>
                    <?php if ($editando): ?><input type="hidden" name="id" value="<?php echo stridebr_e($editando['idequipamento']); ?>"><?php endif; ?>
                    <div class="activity-record-fields">
                        <div class="input-field"><label for="nome"><?php echo stridebr_e(stridebr_t('common.name')); ?></label><input id="nome" name="nome" maxlength="120" required value="<?php echo stridebr_e($editando['nome'] ?? ''); ?>" placeholder="<?php echo stridebr_e(stridebr_t('equipment.placeholder_name')); ?>"></div>
                        <div class="input-field"><label for="categoria"><?php echo stridebr_e(stridebr_t('equipment.category')); ?></label><select id="categoria" name="categoria"><?php foreach ($categorias as $value => $label): ?><option value="<?php echo $value; ?>"<?php echo (($editando['categoria'] ?? 'outro') === $value) ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?></select></div>
                        <div class="input-field"><label for="marca"><?php echo stridebr_e(stridebr_t('equipment.brand')); ?></label><input id="marca" name="marca" maxlength="80" value="<?php echo stridebr_e($editando['marca'] ?? ''); ?>"></div>
                        <div class="input-field"><label for="modelo"><?php echo stridebr_e(stridebr_t('equipment.model')); ?></label><input id="modelo" name="modelo" maxlength="100" value="<?php echo stridebr_e($editando['modelo'] ?? ''); ?>"></div>
                        <div class="input-field"><label for="data_inicio_uso"><?php echo stridebr_e(stridebr_t('equipment.start_use')); ?></label><input type="date" id="data_inicio_uso" name="data_inicio_uso" value="<?php echo stridebr_e($editando['data_inicio_uso'] ?? ''); ?>"></div>
                        <div class="input-field"><label for="distancia_inicial_km"><?php echo stridebr_e(stridebr_t('equipment.previous_mileage')); ?> <span class="field-unit">km</span></label><input type="number" min="0" step="0.001" inputmode="decimal" id="distancia_inicial_km" name="distancia_inicial_km" value="<?php echo stridebr_e($editando['distancia_inicial_km'] ?? '0'); ?>"></div>
                        <div class="input-field"><label for="limite_alerta_km">Alerta de uso <span class="field-unit">km</span></label><input type="number" min="1" step="1" id="limite_alerta_km" name="limite_alerta_km" value="<?php echo stridebr_e($editando['limite_alerta_km'] ?? ''); ?>" placeholder="600"></div>
                        <div class="input-field"><label for="data_fim_uso">Fim de uso</label><input type="date" id="data_fim_uso" name="data_fim_uso" value="<?php echo stridebr_e($editando['data_fim_uso'] ?? ''); ?>"></div>
                    </div>
                    <div class="input-field equipment-notes"><label for="observacoes"><?php echo stridebr_e(stridebr_t('activity.notes')); ?></label><textarea id="observacoes" name="observacoes" rows="3"><?php echo stridebr_e($editando['observacoes'] ?? ''); ?></textarea></div>
                    <div class="activity-form-actions">
                        <?php if ($editando): ?><a class="activity-secondary-button" href="/user/equipamentos.php"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a><?php endif; ?>
                        <button class="activity-primary-action" type="submit"><?php echo stridebr_e($editando ? stridebr_t('equipment.save_changes') : stridebr_t('equipment.add')); ?></button>
                    </div>
                </form>
            </section>

            <section class="equipment-panel">
                <div class="equipment-panel-heading"><h2><?php echo stridebr_e(stridebr_t('equipment.my')); ?></h2><span><?php echo stridebr_e(stridebr_t('equipment.accumulated_help')); ?></span></div>
                <?php if (!$equipamentos): ?>
                    <div class="activity-empty-state"><strong><?php echo stridebr_e(stridebr_t('equipment.empty')); ?></strong><a class="activity-empty-action" href="#equipment-form"><?php echo stridebr_e(stridebr_t('activity.add_first_equipment')); ?></a></div>
                <?php else: ?>
                    <div class="equipment-list">
                        <?php foreach ($equipamentos as $equipamento): ?>
                            <article class="equipment-row<?php echo stridebr_db_bool($equipamento['ativo']) ? '' : ' is-archived'; ?>">
                                <div class="equipment-row-main">
                                    <strong><?php echo stridebr_e($equipamento['nome']); ?></strong>
                                    <span><?php echo stridebr_e($categorias[$equipamento['categoria']] ?? ucfirst((string) $equipamento['categoria'])); ?><?php echo $equipamento['marca'] ? ' · ' . stridebr_e($equipamento['marca']) : ''; ?><?php echo $equipamento['modelo'] ? ' ' . stridebr_e($equipamento['modelo']) : ''; ?></span>
                                </div>
                                <div class="equipment-row-stat"><strong><?php echo stridebr_e(stridebr_format_number((float) $equipamento['distancia_total_km'], 1)); ?> km</strong><span><?php echo stridebr_e(stridebr_t('equipment.distance')); ?></span></div>
                                <div class="equipment-row-stat"><strong><?php echo (int) $equipamento['total_atividades']; ?></strong><span><?php echo stridebr_e(stridebr_tn('equipment.activities.one', 'equipment.activities.other', (int) $equipamento['total_atividades'], ['count' => ''])); ?></span></div>
                                <div class="equipment-row-actions">
                                    <a href="/user/equipamentos.php?view=<?php echo rawurlencode($equipamento['idequipamento']); ?>">Abrir</a><a href="/user/equipamentos.php?edit=<?php echo rawurlencode($equipamento['idequipamento']); ?>"><?php echo stridebr_e(stridebr_t('activity.edit')); ?></a>
                                    <form method="POST">
                                        <?php echo stridebr_csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo stridebr_e($equipamento['idequipamento']); ?>">
                                        <input type="hidden" name="ativo" value="<?php echo stridebr_db_bool($equipamento['ativo']) ? '0' : '1'; ?>">
                                        <button type="submit"><?php echo stridebr_e(stridebr_db_bool($equipamento['ativo']) ? stridebr_t('equipment.archive') : stridebr_t('equipment.reactivate')); ?></button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php if ($detalheEquipamento): ?>
            <section class="equipment-panel equipment-detail-v2">
                <div class="equipment-panel-heading"><div><h2><?php echo stridebr_e((string) $detalheEquipamento['nome']); ?></h2><span><?php echo stridebr_e($categorias[$detalheEquipamento['categoria']] ?? ucfirst((string) $detalheEquipamento['categoria'])); ?></span></div><a class="activity-secondary-button" href="/user/equipamentos.php?edit=<?php echo rawurlencode((string) $detalheEquipamento['idequipamento']); ?>">Editar</a></div>
                <div class="equipment-detail-metrics">
                    <div><strong><?php echo stridebr_e(stridebr_format_number((float) $detalheEquipamento['distancia_total_km'],1)); ?> km</strong><span>Distância</span></div>
                    <div><strong><?php echo (int) $detalheEquipamento['total_atividades']; ?></strong><span>Atividades</span></div>
                    <div><strong><?php echo stridebr_e(stridebr_format_number((float) $detalheEquipamento['duracao_total_s']/3600,1)); ?> h</strong><span>Duração</span></div>
                </div>
                <?php if (!empty($detalheEquipamento['limite_alerta_km'])): $ratio=min(100,max(0,(float)$detalheEquipamento['distancia_total_km']/(float)$detalheEquipamento['limite_alerta_km']*100)); ?>
                <div class="equipment-usage"><div><span>Uso configurado</span><strong><?php echo stridebr_e(stridebr_format_number((float)$detalheEquipamento['distancia_total_km'],1)); ?> / <?php echo stridebr_e(stridebr_format_number((float)$detalheEquipamento['limite_alerta_km'],0)); ?> km</strong></div><progress max="100" value="<?php echo stridebr_e((string)$ratio); ?>"></progress></div>
                <?php endif; ?>
                <div class="equipment-detail-dates"><span>Primeiro uso: <strong><?php echo $detalheEquipamento['primeiro_uso'] ? stridebr_e(stridebr_format_date((string)$detalheEquipamento['primeiro_uso'])) : '—'; ?></strong></span><span>Último uso: <strong><?php echo $detalheEquipamento['ultimo_uso'] ? stridebr_e(stridebr_format_date((string)$detalheEquipamento['ultimo_uso'])) : '—'; ?></strong></span></div>
                <div class="equipment-history-v2"><div class="equipment-history-heading"><h3>Histórico</h3><form method="get" class="equipment-history-filters"><input type="hidden" name="view" value="<?php echo stridebr_e((string)$detalheEquipamento['idequipamento']); ?>"><label>Buscar<input type="search" name="eq_q" value="<?php echo stridebr_e($equipmentHistoryFilters['q']); ?>" placeholder="Activity ou modalidade"></label><label>De<input type="date" name="eq_from" value="<?php echo stridebr_e($equipmentHistoryFilters['from']); ?>"></label><label>Até<input type="date" name="eq_to" value="<?php echo stridebr_e($equipmentHistoryFilters['to']); ?>"></label><button type="submit">Filtrar</button><?php if(array_filter($equipmentHistoryFilters,static fn($v)=>$v!=='')): ?><a href="/user/equipamentos.php?view=<?php echo rawurlencode((string)$detalheEquipamento['idequipamento']); ?>">Limpar</a><?php endif; ?></form></div><?php if (empty($detalheEquipamento['atividades'])): ?><p>Nenhuma atividade com este equipamento.</p><?php else: ?><?php foreach ($detalheEquipamento['atividades'] as $atividade): ?><a href="/user/atividades.php?activity=<?php echo rawurlencode((string)$atividade['idregistro']); ?>"><span><strong><?php echo stridebr_e(stridebr_present_activity_title((string)($atividade['titulo'] ?: $atividade['modalidade_nome']), (string)$atividade['modalidade_slug'])); ?></strong><small><?php echo stridebr_e(stridebr_format_date((string)$atividade['data_inicio'])); ?></small></span><span><?php echo is_numeric($atividade['distancia_metros']??null)?stridebr_e(stridebr_format_number((float)$atividade['distancia_metros']/1000,2)).' km':'—'; ?></span></a><?php endforeach; ?><?php endif; ?></div>
            </section>
            <?php endif; ?>
            <?php stridebr_render_ad_slot('equipment-end', '/user/equipamentos.php', true); ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
