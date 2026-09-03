<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/product_analytics.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';

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

$userStmt = $pdo->prepare('SELECT nomeusuario, nome_exibicao, username, visibilidadeperfil, preferenciasusuario FROM usuarios WHERE idusuario = :id LIMIT 1');
$userStmt->execute([':id' => $idUsuario]);
$usuario = $userStmt->fetch();
if (!$usuario) {
    stridebr_error_document(404);
}

$modalidadesStmt = $pdo->query("SELECT idmodalidade, nome FROM modalidades WHERE ativo = TRUE AND idusuario IS NULL ORDER BY nome");
$modalidades = $modalidadesStmt->fetchAll();
$modalidadeIds = array_column($modalidades, 'idmodalidade');

$activeStmt = $pdo->prepare('SELECT idmodalidade FROM modalidades_usuario WHERE idusuario = :usuario AND ativo = TRUE');
$activeStmt->execute([':usuario' => $idUsuario]);
$selectedSports = array_column($activeStmt->fetchAll(), 'idmodalidade');
$integrationRegistry = stridebr_integrations_registry();
$integrationConnections = stridebr_integrations_list($pdo, $idUsuario);
$onboardingInitialStep = max(0, min(5, (int) ($_GET['step'] ?? 0)));
if (empty($_SESSION['StrideBROnboardingAnalyticsStarted'])) {
    productAnalyticsRegistrar($pdo, $idUsuario, 'onboarding_started');
    $_SESSION['StrideBROnboardingAnalyticsStarted'] = true;
}

$preferences = is_array($usuario['preferenciasusuario'] ?? null)
    ? $usuario['preferenciasusuario']
    : (json_decode((string) ($usuario['preferenciasusuario'] ?? '{}'), true) ?: []);

