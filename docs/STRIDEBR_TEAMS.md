# StrideBR Teams

## Estado

**Planejado para 2027.**

A especificação deve começar ainda em 2026, sem bloquear a publicação e estabilização da Web 1.0.

O primeiro piloto pretendido é o **IFFar — Campus Frederico Westphalen**. A arquitetura deve ser preparada para múltiplas organizações, mas não haverá cadastro público irrestrito de organizações no primeiro lançamento.

## Objetivo

O StrideBR Teams é a frente do StrideBR para acompanhamento esportivo coletivo.

A proposta é conectar:

```text
organização → equipe → treinador → atleta → planejamento → execução → acompanhamento → competição → evolução
```

O produto não pretende substituir o treinador. Ele deve organizar informações e reduzir trabalho manual para que treinadores e responsáveis acompanhem atletas e equipes com maior clareza.

## Estrutura conceitual

A modelagem futura deve suportar, sem hardcode por instituição:

- organizações;
- unidades ou campi;
- temporadas;
- delegações;
- equipes;
- modalidades;
- subgrupos quando necessários;
- atletas;
- treinadores;
- auxiliares;
- responsáveis gerais;
- vínculos e permissões.

Exemplo inicial:

```text
Instituto Federal Farroupilha
└── Campus Frederico Westphalen
    └── Temporada 2027
        ├── Atletismo
        │   ├── velocidade
        │   ├── meio-fundo
        │   ├── fundo
        │   └── demais grupos quando necessários
        ├── Voleibol
        ├── Futebol
        ├── Futsal
        └── Tênis de mesa
```

Um atleta pode participar de mais de uma equipe ou grupo.

## Papéis e permissões

O Teams não deve tratar `treinador` como autorização para visualizar toda a conta de um atleta.

O desenho futuro deve permitir escopos como:

- responsável da organização;
- responsável do campus ou delegação;
- treinador de uma modalidade/equipe;
- auxiliar;
- atleta.

O acesso deve ser limitado ao necessário para a função. Dados pessoais sensíveis, especialmente informações de saúde, desconfortos e lesões, devem possuir proteção adicional.

## Teams MVP

A primeira versão útil deve ser pequena.

### Fundação

- organização;
- unidade/campus;
- temporada;
- equipe/modalidade;
- atletas;
- treinadores;
- vínculos;
- convites;
- permissões básicas.

### Planejamento e execução

- treinador cria ou atribui treino;
- atleta recebe o treino na própria conta;
- atleta registra, importa ou executa a atividade;
- atividade pode ser vinculada ao treino planejado;
- treinador visualiza conclusão e dados autorizados;
- acompanhamento simples de presença e volume.

O MVP deve reaproveitar cronogramas, atividades, usuários e integrações existentes. Teams é um novo contexto do produto, não um segundo StrideBR isolado.

## Treinamento por elementos

Nem todo desenvolvimento esportivo pode ser descrito apenas por distância ou duração.

Cada modalidade poderá possuir elementos próprios de treinamento.

Exemplos:

### Atletismo

- velocidade;
- resistência;
- aceleração;
- técnica;
- força;
- potência;
- mobilidade.

### Futebol

- passe;
- finalização;
- posse;
- marcação;
- bola parada;
- velocidade;
- resistência.

### Tênis de mesa

- saque;
- recepção;
- forehand;
- backhand;
- deslocamento;
- jogo.

O treinador poderá acompanhar não apenas quanto o atleta treinou, mas quais componentes foram trabalhados ao longo da semana ou temporada.

## Planejado x realizado

O Teams deve evoluir para comparar o que foi prescrito com o que realmente aconteceu.

Exemplo:

```text
Planejado: 6 × 400 m
Executado: 6 × 400 m
Volume previsto: 7,2 km
Volume realizado: 7,6 km
```

Com Activity Timeline e importação enriquecida, a comparação pode posteriormente considerar cada intervalo, ritmo, FC, recuperação e outros streams.

## Visão do treinador

A interface deve priorizar informação operacional antes de gráficos decorativos.

Exemplos de informação útil:

- quem treinou hoje;
- quem possui treino pendente;
- volume da semana;
- sessões leves, moderadas e intensas;
- elementos trabalhados;
- tendência de carga;
- preparação para eventos;
- atletas que exigem atenção do treinador.

A classificação de "atenção" deve ser transparente e conservadora. O produto não deve apresentar diagnósticos médicos ou afirmações clínicas sem base adequada.

## Presença e associação de atividades

Treinos coletivos podem ser cadastrados previamente.

