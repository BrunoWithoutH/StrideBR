<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$headerLoggedIn = stridebr_is_logged_in();
$headerUnreadNotifications = 0;
$headerNotifications = [];
$headerAdminUnreadFeedback = 0;
if ($headerLoggedIn && isset($pdo) && $pdo instanceof PDO) {
    require_once dirname(__DIR__) . '/function/notificacoes.php';
    $headerUserId = (string) ($_SESSION['IdUsuario'] ?? '');
    $headerUnreadNotifications = notificacaoContarNaoLidas($pdo, $headerUserId);
    $headerNotifications = notificacaoListar($pdo, $headerUserId, 6);
    if (stridebr_has_role('admin')) {
        require_once dirname(__DIR__) . '/includes/admin.php';
        $headerAdminUnreadFeedback = stridebr_admin_feedback_unread_count($pdo);
    }
}
$headerPhoto = stridebr_profile_photo_url((string) ($_SESSION['FotoUsuario'] ?? ''), 96);
$headerUsername = trim((string) ($_SESSION['Username'] ?? ''));
$headerDisplayName = trim((string) ($_SESSION['NomeExibicao'] ?? $_SESSION['NomeUsuario'] ?? $headerUsername ?: stridebr_t('common.user')));
$headerProfileUrl = $headerUsername !== '' ? '/u/' . rawurlencode($headerUsername) : '/user/perfil.php';
$headerPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$headerActive = static function (array $paths) use ($headerPath): string {
    foreach ($paths as $path) {
        if ($headerPath === $path || str_starts_with($headerPath, rtrim($path, '/') . '/')) return ' is-active';
    }
    return '';
};
$headerIcon = static function (string $name): string {
    $paths = match ($name) {
        'profile' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0 1 16 0"></path>',
        'edit' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path>',
        'preferences' => '<path d="M4 6h10"></path><path d="M18 6h2"></path><circle cx="16" cy="6" r="2"></circle><path d="M4 12h2"></path><path d="M10 12h10"></path><circle cx="8" cy="12" r="2"></circle><path d="M4 18h7"></path><path d="M15 18h5"></path><circle cx="13" cy="18" r="2"></circle>',
        'connections' => '<path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"></path>',
        'security' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="M9 12l2 2 4-4"></path>',
        'people' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'trainer' => '<circle cx="9" cy="7" r="4"></circle><path d="M2 21v-2a7 7 0 0 1 11.4-5.5"></path><path d="M17 11v6"></path><path d="M14 14h6"></path>',
        'events' => '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path>',
        'tools' => '<path d="M14.7 6.3a4 4 0 0 0-5-5L12 3.6 3.6 12 1.3 9.7a4 4 0 0 0 5 5L15 6.4"></path><path d="m13 11 8 8-2 2-8-8"></path>',
        'news' => '<path d="M3 11h18"></path><path d="M5 7h14"></path><path d="M7 3h10"></path><path d="M5 15h14v6H5z"></path>',
        'admin' => '<path d="M12 3 3 8l9 5 9-5-9-5Z"></path><path d="m3 12 9 5 9-5"></path><path d="m3 16 9 5 9-5"></path>',
        'logout' => '<path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>',
        default => '<circle cx="12" cy="12" r="9"></circle>',
    };
    return '<svg class="menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};
$headerMenuLink = static function (string $href, string $label, string $icon, string $hint = '') use ($headerIcon): string {
    $copy = '<span class="user-menu-link-copy"><strong>' . stridebr_e($label) . '</strong>' . ($hint !== '' ? '<small>' . stridebr_e($hint) . '</small>' : '') . '</span>';
    return '<a href="' . stridebr_e($href) . '">' . $headerIcon($icon) . $copy . '</a>';
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
                                    <a class="header-notification-item<?php echo empty($notification['lida_em']) ? ' is-unread' : ''; ?>" href="<?php echo stridebr_e($notificationUrl); ?>"><span class="header-notification-dot" aria-hidden="true"></span><span><strong><?php echo stridebr_e((string) $notificationCopy['titulo']); ?></strong><?php if (trim((string) $notificationCopy['mensagem']) !== ''): ?><small><?php echo stridebr_e((string) $notificationCopy['mensagem']); ?></small><?php endif; ?><time><?php echo stridebr_e(stridebr_format_datetime_short((string) ($notification['data_criacao'] ?? 'now'))); ?></time></span></a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($headerAdminUnreadFeedback > 0): ?><div class="header-notification-admin" aria-label="<?php echo stridebr_e(stridebr_t('admin.notifications.section')); ?>"><span class="header-notification-admin-label"><?php echo stridebr_e(stridebr_t('admin.notifications.section')); ?></span><a href="/admin/feedback.php?state=unread"><span><?php echo stridebr_e(stridebr_t('admin.notifications.feedback')); ?></span><strong><?php echo (int) $headerAdminUnreadFeedback; ?></strong></a></div><?php endif; ?>
                        <a class="header-notification-all" href="/user/notificacoes.php"><?php echo stridebr_e(stridebr_t('notifications.view_all')); ?></a>
                    </div>
                </details>
                <details data-header-menu="toggle" class="global-create-menu">
                    <summary aria-label="<?php echo stridebr_e(stridebr_t('nav.create')); ?>" title="<?php echo stridebr_e(stridebr_t('nav.create')); ?>">+</summary>
                    <div class="global-create-content"><span class="global-create-label"><?php echo stridebr_e(stridebr_t('nav.create')); ?></span><a href="/user/gravar-atividade.php"><strong><?php echo stridebr_e(stridebr_t('nav.record_gps')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_gps_desc')); ?></span></a><a href="/user/atividades.php?new=1"><strong><?php echo stridebr_e(stridebr_t('nav.physical_activity')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_activity_desc')); ?></span></a><a href="/user/cronogramatreinos.php?new=workout"><strong><?php echo stridebr_e(stridebr_t('nav.workout')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_workout_desc')); ?></span></a><a href="/user/cronogramatreinos.php?new=schedule"><strong><?php echo stridebr_e(stridebr_t('nav.schedule')); ?></strong><span><?php echo stridebr_e(stridebr_t('nav.create_schedule_desc')); ?></span></a></div>
                </details>
                <details data-header-menu="toggle" class="user-menu desktop-account-menu">
                    <summary aria-label="<?php echo stridebr_e(stridebr_t('common.open_profile_menu')); ?>"><img class="userimage" src="<?php echo stridebr_e($headerPhoto); ?>" alt="<?php echo stridebr_e(stridebr_t('common.profile_image_alt')); ?>" width="34" height="34" decoding="async" fetchpriority="high"></summary>
                    <div class="user-menu-content">
                        <div class="user-menu-identity"><img src="<?php echo stridebr_e($headerPhoto); ?>" alt="" width="42" height="42"><span><strong><?php echo stridebr_e($headerDisplayName); ?></strong><?php if ($headerUsername !== ''): ?><small>@<?php echo stridebr_e($headerUsername); ?></small><?php endif; ?><a href="<?php echo stridebr_e($headerProfileUrl); ?>"><?php echo stridebr_e(stridebr_t('nav.profile')); ?></a></span></div>
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.account')); ?></span><?php echo $headerMenuLink('/user/edit-profile.php', stridebr_t('nav.edit_profile', [], 'Editar perfil'), 'edit'); ?><?php echo $headerMenuLink('/user/settings.php?view=preferences', stridebr_t('settings.preferences'), 'preferences'); ?><?php echo $headerMenuLink('/user/settings.php?view=connections', stridebr_t('settings.connections'), 'connections', stridebr_t('nav.connections_hint')); ?><?php echo $headerMenuLink('/user/account.php', stridebr_t('nav.security'), 'security'); ?></div>
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.people')); ?></span><?php echo $headerMenuLink('/user/amigos.php', stridebr_t('nav.friends'), 'people'); ?><?php echo $headerMenuLink('/user/treinador.php', stridebr_t('nav.trainer'), 'trainer'); ?></div>
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.resources')); ?></span><?php echo $headerMenuLink('/pages/extras/changelog.php', stridebr_t('nav.news'), 'news'); ?></div>
                        <?php if (stridebr_has_role('moderator')): ?><div class="user-menu-section"><?php echo $headerMenuLink('/admin/index.php', stridebr_has_role('admin') ? stridebr_t('nav.administration', [], 'Administração') : stridebr_t('nav.moderation', [], 'Moderação'), 'admin'); ?></div><?php endif; ?>
                        <form method="POST" action="/function/logout.php"><?php echo stridebr_csrf_field(); ?><button type="submit"><?php echo $headerIcon('logout'); ?><span class="user-menu-link-copy"><strong><?php echo stridebr_e(stridebr_t('nav.sign_out')); ?></strong></span></button></form>
                    </div>
                </details>
                <details data-header-menu="toggle" class="mobile-global-menu">
                    <summary aria-label="<?php echo stridebr_e(stridebr_t('nav.global_menu')); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg></summary>
                    <button class="mobile-global-menu-backdrop" type="button" data-header-menu-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
                    <div class="mobile-global-menu-content">
                        <div class="mobile-global-menu-head"><strong><?php echo stridebr_e(stridebr_t('nav.global_menu')); ?></strong><span>@<?php echo stridebr_e($headerUsername); ?></span></div>
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.people')); ?></span><?php echo $headerMenuLink('/user/amigos.php', stridebr_t('nav.friends'), 'people'); ?><?php echo $headerMenuLink('/user/treinador.php', stridebr_t('nav.trainer'), 'trainer'); ?></div>
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.resources')); ?></span><?php echo $headerMenuLink('/calendario.php', stridebr_t('nav.events'), 'events'); ?><?php echo $headerMenuLink('/user/ferramentastreino.php', stridebr_t('nav.tools'), 'tools'); ?></div>
                        <div class="user-menu-section"><span class="user-menu-label"><?php echo stridebr_e(stridebr_t('nav.account_stridebr')); ?></span><?php echo $headerMenuLink('/user/settings.php?view=connections', stridebr_t('settings.connections'), 'connections', stridebr_t('nav.connections_hint')); ?><?php echo $headerMenuLink('/user/settings.php', stridebr_t('nav.settings'), 'preferences'); ?><?php echo $headerMenuLink('/pages/extras/changelog.php', stridebr_t('nav.news'), 'news'); ?></div>
                        <?php if (stridebr_has_role('moderator')): ?><div class="user-menu-section"><?php echo $headerMenuLink('/admin/index.php', stridebr_has_role('admin') ? stridebr_t('nav.administration', [], 'Administração') : stridebr_t('nav.moderation', [], 'Moderação'), 'admin'); ?></div><?php endif; ?>
                        <form method="POST" action="/function/logout.php"><?php echo stridebr_csrf_field(); ?><button type="submit"><?php echo $headerIcon('logout'); ?><span class="user-menu-link-copy"><strong><?php echo stridebr_e(stridebr_t('nav.sign_out')); ?></strong></span></button></form>
                    </div>
                </details>
            <?php else: ?><a class="login-button" href="/login.php"><?php echo stridebr_e(stridebr_t('nav.sign_in')); ?></a><?php endif; ?>
        </div>
    </div>
</header>
<noscript><div class="noscript-banner" role="status"><?php echo stridebr_e(stridebr_t('common.javascript_required')); ?></div></noscript>
<?php if (is_array($_SESSION['OwnerImpersonation'] ?? null)): ?><div class="impersonation-banner" role="status"><span><?php echo stridebr_e(stridebr_t('admin.impersonation_notice', ['name' => (string) ($_SESSION['NomeExibicao'] ?? $_SESSION['NomeUsuario'] ?? stridebr_t('common.user'))])); ?></span><form method="POST" action="/admin/stop-impersonation.php"><?php echo stridebr_csrf_field(); ?><button type="submit"><?php echo stridebr_e(stridebr_t('admin.back_to_account')); ?></button></form></div><?php endif; ?>
