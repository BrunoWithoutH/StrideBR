<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/includes/errors.php';
require_once dirname(__DIR__) . '/src/includes/app.php';
require_once dirname(__DIR__) . '/src/layout/ads.php';
require_once dirname(__DIR__) . '/src/config/pg_config.php';
require_once dirname(__DIR__) . '/src/function/eventos.php';
require_once dirname(__DIR__) . '/src/includes/sport_icons.php';

$identifier = trim((string) ($_GET['e'] ?? ''));
$userId = stridebr_user_id();
$available = eventosDisponiveis($pdo) && stridebr_feature_enabled($pdo, 'events.enabled', true);
$event = $available && $identifier !== '' ? eventosBuscarPublico($pdo, $identifier, $userId) : null;
if (!$event) {
    http_response_code(404);
    require __DIR__ . '/errors/404.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = stridebr_require_login();
    stridebr_verify_csrf();
    try {
        $saved = eventosAlternarSalvo($pdo, $userId, (string) $event['idevento']);
        stridebr_flash('success', $saved ? 'Evento salvo.' : 'Evento removido dos salvos.');
    } catch (Throwable $e) {
        stridebr_flash('danger', $e instanceof InvalidArgumentException ? $e->getMessage() : 'Não foi possível atualizar o evento.');
    }
    header('Location: /evento.php?e=' . rawurlencode((string) $event['slug']));
    exit;
}

