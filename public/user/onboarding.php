<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

$errors = [];
$allowedGoals = ['organizar', 'condicionamento', 'prova', 'evolucao', 'lazer'];
$goalLabels = [
    'organizar' => 'Organizar meus treinos',
    'condicionamento' => 'Melhorar condicionamento',
    'prova' => 'Treinar para uma prova',
    'evolucao' => 'Acompanhar minha evolução',
    'lazer' => 'Praticar por lazer',
];

$userStmt = $pdo->prepare('SELECT nomeusuario, nome_exibicao, username, visibilidadeperfil, preferenciasusuario FROM usuarios WHERE idusuario = :id LIMIT 1');
$userStmt->execute([':id' => $idUsuario]);
$usuario = $userStmt->fetch();
if (!$usuario) {
    http_response_code(404);
    exit('Usuário não encontrado.');
}

$modalidadesStmt = $pdo->query("SELECT idmodalidade, nome FROM modalidades WHERE ativo = TRUE AND idusuario IS NULL ORDER BY nome");
$modalidades = $modalidadesStmt->fetchAll();
$modalidadeIds = array_column($modalidades, 'idmodalidade');

$activeStmt = $pdo->prepare('SELECT idmodalidade FROM modalidades_usuario WHERE idusuario = :usuario AND ativo = TRUE');
$activeStmt->execute([':usuario' => $idUsuario]);
$selectedSports = array_column($activeStmt->fetchAll(), 'idmodalidade');
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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = (string) ($_POST['action'] ?? 'finish');

    if ($action === 'skip') {
        $pdo->prepare('UPDATE usuarios SET onboarding_concluido = TRUE WHERE idusuario = :id')->execute([':id' => $idUsuario]);
        $_SESSION['OnboardingConcluido'] = true;
        stridebr_flash('info', 'Você pode completar o perfil depois em Configurações.');
        header('Location: /home.php');
        exit;
    }

    $values['display_name'] = trim((string) ($_POST['nome_exibicao'] ?? ''));
    $values['username'] = stridebr_lower(trim((string) ($_POST['username'] ?? '')));
    $values['visibility'] = (string) ($_POST['visibilidadeperfil'] ?? 'privado');
    $values['units'] = (string) ($_POST['units'] ?? 'metric');
    $values['week_start'] = (string) ($_POST['week_start'] ?? 'sunday');
    $values['goals'] = array_values(array_intersect($allowedGoals, is_array($_POST['goals'] ?? null) ? $_POST['goals'] : []));
    $selectedSports = array_values(array_intersect($modalidadeIds, is_array($_POST['sports'] ?? null) ? $_POST['sports'] : []));

    if ($values['display_name'] === '' || stridebr_length($values['display_name']) > 120) {
        $errors[] = 'Informe um nome de exibição válido.';
    }
    if ($values['username'] !== '' && !stridebr_username_is_valid($values['username'])) {
        $errors[] = 'O username precisa ter de 3 a 40 caracteres e usar apenas letras minúsculas, números, ponto, hífen ou underline.';
    }
    if (!in_array($values['visibility'], ['privado', 'amigos', 'publico'], true)) {
        $errors[] = 'Privacidade inválida.';
    }
    if (!in_array($values['units'], ['metric', 'imperial'], true)) {
        $errors[] = 'Sistema de unidades inválido.';
    }
    if (!in_array($values['week_start'], ['sunday', 'monday'], true)) {
        $errors[] = 'Início da semana inválido.';
    }

    if ($errors === [] && $values['username'] !== '') {
        $check = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(username) = lower(:username) AND idusuario <> :id LIMIT 1');
        $check->execute([':username' => $values['username'], ':id' => $idUsuario]);
        if ($check->fetchColumn()) {
            $errors[] = 'Esse username já está em uso.';
        }
    }

    if ($errors === []) {
        $pdo->beginTransaction();
        try {
            $preferences = [
                'units' => $values['units'],
                'week_start' => $values['week_start'],
                'goals' => $values['goals'],
            ];
            $stmt = $pdo->prepare('UPDATE usuarios SET nome_exibicao = :display, username = :username, visibilidadeperfil = :visibilidade, preferenciasusuario = CAST(:preferencias AS jsonb), onboarding_concluido = TRUE WHERE idusuario = :id');
            $stmt->execute([
                ':display' => $values['display_name'],
                ':username' => $values['username'] !== '' ? $values['username'] : null,
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

            $_SESSION['NomeExibicao'] = $values['display_name'];
            $_SESSION['Username'] = $values['username'] !== '' ? $values['username'] : null;
            $_SESSION['OnboardingConcluido'] = true;
            stridebr_flash('success', 'Perfil inicial configurado.');
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
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Configure seu perfil | StrideBR</title>
</head>
<body class="onboarding-body">
<div class="onboarding-shell">
    <a class="onboarding-brand" href="/home.php"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-logo.svg')); ?>" alt="StrideBR" width="110" height="43"></a>
    <main class="onboarding-card" data-onboarding>
        <div class="onboarding-topline">
            <div>
                <span class="eyebrow">Primeiros passos</span>
                <h1>Deixe o StrideBR com a sua cara</h1>
                <p>Quatro etapas curtas. Tudo que não for essencial pode ser alterado depois.</p>
            </div>
            <span class="onboarding-progress-label" data-step-label>1 de 4</span>
        </div>
        <div class="onboarding-progress"><span data-progress-bar></span></div>

        <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

        <form method="POST" class="onboarding-form">
            <?php echo stridebr_csrf_field(); ?>
            <input type="hidden" name="action" value="finish">

            <section class="onboarding-step is-active" data-step="0">
                <h2>Você</h2>
                <p>Esse é o nome que outras pessoas verão quando você decidir compartilhar algo.</p>
                <div class="onboarding-grid">
                    <label>Nome de exibição
                        <input type="text" name="nome_exibicao" maxlength="120" value="<?php echo stridebr_e($values['display_name']); ?>" required>
                    </label>
                    <label>@username <span>opcional por enquanto</span>
                        <input type="text" name="username" maxlength="40" value="<?php echo stridebr_e($values['username']); ?>" placeholder="bruno">
                    </label>
                </div>
            </section>

            <section class="onboarding-step" data-step="1" hidden>
                <h2>O que você pratica?</h2>
                <p>Escolha quantas modalidades quiser. Isso ajuda a priorizar modelos e exercícios.</p>
                <div class="onboarding-choice-grid">
                    <?php foreach ($modalidades as $modalidade): ?>
                        <label class="choice-card"><input type="checkbox" name="sports[]" value="<?php echo stridebr_e($modalidade['idmodalidade']); ?>"<?php echo in_array($modalidade['idmodalidade'], $selectedSports, true) ? ' checked' : ''; ?>><span><?php echo stridebr_e($modalidade['nome']); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="onboarding-step" data-step="2" hidden>
                <h2>O que você quer fazer no StrideBR?</h2>
                <p>Isso serve para adaptar atalhos e conteúdo, não para te encaixar num padrão físico.</p>
                <div class="onboarding-choice-grid compact">
                    <?php foreach ($goalLabels as $value => $label): ?>
                        <label class="choice-card"><input type="checkbox" name="goals[]" value="<?php echo stridebr_e($value); ?>"<?php echo in_array($value, $values['goals'], true) ? ' checked' : ''; ?>><span><?php echo stridebr_e($label); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="onboarding-step" data-step="3" hidden>
                <h2>Preferências</h2>
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
                </div>
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
