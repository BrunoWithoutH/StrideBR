<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();
stridebr_require_role('moderator');

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();

    try {
        $id = trim((string) ($_POST['idfeedback'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'novo');
        $priority = (string) ($_POST['prioridade'] ?? 'normal');
        $notes = trim((string) ($_POST['notas_admin'] ?? ''));

        if (!in_array($status, ['novo', 'lendo', 'planejado', 'resolvido', 'arquivado'], true)
            || !in_array($priority, ['baixa', 'normal', 'alta'], true)) {
            throw new InvalidArgumentException('Valores inválidos.');
        }

        $stmt = $pdo->prepare(
            'UPDATE feedbacks
                SET status = :status,
                    prioridade = :prioridade,
                    notas_admin = :notas,
                    atualizado_em = NOW()
              WHERE idfeedback = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':prioridade' => $priority,
            ':notas' => $notes !== '' ? $notes : null,
            ':id' => $id,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Feedback não encontrado.');
        }

        stridebr_admin_audit(
            $pdo,
            $idUsuario,
            'feedback.update',
            'feedback',
            $id,
            ['status' => $status, 'prioridade' => $priority]
        );

        stridebr_flash('success', 'Feedback atualizado.');
        header('Location: /admin/feedback.php');
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof RuntimeException || $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Não foi possível atualizar.';
    }
}

$statusFilter = (string) ($_GET['status'] ?? '');
$sql = "SELECT f.*,
               CASE
                   WHEN f.anonimo THEN 'Anônimo'
                   ELSE COALESCE(NULLIF(u.nome_exibicao, ''), u.nomeusuario, 'Usuário removido')
               END AS nome_exibicao,
               CASE WHEN f.anonimo THEN NULL ELSE u.username END AS username,
               CASE WHEN f.anonimo THEN NULL ELSE u.emailusuario END AS emailusuario
          FROM feedbacks f
          LEFT JOIN usuarios u ON u.idusuario = f.idusuario";
$params = [];

if (in_array($statusFilter, ['novo', 'lendo', 'planejado', 'resolvido', 'arquivado'], true)) {
    $sql .= ' WHERE f.status = :status';
    $params[':status'] = $statusFilter;
}

$sql .= " ORDER BY CASE f.prioridade WHEN 'alta' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,
                  f.criado_em DESC
          LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$flashes = stridebr_take_flashes();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Feedback | StrideBR Admin</title>
</head>
<body class="admin-body">
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="admin-shell">
            <?php echo stridebr_admin_nav('feedback'); ?>

            <div class="admin-heading">
                <div>
                    <span class="eyebrow">Alpha</span>
                    <h1>Feedback</h1>
                    <p>Fila de bugs, ideias e observações dos testers.</p>
                </div>
            </div>

            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?php echo stridebr_e($flash['type']); ?>"><?php echo stridebr_e($flash['message']); ?></div>
            <?php endforeach; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><?php echo stridebr_e($error); ?></div>
            <?php endforeach; ?>

            <form class="admin-filter" method="get">
                <select name="status">
                    <option value="">Todos</option>
                    <?php foreach (['novo', 'lendo', 'planejado', 'resolvido', 'arquivado'] as $status): ?>
                        <option value="<?php echo $status; ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="secondary-action">Filtrar</button>
            </form>

            <div class="feedback-admin-list">
                <?php if ($rows === []): ?>
                    <section class="content-card"><p>Nenhum feedback nessa fila.</p></section>
                <?php endif; ?>

                <?php foreach ($rows as $feedback): ?>
                    <article class="admin-card feedback-admin-card">
                        <div class="feedback-admin-top">
                            <div>
                                <div class="feedback-admin-badges">
                                    <span class="status-pill"><?php echo stridebr_e($feedback['tipo']); ?></span>
                                    <?php if (stridebr_db_bool($feedback['anonimo'] ?? false)): ?>
                                        <span class="status-pill">anônimo</span>
                                    <?php endif; ?>
                                </div>
                                <h2><?php echo stridebr_e($feedback['titulo']); ?></h2>
                                <small>
                                    <?php echo stridebr_e($feedback['nome_exibicao']); ?>
                                    <?php echo $feedback['username'] ? ' · @' . stridebr_e($feedback['username']) : ''; ?>
                                    · <?php echo stridebr_e($feedback['criado_em']); ?>
                                </small>
                            </div>
                            <span class="priority-pill priority-<?php echo stridebr_e($feedback['prioridade']); ?>"><?php echo stridebr_e($feedback['prioridade']); ?></span>
                        </div>

                        <p class="feedback-message"><?php echo nl2br(stridebr_e($feedback['mensagem'])); ?></p>

                        <?php if ($feedback['pagina']): ?>
                            <p><strong>Contexto:</strong> <?php echo stridebr_e($feedback['pagina']); ?></p>
                        <?php endif; ?>

                        <form method="post" class="feedback-admin-form">
                            <?php echo stridebr_csrf_field(); ?>
                            <input type="hidden" name="idfeedback" value="<?php echo stridebr_e($feedback['idfeedback']); ?>">

                            <label>Status
                                <select name="status">
                                    <?php foreach (['novo', 'lendo', 'planejado', 'resolvido', 'arquivado'] as $status): ?>
                                        <option value="<?php echo $status; ?>"<?php echo $feedback['status'] === $status ? ' selected' : ''; ?>><?php echo $status; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>Prioridade
                                <select name="prioridade">
                                    <?php foreach (['baixa', 'normal', 'alta'] as $priority): ?>
                                        <option value="<?php echo $priority; ?>"<?php echo $feedback['prioridade'] === $priority ? ' selected' : ''; ?>><?php echo $priority; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="feedback-notes">Notas internas
                                <textarea name="notas_admin" rows="2" maxlength="5000"><?php echo stridebr_e($feedback['notas_admin'] ?? ''); ?></textarea>
                            </label>

                            <button class="primary-action">Salvar</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
