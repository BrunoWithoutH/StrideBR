<?php
$root = dirname(__DIR__, 2);
$ui = file_get_contents($root . '/public/assets/css/ui-refresh.css');
$planning = file_get_contents($root . '/public/assets/css/cronogramas.css');
$sportPicker = file_get_contents($root . '/src/layout/sport_picker.php');
$personalization = file_get_contents($root . '/src/layout/sport_personalization.php');
$settings = file_get_contents($root . '/public/user/settings.php');
$scripts = file_get_contents($root . '/public/assets/js/scripts.js');
$edit = file_get_contents($root . '/public/user/editatividade.php');
$event = file_get_contents($root . '/public/evento.php');
$exchange = file_get_contents($root . '/public/user/importar-exportar.php');
$scheduleExercises = file_get_contents($root . '/public/user/exercicioscronograma.php');
$equipment = file_get_contents($root . '/public/user/equipamentos.php');
$adminUser = file_get_contents($root . '/public/admin/user.php');
$baseCss = file_get_contents($root . '/public/assets/css/style.css');
$loginCss = file_get_contents($root . '/public/assets/css/loginsignup.css');
$login = file_get_contents($root . '/public/login.php');
$signup = file_get_contents($root . '/public/signup.php');
$forgot = file_get_contents($root . '/public/forgot-password.php');
$reset = file_get_contents($root . '/public/reset-password.php');
$loginJs = file_get_contents($root . '/public/assets/js/loginform.js');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "Falhou: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($ui, '.context-back-button {'), 'controle Back compartilhado existe');
$assert(str_contains($ui, 'border: var(--border-width) solid var(--ui-border-soft);'), 'Back usa borda semântica discreta');
$assert(str_contains($ui, '.context-back-button:hover'), 'Back possui hover');
$assert(str_contains($ui, 'min-height: var(--control-touch);'), 'Back ganha alvo touch no mobile');
$assert(substr_count($sportPicker, 'context-back-button') >= 2, 'picker usa Back compartilhado nos dois renderers');
$assert(str_contains($personalization, 'context-back-button'), 'personalização usa Back compartilhado');
$assert(str_contains($settings, 'context-back-button'), 'settings usa Back compartilhado');
$assert(str_contains($scripts, 'window.StrideBRSafeBack'), 'helper seguro de navegação está disponível');
$assert(str_contains($scripts, 'document.referrer'), 'Back avalia origem real de navegação');
$assert(str_contains($scripts, 'url.origin !== window.location.origin'), 'Back só usa histórico para mesma origem');
$assert(str_contains($edit, 'data-safe-back'), 'Editar atividade possui fallback Back seguro');
$assert(str_contains($event, 'data-safe-back'), 'Detalhe de evento possui fallback Back seguro');
$assert(str_contains($exchange, 'data-safe-back'), 'Importar/exportar possui fallback Back seguro');
$assert(str_contains($scheduleExercises, 'data-safe-back'), 'editor de exercícios do cronograma possui fallback Back seguro');
$assert(str_contains($equipment, 'data-safe-back'), 'Equipamentos preserva Back seguro no PWA');
$assert(str_contains($adminUser, 'context-back-button') && str_contains($adminUser, 'data-safe-back'), 'detalhe administrativo não usa Back como texto solto');
$assert(str_contains($planning, '.library-exercise-grid-modern .library-exercise-card{') && str_contains($planning, 'border:1px solid var(--ui-border)'), 'Library Exercises usa token de borda');
$assert(str_contains($planning, '.library-exercise-grid-modern .library-card-actions{') && str_contains($planning, 'border-top:1px solid var(--ui-border-soft)'), 'separador das ações da Library usa token discreto');
$assert(!str_contains($planning, '.library-exercise-grid-modern .library-exercise-card{min-width:0;display:flex;flex-direction:column;gap:8px;padding:13px;border:1px solid #d8dee7'), 'Library moderna não mantém borda clara literal');
$assert(str_contains($ui, '[data-generic-sport-picker] [hidden] { display:none!important; }'), 'hidden do picker vence display de layout');
$assert(str_contains($planning, '.library-exercise-card[hidden]') && preg_match('/\.library-exercise-card\[hidden\]\s*\{\s*display:\s*none;\s*\}/', $planning) === 1, 'hidden da Library permanece estrutural');
$assert(str_contains($ui, 'right: max(12px, env(safe-area-inset-right));'), 'picker mobile respeita safe area lateral');
$assert(str_contains($ui, 'bottom: max(12px, env(safe-area-inset-bottom));'), 'picker mobile respeita safe area inferior');
$assert(str_contains($ui, '.library-editor-dialog,.library-exercise-dialog{max-height:calc(100dvh - max(12px,env(safe-area-inset-top)));padding-bottom:max(15px,env(safe-area-inset-bottom))!important}'), 'modais da Library respeitam viewport e safe area');
$assert(preg_match('/\.site-footer\s*\{([^}]*)\}/s', $baseCss, $footerMatch) === 1 && !str_contains($footerMatch[1], 'content-visibility') && !str_contains($footerMatch[1], 'contain-intrinsic-size'), 'footer não reserva altura intrínseca artificial fora da viewport');
$assert(str_contains($ui, '.calendar-view[hidden]') && str_contains($ui, '.schedule-month-view[hidden]') && str_contains($ui, '.agenda-view[hidden]'), 'views ocultas de Cronogramas não participam do layout');
$assert(str_contains($planning, '.zoom-controls[hidden]') && preg_match('/\.zoom-controls\[hidden\]\s*\{\s*display:\s*none;\s*\}/', $planning) === 1, 'zoom de Cronogramas desaparece fora da Semana');
$assert(str_contains($login, 'type="button" class="showHidePw"') && str_contains($login, 'aria-controls="login-password"') && str_contains($login, 'autocomplete="current-password"'), 'login mantém toggle explícito sem alterar autocomplete');
$assert(str_contains($loginJs, "document.getElementById(button.getAttribute('aria-controls'))") && !str_contains($loginJs, "closest('.input-field')"), 'toggle de senha aponta explicitamente para o input atual');
$assert(str_contains($loginCss, '.auth-modern-field-wrap .showHidePw') && str_contains($loginCss, 'width: 72px;'), 'toggle de senha mantém largura estável');
$assert(substr_count($reset, 'type="button" class="showHidePw"') === 2 && str_contains($reset, 'aria-controls="reset-password"') && str_contains($reset, 'aria-controls="reset-password-confirm"'), 'reset possui toggles independentes nas duas senhas');
$assert(substr_count($reset, 'autocomplete="new-password"') === 2 && str_contains($reset, "/assets/js/loginform.js"), 'reset preserva new-password e reutiliza o toggle validado');
$assert(str_contains($signup, 'signup-login-link') && str_contains($signup, "stridebr_t('auth.already_account')") && str_contains($signup, 'href="/login.php"'), 'cadastro mantém ação secundária para login');
$assert(str_contains($forgot, 'auth-unified-card') && str_contains($forgot, 'aria-describedby="recovery-guidance"') && str_contains($forgot, 'role="status"'), 'recuperação mantém cartão unificado, orientação e feedback acessível');

printf("✓ visual consistency static: %d assertions\n", $assertions);
