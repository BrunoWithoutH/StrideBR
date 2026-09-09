<?php
require_once dirname(__DIR__) . '/function/monetization.php';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$footerLoggedIn = stridebr_is_logged_in();
$footerInstagramUrl = trim((string) (getenv('STRIDEBR_INSTAGRAM_URL') ?: ''));
if ($footerInstagramUrl !== '' && (filter_var($footerInstagramUrl, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($footerInstagramUrl, PHP_URL_SCHEME)) !== 'https')) $footerInstagramUrl = '';
$feedbackEnabled = false;
if ($footerLoggedIn) {
    if (isset($pdo) && $pdo instanceof PDO) {
        $feedbackEnabled = stridebr_feature_enabled($pdo, 'feedback.enabled', false);
    } else {
        $cachedFeedback = $_SESSION['StrideBRFeatureFlags']['feedback.enabled'] ?? null;
        $cacheTtl = max(0, min(300, (int) (getenv('STRIDEBR_FEATURE_CACHE_TTL') ?: 60)));
        if (is_array($cachedFeedback) && isset($cachedFeedback['at']) && (time() - (int) $cachedFeedback['at']) < $cacheTtl) {
            $feedbackEnabled = (bool) ($cachedFeedback['value'] ?? false);
        }
    }
}
$navActive = static function (array $prefixes) use ($currentPath): string {
    foreach ($prefixes as $prefix) {
        if ($currentPath === $prefix || str_starts_with($currentPath, rtrim($prefix, '/') . '/')) return ' is-active';
    }
    return '';
};
?>
<div class="network-status-banner" data-network-status role="status" aria-live="polite" hidden><?php echo stridebr_e(stridebr_t('common.offline_forms_warning')); ?></div>

<?php require_once __DIR__ . '/ads.php'; stridebr_render_ads_runtime(); ?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-top">
            <div class="footer-brand">
                <img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo-white.svg')); ?>" alt="StrideBR" class="footer-logo" width="82" height="32" loading="lazy" decoding="async">
                <p><?php echo stridebr_e(stridebr_t('footer.tagline')); ?></p>
            </div>
            <div class="footer-column"><h4><?php echo stridebr_e(stridebr_t('library.stridebr')); ?></h4><a href="/pages/about/about.php"><?php echo stridebr_e(stridebr_t('footer.about')); ?></a><a href="/pages/about/team.php"><?php echo stridebr_e(stridebr_t('footer.team')); ?></a><a href="/pages/about/contact.php"><?php echo stridebr_e(stridebr_t('footer.contact')); ?></a></div>
            <div class="footer-column"><h4><?php echo stridebr_e(stridebr_t('footer.help')); ?></h4><a href="/pages/help/faq.php"><?php echo stridebr_e(stridebr_t('footer.faq')); ?></a><a href="/pages/help/support.php"><?php echo stridebr_e(stridebr_t('footer.support')); ?></a><?php if ($feedbackEnabled): ?><a href="/feedback.php"><?php echo stridebr_e(stridebr_t('common.feedback')); ?></a><?php endif; ?></div>
            <div class="footer-column"><h4><?php echo stridebr_e(stridebr_t('footer.legal')); ?></h4><a href="/pages/legal/terms.php"><?php echo stridebr_e(stridebr_t('footer.terms')); ?></a><a href="/pages/legal/privacy.php"><?php echo stridebr_e(stridebr_t('footer.privacy')); ?></a><a href="/pages/legal/cookies.php"><?php echo stridebr_e(stridebr_t('footer.cookies')); ?></a></div>
            <div class="footer-column"><h4><?php echo stridebr_e(stridebr_t('footer.project')); ?></h4><a href="/pages/extras/roadmap.php"><?php echo stridebr_e(stridebr_t('footer.roadmap')); ?></a><a href="/pages/extras/changelog.php"><?php echo stridebr_e(stridebr_t('footer.updates')); ?></a><a href="/pages/extras/credits.php"><?php echo stridebr_e(stridebr_t('footer.credits')); ?></a><?php if (stridebr_donation_enabled()): ?><a href="/pages/about/support-project.php"><?php echo stridebr_e(stridebr_t('footer.support_project')); ?></a><?php endif; ?></div>
        </div>
        <div class="footer-bottom"><div><a href="https://github.com/BrunoWithoutH/StrideBR" target="_blank" rel="noopener noreferrer">GitHub</a><?php if ($footerInstagramUrl !== ''): ?><a href="<?php echo stridebr_e($footerInstagramUrl); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?><span class="footer-build" data-stridebr-version="<?php echo stridebr_e(stridebr_version()); ?>" data-stridebr-build="<?php echo stridebr_e(stridebr_build()); ?>"><?php echo stridebr_e(stridebr_t('library.stridebr')); ?> <?php echo stridebr_e(stridebr_version()); ?> · build <?php echo stridebr_e(stridebr_build()); ?></span></div><p>© <?php echo date('Y'); ?> StrideBR.</p></div>
    </div>
</footer>

