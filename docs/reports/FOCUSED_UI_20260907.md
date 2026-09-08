# Correção funcional de UI — 07/09/2026

Working tree inspecionado e preservado; nenhuma migration, tag ou descarte.

## Causas confirmadas
- CSS de display sobrepunha `hidden` na grade de categorias e nas ações do onboarding.
- Personalização após login ainda usava uma lista plana própria.
- Semana da Home usava tokens antigos com fallback branco e footer sem padding lateral.

## Implementação
- Cadastro e onboarding usam `src/layout/sport_personalization.php`, mantendo os IDs do catálogo existente.
- Categoria abre na mesma posição da grade, com voltar, descrição, esportes comuns e expansão opcional. Busca inclui nome localizado e original. Foco acompanha abertura/retorno.
- Corrida/caminhada em primeiro lugar; provas de corrida de pista existentes ficam no detalhamento. Agrupamento de seleção não altera famílias armazenadas nem analytics.
- Create account aparece apenas no último passo; fica desabilitado até validade dos campos obrigatórios. Pular personalização leva à conta; demais etapas continuam opcionais.
- Settings e seletor genérico respeitam hidden e recebem foco ao abrir categoria.
- Semana usa tokens de tema ativos, footer com padding e links com alvo acessível. Navegação anterior/próxima/atual usa consulta semanal existente, mantendo hoje e timezone reais.

## Validação
- `./scripts/test_static.sh`: passou completo, incluindo sintaxe PHP/JS, i18n (3011 keys por locale), componentes e regressões.
- `test_dashboard_week_consistency.php`: 16 assertions, incluindo navegação histórica e preservação de hoje.
- `browser_focused_ui.py`: Chromium real sobre localhost:8080; cadastro desktop/mobile, categorias/mais esportes, CTA oculto/desabilitado/habilitado, onboarding autenticado, Settings, Home escura, navegação com três atividades na semana anterior e seleção Running em Metas nas duas larguras.
- Screenshots reais inspecionados em `/tmp/stridebr-focused-ui`: signup, conta, Home escura, Settings e seletor.
- `git diff --check`: passou.

Não foram enviados formulários de criação de conta nem criadas migrations. Nenhum redesenho de Progresso, compartilhamento, GPS ou editor de atividades.
