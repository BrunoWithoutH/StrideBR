# Atividades v2

A reforma de Atividades transforma o cadastro manual numa interface compacta e orientada à tarefa, mantendo a modelagem flexível de modalidades, modelos, campos, unidades e valores.

## Entregue nesta etapa

- catálogo com 89 modalidades no seed, agrupadas por categoria;
- favoritos e ordenação por uso recente;
- seletor de esporte pesquisável, sem bloquear a rolagem da página;
- ocultação automática de `Formato` quando a modalidade só tem um modelo;
- hora segmentada `HH:MM`, com limite real de 00–23 / 00–59 e avanço automático;
- duração segmentada em horas, minutos e segundos;
- distância, duração e ritmo/velocidade/split interligados conforme a modalidade;
- resumo calculado em tempo real;
- campos opcionais expostos como chips `+ Campo`;
- esforço percebido de 1 a 10;
- histórico compacto com busca, filtro e painel lateral de detalhes;
- cadastro e associação de equipamentos, com distância e total de atividades acumulados;
- edição de atividade atualizada para os mesmos componentes;
- intensidade antiga preservada como campo opcional para compatibilidade com registros existentes.

## Migração

Em banco existente, execute antes de publicar o PHP novo:

```sql
src/database/migrations/20260903_v1_rc.sql
```

No pgAdmin, abra o Query Tool no banco do StrideBR, carregue o conteúdo desse arquivo e execute-o inteiro. O script usa uma transação e `SET search_path TO stridebr, public`.

Também foi adicionado ao `scripts/migrate_product.sh`.

## Estrutura adicionada

### `modalidades`

- `categoria`
- `icone`
- `ordem_catalogo`
- `metrica_derivada`

### `modalidades_usuario`

- `favorita`
- `ordem_preferencia`
- `ultimo_uso`

### `campos_modelo`

- `exibicao_padrao`
- `grupo_ui`

### `registros_atividade`

- `esforco_percebido`

### Equipamentos

- `equipamentos_usuario`
- `registros_atividade_equipamentos`

## Próximas etapas

O motor de calorias fica separado desta entrega. A ideia é usar uma tabela versionada de equivalentes metabólicos e escolher a metodologia conforme idade/modalidade, em vez de deixar coeficientes soltos no JavaScript.

O redesign dos cronogramas também fica como etapa seguinte, reaproveitando a mesma linguagem visual: toolbar compacta, sidebar contextual, mini calendário, grade temporal ocupando a viewport e faixas de horário recolhíveis.
