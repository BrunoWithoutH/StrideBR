<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$headerLoggedIn = stridebr_is_logged_in();
$headerPhoto = (string) ($_SESSION['FotoUsuario'] ?? '');
if ($headerPhoto !== '' && !str_starts_with($headerPhoto, '/')) {
    $parts = parse_url($headerPhoto);
    if ($parts === false || !isset($parts['scheme']) || !in_array(stridebr_lower($parts['scheme']), ['http', 'https'], true) || filter_var($headerPhoto, FILTER_VALIDATE_URL) === false) {
        $headerPhoto = '';
    }
}
$headerPhoto = $headerPhoto !== '' ? $headerPhoto : '/assets/img/ui/userdefault.svg';
?>
<header class="site-header">
    <div class="header-inner">
        <a class="brand-link" href="<?php echo $headerLoggedIn ? '/home.php' : '/index.php'; ?>" aria-label="StrideBR">
            <img src="/assets/img/logos/stridebr-logo-white.svg" alt="StrideBR" class="nav-logo" width="87" height="34" decoding="async">
        </a>
        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-label="Abrir navegação">☰</button>
        <nav class="main-nav" data-nav-menu>
            <a href="/home.php">Início</a>
            <a href="/user/cronogramatreinos.php">Cronogramas</a>
            <a href="/user/atividades.php">Atividades</a>
            <a href="/user/bibliotecaexercicios.php">Exercícios</a>
            <a href="/user/ferramentastreino.php">Ferramentas</a>
            <a href="/calendario.php">Eventos</a>
        </nav>
        <div class="usersection">
            <?php if ($headerLoggedIn): ?>
                <details class="user-menu">
                    <summary><img class="userimage" src="<?php echo stridebr_e($headerPhoto); ?>" alt="Perfil"></summary>
                    <div class="user-menu-content">
                        <a href="/user/settings.php">Configurações</a>
                        <form method="POST" action="/function/logout.php">
                            <?php echo stridebr_csrf_field(); ?>
                            <button type="submit">Sair</button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a class="login-button" href="/login.php">Entrar</a>
            <?php endif; ?>
        </div>
    </div>
</header>
