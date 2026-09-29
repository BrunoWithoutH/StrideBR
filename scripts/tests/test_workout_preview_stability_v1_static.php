<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};

$page = file_get_contents($root . '/public/user/cronogramatreinos.php');
$js = file_get_contents($root . '/public/assets/js/cronogramas.js');
$endpoint = file_get_contents($root . '/public/api/cronograma-treino-preview.php');
$css = file_get_contents($root . '/public/assets/css/cronogramas.css');
$assert(is_string($page) && is_string($js) && is_string($endpoint) && is_string($css), 'Preview sources unavailable');
$assert(substr_count($page, 'data-workout-preview-host') === 1, 'Preview modal must expose exactly one content host');
$assert(!str_contains($page, 'data-workout-preview-content="<?php'), 'Server still pre-renders one preview node per workout');
$assert(str_contains($js, 'createWorkoutPreviewSlot'), 'Preview slot lifecycle helper missing');
$assert(str_contains($js, 'previewSlot.begin(context, createPreviewLoading(context))'), 'Preview loading does not enter the single slot through request ownership');
$assert(str_contains($js, 'previewSlot.replace(request, activeContent)'), 'Preview success does not replace the owned single slot');
$assert(str_contains($js, 'previewSlot?.close()'), 'Preview close does not invalidate and clear the slot');
$assert(str_contains($js, 'signal:request.controller.signal'), 'Preview fetch is not abortable');
$assert(str_contains($js, 'if (!previewRequestIsCurrent(request)) return'), 'Preview lacks stale post-await guards');
$assert(str_contains($js, 'Object.freeze({...context})'), 'Preview request context is not immutable');
$assert(!str_contains($js, ".querySelector('.workout-preview-dialog')?.appendChild(content)"), 'Workout content is still appended to the dialog');
$assert(str_contains($css, '.workout-preview-actions') && str_contains($css, 'background: var(--ui-panel);'), 'Mobile preview actions do not use the theme panel token');

$login = strpos($endpoint, 'stridebr_require_login()');
$pg = strpos($endpoint, "src/config/pg_config.php");
$release = strpos($endpoint, 'stridebr_session_release()');
$assert($login !== false && $pg !== false && $release !== false, 'Preview endpoint auth/session lifecycle is incomplete');
$assert($login < $pg && $pg < $release, 'Preview endpoint releases the session before authentication/session guard');

$files = array_merge(
    glob($root . '/public/api/*.php') ?: [],
    glob($root . '/src/function/*api*.php') ?: []
);
$unsafe = [];
$audited = 0;
foreach ($files as $file) {
    $source = file_get_contents($file);
    if (!is_string($source) || !str_contains($source, 'stridebr_session_release')) continue;
    $releaseOffset = strpos($source, 'stridebr_session_release');
    $loginOffset = strpos($source, 'stridebr_require_login');
    $pgOffset = strpos($source, 'pg_config.php');
    if ($loginOffset === false) continue;
    $audited++;
    $safe = $releaseOffset > $loginOffset && ($pgOffset === false || $releaseOffset > $pgOffset);
    if (!$safe) $unsafe[] = str_replace($root . '/', '', $file);
}
$assert($audited >= 20, 'Session release audit did not cover the authenticated API surface');
$assert($unsafe === [], 'Authenticated endpoints release sessions before auth/guard: ' . implode(', ', $unsafe));

echo "Workout Preview Stability V1 static: {$assertions} assertions\n";
