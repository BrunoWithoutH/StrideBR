<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "Final product polish static failed: {$message}\n");
        exit(1);
    }
};

require_once $root . '/src/function/exercise_resolver.php';

$cronJs = $read('public/assets/js/cronogramas.js');
$cronPage = $read('public/user/cronogramatreinos.php');
$activityPage = $read('public/user/atividades.php');
$activityJs = $read('public/assets/js/atividades.js');
$activityCss = $read('public/assets/css/atividades.css');
$presenter = $read('src/function/atividade_presenter.php');
$auth = $read('src/includes/auth.php');
$forgot = $read('public/forgot-password.php');
$reset = $read('public/reset-password.php');
$strength = $read('src/function/strength_activity.php');
$scheduleFn = $read('src/function/cronograma.php');
$library = $read('public/user/biblioteca.php');
$libraryJs = $read('public/assets/js/library.js');
$libraryCss = $read('public/assets/css/cronogramas.css');
$trainer = $read('public/user/treinador.php');
$trainerLayout = $read('src/layout/trainer_as_athlete.php');
$trainerJs = $read('public/assets/js/trainer.js');
$progress = $read('public/user/progresso.php');
$progressJs = $read('public/assets/js/progresso.js');
$progressCss = $read('public/assets/css/sport-hub.css');
$home = $read('public/home.php');
$events = $read('public/calendario.php');
$friends = $read('public/user/amigos.php');
$profile = $read('public/user/perfil.php');
$header = $read('src/layout/header.php');
$footer = $read('src/layout/footer.php');
$headerCss = $read('public/assets/css/style.css');
$agenda = $read('public/user/agenda-mensal.php');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$assert(str_contains($cronJs, 'const navigateScheduleWorkspace = async') && str_contains($cronJs, "history.pushState({stridebrWorkspace:true}"), 'Cronogramas deve usar navegação parcial com History API.');
$assert(str_contains($cronJs, "window.addEventListener('popstate'") && str_contains($cronJs, "navigateScheduleWorkspace(window.location.href, {historyMode:'none'})"), 'Cronogramas deve revalidar o workspace no Back/Forward.');
$assert(str_contains($cronJs, "navigateScheduleWorkspace(url, {historyMode:'push'})") && str_contains($cronJs, "window.location.href = url.toString()"), 'Troca de cronograma deve usar fetch com fallback de navegação normal.');
$assert(substr_count($cronPage, 'data-schedule-workspace-nav') >= 4 && str_contains($cronPage, 'href="/user/cronogramatreinos.php'), 'Navegação parcial deve preservar href real.');
$assert(str_contains($cronJs, "workspace?.setAttribute('aria-busy', 'true')") && str_contains($cronJs, "workspace?.classList.add('is-refreshing')"), 'Cronogramas deve sinalizar loading somente no workspace.');
$assert(str_contains($cronJs, "action !== 'delete_workout' && action !== 'undo_delete_workout'") && str_contains($cronJs, "'Accept':'application/json'") && str_contains($cronJs, "await refreshScheduleView()"), 'Apagar/desfazer treino deve usar mutation assíncrona com revalidação local.');
$assert(str_contains($cronJs, 'event.stopImmediatePropagation()') && str_contains($cronJs, 'form.dataset.confirm') && str_contains($cronJs, '}, true);'), 'Exclusão assíncrona deve preservar confirmação antes do POST.');
$assert(str_contains($cronPage, '$wantsJson = str_contains') && str_contains($cronPage, "'undo_token' =>") && str_contains($cronPage, "'ok' => true"), 'Handler existente de Cronogramas deve oferecer resposta JSON sem criar backend paralelo.');

$assert(str_contains($presenter, 'COUNT(DISTINCT ordem_exercicio) AS exercises') && str_contains($presenter, 'COUNT(*) AS sets'), 'Resumo de força deve agregar exercícios e séries sem N+1.');
$assert(str_contains($presenter, 'atividadeHistoricoStrengthPreviewHtml') && str_contains($activityCss, '.activity-row-strength-preview'), 'Histórico de força deve usar prévia compacta própria.');
$assert(!str_contains($presenter, "['label' => stridebr_t('activity.history.code')") && !str_contains($presenter, "['label' => stridebr_t('activity.history.focus')"), 'Código e foco não podem voltar ao grid principal de métricas.');
$assert(str_contains($activityCss, '.activity-row-strength-code') && str_contains($activityCss, 'text-overflow:ellipsis'), 'Código deve ser badge compacto e foco deve ser truncável.');
$assert(str_contains($activityJs, 'renderStrengthPreview(item)') && str_contains($activityJs, 'activity-row-strength-focus'), 'Linhas carregadas dinamicamente devem manter a prévia compacta de força.');

