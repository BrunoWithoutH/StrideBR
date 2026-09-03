# StrideBR — direção de produto

A sequência central do produto é:

```text
planejar → executar → registrar → acompanhar → compartilhar
```

## Base atual

- Navegação adaptativa: navbar no desktop e barra inferior no mobile.
- Página de cronogramas com scroll normal do documento, toolbar contextual sticky e calendário com scroll próprio.
- Preview de treino sem sair da agenda, edição separada e exclusão do treino.
- Ferramentas rápidas globais: cronômetro, timer e contador de sets, com favoritos fixáveis.
- Sessão de treino iniciada pelo cronograma, acompanhamento de séries/exercícios e finalização em atividade.
- Cards de atividades com métricas principais automáticas e detalhes secundários em modal.
- Rotas v1 com desenho manual, distância validada no backend, elevação estimada e card compartilhável com identidade do StrideBR.
- Perfil com nome de exibição, username, privacidade e onboarding curto.
- Amigos com solicitação mútua, compartilhamento por snapshot e cronogramas sincronizados em modo leitura.
- Exercícios com imagem principal e vídeo demonstrativo por URL.
- Papéis `user`, `moderator`, `admin` e `owner`, feature flags, métricas, logs e auditoria.
- Agenda mensal que projeta cronogramas semanais e aceita treinos por data específica.
- Modo treinador separado de papéis administrativos, com vínculo aceito, permissões do atleta, prescrição, execução, feedback e resumo do atleta respeitando os acessos concedidos.
- Home contextual, Progresso sem PBL, comparação A/B de atividades e notificações internas.
- Atividades com exclusão reversível e privacidade opcional do início/fim da rota compartilhada.

## Próximas etapas prioritárias

### Núcleo e confiabilidade

- Expandir continuamente a suíte de integração PostgreSQL e manter o checklist de release da alpha como gate antes de novas rodadas.
- Refinar edição durante uma sessão: reps/carga reais por série, descanso e notas por exercício.
- Recuperação de sessão após fechamento da página e tratamento explícito de sessão abandonada.
- Histórico detalhado de sessões e comparação entre planejado e realizado.

### Evolução do planejamento e treinador

- Editor estruturado de treinos de corrida com aquecimento, bloco principal, recuperação, repetição, desaquecimento e alvos por tempo, distância ou ritmo.
- Comparação planejado × realizado usando a sessão executada e a prescrição original.
- Painel do treinador com tendências simples por atleta sem transformar dados esportivos em acesso administrativo à conta.
- Evolução posterior para equipes/assessorias e verificação profissional quando houver necessidade de exposição pública ou recursos comerciais.
- Verificação profissional deve ser separada do modo treinador e só coletar os dados estritamente necessários.

### Cronogramas compartilhados

- Validar em produção o fluxo atual de convite, aceite, leitura sincronizada, notificações e revogação.
- Manter o participante em modo leitura enquanto não houver uma política robusta de conflitos para edição compartilhada.
- Evoluir depois para papéis `owner/editor/viewer`, planejamento colaborativo e histórico de alterações importantes.
- Manter a execução individual para cada membro e evitar transformar o produto numa rede social de ranking.

### Perfis e biblioteca

- Refinar avatar/storage e política de limpeza de arquivos órfãos conforme a alpha crescer.
- Página pública de cronogramas que o usuário escolheu publicar.
- Busca unificada de exercícios, amigos e cronogramas.
- Melhorar a biblioteca com instruções, categorias, imagem principal e referência de vídeo.

### Administração

- Dashboard por períodos e séries temporais.
- Métricas agregadas por país/região sem transformar analytics em armazenamento permanente de IPs.
- Filtros e busca de audit log.
- Moderação de conteúdo público e denúncias quando publicação pública crescer.
- Controles de manutenção e feature flags por ambiente.

### Produto e distribuição

- Validar a nova landing e a Home contextual com uso real antes de novas mudanças grandes.
- Prototipar a experiência do app mobile separadamente do responsivo web.
- PWA instalável e cache offline seletivo quando trouxerem benefício real ao web.
- App dedicado com GPS confiável, persistência offline e gravação em segundo plano como prioridade mobile.
- Importação/exportação XLSX depois dos formatos esportivos e JSON estabilizarem.

## Princípios

- Social é utilitário: amigos, treino e compartilhamento; não feed infinito.
- O cronograma é o plano; a sessão é o que aconteceu; o histórico nunca depende do template atual.
- Conteúdo secundário sai dos cards e vai para preview/detalhes.
- Mobile não é desktop encolhido: usa navegação inferior, sheets e alvos de toque grandes.
- Dados e permissões devem ter defaults privados e papéis administrativos com privilégio mínimo.
- Vídeo de exercício é referência por URL; o StrideBR não vira hospedagem de vídeo sem necessidade.

## GPS Web e gravação de atividades

Planejamento detalhado em `docs/GPS_WEB_RECORDING_PLAN.md`.

Prioridades: início rápido, gravação local-first sem depender de internet, recuperação após reload, filtro de qualidade do GPS, tela focada em distância/pace/tempo/elevação, metas opcionais com encerramento automático seguro, trechos/tiros com rota própria e integração direta com o Compartilhamento v2. A gravação confiável em segundo plano continua sendo responsabilidade do app mobile.
