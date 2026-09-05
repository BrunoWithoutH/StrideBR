<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
stridebr_require_role('admin');
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/admin.php';
require_once dirname(__DIR__, 2) . '/src/function/eventos.php';
require_once dirname(__DIR__, 2) . '/src/layout/sport_picker.php';

$errors = [];
$available = eventosDisponiveis($pdo);
$modalidades = $available ? eventosListarModalidades($pdo) : [];
$importPreview = is_array($_SESSION['event_import_preview'] ?? null) ? $_SESSION['event_import_preview'] : [];
$uploadDirectory = dirname(__DIR__) . '/uploads/events';
$publicRoot = dirname(__DIR__);

$deleteEventImageFile = static function (string $path) use ($publicRoot): void {
    if (preg_match('#^/uploads/events/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#i', $path) !== 1) return;
    $disk = $publicRoot . $path;
    if (is_file($disk)) @unlink($disk);
};

$saveUploadedImages = static function (string $eventId, string $title) use ($pdo, $uploadDirectory): int {
    $files = $_FILES['imagens'] ?? null;
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) return 0;

    $existingStmt = $pdo->prepare('SELECT COUNT(*), COALESCE(bool_or(principal), FALSE) FROM eventos_imagens WHERE idevento=:evento');
    $existingStmt->execute([':evento' => $eventId]);
    $existing = $existingStmt->fetch(PDO::FETCH_NUM) ?: [0, false];
    $currentCount = (int) ($existing[0] ?? 0);
    $hasPrimary = stridebr_db_bool($existing[1] ?? false);
    $saved = 0;

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Não foi possível preparar a pasta de imagens de eventos.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $total = count($files['name']);
    for ($i = 0; $i < $total; $i++) {
        $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($currentCount + $saved >= 8) throw new InvalidArgumentException('Cada evento pode ter no máximo 8 imagens.');
        if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Uma das imagens não pôde ser enviada.');
        $size = (int) ($files['size'][$i] ?? 0);
        if ($size <= 0 || $size > 6 * 1024 * 1024) throw new InvalidArgumentException('Cada imagem deve ter no máximo 6 MB.');
        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Upload de imagem inválido.');
        $info = @getimagesize($tmp);
        $mime = '';
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($tmp);
        }
        if ($info === false || !isset($allowed[$mime])) throw new InvalidArgumentException('Use imagens JPG, PNG ou WebP válidas.');
        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000 || $width * $height > 30000000) {
            throw new InvalidArgumentException('A imagem é grande demais. Use até 30 megapixels.');
        }
        $baseName = bin2hex(random_bytes(16));
        $name = $baseName . '.webp';
        $destination = $uploadDirectory . '/' . $name;
        if (!stridebr_image_webp_fit($tmp, $destination, 1920, 1920, 82)) {
            $name = $baseName . '.' . $allowed[$mime];
            $destination = $uploadDirectory . '/' . $name;
            if (!move_uploaded_file($tmp, $destination)) throw new RuntimeException('Não foi possível salvar uma imagem do evento.');
        }
        @chmod($destination, 0644);
        $path = '/uploads/events/' . $name;
        $primary = !$hasPrimary && $saved === 0;
        $insert = $pdo->prepare('INSERT INTO eventos_imagens (idimagem, idevento, caminho, texto_alternativo, principal, ordem) VALUES (:id,:evento,:caminho,:alt,:principal,:ordem)');
        $insert->execute([
            ':id' => stridebr_generate_id(),
            ':evento' => $eventId,
            ':caminho' => $path,
            ':alt' => 'Imagem de ' . $title,
            ':principal' => $primary,
            ':ordem' => $currentCount + $saved + 1,
        ]);
        if ($primary) $hasPrimary = true;
        $saved++;
    }
    return $saved;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    try {
        if (!$available) throw new RuntimeException('A migration de eventos ainda não foi aplicada.');

        if ($action === 'import_preview') {
            $raw = trim((string) ($_POST['import_text'] ?? ''));
            $format = trim((string) ($_POST['import_format'] ?? ''));
            $file = $_FILES['import_file'] ?? null;
            if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Não foi possível ler o arquivo de importação.');
                if ((int) ($file['size'] ?? 0) > 1024 * 1024) throw new InvalidArgumentException('O arquivo de importação deve ter no máximo 1 MB.');
                $tmp = (string) ($file['tmp_name'] ?? '');
                if ($tmp === '' || !is_uploaded_file($tmp)) throw new InvalidArgumentException('Arquivo de importação inválido.');
                $raw = (string) file_get_contents($tmp);
                $extension = stridebr_lower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
                if (in_array($extension, ['json', 'csv'], true)) $format = $extension;
            }
            try {
                $preview = eventosImportacaoLer($raw, $format, $pdo, $modalidades);
            } catch (JsonException) {
                throw new InvalidArgumentException('O JSON não é válido.');
            }
            $_SESSION['event_import_preview'] = $preview;
            stridebr_flash('success', count($preview) . ' evento(s) analisado(s). Revise a prévia antes de importar.');
            header('Location: /admin/events.php#importar-eventos');
            exit;
        }

        if ($action === 'import_clear') {
            unset($_SESSION['event_import_preview']);
            stridebr_flash('info', 'Prévia de importação descartada.');
            header('Location: /admin/events.php#importar-eventos');
            exit;
        }

        if ($action === 'import_commit') {
            $preview = is_array($_SESSION['event_import_preview'] ?? null) ? $_SESSION['event_import_preview'] : [];
            $validRows = array_values(array_filter($preview, static fn(array $row): bool => !empty($row['valid'])));
            if ($validRows === []) throw new InvalidArgumentException('Não há eventos válidos e novos para importar.');
            $created = [];
            $pdo->beginTransaction();
            try {
                foreach ($validRows as $row) {
                    $savedId = eventosSalvar($pdo, $idUsuario, (array) $row['payload']);
                    $sourceUrl = trim((string) ($row['source_url'] ?? ''));
                    if ($sourceUrl !== '') eventosSalvarFontes($pdo, $savedId, [(string) ($row['source_name'] ?? 'Importação')], [$sourceUrl]);
                    $created[] = $savedId;
                }
                $pdo->commit();
            } catch (Throwable $importError) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $importError;
            }
            unset($_SESSION['event_import_preview']);
            stridebr_admin_audit($pdo, $idUsuario, 'event.import', 'eventos', null, ['quantidade' => count($created)]);
            stridebr_flash('success', count($created) . ' evento(s) importado(s) como rascunho.');
            header('Location: /admin/events.php');
            exit;
        }

        if ($action === 'save') {
            $eventId = trim((string) ($_POST['idevento'] ?? '')) ?: null;
            $pdo->beginTransaction();
            try {
                $savedId = eventosSalvar($pdo, $idUsuario, $_POST, $eventId);
                eventosSalvarFontes($pdo, $savedId, (array) ($_POST['fonte_nome'] ?? []), (array) ($_POST['fonte_url'] ?? []));
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $count = $saveUploadedImages($savedId, trim((string) ($_POST['titulo'] ?? 'Evento')));
            stridebr_admin_audit($pdo, $idUsuario, $eventId === null ? 'event.create' : 'event.update', 'evento', $savedId, ['imagens_adicionadas' => $count]);
            stridebr_flash('success', $eventId === null ? 'Evento criado.' : 'Evento atualizado.');
            header('Location: /admin/events.php?edit=' . rawurlencode($savedId));
            exit;
        }

        if ($action === 'delete_image') {
            $imageId = trim((string) ($_POST['idimagem'] ?? ''));
            $stmt = $pdo->prepare('DELETE FROM eventos_imagens WHERE idimagem=:id RETURNING idevento,caminho,principal');
            $stmt->execute([':id' => $imageId]);
            $image = $stmt->fetch();
            if (!$image) throw new InvalidArgumentException('Imagem não encontrada.');
            $deleteEventImageFile((string) $image['caminho']);
            if (stridebr_db_bool($image['principal'])) {
                $pdo->prepare('UPDATE eventos_imagens SET principal=TRUE WHERE idimagem=(SELECT idimagem FROM eventos_imagens WHERE idevento=:evento ORDER BY ordem,idimagem LIMIT 1)')->execute([':evento' => $image['idevento']]);
            }
            stridebr_admin_audit($pdo, $idUsuario, 'event.image.delete', 'evento', (string) $image['idevento']);
            stridebr_flash('success', 'Imagem removida.');
            header('Location: /admin/events.php?edit=' . rawurlencode((string) $image['idevento']));
            exit;
        }

        if ($action === 'set_primary') {
            $imageId = trim((string) ($_POST['idimagem'] ?? ''));
            $stmt = $pdo->prepare('SELECT idevento FROM eventos_imagens WHERE idimagem=:id LIMIT 1');
            $stmt->execute([':id' => $imageId]);
            $eventId = $stmt->fetchColumn();
            if (!$eventId) throw new InvalidArgumentException('Imagem não encontrada.');
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE eventos_imagens SET principal=FALSE WHERE idevento=:evento')->execute([':evento' => $eventId]);
            $pdo->prepare('UPDATE eventos_imagens SET principal=TRUE WHERE idimagem=:imagem AND idevento=:evento')->execute([':imagem' => $imageId, ':evento' => $eventId]);
            $pdo->commit();
            stridebr_flash('success', 'Capa do evento atualizada.');
            header('Location: /admin/events.php?edit=' . rawurlencode((string) $eventId));
            exit;
        }

        if ($action === 'delete_event') {
            $eventId = trim((string) ($_POST['idevento'] ?? ''));
            $images = eventosImagens($pdo, $eventId);
            $stmt = $pdo->prepare('DELETE FROM eventos_esportivos WHERE idevento=:id');
            $stmt->execute([':id' => $eventId]);
            if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('Evento não encontrado.');
            foreach ($images as $image) $deleteEventImageFile((string) $image['caminho']);
            stridebr_admin_audit($pdo, $idUsuario, 'event.delete', 'evento', $eventId);
            stridebr_flash('success', 'Evento excluído.');
            header('Location: /admin/events.php');
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar o evento.';
        if (!$e instanceof InvalidArgumentException && !$e instanceof RuntimeException) error_log($e->getMessage());
    }
}

