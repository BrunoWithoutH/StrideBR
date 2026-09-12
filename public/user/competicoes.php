<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/competitions.php';
require_once dirname(__DIR__, 2) . '/src/function/benchmarks.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';

date_default_timezone_set('America/Sao_Paulo');
if (!competitionTableExists($pdo)) {
    stridebr_error_document(503);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    try {
        $action = stridebr_lower(trim((string) ($_POST['action'] ?? 'create')));
        if ($action === 'delete') {
            $id = trim((string) ($_POST['idcompeticao'] ?? ''));
            if ($id === '' || !competitionDelete($pdo, $idUsuario, $id)) throw new InvalidArgumentException(stridebr_t('competitions.error.not_found'));
            stridebr_flash('success', stridebr_t('competitions.flash.deleted'));
            header('Location: /user/competicoes.php', true, 303);
            exit;
        }
        $payload = [
            'nome' => $_POST['nome'] ?? '',
            'data_inicio' => $_POST['data_inicio'] ?? '',
            'data_fim' => $_POST['data_fim'] ?? '',
            'status' => $_POST['status'] ?? '',
            'idmodalidade_principal' => $_POST['idmodalidade_principal'] ?? '',
            'idevento' => $_POST['idevento'] ?? '',
            'tipo' => $_POST['tipo'] ?? '',
            'organizador' => $_POST['organizador'] ?? '',
            'local_nome' => $_POST['local_nome'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
            'estado' => $_POST['estado'] ?? '',
            'pais' => $_POST['pais'] ?? '',
            'nivel' => $_POST['nivel'] ?? '',
            'observacoes' => $_POST['observacoes'] ?? '',
            'oficialidade' => isset($_POST['reported_official']) ? 'informado_oficial' : ($_POST['oficialidade'] ?? 'nao_informada'),
            'origem' => $_POST['origem'] ?? 'manual',
        ];
        if ($action === 'update') {
            $id = trim((string) ($_POST['idcompeticao'] ?? ''));
            $saved = competitionUpdate($pdo, $idUsuario, $id, $payload);
            stridebr_flash('success', stridebr_t('competitions.flash.updated'));
        } elseif ($action === 'create') {
            $saved = competitionCreate($pdo, $idUsuario, $payload);
            stridebr_flash('success', stridebr_t('competitions.flash.created'));
        } else {
            throw new InvalidArgumentException(stridebr_t('competitions.error.invalid_action'));
        }
        header('Location: /user/competicoes.php?id=' . rawurlencode((string)$saved['idcompeticao']), true, 303);
        exit;
    } catch (Throwable $error) {
        if (!$error instanceof InvalidArgumentException) error_log('StrideBR competitions: ' . $error->getMessage());
        stridebr_flash('danger', $error instanceof InvalidArgumentException ? $error->getMessage() : stridebr_t('competitions.error.save_failed'));
        $return = trim((string) ($_POST['idcompeticao'] ?? '')) !== '' ? '/user/competicoes.php?edit=' . rawurlencode((string)$_POST['idcompeticao']) : '/user/competicoes.php?new=1';
        header('Location: ' . $return, true, 303);
        exit;
    }
}

$eventId = trim((string) ($_GET['event'] ?? ''));
if (isset($_GET['new']) && $eventId !== '') {
    $existingForEvent = competitionFindByEvent($pdo, $idUsuario, $eventId);
    if (is_array($existingForEvent)) {
        header('Location: /user/competicoes.php?id=' . rawurlencode((string)$existingForEvent['idcompeticao']), true, 302);
        exit;
    }
}

$editId = trim((string) ($_GET['edit'] ?? ''));
$detailId = trim((string) ($_GET['id'] ?? ''));
$editCompetition = $editId !== '' ? competitionGet($pdo, $idUsuario, $editId) : null;
$detailCompetition = $detailId !== '' ? competitionGet($pdo, $idUsuario, $detailId) : null;
if ($editId !== '' && !is_array($editCompetition)) stridebr_error_document(404);
if ($detailId !== '' && !is_array($detailCompetition)) stridebr_error_document(404);

$prefill = isset($_GET['new']) && $eventId !== '' ? competitionPrefillFromEvent($pdo, $eventId) : [];
$form = is_array($editCompetition) ? $editCompetition : $prefill;
$isFormOpen = isset($_GET['new']) || is_array($editCompetition);
$modalities = competitionAvailableModalities($pdo, $idUsuario);
$all = competitionList($pdo, $idUsuario);
$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$upcoming = array_values(array_filter($all, static fn(array $row): bool => (string)($row['status']??'') === 'planejada' && (string)($row['data_inicio']??'') >= $today));
$recent = array_values(array_filter($all, static fn(array $row): bool => !in_array($row, $upcoming, true)));
$activities = is_array($detailCompetition) ? competitionActivities($pdo, $idUsuario, (string)$detailCompetition['idcompeticao']) : [];
$benchmarks = is_array($detailCompetition) ? competitionBenchmarks($pdo, $idUsuario, (string)$detailCompetition['idcompeticao']) : [];
$flashes = stridebr_take_flashes();
$formatDateRange = static function(array $row): string {
    try { $start=new DateTimeImmutable((string)$row['data_inicio']); } catch(Throwable) { return ''; }
    $endRaw=trim((string)($row['data_fim']??''));
    if ($endRaw==='') return stridebr_format_date_short($start);
    try { $end=new DateTimeImmutable($endRaw); } catch(Throwable) { return stridebr_format_date_short($start); }
    if ($end->format('Y-m-d')===$start->format('Y-m-d')) return stridebr_format_date_short($start);
    return stridebr_format_date_short($start) . ' – ' . stridebr_format_date_short($end);
};
?>
<!DOCTYPE html>
<html lang="<?php echo stridebr_e(stridebr_html_lang()); ?>">
<head>
    <?php echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/competitions.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('competitions.title')); ?> | StrideBR</title>
