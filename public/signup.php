<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/includes/auth.php';
require_once dirname(__DIR__) . '/src/function/product_analytics.php';
require_once dirname(__DIR__) . '/src/function/marketing.php';
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
stridebr_marketing_signup_start($pdo, $_GET);
$errors = [];
$allowedGoals = ['organizar', 'condicionamento', 'prova', 'evolucao', 'rotina', 'lazer'];
$allowedExperience = ['', 'comecando', 'pratico', 'regular'];
$allowedTracking = ['frequencia', 'duracao', 'distancia', 'elevacao', 'metas', 'carga'];
$goalLabels = [
    'organizar' => stridebr_t('onboarding.goal.organize'),
    'evolucao' => stridebr_t('onboarding.goal.progress'),
    'condicionamento' => stridebr_t('onboarding.goal.fitness'),
    'prova' => stridebr_t('onboarding.goal.race'),
    'rotina' => stridebr_t('onboarding.goal.routine'),
    'lazer' => stridebr_t('onboarding.goal.leisure'),
];
$trackingChoices = [
    'frequencia' => [stridebr_t('onboarding.tracking.consistency'), stridebr_t('onboarding.tracking.consistency_help')],
    'duracao' => [stridebr_t('onboarding.tracking.time'), stridebr_t('onboarding.tracking.time_help')],
    'distancia' => [stridebr_t('onboarding.tracking.distance'), stridebr_t('onboarding.tracking.distance_help')],
    'elevacao' => [stridebr_t('onboarding.tracking.elevation'), stridebr_t('onboarding.tracking.elevation_help')],
    'metas' => [stridebr_t('onboarding.tracking.goals'), stridebr_t('onboarding.tracking.goals_help')],
    'carga' => [stridebr_t('onboarding.tracking.strength'), stridebr_t('onboarding.tracking.strength_help')],
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
        $errors[] = stridebr_t('onboarding.signup_rate_limit');
    } elseif ($signupIp !== '') {
        stridebr_auth_limit_record_attempt($pdo, 'signup-ip', $signupIp, 20, 3600, 3600);
    }

    if (!$registrationEnabled) $errors[] = stridebr_t('onboarding.registration_closed_error');

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

    if (!stridebr_person_name_is_valid($values['nome'], 80)) $errors[] = stridebr_t('onboarding.name_error');
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || stridebr_length($values['email']) > 255) $errors[] = stridebr_t('onboarding.email_error');
    if (!stridebr_password_is_valid_length($password, 8, 128)) $errors[] = stridebr_t('onboarding.password_length_error');
    if ($password !== $confirm) $errors[] = stridebr_t('onboarding.password_mismatch');
    if (!$accepted) $errors[] = stridebr_t('onboarding.terms_required');
    if ($inviteOnly && $values['invite'] === '') $errors[] = stridebr_t('onboarding.invite_required');
    if (!in_array($values['experience'], $allowedExperience, true)) $errors[] = stridebr_t('onboarding.invalid_experience');
    if (!in_array($values['units'], ['metric', 'imperial'], true)) $errors[] = stridebr_t('onboarding.invalid_units');
    if (!in_array($values['week_start'], ['sunday', 'monday'], true)) $errors[] = stridebr_t('onboarding.invalid_week_start');
    if (!in_array($values['profile_visibility'], ['privado', 'amigos', 'publico'], true)) $errors[] = stridebr_t('onboarding.invalid_profile_privacy');
    if (!in_array($values['activity_visibility'], ['privado', 'amigos', 'publico'], true)) $errors[] = stridebr_t('onboarding.invalid_activity_privacy');

    if ($errors === []) {
        if (stridebr_auth_limit_is_blocked($pdo, 'signup-email', $values['email'])) {
            $errors[] = stridebr_t('onboarding.signup_later');
        } else {
            stridebr_auth_limit_record_attempt($pdo, 'signup-email', $values['email'], 8, 3600, 3600);
            $stmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(emailusuario) = lower(:email) LIMIT 1');
            $stmt->execute([':email' => $values['email']]);
            if ($stmt->fetchColumn()) $errors[] = stridebr_t('onboarding.email_exists');
        }
    }

    $inviteId = null;
    if ($errors === [] && $inviteOnly) {
        $inviteStmt = $pdo->prepare("SELECT idconvite FROM convites_alpha WHERE codigo_hash = :hash AND ativo = TRUE AND usos < usos_maximos AND (expira_em IS NULL OR expira_em > NOW()) LIMIT 1");
        $inviteStmt->execute([':hash' => hash('sha256', $values['invite'])]);
        $inviteId = $inviteStmt->fetchColumn();
        if ($inviteId === false) {
            $errors[] = stridebr_t('onboarding.invalid_invite');
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
                if ($consume->rowCount() !== 1) throw new RuntimeException(stridebr_t('onboarding.invite_unavailable'));
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

            stridebr_marketing_signup_complete($pdo, $id);

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
                if (!$sent) stridebr_flash('warning', stridebr_t('onboarding.code_send_warning'));
                header('Location: /verify-email.php');
                exit;
            }

            $newUser = stridebr_auth_load_user($pdo, $id);
            if (!$newUser) throw new RuntimeException(stridebr_t('onboarding.account_start_error'));
            stridebr_auth_start_session($pdo, $newUser, $signupIp);
            $_SESSION['OnboardingConcluido'] = true;
            header('Location: /home.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof PDOException && $e->getCode() === '23505') $errors[] = stridebr_t('onboarding.email_exists');
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

    <title><?php echo stridebr_e(stridebr_t('auth.signup_title')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="onboarding-body signup-onboarding-body">
<div class="onboarding-shell signup-onboarding-shell">
        <a class="onboarding-brand" href="/"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
    <main class="onboarding-card signup-onboarding-card" data-onboarding data-initial-step="<?php echo $errors !== [] ? '4' : '0'; ?>">
        <div class="onboarding-topline signup-onboarding-topline">
            <div class="signup-step-status">
                <span class="onboarding-step-kind" data-step-kind><?php echo stridebr_e(stridebr_t('onboarding.optional_step')); ?></span>
                <button type="button" class="onboarding-skip-inline" data-skip-to-account><?php echo stridebr_e(stridebr_t('auth.skip_personalization')); ?></button>
            </div>
            <span class="onboarding-progress-label" data-step-label><?php echo stridebr_e(stridebr_t('onboarding.step_count', ['step' => 1, 'total' => 5])); ?></span>
        </div>
        <div class="onboarding-progress"><span data-progress-bar></span></div>

        <?php foreach ($errors as $error): ?><div class="alert alert-danger signup-onboarding-alert"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
        <?php if (!$registrationEnabled): ?><div class="alert alert-info signup-onboarding-alert"><?php echo stridebr_e(stridebr_t('onboarding.closed')); ?></div><?php endif; ?>

        <?php if ($registrationEnabled): ?>
        <form method="POST" class="onboarding-form signup-onboarding-form">
            <?php echo stridebr_csrf_field(); ?>

            <section class="onboarding-step signup-onboarding-step is-active" data-step="0">
                <div class="onboarding-step-heading"><h1><?php echo stridebr_e(stridebr_t('onboarding.sports_question')); ?></h1></div>
                <?php $sportGroups = $signupSportGroups; $selectedSports = $values['sports']; require dirname(__DIR__) . '/src/layout/sport_personalization.php'; ?>
            </section>

            <section class="onboarding-step signup-onboarding-step" data-step="1" hidden>
                <div class="onboarding-step-heading"><h1><?php echo stridebr_e(stridebr_t('onboarding.goals_question')); ?></h1></div>
                <div class="onboarding-choice-grid compact signup-purpose-grid">
                    <?php foreach ($goalLabels as $value => $label): ?>
                        <label class="choice-card"><input type="checkbox" name="goals[]" value="<?php echo stridebr_e($value); ?>" data-summary-label="<?php echo stridebr_e($label); ?>"<?php echo in_array($value, $values['goals'], true) ? ' checked' : ''; ?>><span><?php echo stridebr_e($label); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="onboarding-step signup-onboarding-step" data-step="2" hidden>
                <div class="onboarding-step-heading"><h1><?php echo stridebr_e(stridebr_t('onboarding.experience_question')); ?></h1></div>
                <div class="onboarding-grid signup-training-grid">
                    <label><?php echo stridebr_e(stridebr_t('settings.experience')); ?>
                        <select name="experience"><option value=""<?php echo $values['experience'] === '' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.prefer_not')); ?></option><option value="comecando"<?php echo $values['experience'] === 'comecando' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.beginner')); ?></option><option value="pratico"<?php echo $values['experience'] === 'pratico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.active')); ?></option><option value="regular"<?php echo $values['experience'] === 'regular' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.regular')); ?></option></select>
                    </label>
                    <label><?php echo stridebr_e(stridebr_t('settings.weekly_frequency')); ?>
                        <select name="weekly_frequency"><option value="0"><?php echo stridebr_e(stridebr_t('settings.prefer_not_define')); ?></option><?php for ($i=1;$i<=7;$i++): ?><option value="<?php echo $i; ?>"<?php echo $values['weekly_frequency'] === $i ? ' selected' : ''; ?>><?php echo $i; ?> <?php echo stridebr_e(stridebr_t('settings.day')); ?><?php echo $i===1?'':'s'; ?></option><?php endfor; ?></select>
                    </label>
                </div>
                <div class="onboarding-subsection">
                    <strong><?php echo stridebr_e(stridebr_t('onboarding.progress_question')); ?></strong>
                    <div class="onboarding-choice-grid compact signup-tracking-grid">
                        <?php foreach ($trackingChoices as $value => [$label, $description]): ?>
                            <label class="choice-card choice-card-detail"><input type="checkbox" name="tracking[]" value="<?php echo stridebr_e($value); ?>" data-summary-label="<?php echo stridebr_e($label); ?>"<?php echo in_array($value,$values['tracking'],true)?' checked':''; ?>><span><strong><?php echo stridebr_e($label); ?></strong><small><?php echo stridebr_e($description); ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="onboarding-step signup-onboarding-step" data-step="3" hidden>
                <div class="onboarding-step-heading"><h1><?php echo stridebr_e(stridebr_t('onboarding.basic_preferences')); ?></h1></div>
                <div class="onboarding-grid signup-defaults-grid">
                    <label><?php echo stridebr_e(stridebr_t('settings.units')); ?><select name="units"><option value="metric"<?php echo $values['units']==='metric'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('onboarding.metric_units')); ?></option><option value="imperial"<?php echo $values['units']==='imperial'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('settings.imperial_units')); ?></option></select></label>
                    <label><?php echo stridebr_e(stridebr_t('settings.week_start')); ?><select name="week_start"><option value="sunday"<?php echo $values['week_start']==='sunday'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('onboarding.week_sunday')); ?></option><option value="monday"<?php echo $values['week_start']==='monday'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('onboarding.week_monday')); ?></option></select></label>
                    <label><?php echo stridebr_e(stridebr_t('common.profile')); ?><select name="visibilidadeperfil"><option value="privado"<?php echo $values['profile_visibility']==='privado'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('common.private')); ?></option><option value="amigos"<?php echo $values['profile_visibility']==='amigos'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('common.friends')); ?></option><option value="publico"<?php echo $values['profile_visibility']==='publico'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('common.public')); ?></option></select></label>
                    <label><?php echo stridebr_e(stridebr_t('onboarding.new_activities')); ?><select name="activity_visibility"><option value="privado"<?php echo $values['activity_visibility']==='privado'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('activity.only_me')); ?></option><option value="amigos"<?php echo $values['activity_visibility']==='amigos'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('common.friends')); ?></option><option value="publico"<?php echo $values['activity_visibility']==='publico'?' selected':''; ?>><?php echo stridebr_e(stridebr_t('onboarding.public_plural')); ?></option></select></label>
                </div>
            </section>

            <section class="onboarding-step signup-onboarding-step signup-account-step" data-step="4" hidden>
                <div class="onboarding-step-heading"><h1><?php echo stridebr_e(stridebr_t('auth.signup_title')); ?></h1></div>
                <div class="signup-summary-block" data-summary-block hidden>
                    <div class="signup-summary-title"><?php echo stridebr_e(stridebr_t('onboarding.your_choices')); ?></div>
                    <div class="onboarding-result" data-onboarding-summary aria-live="polite"></div>
                </div>
                <div class="signup-account-grid">
                    <label class="gps-field"><?php echo stridebr_e(stridebr_t('common.name')); ?><input type="text" name="NomeUsuario" value="<?php echo stridebr_e($values['nome']); ?>" autocomplete="name" maxlength="80" required></label>
                    <label class="gps-field"><?php echo stridebr_e(stridebr_t('auth.email')); ?><input type="email" name="EmailUsuario" value="<?php echo stridebr_e($values['email']); ?>" autocomplete="email" maxlength="255" required></label>
                    <?php if ($inviteOnly): ?><label class="gps-field is-wide"><?php echo stridebr_e(stridebr_t('onboarding.invite_code')); ?><input type="text" name="CodigoConvite" value="<?php echo stridebr_e($values['invite']); ?>" autocomplete="off" maxlength="80" required></label><?php endif; ?>
                    <label class="gps-field"><?php echo stridebr_e(stridebr_t('auth.password')); ?><span class="password-field"><input type="password" id="signup-password" name="SenhaUsuario" autocomplete="new-password" minlength="8" maxlength="128" required><button type="button" class="showHidePw" aria-controls="signup-password" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button></span></label>
                    <label class="gps-field"><?php echo stridebr_e(stridebr_t('auth.confirm_password')); ?><span class="password-field"><input type="password" id="signup-password-confirm" name="ConfirmarSenhaUsuario" autocomplete="new-password" minlength="8" maxlength="128" required><button type="button" class="showHidePw" aria-controls="signup-password-confirm" aria-pressed="false" aria-label="<?php echo stridebr_e(stridebr_t('auth.password_visibility')); ?>" data-show-label="<?php echo stridebr_e(stridebr_t('auth.show_password')); ?>" data-hide-label="<?php echo stridebr_e(stridebr_t('auth.hide_password')); ?>"><?php echo stridebr_e(stridebr_t('auth.show_password')); ?></button></span></label>
                </div>
                <label class="signup-terms"><input type="checkbox" name="TermosUsuario" required><span><?php echo stridebr_e(stridebr_t('onboarding.accept_prefix')); ?> <a href="/pages/legal/terms.php" target="_blank" rel="noopener"><?php echo stridebr_e(stridebr_t('onboarding.terms')); ?></a> <?php echo stridebr_e(stridebr_t('onboarding.and_the')); ?> <a href="/pages/legal/privacy.php" target="_blank" rel="noopener"><?php echo stridebr_e(stridebr_t('onboarding.privacy')); ?></a>.</span></label>
            </section>

            <div class="onboarding-actions signup-onboarding-actions">
                <button type="button" class="secondary-action" data-prev-step><?php echo stridebr_e(stridebr_t('common.back')); ?></button>
                <div class="signup-action-end">
                    <button type="button" class="primary-action" data-next-step><?php echo stridebr_e(stridebr_t('common.continue')); ?></button>
                    <button type="submit" class="primary-action" data-finish-step hidden><?php echo stridebr_e(stridebr_t('auth.create_account')); ?></button>
                </div>
            </div>
        </form>
        <?php endif; ?>
        <div class="auth-modern-footer signup-login-link"><span class="text"><?php echo stridebr_e(stridebr_t('auth.already_account')); ?> <a href="/login.php"><?php echo stridebr_e(stridebr_t('auth.sign_in_link')); ?></a></span></div>
    </main>
</div>
<?php echo stridebr_i18n_runtime_script(true); ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/onboarding.js')); ?>" defer></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/loginform.js')); ?>" defer></script>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/ui-preferences.js')); ?>" defer></script>
</body>
</html>