$values = [
    'display_name' => trim((string) ($usuario['nome_exibicao'] ?? '')) ?: (string) $usuario['nomeusuario'],
    'username' => (string) ($usuario['username'] ?? ''),
    'visibility' => (string) ($usuario['visibilidadeperfil'] ?? 'privado'),
    'units' => (string) ($preferences['units'] ?? 'metric'),
    'week_start' => (string) ($preferences['week_start'] ?? 'sunday'),
    'goals' => is_array($preferences['goals'] ?? null) ? $preferences['goals'] : [],
    'experience' => (string) ($preferences['experience'] ?? ''),
    'weekly_frequency' => isset($preferences['weekly_frequency']) && is_numeric($preferences['weekly_frequency']) ? (int) $preferences['weekly_frequency'] : 0,
    'tracking' => is_array($preferences['tracking'] ?? null) ? $preferences['tracking'] : [],
    'activity_visibility' => (string) (($preferences['activity_defaults']['visibility'] ?? null) ?: 'privado'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? 'finish');

    if ($action === 'skip') {
        $pdo->prepare('UPDATE usuarios SET onboarding_concluido = TRUE WHERE idusuario = :id')->execute([':id' => $idUsuario]);
        $_SESSION['OnboardingConcluido'] = true;
        productAnalyticsRegistrar($pdo, $idUsuario, 'onboarding_completed', ['skipped' => true]);
        header('Location: /home.php');
        exit;
    }

    $values['visibility'] = (string) ($_POST['visibilidadeperfil'] ?? $values['visibility']);
    $values['units'] = (string) ($_POST['units'] ?? $values['units']);
    $values['week_start'] = (string) ($_POST['week_start'] ?? $values['week_start']);
    $values['goals'] = array_values(array_intersect($allowedGoals, is_array($_POST['goals'] ?? null) ? $_POST['goals'] : []));
    $values['experience'] = trim((string) ($_POST['experience'] ?? ''));
    $values['weekly_frequency'] = max(0, min(7, (int) ($_POST['weekly_frequency'] ?? 0)));
    $values['tracking'] = array_values(array_intersect($allowedTracking, is_array($_POST['tracking'] ?? null) ? $_POST['tracking'] : []));
    $values['activity_visibility'] = (string) ($_POST['activity_visibility'] ?? $values['activity_visibility']);
    $selectedSports = array_values(array_intersect($modalidadeIds, is_array($_POST['sports'] ?? null) ? $_POST['sports'] : []));

    if (!in_array($values['experience'], $allowedExperience, true)) {
        $errors[] = 'Nível de experiência inválido.';
    }
    if (!in_array($values['visibility'], ['privado', 'amigos', 'publico'], true)) {
        $errors[] = 'Privacidade inválida.';
    }
    if (!in_array($values['activity_visibility'], ['privado', 'amigos', 'publico'], true)) {
        $errors[] = 'Privacidade padrão das atividades inválida.';
    }
    if (!in_array($values['units'], ['metric', 'imperial'], true)) {
        $errors[] = 'Sistema de unidades inválido.';
    }
    if (!in_array($values['week_start'], ['sunday', 'monday'], true)) {
        $errors[] = 'Início da semana inválido.';
    }


    if ($errors === []) {
        $pdo->beginTransaction();
        try {
            $preferences['units'] = $values['units'];
            $preferences['week_start'] = $values['week_start'];
            $preferences['goals'] = $values['goals'];
            $preferences['experience'] = $values['experience'];
            $preferences['weekly_frequency'] = $values['weekly_frequency'] > 0 ? $values['weekly_frequency'] : null;
            $preferences['tracking'] = $values['tracking'];
            $activityDefaults = is_array($preferences['activity_defaults'] ?? null) ? $preferences['activity_defaults'] : [];
            $activityDefaults['visibility'] = $values['activity_visibility'];
            $preferences['activity_defaults'] = $activityDefaults;
            $stmt = $pdo->prepare('UPDATE usuarios SET visibilidadeperfil = :visibilidade, preferenciasusuario = CAST(:preferencias AS jsonb), onboarding_concluido = TRUE WHERE idusuario = :id');
            $stmt->execute([
                ':visibilidade' => $values['visibility'],
                ':preferencias' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':id' => $idUsuario,
            ]);

            $pdo->prepare('UPDATE modalidades_usuario SET ativo = FALSE, data_desativacao = NOW() WHERE idusuario = :usuario')->execute([':usuario' => $idUsuario]);
            $upsert = $pdo->prepare(
                'INSERT INTO modalidades_usuario (idusuario, idmodalidade, ativo, data_ativacao, data_desativacao)
                 VALUES (:usuario, :modalidade, TRUE, NOW(), NULL)
                 ON CONFLICT (idusuario, idmodalidade)
                 DO UPDATE SET ativo = TRUE, data_ativacao = NOW(), data_desativacao = NULL'
            );
            foreach ($selectedSports as $idModalidade) {
                $upsert->execute([':usuario' => $idUsuario, ':modalidade' => $idModalidade]);
            }
            $pdo->commit();

            $_SESSION['OnboardingConcluido'] = true;
            productAnalyticsRegistrar($pdo, $idUsuario, 'onboarding_completed', ['skipped' => false, 'sports' => count($selectedSports), 'weekly_frequency' => $values['weekly_frequency']]);
            header('Location: /home.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Configure seu perfil | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="onboarding-body">
<div class="onboarding-shell">
    <a class="onboarding-brand" href="/home.php"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
    <main class="onboarding-card" data-onboarding data-initial-step="<?php echo $onboardingInitialStep; ?>">
        <div class="onboarding-topline">
            <div>
                <span class="eyebrow">Primeiros passos</span>
                <h1>Configure seu StrideBR</h1>
            </div>
            <span class="onboarding-progress-label" data-step-label>1 de 6</span>
        </div>
        <div class="onboarding-progress"><span data-progress-bar></span></div>

        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

        <form method="POST" class="onboarding-form">
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="action" value="finish">

            <section class="onboarding-step is-active" data-step="0">
                <div class="onboarding-step-heading"><span>Opcional</span><h2>O que você pratica?</h2></div>
                <div class="onboarding-choice-grid">
                    <?php foreach ($modalidades as $modalidade): ?>
                        <label class="choice-card"><input type="checkbox" name="sports[]" value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"<?php echo in_array($modalidade['idmodalidade'], $selectedSports, true) ? ' checked' : ''; ?>><span><?php echo stridebr_e($modalidade['nome']); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="onboarding-step" data-step="1" hidden>
                <div class="onboarding-step-heading"><span>Opcional</span><h2>O que você quer do StrideBR?</h2></div>
                <div class="onboarding-choice-grid compact">
                    <?php foreach ($goalLabels as $value => $label): ?>
                        <label class="choice-card"><input type="checkbox" name="goals[]" value="<?php echo stridebr_e($value); ?>"<?php echo in_array($value, $values['goals'], true) ? ' checked' : ''; ?>><span><?php echo stridebr_e($label); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="onboarding-step" data-step="2" hidden>
                <div class="onboarding-step-heading"><span>Opcional</span><h2>Como você treina hoje?</h2></div>
                <div class="onboarding-grid">
                    <label>Experiência
                        <select name="experience">
                            <option value=""<?php echo $values['experience'] === '' ? ' selected' : ''; ?>>Prefiro não informar</option>
                            <option value="comecando"<?php echo $values['experience'] === 'comecando' ? ' selected' : ''; ?>>Estou começando</option>
                            <option value="pratico"<?php echo $values['experience'] === 'pratico' ? ' selected' : ''; ?>>Já pratico</option>
                            <option value="regular"<?php echo $values['experience'] === 'regular' ? ' selected' : ''; ?>>Treino regularmente</option>
                        </select>
                    </label>
                    <label>Frequência aproximada por semana
                        <select name="weekly_frequency">
                            <option value="0">Prefiro não definir</option>
                            <?php for ($i = 1; $i <= 7; $i++): ?><option value="<?php echo $i; ?>"<?php echo $values['weekly_frequency'] === $i ? ' selected' : ''; ?>><?php echo $i; ?> dia<?php echo $i === 1 ? '' : 's'; ?></option><?php endfor; ?>
                        </select>
                    </label>
                </div>
                <div class="onboarding-subsection">
                    <strong>O que você gosta de acompanhar?</strong>
                    <div class="onboarding-choice-grid compact">
                        <?php foreach ($trackingChoices as $value => [$label, $description]): ?>
                            <label class="choice-card choice-card-detail"><input type="checkbox" name="tracking[]" value="<?php echo stridebr_e($value); ?>" data-summary-label="<?php echo stridebr_e($label); ?>"<?php echo in_array($value, $values['tracking'], true) ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e($label); ?></strong><small><?php echo stridebr_e($description); ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="onboarding-step" data-step="3" hidden>
                <div class="onboarding-step-heading"><span>Padrões</span><h2>Preferências básicas</h2></div>
                <div class="onboarding-grid">
                    <label>Unidades
                        <select name="units"><option value="metric"<?php echo $values['units'] === 'metric' ? ' selected' : ''; ?>>Métricas (km, kg, cm)</option><option value="imperial"<?php echo $values['units'] === 'imperial' ? ' selected' : ''; ?>>Imperiais</option></select>
                    </label>
                    <label>A semana começa
                        <select name="week_start"><option value="sunday"<?php echo $values['week_start'] === 'sunday' ? ' selected' : ''; ?>>Domingo</option><option value="monday"<?php echo $values['week_start'] === 'monday' ? ' selected' : ''; ?>>Segunda-feira</option></select>
                    </label>
                    <label>Privacidade inicial do perfil
                        <select name="visibilidadeperfil"><option value="privado"<?php echo $values['visibility'] === 'privado' ? ' selected' : ''; ?>>Privado</option><option value="amigos"<?php echo $values['visibility'] === 'amigos' ? ' selected' : ''; ?>>Amigos</option><option value="publico"<?php echo $values['visibility'] === 'publico' ? ' selected' : ''; ?>>Público</option></select>
                    </label>
                    <label>Privacidade de novas atividades
                        <select name="activity_visibility"><option value="privado"<?php echo $values['activity_visibility'] === 'privado' ? ' selected' : ''; ?>>Só eu</option><option value="amigos"<?php echo $values['activity_visibility'] === 'amigos' ? ' selected' : ''; ?>>Amigos</option><option value="publico"<?php echo $values['activity_visibility'] === 'publico' ? ' selected' : ''; ?>>Público</option></select>
                    </label>
                </div>
            </section>

            <section class="onboarding-step" data-step="4" hidden>
                <div class="onboarding-step-heading"><span>Opcional</span><h2>Conecte onde você já treina</h2></div>
                <p class="onboarding-connections-intro">O StrideBR pode receber atividades de outros serviços. Você pode fazer isso agora ou conectar depois em Editar perfil.</p>
                <div class="onboarding-connections-grid">
                    <?php foreach (['garmin', 'strava', 'polar', 'health_connect', 'apple_health'] as $providerId): ?>
                        <?php
                        $provider = $integrationRegistry[$providerId];
                        $connection = $integrationConnections[$providerId] ?? null;
                        $connected = is_array($connection) && (string) ($connection['status'] ?? '') === 'conectado';
                        $configured = stridebr_integrations_configured($provider);
                        $kind = (string) ($provider['kind'] ?? 'cloud');
                        $returnTo = '/user/onboarding.php?step=4';
                        ?>
                        <article class="onboarding-connection-card<?php echo $connected ? ' is-connected' : ''; ?>">
                            <div><strong><?php echo stridebr_e($provider['label']); ?></strong><small><?php echo stridebr_e($provider['description']); ?></small></div>
                            <?php if ($connected): ?>
                                <span class="integration-status is-connected">Conectado</span>
                            <?php elseif ($kind === 'cloud' && $configured): ?>
                                <a class="secondary-action" href="/auth/integration.php?provider=<?php echo rawurlencode($providerId); ?>&amp;return=<?php echo rawurlencode($returnTo); ?>">Conectar</a>
                            <?php elseif ($providerId === 'health_connect'): ?>
                                <span class="onboarding-connection-note">App Android</span>
                            <?php elseif ($providerId === 'apple_health'): ?>
                                <span class="onboarding-connection-note">App iOS</span>
                            <?php else: ?>
                                <span class="onboarding-connection-note">Suporte preparado</span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="onboarding-connection-footnote">Arquivos FIT, GPX e TCX também podem ser importados sem conectar uma conta.</p>
            </section>

            <section class="onboarding-step" data-step="5" hidden>
                <div class="onboarding-step-heading"><span>Pronto</span><h2>Resumo</h2></div>
                <div class="onboarding-result" data-onboarding-summary aria-live="polite"></div>
            </section>

            <div class="onboarding-actions">
                <button type="button" class="secondary-action" data-prev-step hidden>Voltar</button>
                <span></span>
                <button type="button" class="primary-action" data-next-step>Continuar</button>
                <button type="submit" class="primary-action" data-finish-step hidden>Concluir</button>
            </div>
        </form>
        <form method="POST" class="onboarding-skip">
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="action" value="skip">
            <button type="submit">Pular por enquanto</button>
        </form>
    </main>
</div>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/onboarding.js')); ?>" defer></script>
</body>
</html>
