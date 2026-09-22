<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$router = $read('public/api/v1/index.php');
$mobile = $read('src/function/api_mobile_completion.php');
$sessionApi = $read('src/function/api_workout_sessions.php');
$people = $read('src/function/people_service.php');
$openapi = $read('docs/api/openapi.yaml');
$foundationMigration = $read('src/database/migrations/20260815_product_foundation.sql');
$v1Migration = $read('src/database/migrations/20260903_v1_rc.sql');
$freeze = $read('docs/MOBILE_CONTRACT_FREEZE_V1.md');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$section = static function (string $source, string $from, string $to): string {
    $start = strpos($source, $from);
    if ($start === false) return '';
    $end = strpos($source, $to, $start + strlen($from));
    return $end === false ? substr($source, $start) : substr($source, $start, $end - $start);
};

$assert(str_contains($router, "['data' => ['session' => \$result['session'], 'set_id' => \$result['set_id'], 'reused' => !empty(\$result['reused'])]]"), 'Append Set precisa manter session/set_id/reused dentro de data.');
$assert(str_contains($sessionApi, "\$payload['actual_repetitions'] ?? (\$payload['repetitions'] ?? '')"), 'PATCH set precisa aceitar actual_repetitions canônico.');
$assert(str_contains($sessionApi, "\$payload['actual_load'] ?? (\$payload['load'] ?? '')"), 'PATCH set precisa aceitar actual_load canônico.');
$assert(str_contains($sessionApi, "\$payload['actual_duration_s'] ?? (\$payload['duration_s']"), 'PATCH set precisa aceitar actual_duration_s canônico.');
$assert(str_contains($sessionApi, "\$payload['actual_distance_m'] ?? (\$payload['distance_m']"), 'PATCH set precisa aceitar actual_distance_m canônico.');
$assert(str_contains($mobile, "'completed' => stridebr_db_bool(\$set['concluida'] ?? false)"), 'Activity strength precisa normalizar completed pelo boolean canônico.');
$assert(str_contains($mobile, "'duration_s' => \$set['duracao_segundos'] !== null ? (float)"), 'Activity strength duration_s precisa permanecer numérico real no payload.');
$assert(str_contains($mobile, "'actual_repetitions'=>\$actualRepetitions") && str_contains($mobile, "'repetitions'=>\$actualRepetitions"), 'Execution summary precisa expor actual e alias realizado sem prescription fallback.');
$assert(str_contains($mobile, "'activity'=>!empty(\$session['idregistro_atividade'])"), 'Execution summary de sessão precisa expor activity.');
$assert(str_contains($mobile, "'execution_mode'=>'quick_register'") && str_contains($mobile, "'exercises'=>[]"), 'Quick Register summary precisa permanecer sem sets fabricados.');
$assert(str_contains($mobile, "\$payload['bio']") && str_contains($mobile, "\$payload['phone']") && str_contains($mobile, "\$payload['birth_date']"), 'PATCH /me precisa manter campos simples congelados.');
$personSection = $section($people, 'function peoplePersonPayload', 'function peopleFriendshipStatus');
$assert(str_contains($personSection, "'display_name' =>") && !str_contains($personSection, "'name' =>"), 'Person DTO precisa usar display_name e não name.');
$coachingSection = $section($people, 'function peopleCoachingPayload', 'function peopleCoaching(');
$assert(str_contains($coachingSection, "'trainer' =>") && str_contains($coachingSection, "'athlete' =>"), 'Coaching DTO precisa manter trainer + athlete.');
$assert(str_contains($foundationMigration, "('friends.enabled', TRUE") && str_contains($v1Migration, "('trainer.enabled', TRUE"), 'Feature flags friends/trainer precisam nascer TRUE nas migrations normais.');
$appendPath = $section($openapi, '  /workout-sessions/{session_id}/exercises/{exercise_id}/sets:', '  /workout-sessions/by-workout/{workout_id}:');
$assert(str_contains($appendPath, "schema: { \$ref: '#/components/schemas/AppendSetResponse' }") && !str_contains($appendPath, 'requestBody:'), 'OpenAPI Append Set precisa documentar header-only e resposta congelada.');
$setSchema = $section($openapi, '    WorkoutSessionSetUpdateRequest:', '    WorkoutSessionToggleRequest:');
foreach (['actual_repetitions','actual_load','actual_duration_s','actual_distance_m'] as $field) $assert(str_contains($setSchema, $field . ':'), "OpenAPI PATCH set precisa documentar {$field}.");
$executionSet = $section($openapi, '    WorkoutExecutionSet:', '    WorkoutExecutionExercise:');
$assert(str_contains($executionSet, 'actual_repetitions: { type: string') && str_contains($executionSet, 'actual_load: { type: string'), 'OpenAPI execution actual reps/load precisam permanecer strings.');
foreach (['repetitions:','load:','duration_s:','distance_m:'] as $field) $assert(str_contains($executionSet, $field), "OpenAPI execution summary precisa congelar alias {$field}");
$executionSummary = $section($openapi, '    WorkoutExecutionSummary:', '    SelfProfile:');
$assert(str_contains($executionSummary, 'activity_id:') && str_contains($executionSummary, 'activity:'), 'OpenAPI execution summary precisa documentar activity_id + activity.');
$strengthSet = $section($openapi, '    ActivityStrengthSet:', '    ActivityStrengthExercise:');
$assert(str_contains($strengthSet, 'duration_s: { type: number, format: double'), 'OpenAPI Activity strength duration_s precisa aceitar número não inteiro.');
foreach (['number','type','repetitions','load_kg','duration_s','distance_m','rir','rpe','completed','notes'] as $field) $assert(str_contains($strengthSet, $field), "Activity strength schema precisa manter {$field}.");
$selfProfile = $section($openapi, '    SelfProfile:', '    SelfProfileResponse:');
foreach (['bio','phone','birth_date','profile_visibility','discoverable'] as $field) $assert(str_contains($selfProfile, $field . ':'), "OpenAPI /me precisa manter {$field}.");
$personSchema = $section($openapi, '    PersonPublic:', '    PeopleCapabilities:');
$assert(str_contains($personSchema, 'display_name:') && !preg_match('/^\s+name:/m', $personSchema), 'OpenAPI PersonPublic precisa usar display_name.');
$coachingSchema = $section($openapi, '    CoachingLink:', '    CoachingData:');
$assert(str_contains($coachingSchema, 'trainer:') && str_contains($coachingSchema, 'athlete:'), 'OpenAPI CoachingLink precisa manter trainer + athlete.');
$manualPath = $section($openapi, '  /activities/manual:', '  /activities:');
$assert(str_contains($manualPath, 'ManualActivityCreateResponse') && str_contains($manualPath, 'Location:'), 'OpenAPI manual Activity precisa congelar ActivityDetail/reused e Location.');
$assert(str_contains($router, "header('Location: /api/v1/activities/'") && str_contains($router, "['data' => \$created['activity'], 'reused' => !empty(\$created['reused'])]"), 'HTTP manual Activity precisa retornar ActivityDetail/reused e Location.');
$manualRequest = $section($openapi, '    ManualActivityCreateRequest:', '    ActivityPatchRequest:');
$assert(str_contains($manualRequest, 'workout_id:'), 'OpenAPI manual Activity precisa manter workout_id suportado.');
$activityDetail = $section($openapi, '    ActivityDetail:', '    InstitutionalNamedRef:');
$assert(str_contains($activityDetail, 'version') && str_contains($activityDetail, 'updated_at') && str_contains($activityDetail, "capabilities: { \$ref: '#/components/schemas/ActivityMutationCapabilities' }"), 'ActivityDetail precisa referenciar capabilities e manter version/updated_at.');
$capabilities = $section($openapi, '    ActivityMutationCapabilities:', '    ActivitySummary:');
foreach (['can_edit','can_delete','can_edit_title','can_edit_notes','can_edit_effort','can_edit_visibility','can_edit_datetime','can_edit_sport','can_edit_equipment','can_edit_metrics','can_edit_strength','can_trim_route'] as $field) $assert(str_contains($capabilities, $field . ':'), "Activity capabilities precisa manter {$field}.");
$assert($freeze !== '' && str_contains($freeze, '# MOBILE CONTRACT FREEZE V1'), 'Documento de freeze precisa existir.');

echo "Core App Completion V1.1 static: {$assertions} assertions\n";
