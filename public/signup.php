<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';
require_once dirname(__DIR__) . '/src/function/product_analytics.php';
require_once dirname(__DIR__) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__) . '/src/function/sport_catalog.php';

if (stridebr_is_logged_in()) {
    header('Location: /home.php');
    exit;
}

$registrationEnabled = stridebr_feature_enabled($pdo, 'registration.enabled', true);
$inviteOnly = stridebr_feature_enabled($pdo, 'registration.invite_only.enabled', false);
$emailVerificationEnabled = stridebr_auth_email_verification_enabled($pdo);
$emailVerificationRequired = stridebr_auth_email_verification_required($pdo);
$errors = [];
$allowedGoals = ['organizar', 'condicionamento', 'prova', 'evolucao', 'rotina', 'lazer'];
$allowedExperience = ['', 'comecando', 'pratico', 'regular'];
$allowedTracking = ['frequencia', 'duracao', 'distancia', 'elevacao', 'metas', 'carga'];
$goalLabels = [
    'organizar' => 'Organizar meus treinos',
    'evolucao' => 'Acompanhar minha evolução',
    'condicionamento' => 'Melhorar condicionamento',
    'prova' => 'Preparar para uma prova',
    'rotina' => 'Manter uma rotina',
    'lazer' => 'Registrar por lazer',
];
$trackingChoices = [
    'frequencia' => ['Consistência', 'Frequência semanal e regularidade'],
    'duracao' => ['Tempo em atividade', 'Volume por duração'],
    'distancia' => ['Distância e ritmo', 'Corrida, caminhada e ciclismo'],
    'elevacao' => ['Altimetria', 'Ganho de elevação e percursos'],
    'metas' => ['Metas', 'Progresso dos objetivos definidos'],
    'carga' => ['Força', 'Carga, séries e volume na musculação'],
];

$modalidadesStmt = $pdo->query("SELECT idmodalidade, nome, slug, COALESCE(NULLIF(categoria,''),'Outros') AS categoria, COALESCE(NULLIF(familia_hub,''),'other') AS familia_hub, COALESCE(ordem_catalogo,9999) AS ordem_catalogo FROM modalidades WHERE ativo = TRUE AND idusuario IS NULL ORDER BY COALESCE(ordem_catalogo,9999), nome");
$modalidades = $modalidadesStmt->fetchAll() ?: [];
$modalidadeIds = array_map('strval', array_column($modalidades, 'idmodalidade'));
$signupSportGroups = sportCatalogGroups($modalidades);

