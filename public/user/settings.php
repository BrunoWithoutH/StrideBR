<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/includes/errors.php';
require_once dirname(__DIR__, 2) . '/src/includes/app.php';
require_once dirname(__DIR__, 2) . '/src/layout/settings_workspace.php';

$idUsuario = stridebr_require_login();
$settingsView = (string) ($_GET['view'] ?? 'settings');
$settingsView = $settingsView === 'profile' ? 'profile' : 'settings';
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/src/includes/auth.php';
require_once dirname(__DIR__, 2) . '/src/function/atividade_modelo.php';
require_once dirname(__DIR__, 2) . '/src/includes/sport_icons.php';
require_once dirname(__DIR__, 2) . '/src/function/integrations.php';
require_once dirname(__DIR__, 2) . '/src/function/sport_catalog.php';

$sportsCatalogSnapshot = atividadeListarCatalogo($pdo, $idUsuario);
$validSportIds = array_fill_keys(array_map(static fn(array $sport): string => (string) $sport['idmodalidade'], $sportsCatalogSnapshot), true);

$errors = [];
$generos = [
    'Masculino' => stridebr_t('settings.gender_male'),
    'Feminino' => stridebr_t('settings.gender_female'),
    'Não-binário' => stridebr_t('settings.gender_nonbinary'),
    'Agênero' => stridebr_t('settings.gender_agender'),
    'Bigênero' => stridebr_t('settings.gender_bigender'),
    'Gênero fluido' => stridebr_t('settings.gender_fluid'),
    'Prefiro não informar' => stridebr_t('settings.gender_prefer_not'),
    'Outro' => stridebr_t('settings.gender_other'),
];
$socialPlatforms = [
    'instagram' => ['label' => 'Instagram', 'prefix' => 'instagram.com/', 'base' => 'https://instagram.com/', 'placeholder' => 'seuusuario'],
    'tiktok' => ['label' => 'TikTok', 'prefix' => 'tiktok.com/@', 'base' => 'https://tiktok.com/@', 'placeholder' => 'seuusuario'],
    'youtube' => ['label' => 'YouTube', 'prefix' => 'youtube.com/@', 'base' => 'https://youtube.com/@', 'placeholder' => 'seucanal'],
    'strava' => ['label' => 'Strava', 'prefix' => 'strava.com/athletes/', 'base' => 'https://www.strava.com/athletes/', 'placeholder' => 'seu-id'],
    'garmin' => ['label' => 'Garmin Connect', 'prefix' => 'https://', 'base' => 'https://', 'placeholder' => 'connect.garmin.com/modern/profile/...'],
    'polar' => ['label' => 'Polar Flow', 'prefix' => 'https://', 'base' => 'https://', 'placeholder' => 'flow.polar.com/...'],
    'suunto' => ['label' => 'Suunto', 'prefix' => 'https://', 'base' => 'https://', 'placeholder' => 'sports-tracker.com/...'],
    'fitbit' => ['label' => 'Fitbit', 'prefix' => 'https://', 'base' => 'https://', 'placeholder' => 'fitbit.com/user/...'],
    'hevy' => ['label' => 'Hevy', 'prefix' => 'hevy.com/user/', 'base' => 'https://hevy.com/user/', 'placeholder' => 'seuusuario'],
    'github' => ['label' => 'GitHub', 'prefix' => 'github.com/', 'base' => 'https://github.com/', 'placeholder' => 'seuusuario'],
    'website' => ['label' => 'Site / outro link', 'prefix' => 'https://', 'base' => 'https://', 'placeholder' => 'seusite.com.br'],
];
$socialLinksInput = [];
$profileHighlightTypes = ['activities', 'distance', 'duration', 'sports', 'member_since', 'custom', 'none'];
$trainingGoalLabels = [
    'organizar' => stridebr_t('settings.goal_organize'),
    'evolucao' => stridebr_t('settings.goal_progress'),
    'condicionamento' => stridebr_t('settings.goal_conditioning'),
    'prova' => stridebr_t('settings.goal_event'),
    'rotina' => stridebr_t('settings.goal_routine'),
    'lazer' => stridebr_t('settings.goal_leisure'),
];
$allowedTrainingGoals = array_keys($trainingGoalLabels);
$allowedTrainingExperience = ['', 'comecando', 'pratico', 'regular'];
$trainingTrackingChoices = [
    'frequencia' => [stridebr_t('settings.track_consistency'), stridebr_t('settings.track_consistency_help')],
    'duracao' => [stridebr_t('settings.track_duration'), stridebr_t('settings.track_duration_help')],
    'distancia' => [stridebr_t('settings.track_distance'), stridebr_t('settings.track_distance_help')],
    'elevacao' => [stridebr_t('settings.track_elevation'), stridebr_t('settings.track_elevation_help')],
    'metas' => [stridebr_t('settings.track_goals'), stridebr_t('settings.track_goals_help')],
    'carga' => [stridebr_t('settings.track_strength'), stridebr_t('settings.track_strength_help')],
];
$allowedTrainingTracking = array_keys($trainingTrackingChoices);

$socialHandleFromUrl = static function (string $key, string $value) use ($socialPlatforms): string {
    $value = trim($value);
    $base = (string) ($socialPlatforms[$key]['base'] ?? '');
    if ($base !== '' && str_starts_with(stridebr_lower($value), stridebr_lower($base))) return ltrim(substr($value, strlen($base)), '@/');
    return preg_replace('#^https?://#i', '', $value) ?? $value;
};

$photoStmt = $pdo->prepare('SELECT fotousuario, preferenciasusuario, pesousuario FROM usuarios WHERE idusuario = :id LIMIT 1');
$photoStmt->execute([':id' => $idUsuario]);
$currentAssets = $photoStmt->fetch() ?: [];
$currentPhoto = trim((string) ($currentAssets['fotousuario'] ?? ''));
$currentWeight = is_numeric($currentAssets['pesousuario'] ?? null) ? (float) $currentAssets['pesousuario'] : null;
$currentPreferences = is_array($currentAssets['preferenciasusuario'] ?? null) ? $currentAssets['preferenciasusuario'] : (json_decode((string) ($currentAssets['preferenciasusuario'] ?? '{}'), true) ?: []);
$currentBanner = is_array($currentPreferences['profile_banner'] ?? null) ? $currentPreferences['profile_banner'] : [];
$currentBannerPath = trim((string) ($currentBanner['image'] ?? ''));
$currentBannerColor = strtolower(trim((string) ($currentBanner['color'] ?? '#1d3150')));
if (preg_match('/^#[0-9a-f]{6}$/', $currentBannerColor) !== 1) $currentBannerColor = '#1d3150';
$pendingAvatar = null;
$pendingBanner = null;
$removeAvatar = false;
$removeBanner = false;
$publicRoot = dirname(__DIR__);
$avatarDirectory = $publicRoot . '/uploads/avatars';
$bannerDirectory = $publicRoot . '/uploads/profile-banners';

$deleteLocalAvatar = static function (string $publicPath) use ($publicRoot): void {
    if (preg_match('#^/uploads/avatars/([a-f0-9]{32})\.(?:jpg|jpeg|png|webp)$#i', $publicPath, $match) !== 1) {
        return;
    }
    $diskPath = $publicRoot . $publicPath;
    if (is_file($diskPath)) {
        @unlink($diskPath);
    }
    foreach (glob($publicRoot . '/uploads/avatars/cache/' . strtolower($match[1]) . '-*.webp') ?: [] as $variant) {
        if (is_file($variant)) @unlink($variant);
    }
};

