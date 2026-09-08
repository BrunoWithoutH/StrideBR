<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

date_default_timezone_set('America/Sao_Paulo');

if (!defined('STRIDEBR_REQUEST_STARTED_AT')) {
    define('STRIDEBR_REQUEST_STARTED_AT', microtime(true));
}
$GLOBALS['stridebr_server_timing'] = $GLOBALS['stridebr_server_timing'] ?? [];

function stridebr_timing_measure(string $name, float $startedAt, string $description = ''): void
{
    $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $name) ?: 'metric';
    $duration = max(0.0, (microtime(true) - $startedAt) * 1000);
    $GLOBALS['stridebr_server_timing'][$name] = [
        'duration' => $duration,
        'description' => trim($description),
    ];
}

function stridebr_send_server_timing(): void
{
    $total = max(0.0, (microtime(true) - (float) STRIDEBR_REQUEST_STARTED_AT) * 1000);
    $metrics = (array) ($GLOBALS['stridebr_server_timing'] ?? []);
    $metrics['total'] = ['duration' => $total, 'description' => 'Resposta total'];
    if (!headers_sent()) {
        $parts = [];
        foreach ($metrics as $name => $metric) {
            $part = $name . ';dur=' . number_format((float) ($metric['duration'] ?? 0), 1, '.', '');
            $description = str_replace(['"', "\r", "\n"], ['', ' ', ' '], (string) ($metric['description'] ?? ''));
            $description = preg_replace('/[^\x20-\x7E]/', '', $description) ?? '';
            if ($description !== '') {
                $part .= ';desc="' . $description . '"';
            }
            $parts[] = $part;
        }
        header('Server-Timing: ' . implode(', ', $parts));
    }
    if ($total >= 2000) {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        error_log(sprintf('[StrideBR slow request] %s %s %.0fms', $method, $uri, $total));
    }
}

register_shutdown_function('stridebr_send_server_timing');

function stridebr_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secureCookie = stridebr_secure_cookie();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $secureCookie ? '1' : '0');

    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

function stridebr_e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


function stridebr_error_document(int $status): void
{
    $status = in_array($status, [403, 404, 500], true) ? $status : 500;
    http_response_code($status);
    $file = dirname(__DIR__, 2) . '/public/errors/' . $status . '.php';
    if (is_file($file)) {
        require $file;
    } else {
        echo $status === 403 ? 'Acesso negado.' : ($status === 404 ? 'Página não encontrada.' : 'Erro interno.');
    }
    exit;
}

function stridebr_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function stridebr_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function stridebr_db_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value !== 0;
    }
    return in_array(stridebr_lower(trim((string) $value)), ['1', 't', 'true', 'y', 'yes', 'on'], true);
}

function stridebr_is_logged_in(): bool
{
    return isset($_SESSION['IdUsuario']) && is_string($_SESSION['IdUsuario']) && $_SESSION['IdUsuario'] !== '';
}

function stridebr_session_release(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function stridebr_user_id(): ?string
{
    return stridebr_is_logged_in() ? $_SESSION['IdUsuario'] : null;
}

function stridebr_require_login(string $loginUrl = '/login.php'): string
{
    if (!stridebr_is_logged_in()) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (str_starts_with($uri, '/') && !str_starts_with($uri, '//')) {
            $_SESSION['previous_page'] = $uri;
        }
        header('Location: ' . $loginUrl);
        exit;
    }

    return $_SESSION['IdUsuario'];
}

function stridebr_safe_redirect(?string $target, string $fallback = '/home.php'): string
{
    if (!is_string($target) || $target === '') {
        return $fallback;
    }

    $parts = parse_url($target);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return $fallback;
    }

    if (!str_starts_with($target, '/') || str_starts_with($target, '//') || str_contains($target, '\\') || preg_match('/[\x00-\x1F\x7F]/', $target)) {
        return $fallback;
    }

    return $target;
}

function stridebr_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function stridebr_idempotency_key(): string
{
    return bin2hex(random_bytes(16));
}

function stridebr_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . stridebr_e(stridebr_csrf_token()) . '">'
        . '<input type="hidden" name="_idempotency_key" value="' . stridebr_e(stridebr_idempotency_key()) . '">';
}

