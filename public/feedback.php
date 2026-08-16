<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__) . '/src/config/pg_config.php';

if (!stridebr_feature_enabled($pdo, 'feedback.enabled', false)) {
    http_response_code(404);
    exit('Feedback indisponível.');
}

$anonymousEnabled = stridebr_feature_enabled($pdo, 'feedback.anonymous.enabled', true);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();

    if (!stridebr_rate_limit('feedback', 8, 3600)) {
        $errors[] = 'Você enviou muitos feedbacks em pouco tempo. Tente novamente mais tarde.';
    }

    $tipo = (string) ($_POST['tipo'] ?? 'outro');
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $mensagem = trim((string) ($_POST['mensagem'] ?? ''));
    $pagina = trim((string) ($_POST['pagina'] ?? ''));
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

        stridebr_flash(
            'success',
            $anonimo
                ? 'Feedback enviado anonimamente. Ele não ficará vinculado à sua conta nem aparecerá em Meus envios.'
                : 'Feedback enviado. Valeu por ajudar a quebrar a alpha.'
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
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Feedback | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell feedback-shell">
            <div class="page-heading">
                <span class="eyebrow">Alpha fechada</span>
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
                    <form method="post" class="feedback-form">
                        <?php echo stridebr_csrf_field(); ?>

                        <label>Tipo
                            <select name="tipo">
                                <option value="bug">Bug</option>
                                <option value="ux">Interface / UX</option>
                                <option value="ideia">Ideia</option>
                                <option value="elogio">Elogio</option>
                                <option value="outro">Outro</option>
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

                        <button class="primary-action" type="submit">Enviar feedback</button>
                    </form>
                </section>

                <section class="content-card">
                    <h2>Meus envios</h2>
                    <div class="feedback-list">
                        <?php if ($mine === []): ?>
                            <p>Nenhum feedback identificado enviado ainda.</p>
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
</body>
</html>
