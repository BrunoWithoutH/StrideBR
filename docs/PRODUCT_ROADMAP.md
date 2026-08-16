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
- Perfil com nome de exibição, username, privacidade e onboarding curto.
- Amigos com solicitação mútua e compartilhamento de cronograma por snapshot.
- Exercícios com imagem principal e vídeo demonstrativo por URL.
- Papéis `user`, `moderator`, `admin` e `owner`, feature flags, métricas, logs e auditoria.

## Próximas etapas prioritárias

### Núcleo e confiabilidade

- Testes de integração PostgreSQL para migrações, sessão de treino e compartilhamentos.
- Refinar edição durante uma sessão: reps/carga reais por série, descanso e notas por exercício.
- Recuperação de sessão após fechamento da página e tratamento explícito de sessão abandonada.
- Histórico detalhado de sessões e comparação entre planejado e realizado.

### Cronogramas compartilhados

- Ativar `synced_schedules.enabled` depois de fechar permissões `owner/editor/viewer`.
- Convites para um cronograma sincronizado sem transformar o produto numa rede social.
- Planejamento compartilhado e execução individual para cada membro.
- Histórico de alterações importantes no cronograma compartilhado.

### Perfis e biblioteca

- Avatar por URL/storage controlado.
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

- Redesign completo da landing page pública.
- Redesign do `home` autenticado como dashboard de treino do dia, próximos treinos e atividade recente.
- PWA instalável, cache offline seletivo e notificações de treino/descanso quando fizer sentido.
- Importação/exportação XLSX depois do formato StrideBR/JSON e CSV estabilizarem.

## Princípios

- Social é utilitário: amigos, treino e compartilhamento; não feed infinito.
- O cronograma é o plano; a sessão é o que aconteceu; o histórico nunca depende do template atual.
- Conteúdo secundário sai dos cards e vai para preview/detalhes.
- Mobile não é desktop encolhido: usa navegação inferior, sheets e alvos de toque grandes.
- Dados e permissões devem ter defaults privados e papéis administrativos com privilégio mínimo.
- Vídeo de exercício é referência por URL; o StrideBR não vira hospedagem de vídeo sem necessidade.
