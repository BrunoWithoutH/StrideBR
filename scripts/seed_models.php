<?php

declare(strict_types=1);

require __DIR__ . '/../src/config/pg_config.php';

$sql = file_get_contents(__DIR__ . '/../src/database/stridebr_seed.sql');
if ($sql === false) {
    fwrite(STDERR, "Não foi possível ler o arquivo de seed.\n");
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "Seed aplicado com sucesso.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Falha ao aplicar o seed: {$e->getMessage()}\n");
    exit(1);
}
