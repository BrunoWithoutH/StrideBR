# People API V1

Base: `/api/v1`. Todas as rotas exigem `Authorization: Bearer <token>` e são restritas ao usuário autenticado.

Pessoas é uma superfície social do Core e não representa integrações externas. Strava, Polar, Garmin e demais provedores continuam pertencendo a Conexões/Integrações.

## DTO público

Uma pessoa é exposta somente com campos seguros:

```json
{
  "id": "...",
  "username": "bruno",
  "display_name": "Bruno",
  "avatar_url": null,
  "bio": null,
  "trainer_mode": true
}
```

Email, telefone, nascimento e outros dados privados nunca fazem parte do DTO. Em busca, `bio` é omitida semanticamente como `null`; no detalhe ela só é exposta quando o perfil/relação permite.

Usuários inativos não aparecem. Busca exige `discoverable=true`. O detalhe de terceiros exige perfil público ou uma relação aceita de amizade/coaching; o próprio usuário sempre pode consultar a si mesmo.

## Feature flags

A superfície respeita `friends.enabled` e `trainer.enabled`. Busca e detalhe retornam capabilities gerais. Rotas específicas desativadas retornam `503 feature_disabled`.

## Busca

### GET `/people/search?q=...&type=all|friend|trainer`

`q` é obrigatório para produzir resultados; busca vazia retorna lista vazia. Máximo: 20 resultados.

- `all`: pessoas descobríveis;
- `friend`: somente amizades aceitas que casam com a busca;
- `trainer`: somente pessoas em modo treinador.

Cada item pode incluir `friendship_status` e `coaching_status` do usuário autenticado.

## Detalhe

### GET `/people/{id}`

Retorna o DTO público seguro, `friendship_status`, `coaching_status` e capabilities para iniciar relação quando aplicável.

Recurso não visível para o usuário atual responde `404`, evitando expor perfil privado.

## Amigos

### GET `/people/friends`

```json
{
  "data": {
    "friends": [],
    "incoming": [],
    "outgoing": []
  }
}
```

Cada relação contém `id`, `status`, `person` e capabilities de ação.

Estados públicos relevantes: `accepted`, `incoming`, `outgoing`.

### POST `/people/friendships`

```json
{
  "user_id": "..."
}
```

Cria solicitação e reutiliza a notificação de amizade já existente no Core.

### POST `/people/friendships/{id}/accept`

Somente o destinatário da solicitação pendente pode aceitar. O aceite reutiliza a notificação existente de amizade aceita.

### POST `/people/friendships/{id}/reject`

Somente o destinatário pode rejeitar. A relação pendente é removida.

### DELETE `/people/friendships/{id}`

Quem enviou pode cancelar uma solicitação outgoing. Qualquer participante pode remover uma amizade aceita. Retry após a relação já não existir é seguro e pode retornar `reused=true`.

## Treinadores e atletas

### GET `/people/coaching`

```json
{
  "data": {
    "trainers": [],
    "athletes": [],
    "incoming": [],
    "outgoing": []
  }
}
```

Cada vínculo contém:

```json
{
  "id": "...",
  "status": "accepted",
  "trainer": {},
  "athlete": {},
  "requested_by": "trainer",
  "permissions": {
    "can_prescribe": true,
    "can_view_schedule": true,
    "can_view_activities": true,
    "can_view_feedback": false
  },
  "capabilities": {
    "can_accept": false,
    "can_reject": false,
    "can_cancel": false,
    "can_end": true,
    "can_edit_permissions": true
  }
}
```

Estados públicos: `pending`, `accepted`, `rejected`, `ended`. Os valores internos do banco continuam usando o domínio Web existente.

### POST `/people/coaching`

Quando o usuário autenticado será treinador:

```json
{
  "user_id": "...",
  "role_for_me": "trainer"
}
```

Quando quer a outra pessoa como treinador:

```json
{
  "user_id": "...",
  "role_for_me": "athlete"
}
```

A criação reutiliza `treinadorCriarConvite()` e o mesmo domínio de `vinculos_treinador_atleta` da Web.

### POST `/people/coaching/{id}/accept`
### POST `/people/coaching/{id}/reject`

Somente o participante que não iniciou o convite pendente pode responder.

### DELETE `/people/coaching/{id}`

Quem iniciou pode cancelar convite pendente. Qualquer participante pode encerrar vínculo aceito. O histórico de coaching é preservado no domínio, em vez de apagar a linha aceita/encerrada.

### PATCH `/people/coaching/{id}/permissions`

Body parcial:

```json
{
  "can_prescribe": true,
  "can_view_schedule": false,
  "can_view_activities": true,
  "can_view_feedback": false
}
```

Somente o atleta do vínculo aceito pode alterar permissões. O treinador não pode conceder permissões a si mesmo.

## Ownership e privacidade

IDs de amizade e coaching são resolvidos sempre no contexto do usuário autenticado. Ações em relações de terceiros não são permitidas. Busca não transforma `discoverable` em acesso ao perfil privado completo.

## Erros

- `401 authentication_required` / `token_expired`;
- `403 forbidden` para ação que pertence ao outro participante;
- `404 not_found` para pessoa/relação ausente ou não visível;
- `409 invalid_state` para relação incompatível/duplicada;
- `422 validation_error` para payload ou filtro inválido;
- `503 feature_disabled` quando a feature correspondente está desativada.

## Contract freeze V1.1

O shape desta API está congelado para o Android em [`MOBILE_CONTRACT_FREEZE_V1.md`](MOBILE_CONTRACT_FREEZE_V1.md). `Person` usa `display_name`; Coaching mantém `trainer` e `athlete` como objetos distintos. Esses nomes não devem ser substituídos por DTOs provisórios de cliente.
