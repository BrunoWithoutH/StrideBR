<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$signup = (string) file_get_contents($root . '/public/signup.php');
$js = (string) file_get_contents($root . '/public/assets/js/onboarding.js');
$en = (string) file_get_contents($root . '/src/i18n/en.php');
$pt = (string) file_get_contents($root . '/src/i18n/pt-BR.php');

$runtimePos = strpos($signup, 'stridebr_i18n_runtime_script(true)');
$onboardingScriptPos = strpos($signup, "/assets/js/onboarding.js");

$checks = [
    'runtime i18n carrega antes do onboarding no signup' => $runtimePos !== false && $onboardingScriptPos !== false && $runtimePos < $onboardingScriptPos,
    'contagem inicial do signup usa chave traduzida' => str_contains($signup, "stridebr_t('onboarding.step_count', ['step' => 1, 'total' => 5])") && !str_contains($signup, '>1 de 5<'),
    'locale EN tem contagem humana completa' => str_contains($en, "'onboarding.step_count' => 'Step {step} of {total}'"),
    'locale PT tem contagem humana completa' => str_contains($pt, "'onboarding.step_count' => 'Etapa {step} de {total}'"),
    'skip step existe nos dois locales' => str_contains($en, "'onboarding.skip_step' => 'Skip step'") && str_contains($pt, "'onboarding.skip_step' => 'Pular etapa'"),
    'onboarding possui fallback humano local' => str_contains($js, 'const onboardingFallbacks =') && str_contains($js, "'onboarding.optional_step': 'Optional step'") && str_contains($js, "'onboarding.step_count': 'Step {step} of {total}'"),
    'fallback do onboarding não devolve key técnica' => !str_contains($js, 'fallback ?? key') && str_contains($js, 'return replaceFallback(humanFallback, values)'),
    'tr detecta runtime que devolveu a própria key' => str_contains($js, "String(translated).toLowerCase() !== String(key).toLowerCase()"),
    'CTA inicial usa skip step sem esporte selecionado' => str_contains($js, "signupFlow && index === 0 && !hasSportSelection ? 'onboarding.skip_step' : 'common.continue'"),
    'CTA reage a seleção do esporte' => str_contains($js, 'syncPrimaryAction()') && str_contains($js, "root.addEventListener('change'"),
    'create account permanece exclusivo do submit final' => substr_count($signup, "stridebr_t('auth.create_account')") === 1 && str_contains($signup, 'data-finish-step hidden'),
    'onboarding se recupera se runtime chegar depois' => str_contains($js, "document.addEventListener('stridebr:i18n-ready'"),
];

$failed = [];
foreach ($checks as $label => $ok) if (!$ok) $failed[] = $label;
if ($failed !== []) {
    fwrite(STDERR, "Falhas no onboarding i18n static:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo '✓ onboarding i18n static: ' . count($checks) . " assertions\n";