</head>
<body><div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="page-shell competitions-page">
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string)($flash['type']??'info')); ?>"><?php echo stridebr_e((string)($flash['message']??'')); ?></div><?php endforeach; ?>
    <header class="competitions-page-head"><div><span class="competitions-eyebrow"><?php echo stridebr_e(stridebr_t('competitions.mine')); ?></span><h1><?php echo stridebr_e(stridebr_t('competitions.title')); ?></h1></div><a class="primary-button" href="/user/competicoes.php?new=1"><?php echo stridebr_e(stridebr_t('competitions.register')); ?></a></header>

    <?php if ($isFormOpen): ?>
    <section class="content-card competition-form-card" aria-labelledby="competition-form-title">
        <header><div><h2 id="competition-form-title"><?php echo stridebr_e(stridebr_t(is_array($editCompetition)?'competitions.edit':'competitions.register')); ?></h2><p><?php echo stridebr_e(stridebr_t('competitions.form_help')); ?></p></div><a href="/user/competicoes.php"><?php echo stridebr_e(stridebr_t('common.close')); ?></a></header>
        <form method="post" class="competition-form">
            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="<?php echo is_array($editCompetition)?'update':'create'; ?>"><?php if(is_array($editCompetition)): ?><input type="hidden" name="idcompeticao" value="<?php echo stridebr_e((string)$editCompetition['idcompeticao']); ?>"><?php endif; ?><input type="hidden" name="idevento" value="<?php echo stridebr_e((string)($form['idevento']??'')); ?>"><input type="hidden" name="origem" value="<?php echo stridebr_e((string)($form['origem']??'manual')); ?>">
            <label><?php echo stridebr_e(stridebr_t('competitions.name')); ?><input name="nome" maxlength="160" required value="<?php echo stridebr_e((string)($form['nome']??'')); ?>"></label>
            <div class="competition-form-grid"><label><?php echo stridebr_e(stridebr_t('competitions.start_date')); ?><input type="date" name="data_inicio" required value="<?php echo stridebr_e((string)($form['data_inicio']??$today)); ?>"></label><label><?php echo stridebr_e(stridebr_t('competitions.end_date')); ?> <small><?php echo stridebr_e(stridebr_t('common.optional')); ?></small><input type="date" name="data_fim" value="<?php echo stridebr_e((string)($form['data_fim']??'')); ?>"></label><label><?php echo stridebr_e(stridebr_t('common.status')); ?><select name="status"><?php foreach(['planejada','realizada','cancelada'] as $status): ?><option value="<?php echo $status; ?>"<?php echo (string)($form['status']??'')===$status?' selected':''; ?>><?php echo stridebr_e(competitionStatusLabel($status)); ?></option><?php endforeach; ?></select></label><label><?php echo stridebr_e(stridebr_t('competitions.main_sport')); ?> <small><?php echo stridebr_e(stridebr_t('common.optional')); ?></small><select name="idmodalidade_principal"><option value=""><?php echo stridebr_e(stridebr_t('common.not_informed')); ?></option><?php foreach($modalities as $modality): ?><option value="<?php echo stridebr_e((string)$modality['idmodalidade']); ?>"<?php echo (string)($form['idmodalidade_principal']??'')===(string)$modality['idmodalidade']?' selected':''; ?>><?php echo stridebr_e(stridebr_sport_name((string)($modality['slug']??''),(string)($modality['nome']??''))); ?></option><?php endforeach; ?></select></label></div>
            <details class="competition-form-more"<?php echo array_filter([(string)($form['local_nome']??''),(string)($form['cidade']??''),(string)($form['organizador']??''),(string)($form['observacoes']??''),(string)($form['oficialidade']??'')])? ' open':''; ?>><summary><?php echo stridebr_e(stridebr_t('competitions.optional_details')); ?></summary><div class="competition-form-grid"><label><?php echo stridebr_e(stridebr_t('competitions.location')); ?><input name="local_nome" maxlength="160" value="<?php echo stridebr_e((string)($form['local_nome']??'')); ?>"></label><label><?php echo stridebr_e(stridebr_t('competitions.city')); ?><input name="cidade" maxlength="100" value="<?php echo stridebr_e((string)($form['cidade']??'')); ?>"></label><label><?php echo stridebr_e(stridebr_t('competitions.state')); ?><input name="estado" maxlength="80" value="<?php echo stridebr_e((string)($form['estado']??'')); ?>"></label><label><?php echo stridebr_e(stridebr_t('competitions.country')); ?><input name="pais" maxlength="80" value="<?php echo stridebr_e((string)($form['pais']??'')); ?>"></label><label><?php echo stridebr_e(stridebr_t('competitions.organizer')); ?><input name="organizador" maxlength="160" value="<?php echo stridebr_e((string)($form['organizador']??'')); ?>"></label><label><?php echo stridebr_e(stridebr_t('competitions.level')); ?><input name="nivel" maxlength="80" value="<?php echo stridebr_e((string)($form['nivel']??'')); ?>"></label></div><label class="competition-official-check"><input type="checkbox" name="reported_official" value="1"<?php echo (string)($form['oficialidade']??'')==='informado_oficial'?' checked':''; ?>><span><strong><?php echo stridebr_e(stridebr_t('competitions.report_as_official')); ?></strong><small><?php echo stridebr_e(stridebr_t('competitions.report_as_official_help')); ?></small></span></label><label><?php echo stridebr_e(stridebr_t('competitions.notes')); ?><textarea name="observacoes" rows="3" maxlength="4000"><?php echo stridebr_e((string)($form['observacoes']??'')); ?></textarea></label></details>
            <?php if (!empty($form['idevento'])): ?><p class="competition-related-event"><?php echo stridebr_e(stridebr_t('competitions.related_event')); ?> · <?php echo stridebr_e((string)($form['evento_titulo']??$prefill['nome']??'')); ?></p><?php endif; ?>
            <div class="competition-form-actions"><a class="secondary-button" href="/user/competicoes.php"><?php echo stridebr_e(stridebr_t('common.cancel')); ?></a><button class="primary-button" type="submit"><?php echo stridebr_e(stridebr_t('common.save')); ?></button></div>
        </form>
    </section>
    <?php endif; ?>

    <?php if (is_array($detailCompetition)): ?>
    <article class="competition-detail">
        <header class="competition-detail-head"><div><span class="competition-status"><?php echo stridebr_e(competitionStatusLabel((string)$detailCompetition['status'])); ?></span><h2><?php echo stridebr_e((string)$detailCompetition['nome']); ?></h2><p><?php echo stridebr_e($formatDateRange($detailCompetition)); ?><?php $loc=implode(', ',array_filter([(string)($detailCompetition['cidade']??''),(string)($detailCompetition['estado']??'')])); if($loc!==''): ?> · <?php echo stridebr_e($loc); ?><?php endif; ?></p></div><div class="competition-detail-actions"><a class="secondary-button" href="/user/competicoes.php?edit=<?php echo rawurlencode((string)$detailCompetition['idcompeticao']); ?>"><?php echo stridebr_e(stridebr_t('common.edit')); ?></a></div></header>
        <div class="competition-facts"><div><span><?php echo stridebr_e(stridebr_t('competitions.activities')); ?></span><strong><?php echo (int)($detailCompetition['atividades_count']??0); ?></strong></div><div><span><?php echo stridebr_e(stridebr_t('competitions.marks_tests')); ?></span><strong><?php echo count($benchmarks); ?></strong></div><?php if(($label=competitionOfficialityLabel((string)$detailCompetition['oficialidade']))!==''): ?><div><span><?php echo stridebr_e(stridebr_t('competitions.officiality')); ?></span><strong><?php echo stridebr_e($label); ?></strong></div><?php endif; ?></div>
        <?php if (!empty($detailCompetition['evento_slug'])): ?><p class="competition-related-event"><?php echo stridebr_e(stridebr_t('competitions.related_event')); ?> · <a href="/evento.php?e=<?php echo rawurlencode((string)$detailCompetition['evento_slug']); ?>"><?php echo stridebr_e((string)$detailCompetition['evento_titulo']); ?></a></p><?php endif; ?>
        <?php if (!empty($detailCompetition['organizador']) || !empty($detailCompetition['local_nome']) || !empty($detailCompetition['observacoes'])): ?><dl class="competition-detail-meta"><?php if($detailCompetition['organizador']): ?><div><dt><?php echo stridebr_e(stridebr_t('competitions.organizer')); ?></dt><dd><?php echo stridebr_e((string)$detailCompetition['organizador']); ?></dd></div><?php endif; ?><?php if($detailCompetition['local_nome']): ?><div><dt><?php echo stridebr_e(stridebr_t('competitions.location')); ?></dt><dd><?php echo stridebr_e((string)$detailCompetition['local_nome']); ?></dd></div><?php endif; ?><?php if($detailCompetition['observacoes']): ?><div><dt><?php echo stridebr_e(stridebr_t('competitions.notes')); ?></dt><dd><?php echo nl2br(stridebr_e((string)$detailCompetition['observacoes'])); ?></dd></div><?php endif; ?></dl><?php endif; ?>
        <section class="competition-detail-section"><header><h3><?php echo stridebr_e(stridebr_t('competitions.activities')); ?></h3></header><?php if($activities===[]): ?><p class="competition-empty-inline"><?php echo stridebr_e(stridebr_t('competitions.no_activities')); ?></p><?php else: ?><div class="competition-linked-list"><?php foreach($activities as $activity): ?><a href="/user/editatividade.php?id=<?php echo rawurlencode((string)$activity['idregistro']); ?>"><div><strong><?php echo stridebr_e((string)($activity['titulo']?:$activity['modalidade_nome'])); ?></strong><small><?php echo stridebr_e(stridebr_sport_name((string)($activity['modalidade_slug']??''),(string)($activity['modalidade_nome']??''))); ?></small></div><span><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$activity['data_inicio']))); ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
        <section class="competition-detail-section"><header><h3><?php echo stridebr_e(stridebr_t('competitions.marks_tests')); ?></h3></header><?php if($benchmarks===[]): ?><p class="competition-empty-inline"><?php echo stridebr_e(stridebr_t('competitions.no_benchmarks')); ?></p><?php else: ?><div class="competition-linked-list"><?php foreach($benchmarks as $benchmark): ?><a href="/user/progresso.php?sport=<?php echo rawurlencode((string)($benchmark['modalidade_slug']??'')); ?>&edit_benchmark=<?php echo rawurlencode((string)$benchmark['idbenchmark']); ?>"><div><strong><?php echo stridebr_e(benchmarkFormatValue((string)$benchmark['tipo'],(float)$benchmark['valor_canonico'],isset($benchmark['distancia_m']) && is_numeric($benchmark['distancia_m']) ? (float)$benchmark['distancia_m'] : null)); ?></strong><small><?php echo stridebr_e(stridebr_t((string)(benchmarkTypeConfig((string)$benchmark['tipo'])['label_key']??'benchmarks.title'))); ?><?php if(!empty($benchmark['atividade_titulo'])): ?> · <?php echo stridebr_e((string)$benchmark['atividade_titulo']); ?><?php endif; ?></small></div><span><?php echo stridebr_e(stridebr_format_date_short(new DateTimeImmutable((string)$benchmark['data_resultado']))); ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
        <form method="post" class="competition-delete" data-confirm="<?php echo stridebr_e(stridebr_t('competitions.delete_confirm')); ?>"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="idcompeticao" value="<?php echo stridebr_e((string)$detailCompetition['idcompeticao']); ?>"><button class="danger-link" type="submit"><?php echo stridebr_e(stridebr_t('competitions.delete')); ?></button><small><?php echo stridebr_e(stridebr_t('competitions.delete_help')); ?></small></form>
    </article>
    <?php endif; ?>

    <?php if ($upcoming !== []): ?><section class="competitions-list-section"><header><h2><?php echo stridebr_e(stridebr_t('competitions.upcoming')); ?></h2></header><div class="competitions-list"><?php foreach($upcoming as $competition): ?><a href="/user/competicoes.php?id=<?php echo rawurlencode((string)$competition['idcompeticao']); ?>"><div><strong><?php echo stridebr_e((string)$competition['nome']); ?></strong><small><?php echo stridebr_e($formatDateRange($competition)); ?><?php $loc=implode(', ',array_filter([(string)($competition['cidade']??''),(string)($competition['estado']??'')])); if($loc!==''): ?> · <?php echo stridebr_e($loc); ?><?php endif; ?></small></div><span><?php echo stridebr_e(competitionStatusLabel((string)$competition['status'])); ?></span></a><?php endforeach; ?></div></section><?php endif; ?>
    <section class="competitions-list-section"><header><h2><?php echo stridebr_e(stridebr_t('competitions.recent')); ?></h2></header><?php if($recent===[] && $upcoming===[]): ?><div class="competition-empty"><p><?php echo stridebr_e(stridebr_t('competitions.empty')); ?></p><a class="primary-button" href="/user/competicoes.php?new=1"><?php echo stridebr_e(stridebr_t('competitions.register')); ?></a></div><?php elseif($recent===[]): ?><p class="competition-empty-inline"><?php echo stridebr_e(stridebr_t('competitions.no_recent')); ?></p><?php else: ?><div class="competitions-list"><?php foreach($recent as $competition): ?><a href="/user/competicoes.php?id=<?php echo rawurlencode((string)$competition['idcompeticao']); ?>"><div><strong><?php echo stridebr_e((string)$competition['nome']); ?></strong><small><?php echo stridebr_e($formatDateRange($competition)); ?> · <?php echo (int)($competition['atividades_count']??0); ?> <?php echo stridebr_e(stridebr_t('competitions.activities_short')); ?></small></div><span><?php echo stridebr_e(competitionStatusLabel((string)$competition['status'])); ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
</div></main>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</div></body></html>
