<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$suites = [
    'migration registry' => 'test_migrations.php',
    'auth' => 'test_auth.php', 'activities' => 'test_activity.php', 'routes' => 'test_routes.php',
    'schedules' => 'test_schedule.php', 'workout sessions' => 'test_workout_session.php',
    'friends' => 'test_friends.php', 'schedule sharing' => 'test_schedule_sharing.php',
    'trainer' => 'test_trainer.php', 'permissions' => 'test_permissions.php',
    'product polish' => 'test_product_polish.php',
];
$passed = 0;
$failures = [];
foreach ($suites as $label => $file) {
    try {
        alphaTestCleanup($pdo);
        $suite = require __DIR__ . '/' . $file;
        $suite($pdo);
        alphaTestCleanup($pdo);
        echo "✓ $label\n";
        $passed++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        alphaTestCleanup($pdo);
        echo "✗ $label\n  {$e->getMessage()}\n";
        $failures[] = $label;
    }
}
echo "\n$passed passed, " . count($failures) . " failed, " . AlphaTest::$assertions . " assertions\n";
exit($failures === [] ? 0 : 1);
