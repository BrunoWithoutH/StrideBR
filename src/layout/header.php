<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$headerLoggedIn = stridebr_is_logged_in();
$headerUnreadNotifications = 0;
$headerNotifications = [];
if ($headerLoggedIn && isset($pdo) && $pdo instanceof PDO) {
    require_once dirname(__DIR__) . '/function/notificacoes.php';
    $headerUserId = (string) ($_SESSION['IdUsuario'] ?? '');
    $headerUnreadNotifications = notificacaoContarNaoLidas($pdo, $headerUserId);
    $headerNotifications = notificacaoListar($pdo, $headerUserId, 6);
}
$headerPhoto = stridebr_profile_photo_url((string) ($_SESSION['FotoUsuario'] ?? ''), 96);
$headerPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$headerActive = static function (array $paths) use ($headerPath): string {
    foreach ($paths as $path) {
        if ($headerPath === $path || str_starts_with($headerPath, rtrim($path, '/') . '/')) {
            return ' is-active';
        }
    }
    return '';
};
?>
<header class="site-header">
    <div class="header-inner">
        <a class="brand-link" href="<?php echo $headerLoggedIn ? '/home.php' : '/index.php'; ?>" aria-label="<?php echo stridebr_e(stridebr_t('library.stridebr')); ?>">
            <img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo-white.svg')); ?>" alt="StrideBR" class="nav-logo" width="87" height="34" decoding="async">
        </a>
        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="<?php echo stridebr_e(stridebr_t('nav.open_navigation')); ?>">☰</button>
        <nav class="main-nav" data-nav-menu aria-label="<?php echo stridebr_e(stridebr_t('nav.open_navigation')); ?>">
            <a class="<?php echo trim($headerActive(['/home.php'])); ?>" href="/home.php"><?php echo stridebr_e(stridebr_t('nav.home')); ?></a>
            <details data-header-menu="hover-toggle" class="nav-menu-group<?php echo $headerActive(['/user/cronogramatreinos.php', '/user/agenda-mensal.php', '/user/biblioteca.php', '/user/exercicioscronograma.php', '/user/exerciciostreinomodelo.php']); ?>">
                <summary><?php echo stridebr_e(stridebr_t('nav.training')); ?> <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4.5 6 3.5 3.5L11.5 6"></path></svg></summary>
                <div class="nav-dropdown">
                    <a href="/user/cronogramatreinos.php"><strong><?php echo stridebr_e(stridebr_t('nav.schedules')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.training_schedules_desc')); ?></span></a>
                    <a href="/user/agenda-mensal.php"><strong><?php echo stridebr_e(stridebr_t('nav.agenda')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.training_agenda_desc')); ?></span></a>
                    <a href="/user/biblioteca.php"><strong><?php echo stridebr_e(stridebr_t('nav.library')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.training_library_desc')); ?></span></a>
                </div>
            </details>
            <a class="<?php echo trim($headerActive(['/user/atividades.php', '/user/editatividade.php', '/user/equipamentos.php', '/user/gravar-atividade.php'])); ?>" href="/user/atividades.php"><?php echo stridebr_e(stridebr_t('nav.activities')); ?></a>
            <a class="<?php echo trim($headerActive(['/user/progresso.php', '/user/metas.php', '/user/comparar-atividades.php'])); ?>" href="/user/progresso.php"><?php echo stridebr_e(stridebr_t('nav.progress')); ?></a>
            <a class="<?php echo trim($headerActive(['/user/ferramentastreino.php'])); ?>" href="/user/ferramentastreino.php"><?php echo stridebr_e(stridebr_t('nav.tools')); ?></a>
            <a class="<?php echo trim($headerActive(['/calendario.php', '/evento.php'])); ?>" href="/calendario.php"><?php echo stridebr_e(stridebr_t('nav.events')); ?></a>
        </nav>
        <div class="usersection">
            <?php if ($headerLoggedIn): ?>
                <details data-header-menu="toggle" class="header-notification-menu">
                    <summary class="header-notification-button<?php echo $headerUnreadNotifications > 0 ? ' has-unread' : ''; ?>" aria-label="<?php echo stridebr_e(stridebr_t('notifications.title')); ?>" title="<?php echo stridebr_e(stridebr_t('notifications.title')); ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>
                        <?php if ($headerUnreadNotifications > 0): ?><span><?php echo min(99, $headerUnreadNotifications); ?></span><?php endif; ?>
                    </summary>
                    <div class="header-notification-popover">
                        <div class="header-notification-head"><strong><?php echo stridebr_e(stridebr_t('notifications.title')); ?></strong><?php if ($headerUnreadNotifications > 0): ?><small><?php echo stridebr_e(stridebr_t('notifications.unread', ['count' => $headerUnreadNotifications])); ?></small><?php endif; ?></div>
                        <div class="header-notification-list">
                            <?php if ($headerNotifications === []): ?>
                                <p class="header-notification-empty"><?php echo stridebr_e(stridebr_t('notifications.empty')); ?></p>
                            <?php else: ?>
                                <?php foreach ($headerNotifications as $notification): ?>
                                    <?php $notificationUrl = trim((string) ($notification['url'] ?? '')) ?: '/user/notificacoes.php'; $notificationCopy = function_exists('notificacaoApresentar') ? notificacaoApresentar($notification) : ['titulo' => (string) ($notification['titulo'] ?? ''), 'mensagem' => (string) ($notification['mensagem'] ?? '')]; ?>
                                    <a class="header-notification-item<?php echo empty($notification['lida_em']) ? ' is-unread' : ''; ?>" href="<?php echo stridebr_e($notificationUrl); ?>">
                                        <span class="header-notification-dot" aria-hidden="true"></span>
                                        <span><strong><?php echo stridebr_e((string) $notificationCopy['titulo']); ?></strong><?php if (trim((string) $notificationCopy['mensagem']) !== ''): ?><small><?php echo stridebr_e((string) $notificationCopy['mensagem']); ?></small><?php endif; ?><time><?php echo stridebr_e(stridebr_format_datetime_short((string) ($notification['data_criacao'] ?? 'now'))); ?></time></span>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a class="header-notification-all" href="/user/notificacoes.php"><?php echo stridebr_e(stridebr_t('notifications.view_all')); ?></a>
                    </div>
                </details>
                <details data-header-menu="toggle" class="global-create-menu">
                    <summary aria-label="<?php echo stridebr_e(stridebr_t('nav.create')); ?>" title="<?php echo stridebr_e(stridebr_t('nav.create')); ?>">+</summary>
                    <div class="global-create-content">
                        <span class="global-create-label"><?php echo stridebr_e(stridebr_t('nav.create')); ?></span>
                        <a href="/user/gravar-atividade.php"><strong><?php echo stridebr_e(stridebr_t('nav.record_gps')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_gps_desc')); ?></span></a>
                        <a href="/user/atividades.php?new=1"><strong><?php echo stridebr_e(stridebr_t('nav.physical_activity')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_activity_desc')); ?></span></a>
                        <a href="/user/cronogramatreinos.php?new=workout"><strong><?php echo stridebr_e(stridebr_t('nav.workout')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_workout_desc')); ?></span></a>
                        <a href="/user/cronogramatreinos.php?new=schedule"><strong><?php echo stridebr_e(stridebr_t('nav.schedule')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_schedule_desc')); ?></span></a>
                    </div>
                </details>
                <details data-header-menu="toggle" class="user-menu">
                    <summary aria-label="<?php echo stridebr_e(stridebr_t('common.open_profile_menu')); ?>"><img class="userimage" src="<?php echo stridebr_e($headerPhoto); ?>" alt="<?php echo stridebr_e(stridebr_t('common.profile_image_alt')); ?>" width="34" height="34" decoding="async" fetchpriority="high"></summary>
                    <div class="user-menu-content">
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.account')); ?></span>
                        <?php if (!empty($_SESSION['Username'])): ?><a href="/u/<?php echo rawurlencode((string) $_SESSION['Username']); ?>"><?php echo stridebr_e(stridebr_t('nav.profile')); ?></a><?php endif; ?>
                        <a href="/user/edit-profile.php"><?php echo stridebr_e(stridebr_t('nav.edit_profile', [], 'Editar perfil')); ?></a>
                        <a href="/user/settings.php"><?php echo stridebr_e(stridebr_t('nav.settings', [], 'Configurações')); ?></a>
                        <a href="/user/account.php"><?php echo stridebr_e(stridebr_t('nav.security')); ?></a>
                        <a href="/pages/extras/changelog.php"><?php echo stridebr_e(stridebr_t('nav.news')); ?></a>
                        <a href="/user/progresso.php"><?php echo stridebr_e(stridebr_t('nav.progress_goals')); ?></a></div>
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.connections')); ?></span>
                        <a href="/user/amigos.php"><?php echo stridebr_e(stridebr_t('nav.friends')); ?></a>
                        <a href="/user/treinador.php"><?php echo stridebr_e(stridebr_t('nav.trainer')); ?></a></div>
                        <?php if (stridebr_has_role('moderator')): ?><a href="/admin/index.php"><?php echo stridebr_e(stridebr_has_role('admin') ? stridebr_t('nav.administration', [], 'Administração') : stridebr_t('nav.moderation', [], 'Moderação')); ?></a><?php endif; ?>
                        <form method="POST" action="/function/logout.php">
                            <?php echo stridebr_csrf_field(); ?>
                            <button type="submit"><?php echo stridebr_e(stridebr_t('nav.sign_out')); ?></button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a class="login-button" href="/login.php"><?php echo stridebr_e(stridebr_t('nav.sign_in')); ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>
<noscript><div class="noscript-banner" role="status"><?php echo stridebr_e(stridebr_t('common.javascript_required')); ?></div></noscript>
<?php if (is_array($_SESSION['OwnerImpersonation'] ?? null)): ?>
<div class="impersonation-banner" role="status">
    <span><?php echo stridebr_e(stridebr_t('admin.impersonation_notice', ['name' => (string) ($_SESSION['NomeExibicao'] ?? $_SESSION['NomeUsuario'] ?? stridebr_t('common.user'))])); ?></span>
    <form method="POST" action="/admin/stop-impersonation.php"><?php echo stridebr_csrf_field(); ?><button type="submit"><?php echo stridebr_e(stridebr_t('admin.back_to_account')); ?></button></form>
</div>
<?php endif; ?>