function stridebr_consume_idempotency_key(?string $key): bool
{
    $key = trim((string) $key);
    if ($key === '') return true;
    if (preg_match('/^[a-f0-9]{32}$/', $key) !== 1) return false;
    $used = is_array($_SESSION['StrideBRUsedSubmissions'] ?? null) ? $_SESSION['StrideBRUsedSubmissions'] : [];
    $cutoff = time() - 3600;
    foreach ($used as $usedKey => $usedAt) {
        if ((int) $usedAt < $cutoff) unset($used[$usedKey]);
    }
    if (isset($used[$key])) {
        $_SESSION['StrideBRUsedSubmissions'] = $used;
        return false;
    }
    $used[$key] = time();
    if (count($used) > 200) $used = array_slice($used, -200, null, true);
    $_SESSION['StrideBRUsedSubmissions'] = $used;
    return true;
}

function stridebr_reject_duplicate_submission(): never
{
    $accept = stridebr_lower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json')) {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Essa ação já foi enviada.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    stridebr_flash('info', 'Essa ação já foi enviada.');
    $target = stridebr_safe_redirect((string) ($_SERVER['REQUEST_URI'] ?? ''), '/home.php');
    header('Location: ' . $target, true, 303);
    exit;
}

function stridebr_verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(stridebr_csrf_token(), $token)) {
        stridebr_error_document(403);
    }
    if (isset($_POST['_idempotency_key']) && !stridebr_consume_idempotency_key((string) $_POST['_idempotency_key'])) {
        stridebr_reject_duplicate_submission();
    }
}

function stridebr_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function stridebr_take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flashes) ? $flashes : [];
}

