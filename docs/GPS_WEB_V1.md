# GPS Web v1 — implementação

## Escopo

O StrideBR Web pode gravar uma atividade compatível com rota diretamente pelo navegador. A implementação é deliberadamente apresentada como **melhor esforço**, não como substituta de um app nativo ou relógio GPS.

A entrada principal é `/user/gravar-atividade.php`. Há também atalhos para uma corrida rápida com início automático.

## Transparência de precisão

A interface informa antes, durante e depois da atividade que:

- a geolocalização depende do navegador, aparelho e sensores disponíveis;
- `accuracy` é uma estimativa entregue pelo dispositivo, não garantia de exatidão;
- tela bloqueada, aba oculta, economia de bateria e políticas do sistema podem interromper atualizações;
- distância, tempo e elevação podem ser corrigidos na revisão e novamente na edição da atividade.

Atividades originadas no GPS Web mantêm metadados de qualidade para que o detalhe mostre precisão média, pontos aceitos/descartados, lacunas de visibilidade e se o usuário ajustou os dados.

## Captura e filtro

A gravação usa `navigator.geolocation.watchPosition()` com:

- `enableHighAccuracy: true`;
- `maximumAge: 0`;
- `timeout: 15000`.

O navegador entrega posições já processadas pelo sistema operacional. O StrideBR aplica uma segunda camada conservadora antes de somar um ponto à distância:

- limite de `accuracy` por classe de esporte;
- intervalo mínimo entre amostras;
- velocidade máxima fisicamente plausível;
- validação da velocidade informada pelo aparelho quando disponível;
- piso de jitter proporcional à incerteza das amostras;
- descarte de pequenos deslocamentos sob precisão ruim;
- ancoragem depois de uma pausa para não somar como atividade o deslocamento ocorrido enquanto o cronômetro estava pausado;
- lacunas superiores a 15 segundos entre amostras são tratadas como descontinuidade: o próximo ponto vira uma nova âncora e o sistema não soma uma linha reta fictícia entre os dois pontos.

Os contadores de pontos recebidos, aceitos e descartados são preservados para diagnóstico. O filtro não promete reconstruir um trecho que o navegador deixou de enviar.

## Elevação

Altitude só é usada quando o dispositivo realmente fornece o valor. Amostras com `altitudeAccuracy` muito ruim são ignoradas. Uma janela curta de mediana reduz ruído e o ganho positivo só é acumulado acima de um limiar mínimo.

Por ser GPS Web, a elevação do aparelho também é estimativa e pode ser editada na revisão.

## Local-first e internet ruim

Enquanto a atividade está em andamento, o estado é salvo em IndexedDB no próprio navegador:

- início e pausas;
- pontos aceitos;
- distância calculada;
- qualidade do GPS;
- elevação;
- trechos;
- meta;
- estado da gravação.

A internet não participa do cálculo ao vivo. Se ela cair, a gravação continua. Se o usuário tentar salvar sem rede, o StrideBR mantém a sessão local e tenta novamente quando a conexão voltar enquanto a página ainda estiver aberta; depois de um reload, a gravação continua disponível para retomada/salvamento manual.

## Reload, suspensão e recuperação

Uma sessão não finalizada é oferecida ao voltar à página. Um reload curto pode continuar a gravação. Se houver uma lacuna longa desde o último ponto conhecido, o StrideBR não finge que rastreou esse período: a sessão é recuperada pausada e a lacuna é sinalizada para revisão.

`beforeunload` avisa sobre saída acidental e o estado também é persistido periodicamente e em `pagehide`.

## Wake Lock e background

Quando solicitado e suportado, o StrideBR usa Screen Wake Lock para tentar manter a tela ligada e reacquire o lock quando o documento volta a ficar visível.

Isso **não transforma o site em um gravador de background**. Um navegador móvel pode suspender JavaScript/geolocalização quando a tela é bloqueada ou a aba deixa de estar ativa. Essa garantia ficará para o app nativo.

## Meta e encerramento automático

A meta é opcional:

- distância;
- tempo;
- sem meta.

O usuário pode escolher finalização automática. Meta de distância usa apenas a distância filtrada e não encerra imediatamente na primeira ultrapassagem marginal: exige margem proporcional à precisão atual ou confirmação por uma nova amostra aceita. Sem meta, o usuário finaliza manualmente.

Toda finalização abre a revisão antes de salvar definitivamente.

## Trechos, tiros e voltas

`Marcar trecho` fecha a unidade atual e inicia a próxima. Cada unidade guarda:

- índices inicial/final de pontos;
- distância;
- duração ativa;
- elevação positiva;
- recorte da rota correspondente.

Esses dados usam `rotas_unidades_atividade`, integrando diretamente com o Compartilhamento v2 para cards dos tiros juntos ou separados.

## Revisão

Antes de salvar o usuário pode corrigir:

- título;
- distância;
- tempo;
- elevação;
- visibilidade;
- percepção de esforço;
- ocultação de início/fim da rota;
- observações.

A distância geométrica medida originalmente é mantida em `gravacoes_gps_web.distancia_medida_m`, enquanto o valor final revisado é mantido separadamente em `distancia_final_m` e nos dados normais da atividade.

## Idempotência

Cada gravação recebe uma chave estável gerada no navegador. `gravacoes_gps_web.chave_gravacao` é `UNIQUE`.

Se o servidor salvar a atividade mas a resposta se perder, uma nova tentativa com a mesma gravação retorna o registro já existente em vez de criar uma segunda atividade. A transação também protege contra duas requisições concorrentes.

## Onboarding / IKEA effect

O cadastro agora começa pela personalização opcional:

1. esportes;
2. objetivos;
3. experiência, frequência e métricas de interesse;
4. defaults de unidade e privacidade;
5. somente então nome, e-mail e senha para salvar o StrideBR configurado.

Todas as etapas de personalização podem ser ignoradas. O usuário pode pular direto para a criação da conta.

## Fora do escopo desta versão

- GPS realmente confiável com tela bloqueada/background;
- auto-pause automático;
- sensores BLE/relógios;
- instruções de treino estruturado em tempo real;
- correção de elevação por DEM depois da gravação;
- processamento avançado de mapa offline.

Esses itens continuam planejados para fases futuras e principalmente para o app.
