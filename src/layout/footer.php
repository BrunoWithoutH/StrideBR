<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$footerLoggedIn = stridebr_is_logged_in();
$feedbackEnabled = false;
if ($footerLoggedIn) {
    if (!isset($pdo) || !($pdo instanceof PDO)) { require_once dirname(__DIR__) . '/config/pg_config.php'; }
    $feedbackEnabled = stridebr_feature_enabled($pdo, 'feedback.enabled', false);
}
$navActive = static function (array $prefixes) use ($currentPath): string {
    foreach ($prefixes as $prefix) {
        if ($currentPath === $prefix || str_starts_with($currentPath, rtrim($prefix, '/') . '/')) return ' is-active';
    }
    return '';
};
?>
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-top">
            <div class="footer-brand">
                <img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo-white.svg')); ?>" alt="StrideBR" class="footer-logo" width="82" height="32" loading="lazy" decoding="async">
                <p>Planeje, execute e registre atividades físicas do seu jeito.</p>
            </div>
            <div class="footer-column"><h4>StrideBR</h4><a href="/pages/about/about.php">Sobre</a><a href="/pages/about/team.php">Equipe</a><a href="/pages/about/contact.php">Contato</a></div>
            <div class="footer-column"><h4>Ajuda</h4><a href="/pages/help/faq.php">FAQ</a><a href="/pages/help/support.php">Suporte</a><?php if ($feedbackEnabled): ?><a href="/feedback.php">Feedback alpha</a><?php endif; ?></div>
            <div class="footer-column"><h4>Legal</h4><a href="/pages/legal/terms.php">Termos</a><a href="/pages/legal/privacy.php">Privacidade</a><a href="/pages/legal/cookies.php">Cookies</a></div>
            <div class="footer-column"><h4>Projeto</h4><a href="/pages/extras/roadmap.php">Roadmap</a><a href="/pages/extras/changelog.php">Atualizações</a><a href="/pages/extras/credits.php">Créditos</a></div>
        </div>
        <div class="footer-bottom"><a href="https://github.com/BrunoWithoutH/StrideBR" target="_blank" rel="noopener noreferrer">GitHub</a><p>© <?php echo date('Y'); ?> StrideBR.</p></div>
    </div>
</footer>

<?php if ($footerLoggedIn && $feedbackEnabled): ?><a class="feedback-fab" href="/feedback.php?from=<?php echo rawurlencode($currentPath); ?>" aria-label="Enviar feedback">Feedback</a><?php endif; ?>

<?php if ($footerLoggedIn): ?>
<nav class="mobile-bottom-nav" aria-label="Navegação principal no celular">
    <a class="mobile-nav-item<?php echo $navActive(['/home.php']); ?>" href="/home.php" aria-label="Início">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
            <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        </svg>
        <span>Início</span>
    </a>
    <a class="mobile-nav-item<?php echo $navActive(['/user/cronogramatreinos.php', '/user/exercicioscronograma.php']); ?>" href="/user/cronogramatreinos.php" aria-label="Treinos">
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
        <span>Treinos</span>
    </a>
    <a class="mobile-nav-item<?php echo $navActive(['/user/atividades.php', '/user/editatividade.php']); ?>" href="/user/atividades.php" aria-label="Atividades">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
        </svg>
        <span>Atividades</span>
    </a>
    <a class="mobile-nav-item<?php echo $navActive(['/user/bibliotecaexercicios.php']); ?>" href="/user/bibliotecaexercicios.php" aria-label="Exercícios">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M17.596 12.768a2 2 0 1 0 2.829-2.829l-1.768-1.767a2 2 0 0 0 2.828-2.829l-2.828-2.828a2 2 0 0 0-2.829 2.828l-1.767-1.768a2 2 0 1 0-2.829 2.829z"></path>
            <path d="m2.5 21.5 1.4-1.4"></path>
            <path d="m20.1 3.9 1.4-1.4"></path>
            <path d="M5.343 21.485a2 2 0 1 0 2.829-2.828l1.767 1.768a2 2 0 1 0 2.829-2.829l-6.364-6.364a2 2 0 1 0-2.829 2.829l1.768 1.767a2 2 0 0 0-2.828 2.829z"></path>
            <path d="m9.6 14.4 4.8-4.8"></path>
        </svg>
        <span>Exercícios</span>
    </a>
    <button class="mobile-nav-item mobile-more-button<?php echo $navActive(['/user/amigos.php', '/user/ferramentastreino.php', '/user/settings.php', '/calendario.php', '/admin/index.php']); ?>" type="button" data-mobile-more-toggle aria-expanded="false" aria-label="Mais opções">
        <svg class="mobile-nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M4 12h.01"></path>
            <path d="M12 12h.01"></path>
            <path d="M20 12h.01"></path>
        </svg>
        <span>Mais</span>
    </button>
</nav>
<div class="mobile-more-sheet" data-mobile-more-sheet hidden>
    <button class="mobile-more-backdrop" type="button" data-mobile-more-close aria-label="Fechar"></button>
    <div class="mobile-more-panel">
        <div class="mobile-more-handle"></div>
        <strong>Mais</strong>
        <a href="/user/amigos.php">Amigos</a>
        <button type="button" data-quick-tools-open>Ferramentas rápidas</button>
        <a href="/user/ferramentastreino.php">Ferramentas de treino</a>
        <a href="/calendario.php">Eventos</a>
        <a href="/user/settings.php">Perfil e configurações</a>
        <?php if ($feedbackEnabled): ?><a href="/feedback.php">Enviar feedback</a><?php endif; ?>
        <?php if (stridebr_has_role('moderator')): ?><a href="/admin/index.php">Administração</a><?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/quick_tools.php'; ?>
<?php endif; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/scripts.js')); ?>"></script>
<?php if ($footerLoggedIn): ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/quick-tools.js')); ?>"></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/workout-session.js')); ?>"></script>
<?php endif; ?>
