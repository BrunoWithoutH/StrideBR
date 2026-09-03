<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/eventos.php';
require_once dirname(__DIR__) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__) . '/src/layout/sport_picker.php';

$userId = stridebr_user_id();
$available = eventosDisponiveis($pdo) && stridebr_feature_enabled($pdo, 'events.enabled', true);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = stridebr_require_login();
    stridebr_verify_csrf();
    try {
        if (!$available) throw new RuntimeException('Eventos não estão disponíveis no momento.');
        $eventId = trim((string) ($_POST['idevento'] ?? ''));
        $saved = eventosAlternarSalvo($pdo, $userId, $eventId);
        stridebr_flash('success', $saved ? 'Evento salvo.' : 'Evento removido dos salvos.');
        $return = stridebr_safe_redirect((string) ($_POST['return_to'] ?? ''), '/calendario.php');
        header('Location: ' . $return);
        exit;
    } catch (Throwable $e) {
        $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível atualizar o evento.';
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sport = trim((string) ($_GET['modalidade'] ?? ''));
$state = trim((string) ($_GET['estado'] ?? ''));
$month = trim((string) ($_GET['mes'] ?? ''));
$savedOnly = isset($_GET['salvos']) && $userId !== null;
$filters = ['q' => $q, 'modalidade' => $sport, 'estado' => $state, 'mes' => $month, 'salvos' => $savedOnly];
$events = $available ? eventosListarPublicados($pdo, $filters, $userId, 100) : [];
$modalidades = $available ? eventosListarModalidades($pdo) : [];
$states = [];
if ($available) {
    try {
        $states = $pdo->query("SELECT DISTINCT estado FROM eventos_esportivos WHERE status IN ('publicado','cancelado') AND estado IS NOT NULL AND trim(estado) <> '' ORDER BY estado")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) { $states = []; }
}
$flashes = stridebr_take_flashes();
$currentQuery = $_SERVER['REQUEST_URI'] ?? '/calendario.php';
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Encontre corridas, rústicas, pedais e outros eventos esportivos cadastrados no StrideBR.">
    <meta property="og:title" content="Eventos esportivos | StrideBR">
    <meta property="og:description" content="Corridas e outros eventos esportivos em um calendário direto, com fontes oficiais e links de inscrição.">
    <meta property="og:image" content="<?php echo stridebr_e(stridebr_public_url() . '/assets/img/branding/stridebr-og.png'); ?>">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/events.css')); ?>">
    <title>Eventos esportivos | StrideBR</title>
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell events-page">
            <header class="page-heading events-heading">
                <div><span class="eyebrow">Calendário esportivo</span><h1>Eventos</h1><p>Corridas e outros eventos cadastrados com fonte, data, local e links oficiais.</p></div>
                <?php if ($userId !== null): ?><a class="secondary-button" href="/calendario.php?salvos=1">Meus salvos</a><?php endif; ?>
            </header>

            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>

            <?php if (!$available): ?>
                <section class="content-card events-empty"><h2>Eventos indisponíveis no momento</h2></section>
            <?php else: ?>
                <form class="events-filter content-card" method="get">
                    <label class="events-search">Buscar<input type="search" name="q" value="<?php echo stridebr_e($q); ?>" placeholder="Evento, cidade, organizador..."></label>
                    <div class="event-sport-filter"><span class="form-field-label">Esporte</span><?php echo sportPickerRenderSelect($modalidades, ['name' => 'modalidade', 'selected' => $sport, 'empty_label' => 'Todos']); ?></div>
                    <label>Estado<select name="estado"><option value="">Todos</option><?php foreach ($states as $uf): ?><option value="<?php echo stridebr_e((string) $uf); ?>"<?php echo $state === (string) $uf ? ' selected' : ''; ?>><?php echo stridebr_e((string) $uf); ?></option><?php endforeach; ?></select></label>
                    <label>Mês<input type="month" name="mes" value="<?php echo stridebr_e($month); ?>"></label>
                    <?php if ($savedOnly): ?><input type="hidden" name="salvos" value="1"><?php endif; ?>
                    <button class="primary-button" type="submit">Filtrar</button>
                    <?php if ($q !== '' || $sport !== '' || $state !== '' || $month !== '' || $savedOnly): ?><a class="events-clear" href="/calendario.php">Limpar</a><?php endif; ?>
                </form>

                <?php if ($events === []): ?>
                    <section class="content-card events-empty"><h2><?php echo $savedOnly ? 'Nenhum evento salvo' : 'Nenhum evento encontrado'; ?></h2><p><?php echo $savedOnly ? 'Salve um evento para encontrá-lo rapidamente aqui.' : 'Tente remover algum filtro ou volte mais tarde; novos eventos podem ser cadastrados pela administração.'; ?></p><?php if ($savedOnly): ?><a class="primary-button" href="/calendario.php">Ver todos os eventos</a><?php endif; ?></section>
                <?php else: ?>
                <section class="events-grid" aria-label="Eventos encontrados">
                    <?php foreach ($events as $event):
                        $date = new DateTimeImmutable((string) $event['data_inicio']);
                        $distances = is_array($event['distancias']) ? $event['distancias'] : (json_decode((string) $event['distancias'], true) ?: []);
                        $location = trim((string) ($event['cidade'] ?? ''));
                        if (!empty($event['estado'])) $location .= ($location !== '' ? ' · ' : '') . $event['estado'];
                        if ($location === '') $location = (string) ($event['local_nome'] ?: 'Local a confirmar');
                    ?>
                    <article class="event-card<?php echo stridebr_db_bool($event['destaque']) ? ' is-featured' : ''; ?><?php echo $event['status'] === 'cancelado' ? ' is-cancelled' : ''; ?>">
                        <a class="event-card-media" href="/evento.php?e=<?php echo rawurlencode((string) $event['slug']); ?>"><?php if ($event['imagem_principal']): ?><img src="<?php echo stridebr_e((string) $event['imagem_principal']); ?>" alt="<?php echo stridebr_e((string) $event['titulo']); ?>" loading="lazy" decoding="async"><?php else: ?><div class="event-card-placeholder"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-icon.svg')); ?>" alt=""></div><?php endif; ?><time datetime="<?php echo stridebr_e($date->format(DATE_ATOM)); ?>"><strong><?php echo stridebr_e($date->format('d')); ?></strong><span><?php echo stridebr_e(strtoupper(['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'][(int) $date->format('n') - 1])); ?></span></time><?php if ($event['status'] === 'cancelado'): ?><b class="event-status-badge">Cancelado</b><?php elseif (stridebr_db_bool($event['destaque'])): ?><b class="event-status-badge">Destaque</b><?php endif; ?></a>
                        <div class="event-card-body">
                            <div class="event-card-kind"><?php if (!empty($event['modalidade_slug'])): ?><span><?php echo stridebr_sport_icon_html((string) $event['modalidade_slug'], 'sport-icon'); ?></span><?php endif; ?><span><?php echo stridebr_e((string) ($event['tipo'] ?: $event['modalidade_nome'] ?: 'Evento esportivo')); ?></span></div>
                            <h2><a href="/evento.php?e=<?php echo rawurlencode((string) $event['slug']); ?>"><?php echo stridebr_e((string) $event['titulo']); ?></a></h2>
                            <p><?php echo stridebr_e($location); ?> · <?php echo stridebr_e($date->format('H:i')); ?></p>
                            <?php if ($distances !== []): ?><div class="event-distance-chips"><?php foreach (array_slice($distances, 0, 5) as $distance): ?><span><?php echo stridebr_e((string) $distance); ?></span><?php endforeach; ?></div><?php endif; ?>
                            <footer><a href="/evento.php?e=<?php echo rawurlencode((string) $event['slug']); ?>">Ver detalhes</a><?php if ($userId !== null): ?><form method="post"><?php echo stridebr_csrf_field(); ?><input type="hidden" name="idevento" value="<?php echo stridebr_e((string) $event['idevento']); ?>"><input type="hidden" name="return_to" value="<?php echo stridebr_e($currentQuery); ?>"><button type="submit" class="event-save-button<?php echo stridebr_db_bool($event['salvo']) ? ' is-saved' : ''; ?>"><?php echo stridebr_db_bool($event['salvo']) ? '★ Salvo' : '☆ Salvar'; ?></button></form><?php endif; ?></footer>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
</body>
</html>
