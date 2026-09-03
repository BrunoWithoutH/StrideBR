# StrideBR 1.0 — release candidate

Esta rodada fecha pendências de interface e conta antes da publicação da 1.0.

## Alterações

- Metas podem ser contínuas, com data limite ou recorrentes; o progresso continua vindo automaticamente das atividades concluídas.
- Correção da formatação de números inteiros no dashboard, incluindo `0 m` em elevação.
- Agenda mensal recebe navegação contextual e controles com o mesmo padrão visual do restante do produto.
- Feedback e ferramentas rápidas passam a compartilhar um único dock alinhado; no mobile o dock fica acima da navegação e some enquanto o menu Mais ou o modal de ferramentas está aberto.
- Terminologia do contador rápido usa “séries”.
- Verificação de e-mail e recuperação de senha ficam disponíveis quando `STRIDEBR_MAIL_FROM` está configurado.
- Contas ativas existentes são preservadas como verificadas pela migration da release.
- Deploy por rsync protege `public/uploads/`, evitando apagar avatares e imagens de eventos.

## Migration

Aplicar e registrar:

```text
20260903_v1_rc.sql
```

A migration adiciona o período `continuo` às metas, preserva o acesso das contas já existentes e habilita as feature flags de verificação de e-mail e recuperação de senha.

## Configuração obrigatória para e-mail

```text
STRIDEBR_APP_URL=https://stridebr.alwaysdata.net
STRIDEBR_MAIL_FROM=ENDERECO_DE_ENVIO
STRIDEBR_MAIL_FROM_NAME=StrideBR
```

Sem um remetente válido, os recursos de e-mail permanecem indisponíveis e a confirmação de e-mail não bloqueia o acesso.

## Deploy

Preferir:

```bash
./scripts/deploy_alwaysdata.sh
```

O script preserva uploads criados no servidor mesmo usando `rsync --delete`.


## Daily-use polish

A rodada de uso diário acrescenta melhorias pensadas para a semana de teste antes da 1.0:

- repetir uma atividade existente sem copiar data, horário, esforço percebido ou observações;
- rascunhos locais por 7 dias nos formulários de nova atividade, novo cronograma e novo treino;
- resumo pós-treino com duração, exercícios e séries concluídas;
- histórico rápido do exercício durante a sessão, com última execução e melhor carga recente quando disponível;
- troca de e-mail com senha atual e código enviado ao novo endereço;
- sessões/dispositivos recentes, encerramento individual e encerramento das outras sessões;
- data da última troca de senha quando disponível;
- envio de e-mail de teste pelo painel administrativo;
- importador de eventos JSON/CSV com prévia, validação e detecção de duplicados;
- estados vazios mais úteis em fluxos principais;
- feedback de bug inclui contexto técnico não sensível da página, tela, navegador e build;
- versão/build visível no rodapé e atualizado automaticamente no deploy;
- atalho de Novidades para o changelog.

### Migration desta rodada

```text
20260903_v1_rc.sql
```

Ela adiciona `senha_alterada_em` a `usuarios` e a tabela `sessoes_usuario`. O restante do pacote continua utilizável antes da migration; apenas o histórico detalhado de sessões e a data da última troca de senha ficam indisponíveis até ela ser aplicada.


## Pré-release hardening

A última rodada antes da semana de uso adiciona:

- diagnóstico administrativo em `/admin/diagnostics.php`, incluindo migrations pendentes, ambiente, HTTPS, banco, e-mail, uploads, extensões PHP e flags importantes;
- trava de envio duplicado nos formulários e aviso de conexão offline;
- referência curta em erros 500 para correlação com os logs de produção;
- otimização das imagens de eventos para WebP com limite de dimensão quando GD ou Imagick estiver disponível;
- estados vazios mais acionáveis em cronogramas, perfil, dashboard e treinador;
- `scripts/release_check.sh` para concentrar checks estáticos, Git e status das migrations;
- teste estático contra formulários HTML aninhados e contra remoção acidental das proteções de `.htaccess`.

Esta rodada não adiciona migration.

## 2026-08-30 — Compartilhamento de atividades v2

- Atividades podem ser compartilhadas mesmo quando não possuem rota.
- Quando há rota, o usuário escolhe explicitamente **Exibir rota?** sem alterar o registro original.
- Novo visual **Dados** funciona sem mapa/rota e evita cartões com espaço vazio.
- **Copiar card** permite colar a imagem em editores compatíveis; **Exportar rota como PNG** gera um traçado transparente para sobrepor a fotos.
- Trechos, tiros, voltas e tentativas podem armazenar rota própria e mostrar prévia individual.
- Compartilhamento de múltiplos trechos permite gerar todos juntos ou um arquivo por trecho selecionado.
- No mobile web, a personalização do compartilhamento passa a usar bottom sheet.
- Nova migration `20260903_v1_rc.sql` cria `rotas_unidades_atividade`.
