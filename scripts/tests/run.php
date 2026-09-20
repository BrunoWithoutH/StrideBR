<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$suites = [
    'migration registry' => 'test_migrations.php',
    'auth' => 'test_auth.php', 'activities' => 'test_activity.php', 'routes' => 'test_routes.php',
    'schedules' => 'test_schedule.php', 'workout sessions' => 'test_workout_session.php',
    'Workout Execution V2' => 'test_workout_execution_v2.php',
    'Exercise Library V2' => 'test_exercise_library_v2.php',
    'Core Mobile API Completion V1' => 'test_core_mobile_api_completion_v1.php',
    'Coach Workspace V1' => 'test_coach_workspace_v1.php',
    'friends' => 'test_friends.php', 'schedule sharing' => 'test_schedule_sharing.php',
    'trainer' => 'test_trainer.php', 'permissions' => 'test_permissions.php',
    'integration synchronization' => 'test_integration_sync.php',
    'Strava webhooks' => 'test_strava_webhooks_integration.php',
    'Progress B1 marks and tests' => 'test_progress_b1_integration.php',
    'Progress B2 typed sport goals' => 'test_progress_b2_integration.php',
    'Progress B3 competitions' => 'test_progress_b3_integration.php',
    'Progress B4 seasons, PB/SB and athletics' => 'test_progress_b4_integration.php',
    'Progress B5 combat grading and techniques' => 'test_progress_b5_integration.php',
    'API v1 mobile foundation' => 'test_api_v1.php',
    'Mobile workouts v1 calendar and planning' => 'test_mobile_workouts_v1.php',
    'Mobile workout session v1' => 'test_mobile_workout_session_v1.php',
    'Mobile training platform v1' => 'test_mobile_training_platform_v1.php',
    'Mobile progress platform v1' => 'test_mobile_progress_platform_v1.php',
    'Activity Streams + Analysis + Pacer v1' => 'test_activity_streams_analysis_pacer_v1.php',
    'Shared Activity V1' => 'test_shared_activity_v1.php',
    'Product organization contracts' => 'test_product_organization_contracts.php',
    'External Sports Events V1' => 'test_external_events_v1.php',
    'Web Product Expansion v1' => 'test_web_product_expansion_v1.php',
    'Institutional Athlete Surface v1' => 'test_teams_surface_core_v1.php',
    'SEO public sitemap' => 'test_seo_database.php',
    'product polish' => 'test_product_polish.php',
    'marketing attribution' => 'test_marketing_attribution.php',
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
