# Pacer Runtime v2

O endpoint `POST /api/v1/pacer-plans/{id}/evaluate` existe para debug, Web e testes. O app Mobile deve portar este algoritmo e executá-lo offline; não deve fazer uma request por avaliação.

## Plano portátil

Os segmentos cobrem `0..target_distance_m` sem lacunas. `pacerTargetElapsedAtDistance(plan, distance_m)` soma os segmentos completos e interpola linearmente o trecho atual. `guidance_rules` é JSONB e v2 inclui `goal_mode` (`target_time` ou `best_effort`), `clock_mode` (`auto`, `elapsed`, `moving`), persistência, histerese, cooldown, `final_phase`, milestones e o filtro de oportunidade. Planos v1 sem esses campos normalizam para `target_time`, `auto` e defaults v2; nenhum plano antigo é invalidado.

`auto` usa `elapsed_s` no Core por compatibilidade. O Mobile pode decidir `elapsed` para prova e `moving` para treino, sem mudar a matemática restante.

Unidades do contrato: distância em metros, tempos em segundos, pace em segundos/km, FC em bpm e tolerância em segundos/km. Os cálculos mantêm ponto flutuante; só a camada de apresentação arredonda `5:12/km` ou `52:00`.

## Entrada por avaliação

```json
{"distance_m":6200,"elapsed_s":1993,"moving_time_s":1980,"recent_pace_s_per_km":302,"average_pace_s_per_km":307,"heart_rate_bpm":154,"gps_quality":"good","paused":false,"guidance_state":{}}
```

`recent_pace_s_per_km` é o sinal para correção local; calcular com janela robusta de aproximadamente 20–30 s ou 80–150 m. Nunca derive esse valor de apenas dois fixes GPS. `average_pace_s_per_km` é usado para projeção. `instant_pace` pode existir no cliente, mas não participa da engine. `gps_quality` é `good`, `degraded` ou `poor` e vem do diagnóstico do consumidor.

`distance_m`, `elapsed_s` e `moving_time_s` são obrigatórios e não negativos. Pace fornecido deve ser positivo; FC, quando fornecida, fica entre 20 e 260. Distância acima do alvo retorna `finished`; pace ausente é aceito e somente elimina a correção local. `guidance_state` é o `next_state` da avaliação anterior e deve ser carregado pelo consumidor sem alterar seus valores.

## Saída

O resultado mantém `ahead_behind_s`, `target_elapsed_s`, `segment`, `target`, `projected_finish` e `next_state` de v1. V2 acrescenta `phase`, `severity`, `progress`, `timing`, `gps`, `opportunity` e `message` sem copy localizada: `message.headline_code`, `detail_code` e `params` devem ser traduzidos pelo cliente.

`ahead_behind_s = clock_s - target_elapsed_s`: negativo é adiantado, zero é alvo e positivo é atrasado. A engine compara esse dado global separadamente do pace recente do bloco. Quando um atraso diminui, retorna `recovering` em vez de insistir em `speed_up`.

## Prioridades e estabilidade

A ordem é: `paused`, `hr_limit`, `gps_unreliable`, `segment_change`, fase final, correção de pace, `milestone`, `on_target`. Desvios precisam persistir `persistence_s`; FC usa `heart_rate_persistence_s`; `hysteresis_s_per_km` impede inversões no limite e `cooldown_s` evita repetição. Em `degraded`, a tolerância de pace cresce 50%; em `poor`, a orientação informa GPS instável e se baseia somente no progresso acumulado. Pausado nunca recebe correção.

Os códigos são semânticos: `paused`, `gps_unreliable`, `hr_limit`, `segment_change`, `speed_up`, `slow_down`, `recovering`, `on_target`, `final_phase_available`, `final_push`, `final_kick`, `milestone` e `finished`. O consumidor traduz `message.headline_code`/`detail_code`; a engine não devolve texto localizado como contrato.

FC é apenas guardrail configurado: teto persistente retorna `hr_limit` e bloqueia aceleração/fase final. Ausência de FC não bloqueia o Pacer.

## Objetivos e fase final

Em `target_time`, vantagem relevante cedo pode gerar `slow_down`. Em `best_effort`, estar adiantado nunca gera redução apenas pela vantagem. A fase final padrão é 10% da distância, limitada a 150–1000 m: `final_phase_available` é convite, `final_push` reconhece aumento sustentável e `final_kick` só aparece na reta final. Nenhum deles é obrigatório.

Para `best_effort`, `opportunity` usa o próximo minuto cheio abaixo da projeção. Só fica disponível se o pace necessário no restante estiver até 7% mais rápido que o pace recente/médio sustentável; é um filtro matemático conservador, não promessa de desempenho.

Milestones usam `milestone_distance_m`, são disparados apenas ao cruzar uma nova marca e perdem para qualquer estado de prioridade maior. A engine não consulta banco, relógio do servidor, rede ou sessão: `plan + state` sempre produz o mesmo resultado. Fixtures de paridade vivem em `scripts/tests/test_pacer_v2_static.php`; elas cobrem curvas, legado, pausa, GPS, FC, recuperação e oportunidade.