$assert(str_contains($activityPage, 'data-bulk-toolbar') && str_contains($activityPage, 'data-bulk-edit') && str_contains($activityPage, 'data-bulk-dialog'), 'Modo seleção deve substituir o header e mover edição em lote para dialog.');
$assert(str_contains($activityPage, 'data-bulk-cancel') && str_contains($activityPage, 'data-bulk-delete'), 'Modo seleção deve ter saída clara e ação destrutiva separada.');
$assert(str_contains($activityJs, 'const bulkHasChanges =') && str_contains($activityJs, 'submit.disabled = bulkSelected.size === 0 || !bulkHasChanges()'), 'Aplicar deve ficar desabilitado sem seleção ou sem alteração.');
$assert(str_contains($activityJs, "tr('activity.bulk_select_loaded_count'") && str_contains($activityJs, "tr('activity.bulk_clear_loaded'"), 'Selecionar carregadas deve ter semântica precisa.');
$assert(str_contains($activityCss, '.activity-bulk-dialog') && str_contains($activityCss, '@media(max-width:760px)') && str_contains($activityCss, '.activity-bulk-toolbar-actions'), 'Batch mobile deve usar toolbar contextual e dialog/bottom sheet sem clipping.');

$assert(str_contains($auth, 'random_int(100000, 999999)') && str_contains($auth, "stridebr_auth_create_verification_code(\$pdo, \$userId, \$email, 15, 'redefinir_senha')"), 'Reset deve usar código CSPRNG de seis dígitos por 15 minutos.');
$assert(str_contains($auth, "hash('sha256', \$code)") && str_contains($auth, "tipo = 'redefinir_senha'"), 'Código de reset deve ser armazenado somente como hash em auth_tokens.');
$assert(str_contains($reset, 'autocomplete="one-time-code"') && str_contains($reset, 'pattern="[0-9]{6}"'), 'Tela de reset deve usar input de código acessível e numérico.');
$assert(str_contains($reset, "'reset-code-flow'") && str_contains($reset, '6, 900, 900') && str_contains($reset, "'reset-code-ip'") && str_contains($reset, '20, 900, 900'), 'Código de reset deve ter rate limit por fluxo e IP.');
$assert(str_contains($auth, "'expires_at' => time() + 600") && str_contains($reset, 'stridebr_auth_start_password_reset_session'), 'Código válido deve abrir sessão limitada de redefinição.');
$assert(str_contains($reset, 'sessao_versao = sessao_versao + 1') && str_contains($reset, 'stridebr_session_revoke_all') && !str_contains($reset, "\$_SESSION['Usuario']"), 'Reset deve revogar sessões antigas e não autenticar automaticamente.');
$assert(str_contains($forgot, 'auth.reset_request_generic') || str_contains($reset, 'auth.reset_request_generic'), 'Resposta pública do reset deve continuar genérica.');
$assert(str_contains($auth, "stridebr_t('auth.reset_email_body'") && str_contains($auth, "stridebr_t('auth.reset_email_subject'"), 'E-mail do novo fluxo de reset deve usar i18n central.');
$assert(str_contains($reset, "action\" value=\"resend") && str_contains($auth, 'criado_em > NOW() - INTERVAL \'2 minutes\''), 'Reenvio deve respeitar cooldown e invalidar código anterior pelo helper existente.');

