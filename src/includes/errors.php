<?php

declare(strict_types=1);

$environment = getenv('STRIDEBR_APP_ENV') ?: 'development';
$showErrors = $environment !== 'production';

ini_set('display_errors', $showErrors ? '1' : '0');
ini_set('display_startup_errors', $showErrors ? '1' : '0');
error_reporting(E_ALL);