$start = new DateTimeImmutable((string) $event['data_inicio']);
$end = $event['data_fim'] ? new DateTimeImmutable((string) $event['data_fim']) : null;
$deadline = $event['inscricoes_ate'] ? new DateTimeImmutable((string) $event['inscricoes_ate']) : null;
$distances = is_array($event['distancias']) ? $event['distancias'] : (json_decode((string) $event['distancias'], true) ?: []);
$images = (array) ($event['imagens'] ?? []);
$mainImage = $images[0]['caminho'] ?? '/assets/img/branding/stridebr-og.png';
$locationParts = array_filter([(string) ($event['local_nome'] ?? ''), (string) ($event['cidade'] ?? ''), (string) ($event['estado'] ?? '')], static fn(string $v): bool => trim($v) !== '');
$location = implode(' · ', $locationParts);
$descriptionMeta = trim(preg_replace('/\s+/u', ' ', (string) ($event['descricao'] ?? '')) ?? '');
if ($descriptionMeta === '') $descriptionMeta = 'Detalhes, data e fontes do evento esportivo no StrideBR.';
if (function_exists('mb_substr')) $descriptionMeta = mb_substr($descriptionMeta, 0, 160, 'UTF-8'); else $descriptionMeta = substr($descriptionMeta, 0, 160);
$eventCanonical = stridebr_public_url() . '/evento.php?e=' . rawurlencode((string) $event['slug']);
$eventImageUrls = [];
foreach ($images as $image) {
    $path = trim((string) ($image['caminho'] ?? ''));
    if ($path === '') continue;
    $eventImageUrls[] = str_starts_with($path, 'http') ? $path : stridebr_public_url() . $path;
}
if ($eventImageUrls === []) $eventImageUrls[] = stridebr_public_url() . '/assets/img/branding/stridebr-og.png';
$structuredEvent = [
    '@context' => 'https://schema.org',
    '@type' => 'SportsEvent',
    'name' => (string) $event['titulo'],
    'description' => $descriptionMeta,
    'startDate' => $start->format(DATE_ATOM),
    'url' => $eventCanonical,
    'image' => $eventImageUrls,
    'eventStatus' => $event['status'] === 'cancelado' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
];
if ($end) $structuredEvent['endDate'] = $end->format(DATE_ATOM);
$placeName = trim((string) ($event['local_nome'] ?? '')) ?: trim(implode(', ', array_filter([(string) ($event['cidade'] ?? ''), (string) ($event['estado'] ?? '')])));
if ($placeName !== '' || trim((string) ($event['endereco'] ?? '')) !== '') {
    $structuredEvent['location'] = [
        '@type' => 'Place',
        'name' => $placeName !== '' ? $placeName : stridebr_t('event.place_fallback'),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => (string) ($event['endereco'] ?? ''),
            'addressLocality' => (string) ($event['cidade'] ?? ''),
            'addressRegion' => (string) ($event['estado'] ?? ''),
            'addressCountry' => (string) ($event['pais'] ?? 'BR'),
        ],
    ];
}
if (trim((string) ($event['organizador'] ?? '')) !== '') {
    $structuredEvent['organizer'] = ['@type' => 'Organization', 'name' => (string) $event['organizador']];
}
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?php echo stridebr_e($descriptionMeta); ?>">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo stridebr_e((string) $event['titulo']); ?> | StrideBR">
    <meta property="og:description" content="<?php echo stridebr_e($descriptionMeta); ?>">
    <meta property="og:image" content="<?php echo stridebr_e(str_starts_with((string) $mainImage, 'http') ? (string) $mainImage : stridebr_public_url() . (string) $mainImage); ?>">
    <meta property="og:url" content="<?php echo stridebr_e($eventCanonical); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="<?php echo stridebr_e($eventCanonical); ?>">
    <script type="application/ld+json"><?php echo json_encode($structuredEvent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">

    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/events.css')); ?>">
    <title><?php echo stridebr_e((string) $event['titulo']); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
<?php require dirname(__DIR__) . '/src/layout/header.php'; ?>
<main class="main-content"><div class="page-shell event-detail-page">
    <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e((string) ($flash['type'] ?? 'info')); ?>"><?php echo stridebr_e((string) ($flash['message'] ?? '')); ?></div><?php endforeach; ?>
    <a class="event-back-link context-back-button" href="/calendario.php" data-safe-back>← <?php echo stridebr_e(stridebr_t('event.back')); ?></a>
    <article class="event-detail-hero<?php echo $event['status'] === 'cancelado' ? ' is-cancelled' : ''; ?>">
        <div class="event-detail-media"><?php if ($images !== []): ?><img src="<?php echo stridebr_e((string) $images[0]['caminho']); ?>" alt="<?php echo stridebr_e((string) ($images[0]['texto_alternativo'] ?: $event['titulo'])); ?>"><?php else: ?><div class="event-detail-placeholder"><img src="<?php echo stridebr_e(stridebr_asset('/assets/img/logos/stridebr-icon.svg')); ?>" alt=""></div><?php endif; ?></div>
        <div class="event-detail-intro">
            <div class="event-detail-kind"><?php if (!empty($event['modalidade_slug'])): ?><span><?php echo stridebr_sport_icon_html((string) $event['modalidade_slug'], 'sport-icon'); ?></span><?php endif; ?><span><?php echo stridebr_e((string) ($event['tipo'] ?: stridebr_sport_name((string) ($event['modalidade_slug'] ?? ''), (string) ($event['modalidade_nome'] ?: stridebr_t('events.sport_event'))))); ?></span><?php if ($event['status'] === 'cancelado'): ?><b><?php echo stridebr_e(stridebr_t('events.cancelled')); ?></b><?php endif; ?></div>
            <h1><?php echo stridebr_e((string) $event['titulo']); ?></h1>
            <p class="event-detail-date"><strong><?php echo stridebr_e(stridebr_format_date_short($start)); ?></strong> · <?php echo stridebr_e($start->format('H:i')); ?><?php if ($end): ?> <?php echo stridebr_e(stridebr_t('event.until')); ?> <?php echo stridebr_e($end->format($end->format('Y-m-d') === $start->format('Y-m-d') ? 'H:i' : (stridebr_locale() === 'en' ? 'M j, Y H:i' : 'd/m/Y H:i'))); ?><?php endif; ?></p>
            <?php if ($location !== ''): ?><p class="event-detail-location"><?php echo stridebr_e($location); ?></p><?php endif; ?>
            <?php if ($distances !== []): ?><div class="event-distance-chips event-detail-distances"><?php foreach ($distances as $distance): ?><span><?php echo stridebr_e((string) $distance); ?></span><?php endforeach; ?></div><?php endif; ?>
            <div class="event-detail-actions">
                <?php if ($event['status'] !== 'cancelado' && $event['url_inscricao']): ?><a class="primary-button" href="<?php echo stridebr_e((string) $event['url_inscricao']); ?>" target="_blank" rel="noopener noreferrer"><?php echo stridebr_e(stridebr_t('event.open_registration')); ?></a><?php endif; ?>
                <?php if ($event['url_oficial']): ?><a class="secondary-button" href="<?php echo stridebr_e((string) $event['url_oficial']); ?>" target="_blank" rel="noopener noreferrer"><?php echo stridebr_e(stridebr_t('event.official_site')); ?></a><?php endif; ?>
                <?php if ($userId !== null): ?><form method="post"><?php echo stridebr_csrf_field(); ?><button class="secondary-button" type="submit"><?php echo stridebr_e(stridebr_t(stridebr_db_bool($event['salvo']) ? 'event.saved' : 'event.save')); ?></button></form><?php else: ?><a class="secondary-button" href="/login.php"><?php echo stridebr_e(stridebr_t('event.sign_in_save')); ?></a><?php endif; ?>
            </div>
            <?php if ($deadline): ?><p class="event-registration-deadline"><?php echo stridebr_e(stridebr_t('event.registration_until', ['date' => stridebr_format_datetime_short($deadline)])); ?></p><?php endif; ?>
        </div>
    </article>

    <div class="event-detail-grid">
        <section class="content-card event-description"><h2><?php echo stridebr_e(stridebr_t('event.about')); ?></h2><?php if ($event['descricao']): ?><p><?php echo nl2br(stridebr_e((string) $event['descricao'])); ?></p><?php else: ?><p><?php echo stridebr_e(stridebr_t('event.no_description')); ?></p><?php endif; ?></section>
        <aside class="content-card event-facts"><h2><?php echo stridebr_e(stridebr_t('event.information')); ?></h2><dl><?php if ($event['organizador']): ?><div><dt><?php echo stridebr_e(stridebr_t('event.organizer')); ?></dt><dd><?php echo stridebr_e((string) $event['organizador']); ?></dd></div><?php endif; ?><div><dt><?php echo stridebr_e(stridebr_t('common.date')); ?></dt><dd><?php echo stridebr_e(stridebr_format_datetime_short($start)); ?></dd></div><?php if ($location !== ''): ?><div><dt><?php echo stridebr_e(stridebr_t('event.location')); ?></dt><dd><?php echo stridebr_e($location); ?></dd></div><?php endif; ?><?php if ($event['endereco']): ?><div><dt><?php echo stridebr_e(stridebr_t('event.address')); ?></dt><dd><?php echo stridebr_e((string) $event['endereco']); ?></dd></div><?php endif; ?></dl></aside>
    </div>

    <?php if (count($images) > 1): ?><section class="event-gallery-section"><div class="section-title-row"><div><h2><?php echo stridebr_e(stridebr_t('event.images')); ?></h2><p><?php echo stridebr_e(stridebr_t('event.images_help')); ?></p></div></div><div class="event-public-gallery"><?php foreach ($images as $image): ?><a href="<?php echo stridebr_e((string) $image['caminho']); ?>" target="_blank" rel="noopener"><img src="<?php echo stridebr_e((string) $image['caminho']); ?>" alt="<?php echo stridebr_e((string) ($image['texto_alternativo'] ?: $event['titulo'])); ?>" loading="lazy"></a><?php endforeach; ?></div></section><?php endif; ?>

    <section class="content-card event-sources-public"><h2><?php echo stridebr_e(stridebr_t('event.sources')); ?></h2><p><?php echo stridebr_e(stridebr_t('event.disclaimer')); ?></p><?php if (!empty($event['fontes'])): ?><ul><?php foreach ($event['fontes'] as $source): ?><li><a href="<?php echo stridebr_e((string) $source['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo stridebr_e((string) $source['nome']); ?> ↗</a></li><?php endforeach; ?></ul><?php elseif ($event['url_oficial']): ?><p><a href="<?php echo stridebr_e((string) $event['url_oficial']); ?>" target="_blank" rel="noopener noreferrer"><?php echo stridebr_e(stridebr_t('event.official_site')); ?></a></p><?php else: ?><p><?php echo stridebr_e(stridebr_t('event.no_sources')); ?></p><?php endif; ?></section>
    <?php stridebr_render_ad_slot('event-detail-end', '/evento.php', $userId !== null); ?>
</div></main>
</div>
<?php require dirname(__DIR__) . '/src/layout/footer.php'; ?>
</body>
</html>
