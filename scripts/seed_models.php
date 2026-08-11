<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/config/pg_config.php';
require __DIR__ . '/../src/function/atividade_modelo.php';

try {
    $created = atividadeGarantirModelosPadrao($pdo);
    echo "Modelos garantidos: " . count($created) . "\n";
    foreach ($created as $slug => $info) {
        echo "- " . $slug . " => " . ($info['idmodelo'] ?? '') . "\n";
    }
} catch (Throwable $e) {
    echo "Erro ao garantir modelos: " . $e->getMessage() . "\n";
}

?>