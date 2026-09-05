<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';

if (is_array($_SESSION['OwnerImpersonation'] ?? null)) {
    stridebr_flash('danger', 'Saia do modo de teste antes de gerenciar a exclusão de uma conta.');
    header('Location: /user/account.php#dados-conta');
    exit;
}

$stmt = $pdo->prepare('SELECT idusuario,COALESCE(NULLIF(nome_exibicao,\'\'),nomeusuario) AS nome_exibicao,username,emailusuario,senhausuario,fotousuario,papelusuario FROM usuarios WHERE idusuario=:id LIMIT 1');
$stmt->execute([':id' => $idUsuario]);
$user = $stmt->fetch();
if (!$user) {
    stridebr_destroy_session();
    header('Location: /');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $password = (string) ($_POST['senha'] ?? '');
    $confirmation = strtoupper(trim((string) ($_POST['confirmacao'] ?? '')));
    if ((string) $user['papelusuario'] === 'owner') {
        $errors[] = 'A conta proprietária não pode ser excluída por este fluxo. Transfira a responsabilidade do projeto antes de remover essa conta.';
    }
    if (!password_verify($password, (string) $user['senhausuario'])) $errors[] = 'Senha incorreta.';
    if ($confirmation !== 'EXCLUIR') $errors[] = 'Digite EXCLUIR para confirmar.';

    if ($errors === []) {
        $photo = trim((string) ($user['fotousuario'] ?? ''));
        $pdo->beginTransaction();
        try {
            if ($pdo->query("SELECT to_regclass('stridebr.feedbacks') IS NOT NULL")->fetchColumn()) {
                $pdo->prepare('UPDATE feedbacks SET idusuario=NULL WHERE idusuario=:id')->execute([':id' => $idUsuario]);
            }
            $delete = $pdo->prepare('DELETE FROM usuarios WHERE idusuario=:id');
            $delete->execute([':id' => $idUsuario]);
            if ($delete->rowCount() !== 1) throw new RuntimeException('A conta não pôde ser removida.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('StrideBR self account delete failed: ' . $e->getMessage());
            $errors[] = 'Não foi possível excluir a conta agora. Tente novamente ou fale com o suporte.';
        }

        if ($errors === []) {
            if (preg_match('#^/uploads/avatars/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#i', $photo) === 1) {
                $disk = dirname(__DIR__) . $photo;
                if (is_file($disk)) @unlink($disk);
            }
            stridebr_destroy_session();
            header('Location: /index.php?conta=excluida');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">

    <title>Excluir conta | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="page-shell account-delete-shell">
    <div class="page-heading"><span class="eyebrow">Conta e dados</span><h1>Excluir conta</h1><p>Esta ação remove a conta e os dados associados que dependem dela no banco. Ela não pode ser desfeita.</p></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
    <section class="content-card account-danger-card">
        <h2>Antes de excluir</h2>
        <ul><li>Se quiser guardar uma cópia, volte às configurações e exporte seus dados primeiro.</li><li>Cronogramas, atividades, rotas, metas, equipamentos e demais registros vinculados à conta serão removidos conforme as relações do sistema.</li><li>Feedbacks já enviados podem permanecer sem vínculo com a conta para preservar o histórico de correções.</li><li>Conteúdos administrativos ou logs que precisem ser preservados por segurança podem permanecer sem o identificador da conta.</li></ul>
        <?php if ((string) $user['papelusuario'] === 'owner'): ?><div class="alert alert-info">A conta proprietária do projeto não pode ser excluída por esta tela.</div><?php else: ?>
        <form method="post" class="account-delete-form">
            <?php echo stridebr_csrf_field(); ?>
            <label>Confirme sua senha<input type="password" name="senha" autocomplete="current-password" minlength="8" maxlength="128" required></label>
            <label>Digite <strong>EXCLUIR</strong><input type="text" name="confirmacao" autocomplete="off" required></label>
            <div><a class="secondary-button" href="/user/account.php#dados-conta">Cancelar</a><button class="danger-button" type="submit">Excluir minha conta definitivamente</button></div>
        </form>
        <?php endif; ?>
    </section>
</div></main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body></html>
