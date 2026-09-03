<?php
return function (PDO $pdo): void {
    $a = alphaTestUser($pdo, 'friend_a');
    $b = alphaTestUser($pdo, 'friend_b');
    $insert = $pdo->prepare('INSERT INTO amizades (idamizade, idusuario_solicitante, idusuario_destino) VALUES (:id, :a, :b)');
    $insert->execute([':id' => stridebr_generate_id(), ':a' => $a, ':b' => $b]);
    AlphaTest::throws(fn() => $insert->execute([':id' => stridebr_generate_id(), ':a' => $b, ':b' => $a]), 'Solicitação duplicada invertida foi aceita');
    AlphaTest::throws(fn() => $insert->execute([':id' => stridebr_generate_id(), ':a' => $a, ':b' => $a]), 'Autoamizade foi aceita');
    $stmt = $pdo->prepare("UPDATE amizades SET status = 'aceita' WHERE idusuario_solicitante = :a AND idusuario_destino = :b AND status = 'pendente'");
    $stmt->execute([':a' => $a, ':b' => $b]);
    AlphaTest::same(1, $stmt->rowCount(), 'Solicitação não foi aceita');
};
