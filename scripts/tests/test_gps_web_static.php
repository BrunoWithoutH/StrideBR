<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

$page = $read('public/user/gravar-atividade.php');
$js = $read('public/assets/js/gps-recorder.js');
$css = $read('public/assets/css/gps-recorder.css');
$api = $read('public/api/gps-salvar.php');
$helper = $read('src/function/gps_web.php');
$migration = $read('src/database/migrations/20260903_v1_rc.sql');
$presenter = $read('src/function/atividade_presenter.php');
$activitiesJs = $read('public/assets/js/atividades.js');
$activitiesCss = $read('public/assets/css/atividades.css');
$header = $read('src/layout/header.php');
$footer = $read('src/layout/footer.php');
$home = $read('public/home.php');
$activitiesPage = $read('public/user/atividades.php');
$signup = $read('public/signup.php');
$onboardingJs = $read('public/assets/js/onboarding.js');
$privacy = $read('public/pages/legal/privacy.php');
$terms = $read('public/pages/legal/terms.php');
$pt = $read('src/i18n/pt-BR.php');
$en = $read('src/i18n/en.php');

$sportsPos = strpos($signup, 'data-step="0"');
$accountPos = strpos($signup, 'signup-account-step');
$namePos = strpos($signup, 'name="NomeUsuario"');

