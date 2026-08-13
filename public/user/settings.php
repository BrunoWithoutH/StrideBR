<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';

$idUsuario = stridebr_require_login();

require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
$errors = [];
$generos = ['Masculino', 'Feminino', 'Não-binário', 'Agênero', 'Bigênero', 'Gênero fluido', 'Prefiro não informar', 'Outro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $nome = trim((string) ($_POST['nomeusuario'] ?? ''));
    $fone = trim((string) ($_POST['foneusuario'] ?? ''));
    $nascimento = trim((string) ($_POST['datanascimentousuario'] ?? ''));
    $genero = trim((string) ($_POST['generousuario'] ?? ''));
    $pronomes = trim((string) ($_POST['pronomesusuario'] ?? ''));
    $bio = trim((string) ($_POST['biousuario'] ?? ''));
    $pesoRaw = str_replace(',', '.', trim((string) ($_POST['pesousuario'] ?? '')));
    $alturaRaw = trim((string) ($_POST['alturausuario'] ?? ''));
    $objetivo = trim((string) ($_POST['objetivousuario'] ?? ''));
    $visibilidade = (string) ($_POST['visibilidadeperfil'] ?? 'privado');

    if ($nome === '' || stridebr_length($nome) > 255) {
        $errors[] = 'Informe um nome válido.';
    }
    if ($fone !== '' && stridebr_length($fone) > 20) {
        $errors[] = 'O telefone é muito longo.';
    }
    if ($nascimento !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $nascimento);
        if (!$date || $date->format('Y-m-d') !== $nascimento || $date > new DateTimeImmutable('today')) {
            $errors[] = 'Data de nascimento inválida.';
        }
    }
    if ($genero !== '' && !in_array($genero, $generos, true)) {
        $errors[] = 'Gênero inválido.';
    }
    if (stridebr_length($pronomes) > 30) {
        $errors[] = 'Pronomes muito longos.';
    }
    if ($pesoRaw !== '' && (!is_numeric($pesoRaw) || (float) $pesoRaw <= 0 || (float) $pesoRaw > 9999)) {
        $errors[] = 'Peso inválido.';
    }
    if ($alturaRaw !== '' && (filter_var($alturaRaw, FILTER_VALIDATE_INT) === false || (int) $alturaRaw <= 0 || (int) $alturaRaw > 300)) {
        $errors[] = 'Altura inválida.';
    }
    if (!in_array($visibilidade, ['privado', 'amigos', 'publico'], true)) {
        $errors[] = 'Privacidade inválida.';
    }

    if ($errors === []) {
        $stmt = $pdo->prepare('UPDATE usuarios SET nomeusuario = :nome, foneusuario = :fone, datanascimentousuario = :nascimento, generousuario = :genero, pronomesusuario = :pronomes, biousuario = :bio, pesousuario = :peso, alturausuario = :altura, objetivousuario = :objetivo, visibilidadeperfil = :visibilidade WHERE idusuario = :id');
        $stmt->execute([
            ':nome' => $nome,
            ':fone' => $fone !== '' ? $fone : null,
            ':nascimento' => $nascimento !== '' ? $nascimento : null,
            ':genero' => $genero !== '' ? $genero : null,
            ':pronomes' => $pronomes !== '' ? $pronomes : null,
            ':bio' => $bio !== '' ? $bio : null,
            ':peso' => $pesoRaw !== '' ? $pesoRaw : null,
            ':altura' => $alturaRaw !== '' ? (int) $alturaRaw : null,
            ':objetivo' => $objetivo !== '' ? $objetivo : null,
            ':visibilidade' => $visibilidade,
            ':id' => $idUsuario,
        ]);
        $_SESSION['NomeUsuario'] = $nome;
        stridebr_flash('success', 'Configurações salvas.');
        header('Location: /user/settings.php');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT nomeusuario, emailusuario, foneusuario, datanascimentousuario, generousuario, pronomesusuario, biousuario, pesousuario, alturausuario, objetivousuario, visibilidadeperfil FROM usuarios WHERE idusuario = :id LIMIT 1');
$stmt->execute([':id' => $idUsuario]);
$usuario = $stmt->fetch();
if (!$usuario) {
    http_response_code(404);
    exit('Usuário não encontrado.');
}
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/assets/img/favicon/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/css/style.css">
    <title>Configurações | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell settings-shell">
            <div class="page-heading"><h1>Configurações</h1><p>Dados do perfil e preferências básicas. Recursos sociais continuam preparados apenas na estrutura.</p></div>
            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <form method="POST" class="content-card settings-form">
                <?php echo stridebr_csrf_field(); ?>
                <div class="settings-grid">
                    <label>Nome<input type="text" name="nomeusuario" maxlength="255" value="<?php echo stridebr_e($usuario['nomeusuario']); ?>" required></label>
                    <label>E-mail<input type="email" value="<?php echo stridebr_e($usuario['emailusuario']); ?>" disabled></label>
                    <label>Telefone<input type="tel" name="foneusuario" maxlength="20" value="<?php echo stridebr_e($usuario['foneusuario'] ?? ''); ?>"></label>
                    <label>Data de nascimento<input type="date" name="datanascimentousuario" value="<?php echo stridebr_e($usuario['datanascimentousuario'] ?? ''); ?>"></label>
                    <label>Gênero<select name="generousuario"><option value="">Não informado</option><?php foreach ($generos as $genero): ?><option value="<?php echo stridebr_e($genero); ?>"<?php echo $usuario['generousuario'] === $genero ? ' selected' : ''; ?>><?php echo stridebr_e($genero); ?></option><?php endforeach; ?></select></label>
                    <label>Pronomes<input type="text" name="pronomesusuario" maxlength="30" value="<?php echo stridebr_e($usuario['pronomesusuario'] ?? ''); ?>"></label>
                    <label>Peso (kg)<input type="number" name="pesousuario" min="0.01" max="9999" step="0.01" value="<?php echo stridebr_e($usuario['pesousuario'] ?? ''); ?>"></label>
                    <label>Altura (cm)<input type="number" name="alturausuario" min="1" max="300" step="1" value="<?php echo stridebr_e($usuario['alturausuario'] ?? ''); ?>"></label>
                    <label>Privacidade do perfil<select name="visibilidadeperfil"><option value="privado"<?php echo $usuario['visibilidadeperfil'] === 'privado' ? ' selected' : ''; ?>>Privado</option><option value="amigos"<?php echo $usuario['visibilidadeperfil'] === 'amigos' ? ' selected' : ''; ?>>Amigos</option><option value="publico"<?php echo $usuario['visibilidadeperfil'] === 'publico' ? ' selected' : ''; ?>>Público</option></select></label>
                    <label class="settings-wide">Bio<textarea name="biousuario" rows="4"><?php echo stridebr_e($usuario['biousuario'] ?? ''); ?></textarea></label>
                    <label class="settings-wide">Objetivo<textarea name="objetivousuario" rows="3"><?php echo stridebr_e($usuario['objetivousuario'] ?? ''); ?></textarea></label>
                </div>
                <button type="submit" class="settings-save">Salvar alterações</button>
            </form>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
