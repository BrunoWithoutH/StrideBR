# StrideBR — visão de produto

O StrideBR é uma plataforma esportiva para **planejar, executar, registrar, analisar e acompanhar atividades físicas** sem limitar o produto a uma única modalidade.

O objetivo não é ser apenas um diário de corrida ou uma rede social esportiva. O produto deve funcionar como uma base pessoal de treinamento e, no futuro, também como uma plataforma para atletas, treinadores, equipes e organizações.

## Ciclos principais

Para uso individual:

```text
planejar → executar → registrar → analisar → evoluir
```

Para uso com treinador ou equipe:

```text
treinador planeja → atleta executa → StrideBR registra e analisa → treinador acompanha → próximo treino
```

## Pilares do produto

### Activity

Registro manual, GPS, importação, edição, compartilhamento e histórico de atividades.

A atividade deve preservar o máximo possível do que realmente aconteceu: tempo, distância, rota, elevação, voltas, trechos, frequência cardíaca, cadência, potência, temperatura, pausas, sensores e demais streams disponíveis.

### Training

Cronogramas, sessões planejadas, biblioteca de exercícios, treinos estruturados, execução e comparação entre planejado e realizado.

### Analytics

Transformar o histórico em informação útil: timeline da atividade, comparação entre atividades e períodos, eficiência, evolução, distribuição de carga e análises específicas de cada esporte.

### Routes

Criação manual, rotas salvas, comparação de percursos, exploração e, futuramente, geração inteligente de rotas adequadas ao treino.

### Gear

Equipamentos como parte real do histórico esportivo, não apenas como um contador de quilometragem. O produto deve permitir acompanhar uso, desgaste, desempenho e, futuramente, equipamento por trecho.

### Events

Eventos e competições, próximos compromissos, participações, resultados e histórico esportivo.

### Teams

Atletas, treinadores, equipes e organizações em um ambiente compartilhado de planejamento e acompanhamento esportivo. O primeiro piloto planejado é o IFFar — Campus Frederico Westphalen.

Consulte `STRIDEBR_TEAMS.md` para a visão específica deste pilar.

## Diferenciais estratégicos

Algumas frentes têm potencial para definir a identidade do StrideBR no longo prazo:

- **Timeline esportiva rica:** visualizar o que aconteceu ao longo de uma atividade, e não apenas médias finais.
- **Smart Activity Merge:** combinar atividades interrompidas, sessões relacionadas e streams de múltiplos dispositivos.
- **Gear Intelligence:** analisar equipamentos, inclusive por trecho e em comparação com contextos semelhantes.
- **Music & Performance:** relacionar a trilha sonora da atividade com momentos, métricas e desempenho sem hospedar arquivos de áudio.
- **Smart Routes:** gerar rotas adequadas ao objetivo do treino, considerando distância, elevação, terreno, formato e preferências.
- **StrideBR Teams:** conectar planejamento, execução, acompanhamento e evolução de atletas e equipes.

## Princípios de produto

- O StrideBR é multiesporte por arquitetura e por interface.
- Dados históricos não devem perder significado quando templates ou definições mudarem.
- O usuário deve ter controle sobre seus dados, privacidade e o que aparece na interface.
- Social deve servir à utilidade: colaboração, treino, compartilhamento e equipes. Não há objetivo de criar um feed infinito.
- Recursos avançados devem continuar compreensíveis para usuários comuns.
- Métricas não devem fingir precisão ou causalidade que os dados não suportam.
- Dados de saúde e bem-estar exigem acesso restrito e tratamento adicional.
- Mobile não é apenas o site reduzido: o aplicativo deve aproveitar GPS, funcionamento offline, gravação em segundo plano e controles durante a atividade.
- Recursos novos não devem transformar a interface em uma coleção de módulos obrigatórios. Personalização deve crescer junto com o produto.
- O Git deve ser a fonte permanente das decisões de produto aprovadas. Conversas, protótipos e documentos externos podem amadurecer ideias antes de entrarem na documentação oficial.

## Documentos relacionados

- `PRODUCT_ROADMAP.md` — backlog e direção por horizonte.
- `STRIDEBR_TEAMS.md` — visão e escopo futuro do StrideBR Teams.
- `architecture.md` — arquitetura e regras atuais do sistema.
- `V1_RELEASE_CHECKLIST.md` — critérios da Web 1.0.