function stridebr_slug(string $value): string
{
    $value = trim($value);
    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($transliterated)) {
        $value = $transliterated;
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

require_once __DIR__ . '/http_headers.php';
stridebr_send_security_headers();
stridebr_start_session();
require_once __DIR__ . '/i18n.php';

function stridebr_display_name(): string
{
    $name = (string) ($_SESSION['NomeExibicao'] ?? $_SESSION['NomeUsuario'] ?? '');
    return stridebr_person_name_for_display($name, (string) ($_SESSION['Username'] ?? ''), 'Usuário', 60);
}

function stridebr_person_name_normalize(string $name): string
{
    $name = trim($name);
    if (class_exists('Normalizer')) {
        $normalized = Normalizer::normalize($name, Normalizer::FORM_KC);
        if (is_string($normalized)) {
            $name = $normalized;
        }
    }
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    return trim($name);
}

function stridebr_person_name_is_valid(string $name, int $maxLength = 80): bool
{
    $name = stridebr_person_name_normalize($name);
    if ($name === '' || stridebr_length($name) > $maxLength) {
        return false;
    }
    if (preg_match('/[\p{C}\p{M}]/u', $name) === 1) {
        return false;
    }
    if (preg_match('/\p{Latin}/u', $name) !== 1) {
        return false;
    }
    if (preg_match('/[.\'’\-]{2}/u', $name) === 1) {
        return false;
    }
    return preg_match('/^[\p{Latin}\p{N}](?:[\p{Latin}\p{N} .\'’\-]*[\p{Latin}\p{N}])?$/u', $name) === 1;
}

function stridebr_person_name_for_display(?string $name, ?string $username = null, string $fallback = 'Usuário', int $maxLength = 80): string
{
    $value = stridebr_person_name_normalize((string) $name);
    $value = preg_replace('/[\p{C}\p{M}]/u', '', $value) ?? '';
    $value = preg_replace('/[^\p{Latin}\p{N} .\'’\-]+/u', '', $value) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    $value = trim($value);
    if ($value === '' || preg_match('/\p{Latin}/u', $value) !== 1) {
        $username = stridebr_lower(trim((string) $username));
        return stridebr_username_is_valid($username) ? '@' . $username : $fallback;
    }
    if (stridebr_length($value) > $maxLength) {
        $value = function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }
    return $value;
}

function stridebr_username_is_reserved(string $username): bool
{
    return in_array($username, [
        'admin', 'administrator', 'moderator', 'owner', 'root', 'system', 'sistema',
        'stridebr', 'official', 'oficial', 'support', 'suporte', 'security', 'seguranca',
        'api', 'login', 'logout', 'signup', 'settings', 'feedback', 'null', 'undefined',
    ], true);
}

function stridebr_username_is_valid(string $username): bool
{
    if (stridebr_username_is_reserved($username)) {
        return false;
    }
    return preg_match('/^(?!.*[._-]{2})[a-z0-9][a-z0-9._-]{1,38}[a-z0-9]$/', $username) === 1;
}

function stridebr_user_role(): string
{
    $role = (string) ($_SESSION['PapelUsuario'] ?? 'user');
    return in_array($role, ['user', 'moderator', 'admin', 'owner'], true) ? $role : 'user';
}

function stridebr_role_rank(string $role): int
{
    return match ($role) {
        'owner' => 40,
        'admin' => 30,
        'moderator' => 20,
        default => 10,
    };
}

function stridebr_role_label(?string $role = null): string
{
    $role = $role ?? stridebr_user_role();
    return match ($role) {
        'owner' => 'Proprietário',
        'admin' => 'Administrador',
        'moderator' => 'Moderador',
        default => 'Usuário',
    };
}

function stridebr_image_square_webp(string $sourcePath, string $destinationPath, int $size = 320, int $quality = 82): bool
{
    $size = max(64, min(1024, $size));
    $quality = max(55, min(92, $quality));
    $info = @getimagesize($sourcePath);
    if ($info === false || empty($info[0]) || empty($info[1])) {
        return false;
    }

    if (class_exists('Imagick')) {
        try {
            $image = new Imagick($sourcePath);
            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }
            $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
            $image->cropThumbnailImage($size, $size);
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($quality);
            $ok = $image->writeImage($destinationPath);
            $image->clear();
            $image->destroy();
            return $ok;
        } catch (Throwable) {
        }
    }

    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
        return false;
    }

    $mime = (string) ($info['mime'] ?? '');
    $source = match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };
    if (!$source) {
        return false;
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        try {
            $exif = @exif_read_data($sourcePath);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if ($orientation === 3) {
                $rotated = imagerotate($source, 180, 0);
                if ($rotated) { imagedestroy($source); $source = $rotated; }
            } elseif ($orientation === 6) {
                $rotated = imagerotate($source, -90, 0);
                if ($rotated) { imagedestroy($source); $source = $rotated; }
            } elseif ($orientation === 8) {
                $rotated = imagerotate($source, 90, 0);
                if ($rotated) { imagedestroy($source); $source = $rotated; }
            }
        } catch (Throwable) {
        }
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $crop = min($sourceWidth, $sourceHeight);
    $sourceX = (int) floor(($sourceWidth - $crop) / 2);
    $sourceY = (int) floor(($sourceHeight - $crop) / 2);

    $target = imagecreatetruecolor($size, $size);
    if (!$target) {
        imagedestroy($source);
        return false;
    }
    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefill($target, 0, 0, $transparent);
    imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $size, $size, $crop, $crop);
    $ok = imagewebp($target, $destinationPath, $quality);
    imagedestroy($target);
    imagedestroy($source);
    return $ok;
}

function stridebr_image_webp_fit(string $sourcePath, string $destinationPath, int $maxWidth = 1920, int $maxHeight = 1920, int $quality = 82): bool
{
    $maxWidth = max(320, min(4096, $maxWidth));
    $maxHeight = max(320, min(4096, $maxHeight));
    $quality = max(55, min(92, $quality));
    $info = @getimagesize($sourcePath);
    if ($info === false || empty($info[0]) || empty($info[1])) return false;

    if (class_exists('Imagick')) {
        try {
            $image = new Imagick($sourcePath);
            if (method_exists($image, 'setIteratorIndex')) $image->setIteratorIndex(0);
            if (method_exists($image, 'autoOrient')) $image->autoOrient();
            elseif (method_exists($image, 'autoOrientImage')) $image->autoOrientImage();
            $image->setImageColorspace(Imagick::COLORSPACE_SRGB);
            if ($image->getImageWidth() > $maxWidth || $image->getImageHeight() > $maxHeight) {
                $image->thumbnailImage($maxWidth, $maxHeight, true, true);
            }
            $image->stripImage();
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($quality);
            $ok = $image->writeImage($destinationPath);
            $image->clear();
            $image->destroy();
            return $ok;
        } catch (Throwable) {
        }
    }

    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) return false;
    $mime = (string) ($info['mime'] ?? '');
    $source = match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };
    if (!$source) return false;

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        try {
            $exif = @exif_read_data($sourcePath);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            $angle = match ($orientation) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
            if ($angle !== 0) {
                $rotated = imagerotate($source, $angle, 0);
                if ($rotated) { imagedestroy($source); $source = $rotated; }
            }
        } catch (Throwable) {
        }
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $scale = min(1, $maxWidth / max(1, $sourceWidth), $maxHeight / max(1, $sourceHeight));
    $targetWidth = max(1, (int) round($sourceWidth * $scale));
    $targetHeight = max(1, (int) round($sourceHeight * $scale));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$target) { imagedestroy($source); return false; }
    imagealphablending($target, false);
    imagesavealpha($target, true);
    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefill($target, 0, 0, $transparent);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
    $ok = imagewebp($target, $destinationPath, $quality);
    imagedestroy($target);
    imagedestroy($source);
    return $ok;
}