Quando um atleta registra ou importa uma atividade compatível no mesmo período, o StrideBR poderá sugerir a associação ao treino da equipe.

Isso reduz a necessidade de manter chamada, planilha e aplicativo como registros independentes.

## Percepção de esforço e recuperação

Uma etapa posterior pode permitir check-ins simples do atleta, como:

- RPE/percepção de esforço;
- fadiga;
- recuperação;
- dor muscular;
- disposição;
- sono;
- desconforto opcional.

Esses dados complementam métricas objetivas, sem substituir avaliação profissional.

## Lesões e interrupções

Uma fase futura poderá manter histórico de intercorrências esportivas.

Exemplos de dados:

- região afetada;
- início e fim;
- atividade relacionada;
- volume anterior;
- período afastado;
- retorno progressivo.

A primeira função desta área é **registro e contexto**, não previsão de lesão.

Somente com base histórica suficiente e metodologia adequada poderão ser investigadas correlações entre carga, fortalecimento, tipos de treino, recuperação e ocorrências. Correlação não deve ser apresentada como causalidade.

## Competições

Teams deve se integrar ao módulo de eventos para acompanhar preparação e resultados.

No contexto inicial do IFFar, exemplos incluem:

- JEIF;
- JIF Sul;
- JIF Nacional;
- outras competições relevantes.

Possibilidades:

- atletas convocados;
- modalidade e prova;
- resultado;
- tempo ou marca;
- colocação;
- recorde pessoal;
- evolução entre temporadas.

## Temporadas e histórico

A organização por temporada deve permitir preservar o contexto de cada ano sem apagar vínculos históricos.

Exemplo:

```text
2027
├── atletas
├── equipes
├── treinamentos
└── competições

2028
├── atletas
├── equipes
├── treinamentos
└── competições
```

## Pesquisa e análise longitudinal

Com consentimento, governança adequada e dados suficientes, o Teams pode futuramente apoiar análises acadêmicas e esportivas, como:

- evolução do desempenho;
- distribuição de carga;
- comparação entre temporadas;
- relação entre estímulos e resultados;
- padrões de interrupção e retorno;
- estudos agregados e anonimizados.

Esta frente é posterior ao produto operacional e não deve atrasar o MVP.

## Privacidade e menores de idade

O piloto pode incluir estudantes menores de idade. Portanto, privacidade, consentimento e minimização de dados fazem parte do produto, não são um detalhe posterior.

Antes de disponibilizar módulos de saúde, recuperação ou pesquisa, devem ser definidos:

- base legal e consentimentos necessários;
- responsáveis autorizados;
- duração de retenção;
- política de exportação e exclusão;
- acesso por papel;
- uso de dados agregados/anonimizados;
- requisitos aplicáveis da LGPD.

## Estratégia de lançamento

### Final de 2026

- conversar com professores e treinadores;
- observar o fluxo real de preparação esportiva;
- levantar informações atualmente registradas;
- validar o que vale ou não ser digitalizado;
- desenhar permissões e entidades;
- prototipar interfaces principais.

### 2027 — piloto

Começar pelo IFFar — Campus Frederico Westphalen com escopo controlado.

O piloto deve validar o valor do sistema antes da expansão para outras organizações.

### Expansão

A arquitetura deve suportar novos campi, clubes, assessorias e instituições futuramente, mas a liberação não precisa ser automática nem pública desde o início.

## Subdomínio

`teams.stridebr.com.br` é uma possibilidade de apresentação futura.

O subdomínio não implica um codebase, banco ou autenticação separados. A preferência é manter o mesmo domínio de dados e serviços do StrideBR e expor o Teams como uma área integrada do produto.

## Perguntas para validação com treinadores

Antes do desenvolvimento pesado, confirmar:

- Como os treinos são planejados hoje?
- Como é feito o acompanhamento dos atletas?
- Quais dados já são registrados?
- O que deveria ser acompanhado, mas hoje não é?
- O que o treinador precisa visualizar semanalmente?
- O que seria trabalhoso demais para registrar?
- Quais dados devem ser privados?
- Quem deve visualizar cada categoria de informação?
- O que realmente economizaria trabalho?
- Como o sistema poderia ajudar na preparação para JEIF, JIF Sul e JIF Nacional?

## Fora do MVP

Não entram na primeira etapa:

- previsão automatizada de lesões;
- modelos de risco clínico;
- pesquisa longitudinal avançada;
- marketplace de treinadores;
- cadastro público irrestrito de organizações;
- cobrança complexa por instituição;
- ranking social entre equipes.
