<?php
return function (PDO $pdo): void {
    $trainer = alphaTestUser($pdo, 'trainer_a', ['trainer' => true]);
    $athlete = alphaTestUser($pdo, 'trainer_b');
    $intruder = alphaTestUser($pdo, 'trainer_c');
    $link = treinadorCriarConvite($pdo, $trainer, 'alpha_trainer_b', 'treinador');
    AlphaTest::throws(fn() => treinadorResponderVinculo($pdo, $intruder, $link, 'aceitar'), 'Terceiro aceitou vínculo alheio');
    treinadorResponderVinculo($pdo, $athlete, $link, 'aceitar');
    AlphaTest::assert(treinadorVinculoAceito($pdo, $trainer, $athlete) !== [], 'Vínculo não foi aceito');
    treinadorAtualizarPermissoes($pdo, $athlete, $link, ['pode_prescrever' => '1']);
    AlphaTest::throws(fn() => treinadorAtualizarPermissoes($pdo, $intruder, $link, ['pode_prescrever' => '1']), 'Terceiro mudou permissões do atleta');
    treinadorEncerrarVinculo($pdo, $athlete, $link);
    AlphaTest::same([], treinadorVinculoAceito($pdo, $trainer, $athlete), 'Vínculo encerrado continuou autorizado');
};