$importPreview = is_array($_SESSION['event_import_preview'] ?? null) ? $_SESSION['event_import_preview'] : [];
$editId = trim((string) ($_GET['edit'] ?? ''));
$editEvent = $available && $editId !== '' ? eventosBuscarAdmin($pdo, $editId) : null;
if ($editId !== '' && !$editEvent && $errors === []) $errors[] = 'Evento não encontrado.';
$q = trim((string) ($_GET['q'] ?? ''));
$events = [];
if ($available) {
    $sql = "SELECT e.idevento,e.titulo,e.slug,e.data_inicio,e.cidade,e.estado,e.status,e.destaque,m.nome modalidade_nome,(SELECT caminho FROM eventos_imagens i WHERE i.idevento=e.idevento ORDER BY i.principal DESC,i.ordem LIMIT 1) imagem FROM eventos_esportivos e LEFT JOIN modalidades m ON m.idmodalidade=e.idmodalidade";
    $params = [];
    if ($q !== '') { $sql .= " WHERE e.titulo ILIKE :q OR COALESCE(e.cidade,'') ILIKE :q OR COALESCE(e.organizador,'') ILIKE :q"; $params[':q'] = '%' . $q . '%'; }
    $sql .= ' ORDER BY e.data_inicio DESC LIMIT 100';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $events = $stmt->fetchAll();
}

