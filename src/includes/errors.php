<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

$environment = getenv('STRIDEBR_APP_ENV') ?: 'development';
$showErrors = $environment !== 'production';

if (!isset($GLOBALS['stridebr_request_id']) || !is_string($GLOBALS['stridebr_request_id'])) {
    try {
        $GLOBALS['stridebr_request_id'] = bin2hex(random_bytes(6));
    } catch (Throwable) {
        $GLOBALS['stridebr_request_id'] = substr(hash('sha256', uniqid('', true)), 0, 12);
    }
}
$requestId = $GLOBALS['stridebr_request_id'];
if (!headers_sent()) {
    header('X-Request-ID: ' . $requestId);
}

ini_set('display_errors', $showErrors ? '1' : '0');
ini_set('display_startup_errors', $showErrors ? '1' : '0');
error_reporting(E_ALL);

if (!$showErrors) {
    $renderInternalError = static function () use ($requestId): void {
        if (!headers_sent()) {
            http_response_code(500);
            header('Cache-Control: no-store');
            header('X-Request-ID: ' . $requestId);
        }
        $file = dirname(__DIR__, 2) . '/public/errors/500.php';
        if (is_file($file)) {
            require $file;
        } else {
            echo 'O StrideBR encontrou um erro inesperado.';
        }
    };

    set_exception_handler(static function (Throwable $error) use ($renderInternalError): void {
        error_log('StrideBR [' . $GLOBALS['stridebr_request_id'] . '] uncaught exception: ' . $error::class . ': ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
        $renderInternalError();
        exit;
    });

    register_shutdown_function(static function () use ($renderInternalError): void {
        $error = error_get_last();
        if (!is_array($error) || !in_array((int) ($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        error_log('StrideBR [' . $GLOBALS['stridebr_request_id'] . '] fatal error: ' . (string) ($error['message'] ?? 'unknown') . ' in ' . (string) ($error['file'] ?? '') . ':' . (string) ($error['line'] ?? ''));
        $renderInternalError();
    });
}
