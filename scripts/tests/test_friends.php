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

    $c = alphaTestUser($pdo, 'friend_c');
    $d = alphaTestUser($pdo, 'friend_d');
    $pending = $pdo->prepare('INSERT INTO amizades (idamizade, idusuario_solicitante, idusuario_destino) VALUES (:id, :a, :b)');
    $pending->execute([':id' => stridebr_generate_id(), ':a' => $c, ':b' => $d]);
    $outgoing = $pdo->prepare("SELECT idusuario_destino FROM amizades WHERE idusuario_solicitante=:me AND status='pendente' ORDER BY data_criacao DESC");
    $outgoing->execute([':me' => $c]);
    AlphaTest::same($d, (string) $outgoing->fetchColumn(), 'Solicitação enviada não apareceu para o remetente');
    $incoming = $pdo->prepare("SELECT idusuario_solicitante FROM amizades WHERE idusuario_destino=:me AND status='pendente' ORDER BY data_criacao DESC");
    $incoming->execute([':me' => $d]);
    AlphaTest::same($c, (string) $incoming->fetchColumn(), 'Solicitação recebida não apareceu para o destinatário');
    $cancel = $pdo->prepare("DELETE FROM amizades WHERE idusuario_solicitante=:me AND idusuario_destino=:target AND status='pendente'");
    $cancel->execute([':me' => $d, ':target' => $c]);
    AlphaTest::same(0, $cancel->rowCount(), 'Destinatário cancelou solicitação enviada por outra pessoa');
    $exists = $pdo->prepare("SELECT COUNT(*) FROM amizades WHERE idusuario_solicitante=:a AND idusuario_destino=:b AND status='pendente'");
    $exists->execute([':a'=>$c, ':b'=>$d]);
    AlphaTest::same(1, (int) $exists->fetchColumn(), 'Tentativa alheia removeu solicitação pendente');
    $cancel->execute([':me' => $c, ':target' => $d]);
    AlphaTest::same(1, $cancel->rowCount(), 'Remetente não conseguiu cancelar solicitação pendente');
    $accepted = $pdo->prepare("SELECT COUNT(*) FROM amizades WHERE idusuario_solicitante=:a AND idusuario_destino=:b AND status='aceita'");
    $accepted->execute([':a'=>$a, ':b'=>$b]);
    AlphaTest::same(1, (int) $accepted->fetchColumn(), 'Cancelar solicitação independente alterou amizade existente');
};