<?php if ($footerLoggedIn): ?>
<nav class="mobile-bottom-nav" aria-label="<?php echo stridebr_e(stridebr_t('nav.open_navigation')); ?>">
    <a class="mobile-nav-item<?php echo $navActive(['/home.php']); ?>" href="/home.php" aria-label="<?php echo stridebr_e(stridebr_t('nav.home')); ?>">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
            <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        </svg>
        <span><?php echo stridebr_e(stridebr_t('nav.home')); ?></span>
    </a>
    <a class="mobile-nav-item<?php echo $navActive(['/user/cronogramatreinos.php', '/user/exercicioscronograma.php', '/user/agenda-mensal.php', '/user/biblioteca.php', '/user/exerciciostreinomodelo.php']); ?>" href="/user/cronogramatreinos.php" aria-label="<?php echo stridebr_e(stridebr_t('nav.training')); ?>">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M8 2v4"></path>
            <path d="M16 2v4"></path>
            <rect width="18" height="18" x="3" y="4" rx="2"></rect>
            <path d="M3 10h18"></path>
            <path d="M8 14h.01"></path>
            <path d="M12 14h.01"></path>
            <path d="M16 14h.01"></path>
            <path d="M8 18h.01"></path>
            <path d="M12 18h.01"></path>
        </svg>
        <span><?php echo stridebr_e(stridebr_t('nav.training')); ?></span>
    </a>
    <a class="mobile-nav-item<?php echo $navActive(['/user/atividades.php', '/user/editatividade.php', '/user/importar-exportar.php', '/user/gravar-atividade.php']); ?>" href="/user/atividades.php" aria-label="<?php echo stridebr_e(stridebr_t('nav.activities')); ?>">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
        </svg>
        <span><?php echo stridebr_e(stridebr_t('nav.activities')); ?></span>
    </a>
    <a class="mobile-nav-item<?php echo $navActive(['/user/progresso.php', '/user/metas.php', '/user/comparar-atividades.php']); ?>" href="/user/progresso.php" aria-label="<?php echo stridebr_e(stridebr_t('nav.progress')); ?>">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4 19V9"></path>
            <path d="M10 19V5"></path>
            <path d="M16 19v-7"></path>
            <path d="M22 19V3"></path>
        </svg>
        <span><?php echo stridebr_e(stridebr_t('nav.progress')); ?></span>
    </a>
    <button class="mobile-nav-item mobile-more-button<?php echo $navActive(['/user/amigos.php', '/user/ferramentastreino.php', '/user/settings.php', '/user/account.php', '/user/treinador.php', '/calendario.php', '/evento.php', '/admin/index.php']); ?>" type="button" data-mobile-more-toggle aria-expanded="false" aria-label="<?php echo stridebr_e(stridebr_t('nav.more_options')); ?>">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4 12h.01"></path>
            <path d="M12 12h.01"></path>
            <path d="M20 12h.01"></path>
        </svg>
        <span><?php echo stridebr_e(stridebr_t('nav.more')); ?></span>
    </button>
</nav>
<div class="mobile-more-sheet" data-mobile-more-sheet hidden>
    <button class="mobile-more-backdrop" type="button" data-mobile-more-close aria-label="<?php echo stridebr_e(stridebr_t('common.close')); ?>"></button>
    <div class="mobile-more-panel" role="dialog" aria-modal="true" aria-label="<?php echo stridebr_e(stridebr_t('nav.more_options')); ?>">
        <div class="mobile-more-handle"></div>
        <div class="mobile-more-header">
            <strong><?php echo stridebr_e(stridebr_t('nav.more')); ?></strong>
            <button class="mobile-more-close-button" type="button" data-mobile-more-close aria-label="<?php echo stridebr_e(stridebr_t('nav.close_menu')); ?>">×</button>
        </div>
        <a href="/user/gravar-atividade.php"><?php echo stridebr_e(stridebr_t('nav.record_gps')); ?></a>
        <a href="/user/amigos.php"><?php echo stridebr_e(stridebr_t('nav.friends')); ?></a>
        <a href="/user/agenda-mensal.php"><?php echo stridebr_e(stridebr_t('schedule.monthly_agenda')); ?></a>
        <a href="/user/biblioteca.php?tab=treinos"><?php echo stridebr_e(stridebr_t('nav.library')); ?></a>
        <a href="/user/biblioteca.php?tab=exercicios"><?php echo stridebr_e(stridebr_t('nav.library_exercises')); ?></a>
        <a href="/user/comparar-atividades.php"><?php echo stridebr_e(stridebr_t('nav.compare_activities')); ?></a>
        <a href="/user/importar-exportar.php"><?php echo stridebr_e(stridebr_t('nav.import_export')); ?></a>
        <a href="/user/treinador.php"><?php echo stridebr_e(stridebr_t('nav.trainer')); ?></a>
        <button type="button" data-quick-tools-open><?php echo stridebr_e(stridebr_t('nav.quick_tools')); ?></button>
        <a href="/user/ferramentastreino.php"><?php echo stridebr_e(stridebr_t('nav.training_tools')); ?></a>
        <a href="/calendario.php"><?php echo stridebr_e(stridebr_t('nav.events')); ?></a>
        <a href="/user/edit-profile.php"><?php echo stridebr_e(stridebr_t('nav.edit_profile')); ?></a>
        <a href="/user/settings.php"><?php echo stridebr_e(stridebr_t('nav.settings')); ?></a>
        <a href="/user/account.php"><?php echo stridebr_e(stridebr_t('nav.security')); ?></a>
        <a href="/pages/extras/changelog.php"><?php echo stridebr_e(stridebr_t('nav.news')); ?></a>

        <?php if (stridebr_has_role('moderator')): ?><a href="/admin/index.php"><?php echo stridebr_e(stridebr_has_role('admin') ? stridebr_t('nav.administration', [], 'Administração') : stridebr_t('nav.moderation', [], 'Moderação')); ?></a><?php endif; ?>
        <button class="mobile-more-dismiss" type="button" data-mobile-more-close><?php echo stridebr_e(stridebr_t('nav.close_menu')); ?></button>
    </div>
</div>

<?php require __DIR__ . '/quick_tools.php'; ?>
<?php endif; ?>
<?php echo stridebr_i18n_runtime_script(false); ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/scripts.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/page-loading.js')); ?>"></script>
<?php if ($footerLoggedIn): ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/quick-tools.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/workout-session.js')); ?>"></script>
<?php endif; ?>

<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/ui-preferences.js')); ?>" defer></script>
