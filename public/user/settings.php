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
    $nomeExibicao = trim((string) ($_POST['nome_exibicao'] ?? ''));
    $username = stridebr_lower(trim((string) ($_POST['username'] ?? '')));
    $fone = trim((string) ($_POST['foneusuario'] ?? ''));
    $nascimento = trim((string) ($_POST['datanascimentousuario'] ?? ''));
    $genero = trim((string) ($_POST['generousuario'] ?? ''));
    $pronomes = trim((string) ($_POST['pronomesusuario'] ?? ''));
    $bio = trim((string) ($_POST['biousuario'] ?? ''));
    $pesoRaw = str_replace(',', '.', trim((string) ($_POST['pesousuario'] ?? '')));
    $alturaRaw = trim((string) ($_POST['alturausuario'] ?? ''));
    $objetivo = trim((string) ($_POST['objetivousuario'] ?? ''));
    $visibilidade = (string) ($_POST['visibilidadeperfil'] ?? 'privado');
    $units = (string) ($_POST['units'] ?? 'metric');
    $weekStart = (string) ($_POST['week_start'] ?? 'sunday');

    if ($nome === '' || stridebr_length($nome) > 255) $errors[] = 'Informe um nome válido.';
    if ($nomeExibicao === '' || stridebr_length($nomeExibicao) > 120) $errors[] = 'Informe um nome de exibição válido.';
    if ($username !== '' && !stridebr_username_is_valid($username)) $errors[] = 'Username inválido. Use de 3 a 40 caracteres: letras minúsculas, números, ponto, hífen ou underline.';
    if ($fone !== '' && stridebr_length($fone) > 20) $errors[] = 'O telefone é muito longo.';
    if ($nascimento !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $nascimento);
        if (!$date || $date->format('Y-m-d') !== $nascimento || $date > new DateTimeImmutable('today')) $errors[] = 'Data de nascimento inválida.';
    }
    if ($genero !== '' && !in_array($genero, $generos, true)) $errors[] = 'Gênero inválido.';
    if (stridebr_length($pronomes) > 30) $errors[] = 'Pronomes muito longos.';
    if ($pesoRaw !== '' && (!is_numeric($pesoRaw) || (float) $pesoRaw <= 0 || (float) $pesoRaw > 9999)) $errors[] = 'Peso inválido.';
    if ($alturaRaw !== '' && (filter_var($alturaRaw, FILTER_VALIDATE_INT) === false || (int) $alturaRaw <= 0 || (int) $alturaRaw > 300)) $errors[] = 'Altura inválida.';
    if (!in_array($visibilidade, ['privado', 'amigos', 'publico'], true)) $errors[] = 'Privacidade inválida.';
    if (!in_array($units, ['metric', 'imperial'], true)) $errors[] = 'Sistema de unidades inválido.';
    if (!in_array($weekStart, ['sunday', 'monday'], true)) $errors[] = 'Início da semana inválido.';

    if ($errors === [] && $username !== '') {
        $check = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(username) = lower(:username) AND idusuario <> :id LIMIT 1');
        $check->execute([':username' => $username, ':id' => $idUsuario]);
        if ($check->fetchColumn()) $errors[] = 'Esse username já está em uso.';
    }

    if ($errors === []) {
        $prefStmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :id');
        $prefStmt->execute([':id' => $idUsuario]);
        $rawPrefs = $prefStmt->fetchColumn();
        $preferences = is_array($rawPrefs) ? $rawPrefs : (json_decode((string) $rawPrefs, true) ?: []);
        $preferences['units'] = $units;
        $preferences['week_start'] = $weekStart;

        $stmt = $pdo->prepare('UPDATE usuarios SET nomeusuario = :nome, nome_exibicao = :display, username = :username, foneusuario = :fone, datanascimentousuario = :nascimento, generousuario = :genero, pronomesusuario = :pronomes, biousuario = :bio, pesousuario = :peso, alturausuario = :altura, objetivousuario = :objetivo, visibilidadeperfil = :visibilidade, preferenciasusuario = CAST(:preferencias AS jsonb) WHERE idusuario = :id');
        $stmt->execute([
            ':nome' => $nome,
            ':display' => $nomeExibicao,
            ':username' => $username !== '' ? $username : null,
            ':fone' => $fone !== '' ? $fone : null,
            ':nascimento' => $nascimento !== '' ? $nascimento : null,
            ':genero' => $genero !== '' ? $genero : null,
            ':pronomes' => $pronomes !== '' ? $pronomes : null,
            ':bio' => $bio !== '' ? $bio : null,
            ':peso' => $pesoRaw !== '' ? $pesoRaw : null,
            ':altura' => $alturaRaw !== '' ? (int) $alturaRaw : null,
            ':objetivo' => $objetivo !== '' ? $objetivo : null,
            ':visibilidade' => $visibilidade,
            ':preferencias' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => $idUsuario,
        ]);
        $_SESSION['NomeUsuario'] = $nome;
        $_SESSION['NomeExibicao'] = $nomeExibicao;
        $_SESSION['Username'] = $username !== '' ? $username : null;
        stridebr_flash('success', 'Configurações salvas.');
        header('Location: /user/settings.php');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT nomeusuario, nome_exibicao, username, papelusuario, emailusuario, verificado, email_verificado_em, termos_versao, privacidade_versao, foneusuario, datanascimentousuario, generousuario, pronomesusuario, biousuario, pesousuario, alturausuario, objetivousuario, visibilidadeperfil, preferenciasusuario FROM usuarios WHERE idusuario = :id LIMIT 1');
$stmt->execute([':id' => $idUsuario]);
$usuario = $stmt->fetch();
if (!$usuario) { http_response_code(404); exit('Usuário não encontrado.'); }
$preferences = is_array($usuario['preferenciasusuario'] ?? null) ? $usuario['preferenciasusuario'] : (json_decode((string) ($usuario['preferenciasusuario'] ?? '{}'), true) ?: []);
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title>Configurações | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell settings-shell">
            <div class="page-heading"><h1>Perfil e preferências</h1><p>Controle como você aparece no StrideBR e os padrões usados pelo aplicativo.</p></div>
            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <form method="POST" class="content-card settings-form">
                <?php echo stridebr_csrf_field(); ?>
                <section class="settings-section">
                    <div><h2>Identidade</h2><p>Nome de exibição é o nome usado em amigos, compartilhamentos e perfis.</p></div>
                    <div class="settings-grid">
                        <label>Nome cadastrado<input type="text" name="nomeusuario" maxlength="255" value="<?php echo stridebr_e($usuario['nomeusuario']); ?>" required></label>
                        <label>Nome de exibição<input type="text" name="nome_exibicao" maxlength="120" value="<?php echo stridebr_e($usuario['nome_exibicao'] ?: $usuario['nomeusuario']); ?>" required></label>
                        <label>@username<input type="text" name="username" maxlength="40" value="<?php echo stridebr_e($usuario['username'] ?? ''); ?>" placeholder="bruno"></label>
                        <label>E-mail<input type="email" value="<?php echo stridebr_e($usuario['emailusuario']); ?>" disabled></label>
                        <label>Nível da conta<input type="text" value="<?php echo stridebr_e($usuario['papelusuario']); ?>" disabled></label>
                    </div>
                </section>

                <section class="settings-section">
                    <div><h2>Perfil</h2><p>Esses dados são opcionais e podem ficar privados.</p></div>
                    <div class="settings-grid">
                        <label>Telefone<input type="tel" name="foneusuario" maxlength="20" value="<?php echo stridebr_e($usuario['foneusuario'] ?? ''); ?>"></label>
                        <label>Data de nascimento<input type="date" name="datanascimentousuario" value="<?php echo stridebr_e($usuario['datanascimentousuario'] ?? ''); ?>"></label>
                        <label>Gênero<select name="generousuario"><option value="">Não informado</option><?php foreach ($generos as $genero): ?><option value="<?php echo stridebr_e($genero); ?>"<?php echo $usuario['generousuario'] === $genero ? ' selected' : ''; ?>><?php echo stridebr_e($genero); ?></option><?php endforeach; ?></select></label>
                        <label>Pronomes<input type="text" name="pronomesusuario" maxlength="30" value="<?php echo stridebr_e($usuario['pronomesusuario'] ?? ''); ?>"></label>
                        <label>Peso (kg)<input type="number" name="pesousuario" min="0.01" max="9999" step="0.01" value="<?php echo stridebr_e($usuario['pesousuario'] ?? ''); ?>"></label>
                        <label>Altura (cm)<input type="number" name="alturausuario" min="1" max="300" step="1" value="<?php echo stridebr_e($usuario['alturausuario'] ?? ''); ?>"></label>
                        <label>Privacidade do perfil<select name="visibilidadeperfil"><option value="privado"<?php echo $usuario['visibilidadeperfil'] === 'privado' ? ' selected' : ''; ?>>Privado</option><option value="amigos"<?php echo $usuario['visibilidadeperfil'] === 'amigos' ? ' selected' : ''; ?>>Amigos</option><option value="publico"<?php echo $usuario['visibilidadeperfil'] === 'publico' ? ' selected' : ''; ?>>Público</option></select></label>
                        <label class="settings-wide">Bio<textarea name="biousuario" rows="4"><?php echo stridebr_e($usuario['biousuario'] ?? ''); ?></textarea></label>
                        <label class="settings-wide">Objetivo / notas pessoais<textarea name="objetivousuario" rows="3"><?php echo stridebr_e($usuario['objetivousuario'] ?? ''); ?></textarea></label>
                    </div>
                </section>

                <section class="settings-section">
                    <div><h2>Preferências</h2><p>Padrões usados nas interfaces de treino e cronograma.</p></div>
                    <div class="settings-grid">
                        <label>Unidades<select name="units"><option value="metric"<?php echo ($preferences['units'] ?? 'metric') === 'metric' ? ' selected' : ''; ?>>Métricas</option><option value="imperial"<?php echo ($preferences['units'] ?? '') === 'imperial' ? ' selected' : ''; ?>>Imperiais</option></select></label>
                        <label>A semana começa<select name="week_start"><option value="sunday"<?php echo ($preferences['week_start'] ?? 'sunday') === 'sunday' ? ' selected' : ''; ?>>Domingo</option><option value="monday"<?php echo ($preferences['week_start'] ?? '') === 'monday' ? ' selected' : ''; ?>>Segunda-feira</option></select></label>
                    </div>
                </section>

                <section class="settings-section">
                    <div><h2>Conta e segurança</h2><p>Status da conta e recursos de recuperação preparados para a alpha.</p></div>
                    <div class="settings-security-grid">
                        <article><strong>E-mail</strong><span><?php echo ($usuario['email_verificado_em'] || stridebr_db_bool($usuario['verificado'])) ? 'Verificado' : 'Ainda não verificado'; ?></span><?php if (!($usuario['email_verificado_em'] || stridebr_db_bool($usuario['verificado'])) && stridebr_feature_enabled($pdo, 'auth.email_verification.enabled', false)): ?><a href="/resend-verification.php">Reenviar verificação</a><?php endif; ?></article>
                        <article><strong>Recuperação de senha</strong><span><?php echo stridebr_feature_enabled($pdo, 'auth.password_reset.enabled', false) ? 'Disponível por e-mail' : 'Desativada durante a alpha'; ?></span><?php if (stridebr_feature_enabled($pdo, 'auth.password_reset.enabled', false)): ?><a href="/forgot-password.php">Abrir recuperação</a><?php endif; ?></article>
                        <article><strong>Termos aceitos</strong><span><?php echo stridebr_e($usuario['termos_versao'] ?? 'conta anterior ao versionamento'); ?></span><a href="/pages/legal/terms.php">Ver termos atuais</a></article>
                        <article><strong>Privacidade</strong><span><?php echo stridebr_e($usuario['privacidade_versao'] ?? 'conta anterior ao versionamento'); ?></span><a href="/pages/legal/privacy.php">Ver política atual</a></article>
                    </div>
                </section>
                <button type="submit" class="settings-save">Salvar alterações</button>
            </form>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
