<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/includes/app.php';
require __DIR__ . '/../src/config/pg_config.php';
require __DIR__ . '/../src/function/atividade_modelo.php';

$idUsuario = 'test_stridebr_runner';
$idRegistro = null;

try {
    $pdo->prepare('DELETE FROM usuarios WHERE idusuario = :id')->execute([':id' => $idUsuario]);
    $pdo->prepare('INSERT INTO usuarios (idusuario, nomeusuario, emailusuario, senhausuario) VALUES (:id, :nome, :email, :senha)')->execute([
        ':id' => $idUsuario,
        ':nome' => 'Teste StrideBR',
        ':email' => 'stridebr-integration-test@example.invalid',
        ':senha' => password_hash('stridebr-test-password', PASSWORD_DEFAULT),
    ]);

    $campos = atividadeBuscarCamposModelo($pdo, 'md_dardo');
    $porSlug = [];
    foreach ($campos as $campo) {
        $porSlug[$campo['slug']] = $campo['idcampo'];
    }

    $idRegistro = atividadeSalvarRegistro($pdo, $idUsuario, [
        'idmodelo' => 'md_dardo',
        'titulo' => 'Teste de integração',
        'data_inicio' => date('Y-m-d H:i'),
        'status' => 'concluido',
        'visibilidade' => 'privado',
        'record_values' => [],
        'unidades' => [
            [
                'rotulo' => 'Tentativa 1',
                'values' => [
                    $porSlug['distancia'] => '42.30',
                    $porSlug['valida'] => '1',
                ],
            ],
            [
                'rotulo' => 'Tentativa 2',
                'values' => [
                    $porSlug['distancia'] => '44.10',
                    $porSlug['valida'] => '1',
                ],
            ],
        ],
    ]);

    $registro = atividadeCarregarRegistro($pdo, $idRegistro, $idUsuario);
    if ($registro === [] || count($registro['unidades']) !== 2) {
        throw new RuntimeException('O registro salvo não retornou as duas unidades esperadas.');
    }

    if (!atividadeExcluirRegistro($pdo, $idRegistro, $idUsuario)) {
        throw new RuntimeException('Não foi possível excluir o registro de teste.');
    }
    $idRegistro = null;

    $pdo->prepare('DELETE FROM usuarios WHERE idusuario = :id')->execute([':id' => $idUsuario]);
    echo "Teste de atividade concluído com sucesso.\n";
} catch (Throwable $e) {
    if ($idRegistro !== null) {
        $pdo->prepare('DELETE FROM registros_atividade WHERE idregistro = :id')->execute([':id' => $idRegistro]);
    }
    $pdo->prepare('DELETE FROM usuarios WHERE idusuario = :id')->execute([':id' => $idUsuario]);
    fwrite(STDERR, "Falha no teste de atividade: {$e->getMessage()}\n");
    exit(1);
}
