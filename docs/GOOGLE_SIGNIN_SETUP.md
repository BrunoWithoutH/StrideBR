# Login com Google — configuração do StrideBR

O StrideBR usa OAuth 2.0 / OpenID Connect no servidor e solicita somente `openid email profile`.

## Callback do projeto hospedado no alwaysdata

Use exatamente:

```text
https://stridebr.alwaysdata.net/auth/google-callback.php
```

A URI cadastrada no Google e `GOOGLE_OAUTH_REDIRECT_URI` precisam ser idênticas, incluindo `https`, caminho e barra final (neste caso, não há barra final).

## Ativar ou desativar

O login do Google possui uma chave própria e fica **desativado por padrão**. Isso permite deixar as credenciais configuradas no servidor e ligar/desligar o recurso sem remover nenhuma delas.

```env
GOOGLE_OAUTH_ENABLED=0
```

Use `GOOGLE_OAUTH_ENABLED=1` para habilitar. Também são aceitos `true`, `yes` e `on`. Qualquer outro valor mantém o login do Google desativado. Quando desativado, o botão some do login e o endpoint OAuth recusa novas tentativas; e-mail e senha continuam funcionando normalmente.

## Variáveis necessárias

```env
GOOGLE_OAUTH_ENABLED=1
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
GOOGLE_OAUTH_REDIRECT_URI=https://stridebr.alwaysdata.net/auth/google-callback.php
```

Nunca envie o client secret ao navegador e não o versione no Git.

## Banco de dados

Antes de trocar `GOOGLE_OAUTH_ENABLED` para `1`, aplique:

```text
src/database/migrations/20260903_v1_rc.sql
```

Ela adiciona `usuarios.google_sub` e o índice único associado.

## Desenvolvimento/teste

No Google Auth Platform, use público `External` e mantenha o app em `Testing` enquanto estiver testando. Adicione explicitamente as contas Google que poderão testar o login.

## Produção e domínio

O endereço `stridebr.alwaysdata.net` usa o domínio compartilhado `alwaysdata.net`. O Google exige verificação de domínio para branding/produção pública e verifica o domínio privado superior associado às URLs. Como esse domínio é do provedor de hospedagem, a publicação pública do branding pode exigir que o StrideBR use um domínio próprio que você consiga verificar no Google Search Console e aponte esse domínio para o alwaysdata.

Isso não impede a fase de testes com usuários autorizados, mas deve ser resolvido antes de depender do login Google para público geral.