$catalog = [
    ['idexercicio' => 'ex_reto', 'nome' => 'Supino Reto', 'slug' => 'supino-reto'],
    ['idexercicio' => 'ex_inclinado', 'nome' => 'Supino Inclinado', 'slug' => 'supino-inclinado'],
    ['idexercicio' => 'ex_agachamento', 'nome' => 'Agachamento Livre', 'slug' => 'agachamento-livre'],
];
$assert(stridebr_normalize_exercise_name('  SUPÍNO   RETO ') === 'supino reto', 'Normalização deve lidar com case, acentos e espaços.');
$assert(stridebr_normalize_exercise_name('supino-reto') === 'supino reto', 'Normalização deve tratar hífen como separador.');
$assert(stridebr_normalize_exercise_name('Supino, reto!') === 'supino reto', 'Normalização deve remover pontuação leve.');
$exact = stridebr_exercise_resolve_catalog($catalog, ['nome' => ' SUPINO   RETO ']);
$assert(($exact['match']['idexercicio'] ?? '') === 'ex_reto' && ($exact['reason'] ?? '') === 'normalized_name', 'Nome canônico normalizado deve resolver exatamente.');
$slug = stridebr_exercise_resolve_catalog($catalog, ['slug' => 'agachamento-livre']);
$assert(($slug['match']['idexercicio'] ?? '') === 'ex_agachamento' && ($slug['reason'] ?? '') === 'slug', 'Slug exato deve resolver exercício.');
$alias = stridebr_exercise_resolve_catalog($catalog, ['nome' => 'agachamento com peso livre'], ['  Agachamento-com Peso Livre ' => 'Agachamento Livre']);
$assert(($alias['match']['idexercicio'] ?? '') === 'ex_agachamento' && ($alias['reason'] ?? '') === 'alias', 'Resolver deve normalizar alias curado e resolver sem banco novo.');
$typo = stridebr_exercise_resolve_catalog($catalog, ['nome' => 'Supino Retoo']);
$assert(in_array(($typo['status'] ?? ''), ['matched', 'suggest'], true) && (($typo['match']['idexercicio'] ?? 'ex_reto') === 'ex_reto'), 'Typo pequeno deve resolver ou sugerir o exercício correto.');
$ambiguous = stridebr_exercise_resolve_catalog($catalog, ['nome' => 'Supino']);
$assert(($ambiguous['status'] ?? '') !== 'matched', 'Entrada ambígua não pode ser auto-vinculada.');
$wrong = stridebr_exercise_resolve_catalog($catalog, ['nome' => 'Supino Reto']);
$assert(($wrong['match']['idexercicio'] ?? '') !== 'ex_inclinado', 'Supino reto nunca pode virar supino inclinado.');
$unknown = stridebr_exercise_resolve_catalog($catalog, ['nome' => 'Movimento completamente desconhecido']);
$assert(($unknown['status'] ?? '') === 'unknown' && ($unknown['match'] ?? null) === null, 'Exercício desconhecido deve continuar válido como snapshot/custom.');
$assert(str_contains($strength, 'stridebr_exercise_resolve_catalog') && str_contains($scheduleFn, 'stridebr_exercise_resolve_catalog'), 'Registro/import e cronograma manual devem compartilhar o resolver do servidor.');
$assert(str_contains($activityJs, "tr('activity.strength.looks_like'") && !str_contains($activityJs, 'strengthSimilarity(name, candidate.name) >= 0.97'), 'Frontend deve sugerir fuzzy sem auto-link agressivo.');

$assert(str_contains($library, 'class="library-page-heading-actions"') && str_contains($library, 'data-library-heading-actions="treinos"') && str_contains($library, 'data-library-heading-actions="exercicios"'), 'Biblioteca deve manter um único slot estrutural para ações contextuais por tab.');
$assert(str_contains($libraryJs, 'history.pushState({libraryTab: tab}') && str_contains($libraryJs, "window.addEventListener('popstate'"), 'Tabs da Biblioteca devem preservar History API.');
$assert(str_contains($libraryCss, '.library-heading-actions[hidden]{display:none!important}') && str_contains($libraryCss, '.library-page-heading-actions{'), 'Ações inativas da Biblioteca devem obedecer hidden e não criar uma terceira coluna no heading.');
$assert(str_contains($libraryCss, '.library-heading-actions[data-library-heading-actions="treinos"]{grid-template-columns:1fr}'), 'Ação primária de Treinos deve continuar fácil de encontrar no mobile.');
$assert(str_contains($activityCss, '.activity-bulk-toolbar{') && str_contains($activityCss, '.activity-bulk-dialog{') && str_contains($activityCss, '.activity-bulk-dialog .activity-bulk-fields{display:grid;'), 'Batch deve manter seleção no header e edição em dialog compacto.');
$assert(str_contains($read('public/user/bibliotecaexercicios.php'), "require __DIR__ . '/biblioteca.php';") && str_contains($read('public/user/bibliotecatreinos.php'), "require __DIR__ . '/biblioteca.php';"), 'Wrappers antigos da Biblioteca devem continuar compatíveis sem páginas duplicadas.');

$assert(!str_contains($trainer, "stridebr_t('trainer.open_agenda')") && !str_contains($trainer, "stridebr_t('trainer.subtitle')"), 'Heading de Treinador não deve duplicar Agenda nem carregar subtitle genérico.');
$assert(str_contains($trainerLayout, "stridebr_t('trainer.subtitle')") && str_contains($trainerLayout, "stridebr_t('trainer.permissions_help')"), 'Copy de privacidade deve viver no contexto de permissões.');
$assert(str_contains($trainer, 'data-trainer-athlete-link') && str_contains($trainerJs, 'replaceAthleteWorkspace') && str_contains($trainerJs, 'history.pushState({trainerAthlete: true}'), 'Troca de atleta deve atualizar workspace sem reload quando JS está disponível.');
$assert(str_contains($trainerJs, "window.addEventListener('popstate'") && str_contains($trainerJs, 'window.location.assign(athleteLink.href)'), 'Trainer deve suportar Back/Forward e fallback de link real.');
$assert(str_contains($trainer, "stridebr_t('trainer.as_athlete')") === false && str_contains($trainerLayout, "stridebr_t('trainer.as_athlete')"), 'Como atleta deve ser uma seção única, não duplicada no shell.');

