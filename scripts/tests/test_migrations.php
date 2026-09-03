<?php
return function (PDO $pdo): void {
    $files = array_map('basename', glob(dirname(__DIR__, 2) . '/src/database/migrations/*.sql') ?: []);
    sort($files);
    $applied = $pdo->query('SELECT version FROM public.stridebr_schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
    sort($applied);
    AlphaTest::same($files, $applied, 'Todas as migrations do repositório devem estar registradas');
    foreach (['usuarios', 'cronogramas', 'registros_atividade', 'rotas_atividade', 'sessoes_treino', 'amizades', 'cronograma_compartilhamentos', 'vinculos_treinador_atleta'] as $table) {
        AlphaTest::assert((bool) $pdo->query("SELECT to_regclass('stridebr.$table')")->fetchColumn(), "Tabela final ausente: $table");
    }
};
