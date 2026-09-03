<?php
return function (PDO $pdo): void {
    $a = alphaTestUser($pdo, 'permission_a');
    $b = alphaTestUser($pdo, 'permission_b');
    $equipment = atividadeSalvarEquipamento($pdo, $a, ['nome' => 'Tênis Alpha', 'tipo' => 'tenis']);
    AlphaTest::throws(fn() => atividadeSalvarEquipamento($pdo, $b, ['nome' => 'IDOR', 'tipo' => 'tenis'], $equipment), 'Usuário editou equipamento alheio');
    AlphaTest::assert(!atividadeDefinirEquipamentoAtivo($pdo, $b, $equipment, false), 'Usuário desativou equipamento alheio');
    $schedule = cronogramaCriar($pdo, $a, 'Privado');
    AlphaTest::same([], cronogramaBuscar($pdo, $schedule, $b), 'Cronograma privado vazou por ID');
    AlphaTest::assert(!cronogramaExcluir($pdo, $schedule, $b), 'Cronograma alheio foi excluído');
};
