# Pacer Web V1

`/user/pacer.php` é a interface Web da Pacer Platform determinística do Core.

## Domínio

A UI usa exclusivamente `pacer_service.php` para:

- listar plans;
- criar/editar;
- duplicar;
- arquivar;
- gerar prévias;
- validar segmentos;
- validar vínculo com Workout.

Nenhuma estratégia é calculada por uma implementação paralela no JavaScript.

## Estratégias

A interface expõe:

- Ritmo constante (`even`);
- Negative split;
- Custom;
- Positive split.

Distância e tempo-alvo produzem o pace médio pelo Core. Negative/positive split aceitam progressão e tamanho de segmento. Custom permite segmentos por distância, pace, tolerância e limites opcionais de FC.

A prévia Web chama `/api/pacer-preview.php`, que usa `pacerBlueprint()`. Ela mostra distância, tempo, pace médio, segmentos e tempo acumulado.

## Frequência cardíaca

Floor/ceiling são constraints configuradas pelo usuário ou treinador. A UI deixa explícito que não são recomendação médica.

## Edição segura

Alterar somente nome/status/regras de um plano gerado não regenera silenciosamente seus segmentos. Quando alvo, estratégia, tolerância ou opções de geração mudam, o Core volta a validar/gerar os segmentos.

## Workout

O editor Web de cronograma possui o campo “Estratégia de pace”. A lista é filtrada pela modalidade do treino e o vínculo é validado por `pacerPlanValidateForWorkout()`.

Em recorrências:

- `all`: altera a série;
- `future`: o vínculo acompanha a série futura;
- `this`: não permite trocar Pacer isoladamente porque a exceção atual não possui campo próprio para o vínculo.

## Runtime offline

A página Web gerencia o plano. A execução em tempo real continua sendo responsabilidade do Mobile offline usando os rules/segments do Core. O Web não cria um loop de requests de guidance.