function stridebr_profile_photo_url(?string $photo, int $size = 320): string
{
    $photo = trim((string) $photo);
    if ($photo === '') {
        return '/assets/img/ui/userdefault.svg';
    }

    if (str_starts_with($photo, '/assets/')) {
        return $photo;
    }

    if (preg_match('#^/uploads/avatars/([a-f0-9]{32})\.(?:jpg|jpeg|png|webp)$#i', $photo, $match) === 1) {
        $size = $size <= 128 ? 96 : 320;
        $publicRoot = dirname(__DIR__, 2) . '/public';
        $sourcePath = $publicRoot . $photo;
        if (!is_file($sourcePath)) {
            return '/assets/img/ui/userdefault.svg';
        }

        $cacheDirectory = $publicRoot . '/uploads/avatars/cache';
        $variantName = strtolower($match[1]) . '-' . $size . '.webp';
        $variantPath = $cacheDirectory . '/' . $variantName;
        $variantUrl = '/uploads/avatars/cache/' . $variantName;
        $sourceMtime = (int) (@filemtime($sourcePath) ?: 0);
        $variantMtime = is_file($variantPath) ? (int) (@filemtime($variantPath) ?: 0) : 0;

        if ($variantMtime < $sourceMtime) {
            if ((is_dir($cacheDirectory) || @mkdir($cacheDirectory, 0755, true))
                && stridebr_image_square_webp($sourcePath, $variantPath, $size, 82)) {
                @chmod($variantPath, 0644);
                $variantMtime = (int) (@filemtime($variantPath) ?: time());
            }
        }

        if (is_file($variantPath)) {
            return $variantUrl . '?v=' . $variantMtime;
        }
        return $photo;
    }

    $parts = parse_url($photo);
    if ($parts !== false && isset($parts['scheme']) && in_array(stridebr_lower((string) $parts['scheme']), ['http', 'https'], true) && filter_var($photo, FILTER_VALIDATE_URL) !== false) {
        return $photo;
    }
    return '/assets/img/ui/userdefault.svg';
}

function stridebr_has_role(string $minimumRole): bool
{
    return stridebr_role_rank(stridebr_user_role()) >= stridebr_role_rank($minimumRole);
}

function stridebr_require_role(string $minimumRole): void
{
    stridebr_require_login();
    if (!stridebr_has_role($minimumRole)) {
        stridebr_error_document(403);
    }
}

