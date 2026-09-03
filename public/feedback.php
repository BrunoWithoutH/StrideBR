<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__) . '/src/config/pg_config.php';

if (!stridebr_feature_enabled($pdo, 'feedback.enabled', false)) {
    stridebr_error_document(404);
}

$anonymousEnabled = stridebr_feature_enabled($pdo, 'feedback.anonymous.enabled', true);
$errors = [];
if (!isset($_SESSION['feedback_form_token']) || !is_string($_SESSION['feedback_form_token'])) {
    $_SESSION['feedback_form_token'] = bin2hex(random_bytes(24));
}
$feedbackFormToken = $_SESSION['feedback_form_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $submittedFormToken = (string) ($_POST['feedback_form_token'] ?? '');
    $validFormToken = $submittedFormToken !== '' && hash_equals($feedbackFormToken, $submittedFormToken);
    if (!$validFormToken) {
        $errors[] = 'Esse feedback já foi enviado ou o formulário expirou. Atualize a página e tente novamente.';
    } elseif (stridebr_auth_limit_is_blocked($pdo, 'feedback-user', $idUsuario)) {
        $errors[] = 'Você enviou muitos feedbacks em pouco tempo. Tente novamente mais tarde.';
    } else {
        stridebr_auth_limit_record_attempt($pdo, 'feedback-user', $idUsuario, 8, 3600, 3600);
    }

    $tipo = (string) ($_POST['tipo'] ?? 'outro');
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
    $pagina = trim((string) ($_POST['pagina'] ?? ''));
    $contextoTecnico = trim((string) ($_POST['contexto_tecnico'] ?? ''));
    $anonimo = $anonymousEnabled && (string) ($_POST['anonimo'] ?? '') === '1';

    if (!in_array($tipo, ['bug', 'ideia', 'ux', 'elogio', 'outro'], true)) {
        $tipo = 'outro';
    }

    if ($titulo === '' || stridebr_length($titulo) > 140) {
        $errors[] = 'Informe um título de até 140 caracteres.';
    }

    if (stridebr_length($mensagem) < 5 || stridebr_length($mensagem) > 5000) {
        $errors[] = 'Descreva o feedback em até 5.000 caracteres.';
    }

    if ($pagina !== '' && stridebr_length($pagina) > 1000) {
        $pagina = substr($pagina, 0, 1000);
    }
    if ($contextoTecnico !== '') {
        $contextoTecnico = preg_replace('/[^\P{C}\n\t]+/u', '', $contextoTecnico) ?? '';
        $separator = "\n\n--- Contexto técnico automático ---\n";
        $availableContext = max(0, 5000 - stridebr_length($mensagem) - stridebr_length($separator));
        $contextLimit = min(1200, $availableContext);
        if ($contextLimit > 0 && stridebr_length($contextoTecnico) > $contextLimit) {
            $contextoTecnico = function_exists('mb_substr') ? mb_substr($contextoTecnico, 0, $contextLimit, 'UTF-8') : substr($contextoTecnico, 0, $contextLimit);
        }
        if ($contextLimit > 0 && $contextoTecnico !== '') {
            $mensagem .= $separator . $contextoTecnico;
        }
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO feedbacks (idfeedback, idusuario, anonimo, tipo, titulo, mensagem, pagina, user_agent, ip)
             VALUES (:id, :usuario, :anonimo, :tipo, :titulo, :mensagem, :pagina, :ua, CAST(:ip AS inet))'
        );

        $ip = !$anonimo && stridebr_feature_enabled($pdo, 'access_logs.enabled', false)
            ? stridebr_client_ip()
            : null;

        $userAgent = $anonimo
            ? null
            : (substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500) ?: null);

        $stmt->bindValue(':id', stridebr_generate_id());
        $stmt->bindValue(':usuario', $anonimo ? null : $idUsuario, $anonimo ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':anonimo', $anonimo, PDO::PARAM_BOOL);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':titulo', $titulo);
        $stmt->bindValue(':mensagem', $mensagem);
        $stmt->bindValue(':pagina', $pagina !== '' ? $pagina : null, $pagina !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ua', $userAgent, $userAgent === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ip', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->execute();
        $_SESSION['feedback_form_token'] = bin2hex(random_bytes(24));
        stridebr_auth_limit_cleanup($pdo);

        stridebr_flash(
            'success',
            $anonimo
                ? 'Feedback enviado anonimamente. Ele não ficará vinculado à sua conta nem aparecerá em Meus envios.'
                : 'Feedback enviado. Valeu por ajudar a melhorar o StrideBR.'
        );

        header('Location: /feedback.php');
        exit;
    }
}

