<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$userId = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/cronograma.php';
$kind = (string) ($_POST['kind'] ?? $_GET['kind'] ?? 'workout');
$id = (string) ($_POST['id'] ?? $_GET['id'] ?? '');
$returnTo = stridebr_safe_redirect((string) ($_POST['return_to'] ?? $_GET['return_to'] ?? ''), '/user/cronogramatreinos.php');
try { $plan = cronogramaPlanoNomes($pdo, $userId, $kind, $id); } catch (InvalidArgumentException) { stridebr_error_document(404); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    stridebr_verify_csrf();
    try {
        $action=(string) ($_POST['action'] ?? '');
        cronogramaRepararNomesPlano($pdo,$userId,$kind,$id,$action,(string) ($_POST['row'] ?? ''),(string) ($_POST['exercise'] ?? ''));
        if ($action === 'safe') { header('Location: ' . $returnTo); exit; }
        header('Location: /user/revisar-exercicios-plano.php?' . http_build_query(['kind'=>$kind,'id'=>$id,'return_to'=>$returnTo])); exit;
    } catch (InvalidArgumentException $exception) { $error=$exception->getMessage(); }
      catch (Throwable $exception) { error_log('StrideBR planned name review: '.$exception->getMessage()); $error=stridebr_t('common.operation_failed'); }
}
$catalog=stridebr_exercise_catalog_for_user($pdo,$userId);
$rows=cronogramaHidratarExerciciosPlanejados($pdo,$userId,$plan['rows'],$catalog);
$rows=array_filter($rows,static fn(array $row): bool => !empty($row['nome_revisao']));
?>
<!DOCTYPE html>
<html lang="<?php echo stridebr_e(stridebr_html_lang()); ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo stridebr_e(stridebr_t('schedule.names.review')); ?> | StrideBR</title>
<?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
<link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
<link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/cronogramas.css')); ?>">
<link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head><body><div class="container-fluid">
<?php require dirname(__DIR__,2).'/src/layout/header.php'; ?>
<main class="main-content planned-name-page"><div class="planned-name-shell">
<header class="draft-exercise-heading"><div><h1><?php echo stridebr_e(stridebr_t('schedule.names.review')); ?></h1><p><?php echo stridebr_e(stridebr_t('schedule.names.count', ['count'=>count($rows)])); ?></p></div><a class="secondary-button" href="<?php echo stridebr_e($returnTo); ?>"><?php echo stridebr_e(stridebr_t('common.back')); ?></a></header>
<?php if ($error !== ''): ?><p class="alert alert-error" role="alert"><?php echo stridebr_e($error); ?></p><?php endif; ?>
<?php $safeCount=count(array_filter($rows,static fn(array $row): bool => ($row['nome_revisao'] ?? '') === 'safe')); ?>
<div class="planned-name-toolbar"><span><?php echo stridebr_e(stridebr_t('schedule.names.safe_count',['count'=>$safeCount])); ?></span><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="kind" value="<?php echo stridebr_e($kind); ?>"><input type="hidden" name="id" value="<?php echo stridebr_e($id); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>"><input type="hidden" name="action" value="safe"><button class="secondary-button" type="submit" <?php echo $safeCount === 0 ? 'disabled' : ''; ?>><?php echo stridebr_e(stridebr_t('schedule.names.safe')); ?></button></form></div>
<?php if (!$rows): ?><p class="planned-name-empty"><?php echo stridebr_e(stridebr_t('schedule.names.none')); ?></p><?php endif; ?>
<div class="planned-name-list">
<?php foreach ($rows as $row): $rowId=(string) $row[$plan['key']]; $suggestion=$row['nome_resolucao']['suggestions'][0] ?? null; ?>
<section class="planned-name-item" data-exercise-row data-review-state="<?php echo stridebr_e((string)$row['nome_revisao']); ?>">
<div class="planned-name-comparison"><div><small><?php echo stridebr_e(stridebr_t('schedule.names.original')); ?></small><strong><?php echo stridebr_e($row['nome_original']); ?></strong></div><?php if ($row['nome_revisao']==='safe' || $suggestion): ?><div><small><?php echo stridebr_e(stridebr_t('schedule.names.suggestion')); ?></small><strong><?php echo stridebr_e($row['nome_revisao']==='safe' ? $row['nome_snapshot'] : $suggestion['nome']); ?></strong></div><?php endif; ?></div>
<div class="planned-name-actions">
<?php if ($row['nome_revisao']==='safe'): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="kind" value="<?php echo stridebr_e($kind); ?>"><input type="hidden" name="id" value="<?php echo stridebr_e($id); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>"><input type="hidden" name="action" value="map"><input type="hidden" name="row" value="<?php echo stridebr_e($rowId); ?>"><input type="hidden" name="exercise" value="<?php echo stridebr_e((string)$row['idexercicio']); ?>"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.names.use_suggestion')); ?></button></form>
<?php elseif ($suggestion): ?><form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="kind" value="<?php echo stridebr_e($kind); ?>"><input type="hidden" name="id" value="<?php echo stridebr_e($id); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>"><input type="hidden" name="action" value="map"><input type="hidden" name="row" value="<?php echo stridebr_e($rowId); ?>"><input type="hidden" name="exercise" value="<?php echo stridebr_e($suggestion['idexercicio']); ?>"><button type="submit" class="primary-button"><?php echo stridebr_e(stridebr_t('schedule.names.use_suggestion')); ?></button></form><?php endif; ?>
<button type="button" class="secondary-button" data-open-name-mapping aria-expanded="false"><?php echo stridebr_e(stridebr_t('schedule.names.choose_other')); ?></button>
<form method="POST"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="kind" value="<?php echo stridebr_e($kind); ?>"><input type="hidden" name="id" value="<?php echo stridebr_e($id); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>"><input type="hidden" name="action" value="keep"><input type="hidden" name="row" value="<?php echo stridebr_e($rowId); ?>"><button class="secondary-button" type="submit"><?php echo stridebr_e(stridebr_t('schedule.names.keep')); ?></button></form></div>
<form method="POST" class="planned-name-mapping" hidden><?php echo stridebr_csrf_field(); ?><input type="hidden" name="kind" value="<?php echo stridebr_e($kind); ?>"><input type="hidden" name="id" value="<?php echo stridebr_e($id); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($returnTo); ?>"><input type="hidden" name="action" value="map"><input type="hidden" name="row" value="<?php echo stridebr_e($rowId); ?>"><input type="hidden" name="exercise" data-exercise-id required><label><?php echo stridebr_e(stridebr_t('schedule.names.choose_library')); ?><input data-exercise-name maxlength="120" type="text" required></label><button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('common.apply')); ?></button></form>
</section><?php endforeach; ?></div></div></main>
<?php require dirname(__DIR__,2).'/src/layout/footer.php'; ?></div><script src="<?php echo stridebr_e(stridebr_asset('/assets/js/planned-name-review.js')); ?>"></script></body></html>
