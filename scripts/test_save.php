<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/config/pg_config.php';
require __DIR__ . '/../src/function/atividade_modelo.php';

try {
    // Ensure models exist
    $models = atividadeGarantirModelosPadrao($pdo);
    $first = reset($models);
    if (!$first) {
        throw new Exception('Nenhum modelo disponível');
    }
    $idmodelo = $first['idmodelo'];
    $idmodalidade = $first['idmodalidade'];

    $fields = atividadeBuscarCamposModelo($pdo, $idmodelo);

    $unit_values = [];
    foreach ($fields as $f) {
        // fill sample values based on type
        switch ($f['tipo_campo']) {
            case 'inteiro': $unit_values[$f['slug']] = 3600; break;
            case 'decimal': $unit_values[$f['slug']] = 5.3; break;
            case 'texto': case 'texto_longo': $unit_values[$f['slug']] = 'teste'; break;
            default: $unit_values[$f['slug']] = 'teste'; break;
        }
    }

    $payload = [
        'idusuario' => 'demo_stridebr01',
        'idmodalidade' => $idmodalidade,
        'idmodelo' => $idmodelo,
        'titulo' => 'Atividade teste',
        'observacoes' => 'Inserida via CLI',
        'data_inicio' => date('Y-m-d H:i:s'),
        'data_fim' => null,
        'status' => 'ativo',
        'unit_observacoes' => null,
        'field_list' => $fields,
        'unit_values' => $unit_values,
    ];

    $res = atividadeSalvarRegistro($pdo, $payload);
    echo "Salvo: " . json_encode($res) . "\n";

    $list = atividadeListarRegistros($pdo, 'demo_stridebr01');
    echo "List count: " . count($list) . "\n";
    if (count($list) > 0) {
        echo "First: " . json_encode($list[0]) . "\n";
    }
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

?>