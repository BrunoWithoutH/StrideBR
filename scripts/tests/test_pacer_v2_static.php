<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/src/function/activity_stream_service.php';
require_once $root . '/src/function/pacer_service.php';
$n = 0;
$ok = static function (bool $value, string $message) use (&$n): void { $n++; if (!$value) throw new RuntimeException($message); };

$plan = ['target_distance_m' => 10000.0, 'target_time_s' => 3120.0, 'default_tolerance_s_per_km' => 10.0, 'segments' => pacerGeneratedSegments(10000, 3120, 'negative_split', 10, ['segment_distance_m' => 2000]), 'guidance_rules' => pacerDefaultRules()];
$ok(abs(pacerTargetElapsedAtDistance($plan, 0)) < .01, 'curve must begin at zero');
$ok(abs(pacerTargetElapsedAtDistance($plan, 10000) - 3120) < .01, 'curve must finish at target');
$ok(pacerTargetElapsedAtDistance($plan, 4300) > pacerTargetElapsedAtDistance($plan, 4000), 'curve must interpolate within a segment');
foreach (['even', 'negative_split', 'positive_split'] as $strategy) {
    $segments = pacerGeneratedSegments(21097.5, 7200, $strategy, 10, ['segment_distance_m' => 2000]);
    $curvePlan = ['target_distance_m' => 21097.5, 'segments' => $segments];
    $ok(abs(pacerTargetElapsedAtDistance($curvePlan, 21097.5) - 7200) < .001, "{$strategy} curve must finish exactly at target");
    $ok(pacerTargetElapsedAtDistance($curvePlan, 4321.25) > 0, "{$strategy} curve must support non-round distances");
}
$custom = ['target_distance_m' => 5000.0, 'segments' => pacerValidateSegments([
    ['start_distance_m' => 0, 'end_distance_m' => 2500, 'target_pace_s_per_km' => 320],
    ['start_distance_m' => 2500, 'end_distance_m' => 5000, 'target_pace_s_per_km' => 280],
], 5000, 10)];
$ok(abs(pacerTargetElapsedAtDistance($custom, 3000) - 940) < .001, 'custom curve must interpolate from the current segment boundary');
$ok(abs(pacerFinalPhaseDistance(['target_distance_m' => 1500], pacerDefaultRules()) - 150) < .001, 'final phase lower distance remains bounded');
$ok(abs(pacerFinalPhaseDistance(['target_distance_m' => 42195], pacerDefaultRules()) - 1000) < .001, 'marathon final phase remains capped');
$legacy = $plan; $legacy['guidance_rules'] = ['rules_version' => 1];
$legacyResult = pacerEvaluate($legacy, ['distance_m' => 1000, 'elapsed_s' => 312, 'moving_time_s' => 312, 'recent_pace_s_per_km' => 312]);
$ok($legacyResult['rules']['goal_mode'] === 'target_time', 'v1 rules default to target_time');
$ok($legacyResult['timing']['ahead_behind_s'] === $legacyResult['ahead_behind_s'], 'v1 timing field remains compatible');
$paused = pacerEvaluate($plan, ['distance_m' => 2000, 'elapsed_s' => 700, 'moving_time_s' => 600, 'recent_pace_s_per_km' => 360, 'paused' => true]);
$ok($paused['code'] === 'paused', 'pause has priority');
$poor = pacerEvaluate($plan, ['distance_m' => 2000, 'elapsed_s' => 700, 'moving_time_s' => 700, 'recent_pace_s_per_km' => 360, 'gps_quality' => 'poor']);
$ok($poor['code'] === 'gps_unreliable', 'poor GPS suppresses pace correction');
$degraded = pacerEvaluate($plan, ['distance_m' => 2000, 'elapsed_s' => 640, 'moving_time_s' => 640, 'recent_pace_s_per_km' => 325, 'gps_quality' => 'degraded']);
$ok($degraded['code'] === 'on_target', 'degraded GPS expands the pace tolerance conservatively');
$hr = $plan; $hr['segments'][0]['heart_rate_ceiling_bpm'] = 150;
$one = pacerEvaluate($hr, ['distance_m' => 1000, 'elapsed_s' => 330, 'moving_time_s' => 330, 'recent_pace_s_per_km' => 330, 'heart_rate_bpm' => 155]);
$two = pacerEvaluate($hr, ['distance_m' => 1100, 'elapsed_s' => 363, 'moving_time_s' => 346, 'recent_pace_s_per_km' => 330, 'heart_rate_bpm' => 155, 'guidance_state' => $one['next_state']]);
$ok($two['code'] === 'hr_limit', 'persistent HR ceiling has priority');
$best = $plan; $best['guidance_rules'] = array_replace(pacerDefaultRules(), ['goal_mode' => 'best_effort']);
$ahead = pacerEvaluate($best, ['distance_m' => 3000, 'elapsed_s' => 900, 'moving_time_s' => 900, 'recent_pace_s_per_km' => 280, 'average_pace_s_per_km' => 300]);
$ok($ahead['code'] !== 'slow_down', 'best effort does not punish being ahead');
$recoveringState = ['ahead_behind_s' => 15, 'candidate_code' => 'speed_up', 'candidate_since_s' => 0, 'segment_order' => 1];
$recovering = pacerEvaluate($plan, ['distance_m' => 1100, 'elapsed_s' => pacerTargetElapsedAtDistance($plan, 1100) + 9, 'moving_time_s' => pacerTargetElapsedAtDistance($plan, 1100) + 9, 'recent_pace_s_per_km' => 350, 'guidance_state' => $recoveringState]);
$ok($recovering['code'] === 'recovering', 'an improving accumulated delay returns recovering instead of repeated speed_up');
$opportunity = pacerOpportunity($best, 9000, 2814, 260, 260, pacerNormalizeRules($best['guidance_rules']));
$ok($opportunity['available'] && $opportunity['target_finish_s'] === 3060.0, 'plausible best-effort projection offers the next whole-minute target');
$impossible = pacerOpportunity($best, 9000, 2814, 310, 260, pacerNormalizeRules($best['guidance_rules']));
$ok(!$impossible['available'], 'implausible late opportunity is rejected');
$finalAvailable = pacerEvaluate($plan, ['distance_m' => 9200, 'elapsed_s' => pacerTargetElapsedAtDistance($plan, 9200), 'moving_time_s' => pacerTargetElapsedAtDistance($plan, 9200), 'recent_pace_s_per_km' => 290]);
$ok($finalAvailable['code'] === 'final_phase_available', 'final phase offers an optional invitation when the plan is on target');
$finalPush = pacerEvaluate($plan, ['distance_m' => 9200, 'elapsed_s' => pacerTargetElapsedAtDistance($plan, 9200), 'moving_time_s' => pacerTargetElapsedAtDistance($plan, 9200), 'recent_pace_s_per_km' => 260]);
$ok($finalPush['code'] === 'final_push', 'sustained faster final pace receives final_push');
$finalKick = pacerEvaluate($plan, ['distance_m' => 9900, 'elapsed_s' => pacerTargetElapsedAtDistance($plan, 9900), 'moving_time_s' => pacerTargetElapsedAtDistance($plan, 9900), 'recent_pace_s_per_km' => 260]);
$ok($finalKick['code'] === 'final_kick', 'final kick is limited to the final configured distance');
$milestone = pacerEvaluate($plan, ['distance_m' => 4100, 'elapsed_s' => pacerTargetElapsedAtDistance($plan, 4100), 'moving_time_s' => pacerTargetElapsedAtDistance($plan, 4100), 'recent_pace_s_per_km' => 310, 'guidance_state' => ['segment_order' => 3, 'last_milestone' => 3]]);
$ok($milestone['code'] === 'milestone', 'milestone triggers after crossing rather than requiring an exact boundary');
$movingPlan = $plan; $movingPlan['guidance_rules'] = array_replace(pacerDefaultRules(), ['clock_mode' => 'moving']);
$movingClock = pacerEvaluate($movingPlan, ['distance_m' => 1000, 'elapsed_s' => 400, 'moving_time_s' => 320, 'recent_pace_s_per_km' => 320]);
$ok($movingClock['timing']['clock_s'] === 320.0, 'moving clock mode uses moving_time_s for accumulated timing');
$invalid = false; try { pacerEvaluate($plan, ['distance_m' => 1, 'elapsed_s' => 1, 'moving_time_s' => 1, 'recent_pace_s_per_km' => 0]); } catch (InvalidArgumentException) { $invalid = true; }
$ok($invalid, 'non-positive supplied pace is rejected instead of becoming a guidance signal');
$finish = pacerEvaluate($plan, ['distance_m' => 10000, 'elapsed_s' => 3100, 'moving_time_s' => 3100]);
$ok($finish['code'] === 'finished' && $finish['ahead_behind_s'] === -20.0, 'finish preserves final timing');
printf("✓ Pacer v2 static: %d assertions\n", $n);
