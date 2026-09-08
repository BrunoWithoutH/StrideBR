<?php
// Liveness only: no DB, sessions, configuration or diagnostics disclosure.
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
echo "OK\n";
