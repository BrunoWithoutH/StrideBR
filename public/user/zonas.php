<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/function/activity_stream_service.php';
require_once dirname(__DIR__, 2) . '/src/function/zone_profile_service.php';

date_default_timezone_set('America/Sao_Paulo');
$errors = [];
$returnTo = zoneProfileReturnTo((string) ($_REQUEST['return_to'] ?? ''));

function stridebr_zone_pace_seconds(string $value): ?float
{
    $value = trim($value);
    if ($value === '') return null;
    if (is_numeric($value)) return (float) $value;
    if (preg_match('/^(\d{1,3}):([0-5]\d)$/', $value, $match) !== 1) throw new InvalidArgumentException('Use pace no formato mm:ss.');
    return ((int) $match[1]) * 60 + (int) $match[2];
}

function stridebr_zone_pace_text(mixed $value): string
{
    if (!is_numeric($value)) return '';
    $seconds = max(0, (int) round((float) $value));
    return intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = trim((string) ($_POST['action'] ?? 'save'));
    try {
        if ($action === 'delete') {
            zoneProfileDelete($pdo, $idUsuario, trim((string) ($_POST['idprofile'] ?? '')));
            stridebr_flash('success', 'Perfil de zonas excluído.');
            header('Location: /user/zonas.php' . ($returnTo !== '' ? '?return_to=' . rawurlencode($returnTo) : ''));
            exit;
        }
        $type = zoneProfileNormalizeType((string) ($_POST['profile_type'] ?? ''));
        $zones = [];
        foreach ((array) ($_POST['zones'] ?? []) as $zone) {
            if (!is_array($zone)) continue;
            $code = strtoupper(trim((string) ($zone['code'] ?? '')));
            $label = trim((string) ($zone['label'] ?? ''));
            if ($code === '' && $label === '' && trim((string) ($zone['min'] ?? '')) === '' && trim((string) ($zone['max'] ?? '')) === '') continue;
            $min = $type === 'pace' ? stridebr_zone_pace_seconds((string) ($zone['min'] ?? '')) : (trim((string) ($zone['min'] ?? '')) === '' ? null : (float) $zone['min']);
            $max = $type === 'pace' ? stridebr_zone_pace_seconds((string) ($zone['max'] ?? '')) : (trim((string) ($zone['max'] ?? '')) === '' ? null : (float) $zone['max']);
            $zones[] = ['code' => $code, 'label' => $label, 'min' => $min, 'max' => $max];
        }
        zoneProfileSave($pdo, $idUsuario, [
            'profile_type' => $type,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'sport' => trim((string) ($_POST['sport'] ?? '')),
            'is_default' => !empty($_POST['is_default']),
            'zones' => $zones,
        ], trim((string) ($_POST['idprofile'] ?? '')) ?: null);
        stridebr_flash('success', 'Perfil de zonas salvo.');
        header('Location: ' . ($returnTo !== '' ? $returnTo : '/user/zonas.php'));
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível salvar o perfil de zonas.';
    }
}

