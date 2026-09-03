<?php
$pageTitle = 'Perguntas frequentes';
$pageDescription = 'Respostas rápidas sobre como o StrideBR funciona.';
$pageHtml = '<div class="faq-list">
<details open><summary>O StrideBR é só para corrida?</summary><p>Não. O sistema usa modalidades e modelos diferentes para que cada esporte possa registrar os campos que fazem sentido. Corrida, caminhada, ciclismo e musculação são alguns exemplos.</p></details>
<details><summary>Qual a diferença entre treino e atividade?</summary><p>Treino é planejamento: algo que está no cronograma ou foi prescrito. Atividade é o registro do que aconteceu de verdade. Uma sessão concluída pode gerar uma atividade.</p></details>
<details><summary>Posso ter mais de um cronograma?</summary><p>Sim. Cada cronograma possui sua própria rotina, treinos e configurações. A agenda mensal reúne recorrências e treinos marcados por data.</p></details>
<details><summary>Como funciona um cronograma sincronizado?</summary><p>Além de enviar uma cópia independente, você pode convidar um amigo para acompanhar o cronograma original em modo leitura. Depois do aceite, mudanças feitas pelo proprietário aparecem na versão sincronizada e podem gerar uma notificação. O acesso pode ser encerrado depois.</p></details>
<details><summary>Posso criar exercícios próprios?</summary><p>Sim. A biblioteca combina exercícios do StrideBR com exercícios pessoais, que podem ser reutilizados nos seus treinos.</p></details>
<details><summary>Como funcionam as rotas?</summary><p>Em modalidades compatíveis, você pode desenhar ou importar uma rota. O sistema calcula a distância e pode estimar a elevação. Para compartilhamento, é possível ocultar opcionalmente uma distância do início e do fim sem apagar a rota original privada.</p></details>
<details><summary>A elevação é exata?</summary><p>Ela é uma estimativa baseada em dados de terreno e pode diferir de um relógio com altímetro barométrico ou de uma gravação GPS dedicada.</p></details>
<details><summary>Como funcionam as metas e o progresso?</summary><p>Metas podem acompanhar distância, tempo, atividades, elevação ou frequência. A área Progresso mostra tendências, consistência, metas e marcas pessoais ao longo do tempo.</p></details>
<details><summary>Como comparo duas atividades?</summary><p>Abra Comparar atividades. A atividade mais recente pode ser comparada por padrão com a última do mesmo esporte, e o StrideBR também pode sugerir alternativas relacionadas, como rota ou distância semelhante.</p></details>
<details><summary>Apaguei uma atividade por engano. Dá para desfazer?</summary><p>Sim, por um período curto. Atividades removidas ficam fora dos fluxos normais e podem ser restauradas pela ação Desfazer por até 30 minutos.</p></details>
<details><summary>O modo treinador dá acesso à minha conta?</summary><p>Não. O vínculo treinador-atleta possui permissões próprias e não transforma o treinador em administrador da conta. O atleta controla as permissões e pode encerrar o vínculo.</p></details>
<details><summary>O StrideBR usa analytics?</summary><p>Pode registrar eventos limitados de produto para entender fluxos e desempenho, sem conteúdo pessoal como rota, notas ou cargas. Essa opção pode ser desativada nas preferências.</p></details>
<details><summary>Posso baixar meus dados?</summary><p>Sim. Em Perfil e configurações → Seus dados, você pode gerar uma exportação em JSON com seus principais registros. Atividades também possuem opções de exportação específicas.</p></details>
<details><summary>Onde relato um problema?</summary><p>Use o Feedback dentro do StrideBR para bugs e ideias. Para conta, privacidade ou informações sensíveis, use o e-mail indicado na página de Suporte.</p></details>
</div>';
require dirname(__DIR__, 3) . '/src/layout/static_page.php';
