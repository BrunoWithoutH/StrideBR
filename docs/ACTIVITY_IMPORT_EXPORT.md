# Importação e exportação de atividades

## Escopo da v1

O StrideBR aceita arquivos de atividades em FIT, TCX e GPX. A importação é feita em duas etapas: análise/preview e confirmação. O arquivo não vira uma atividade antes da confirmação do usuário.

### FIT

O parser lê o cabeçalho e as mensagens FIT mais relevantes para atividades, incluindo `file_id`, `session` e `record`. Quando presentes, são preservados horário, esporte/subesporte, rota GPS, altitude, frequência cardíaca, cadência, potência, velocidade, distância e metadados básicos do dispositivo.

Arquivos FIT identificados como `course` ou `workout` não são convertidos silenciosamente em atividades. Eles aparecem como percurso ou treino estruturado no preview.

### TCX

`Activities` são tratados como atividades. `Courses` são reconhecidos como percursos. Trackpoints compatíveis preservam posição, altitude, distância, frequência cardíaca, cadência, velocidade e potência.

### GPX

Tracks com timestamps são tratados como atividades gravadas. Routes e tracks sem timestamps são tratados como percursos, evitando importar uma rota planejada como se fosse uma atividade concluída.

## Normalização

O parser produz um modelo intermediário independente do arquivo de origem. A atividade confirmada é salva pelo mesmo serviço usado pelos registros normais do StrideBR.

Os dados normalizados incluem:

- início, fim e duração;
- distância;
- ganho/perda e faixa de elevação;
- médias e máximos de sensores quando disponíveis;
- rota simplificada para o modelo de rotas do StrideBR;
- série temporal preservada para exportação e recursos futuros;
- formato, hash, dispositivo e metadados da origem.

A série temporal é limitada a 24.000 pontos e a rota usada pelo produto a 1.800 pontos. Arquivos originais de até 8 MiB são preservados no banco. Arquivos maiores mantêm hash e dados normalizados, mas o binário original não é armazenado.

## Duplicatas

A primeira verificação usa SHA-256 do arquivo. Se o arquivo ainda não tiver sido importado, o StrideBR procura atividades próximas pelo horário de início e compara duração/distância com tolerância. O preview informa a possível duplicata e exige confirmação explícita para importar mesmo assim.

## Exportação

Uma atividade pode ser exportada como:

- GPX, quando existe rota GPS;
- TCX;
- JSON no esquema `stridebr.activity-export.v1`;
- arquivo original FIT/TCX/GPX, quando a atividade veio de importação e o binário original foi preservado.

A v1 não sintetiza um novo arquivo FIT para atividades criadas manualmente. Quando a origem é FIT, o arquivo FIT original pode ser baixado sem conversão.

## Banco

Aplicar:

```text
src/database/migrations/20260903_v1_rc.sql
```

A tabela `atividade_importacoes` mantém preview, origem, dados normalizados e vínculo com o registro criado.

## APIs internas

```text
POST /api/atividade-importacao-preview.php
POST /api/atividade-importacao-confirmar.php
GET  /user/exportar-atividade.php?id=...&format=gpx|tcx|json|original
```

As duas operações POST exigem autenticação e CSRF.