$profiles = zoneProfileList($pdo, $idUsuario);
$catalog = atividadeListarCatalogo($pdo, $idUsuario);
$editId = trim((string) ($_GET['edit'] ?? ''));
$returnQuery = $returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : '';
$editing = $editId !== '' ? zoneProfileGet($pdo, $idUsuario, $editId) : [];
$type = (string) ($editing['profile_type'] ?? ($_POST['profile_type'] ?? 'heart_rate'));
$name = (string) ($editing['name'] ?? ($_POST['name'] ?? ''));
$sport = (string) ($editing['sport']['id'] ?? ($_POST['sport'] ?? ''));
$isDefault = !empty($editing['is_default']) || !empty($_POST['is_default']);
$zones = is_array($editing['zones'] ?? null) ? $editing['zones'] : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($_POST['zones'] ?? null)) $zones = array_values($_POST['zones']);
if ($zones === []) {
    for ($i = 1; $i <= 5; $i++) $zones[] = ['code' => 'Z' . $i, 'label' => '', 'min' => '', 'max' => ''];
}
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/zones.css')); ?>">
    <title>Zonas | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content zones-page" data-zone-page>
        <header class="zones-page-head">
            <div><span>Configuração esportiva</span><h1>Zonas</h1><p>Defina seus próprios limites de frequência cardíaca e pace. O StrideBR não calcula zonas por idade.</p></div>
            <div class="zones-page-actions"><button type="button" class="activity-secondary-button" data-zones-page-help>ⓘ <?php echo stridebr_e(stridebr_t('zones.help_title')); ?></button><a class="activity-secondary-button" href="/user/atividades.php">Atividades</a></div>
        </header>
        <dialog class="zones-help-dialog" data-zones-help-dialog aria-labelledby="zones-help-title">
            <form method="dialog">
                <header><strong id="zones-help-title"><?php echo stridebr_e(stridebr_t('zones.help_title')); ?></strong><button type="submit" value="cancel" aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>">×</button></header>
                <p><?php echo stridebr_e(stridebr_t('zones.help_one')); ?></p>
                <p><?php echo stridebr_e(stridebr_t('zones.help_two')); ?></p>
                <p><?php echo stridebr_e(stridebr_t('zones.help_three')); ?></p>
                <div><button type="submit" class="activity-primary-action"><?php echo stridebr_e(stridebr_t('common.close')); ?></button></div>
            </form>
        </dialog>
        <?php foreach ($errors as $error): ?><div class="zones-error" role="alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
        <div class="zones-layout">
            <section class="zones-list">
                <div class="zones-section-head"><div><span>Perfis</span><strong><?php echo count($profiles); ?> configurado<?php echo count($profiles) === 1 ? '' : 's'; ?></strong></div><a href="/user/zonas.php?return_to=<?php echo rawurlencode($returnTo); ?>" class="activity-primary-action">Novo perfil</a></div>
                <?php if ($profiles === []): ?>
                    <div class="zones-empty"><strong>Nenhum perfil ainda.</strong><span>Crie um perfil manual para habilitar distribuição por zonas nas Activities.</span></div>
                <?php else: ?>
                    <div class="zones-profile-list">
                        <?php foreach ($profiles as $profile): ?>
                            <article class="zones-profile-card<?php echo $editId === (string) $profile['id'] ? ' is-active' : ''; ?>">
                                <div><span><?php echo stridebr_e($profile['profile_type'] === 'heart_rate' ? 'Frequência cardíaca' : 'Pace'); ?></span><strong><?php echo stridebr_e((string) $profile['name']); ?></strong><small><?php echo stridebr_e((string) ($profile['sport']['name'] ?? 'Todas as modalidades compatíveis')); ?><?php echo !empty($profile['is_default']) ? ' · Padrão' : ''; ?></small></div>
                                <div class="zones-profile-actions"><a href="/user/zonas.php?edit=<?php echo rawurlencode((string) $profile['id']); ?><?php echo $returnQuery; ?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este perfil de zonas?')"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>"><input type="hidden" name="idprofile" value="<?php echo stridebr_e((string) $profile['id']); ?>"><button type="submit">Excluir</button></form></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <section class="zones-editor">
                <div class="zones-section-head"><div><span><?php echo $editing ? 'Editar perfil' : 'Novo perfil'; ?></span><strong><?php echo $editing ? stridebr_e((string) $editing['name']) : 'Limites manuais'; ?></strong></div></div>
                <form method="post" data-zone-form>
                    <?php echo stridebr_csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="idprofile" value="<?php echo stridebr_e((string) ($editing['id'] ?? '')); ?>">
                    <input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>">
                    <div class="zones-fields">
                        <label><span>Tipo</span><select name="profile_type" data-zone-type><option value="heart_rate"<?php echo $type === 'heart_rate' ? ' selected' : ''; ?>>Frequência cardíaca</option><option value="pace"<?php echo $type === 'pace' ? ' selected' : ''; ?>>Pace</option></select></label>
                        <label><span>Nome</span><input type="text" name="name" maxlength="80" required value="<?php echo stridebr_e($name); ?>" placeholder="Ex.: Zonas corrida"></label>
                        <label><span>Modalidade</span><select name="sport"><option value="">Todas as compatíveis</option><?php foreach ($catalog as $item): ?><option value="<?php echo stridebr_e((string) $item['idmodalidade']); ?>"<?php echo $sport === (string) $item['idmodalidade'] ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_sport_name((string) $item['slug'], (string) $item['nome'])); ?></option><?php endforeach; ?></select></label>
                        <label class="zones-default"><input type="checkbox" name="is_default" value="1"<?php echo $isDefault ? ' checked' : ''; ?>><span>Usar como padrão neste escopo</span></label>
                    </div>
                    <div class="zones-table-head"><div><strong>Faixas</strong><span data-zone-unit><?php echo $type === 'pace' ? 'mm:ss/km' : 'bpm'; ?></span></div><button type="button" class="activity-secondary-button" data-zone-add>Adicionar zona</button></div>
                    <div class="zones-rows" data-zone-rows>
                        <?php foreach ($zones as $index => $zone): ?>
                            <?php $zoneMin = $type === 'pace' && is_numeric($zone['min'] ?? null) ? stridebr_zone_pace_text($zone['min']) : (string) ($zone['min'] ?? ''); $zoneMax = $type === 'pace' && is_numeric($zone['max'] ?? null) ? stridebr_zone_pace_text($zone['max']) : (string) ($zone['max'] ?? ''); ?>
                            <div class="zones-row" data-zone-row>
                                <input type="text" name="zones[<?php echo $index; ?>][code]" maxlength="20" required value="<?php echo stridebr_e((string) ($zone['code'] ?? '')); ?>" placeholder="Z<?php echo $index + 1; ?>" aria-label="Código da zona">
                                <input type="text" name="zones[<?php echo $index; ?>][label]" maxlength="80" value="<?php echo stridebr_e((string) ($zone['label'] ?? '')); ?>" placeholder="Nome opcional" aria-label="Nome da zona">
                                <label><span>De</span><input type="text" inputmode="decimal" name="zones[<?php echo $index; ?>][min]" value="<?php echo stridebr_e($zoneMin); ?>" data-zone-bound="min"></label>
                                <label><span>Até</span><input type="text" inputmode="decimal" name="zones[<?php echo $index; ?>][max]" value="<?php echo stridebr_e($zoneMax); ?>" data-zone-bound="max"></label>
                                <button type="button" data-zone-remove aria-label="Remover zona">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="zones-help">As faixas precisam ser contínuas. Somente a primeira pode ficar sem limite inferior e somente a última sem limite superior.</p>
                    <div class="zones-form-actions"><a class="activity-secondary-button" href="<?php echo stridebr_e($returnTo !== '' ? $returnTo : '/user/zonas.php'); ?>">Cancelar</a><button class="activity-primary-action" type="submit">Salvar</button></div>
                </form>
            </section>
        </div>
    </main>
    <?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/zone-profiles.js')); ?>"></script>
</body>
</html>