$values = [
    'nome' => '',
    'email' => '',
    'invite' => trim((string) ($_GET['invite'] ?? '')),
    'sports' => [],
    'goals' => [],
    'experience' => '',
    'weekly_frequency' => 0,
    'tracking' => [],
    'units' => 'metric',
    'week_start' => 'sunday',
    'profile_visibility' => 'privado',
    'activity_visibility' => 'privado',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $signupIp = stridebr_client_ip() ?? '';
    if ($signupIp !== '' && stridebr_auth_limit_is_blocked($pdo, 'signup-ip', $signupIp)) {
        $errors[] = 'Muitos cadastros foram tentados desta conexão. Aguarde um pouco e tente novamente.';
    } elseif ($signupIp !== '') {
        stridebr_auth_limit_record_attempt($pdo, 'signup-ip', $signupIp, 20, 3600, 3600);
    }

    if (!$registrationEnabled) $errors[] = 'Novos cadastros estão temporariamente fechados.';

    $values['nome'] = stridebr_person_name_normalize((string) ($_POST['NomeUsuario'] ?? ''));
    $values['email'] = stridebr_lower(trim((string) ($_POST['EmailUsuario'] ?? '')));
    $values['invite'] = strtoupper(trim((string) ($_POST['CodigoConvite'] ?? '')));
    $values['sports'] = array_values(array_intersect($modalidadeIds, is_array($_POST['sports'] ?? null) ? array_map('strval', $_POST['sports']) : []));
    $values['goals'] = array_values(array_intersect($allowedGoals, is_array($_POST['goals'] ?? null) ? $_POST['goals'] : []));
    $values['experience'] = trim((string) ($_POST['experience'] ?? ''));
    $values['weekly_frequency'] = max(0, min(7, (int) ($_POST['weekly_frequency'] ?? 0)));
    $values['tracking'] = array_values(array_intersect($allowedTracking, is_array($_POST['tracking'] ?? null) ? $_POST['tracking'] : []));
    $values['units'] = (string) ($_POST['units'] ?? 'metric');
    $values['week_start'] = (string) ($_POST['week_start'] ?? 'sunday');
    $values['profile_visibility'] = (string) ($_POST['visibilidadeperfil'] ?? 'privado');
    $values['activity_visibility'] = (string) ($_POST['activity_visibility'] ?? 'privado');
    $password = (string) ($_POST['SenhaUsuario'] ?? '');
    $confirm = (string) ($_POST['ConfirmarSenhaUsuario'] ?? '');
    $accepted = isset($_POST['TermosUsuario']);

    if (!stridebr_person_name_is_valid($values['nome'], 80)) $errors[] = 'Informe um nome de até 80 caracteres usando letras latinas, números, espaços, ponto, apóstrofo ou hífen, sem símbolos decorativos.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || stridebr_length($values['email']) > 255) $errors[] = 'Informe um e-mail válido.';
    if (!stridebr_password_is_valid_length($password, 8, 128)) $errors[] = 'A senha deve ter entre 8 e 128 caracteres.';
    if ($password !== $confirm) $errors[] = 'As senhas não coincidem.';
    if (!$accepted) $errors[] = 'Você precisa aceitar os Termos de Uso e a Política de Privacidade.';
    if ($inviteOnly && $values['invite'] === '') $errors[] = 'Este cadastro exige um convite válido.';
    if (!in_array($values['experience'], $allowedExperience, true)) $errors[] = 'Nível de experiência inválido.';
    if (!in_array($values['units'], ['metric', 'imperial'], true)) $errors[] = 'Sistema de unidades inválido.';
    if (!in_array($values['week_start'], ['sunday', 'monday'], true)) $errors[] = 'Início da semana inválido.';
    if (!in_array($values['profile_visibility'], ['privado', 'amigos', 'publico'], true)) $errors[] = 'Privacidade do perfil inválida.';
    if (!in_array($values['activity_visibility'], ['privado', 'amigos', 'publico'], true)) $errors[] = 'Privacidade das atividades inválida.';

    if ($errors === []) {
        if (stridebr_auth_limit_is_blocked($pdo, 'signup-email', $values['email'])) {
            $errors[] = 'Não foi possível concluir o cadastro agora. Aguarde um pouco e tente novamente.';
        } else {
            stridebr_auth_limit_record_attempt($pdo, 'signup-email', $values['email'], 8, 3600, 3600);
            $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) LIMIT 1');
            $stmt->execute([':email' => $values['email']]);
            if ($stmt->fetchColumn()) $errors[] = 'Este e-mail já está cadastrado. Tente entrar com essa conta.';
        }
    }

    $inviteId = null;
    if ($errors === [] && $inviteOnly) {
        $inviteStmt = $pdo->prepare("SELECT idconvite FROM convites_alpha WHERE codigo_hash = :hash AND ativo = TRUE AND usos < usos_maximos AND (expira_em IS NULL OR expira_em > NOW()) LIMIT 1");
        $inviteStmt->execute([':hash' => hash('sha256', $values['invite'])]);
        $inviteId = $inviteStmt->fetchColumn();
        if ($inviteId === false) {
            $errors[] = 'Convite inválido, expirado ou já utilizado.';
            $inviteId = null;
        }
    }

    if ($errors === []) {
        $id = stridebr_generate_id();
        $preferences = [
            'units' => $values['units'],
            'week_start' => $values['week_start'],
            'locale' => 'auto',
            'theme' => 'system',
            'goals' => $values['goals'],
            'experience' => $values['experience'],
            'weekly_frequency' => $values['weekly_frequency'] > 0 ? $values['weekly_frequency'] : null,
            'tracking' => $values['tracking'],
            'activity_defaults' => [
                'visibility' => $values['activity_visibility'],
            ],
        ];
        $pdo->beginTransaction();
        try {
            if ($inviteOnly && is_string($inviteId)) {
                $consume = $pdo->prepare("UPDATE convites_alpha SET usos = usos + 1, ativo = CASE WHEN usos + 1 >= usos_maximos THEN FALSE ELSE ativo END WHERE idconvite = :id AND ativo = TRUE AND usos < usos_maximos AND (expira_em IS NULL OR expira_em > NOW())");
                $consume->execute([':id' => $inviteId]);
                if ($consume->rowCount() !== 1) throw new RuntimeException('O convite não está mais disponível.');
            }

            $stmt = $pdo->prepare('INSERT INTO usuarios (idusuario, nomeusuario, nome_exibicao, emailusuario, senhausuario, ipregistro, termos_versao, privacidade_versao, termos_aceitos_em, visibilidadeperfil, preferenciasusuario, onboarding_concluido) VALUES (:id, :nome, :nome_exibicao, :email, :senha, :ip, :termos, :privacidade, NOW(), :visibilidade, CAST(:preferencias AS jsonb), TRUE)');
            $stmt->execute([
                ':id' => $id,
                ':nome' => $values['nome'],
                ':nome_exibicao' => $values['nome'],
                ':email' => $values['email'],
                ':senha' => stridebr_password_hash($password),
                ':ip' => stridebr_client_ip(),
                ':termos' => stridebr_terms_version(),
                ':privacidade' => stridebr_privacy_version(),
                ':visibilidade' => $values['profile_visibility'],
                ':preferencias' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            if ($values['sports'] !== []) {
                $insertSport = $pdo->prepare('INSERT INTO modalidades_usuario (idusuario,idmodalidade,ativo,data_ativacao,data_desativacao) VALUES (:usuario,:modalidade,TRUE,NOW(),NULL) ON CONFLICT (idusuario,idmodalidade) DO UPDATE SET ativo=TRUE,data_ativacao=NOW(),data_desativacao=NULL');
                foreach ($values['sports'] as $idModalidade) $insertSport->execute([':usuario' => $id, ':modalidade' => $idModalidade]);
            }
            $pdo->commit();

            productAnalyticsRegistrar($pdo, $id, 'onboarding_completed', [
                'source' => 'signup_before_account',
                'sports' => count($values['sports']),
                'weekly_frequency' => $values['weekly_frequency'],
                'customized' => $values['sports'] !== [] || $values['goals'] !== [] || $values['experience'] !== '' || $values['weekly_frequency'] > 0 || $values['tracking'] !== [],
            ]);

            $sent = false;
            if ($emailVerificationEnabled) {
                try {
                    $sent = stridebr_send_verification_email($pdo, $id, $values['email'], $values['nome']);
                } catch (Throwable $mailError) {
                    error_log('StrideBR verification mail failed after signup: ' . $mailError->getMessage());
                }
            }
            stridebr_auth_limit_cleanup($pdo);

            if ($emailVerificationRequired) {
                stridebr_auth_set_pending_verification($id, $values['email'], '/home.php');
                if (!$sent) stridebr_flash('warning', 'A conta foi criada, mas o primeiro código não pôde ser enviado. Tente reenviar na próxima etapa.');
                header('Location: /verify-email.php');
                exit;
            }

            $newUser = stridebr_auth_load_user($pdo, $id);
            if (!$newUser) throw new RuntimeException('Não foi possível iniciar a nova conta.');
            stridebr_auth_start_session($pdo, $newUser, $signupIp);
            $_SESSION['OnboardingConcluido'] = true;
            header('Location: /home.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException && $e->getCode() === '23505') $errors[] = 'Este e-mail já está cadastrado. Tente entrar com essa conta.';
            elseif ($e instanceof RuntimeException) $errors[] = $e->getMessage();
            else throw $e;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo stridebr_e(stridebr_html_lang()); ?>" data-theme="<?php echo stridebr_e(stridebr_theme()); ?>" data-locale="<?php echo stridebr_e(stridebr_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php echo stridebr_ui_boot_script(); ?>
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/loginsignup.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <title>Começar | StrideBR</title>
</head>
<body class="onboarding-body signup-onboarding-body">
<div class="onboarding-shell signup-onboarding-shell">
        <a class="onboarding-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
    <main class="onboarding-card signup-onboarding-card" data-onboarding data-initial-step="<?php echo $errors !== [] ? '4' : '0'; ?>">
        <div class="onboarding-topline signup-onboarding-topline">
            <div class="signup-step-status">
                <span class="onboarding-step-kind" data-step-kind>Etapa opcional</span>
                <button type="button" class="onboarding-skip-inline" data-skip-to-account>Pular personalização</button>
            </div>
            <span class="onboarding-progress-label" data-step-label>1 de 5</span>
        </div>
        <div class="onboarding-progress"><span data-progress-bar></span></div>

        <?php foreach ($errors as $error): ?><div class="alert alert-danger signup-onboarding-alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
        <?php if (!$registrationEnabled): ?><div class="alert alert-info signup-onboarding-alert">A criação de novas contas está fechada no momento.</div><?php endif; ?>

        <?php if ($registrationEnabled): ?>
        <form method="POST" class="onboarding-form signup-onboarding-form">
            <?php echo stridebr_csrf_field(); ?>

            <section class="onboarding-step signup-onboarding-step is-active" data-step="0">
                <div class="onboarding-step-heading"><h1>O que você pratica?</h1></div>
                <div class="signup-sport-picker" data-signup-sport-picker>
                    <label class="signup-sport-search"><span>Buscar esporte</span><input type="search" placeholder="Ex.: corrida, musculação, tênis" data-signup-sport-search autocomplete="off"></label>
                    <div class="signup-sport-families" data-signup-sport-families>
                        <?php foreach ($signupSportGroups as $group): ?>
                            <button type="button" class="signup-sport-family-card" data-signup-sport-family-open="<?php echo stridebr_e((string) $group['key']); ?>">
                                <span><strong><?php echo stridebr_e((string) $group['label']); ?></strong><small><?php echo stridebr_e((string) $group['description']); ?></small></span>
                                <b><?php echo count($group['popular']) + count($group['more']); ?></b><i aria-hidden="true">›</i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="signup-sports-catalog" data-signup-sports-catalog>
                        <?php foreach ($signupSportGroups as $group): ?>
                            <section class="signup-sport-group signup-sport-family-panel" data-signup-sport-group data-signup-sport-family-panel="<?php echo stridebr_e((string) $group['key']); ?>" hidden>
                                <div class="signup-sport-family-head"><button type="button" data-signup-sport-family-back>← Categorias</button><div><strong><?php echo stridebr_e((string) $group['label']); ?></strong><small><?php echo stridebr_e((string) $group['description']); ?></small></div></div>
                                <div class="signup-sport-group-title">Mais comuns</div>
                                <div class="onboarding-choice-grid signup-sports-grid-main">
                                    <?php foreach ($group['popular'] as $modalidade): ?>
                                        <label class="choice-card choice-card-sport" data-signup-sport-card data-sport-name="<?php echo stridebr_e(stridebr_lower((string) $modalidade['nome'] . ' ' . (string) $group['label'])); ?>">
                                            <input type="checkbox" name="sports[]" value="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-summary-label="<?php echo stridebr_e((string) $modalidade['nome']); ?>"<?php echo in_array((string) $modalidade['idmodalidade'], $values['sports'], true) ? ' checked' : ''; ?>>
                                            <?php echo stridebr_sport_icon_html((string) $modalidade['slug'], 'signup-sport-icon'); ?>
                                            <span><?php echo stridebr_e((string) $modalidade['nome']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($group['more']): ?>
                                    <button type="button" class="signup-sport-more" data-signup-sport-more aria-expanded="false">Mais esportes <span aria-hidden="true">⌄</span></button>
                                    <div class="onboarding-choice-grid signup-sports-grid-main signup-sport-more-list" data-signup-sport-more-list hidden>
                                        <?php foreach ($group['more'] as $modalidade): ?>
                                            <label class="choice-card choice-card-sport" data-signup-sport-card data-sport-name="<?php echo stridebr_e(stridebr_lower((string) $modalidade['nome'] . ' ' . (string) $group['label'])); ?>">
                                                <input type="checkbox" name="sports[]" value="<?php echo stridebr_e((string) $modalidade['idmodalidade']); ?>" data-summary-label="<?php echo stridebr_e((string) $modalidade['nome']); ?>"<?php echo in_array((string) $modalidade['idmodalidade'], $values['sports'], true) ? ' checked' : ''; ?>>
                                                <?php echo stridebr_sport_icon_html((string) $modalidade['slug'], 'signup-sport-icon'); ?>
                                                <span><?php echo stridebr_e((string) $modalidade['nome']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                        <div class="signup-sport-empty" data-signup-sport-empty hidden>Nenhum esporte encontrado.</div>
                    </div>
                </div>
            </section>

            <section class="onboarding-step signup-onboarding-step" data-step="1" hidden>
                <div class="onboarding-step-heading"><h1>O que você quer do StrideBR?</h1></div>
                <div class="onboarding-choice-grid compact signup-purpose-grid">
                    <?php foreach ($goalLabels as $value => $label): ?>
                        <label class="choice-card"><input type="checkbox" name="goals[]" value="<?php echo stridebr_e($value); ?>" data-summary-label="<?php echo stridebr_e($label); ?>"<?php echo in_array($value, $values['goals'], true) ? ' checked' : ''; ?>><span><?php echo stridebr_e($label); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="onboarding-step signup-onboarding-step" data-step="2" hidden>
                <div class="onboarding-step-heading"><h1>Como você treina hoje?</h1></div>
                <div class="onboarding-grid signup-training-grid">
                    <label>Experiência
                        <select name="experience"><option value=""<?php echo $values['experience'] === '' ? ' selected' : ''; ?>>Prefiro não informar</option><option value="comecando"<?php echo $values['experience'] === 'comecando' ? ' selected' : ''; ?>>Estou começando</option><option value="pratico"<?php echo $values['experience'] === 'pratico' ? ' selected' : ''; ?>>Já pratico</option><option value="regular"<?php echo $values['experience'] === 'regular' ? ' selected' : ''; ?>>Treino regularmente</option></select>
                    </label>
                    <label>Frequência por semana
                        <select name="weekly_frequency"><option value="0">Prefiro não definir</option><?php for ($i=1;$i<=7;$i++): ?><option value="<?php echo $i; ?>"<?php echo $values['weekly_frequency'] === $i ? ' selected' : ''; ?>><?php echo $i; ?> dia<?php echo $i===1?'':'s'; ?></option><?php endfor; ?></select>
                    </label>
                </div>
                <div class="onboarding-subsection">
                    <strong>O que você quer ver primeiro no progresso?</strong>
                    <div class="onboarding-choice-grid compact signup-tracking-grid">
                        <?php foreach ($trackingChoices as $value => [$label, $description]): ?>
                            <label class="choice-card choice-card-detail"><input type="checkbox" name="tracking[]" value="<?php echo stridebr_e($value); ?>" data-summary-label="<?php echo stridebr_e($label); ?>"<?php echo in_array($value,$values['tracking'],true)?' checked':''; ?>><span><strong><?php echo stridebr_e($label); ?></strong><small><?php echo stridebr_e($description); ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="onboarding-step signup-onboarding-step" data-step="3" hidden>
                <div class="onboarding-step-heading"><h1>Preferências básicas</h1></div>
                <div class="onboarding-grid signup-defaults-grid">
                    <label>Unidades<select name="units"><option value="metric"<?php echo $values['units']==='metric'?' selected':''; ?>>Métricas (km, kg, cm)</option><option value="imperial"<?php echo $values['units']==='imperial'?' selected':''; ?>>Imperiais</option></select></label>
                    <label>Início da semana<select name="week_start"><option value="sunday"<?php echo $values['week_start']==='sunday'?' selected':''; ?>>Domingo</option><option value="monday"<?php echo $values['week_start']==='monday'?' selected':''; ?>>Segunda-feira</option></select></label>
                    <label>Perfil<select name="visibilidadeperfil"><option value="privado"<?php echo $values['profile_visibility']==='privado'?' selected':''; ?>>Privado</option><option value="amigos"<?php echo $values['profile_visibility']==='amigos'?' selected':''; ?>>Amigos</option><option value="publico"<?php echo $values['profile_visibility']==='publico'?' selected':''; ?>>Público</option></select></label>
                    <label>Novas atividades<select name="activity_visibility"><option value="privado"<?php echo $values['activity_visibility']==='privado'?' selected':''; ?>>Só eu</option><option value="amigos"<?php echo $values['activity_visibility']==='amigos'?' selected':''; ?>>Amigos</option><option value="publico"<?php echo $values['activity_visibility']==='publico'?' selected':''; ?>>Públicas</option></select></label>
                </div>
            </section>

            <section class="onboarding-step signup-onboarding-step signup-account-step" data-step="4" hidden>
                <div class="onboarding-step-heading"><h1>Crie sua conta</h1></div>
                <div class="signup-summary-block" data-summary-block hidden>
                    <div class="signup-summary-title">Suas escolhas</div>
                    <div class="onboarding-result" data-onboarding-summary aria-live="polite"></div>
                </div>
                <div class="signup-account-grid">
                    <label class="gps-field">Nome<input type="text" name="NomeUsuario" value="<?php echo stridebr_e($values['nome']); ?>" autocomplete="name" maxlength="80" required></label>
                    <label class="gps-field">E-mail<input type="email" name="EmailUsuario" value="<?php echo stridebr_e($values['email']); ?>" autocomplete="email" maxlength="255" required></label>
                    <?php if ($inviteOnly): ?><label class="gps-field is-wide">Código de convite<input type="text" name="CodigoConvite" value="<?php echo stridebr_e($values['invite']); ?>" autocomplete="off" maxlength="80" required></label><?php endif; ?>
                    <label class="gps-field">Senha<input type="password" name="SenhaUsuario" autocomplete="new-password" minlength="8" maxlength="128" required></label>
                    <label class="gps-field">Confirmar senha<input type="password" name="ConfirmarSenhaUsuario" autocomplete="new-password" minlength="8" maxlength="128" required></label>
                </div>
                <label class="signup-terms"><input type="checkbox" name="TermosUsuario" required><span>Li e aceito os <a href="/pages/legal/terms.php" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/pages/legal/privacy.php" target="_blank" rel="noopener">Política de Privacidade</a>.</span></label>
                <div class="login-signup signup-login-link"><span class="text">Já tem uma conta? <a href="/login.php">Entrar</a></span></div>
            </section>

            <div class="onboarding-actions signup-onboarding-actions">
                <button type="button" class="secondary-action" data-prev-step>Voltar</button>
                <div class="signup-action-end">
                    <button type="button" class="primary-action" data-next-step>Continuar</button>
                    <button type="submit" class="primary-action" data-finish-step hidden>Criar conta</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </main>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/onboarding.js')); ?>" defer></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>" defer></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/ui-preferences.js')); ?>" defer></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/i18n-runtime.js')); ?>" defer></script>
</body>
</html>