$checks = [
    'página GPS avisa claramente que web é estimativa' => str_contains($page, "stridebr_t('gps.estimate_title')") && str_contains($page, "stridebr_t('gps.estimate_help')"),
    'revisão deixa distância tempo e elevação editáveis' => str_contains($page, 'data-gps-review-distance') && str_contains($page, 'data-gps-review-duration') && str_contains($page, 'data-gps-review-elevation'),
    'gravação usa watchPosition em alta precisão e sem cache' => str_contains($js, 'navigator.geolocation.watchPosition') && str_contains($js, 'enableHighAccuracy: true') && str_contains($js, 'maximumAge: 0'),
    'GPS web exige HTTPS seguro' => str_contains($js, '!window.isSecureContext') && str_contains($js, "t('gps.https_required')"),
    'filtro considera precisão velocidade jitter e saltos' => str_contains($js, 'cfg.maxAccuracy') && str_contains($js, 'speed > cfg.maxSpeed') && str_contains($js, 'jitterFloor') && str_contains($js, 'pointsRejected'),
    'altitude ausente não é convertida incorretamente para zero' => str_contains($js, 'coords.altitude === null || coords.altitude === undefined'),
    'elevação usa janela de suavização e descarta altitude imprecisa' => str_contains($js, 'state.altitudeWindow.length > 5') && str_contains($js, 'altitudeAccuracy > 35'),
    'GPS mostra qualidade e precisão atual durante atividade' => str_contains($page, 'data-gps-quality') && str_contains($page, 'data-gps-accuracy-large') && str_contains($js, 'accuracyQuality'),
    'gravação persiste em IndexedDB' => str_contains($js, "const DB_NAME = 'stridebr-gps-web'") && str_contains($js, 'indexedDB.open') && str_contains($js, 'idbPut(snapshot)') && str_contains($js, 'queuePersist(state)'),
    'internet offline não destrói gravação e marca upload pendente persistente' => str_contains($js, "t('gps.offline_pending')") && str_contains($js, 'state.pendingUpload = true') && str_contains($js, 'saveRequestedAtMs') && str_contains($js, 'schedulePersist(true)') && str_contains($page, "stridebr_t('gps.offline_help')"),
    'reload e fechamento avisam durante gravação' => str_contains($js, "window.addEventListener('beforeunload'") && str_contains($js, "window.addEventListener('pagehide'"),
    'gravação anterior pode ser retomada ou descartada' => str_contains($page, 'data-gps-resume') && str_contains($page, 'data-gps-discard-saved') && str_contains($js, 'resumeSaved'),
    'gravação atual pode ser cancelada e apagada antes de salvar' => str_contains($page, 'data-gps-discard-current') && str_contains($page, 'data-gps-discard-review') && str_contains($js, 'const discardCurrent = async'),
    'descarte cancela persistência pendente antes de apagar IndexedDB' => str_contains($js, 'window.clearTimeout(persistTimer)') && str_contains($js, 'await persistChain') && str_contains($js, 'await idbDelete()') && str_contains($js, 'const queuePersist = value =>'),
    'gap longo recuperado vira pausa em vez de fingir rastreamento' => str_contains($js, 'if (gap > 15000)') && str_contains($js, "state.status = 'paused'") && str_contains($js, 'state.pauseStartedMs = lastTrackedAt'),
    'amostra lenta visível não é tratada automaticamente como gap' => !str_contains($js, 'if (dt > 15) {\n                state.visibilityGaps++') && str_contains($js, 'const speed = rawDistance / dt'),
    'gap real de visibilidade cria âncora e quebra a linha ao vivo' => str_contains($js, "document.visibilityState !== 'visible'") && str_contains($js, 'state.needsPositionAnchor = state.points.length > 0') && str_contains($js, 'point.gap_before = true') && str_contains($js, 'mapChunksFromPoints'),
    'sanity check final detecta distância incremental absurdamente baixa sem gaps' => str_contains($js, 'const applyDistanceSanityCheck = () =>') && str_contains($js, 'state.visibilityGaps > 0') && str_contains($js, 'derived * .2'),
    'âncora pós-pausa reinicia altitude para não contar subida durante pausa' => str_contains($js, 'state.altitudeWindow = []') && str_contains($js, 'state.lastSmoothAltitude = null'),
    'retomada após pausa não soma deslocamento ocorrido durante pausa' => str_contains($js, 'needsPositionAnchor') && str_contains($js, 'if (last && state.needsPositionAnchor)'),
    'Wake Lock é automático somente durante recording e reaplicado ao voltar visível' => !str_contains($page, 'data-gps-wakelock checked') && str_contains($js, "state.status !== 'recording'") && str_contains($js, "navigator.wakeLock.request('screen')") && str_contains($js, "document.addEventListener('visibilitychange'") && str_contains($js, 'requestWakeLock()') && str_contains($js, 'releaseWakeLock()'),
    'sem Wake Lock existe aviso honesto e a gravação não depende da API' => str_contains($page, 'data-gps-wakelock-fallback') && str_contains($js, "'wakeLock' in navigator") && str_contains($pt, 'Mantenha a tela ligada. Bloquear o aparelho ou sair do Stride pode interromper a gravação.') && str_contains($en, 'Keep the screen on. Locking the device or leaving Stride may interrupt recording.'),
    'bloqueio de controles preserva gravação e exige hold para toque' => str_contains($page, 'data-gps-lock') && str_contains($page, 'data-gps-unlock') && str_contains($js, 'const setControlsLocked = locked =>') && str_contains($js, '(now - unlockHoldStartedAt) / 1200') && str_contains($js, 'event.stopImmediatePropagation()') && str_contains($js, 'event.detail === 0'),
    'página reconhece que background e tela bloqueada podem interromper GPS' => str_contains($page, "stridebr_t('gps.live_warning')") && str_contains($js, 'visibilityGaps'),
    'meta opcional oferece distância tempo e auto-finalização' => str_contains($page, 'value="distance"') && str_contains($page, 'value="time"') && str_contains($page, 'data-gps-autostop'),
    'meta de distância precisa de confirmação contra ruído antes de finalizar' => str_contains($js, 'goalCandidatePointCount') && str_contains($js, 'confirmationMargin') && str_contains($js, 'state.points.length > state.goalCandidatePointCount'),
    'sem meta finalização continua manual' => str_contains($page, 'value="none" checked') && str_contains($page, 'data-gps-finish'),
    'trechos podem ser marcados durante GPS e guardam limites de rota' => str_contains($page, 'data-gps-lap') && str_contains($js, 'start_index:') && str_contains($js, 'end_index:') && str_contains($helper, "'rota_modo' => 'gps'"),
    'atividade sem tiros explícitos mantém métricas do trecho padrão' => str_contains($helper, '\'distance_m\' => max(0.0, $displayDistanceM)') && str_contains($helper, '\'duration_s\' => max(0.0, $displayDurationS)'),
    'limpar elevação na revisão também limpa elevação derivada dos trechos' => str_contains($helper, 'if ($displayElevationM === null)') && str_contains($helper, '$segment[\'elevation_gain_m\'] = null'),
    'segmento vazio no fim não é criado após marcação manual' => str_contains($js, 'force && state.segments.length > 0 && distance < 1 && duration < 2'),
    'backend limita tamanho de pontos e valida coordenadas' => str_contains($helper, 'GPS_WEB_MAX_POINTS = 2000') && str_contains($helper, 'count($points) < 2') && str_contains($helper, '$lat < -90') && str_contains($helper, '$lon < -180'),
    'backend salva pela infraestrutura normal de atividades com origem GPS' => str_contains($api, 'atividadeSalvarRegistro') && str_contains($helper, "'origem' => 'gps'") && str_contains($api, 'gpsWebSaveMetadata'),
    'endpoint GPS usa CSRF e chave estável da gravação para idempotência' => str_contains($api, 'hash_equals(stridebr_csrf_token(), $csrf)') && str_contains($api, 'gpsWebFindExistingRecording') && str_contains($helper, 'function gpsWebRecordingKey') && str_contains($migration, 'chave_gravacao VARCHAR(80) NOT NULL UNIQUE') && str_contains($js, 'recording_id: state.id'),
    'endpoint GPS nunca redireciona sessão expirada e responde JSON estruturado' => str_contains($api, "'code' => 'auth_required'") && str_contains($api, "header('Content-Type: application/json") && !str_contains($api, 'stridebr_require_login'),
    'IndexedDB só é apagado depois de ACK válido com idregistro' => str_contains($js, '!result?.idregistro') && strpos($js, 'await idbDelete()') > strpos($js, '!result?.idregistro'),
    'retry após reconnect/reload usa pendingUpload persistido' => str_contains($js, 'saved.pendingUpload') && str_contains($js, 'state.pendingUpload') && str_contains($js, "window.addEventListener('online'"),
    'elevação ausente pode ser estimada na review e novamente no servidor em fail-open' => str_contains($js, 'estimateTerrainElevationForReview') && str_contains($page, 'data-elevation-endpoint') && str_contains($helper, 'atividadeConsultarElevacao($geojson, 3)') && str_contains($helper, '$elevationSource') && str_contains($helper, 'open-meteo-copernicus'),
    'migration guarda qualidade da gravação e ajustes do usuário' => str_contains($migration, 'CREATE TABLE IF NOT EXISTS gravacoes_gps_web') && str_contains($migration, 'precisao_media_m') && str_contains($migration, 'lacunas_visibilidade') && str_contains($migration, 'usuario_ajustou'),
    'distância medida original e distância final revisada ficam separadas' => str_contains($migration, 'distancia_medida_m') && str_contains($migration, 'distancia_final_m') && str_contains($helper, "'measured_distance_m' => \$measuredDistanceM") && str_contains($helper, "'distancia_metros' => \$displayDistanceM"),
    'detalhe da atividade expõe transparência do GPS Web' => str_contains($presenter, "'gps_web' => \$gpsWeb") && str_contains($activitiesJs, 'const gpsWebDetailHtml') && str_contains($activitiesJs, "tr('activity.gps.browser_estimate')") && str_contains($activitiesCss, '.activity-gps-web-notice'),
    'múltiplos tiros mostram total real da atividade sem usar só o primeiro trecho' => str_contains($presenter, 'function atividadeCardTotaisUnidades') && str_contains($presenter, '$totaisUnidades = atividadeCardTotaisUnidades($detalhes)'),
    'atalho global e página de atividades expõem GPS' => (str_contains($header, "stridebr_t('nav.record_gps')") || str_contains($header, '<strong>Gravar com GPS</strong>')) && (str_contains($activitiesPage, 'activity.quick_run') || str_contains($activitiesPage, 'Corrida rápida')) && (str_contains($activitiesPage, 'activity.record_gps') || str_contains($activitiesPage, 'Gravar com GPS')),
    'home sem treino mantém registro e GPS como próximas ações' => str_contains($home, '/user/atividades.php?new=1') && str_contains($home, '/user/gravar-atividade.php'),
    'navegação mobile reconhece gravador como parte de atividades' => str_contains($footer, "'/user/gravar-atividade.php'") && str_contains($footer, "stridebr_t('nav.activities')"),
    'onboarding/signup aplica IKEA effect antes das credenciais' => $sportsPos !== false && $accountPos !== false && $namePos !== false && $sportsPos < $accountPos && $accountPos <= $namePos && str_contains($signup, "stridebr_t('onboarding.sports_question')") && str_contains($signup, "stridebr_t('auth.skip_personalization')"),
    'onboarding de cadastro é totalmente pulável para a etapa da conta' => str_contains($signup, 'data-skip-to-account') && str_contains($onboardingJs, "root.querySelector('[data-skip-to-account]')"),
    'preferências escolhidas antes da conta são salvas no usuário' => str_contains($signup, "'activity_defaults' => [") && str_contains($signup, 'preferenciasusuario, onboarding_concluido') && str_contains($signup, "'source' => 'signup_before_account'"),
    'cadastro não envia usuário novo para onboarding duplicado' => !str_contains($signup, "header('Location: /user/onboarding.php')"),
    'privacidade e termos explicam precisão e limitações do GPS Web' => str_contains($privacy, 'GPS Web é uma estimativa') && str_contains($privacy, 'mantido localmente no navegador') && str_contains($terms, 'A gravação GPS feita pelo site depende das APIs, sensores e políticas do navegador'),
    'CSS do gravador possui experiência mobile e reduced motion' => preg_match('/@media\s*\(max-width:\s*800px\)/', $css) === 1 && preg_match('/@media\s*\(prefers-reduced-motion:\s*reduce\)/', $css) === 1 && str_contains($css, '.gps-page.is-gps-recording .site-header'),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas no GPS Web/onboarding:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}
echo '✓ GPS Web + onboarding static: ' . count($checks) . " assertions\n";