function stridebr_generate_id(int $length = 21): string
{
    $bytes = random_bytes((int) ceil($length * 3 / 4) + 2);
    return substr(rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='), 0, $length);
}

function stridebr_request_ip(): ?string
{
    return stridebr_client_ip();
}

function stridebr_feature_enabled(PDO $pdo, string $key, bool $default = false): bool
{
    static $requestCache = [];
    if (array_key_exists($key, $requestCache)) {
        return $requestCache[$key];
    }

    $ttl = max(0, min(300, (int) (getenv('STRIDEBR_FEATURE_CACHE_TTL') ?: 60)));
    if ($ttl > 0 && isset($_SESSION) && is_array($_SESSION)) {
        $cached = $_SESSION['StrideBRFeatureFlags'][$key] ?? null;
        if (is_array($cached) && isset($cached['at']) && (time() - (int) $cached['at']) < $ttl) {
            return $requestCache[$key] = (bool) ($cached['value'] ?? $default);
        }
    }

    try {
        $stmt = $pdo->prepare('SELECT ativo FROM feature_flags WHERE chave = :chave LIMIT 1');
        $stmt->execute([':chave' => $key]);
        $value = $stmt->fetchColumn();
        $resolved = $value === false ? $default : stridebr_db_bool($value);
    } catch (Throwable) {
        $resolved = $default;
    }

    if ($ttl > 0 && isset($_SESSION) && is_array($_SESSION)) {
        $_SESSION['StrideBRFeatureFlags'][$key] = ['value' => $resolved, 'at' => time()];
    }
    return $requestCache[$key] = $resolved;
}

function stridebr_feature_cache_clear(?string $key = null): void
{
    if (!isset($_SESSION['StrideBRFeatureFlags']) || !is_array($_SESSION['StrideBRFeatureFlags'])) {
        return;
    }
    if ($key === null) {
        unset($_SESSION['StrideBRFeatureFlags']);
        return;
    }
    unset($_SESSION['StrideBRFeatureFlags'][$key]);
}


function stridebr_maps_arcgis_key(): string
{
    return trim((string) (getenv('STRIDEBR_MAPS_ARCGIS_KEY') ?: ''));
}

function stridebr_maps_runtime_script(): string
{
    $src = stridebr_asset('/assets/js/map-basemaps.js');
    $key = stridebr_e(stridebr_maps_arcgis_key());
    return '<script src="' . stridebr_e($src) . '" data-arcgis-key="' . $key . '"></script>';
}

function stridebr_version(): string
{
    return trim((string) (getenv('STRIDEBR_VERSION') ?: '1.0.0-rc.4'));
}

function stridebr_build(): string
{
    $configured = trim((string) (getenv('STRIDEBR_BUILD') ?: ''));
    if ($configured !== '') return $configured;
    $file = dirname(__DIR__, 2) . '/.stridebr-build';
    if (is_file($file)) {
        $value = trim((string) @file_get_contents($file));
        if ($value !== '' && stridebr_length($value) <= 80) return $value;
    }
    return '20260825-daily-use-polish';
}

function stridebr_db_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    if (preg_match('/^[a-z_][a-z0-9_]*$/i', $table) !== 1) {
        return $cache[$table] = false;
    }
    try {
        $stmt = $pdo->prepare("SELECT to_regclass(:table) IS NOT NULL");
        $stmt->execute([':table' => 'stridebr.' . $table]);
        return $cache[$table] = stridebr_db_bool($stmt->fetchColumn());
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function stridebr_db_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    if (preg_match('/^[a-z_][a-z0-9_]*$/i', $table) !== 1 || preg_match('/^[a-z_][a-z0-9_]*$/i', $column) !== 1) {
        return $cache[$key] = false;
    }
    try {
        $stmt = $pdo->prepare("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'stridebr' AND table_name = :table AND column_name = :column)");
        $stmt->execute([':table' => $table, ':column' => $column]);
        return $cache[$key] = stridebr_db_bool($stmt->fetchColumn());
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}

function stridebr_session_hash(): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE || session_id() === '') {
        return null;
    }
    return hash('sha256', session_id());
}

function stridebr_session_tracking_available(PDO $pdo): bool
{
    return stridebr_db_table_exists($pdo, 'sessoes_usuario');
}