$inputDate = static function (?string $value): string {
    if (!$value) return '';
    return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d\TH:i');
};
$distances = $editEvent ? (is_array($editEvent['distancias']) ? $editEvent['distancias'] : (json_decode((string) $editEvent['distancias'], true) ?: [])) : [];
$sourceRows = $editEvent['fontes'] ?? [];
while (count($sourceRows) < 3) $sourceRows[] = ['nome' => '', 'url' => ''];
$flashes = stridebr_take_flashes();
?>
<!doctype html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">

    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/events.css')); ?>">
    <title>Eventos | StrideBR Admin</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body class="admin-body">
<div class="container-fluid">
<?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="admin-shell">
    <?php echo stridebr_admin_nav('events'); ?>
    <div class="admin-heading"><div><span class="eyebrow">Administração</span><h1>Eventos</h1><p>Cadastre corridas e outros eventos, mantenha as fontes e publique imagens sem depender de integração externa.</p></div><a class="primary-action" href="/admin/events.php?new=1#event-editor">+ Novo evento</a></div>
    <?php foreach ($flashes as $f): ?><div class="alert alert-<?php echo stridebr_e((string) $f['type']); ?>"><?php echo stridebr_e((string) $f['message']); ?></div><?php endforeach; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?php echo stridebr_e($e); ?></div><?php endforeach; ?>

    <?php if (!$available): ?><section class="admin-card"><h2>Migration pendente</h2><p>Aplique <code>20260903_v1_rc.sql</code> para habilitar eventos.</p></section><?php else: ?>
    <details class="admin-card event-import-card" id="importar-eventos"<?php echo $importPreview !== [] ? ' open' : ''; ?>>
        <summary><div><strong>Importar eventos</strong><span>JSON ou CSV · sempre entra como rascunho para revisão</span></div><span>⌄</span></summary>
        <div class="event-import-body">
            <form method="post" enctype="multipart/form-data" class="event-import-form">
                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="import_preview">
                <label>Arquivo JSON/CSV<input type="file" name="import_file" accept=".json,.csv,application/json,text/csv"></label>
                <label>Ou cole os dados<textarea name="import_text" rows="7" placeholder='[{"titulo":"Corrida Exemplo","data_inicio":"2026-09-20 08:00","cidade":"Porto Alegre","estado":"RS","distancias":["5 km","10 km"],"fonte_url":"https://..."}]'></textarea></label>
                <input type="hidden" name="import_format" value="">
                <div class="event-import-actions"><button type="submit" class="secondary-action">Analisar e mostrar prévia</button><small>Máximo de 200 eventos ou 1 MB por importação.</small></div>
            </form>
            <?php if ($importPreview !== []): ?>
                <?php $importValid = count(array_filter($importPreview, static fn(array $row): bool => !empty($row['valid']))); ?>
                <div class="event-import-preview">
                    <div class="admin-card-heading"><div><h3>Prévia</h3><p><?php echo $importValid; ?> novo(s) pronto(s) para importar · <?php echo count($importPreview) - $importValid; ?> exigem revisão.</p></div></div>
                    <div class="admin-table-wrap"><table><thead><tr><th>Status</th><th>Evento</th><th>Data</th><th>Local</th><th>Observações</th></tr></thead><tbody>
                        <?php foreach ($importPreview as $row): ?>
                            <?php $payload = (array) ($row['payload'] ?? []); $notes = array_merge((array) ($row['errors'] ?? []), (array) ($row['warnings'] ?? [])); ?>
                            <tr><td><span class="status-pill <?php echo !empty($row['valid']) ? 'is-ok' : ''; ?>"><?php echo !empty($row['duplicate']) ? 'Duplicado' : (!empty($row['valid']) ? 'Novo' : 'Revisar'); ?></span></td><td><strong><?php echo stridebr_e((string) ($payload['titulo'] ?? '—')); ?></strong></td><td><?php echo stridebr_e((string) ($payload['data_inicio'] ?? '—')); ?></td><td><?php echo stridebr_e(trim((string) (($payload['cidade'] ?? '') . (($payload['cidade'] ?? '') && ($payload['estado'] ?? '') ? ' · ' : '') . ($payload['estado'] ?? ''))) ?: '—'); ?></td><td><small><?php echo stridebr_e($notes ? implode(' · ', $notes) : 'Pronto para importar.'); ?></small></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                    <div class="event-import-actions"><form method="post"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="import_commit"><button type="submit" class="primary-action"<?php echo $importValid < 1 ? ' disabled' : ''; ?>>Importar <?php echo $importValid; ?> evento(s)</button></form><form method="post"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="import_clear"><button type="submit" class="secondary-action">Descartar prévia</button></form></div>
                </div>
            <?php endif; ?>
        </div>
    </details>
    <?php if (isset($_GET['new']) || $editEvent): ?>
    <section class="admin-card event-admin-editor" id="event-editor">
        <div class="admin-card-heading"><div><h2><?php echo $editEvent ? 'Editar evento' : 'Novo evento'; ?></h2><p>Use as fontes para registrar de onde vieram as informações. O evento só aparece ao público quando estiver como publicado.</p></div><a class="secondary-action compact" href="/admin/events.php">Fechar</a></div>
        <form method="post" enctype="multipart/form-data" class="event-admin-form">
            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="save"><?php if ($editEvent): ?><input type="hidden" name="idevento" value="<?php echo stridebr_e((string) $editEvent['idevento']); ?>"><?php endif; ?>
            <label class="event-form-wide">Título<input name="titulo" maxlength="160" required value="<?php echo stridebr_e((string) ($editEvent['titulo'] ?? '')); ?>" placeholder="Ex.: 10ª Rústica Municipal"></label>
            <div class="admin-event-sport-field"><span class="form-field-label">Modalidade</span><?php echo sportPickerRenderSelect($modalidades, ['name' => 'idmodalidade', 'selected' => (string) ($editEvent['idmodalidade'] ?? ''), 'empty_label' => 'Sem modalidade específica']); ?></div>
            <label>Tipo<input name="tipo" maxlength="40" value="<?php echo stridebr_e((string) ($editEvent['tipo'] ?? '')); ?>" placeholder="Corrida de rua, trail, pedal..."></label>
            <label>Início<input type="datetime-local" name="data_inicio" required value="<?php echo stridebr_e($inputDate($editEvent['data_inicio'] ?? null)); ?>"></label>
            <label>Término <small>opcional</small><input type="datetime-local" name="data_fim" value="<?php echo stridebr_e($inputDate($editEvent['data_fim'] ?? null)); ?>"></label>
            <label>Cidade<input name="cidade" maxlength="100" value="<?php echo stridebr_e((string) ($editEvent['cidade'] ?? '')); ?>"></label>
            <label>Estado<input name="estado" maxlength="80" value="<?php echo stridebr_e((string) ($editEvent['estado'] ?? '')); ?>" placeholder="RS"></label>
            <label>País<input name="pais" maxlength="80" value="<?php echo stridebr_e((string) ($editEvent['pais'] ?? 'Brasil')); ?>"></label>
            <label>Local<input name="local_nome" maxlength="160" value="<?php echo stridebr_e((string) ($editEvent['local_nome'] ?? '')); ?>" placeholder="Praça, parque, ginásio..."></label>
            <label class="event-form-wide">Endereço<input name="endereco" maxlength="1200" value="<?php echo stridebr_e((string) ($editEvent['endereco'] ?? '')); ?>"></label>
            <label>Organizador<input name="organizador" maxlength="160" value="<?php echo stridebr_e((string) ($editEvent['organizador'] ?? '')); ?>"></label>
            <label>Distâncias <small>separe por vírgula</small><input name="distancias" value="<?php echo stridebr_e(implode(', ', array_map('strval', $distances))); ?>" placeholder="5 km, 10 km, 21 km"></label>
            <label>URL oficial<input type="url" name="url_oficial" value="<?php echo stridebr_e((string) ($editEvent['url_oficial'] ?? '')); ?>" placeholder="https://..."></label>
            <label>URL de inscrição<input type="url" name="url_inscricao" value="<?php echo stridebr_e((string) ($editEvent['url_inscricao'] ?? '')); ?>" placeholder="https://..."></label>
            <label>Inscrições até<input type="datetime-local" name="inscricoes_ate" value="<?php echo stridebr_e($inputDate($editEvent['inscricoes_ate'] ?? null)); ?>"></label>
            <label>Status<select name="status"><option value="rascunho"<?php echo ($editEvent['status'] ?? 'rascunho') === 'rascunho' ? ' selected' : ''; ?>>Rascunho</option><option value="publicado"<?php echo ($editEvent['status'] ?? '') === 'publicado' ? ' selected' : ''; ?>>Publicado</option><option value="cancelado"<?php echo ($editEvent['status'] ?? '') === 'cancelado' ? ' selected' : ''; ?>>Cancelado</option><option value="encerrado"<?php echo ($editEvent['status'] ?? '') === 'encerrado' ? ' selected' : ''; ?>>Encerrado</option></select></label>
            <label class="event-form-check"><input type="checkbox" name="destaque" value="1"<?php echo stridebr_db_bool($editEvent['destaque'] ?? false) ? ' checked' : ''; ?>> Destacar no calendário</label>
            <label class="event-form-wide">Descrição<textarea name="descricao" rows="6" maxlength="12000" placeholder="Informações úteis, percurso, categorias, retirada de kits..."><?php echo stridebr_e((string) ($editEvent['descricao'] ?? '')); ?></textarea></label>

            <fieldset class="event-form-wide event-sources"><legend>Fontes</legend><p>Você pode guardar mais de uma página usada para confirmar as informações.</p><?php foreach ($sourceRows as $source): ?><div><input name="fonte_nome[]" maxlength="120" value="<?php echo stridebr_e((string) ($source['nome'] ?? '')); ?>" placeholder="Nome da fonte"><input type="url" name="fonte_url[]" value="<?php echo stridebr_e((string) ($source['url'] ?? '')); ?>" placeholder="https://..."></div><?php endforeach; ?></fieldset>
            <label class="event-form-wide">Adicionar imagens <small>JPG, PNG ou WebP · até 6 MB · imagens grandes são otimizadas automaticamente cada · máximo 8 no evento</small><input type="file" name="imagens[]" accept="image/jpeg,image/png,image/webp" multiple></label>
            <div class="event-form-actions event-form-wide"><a class="secondary-action" href="/admin/events.php">Cancelar</a><button class="primary-action" type="submit">Salvar evento</button></div>
        </form>

        <?php if ($editEvent && !empty($editEvent['imagens'])): ?><div class="event-admin-gallery"><h3>Imagens</h3><div><?php foreach ($editEvent['imagens'] as $image): ?><article><img src="<?php echo stridebr_e((string) $image['caminho']); ?>" alt=""><span><?php echo stridebr_db_bool($image['principal']) ? 'Capa atual' : 'Imagem'; ?></span><div><?php if (!stridebr_db_bool($image['principal'])): ?><form method="post"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="set_primary"><input type="hidden" name="idimagem" value="<?php echo stridebr_e((string) $image['idimagem']); ?>"><button type="submit">Usar como capa</button></form><?php endif; ?><form method="post" data-confirm="Remover esta imagem?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete_image"><input type="hidden" name="idimagem" value="<?php echo stridebr_e((string) $image['idimagem']); ?>"><button class="danger-link" type="submit">Remover</button></form></div></article><?php endforeach; ?></div></div><?php endif; ?>
        <?php if ($editEvent): ?><form method="post" class="event-delete-form" data-confirm="Excluir permanentemente este evento, suas fontes, imagens e salvamentos dos usuários?"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="action" value="delete_event"><input type="hidden" name="idevento" value="<?php echo stridebr_e((string) $editEvent['idevento']); ?>"><button class="danger-link" type="submit">Excluir evento</button></form><?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="admin-card admin-table-card">
        <div class="admin-card-heading"><div><h2>Eventos cadastrados</h2><p><?php echo count($events); ?> resultado(s) carregados.</p></div></div>
        <form method="get" class="admin-filter"><input name="q" value="<?php echo stridebr_e($q); ?>" placeholder="Evento, cidade ou organizador"><button class="secondary-action">Buscar</button></form>
        <?php if ($events === []): ?><div class="event-admin-empty">Nenhum evento cadastrado. <a href="/admin/events.php?new=1#event-editor">Criar o primeiro</a>.</div><?php else: ?><div class="admin-table-wrap"><table><thead><tr><th>Evento</th><th>Data</th><th>Local</th><th>Status</th><th></th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><div class="event-admin-cell"><?php if ($event['imagem']): ?><img src="<?php echo stridebr_e((string) $event['imagem']); ?>" alt=""><?php endif; ?><div><strong><?php echo stridebr_e((string) $event['titulo']); ?></strong><small><?php echo stridebr_e((string) ($event['modalidade_nome'] ?: 'Sem modalidade')); ?><?php echo stridebr_db_bool($event['destaque']) ? ' · destaque' : ''; ?></small></div></div></td><td><?php echo stridebr_e((new DateTimeImmutable((string) $event['data_inicio']))->format('d/m/Y H:i')); ?></td><td><?php echo stridebr_e(trim((string) (($event['cidade'] ?? '') . (($event['cidade'] && $event['estado']) ? ' · ' : '') . ($event['estado'] ?? ''))) ?: '—'); ?></td><td><span class="status-pill <?php echo $event['status'] === 'publicado' ? 'is-ok' : ''; ?>"><?php echo stridebr_e((string) $event['status']); ?></span></td><td><a class="secondary-action compact" href="/admin/events.php?edit=<?php echo rawurlencode((string) $event['idevento']); ?>#event-editor">Editar</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <?php endif; ?>
</div></main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body></html>
