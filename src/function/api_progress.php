<?php

declare(strict_types=1);

require_once __DIR__ . '/progress_service.php';

function stridebr_api_progress_overview(PDO $pdo, string $userId, array $filters): array
{
    return progressOverview($pdo, $userId, $filters);
}

function stridebr_api_progress_timeseries(PDO $pdo, string $userId, array $filters): array
{
    return progressTimeseries($pdo, $userId, $filters);
}

function stridebr_api_progress_sports(PDO $pdo, string $userId, array $filters): array
{
    return progressSports($pdo, $userId, $filters);
}

function stridebr_api_progress_calendar(PDO $pdo, string $userId, array $filters): array
{
    return progressCalendar($pdo, $userId, $filters);
}

function stridebr_api_progress_cardio(PDO $pdo, string $userId, array $filters): array
{
    return progressCardio($pdo, $userId, $filters);
}

function stridebr_api_progress_strength(PDO $pdo, string $userId, array $filters): array
{
    return progressStrength($pdo, $userId, $filters);
}

function stridebr_api_progress_exercises(PDO $pdo, string $userId, array $filters): array
{
    return progressExerciseList($pdo, $userId, $filters);
}

function stridebr_api_progress_exercise(PDO $pdo, string $userId, string $exerciseId, array $filters): array
{
    return progressExerciseDetail($pdo, $userId, $exerciseId, $filters);
}

function stridebr_api_progress_adherence(PDO $pdo, string $userId, array $filters): array
{
    $range = progressResolveRange($filters);
    $sport = progressResolveSport($pdo, $filters['sport'] ?? null);
    return ['range' => progressRangePayload($range), 'sport' => $sport] + progressAdherence($pdo, $userId, $range, $sport);
}

function stridebr_api_progress_dashboard(PDO $pdo, string $userId, array $filters): array
{
    return progressDashboard($pdo, $userId, $filters);
}