foreach (['progress.question_consistency','progress.consistency_help_product','progress.question_volume','progress.question_trend','progress.question_sports','progress.modalities_help','progress.question_compare','progress.period_comparison_help'] as $key) {
    $assert(!str_contains($progress, $key) && !str_contains($pt, "'{$key}'") && !str_contains($en, "'{$key}'"), "Copy decorativa {$key} deve ser removida da UI e dos locales.");
}
$assert(str_contains($progress, 'progress.active_days_count.one') && str_contains($progress, 'progress.active_days_count.other'), 'Consistência deve mostrar somente contagem compacta de dias ativos.');
$assert(str_contains($progressCss, 'height:220px') && str_contains($progressCss, 'max-height:260px'), 'Gráficos de Progresso devem ficar mais densos sem desaparecer.');
$assert(str_contains($progress, 'progress-comparison-list') && !preg_match('/is-positive|is-negative|success|danger/i', substr($progress, strpos($progress, 'progress-comparison-list')) ?: ''), 'Comparação entre períodos deve permanecer semanticamente neutra.');
$assert(str_contains($progressJs, 'AbortController') && str_contains($progressJs, 'history.pushState'), 'Progresso deve preservar navegação parcial existente.');

$assert(!str_contains($home, 'home.active_goals_help'), 'Ajuda redundante das metas ativas deve sair da Home.');
$assert(str_contains($events, "stridebr_t('events.subtitle')"), 'Subtitle de Eventos deve permanecer porque explica escopo e links oficiais.');
$assert(str_contains($friends, "stridebr_t('friends.subtitle')"), 'Subtitle de Amigos deve permanecer porque explica a ação principal.');
$assert(str_contains($agenda, "stridebr_t('agenda.subtitle_self')"), 'Subtitle da Agenda deve permanecer porque explica recorrências e datas específicas.');
$assert(str_contains($events, "stridebr_t('common.events')") && !preg_match('/<h1[^>]*>\s*Calend[aá]rio\s*<\/h1>/ui', $events), 'Rota /calendario.php deve exibir Eventos, não o filename interno.');

$assert(str_contains($profile, '$publicProfilesEnabled = stridebr_feature_enabled') && str_contains($profile, '!$isSelf && !$isFriend && !$publicProfilesEnabled'), 'Flag de perfil público deve ser avaliada depois da amizade e não bloquear amigo autorizado.');
$assert(str_contains($profile, '$isFriend && in_array($visibility, [\'amigos\', \'publico\'], true)'), 'Perfil Amigos/Público deve reconhecer amizade aceita sem abrir perfil privado.');
$assert(str_contains($friends, 'class="person-identity-link"') && substr_count($friends, 'class="person-identity-link"') >= 3, 'Busca, solicitações e lista de amigos devem oferecer bloco de identidade clicável.');
$assert(str_contains($friends, '<div class="person-actions"><form') && str_contains($friends, '<form method="POST" data-confirm'), 'Ações mutáveis dos cards de pessoas devem permanecer fora do link de perfil.');
$assert(str_contains($header, 'class="mobile-global-menu"') && str_contains($footer, 'class="mobile-nav-item mobile-profile-tab'), 'Mobile deve usar hamburger global no header e Perfil direto na bottom nav.');
$assert(str_contains($header, 'data-header-menu-close') && str_contains($footer, 'class="mobile-nav-avatar"'), 'Menu global mobile deve ter backdrop fechável e a aba Perfil deve usar avatar real.');
$assert(str_contains($libraryCss, ".schedule-month-calendar-wrap {\n        height: auto;\n        max-height: none;") && str_contains($libraryCss, 'overflow-y: visible;'), 'Mês desktop deve usar o scroll vertical do documento, sem container vertical interno.');
$assert(str_contains($libraryCss, '.schedule-month-calendar .monthly-day:not(.is-outside):hover{background:var(--ui-surface-hover)}') && str_contains($libraryCss, 'schedule-month-empty-day{display:grid') && str_contains($libraryCss, 'color:var(--ui-faint)'), 'Estados do Mês devem usar tokens e não vazar superfícies claras no dark mode.');

foreach (['activity.bulk_cancel_selection','activity.strength.looks_like','auth.reset_code_title','auth.confirm_code','auth.reset_email_subject','auth.reset_email_body','progress.active_days_count.one','trainer.invite_declined'] as $key) {
    $assert(str_contains($pt, "'{$key}'") && str_contains($en, "'{$key}'"), "Key {$key} precisa existir em PT-BR e EN.");
}

printf("✓ final product polish static: %d assertions\n", $checks);
