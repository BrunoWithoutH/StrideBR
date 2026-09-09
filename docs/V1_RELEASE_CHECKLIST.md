# StrideBR 1.0.0 RC5 — checklist de release

Esta checklist separa checks automatizados do smoke que precisa de ambiente real. Não promova a RC5 para 1.0 somente porque a suíte estática passou.

## 1. Automático

```bash
./scripts/release_check.sh
```

Em máquina com Docker/PostgreSQL:

```bash
./scripts/release_check.sh --full
```

- [ ] sintaxe PHP verde
- [ ] sintaxe JavaScript verde
- [ ] sintaxe shell verde
- [ ] sem marcadores de conflito
- [ ] `git diff --check` verde quando houver working tree Git
- [ ] `./scripts/test_static.sh` verde
- [ ] banco PostgreSQL limpo chega ao schema final
- [ ] segunda execução das migrations não reaplica arquivos
- [ ] upgrade do estado RC1 aplica somente migrations pós-RC1 pendentes
- [ ] `unidades_atividade.duracao_segundos` aceita `12.438`, `0.005` e `78.921` exatamente
- [ ] `public.stridebr_schema_migrations` corresponde ao histórico real
- [ ] `./scripts/migrate_product.sh status` termina sem `PENDENTE`

A migration `20260903_z_activity_duration_precision_ms.sql` só pode ser squashada/renomeada se for comprovado que nunca foi aplicada em nenhum banco compartilhado que precise manter histórico. Caso contrário, preserve o nome histórico.

## 2. Backup e restore

Antes de aplicar migration em produção:

```bash
./scripts/backup_db.sh
```

Restaure em um banco separado e valide a aplicação:

```bash
STRIDEBR_DB_NAME=stridebr_restore_test ./scripts/restore_db.sh backups/ARQUIVO.dump --yes
```

- [ ] backup recente criado
- [ ] dump não versionado
- [ ] restore concluído em banco separado
- [ ] aplicação abre e consulta dados normalmente após restore

## 3. Smoke funcional obrigatório

Use uma conta nova e, quando necessário, uma segunda conta comum.

- [ ] cadastro e confirmação/login
- [ ] onboarding
- [ ] Home/dashboard
- [ ] cronogramas
- [ ] criar/editar treino
- [ ] iniciar e concluir sessão de treino
- [ ] registrar atividade
- [ ] editar atividade
- [ ] duração sem ms e com ms
- [ ] reabrir valor fracionário sem perder precisão
- [ ] criar/editar 3 Trechos
- [ ] rota Livre
- [ ] Fechar rota + Desfazer
- [ ] Circuito/voltas
- [ ] rota individual de Trecho
- [ ] Ruas no editor/compartilhamento onde aplicável
- [ ] Satélite no editor/compartilhamento onde aplicável
- [ ] Privacidade da rota
- [ ] Visibilidade da atividade: Privado/Amigos/Público
- [ ] atividade GPS
- [ ] Wake Lock quando suportado
- [ ] Bloquear controles e segurar para desbloquear
- [ ] Histórico/Preview
- [ ] Abrir detalhes e voltar à Preview
- [ ] importar atividade
- [ ] exportar atividade
- [ ] compartilhar card de atividade em Story
- [ ] compartilhar card de atividade em Retrato
- [ ] compartilhar card de atividade em Quadrado
- [ ] Padrão com Rota/Modalidade/Nenhum e título on/off
- [ ] Compacto de atividade
- [ ] sessão abre direto no editor, sem wizard
- [ ] escopos Sessão / Um trecho / Vários trechos
- [ ] sessão: Visão geral
- [ ] sessão: Por trecho
- [ ] sessão: Comparação
- [ ] sessão: Destaque
- [ ] sessão: Sequência com percurso
- [ ] sessão: Resumo
- [ ] sessão: Lista
- [ ] sessão: Sequência textual
- [ ] sessão: Destaque sem percurso
- [ ] sessão: Minimal
- [ ] sessão: Compacto
- [ ] compartilhar sessão com alguns Trechos sem rota
- [ ] compartilhar sessão sem rotas usando composição compatível
- [ ] sessão longa mostra `+N` sem perder seleção
- [ ] fundo Cor
- [ ] fundo Foto
- [ ] PNG Transparente realmente transparente
- [ ] fundo Mapa só aparece quando compatível
- [ ] Preview e arquivo exportado mantêm a mesma composição/crop
- [ ] mobile sem overflow/clipping
- [ ] PWA standalone quando aplicável
- [ ] excluir atividade e usar Desfazer

## 4. Browsers/aparelhos

Smoke mínimo desejado:

- [ ] Firefox desktop
- [ ] Chromium/Chrome desktop
- [ ] Chrome Android
- [ ] Safari/iPhone quando disponível

A indisponibilidade física de um aparelho pode ser registrada, mas não deve ser escondida no relatório de release.

## 5. Produção e segurança

- [ ] `STRIDEBR_APP_ENV=production`
- [ ] `STRIDEBR_VERSION=1.0.0-rc.5`
- [ ] `STRIDEBR_APP_URL` aponta para HTTPS real
- [ ] HTTP redireciona para HTTPS
- [ ] cookies de sessão mantêm `Secure`, `HttpOnly` e `SameSite=Lax`
- [ ] `.env` não está versionado nem no artefato público
- [ ] uploads não foram empacotados indevidamente
- [ ] dumps, ZIPs, cache e artefatos temporários não estão versionados
- [ ] scripts executáveis continuam executáveis
- [ ] migrations previstas foram aplicadas e registradas
- [ ] `/admin/diagnostics.php` sem alerta crítico de release
- [ ] login comum e owner/admin funcionam depois do deploy

## 6. Git e promoção

Antes da tag RC5:

- [ ] working tree limpa
- [ ] nenhum segredo/dump/upload/ZIP acidental
- [ ] validação local da RC5 aprovada e registrada
- [ ] release check repetido depois da última correção
- [ ] pendências de smoke real e backup/restore registradas para Infra

Somente depois da revisão do diff e da aprovação explícita para commit, push e tag:

```bash
git tag -a v1.0.0-rc.5 -m "StrideBR 1.0.0 RC5"
git push origin v1.0.0-rc.5
```

Não criar `v1.0.0` nesta etapa.
