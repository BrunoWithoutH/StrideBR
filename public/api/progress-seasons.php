<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/seasons.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

stridebr_verify_csrf();
$returnTo = stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), '/user/progresso.php');

try {
    $action = stridebr_lower(trim((string) ($_POST['action'] ?? 'create')));
    $seasonId = trim((string) ($_POST['idtemporada'] ?? ''));
    if ($action === 'create') {
        seasonCreate($pdo, $idUsuario, $_POST);
        stridebr_flash('success', stridebr_t('seasons.flash.created'));
    } elseif ($action === 'update') {
        if ($seasonId === '') throw new InvalidArgumentException(stridebr_t('seasons.error.not_found'));
        seasonUpdate($pdo, $idUsuario, $seasonId, $_POST);
        stridebr_flash('success', stridebr_t('seasons.flash.updated'));
    } elseif ($action === 'close') {
        if ($seasonId === '') throw new InvalidArgumentException(stridebr_t('seasons.error.not_found'));
        seasonClose($pdo, $idUsuario, $seasonId, trim((string) ($_POST['data_fim'] ?? '')) ?: null);
        stridebr_flash('success', stridebr_t('seasons.flash.closed'));
    } elseif ($action === 'reopen') {
        if ($seasonId === '') throw new InvalidArgumentException(stridebr_t('seasons.error.not_found'));
        seasonReopen($pdo, $idUsuario, $seasonId);
        stridebr_flash('success', stridebr_t('seasons.flash.reopened'));
    } else {
        throw new InvalidArgumentException(stridebr_t('seasons.error.invalid_action'));
    }
    header('Location: ' . $returnTo, true, 303);
    exit;
} catch (Throwable $error) {
    if (!$error instanceof InvalidArgumentException) error_log('StrideBR seasons: ' . $error->getMessage());
    stridebr_flash('danger', $error instanceof InvalidArgumentException ? $error->getMessage() : stridebr_t('seasons.error.save_failed'));
    header('Location: ' . $returnTo, true, 303);
    exit;
}
