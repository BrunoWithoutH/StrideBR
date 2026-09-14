<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/combat_progress.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

stridebr_verify_csrf();
$returnTo = stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), '/user/progresso.php');

try {
    $action = stridebr_lower(trim((string) ($_POST['action'] ?? '')));
    if ($action === 'rank_create') {
        combatRankCreate($pdo, $idUsuario, $_POST);
        stridebr_flash('success', stridebr_t('combat.flash.rank_created'));
    } elseif ($action === 'rank_update') {
        $id = trim((string) ($_POST['idgraduacao'] ?? ''));
        if ($id === '') throw new InvalidArgumentException(stridebr_t('combat.error.rank_not_found'));
        combatRankUpdate($pdo, $idUsuario, $id, $_POST);
        stridebr_flash('success', stridebr_t('combat.flash.rank_updated'));
    } elseif ($action === 'rank_delete') {
        $id = trim((string) ($_POST['idgraduacao'] ?? ''));
        if ($id === '' || !combatRankDelete($pdo, $idUsuario, $id)) throw new InvalidArgumentException(stridebr_t('combat.error.rank_not_found'));
        stridebr_flash('success', stridebr_t('combat.flash.rank_deleted'));
    } elseif ($action === 'technique_create') {
        combatTechniqueCreate($pdo, $idUsuario, $_POST);
        stridebr_flash('success', stridebr_t('combat.flash.technique_created'));
    } elseif ($action === 'technique_update') {
        $id = trim((string) ($_POST['idtecnica'] ?? ''));
        if ($id === '') throw new InvalidArgumentException(stridebr_t('combat.error.technique_not_found'));
        combatTechniqueUpdate($pdo, $idUsuario, $id, $_POST);
        stridebr_flash('success', stridebr_t('combat.flash.technique_updated'));
    } elseif ($action === 'technique_archive' || $action === 'technique_reactivate') {
        $id = trim((string) ($_POST['idtecnica'] ?? ''));
        if ($id === '') throw new InvalidArgumentException(stridebr_t('combat.error.technique_not_found'));
        combatTechniqueSetArchived($pdo, $idUsuario, $id, $action === 'technique_archive');
        stridebr_flash('success', stridebr_t($action === 'technique_archive' ? 'combat.flash.technique_archived' : 'combat.flash.technique_reactivated'));
    } elseif ($action === 'practice_create') {
        combatPracticeCreate($pdo, $idUsuario, $_POST);
        stridebr_flash('success', stridebr_t('combat.flash.practice_created'));
    } elseif ($action === 'practice_delete') {
        $id = trim((string) ($_POST['idpratica'] ?? ''));
        if ($id === '' || !combatPracticeDelete($pdo, $idUsuario, $id)) throw new InvalidArgumentException(stridebr_t('combat.error.practice_not_found'));
        stridebr_flash('success', stridebr_t('combat.flash.practice_deleted'));
    } else {
        throw new InvalidArgumentException(stridebr_t('combat.error.invalid_action'));
    }
    header('Location: ' . $returnTo, true, 303);
    exit;
} catch (Throwable $error) {
    if (!$error instanceof InvalidArgumentException) error_log('StrideBR combat progress: ' . $error->getMessage());
    stridebr_flash('danger', $error instanceof InvalidArgumentException ? $error->getMessage() : stridebr_t('combat.error.save_failed'));
    header('Location: ' . $returnTo, true, 303);
    exit;
}