$deleteLocalBanner = static function (string $publicPath) use ($publicRoot): void {
    if (preg_match('#^/uploads/profile-banners/[a-f0-9]{32}\.(?:jpg|jpeg|png|webp)$#i', $publicPath) !== 1) return;
    $diskPath = $publicRoot . $publicPath;
    if (is_file($diskPath)) @unlink($diskPath);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    stridebr_verify_csrf();
    $nome = stridebr_person_name_normalize((string) ($_POST['nomeusuario'] ?? ''));
    $nomeExibicao = stridebr_person_name_normalize((string) ($_POST['nome_exibicao'] ?? ''));
    $username = stridebr_lower(trim((string) ($_POST['username'] ?? '')));
    $fone = trim((string) ($_POST['foneusuario'] ?? ''));
    $nascimento = trim((string) ($_POST['datanascimentousuario'] ?? ''));
    $genero = trim((string) ($_POST['generousuario'] ?? ''));
    $pronomes = trim((string) ($_POST['pronomesusuario'] ?? ''));
    $bio = trim((string) ($_POST['biousuario'] ?? ''));
    $pesoRaw = str_replace(',', '.', trim((string) ($_POST['pesousuario'] ?? '')));
    $historicalWeightDate = trim((string) ($_POST['historico_peso_data'] ?? ''));
    $historicalWeightRaw = str_replace(',', '.', trim((string) ($_POST['historico_peso_kg'] ?? '')));
    $alturaRaw = trim((string) ($_POST['alturausuario'] ?? ''));
    $objetivo = trim((string) ($_POST['objetivousuario'] ?? ''));
    $visibilidade = (string) ($_POST['visibilidadeperfil'] ?? 'privado');
    $descobrivel = isset($_POST['descobrivel']);
    $units = (string) ($_POST['units'] ?? 'metric');
    $weekStart = (string) ($_POST['week_start'] ?? 'sunday');
    $localePreference = stridebr_normalize_locale_mode((string) ($_POST['locale'] ?? ($currentPreferences['locale'] ?? 'auto')));
    $themePreference = stridebr_theme_normalize((string) ($_POST['theme'] ?? stridebr_theme()));
    $activityVisibility = (string) ($_POST['activity_visibility'] ?? 'privado');
    $hideRouteStart = max(0, min(10000, (int) ($_POST['hide_route_start_m'] ?? 0)));
    $hideRouteEnd = max(0, min(10000, (int) ($_POST['hide_route_end_m'] ?? $hideRouteStart)));
    $productAnalytics = isset($_POST['product_analytics']);
    $trainingGoals = array_values(array_intersect($allowedTrainingGoals, is_array($_POST['training_goals'] ?? null) ? array_map('strval', $_POST['training_goals']) : []));
    $trainingExperience = trim((string) ($_POST['training_experience'] ?? ''));
    $trainingWeeklyFrequency = max(0, min(7, (int) ($_POST['training_weekly_frequency'] ?? 0)));
    $trainingTracking = array_values(array_intersect($allowedTrainingTracking, is_array($_POST['training_tracking'] ?? null) ? array_map('strval', $_POST['training_tracking']) : []));
    $bannerColor = strtolower(trim((string) ($_POST['profile_banner_color'] ?? $currentBannerColor)));
    $removeBanner = isset($_POST['remove_profile_banner']);
    $highlightTypesInput = array_values((array) ($_POST['profile_highlight_type'] ?? []));
    $highlightLabelsInput = array_values((array) ($_POST['profile_highlight_label'] ?? []));
    $highlightValuesInput = array_values((array) ($_POST['profile_highlight_value'] ?? []));
    $profileHighlightsInput = [];
    for ($highlightIndex = 0; $highlightIndex < 4; $highlightIndex++) {
        $type = (string) ($highlightTypesInput[$highlightIndex] ?? 'none');
        if (!in_array($type, $profileHighlightTypes, true) || $type === 'none') continue;
        if ($type === 'custom') {
            $label = trim((string) ($highlightLabelsInput[$highlightIndex] ?? ''));
            $value = trim((string) ($highlightValuesInput[$highlightIndex] ?? ''));
            if ($label === '' || $value === '') continue;
            $profileHighlightsInput[] = ['type' => 'custom', 'label' => $label, 'value' => $value];
        } else {
            $profileHighlightsInput[] = ['type' => $type];
        }
    }
    foreach ($socialPlatforms as $socialKey => $socialMeta) {
        $socialValue = trim((string) ($_POST['social_' . $socialKey] ?? ''));
        $socialValue = $socialHandleFromUrl($socialKey, $socialValue);
        $socialValue = ltrim($socialValue, $socialKey === 'website' ? '/' : '@/');
        if ($socialValue !== '') $socialLinksInput[$socialKey] = (string) $socialMeta['base'] . $socialValue;
    }
    $removeAvatar = isset($_POST['remover_foto']);
    $sportsPreferencesPresent = isset($_POST['sports_preferences_present']);
    $sportsPractice = array_values(array_unique(array_filter(array_map('strval', (array) ($_POST['sports_practice'] ?? [])))));
    $sportsFavorites = array_values(array_unique(array_filter(array_map('strval', (array) ($_POST['sports_favorite'] ?? [])))));
    $avatarUpload = $_FILES['fotousuario'] ?? null;
    if (is_array($avatarUpload) && (int) ($avatarUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadError = (int) ($avatarUpload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = stridebr_t('settings.avatar_upload_error');
        } elseif ((int) ($avatarUpload['size'] ?? 0) > 4 * 1024 * 1024) {
            $errors[] = stridebr_t('settings.avatar_size_error');
        } else {
            $tmpPath = (string) ($avatarUpload['tmp_name'] ?? '');
            $imageInfo = $tmpPath !== '' ? @getimagesize($tmpPath) : false;
            $mime = '';
            if ($tmpPath !== '' && class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file($tmpPath);
            }
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if ($imageInfo === false || !isset($allowedMimes[$mime])) {
                $errors[] = stridebr_t('settings.avatar_type_error');
            } elseif ((int) ($imageInfo[0] ?? 0) < 1 || (int) ($imageInfo[1] ?? 0) < 1 || (int) ($imageInfo[0] ?? 0) > 5000 || (int) ($imageInfo[1] ?? 0) > 5000) {
                $errors[] = stridebr_t('settings.avatar_dimensions_error');
            } else {
                $pendingAvatar = ['tmp' => $tmpPath, 'mime' => $mime];
                $removeAvatar = false;
            }
        }
    }

    $bannerUpload = $_FILES['profile_banner_image'] ?? null;
    if (is_array($bannerUpload) && (int) ($bannerUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadError = (int) ($bannerUpload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $errors[] = stridebr_t('settings.banner_upload_error');
        } elseif ((int) ($bannerUpload['size'] ?? 0) > 6 * 1024 * 1024) {
            $errors[] = stridebr_t('settings.banner_size_error');
        } else {
            $tmpPath = (string) ($bannerUpload['tmp_name'] ?? '');
            $imageInfo = $tmpPath !== '' ? @getimagesize($tmpPath) : false;
            $mime = '';
            if ($tmpPath !== '' && class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file($tmpPath);
            }
            if ($imageInfo === false || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $errors[] = stridebr_t('settings.banner_type_error');
            } elseif ((int) ($imageInfo[0] ?? 0) > 7000 || (int) ($imageInfo[1] ?? 0) > 7000) {
                $errors[] = stridebr_t('settings.banner_dimensions_error');
            } else {
                $pendingBanner = ['tmp' => $tmpPath, 'mime' => $mime];
                $removeBanner = false;
            }
        }
    }

    if (!in_array($trainingExperience, $allowedTrainingExperience, true)) $errors[] = stridebr_t('settings.invalid_experience');
    if (!stridebr_person_name_is_valid($nome, 80)) $errors[] = stridebr_t('settings.invalid_name');
    if (!stridebr_person_name_is_valid($nomeExibicao, 60)) $errors[] = stridebr_t('settings.invalid_display_name');
    if ($username !== '' && !stridebr_username_is_valid($username)) $errors[] = stridebr_t('settings.invalid_username');
    if ($fone !== '' && stridebr_length($fone) > 20) $errors[] = stridebr_t('settings.phone_too_long');
    if ($nascimento !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $nascimento);
        if (!$date || $date->format('Y-m-d') !== $nascimento || $date > new DateTimeImmutable('today')) $errors[] = stridebr_t('settings.invalid_birthdate');
    }
    if ($genero !== '' && !array_key_exists($genero, $generos)) $errors[] = stridebr_t('settings.invalid_gender');
    if (stridebr_length($pronomes) > 30) $errors[] = 'Pronomes muito longos.';
    if (stridebr_length($bio) > 1000) $errors[] = stridebr_t('settings.bio_too_long');
    if (stridebr_length($objetivo) > 2000) $errors[] = stridebr_t('settings.notes_too_long');
    if ($pesoRaw !== '' && (!is_numeric($pesoRaw) || (float) $pesoRaw <= 0 || (float) $pesoRaw > 9999)) $errors[] = stridebr_t('settings.invalid_weight');
    if (($historicalWeightDate === '') !== ($historicalWeightRaw === '')) $errors[] = 'Para adicionar um peso anterior, informe a data e o peso.';
    if ($historicalWeightRaw !== '' && (!is_numeric($historicalWeightRaw) || (float) $historicalWeightRaw <= 0 || (float) $historicalWeightRaw > 9999)) $errors[] = stridebr_t('settings.invalid_previous_weight');
    if ($historicalWeightDate !== '') {
        $historicalDate = DateTimeImmutable::createFromFormat('!Y-m-d', $historicalWeightDate);
        if (!$historicalDate || $historicalDate->format('Y-m-d') !== $historicalWeightDate || $historicalDate >= new DateTimeImmutable('today')) $errors[] = stridebr_t('settings.invalid_previous_weight_date');
    }
    if ($alturaRaw !== '' && (filter_var($alturaRaw, FILTER_VALIDATE_INT) === false || (int) $alturaRaw <= 0 || (int) $alturaRaw > 300)) $errors[] = stridebr_t('settings.invalid_height');
    if (!in_array($visibilidade, ['privado', 'amigos', 'publico'], true)) $errors[] = stridebr_t('settings.invalid_profile_privacy');
    if (!in_array($activityVisibility, ['privado', 'amigos', 'publico'], true)) $errors[] = stridebr_t('settings.invalid_activity_privacy');
    if (!in_array($units, ['metric', 'imperial'], true)) $errors[] = stridebr_t('settings.invalid_units');
    if (!in_array($weekStart, ['sunday', 'monday'], true)) $errors[] = stridebr_t('settings.invalid_week_start');
    if (!in_array($localePreference, stridebr_supported_locale_modes(), true)) $errors[] = stridebr_t('settings.invalid_language');
    if (!in_array($themePreference, ['light', 'dark'], true)) $errors[] = stridebr_t('settings.invalid_theme');
    if (preg_match('/^#[0-9a-f]{6}$/', $bannerColor) !== 1) $errors[] = stridebr_t('settings.invalid_banner_color');
    foreach ($profileHighlightsInput as $highlight) {
        if (($highlight['type'] ?? '') === 'custom' && (stridebr_length((string) ($highlight['label'] ?? '')) > 32 || stridebr_length((string) ($highlight['value'] ?? '')) > 40)) {
            $errors[] = stridebr_t('settings.invalid_highlight');
            break;
        }
    }
    foreach ($socialLinksInput as $socialKey => $socialUrl) {
        if (strlen($socialUrl) > 500 || filter_var($socialUrl, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string) parse_url($socialUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $errors[] = stridebr_t('settings.invalid_social_links');
            break;
        }
    }
    if ($sportsPreferencesPresent) {
        foreach (array_unique(array_merge($sportsPractice, $sportsFavorites)) as $sportId) {
            if (!isset($validSportIds[$sportId])) {
                $errors[] = stridebr_t('settings.invalid_sport');
                break;
            }
        }
    }

    if ($errors === [] && $username !== '') {
        $check = $pdo->prepare('SELECT 1 FROM usuarios WHERE lower(username) = lower(:username) AND idusuario <> :id LIMIT 1');
        $check->execute([':username' => $username, ':id' => $idUsuario]);
        if ($check->fetchColumn()) $errors[] = stridebr_t('settings.username_taken');
    }

    if ($errors === []) {
        $photoPath = $removeAvatar ? null : ($currentPhoto !== '' ? $currentPhoto : null);
        $previousLocalPhoto = $currentPhoto;
        $newLocalPhoto = null;
        if (is_array($pendingAvatar)) {
            if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0755, true) && !is_dir($avatarDirectory)) {
                $errors[] = stridebr_t('settings.avatar_dir_error');
            } else {
                $avatarId = bin2hex(random_bytes(16));
                $filename = $avatarId . '.webp';
                $destination = $avatarDirectory . '/' . $filename;
                if (stridebr_image_square_webp((string) $pendingAvatar['tmp'], $destination, 320, 82)) {
                    @chmod($destination, 0644);
                    $photoPath = '/uploads/avatars/' . $filename;
                    $newLocalPhoto = $photoPath;
                } else {
                    $fallbackExtension = match ((string) ($pendingAvatar['mime'] ?? '')) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        default => 'webp',
                    };
                    $fallbackName = $avatarId . '.' . $fallbackExtension;
                    $fallbackDestination = $avatarDirectory . '/' . $fallbackName;
                    if (!move_uploaded_file((string) $pendingAvatar['tmp'], $fallbackDestination)) {
                        $errors[] = stridebr_t('settings.avatar_save_error');
                    } else {
                        @chmod($fallbackDestination, 0644);
                        $photoPath = '/uploads/avatars/' . $fallbackName;
                        $newLocalPhoto = $photoPath;
                    }
                }
            }
        }

        $bannerPath = $removeBanner ? null : ($currentBannerPath !== '' ? $currentBannerPath : null);
        $previousLocalBanner = $currentBannerPath;
        $newLocalBanner = null;
        if ($errors === [] && is_array($pendingBanner)) {
            if (!is_dir($bannerDirectory) && !mkdir($bannerDirectory, 0755, true) && !is_dir($bannerDirectory)) {
                $errors[] = stridebr_t('settings.banner_dir_error');
            } else {
                $bannerId = bin2hex(random_bytes(16));
                $destination = $bannerDirectory . '/' . $bannerId . '.webp';
                if (stridebr_image_webp_fit((string) $pendingBanner['tmp'], $destination, 1800, 900, 82)) {
                    @chmod($destination, 0644);
                    $bannerPath = '/uploads/profile-banners/' . $bannerId . '.webp';
                    $newLocalBanner = $bannerPath;
                } else {
                    $fallbackExtension = match ((string) ($pendingBanner['mime'] ?? '')) { 'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'webp' };
                    $fallbackDestination = $bannerDirectory . '/' . $bannerId . '.' . $fallbackExtension;
                    if (!move_uploaded_file((string) $pendingBanner['tmp'], $fallbackDestination)) {
                        $errors[] = stridebr_t('settings.banner_save_error');
                    } else {
                        @chmod($fallbackDestination, 0644);
                        $bannerPath = '/uploads/profile-banners/' . $bannerId . '.' . $fallbackExtension;
                        $newLocalBanner = $bannerPath;
                    }
                }
            }
        }

        if ($errors !== []) {
            if ($newLocalPhoto !== null && $newLocalPhoto !== $previousLocalPhoto) $deleteLocalAvatar($newLocalPhoto);
            if ($newLocalBanner !== null && $newLocalBanner !== $previousLocalBanner) $deleteLocalBanner($newLocalBanner);
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();

                $prefStmt = $pdo->prepare('SELECT preferenciasusuario FROM usuarios WHERE idusuario = :id FOR UPDATE');
                $prefStmt->execute([':id' => $idUsuario]);
                $rawPrefs = $prefStmt->fetchColumn();
                $preferences = is_array($rawPrefs) ? $rawPrefs : (json_decode((string) $rawPrefs, true) ?: []);
                $preferences['units'] = $units;
                $preferences['week_start'] = $weekStart;
                $preferences['locale'] = $localePreference;
                $preferences['theme'] = $themePreference;
                $preferences['activity_defaults'] = ['visibility' => $activityVisibility, 'hide_route_start_m' => $hideRouteStart, 'hide_route_end_m' => $hideRouteEnd];
                $preferences['goals'] = $trainingGoals;
                $preferences['experience'] = $trainingExperience;
                $preferences['weekly_frequency'] = $trainingWeeklyFrequency > 0 ? $trainingWeeklyFrequency : null;
                $preferences['tracking'] = $trainingTracking;
                $preferences['product_analytics'] = $productAnalytics;
                $preferences['social_links'] = $socialLinksInput;
                $preferences['profile_banner'] = ['color' => $bannerColor, 'image' => $bannerPath];
                $preferences['profile_highlights'] = $profileHighlightsInput;

                $stmt = $pdo->prepare('UPDATE usuarios SET nomeusuario = :nome, nome_exibicao = :display, username = :username, fotousuario = :foto, foneusuario = :fone, datanascimentousuario = :nascimento, generousuario = :genero, pronomesusuario = :pronomes, biousuario = :bio, pesousuario = :peso, alturausuario = :altura, objetivousuario = :objetivo, visibilidadeperfil = :visibilidade, descobrivel = :descobrivel, preferenciasusuario = CAST(:preferencias AS jsonb) WHERE idusuario = :id');
                $stmt->bindValue(':nome', $nome);
                $stmt->bindValue(':display', $nomeExibicao);
                $stmt->bindValue(':username', $username !== '' ? $username : null, $username !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':foto', $photoPath, $photoPath !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':fone', $fone !== '' ? $fone : null, $fone !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':nascimento', $nascimento !== '' ? $nascimento : null, $nascimento !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':genero', $genero !== '' ? $genero : null, $genero !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':pronomes', $pronomes !== '' ? $pronomes : null, $pronomes !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':bio', $bio !== '' ? $bio : null, $bio !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':peso', $pesoRaw !== '' ? $pesoRaw : null, $pesoRaw !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':altura', $alturaRaw !== '' ? (int) $alturaRaw : null, $alturaRaw !== '' ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $stmt->bindValue(':objetivo', $objetivo !== '' ? $objetivo : null, $objetivo !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                $stmt->bindValue(':visibilidade', $visibilidade);
                $stmt->bindValue(':descobrivel', $descobrivel, PDO::PARAM_BOOL);
                $stmt->bindValue(':preferencias', json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $stmt->bindValue(':id', $idUsuario);
                $stmt->execute();

                if ($sportsPreferencesPresent) {
                    atividadeAtualizarEsportesUsuario($pdo, $idUsuario, $sportsPractice, $sportsFavorites);
                }
                if ($pesoRaw !== '' && stridebr_db_table_exists($pdo, 'historico_peso_usuario')) {
                    $newWeight = (float) $pesoRaw;
                    if ($currentWeight === null || abs($newWeight - $currentWeight) >= 0.01) {
                        $weightHistory = $pdo->prepare("INSERT INTO historico_peso_usuario (idpesagem, idusuario, peso_kg, data_medicao, origem) VALUES (:id, :usuario, :peso, CURRENT_DATE, 'perfil') ON CONFLICT (idusuario, data_medicao) DO UPDATE SET peso_kg = EXCLUDED.peso_kg, origem = 'perfil'");
                        $weightHistory->execute([':id' => stridebr_generate_id(), ':usuario' => $idUsuario, ':peso' => number_format($newWeight, 2, '.', '')]);
                    }
                }
                if ($historicalWeightDate !== '' && $historicalWeightRaw !== '' && stridebr_db_table_exists($pdo, 'historico_peso_usuario')) {
                    $weightHistory = $pdo->prepare("INSERT INTO historico_peso_usuario (idpesagem, idusuario, peso_kg, data_medicao, origem) VALUES (:id, :usuario, :peso, :data, 'manual') ON CONFLICT (idusuario, data_medicao) DO UPDATE SET peso_kg = EXCLUDED.peso_kg, origem = 'manual'");
                    $weightHistory->execute([':id' => stridebr_generate_id(), ':usuario' => $idUsuario, ':peso' => number_format((float) $historicalWeightRaw, 2, '.', ''), ':data' => $historicalWeightDate]);
                }

                $pdo->commit();

                $_SESSION['NomeUsuario'] = $nome;
                $_SESSION['NomeExibicao'] = $nomeExibicao;
                $_SESSION['Username'] = $username !== '' ? $username : null;
                $_SESSION['FotoUsuario'] = $photoPath ?? '';
                stridebr_set_locale_preference($localePreference);
                stridebr_set_theme($themePreference);
                if ($previousLocalPhoto !== '' && $previousLocalPhoto !== ($photoPath ?? '')) {
                    $deleteLocalAvatar($previousLocalPhoto);
                }
                if ($previousLocalBanner !== '' && $previousLocalBanner !== ($bannerPath ?? '')) {
                    $deleteLocalBanner($previousLocalBanner);
                }
                stridebr_flash('success', stridebr_t('settings.saved'));
                header('Location: /user/settings.php');
                exit;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($newLocalPhoto !== null && $newLocalPhoto !== $previousLocalPhoto) {
                    $deleteLocalAvatar($newLocalPhoto);
                }
                if ($newLocalBanner !== null && $newLocalBanner !== $previousLocalBanner) {
                    $deleteLocalBanner($newLocalBanner);
                }
                error_log('StrideBR settings save failed for user ' . $idUsuario . ': ' . $error->getMessage());
                $errors[] = stridebr_t('settings.save_error');
            }
        }
    }

}

$stmt = $pdo->prepare('SELECT nomeusuario, nome_exibicao, username, fotousuario, papelusuario, modo_treinador, descobrivel, emailusuario, verificado, email_verificado_em, termos_versao, privacidade_versao, foneusuario, datanascimentousuario, generousuario, pronomesusuario, biousuario, pesousuario, alturausuario, objetivousuario, visibilidadeperfil, preferenciasusuario FROM usuarios WHERE idusuario = :id LIMIT 1');
$stmt->execute([':id' => $idUsuario]);
$usuario = $stmt->fetch();
if (!$usuario) { stridebr_error_document(404); }
$preferences = is_array($usuario['preferenciasusuario'] ?? null) ? $usuario['preferenciasusuario'] : (json_decode((string) ($usuario['preferenciasusuario'] ?? '{}'), true) ?: []);
$activityDefaultsSettings = is_array($preferences['activity_defaults'] ?? null) ? $preferences['activity_defaults'] : [];
$activityVisibilitySettings = in_array((string) ($activityDefaultsSettings['visibility'] ?? 'privado'), ['privado', 'amigos', 'publico'], true) ? (string) ($activityDefaultsSettings['visibility'] ?? 'privado') : 'privado';
$hideRouteStartSettings = max(0, min(10000, (int) ($activityDefaultsSettings['hide_route_start_m'] ?? 0)));
$hideRouteEndSettings = max(0, min(10000, (int) ($activityDefaultsSettings['hide_route_end_m'] ?? $hideRouteStartSettings)));
$productAnalyticsSettings = !isset($preferences['product_analytics']) || stridebr_db_bool($preferences['product_analytics']);
$trainingGoalsSettings = is_array($preferences['goals'] ?? null) ? array_values(array_intersect($allowedTrainingGoals, array_map('strval', $preferences['goals']))) : [];
$trainingExperienceSettings = in_array((string) ($preferences['experience'] ?? ''), $allowedTrainingExperience, true) ? (string) ($preferences['experience'] ?? '') : '';
$trainingWeeklyFrequencySettings = max(0, min(7, (int) ($preferences['weekly_frequency'] ?? 0)));
$trainingTrackingSettings = is_array($preferences['tracking'] ?? null) ? array_values(array_intersect($allowedTrainingTracking, array_map('strval', $preferences['tracking']))) : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []) {
    $usuario['nomeusuario'] = $nome ?? $usuario['nomeusuario'];
    $usuario['nome_exibicao'] = $nomeExibicao ?? $usuario['nome_exibicao'];
    $usuario['username'] = $username ?? $usuario['username'];
    $usuario['foneusuario'] = $fone ?? $usuario['foneusuario'];
    $usuario['datanascimentousuario'] = $nascimento ?? $usuario['datanascimentousuario'];
    $usuario['generousuario'] = $genero ?? $usuario['generousuario'];
    $usuario['pronomesusuario'] = $pronomes ?? $usuario['pronomesusuario'];
    $usuario['biousuario'] = $bio ?? $usuario['biousuario'];
    $usuario['pesousuario'] = $pesoRaw ?? $usuario['pesousuario'];
    $usuario['alturausuario'] = $alturaRaw ?? $usuario['alturausuario'];
    $usuario['objetivousuario'] = $objetivo ?? $usuario['objetivousuario'];
    $usuario['visibilidadeperfil'] = $visibilidade ?? $usuario['visibilidadeperfil'];
    $activityVisibilitySettings = $activityVisibility ?? $activityVisibilitySettings;
    $hideRouteStartSettings = $hideRouteStart ?? $hideRouteStartSettings;
    $hideRouteEndSettings = $hideRouteEnd ?? $hideRouteEndSettings;
    $productAnalyticsSettings = $productAnalytics ?? $productAnalyticsSettings;
    $trainingGoalsSettings = $trainingGoals ?? $trainingGoalsSettings;
    $trainingExperienceSettings = $trainingExperience ?? $trainingExperienceSettings;
    $trainingWeeklyFrequencySettings = $trainingWeeklyFrequency ?? $trainingWeeklyFrequencySettings;
    $trainingTrackingSettings = $trainingTracking ?? $trainingTrackingSettings;
    $usuario['descobrivel'] = $descobrivel ?? $usuario['descobrivel'];
    $preferences['units'] = $units ?? ($preferences['units'] ?? 'metric');
    $preferences['week_start'] = $weekStart ?? ($preferences['week_start'] ?? 'sunday');
    $preferences['locale'] = $localePreference ?? ($preferences['locale'] ?? 'auto');
    $preferences['theme'] = $themePreference ?? ($preferences['theme'] ?? stridebr_theme());
    $preferences['social_links'] = $socialLinksInput;
    $preferences['profile_banner'] = ['color' => $bannerColor ?? $currentBannerColor, 'image' => $currentBannerPath !== '' ? $currentBannerPath : null];
    $preferences['profile_highlights'] = $profileHighlightsInput ?? [];
}
$socialLinks = is_array($preferences['social_links'] ?? null) ? $preferences['social_links'] : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []) $socialLinks = $socialLinksInput;
$sportsCatalog = atividadeListarCatalogo($pdo, $idUsuario);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== [] && isset($sportsPreferencesPresent) && $sportsPreferencesPresent) {
    $submittedPracticeSet = array_fill_keys($sportsPractice, true);
    $submittedFavoriteSet = array_fill_keys($sportsFavorites, true);
    foreach ($sportsCatalog as &$sport) {
        $id = (string) $sport['idmodalidade'];
        $sport['pratica'] = isset($submittedPracticeSet[$id]);
        $sport['favorita'] = isset($submittedFavoriteSet[$id]);
    }
    unset($sport);
}
$settingsSportGroups = sportCatalogGroups($sportsCatalog);
$profileBannerSettings = is_array($preferences['profile_banner'] ?? null) ? $preferences['profile_banner'] : [];
$profileBannerColor = strtolower(trim((string) ($profileBannerSettings['color'] ?? '#1d3150')));
if (preg_match('/^#[0-9a-f]{6}$/', $profileBannerColor) !== 1) $profileBannerColor = '#1d3150';
$profileBannerImage = trim((string) ($profileBannerSettings['image'] ?? ''));
$profileHighlightsSettings = is_array($preferences['profile_highlights'] ?? null) ? array_values($preferences['profile_highlights']) : [];
if ($profileHighlightsSettings === []) {
    $profileHighlightsSettings = [['type' => 'activities'], ['type' => 'distance'], ['type' => 'duration'], ['type' => 'sports']];
}
while (count($profileHighlightsSettings) < 4) $profileHighlightsSettings[] = ['type' => 'none'];
$profileHighlightsSettings = array_slice($profileHighlightsSettings, 0, 4);
$weightHistoryRows = [];
if (stridebr_db_table_exists($pdo, 'historico_peso_usuario')) {
    $weightHistoryStmt = $pdo->prepare('SELECT peso_kg, data_medicao, origem FROM historico_peso_usuario WHERE idusuario = :usuario ORDER BY data_medicao DESC LIMIT 10');
    $weightHistoryStmt->execute([':usuario' => $idUsuario]);
    $weightHistoryRows = $weightHistoryStmt->fetchAll();
}
$integrationRegistry = stridebr_integrations_registry();
$integrationConnections = stridebr_integrations_list($pdo, $idUsuario);
$integrationSyncReady = ['strava' => true, 'polar' => true, 'fitbit' => true, 'suunto' => true];
$profilePhoto = stridebr_profile_photo_url((string) ($usuario['fotousuario'] ?? ''));
$profileBackUrl = trim((string) ($usuario['username'] ?? '')) !== '' ? '/u/' . rawurlencode((string) $usuario['username']) : '/';
$roleLabel = stridebr_role_label((string) ($usuario['papelusuario'] ?? 'user'));
$flashes = stridebr_take_flashes();
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('stridebr_html_lang') ? stridebr_e(stridebr_html_lang()) : 'pt-BR'; ?>">
<head>
    <?php if (function_exists('stridebr_ui_boot_script')) echo stridebr_ui_boot_script(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?php echo stridebr_e(stridebr_asset('/assets/img/favicon/favicon.png')); ?>">
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/style.css')); ?>">
    <title><?php echo stridebr_e(stridebr_t($settingsView === 'profile' ? 'settings.page_profile' : 'settings.page_preferences')); ?> | StrideBR</title>
    <link rel="stylesheet" href="<?php echo stridebr_e(stridebr_asset('/assets/css/ui-refresh.css')); ?>">
</head>
<body>
<div class="container-fluid">
    <?php require dirname(__DIR__, 2) . '/src/layout/header.php'; ?>
    <main class="main-content">
        <div class="page-shell settings-shell">
            <?php stridebr_settings_workspace_heading(stridebr_t($settingsView === 'profile' ? 'settings.page_profile' : 'settings.page_preferences'), stridebr_t($settingsView === 'profile' ? 'settings.profile_heading_help' : 'settings.preferences_heading_help'), $profileBackUrl); ?>
            <?php stridebr_settings_workspace_navigation($settingsView === 'profile' ? 'profile' : 'preferences'); ?>
            <?php foreach ($flashes as $flash): ?><div class="alert alert-<?php echo stridebr_e($flash['type'] ?? 'info'); ?>"><?php echo stridebr_e($flash['message'] ?? ''); ?></div><?php endforeach; ?>
            <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?php echo stridebr_e($error); ?></div><?php endforeach; ?>
            <form method="POST" enctype="multipart/form-data" class="content-card settings-form settings-view-<?php echo stridebr_e($settingsView); ?>">
                <?php echo stridebr_csrf_field(); ?>
                <section class="settings-section settings-appearance-section" data-settings-area="profile">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.profile_appearance')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.cover_help')); ?></p></div>
                    <div class="settings-banner-editor">
                        <div class="settings-banner-preview" data-banner-preview style="--settings-banner-color: <?php echo stridebr_e($profileBannerColor); ?>;<?php echo $profileBannerImage !== '' ? ' --settings-banner-image: url(&quot;' . stridebr_e($profileBannerImage) . '&quot;);' : ''; ?>">
                            <img src="<?php echo stridebr_e($profilePhoto); ?>" alt="" width="72" height="72">
                            <div><strong><?php echo stridebr_e($usuario['nome_exibicao'] ?: $usuario['nomeusuario']); ?></strong><span>@<?php echo stridebr_e((string) ($usuario['username'] ?? 'usuario')); ?></span></div>
                        </div>
                        <div class="settings-banner-controls">
                            <label class="settings-color-field"><?php echo stridebr_e(stridebr_t('settings.base_color')); ?><input type="color" name="profile_banner_color" value="<?php echo stridebr_e($profileBannerColor); ?>" data-banner-color></label>
                            <label class="settings-banner-picker"><span><?php echo stridebr_e(stridebr_t('settings.banner_image')); ?></span><input type="file" name="profile_banner_image" accept="image/jpeg,image/png,image/webp" data-banner-input><small data-banner-status><?php echo stridebr_e(stridebr_t('settings.image_6mb')); ?></small></label>
                            <?php if ($profileBannerImage !== ''): ?><label class="settings-banner-remove"><input type="checkbox" name="remove_profile_banner" value="1"> <?php echo stridebr_e(stridebr_t('settings.remove_banner')); ?></label><?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="settings-section" data-settings-area="profile">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.identity')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.identity_help')); ?></p></div>
                    <?php
                    $profilePhoto = stridebr_profile_photo_url((string) ($usuario['fotousuario'] ?? ''));
                    $roleLabel = stridebr_role_label((string) ($usuario['papelusuario'] ?? 'user'));
                    ?>
                    <div class="settings-avatar-row">
                        <img src="<?php echo stridebr_e($profilePhoto); ?>" alt="<?php echo stridebr_e(stridebr_t('settings.current_profile_photo')); ?>" class="settings-avatar-preview" data-avatar-preview width="96" height="96" decoding="async">
                        <div class="settings-avatar-controls">
                            <label class="settings-avatar-picker"><?php echo stridebr_e(stridebr_t('settings.change_photo')); ?><input type="file" name="fotousuario" accept="image/jpeg,image/png,image/webp" data-avatar-input></label>
                            <small data-avatar-status aria-live="polite"><?php echo stridebr_e(stridebr_t('settings.image_4mb')); ?></small>
                            <?php if (!empty($usuario['fotousuario'])): ?>
                                <label class="settings-avatar-remove"><input type="checkbox" name="remover_foto" value="1"> <?php echo stridebr_e(stridebr_t('settings.remove_photo')); ?></label>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="settings-grid">
                        <label><?php echo stridebr_e(stridebr_t('settings.registered_name')); ?><input type="text" name="nomeusuario" maxlength="80" value="<?php echo stridebr_e($usuario['nomeusuario']); ?>" required></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.display_name')); ?><input type="text" name="nome_exibicao" maxlength="60" value="<?php echo stridebr_e($usuario['nome_exibicao'] ?: $usuario['nomeusuario']); ?>" required></label>
                        <label>@username<input type="text" name="username" maxlength="40" autocapitalize="none" spellcheck="false" value="<?php echo stridebr_e($usuario['username'] ?? ''); ?>" placeholder="nome-de-usuario"></label>
                        <label><?php echo stridebr_e(stridebr_t('auth.email')); ?><input type="email" value="<?php echo stridebr_e($usuario['emailusuario']); ?>" disabled></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.account_level')); ?><input type="text" value="<?php echo stridebr_e($roleLabel); ?>" disabled></label>
                    </div>
                </section>

                <section class="settings-section" data-settings-area="profile">
                    <div><h2><?php echo stridebr_e(stridebr_t('common.profile')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.profile_optional')); ?></p></div>
                    <div class="settings-grid">
                        <label><?php echo stridebr_e(stridebr_t('settings.phone')); ?><input type="tel" name="foneusuario" maxlength="20" inputmode="tel" autocomplete="tel" data-phone-mask placeholder="(00) 00000-0000" value="<?php echo stridebr_e($usuario['foneusuario'] ?? ''); ?>"></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.birth_date')); ?><input type="date" name="datanascimentousuario" value="<?php echo stridebr_e($usuario['datanascimentousuario'] ?? ''); ?>"></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.gender')); ?><select name="generousuario"><option value=""><?php echo stridebr_e(stridebr_t('settings.not_informed')); ?></option><?php foreach ($generos as $generoValue => $generoLabel): ?><option value="<?php echo stridebr_e($generoValue); ?>"<?php echo $usuario['generousuario'] === $generoValue ? ' selected' : ''; ?>><?php echo stridebr_e($generoLabel); ?></option><?php endforeach; ?></select></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.pronouns')); ?><input type="text" name="pronomesusuario" maxlength="30" value="<?php echo stridebr_e($usuario['pronomesusuario'] ?? ''); ?>"></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.weight')); ?><input type="number" name="pesousuario" min="0.01" max="9999" step="0.01" value="<?php echo stridebr_e($usuario['pesousuario'] ?? ''); ?>"><small><?php echo stridebr_e(stridebr_t('settings.weight_help')); ?></small></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.height')); ?><input type="number" name="alturausuario" min="1" max="300" step="1" value="<?php echo stridebr_e($usuario['alturausuario'] ?? ''); ?>"></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.profile_privacy')); ?><select name="visibilidadeperfil"><option value="privado"<?php echo $usuario['visibilidadeperfil'] === 'privado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.private')); ?></option><option value="amigos"<?php echo $usuario['visibilidadeperfil'] === 'amigos' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.friends')); ?></option><option value="publico"<?php echo $usuario['visibilidadeperfil'] === 'publico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.public')); ?></option></select></label><label class="settings-toggle settings-wide"><input type="checkbox" name="descobrivel" value="1"<?php echo stridebr_db_bool($usuario['descobrivel'] ?? true) ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e(stridebr_t('settings.searchable')); ?></strong><small><?php echo stridebr_e(stridebr_t('settings.searchable_help')); ?></small></span></label>
                        <label class="settings-wide"><?php echo stridebr_e(stridebr_t('settings.bio')); ?><textarea name="biousuario" rows="4" maxlength="1000"><?php echo stridebr_e($usuario['biousuario'] ?? ''); ?></textarea></label>
                        <label class="settings-wide"><?php echo stridebr_e(stridebr_t('settings.personal_notes')); ?><textarea name="objetivousuario" rows="3" maxlength="2000"><?php echo stridebr_e($usuario['objetivousuario'] ?? ''); ?></textarea></label>
                    </div>
                </section>

                <section class="settings-section" id="historico-peso" data-settings-area="profile">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.weight_history')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.weight_history_help')); ?></p></div>
                    <div class="settings-weight-history">
                        <div class="settings-grid">
                            <label><?php echo stridebr_e(stridebr_t('settings.previous_date')); ?><input type="date" name="historico_peso_data" max="<?php echo (new DateTimeImmutable('yesterday'))->format('Y-m-d'); ?>" value="<?php echo stridebr_e($historicalWeightDate ?? ''); ?>"></label>
                            <label><?php echo stridebr_e(stridebr_t('settings.weight_on_date')); ?><input type="number" name="historico_peso_kg" min="0.01" max="9999" step="0.01" value="<?php echo stridebr_e($historicalWeightRaw ?? ''); ?>"></label>
                        </div>
                        <?php if ($weightHistoryRows !== []): ?>
                            <div class="settings-weight-history-list" aria-label="<?php echo stridebr_e(stridebr_t('settings.recent_weights')); ?>">
                                <?php foreach ($weightHistoryRows as $weightRow): ?>
                                    <span><strong><?php echo stridebr_e(stridebr_format_number((float)$weightRow['peso_kg'], 2, true)); ?> kg</strong><small><?php echo stridebr_e(stridebr_format_date((string)$weightRow['data_medicao'])); ?></small></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="settings-section" id="destaques" data-settings-area="profile">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.profile_highlights')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.highlights_help')); ?></p></div>
                    <div class="settings-highlight-grid">
                        <?php $highlightLabels = ['activities' => stridebr_t('settings.highlight_activities'), 'distance' => stridebr_t('settings.highlight_distance'), 'duration' => stridebr_t('settings.highlight_duration'), 'sports' => stridebr_t('settings.highlight_sports'), 'member_since' => stridebr_t('settings.highlight_member_since'), 'custom' => stridebr_t('settings.highlight_custom'), 'none' => stridebr_t('settings.highlight_none')]; ?>
                        <?php foreach ($profileHighlightsSettings as $highlightIndex => $highlight): ?>
                            <?php $highlightType = in_array((string) ($highlight['type'] ?? 'none'), $profileHighlightTypes, true) ? (string) ($highlight['type'] ?? 'none') : 'none'; ?>
                            <div class="settings-highlight-slot" data-profile-highlight-slot>
                                <label><?php echo stridebr_e(stridebr_t('settings.highlight')); ?> <?php echo $highlightIndex + 1; ?><select name="profile_highlight_type[]" data-profile-highlight-type><?php foreach ($highlightLabels as $value => $label): ?><option value="<?php echo stridebr_e($value); ?>"<?php echo $highlightType === $value ? ' selected' : ''; ?>><?php echo stridebr_e($label); ?></option><?php endforeach; ?></select></label>
                                <div class="settings-highlight-custom" data-profile-highlight-custom<?php echo $highlightType === 'custom' ? '' : ' hidden'; ?>>
                                    <label><?php echo stridebr_e(stridebr_t('common.title')); ?><input type="text" name="profile_highlight_label[]" maxlength="32" value="<?php echo stridebr_e((string) ($highlight['label'] ?? '')); ?>" placeholder="Ex.: 5 km"></label>
                                    <label><?php echo stridebr_e(stridebr_t('settings.highlight_value')); ?><input type="text" name="profile_highlight_value[]" maxlength="40" value="<?php echo stridebr_e((string) ($highlight['value'] ?? '')); ?>" placeholder="Ex.: 24:18"></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="settings-section" data-settings-area="profile">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.links_social')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.links_help')); ?></p></div>
                    <div class="settings-grid settings-social-grid">
                        <?php foreach ($socialPlatforms as $socialKey => $socialMeta): ?>
                            <label><?php echo stridebr_e($socialMeta['label']); ?><span class="settings-social-input"><span><?php echo stridebr_e($socialMeta['prefix']); ?></span><input type="text" name="social_<?php echo stridebr_e($socialKey); ?>" maxlength="400" placeholder="<?php echo stridebr_e($socialMeta['placeholder']); ?>" value="<?php echo stridebr_e($socialHandleFromUrl($socialKey, (string) ($socialLinks[$socialKey] ?? ''))); ?>" inputmode="url" autocomplete="off"></span></label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <details id="esportes" data-settings-area="settings" class="settings-section settings-sports-section settings-sports-disclosure"<?php echo ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors !== []) ? ' open' : ''; ?>>
                    <summary class="settings-sports-summary">
                        <span><strong><?php echo stridebr_e(stridebr_t('settings.sports_activities')); ?></strong><small><?php echo stridebr_e(stridebr_t('settings.sports_help')); ?></small></span>
                        <span class="settings-sports-summary-meta"><span class="settings-sports-count" data-sports-count></span><span class="settings-sports-chevron" aria-hidden="true">⌄</span></span>
                    </summary>
                    <div class="settings-sports-content">
                        <p class="settings-sports-intro"><?php echo stridebr_e(stridebr_t('settings.search_add_sport')); ?></p>
                    <input type="hidden" name="sports_preferences_present" value="1">
                    <div class="settings-sports-toolbar">
                        <label class="settings-sports-search">
                            <span><?php echo stridebr_e(stridebr_t('settings.add_sport')); ?></span>
                            <input type="search" placeholder="<?php echo stridebr_e(stridebr_t('settings.sport_search_placeholder')); ?>" data-settings-sport-search autocomplete="off">
                        </label>
                        <div class="settings-sports-legend" aria-label="<?php echo stridebr_e(stridebr_t('agenda.legend')); ?>">
                            <span><b class="settings-practice-dot"></b> <?php echo stridebr_e(stridebr_t('settings.appears_profile')); ?></span>
                            <span><b class="settings-favorite-star">★</b> <?php echo stridebr_e(stridebr_t('settings.activity_shortcut')); ?></span>
                        </div>
                    </div>
                    <p class="settings-sports-hint" data-sports-hint><?php echo stridebr_e(stridebr_t('settings.choose_category')); ?></p>
                    <div class="settings-sports-catalog" data-settings-sports-catalog>
                        <div class="settings-sport-family-grid" data-settings-sport-family-grid>
                            <?php foreach ($settingsSportGroups as $group): ?>
                                <button type="button" class="settings-sport-family-card" data-settings-sport-family-open="<?php echo stridebr_e((string) $group['key']); ?>">
                                    <span><strong><?php echo stridebr_e((string) $group['label']); ?></strong><small><?php echo stridebr_e((string) $group['description']); ?></small></span>
                                    <b><?php echo count($group['popular']) + count($group['more']); ?></b><i aria-hidden="true">›</i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($settingsSportGroups as $group): ?>
                            <section class="settings-sport-family-panel" data-settings-sport-family-panel="<?php echo stridebr_e((string) $group['key']); ?>" hidden>
                                <div class="settings-sport-family-head"><button type="button" class="context-back-button" data-settings-sport-family-back>← <?php echo stridebr_e(stridebr_t('settings.back_categories')); ?></button><div><strong><?php echo stridebr_e((string) $group['label']); ?></strong><small><?php echo stridebr_e((string) $group['description']); ?></small></div></div>
                                <div class="settings-sport-section-title"><?php echo stridebr_e(stridebr_t('settings.common')); ?></div>
                                <div class="settings-sport-grid">
                                    <?php foreach ($group['popular'] as $sport): ?>
                                        <?php $sportSearchText = stridebr_lower((string) $sport['nome'] . ' ' . (string) $group['label'] . ' ' . (string) ($sport['categoria'] ?? '')); ?>
                                        <article class="settings-sport-card<?php echo !empty($sport['pratica']) ? ' is-practiced' : ''; ?><?php echo !empty($sport['favorita']) ? ' is-favorite' : ''; ?>" data-settings-sport data-search-text="<?php echo stridebr_e($sportSearchText); ?>">
                                            <span class="settings-sport-icon"><?php echo stridebr_sport_icon_html((string) $sport['slug'], 'sport-icon'); ?></span>
                                            <span class="settings-sport-name"><strong><?php echo stridebr_e((string) $sport['nome']); ?></strong></span>
                                            <span class="settings-sport-controls"><label class="settings-sport-practice"><input type="checkbox" name="sports_practice[]" value="<?php echo stridebr_e((string) $sport['idmodalidade']); ?>"<?php echo !empty($sport['pratica']) ? ' checked' : ''; ?> data-sport-practice><span><?php echo stridebr_e(stridebr_t('settings.practice')); ?></span></label><label class="settings-sport-favorite" title="<?php echo stridebr_e(stridebr_t('settings.favorite_first')); ?>"><input type="checkbox" name="sports_favorite[]" value="<?php echo stridebr_e((string) $sport['idmodalidade']); ?>"<?php echo !empty($sport['favorita']) ? ' checked' : ''; ?> data-sport-favorite><span aria-hidden="true">★</span><span class="visually-hidden"><?php echo stridebr_e(stridebr_t('settings.favorite')); ?></span></label></span>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                                <?php if ($group['more'] !== []): ?>
                                    <button type="button" class="settings-sport-more" data-settings-sport-more aria-expanded="false"><?php echo stridebr_e(stridebr_t('settings.more_sports')); ?> <span aria-hidden="true">⌄</span></button>
                                    <div class="settings-sport-grid settings-sport-more-list" data-settings-sport-more-list hidden>
                                        <?php foreach ($group['more'] as $sport): ?>
                                            <?php $sportSearchText = stridebr_lower((string) $sport['nome'] . ' ' . (string) $group['label'] . ' ' . (string) ($sport['categoria'] ?? '')); ?>
                                            <article class="settings-sport-card<?php echo !empty($sport['pratica']) ? ' is-practiced' : ''; ?><?php echo !empty($sport['favorita']) ? ' is-favorite' : ''; ?>" data-settings-sport data-search-text="<?php echo stridebr_e($sportSearchText); ?>">
                                                <span class="settings-sport-icon"><?php echo stridebr_sport_icon_html((string) $sport['slug'], 'sport-icon'); ?></span>
                                                <span class="settings-sport-name"><strong><?php echo stridebr_e((string) $sport['nome']); ?></strong></span>
                                                <span class="settings-sport-controls"><label class="settings-sport-practice"><input type="checkbox" name="sports_practice[]" value="<?php echo stridebr_e((string) $sport['idmodalidade']); ?>"<?php echo !empty($sport['pratica']) ? ' checked' : ''; ?> data-sport-practice><span><?php echo stridebr_e(stridebr_t('settings.practice')); ?></span></label><label class="settings-sport-favorite" title="<?php echo stridebr_e(stridebr_t('settings.favorite_first')); ?>"><input type="checkbox" name="sports_favorite[]" value="<?php echo stridebr_e((string) $sport['idmodalidade']); ?>"<?php echo !empty($sport['favorita']) ? ' checked' : ''; ?> data-sport-favorite><span aria-hidden="true">★</span><span class="visually-hidden"><?php echo stridebr_e(stridebr_t('settings.favorite')); ?></span></label></span>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        <?php endforeach; ?>
                        <div class="settings-sport-empty" data-settings-sport-empty hidden><?php echo stridebr_e(stridebr_t('settings.no_sport')); ?></div>
                    </div>                    </div>
                </details>

                <section class="settings-section" id="atividade-privacidade" data-settings-area="settings">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.activity_privacy')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.activity_defaults')); ?></p></div>
                    <div class="settings-grid">
                        <label><?php echo stridebr_e(stridebr_t('settings.activity_visibility')); ?><select name="activity_visibility"><option value="privado"<?php echo $activityVisibilitySettings === 'privado' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('activity.only_me')); ?></option><option value="amigos"<?php echo $activityVisibilitySettings === 'amigos' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.friends')); ?></option><option value="publico"<?php echo $activityVisibilitySettings === 'publico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('common.public')); ?></option></select></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.hide_route_start')); ?><input type="number" name="hide_route_start_m" min="0" max="10000" step="50" value="<?php echo (int) $hideRouteStartSettings; ?>"><small><?php echo stridebr_e(stridebr_t('settings.hide_route_zero')); ?></small></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.hide_route_end')); ?><input type="number" name="hide_route_end_m" min="0" max="10000" step="50" value="<?php echo (int) $hideRouteEndSettings; ?>"><small><?php echo stridebr_e(stridebr_t('settings.full_record_saved')); ?></small></label>
                        <label class="settings-toggle settings-wide"><input type="checkbox" name="product_analytics" value="1"<?php echo $productAnalyticsSettings ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e(stridebr_t('settings.product_analytics')); ?></strong><small><?php echo stridebr_e(stridebr_t('settings.product_analytics_help')); ?></small></span></label>
                    </div>
                </section>

                <section class="settings-section" id="preferencias-treino" data-settings-area="settings">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.training_progress')); ?></h2></div>
                    <div class="settings-grid">
                        <label><?php echo stridebr_e(stridebr_t('settings.experience')); ?><select name="training_experience"><option value=""<?php echo $trainingExperienceSettings === '' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.prefer_not')); ?></option><option value="comecando"<?php echo $trainingExperienceSettings === 'comecando' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.beginner')); ?></option><option value="pratico"<?php echo $trainingExperienceSettings === 'pratico' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.active')); ?></option><option value="regular"<?php echo $trainingExperienceSettings === 'regular' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.regular')); ?></option></select></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.weekly_frequency')); ?><select name="training_weekly_frequency"><option value="0"<?php echo $trainingWeeklyFrequencySettings === 0 ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.prefer_not_define')); ?></option><?php for ($i = 1; $i <= 7; $i++): ?><option value="<?php echo $i; ?>"<?php echo $trainingWeeklyFrequencySettings === $i ? ' selected' : ''; ?>><?php echo $i; ?> <?php echo stridebr_e(stridebr_t('settings.day')); ?><?php echo $i === 1 ? '' : 's'; ?></option><?php endfor; ?></select></label>
                    </div>
                    <div class="settings-choice-block"><strong><?php echo stridebr_e(stridebr_t('settings.goals')); ?></strong><div class="onboarding-choice-grid compact settings-preference-grid"><?php foreach ($trainingGoalLabels as $value => $label): ?><label class="choice-card"><input type="checkbox" name="training_goals[]" value="<?php echo stridebr_e($value); ?>"<?php echo in_array($value, $trainingGoalsSettings, true) ? ' checked' : ''; ?>><span><?php echo stridebr_e($label); ?></span></label><?php endforeach; ?></div></div>
                    <div class="settings-choice-block"><strong><?php echo stridebr_e(stridebr_t('settings.progress_first')); ?></strong><div class="onboarding-choice-grid compact settings-preference-grid"><?php foreach ($trainingTrackingChoices as $value => [$label, $description]): ?><label class="choice-card choice-card-detail"><input type="checkbox" name="training_tracking[]" value="<?php echo stridebr_e($value); ?>"<?php echo in_array($value, $trainingTrackingSettings, true) ? ' checked' : ''; ?>><span><strong><?php echo stridebr_e($label); ?></strong><small><?php echo stridebr_e($description); ?></small></span></label><?php endforeach; ?></div></div>
                </section>

                <section class="settings-section" id="preferencias" data-settings-area="settings">
                    <div><h2><?php echo stridebr_e(stridebr_t('settings.personalization')); ?></h2><p><?php echo stridebr_e(stridebr_t('settings.personalization_note')); ?></p></div>
                    <div class="settings-grid">
                        <label><?php echo stridebr_e(stridebr_t('settings.units')); ?><select name="units"><option value="metric"<?php echo ($preferences['units'] ?? 'metric') === 'metric' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.metric_units')); ?></option><option value="imperial"<?php echo ($preferences['units'] ?? '') === 'imperial' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.imperial_units')); ?></option></select></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.week_start')); ?><select name="week_start"><option value="sunday"<?php echo ($preferences['week_start'] ?? 'sunday') === 'sunday' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.week_sunday')); ?></option><option value="monday"<?php echo ($preferences['week_start'] ?? '') === 'monday' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.week_monday')); ?></option></select></label>
                        <?php $localeModeSettings = stridebr_normalize_locale_mode((string) ($preferences['locale'] ?? 'auto')); ?>
                        <label><?php echo stridebr_e(stridebr_t('settings.language')); ?><select name="locale"><option value="auto"<?php echo $localeModeSettings === 'auto' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.language_auto')); ?></option><?php foreach (stridebr_locale_registry() as $localeId => $localeInfo): ?><option value="<?php echo stridebr_e($localeId); ?>"<?php echo $localeModeSettings === $localeId ? ' selected' : ''; ?>><?php echo stridebr_e($localeInfo['autonym']); ?></option><?php endforeach; ?></select><small><?php echo stridebr_e(stridebr_t('settings.language_note')); ?></small></label>
                        <label><?php echo stridebr_e(stridebr_t('settings.theme')); ?><select name="theme"><option value="light"<?php echo stridebr_theme_normalize((string) ($preferences['theme'] ?? 'light')) === 'light' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.theme_light')); ?></option><option value="dark"<?php echo stridebr_theme_normalize((string) ($preferences['theme'] ?? 'light')) === 'dark' ? ' selected' : ''; ?>><?php echo stridebr_e(stridebr_t('settings.theme_dark')); ?></option></select><small><?php echo stridebr_e(stridebr_t('settings.theme_note')); ?></small></label>
                    </div>
                </section>

                <button type="submit" class="settings-save"><?php echo stridebr_e(stridebr_t('settings.save_changes')); ?></button>
            </form>

            <?php if ($settingsView === 'profile'): ?>
                <section class="content-card settings-connections" id="conexoes">
                    <div class="settings-connections-heading">
                        <div>
                            <span class="eyebrow"><?php echo stridebr_e(stridebr_t('settings.connections')); ?></span>
                            <h2><?php echo stridebr_e(stridebr_t('settings.devices_services')); ?></h2>
                            <p><?php echo stridebr_e(stridebr_t('settings.connections_help')); ?></p>
                        </div>
                        <span class="settings-connections-badge"><?php echo stridebr_e(stridebr_t('settings.file_support')); ?></span>
                    </div>
                    <div class="settings-connections-grid">
                        <?php foreach ($integrationRegistry as $providerId => $provider): ?>
                            <?php
                            $connection = $integrationConnections[$providerId] ?? null;
                            $connected = is_array($connection) && in_array((string) ($connection['status'] ?? ''), ['conectado', 'erro'], true);
                            $configured = stridebr_integrations_configured($provider);
                            $kind = (string) ($provider['kind'] ?? 'cloud');
                            $returnTo = '/user/edit-profile.php#conexoes';
                            $lastSync = $connected && !empty($connection['ultima_sincronizacao_em']) ? new DateTimeImmutable((string) $connection['ultima_sincronizacao_em']) : null;
                            ?>
                            <article class="integration-card<?php echo $connected ? ' is-connected' : ''; ?>" data-integration-provider="<?php echo stridebr_e($providerId); ?>">
                                <div class="integration-card-main">
                                    <span class="integration-provider-mark" aria-hidden="true"><?php echo stridebr_e(strtoupper(substr((string) $provider['short'], 0, 1))); ?></span>
                                    <div>
                                        <div class="integration-card-title"><h3><?php echo stridebr_e($provider['label']); ?></h3><?php if ($connected): ?><span class="integration-status<?php echo (string) ($connection['status'] ?? '') === 'erro' ? ' has-error' : ' is-connected'; ?>"><?php echo (string) ($connection['status'] ?? '') === 'erro' ? stridebr_t('common.attention') : stridebr_t('common.connected'); ?></span><?php elseif ($kind !== 'cloud'): ?><span class="integration-status"><?php echo stridebr_e(stridebr_t('settings.app')); ?></span><?php elseif ($configured): ?><span class="integration-status"><?php echo stridebr_e(stridebr_t('settings.available')); ?></span><?php else: ?><span class="integration-status"><?php echo stridebr_e(stridebr_t('settings.prepared')); ?></span><?php endif; ?></div>
                                        <p><?php echo stridebr_e($provider['description']); ?></p>
                                        <?php if ($connected): ?>
                                            <small><?php echo $lastSync ? stridebr_e(stridebr_t('settings.last_sync', ['date' => stridebr_format_datetime_short($lastSync)])) : stridebr_e(stridebr_t('settings.awaiting_first_sync')); ?></small>
                                            <?php if (trim((string) ($connection['ultimo_erro'] ?? '')) !== ''): ?><small class="integration-error-text"><?php echo stridebr_e((string) $connection['ultimo_erro']); ?></small><?php endif; ?>
                                        <?php elseif ($kind === 'mobile'): ?>
                                            <small><?php echo stridebr_e(stridebr_t($providerId === 'health_connect' ? 'settings.android_activation' : 'settings.ios_activation')); ?></small>
                                        <?php elseif ($kind === 'bridge'): ?>
                                            <small><?php echo stridebr_e(stridebr_t('settings.health_connect_help')); ?></small>
                                        <?php elseif (!$configured): ?>
                                            <small><?php echo stridebr_e(stridebr_t('settings.provider_pending')); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="integration-card-actions">
                                    <?php if ($connected): ?>
                                        <?php if (isset($integrationSyncReady[$providerId])): ?>
                                            <form method="POST" action="/function/integration-action.php">
                                                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="provider" value="<?php echo stridebr_e($providerId); ?>"><input type="hidden" name="action" value="sync"><input type="hidden" name="return" value="<?php echo stridebr_e($returnTo); ?>">
                                                <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('settings.sync_now')); ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <details class="integration-preferences">
                                            <summary><?php echo stridebr_e(stridebr_t('settings.preferences')); ?></summary>
                                            <form method="POST" action="/function/integration-action.php" class="integration-preferences-form">
                                                <?php echo stridebr_csrf_field(); ?><input type="hidden" name="provider" value="<?php echo stridebr_e($providerId); ?>"><input type="hidden" name="action" value="preferences"><input type="hidden" name="return" value="<?php echo stridebr_e($returnTo); ?>">
                                                <label><input type="checkbox" name="sync_activities" value="1"<?php echo stridebr_db_bool($connection['sincronizar_atividades'] ?? true) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('settings.sync_activities')); ?></label>
                                                <?php if (in_array('workouts_out', $provider['capabilities'] ?? [], true)): ?><label><input type="checkbox" name="sync_workouts" value="1"<?php echo stridebr_db_bool($connection['sincronizar_treinos'] ?? false) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('settings.send_workouts')); ?></label><?php endif; ?>
                                                <?php if (!empty($provider['profile_link'])): ?><label><input type="checkbox" name="show_profile" value="1"<?php echo stridebr_db_bool($connection['mostrar_perfil'] ?? false) ? ' checked' : ''; ?>> <?php echo stridebr_e(stridebr_t('settings.show_connection')); ?></label><label><?php echo stridebr_e(stridebr_t('settings.public_profile_link')); ?><input type="url" name="profile_url" maxlength="500" placeholder="https://..." value="<?php echo stridebr_e((string) ($connection['perfil_publico_url'] ?? '')); ?>"></label><?php endif; ?>
                                                <button type="submit" class="secondary-button"><?php echo stridebr_e(stridebr_t('settings.save_preferences')); ?></button>
                                            </form>
                                        </details>
                                        <form method="POST" action="/function/integration-action.php">
                                            <?php echo stridebr_csrf_field(); ?><input type="hidden" name="provider" value="<?php echo stridebr_e($providerId); ?>"><input type="hidden" name="action" value="disconnect"><input type="hidden" name="return" value="<?php echo stridebr_e($returnTo); ?>">
                                            <button type="submit" class="text-button danger"><?php echo stridebr_e(stridebr_t('settings.disconnect')); ?></button>
                                        </form>
                                    <?php elseif ($kind === 'cloud' && $configured): ?>
                                        <a class="primary-button" href="/auth/integration.php?provider=<?php echo rawurlencode($providerId); ?>&amp;return=<?php echo rawurlencode($returnTo); ?>"><?php echo stridebr_e(stridebr_t('settings.connect')); ?></a>
                                    <?php elseif ($kind === 'cloud'): ?>
                                        <span class="integration-disabled-action"><?php echo stridebr_e(stridebr_t('settings.server_pending')); ?></span>
                                    <?php elseif ($providerId === 'health_connect'): ?>
                                        <span class="integration-disabled-action"><?php echo stridebr_e(stridebr_t('settings.requires_android')); ?></span>
                                    <?php elseif ($providerId === 'apple_health'): ?>
                                        <span class="integration-disabled-action"><?php echo stridebr_e(stridebr_t('settings.requires_ios')); ?></span>
                                    <?php else: ?>
                                        <span class="integration-disabled-action"><?php echo stridebr_e(stridebr_t('settings.via_health_connect')); ?></span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php require dirname(__DIR__, 2) . '/src/layout/footer.php'; ?>
</body>
</html>
