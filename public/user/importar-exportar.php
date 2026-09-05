<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
$idUsuario = stridebr_require_login();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';

$catalogo = atividadeListarCatalogo($pdo, $idUsuario);
$modalidades = [];
foreach ($catalogo as $modalidade) {
    if (empty($modalidade['modelos'])) continue;
    $modalidades[] = [
        'slug' => (string) $modalidade['slug'],
        'name' => stridebr_sport_name((string) $modalidade['slug'], (string) $modalidade['nome']),
        'category' => (string) ($modalidade['categoria'] ?? ''),
    ];
}
$exchangeSchemaReady = (int) $pdo->query("SELECT CASE WHEN to_regclass('stridebr.atividade_importacoes') IS NULL THEN 0 ELSE 1 END")->fetchColumn() === 1;
if ($exchangeSchemaReady) {
    $recordsStmt = $pdo->prepare("SELECT ra.idregistro, ra.titulo, ra.data_inicio, m.nome AS modalidade_nome, m.slug AS modalidade_slug,
                                         EXISTS (SELECT 1 FROM stridebr.rotas_atividade rota WHERE rota.idregistro = ra.idregistro) AS rota_disponivel,
                                         ai.formato, ai.original_disponivel, ai.rota_importada_disponivel
                                  FROM stridebr.registros_atividade ra
                                  JOIN stridebr.modalidades m ON m.idmodalidade = ra.idmodalidade
                                  LEFT JOIN LATERAL (
                                      SELECT formato,
                                             (arquivo_original IS NOT NULL) AS original_disponivel,
                                             (rota_geojson IS NOT NULL) AS rota_importada_disponivel
                                      FROM stridebr.atividade_importacoes
                                      WHERE idusuario = :usuario_importacao
                                        AND idregistro = ra.idregistro
                                        AND status = 'importado'
                                      ORDER BY data_criacao DESC
                                      LIMIT 1
                                  ) ai ON TRUE
                                  WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL
                                  ORDER BY ra.data_inicio DESC, ra.idregistro DESC
                                  LIMIT 40");
    $recordsStmt->execute([':usuario' => $idUsuario, ':usuario_importacao' => $idUsuario]);
} else {
    $recordsStmt = $pdo->prepare("SELECT ra.idregistro, ra.titulo, ra.data_inicio, m.nome AS modalidade_nome, m.slug AS modalidade_slug,
                                         EXISTS (SELECT 1 FROM stridebr.rotas_atividade rota WHERE rota.idregistro = ra.idregistro) AS rota_disponivel,
                                         NULL::varchar AS formato, FALSE AS original_disponivel, FALSE AS rota_importada_disponivel
                                  FROM stridebr.registros_atividade ra
                                  JOIN stridebr.modalidades m ON m.idmodalidade = ra.idmodalidade
                                  WHERE ra.idusuario = :usuario AND ra.excluido_em IS NULL
                                  ORDER BY ra.data_inicio DESC, ra.idregistro DESC
                                  LIMIT 40");
    $recordsStmt->execute([':usuario' => $idUsuario]);
}
$registros = $recordsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">

    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/activity-exchange.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t('exchange.page_title')); ?></title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content exchange-page" data-exchange-page data-csrf-token="<?php echo stridebr_e(stridebr_csrf_token()); ?>">
        <header class="exchange-heading">
            <div>
                <a href="/user/atividades.php" class="exchange-back"><span aria-hidden="true">←</span><span><?php echo stridebr_e(stridebr_t('activity.summary.activities')); ?></span></a>
                <h1><?php echo stridebr_e(stridebr_t('activity.import_export')); ?></h1>
            </div>
        </header>

        <section class="exchange-card exchange-import-card" id="importar">
            <div class="exchange-card-heading">
                <div><h2><?php echo stridebr_e(stridebr_t('exchange.import_activities')); ?></h2></div>
                <div class="exchange-format-badges"><span>FIT</span><span>TCX</span><span>GPX</span></div>
            </div>

            <?php if (!$exchangeSchemaReady): ?>
                <div class="exchange-system-warning"><?php echo stridebr_e(stridebr_t('exchange.schema_unavailable')); ?></div>
            <?php endif; ?>
            <label class="exchange-dropzone<?php echo !$exchangeSchemaReady ? ' is-disabled' : ''; ?>" data-import-dropzone>
                <input type="file" accept=".fit,.tcx,.gpx,application/gpx+xml,application/vnd.garmin.tcx+xml" multiple data-import-files<?php echo !$exchangeSchemaReady ? ' disabled' : ''; ?>>
                <span class="exchange-drop-icon" aria-hidden="true">↑</span>
                <strong><?php echo stridebr_e(stridebr_t('exchange.choose_files')); ?></strong>
                <small><?php echo stridebr_e(stridebr_t('exchange.file_limits')); ?></small>
            </label>
            <div class="exchange-bulk-toolbar" data-import-bulk hidden>
                <div><strong data-import-selected-count><?php echo stridebr_e(stridebr_t('exchange.none_selected')); ?></strong><span><?php echo stridebr_e(stridebr_t('exchange.bulk_help')); ?></span></div>
                <div class="exchange-bulk-actions">
                    <button type="button" data-import-select-all><?php echo stridebr_e(stridebr_t('exchange.select_all')); ?></button>
                    <button type="button" data-import-close-selected><?php echo stridebr_e(stridebr_t('exchange.close_selected')); ?></button>
                    <button type="button" class="is-primary" data-import-selected><?php echo stridebr_e(stridebr_t('exchange.import_selected')); ?></button>
                </div>
            </div>
            <div class="exchange-import-list" data-import-list></div>
        </section>

        <section class="exchange-card" id="exportar">
            <div class="exchange-card-heading">
                <div><h2><?php echo stridebr_e(stridebr_t('exchange.export_activities')); ?></h2></div>
            </div>
            <?php if ($registros === []): ?>
                <div class="exchange-empty"><strong><?php echo stridebr_e(stridebr_t('exchange.no_export_activity')); ?></strong><p><?php echo stridebr_e(stridebr_t('exchange.no_export_activity_help')); ?></p><div class="exchange-empty-actions"><a class="primary-button" href="/user/atividades.php?new=1"><?php echo stridebr_e(stridebr_t('progress.log_activity')); ?></a><a class="secondary-button" href="#importar"><?php echo stridebr_e(stridebr_t('schedule.import_file')); ?></a></div></div>
            <?php else: ?>
                <div class="exchange-export-list">
                    <?php foreach ($registros as $registro):
                        $id = (string) $registro['idregistro'];
                        $hasRoute = stridebr_db_bool($registro['rota_disponivel'] ?? false) || stridebr_db_bool($registro['rota_importada_disponivel'] ?? false);
                        $date = new DateTimeImmutable((string) $registro['data_inicio']);
                    ?>
                    <article class="exchange-export-row">
                        <div class="exchange-export-main">
                            <strong data-user-content><?php echo stridebr_e((string) ($registro['titulo'] ?: stridebr_sport_name((string) ($registro['modalidade_slug'] ?? ''), (string) $registro['modalidade_nome']))); ?></strong>
                            <span><?php echo stridebr_e(stridebr_sport_name((string) ($registro['modalidade_slug'] ?? ''), (string) $registro['modalidade_nome'])); ?> · <?php echo stridebr_e(stridebr_format_datetime_short($date)); ?></span>
                        </div>
                        <div class="exchange-export-actions">
                            <?php if ($hasRoute): ?><a href="/user/exportar-atividade.php?id=<?php echo rawurlencode($id); ?>&format=gpx">GPX</a><?php endif; ?>
                            <a href="/user/exportar-atividade.php?id=<?php echo rawurlencode($id); ?>&format=tcx">TCX</a>
                            <a href="/user/exportar-atividade.php?id=<?php echo rawurlencode($id); ?>&format=json">JSON</a>
                            <?php if (stridebr_db_bool($registro['original_disponivel'] ?? false)): ?>
                                <a class="is-source" href="/user/exportar-atividade.php?id=<?php echo rawurlencode($id); ?>&format=original"><?php echo stridebr_e(stridebr_t('exchange.original')); ?> <?php echo strtoupper(stridebr_e((string) ($registro['formato'] ?? ''))); ?></a>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
<script type="application/json" id="activity-import-modalities"><?php echo json_encode($modalidades, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
<script src="<?php echo stridebr_e(stridebr_asset('/assets/js/activity-exchange.js')); ?>"></script>
</body>
</html>