function stridebr_session_register(PDO $pdo, string $userId): bool
{
    if (!stridebr_session_tracking_available($pdo)) {
        return true;
    }
    $hash = stridebr_session_hash();
    if ($hash === null || $userId === '') {
        return false;
    }
    $ip = stridebr_client_ip();
    $agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if (stridebr_length($agent) > 500) {
        $agent = function_exists('mb_substr') ? mb_substr($agent, 0, 500, 'UTF-8') : substr($agent, 0, 500);
    }
    $stmt = $pdo->prepare(
        "INSERT INTO sessoes_usuario (sessao_hash, idusuario, ip, user_agent)\n"
        . "VALUES (:hash, :usuario, CAST(:ip AS inet), :agent)\n"
        . "ON CONFLICT (sessao_hash) DO UPDATE SET ultimo_uso_em = NOW(), ip = EXCLUDED.ip, user_agent = EXCLUDED.user_agent\n"
        . "WHERE sessoes_usuario.idusuario = EXCLUDED.idusuario AND sessoes_usuario.revogado_em IS NULL"
    );
    $stmt->bindValue(':hash', $hash);
    $stmt->bindValue(':usuario', $userId);
    $stmt->bindValue(':ip', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':agent', $agent !== '' ? $agent : null, $agent !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();
    $check = $pdo->prepare('SELECT revogado_em FROM sessoes_usuario WHERE sessao_hash = :hash AND idusuario = :usuario LIMIT 1');
    $check->execute([':hash' => $hash, ':usuario' => $userId]);
    $row = $check->fetch();
    return is_array($row) && $row['revogado_em'] === null;
}

function stridebr_session_revoke_others(PDO $pdo, string $userId): void
{
    if (!stridebr_session_tracking_available($pdo)) {
        return;
    }
    $hash = stridebr_session_hash();
    if ($hash === null) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE sessoes_usuario SET revogado_em = NOW() WHERE idusuario = :usuario AND sessao_hash <> :hash AND revogado_em IS NULL');
    $stmt->execute([':usuario' => $userId, ':hash' => $hash]);
}

function stridebr_session_revoke_all(PDO $pdo, string $userId): void
{
    if (!stridebr_session_tracking_available($pdo)) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE sessoes_usuario SET revogado_em = NOW() WHERE idusuario = :usuario AND revogado_em IS NULL');
    $stmt->execute([':usuario' => $userId]);
}

function stridebr_session_revoke(PDO $pdo, string $userId, string $hash): bool
{
    if (!stridebr_session_tracking_available($pdo) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1 || $hash === stridebr_session_hash()) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE sessoes_usuario SET revogado_em = NOW() WHERE idusuario = :usuario AND sessao_hash = :hash AND revogado_em IS NULL');
    $stmt->execute([':usuario' => $userId, ':hash' => $hash]);
    return $stmt->rowCount() > 0;
}

function stridebr_session_revoke_current(PDO $pdo, string $userId): void
{
    if (!stridebr_session_tracking_available($pdo)) {
        return;
    }
    $hash = stridebr_session_hash();
    if ($hash === null) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE sessoes_usuario SET revogado_em = NOW() WHERE idusuario = :usuario AND sessao_hash = :hash AND revogado_em IS NULL');
    $stmt->execute([':usuario' => $userId, ':hash' => $hash]);
}

function stridebr_terms_version(): string
{
    return trim((string) (getenv('STRIDEBR_TERMS_VERSION') ?: '2026-08-30-1'));
}

function stridebr_privacy_version(): string
{
    return trim((string) (getenv('STRIDEBR_PRIVACY_VERSION') ?: '2026-09-01-1'));
}

function stridebr_support_email(): string
{
    $configured = trim((string) (getenv('STRIDEBR_SUPPORT_EMAIL') ?: ''));
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
        return $configured;
    }
    if (!stridebr_is_development()) throw new RuntimeException('Missing or invalid STRIDEBR_SUPPORT_EMAIL');
    return 'pinheirobrunoevaristo@gmail.com';
}

function stridebr_support_mailto(string $subject = ''): string
{
    $url = 'mailto:' . stridebr_support_email();
    if ($subject !== '') {
        $url .= '?subject=' . rawurlencode($subject);
    }
    return $url;
}

function stridebr_social_image_url(): string
{
    return stridebr_public_url() . '/assets/img/branding/stridebr-og.png';
}

function stridebr_password_is_valid_length(string $password, int $min = 8, int $max = 128): bool
{
    $length = stridebr_length($password);
    return $length >= $min && $length <= $max;
}

function stridebr_password_hash(string $password): string
{
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $hash = password_hash($password, $algorithm);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Não foi possível proteger a senha.');
    }
    return $hash;
}

function stridebr_password_needs_rehash(string $hash): bool
{
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    return password_needs_rehash($hash, $algorithm);
}

function stridebr_destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function stridebr_auth_limit_hash(string $scope, string $identifier): string
{
    return hash('sha256', $scope . "\0" . stridebr_lower(trim($identifier)));
}