$mine = $pdo->prepare(
    'SELECT tipo, titulo, status, criado_em
       FROM feedbacks
      WHERE idusuario = :id
        AND anonimo = FALSE
      ORDER BY criado_em DESC
      LIMIT 10'
);
$mine->execute([':id' => $idUsuario]);
$mine = $mine->fetchAll();

$flashes = stridebr_take_flashes();
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Feedback | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell feedback-shell">
            <div class="page-heading">
                <span class="eyebrow">Feedback</span>
                <h1>Feedback</h1>
                <p>Achou bug, interface estranha ou teve uma ideia? Manda aqui com o máximo de contexto que conseguir.</p>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type']); ?>"><?php echo stridebr_e($flash['message']); ?></div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <div class="feedback-grid">
                <section class="content-card">
                    <form method="post" class="feedback-form" data-feedback-form>
                        <?php echo stridebr_csrf_field(); ?>
                        <input type="hidden" name="feedback_form_token" value="<?php echo stridebr_e($feedbackFormToken); ?>">
                        <input type="hidden" name="contexto_tecnico" value="" data-feedback-context>

                        <label>Tipo
                            <?php $feedbackType = (string) ($_POST['tipo'] ?? $_GET['type'] ?? 'bug'); ?>
                            <select name="tipo">
                                <option value="bug"<?php echo $feedbackType === 'bug' ? ' selected' : ''; ?>>Bug</option>
                                <option value="ux"<?php echo $feedbackType === 'ux' ? ' selected' : ''; ?>>Interface / UX</option>
                                <option value="ideia"<?php echo $feedbackType === 'ideia' ? ' selected' : ''; ?>>Ideia</option>
                                <option value="elogio"<?php echo $feedbackType === 'elogio' ? ' selected' : ''; ?>>Elogio</option>
                                <option value="outro"<?php echo $feedbackType === 'outro' ? ' selected' : ''; ?>>Outro</option>
                            </select>
                        </label>

                        <label>Título
                            <input name="titulo" maxlength="140" required placeholder="Ex.: calendário corta no iPhone">
                        </label>

                        <label>O que aconteceu
                            <textarea name="mensagem" rows="8" maxlength="5000" required placeholder="O que você estava fazendo, o que esperava e o que aconteceu..."></textarea>
                        </label>

                        <label>Página / contexto
                            <input name="pagina" maxlength="1000" value="<?php echo stridebr_e((string) ($_GET['from'] ?? ($_SERVER['HTTP_REFERER'] ?? ''))); ?>" placeholder="Opcional">
                        </label>

                        <?php if ($anonymousEnabled): ?>
                            <label class="feedback-anonymous-option">
                                <input type="checkbox" name="anonimo" value="1">
                                <span>
                                    <strong>Enviar anonimamente</strong>
                                    <small>Sua conta, IP e navegador não serão vinculados a este feedback. Ele também não aparecerá em Meus envios.</small>
                                </span>
                            </label>
                        <?php endif; ?>

                        <p class="feedback-context-note">Página, tamanho da tela, fuso e versão do StrideBR serão anexados automaticamente para facilitar o diagnóstico.</p>
                        <button class="primary-action feedback-submit" type="submit" data-feedback-submit>Enviar feedback</button>
                    </form>
                </section>

                <section class="content-card">
                    <h2>Meus envios</h2>
                    <div class="feedback-list">
                        <?php if ($mine === []): ?>
                            <div class="feedback-empty-copy"><strong>Nenhum envio identificado ainda.</strong><span>Se algo te incomodar durante o uso, descreva ao lado; o contexto técnico básico vai junto automaticamente.</span></div>
                        <?php endif; ?>

                        <?php foreach ($mine as $item): ?>
                            <article>
                                <span><?php echo stridebr_e($item['tipo']); ?></span>
                                <strong><?php echo stridebr_e($item['titulo']); ?></strong>
                                <small><?php echo stridebr_e($item['status']); ?> · <?php echo stridebr_e($item['criado_em']); ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/feedback.js')); ?>"></script>
</body>
</html>
