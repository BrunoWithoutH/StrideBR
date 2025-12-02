<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(__DIR__) . '/src/config/pg_config.php';

if (isset($_SESSION['EmailUsuario'])) {
    header('Location: home.php');
}

$estalogado = isset($_SESSION['EmailUsuario']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="author" content="Bruno Evaristo Pinheiro">
    <link rel="icon" type="image/png" href="assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <title>StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="index.php"><img src="assets/img/logos/stridebr-logo.svg" alt="StrideBR"
                                class="nav-logo"></a>
                        <div class="dropdown">
                            <button class="dropbtn">Início<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="home.php" class="NavItem">Painel principal</a>
                                <a href="calendario.php" class="NavItem">Calendário de corridas</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Treinos<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="user/cronogramatreinos.php" class="NavItem">Seu Cronograma de Treinos</a>
                                <a href="user/atividades.php" class="NavItem">Atividades</a>
                                <a href="user/ferramentastreino.php" class="NavItem">Treino</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Ajuda<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="" class="NavItem">Suporte StrideBR</a>
                                <a href="" class="NavItem">FAQ</a>
                            </div>
                        </div>
                        <div class="usersection">
                            <?php if ($estalogado): ?>
                                <div class="dropdown" style="float:right;">
                                    <button class="dropbtnimg"><img class="userimage" src="assets/img/ui/userdefault.svg"
                                            alt="user"></button>
                                    <div class="dropdown-content" style="right: 0;">
                                        <a href="" class="NavItem">Configurações</a>
                                        <a href="function/logout.php">Sair</a>
                                    </div>
                                </div>

                            <?php else: ?>
                                <a href="login.php"><button class="LogButton">Entrar</button></a>
                            <?php endif; ?>
                        </div>

                    </nav>
                </section>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <section class="intro">
                    <h1>StrideBR</h1>
                    <h5>O amigo do atleta</h4>
                </section>
            </div>
        </div>

        <div class="separator"><i class="uil uil-angle-double-down"></i></div>

        <div class="row">
            <div class="col-sm-12">
                <section class="guide">
                    <div class="guide-item">
                        <div class="guide-index">
                            <i class="uil uil-chart-line"></i>
                        </div>
                        <h3>Monitore suas atividades físicas</h3>
                        <p>Acompanhe seu desempenho e progresso com nossas ferramentas de monitoramento.</p>
                    </div>

                    <div class="guide-item">
                        <div class="guide-index">
                            <i class="uil uil-schedule"></i>
                        </div>
                        <h3>Cronograma de treinos</h3>
                        <p>Organize seus treinos com um cronograma personalizado para alcançar seus objetivos.</p>
                    </div>

                    <div class="guide-item">
                        <div class="guide-index">
                            <i class="uil uil-users-alt"></i>
                        </div>
                        <h3>Comunidade de atletas</h3>
                        <p>Conecte-se com outros atletas, compartilhe experiências e participe de eventos.</p>
                    </div>

                    <div class="guide-item">
                        <div class="guide-index">
                            <i class="uil uil-calendar-alt"></i>
                        </div>
                        <h3>Calendário de eventos</h3>
                        <p>Fique por dentro das principais corridas e eventos esportivos na sua região.</p>
                    </div>

                    <div class="guide-item">
                        <div class="guide-index">
                            <i class="uil uil-shield-check"></i>
                        </div>
                        <h3>Suporte dedicado</h3>
                        <p>Conte com nossa equipe de suporte para ajudar você a aproveitar ao máximo o StrideBR.</p>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-top">
                <div class="footer-brand">
                    <img src="assets/img/logos/stridebr-logo.svg" alt="StrideBR" class="footer-logo"
                        style="filter:brightness(0) invert(1);">
                </div>
                <div class="footer-column">
                    <h4>About</h4>
                    <ul>
                        <li><a href="Placeholder">Placeholder</a></li>
                        <li><a href="Placeholder">Placeholder</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="Placeholder">Placeholder</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>More</h4>
                    <ul>
                        <li><a href="Placeholder">Placeholder</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-social">
                    <a href="https://github.com/BrunoWithoutH/StrideBR" aria-label="GitHub">
                        <svg xmlns="http://www.w3.org/2000/svg" class="social-icon" width="20" height="20"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                        </svg>
                    </a>
                    <a href="#placeholder" aria-label="Facebook">
                        <svg xmlns="http://www.w3.org/2000/svg" class="social-icon" width="20" height="20"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z" />
                        </svg>
                    </a>
                    <a href="#placeholder" aria-label="Twitter">
                        <svg xmlns="http://www.w3.org/2000/svg" class="social-icon" width="20" height="20"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z" />
                        </svg>
                    </a>
                    <a href="#placeholder" aria-label="Instagram">
                        <svg xmlns="http://www.w3.org/2000/svg" class="social-icon" width="20" height="20"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                        </svg>
                    </a>
                    <a href="#placeholder" aria-label="LinkedIn">
                        <svg xmlns="http://www.w3.org/2000/svg" class="social-icon" width="20" height="20"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                        </svg>
                    </a>
                    <a href="#placeholder" aria-label="YouTube">
                        <svg xmlns="http://www.w3.org/2000/svg" class="social-icon" width="20" height="20"
                            fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" />
                        </svg>
                    </a>
                </div>
                <p>© 2025 StrideBR. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
        </script>
    <script>
        document.querySelectorAll('.scroll-next').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.querySelector(btn.dataset.target);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>

</html>