function stridebr_auth_limit_is_blocked(PDO $pdo, string $scope, string $identifier): bool
{
    if ($identifier === '') {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM auth_rate_limits WHERE chave_hash = :hash AND bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW() LIMIT 1');
    $stmt->execute([':hash' => stridebr_auth_limit_hash($scope, $identifier)]);
    return (bool) $stmt->fetchColumn();
}

function stridebr_auth_limit_record_failure(PDO $pdo, string $scope, string $identifier, int $maxAttempts, int $windowSeconds, int $blockSeconds): void
{
    if ($identifier === '') {
        return;
    }
    $hash = stridebr_auth_limit_hash($scope, $identifier);
    $sql = <<<'SQL'
INSERT INTO auth_rate_limits (chave_hash, escopo, tentativas, janela_inicio, bloqueado_ate, atualizado_em)
VALUES (:hash, :scope, 1, NOW(), NULL, NOW())
ON CONFLICT (chave_hash) DO UPDATE SET
    escopo = EXCLUDED.escopo,
    tentativas = CASE
        WHEN auth_rate_limits.janela_inicio <= NOW() - (CAST(:window1 AS integer) * INTERVAL '1 second') THEN 1
        ELSE auth_rate_limits.tentativas + 1
    END,
    janela_inicio = CASE
        WHEN auth_rate_limits.janela_inicio <= NOW() - (CAST(:window2 AS integer) * INTERVAL '1 second') THEN NOW()
        ELSE auth_rate_limits.janela_inicio
    END,
    bloqueado_ate = CASE
        WHEN auth_rate_limits.bloqueado_ate IS NOT NULL AND auth_rate_limits.bloqueado_ate > NOW() THEN auth_rate_limits.bloqueado_ate
        WHEN auth_rate_limits.janela_inicio <= NOW() - (CAST(:window3 AS integer) * INTERVAL '1 second') THEN NULL
        WHEN auth_rate_limits.tentativas + 1 >= :max_attempts THEN NOW() + (CAST(:block_seconds AS integer) * INTERVAL '1 second')
        ELSE NULL
    END,
    atualizado_em = NOW()
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':hash' => $hash,
        ':scope' => $scope,
        ':window1' => $windowSeconds,
        ':window2' => $windowSeconds,
        ':window3' => $windowSeconds,
        ':max_attempts' => $maxAttempts,
        ':block_seconds' => $blockSeconds,
    ]);
}

function stridebr_auth_limit_record_attempt(PDO $pdo, string $scope, string $identifier, int $maxAttempts, int $windowSeconds, int $blockSeconds): void
{
    stridebr_auth_limit_record_failure($pdo, $scope, $identifier, $maxAttempts, $windowSeconds, $blockSeconds);
}

function stridebr_auth_limit_clear(PDO $pdo, string $scope, string $identifier): void
{
    if ($identifier === '') {
        return;
    }
    $stmt = $pdo->prepare('DELETE FROM auth_rate_limits WHERE chave_hash = :hash');
    $stmt->execute([':hash' => stridebr_auth_limit_hash($scope, $identifier)]);
}

function stridebr_auth_limit_cleanup(PDO $pdo, int $days = 7): void
{
    $days = max(1, min(90, $days));
    $stmt = $pdo->prepare("DELETE FROM auth_rate_limits WHERE atualizado_em < NOW() - (CAST(:days AS integer) * INTERVAL '1 day')");
    $stmt->execute([':days' => $days]);
}

function stridebr_rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $bucket = $_SESSION['rate_limits'][$key] ?? ['count' => 0, 'started' => $now];
    if (!is_array($bucket)) {
        $bucket = ['count' => 0, 'started' => $now];
    }
    $started = (int) ($bucket['started'] ?? $now);
    if ($now - $started >= $windowSeconds) {
        $bucket = ['count' => 0, 'started' => $now];
    }
    if ((int) ($bucket['count'] ?? 0) >= $maxAttempts) {
        $_SESSION['rate_limits'][$key] = $bucket;
        return false;
    }
    $bucket['count'] = (int) ($bucket['count'] ?? 0) + 1;
    $_SESSION['rate_limits'][$key] = $bucket;
    return true;
}



function stridebr_asset(string $path): string
{
    static $cache = [];
    $path = '/' . ltrim($path, '/');
    if (isset($cache[$path])) {
        return $cache[$path];
    }
    $fullPath = dirname(__DIR__, 2) . '/public' . $path;
    if (!is_file($fullPath)) {
        return $cache[$path] = $path;
    }
    return $cache[$path] = $path . '?v=' . filemtime($fullPath);
}
