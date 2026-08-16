<?php

declare(strict_types=1);
require_once dirname(__DIR__,3).'/src/includes/app.php';
$pageTitle='Termos de Uso';
$pageDescription='Versão '.stridebr_terms_version().' · alpha fechada';
$pageHtml='<p class="legal-lead"><strong>Última atualização: 15 de agosto de 2026.</strong> Estes termos regulam o uso do StrideBR durante a fase alpha. Ao criar uma conta, você confirma que leu estes Termos e a Política de Privacidade.</p>
<h2>1. O que é o StrideBR</h2><p>O StrideBR é uma plataforma em desenvolvimento para planejar treinos, registrar atividades físicas, organizar exercícios e compartilhar determinados conteúdos com amigos. Recursos podem mudar, ser removidos, ficar temporariamente indisponíveis ou ter dados de teste redefinidos durante a alpha.</p>
<h2>2. Conta e segurança</h2><p>Você deve fornecer informações razoavelmente corretas, proteger sua senha e não usar a conta de outra pessoa. Contas podem ser bloqueadas quando houver abuso, tentativa de acesso indevido, automação maliciosa, fraude, assédio ou uso que comprometa outros usuários ou a infraestrutura.</p>
<h2>3. Idade e uso responsável</h2><p>Se você não tiver capacidade legal para aceitar estes Termos sozinho, use o serviço com a participação ou autorização de um responsável conforme a legislação aplicável. O StrideBR procura aplicar configurações de privacidade conservadoras e minimizar exposição desnecessária de informações pessoais.</p>
<h2>4. Treinos e saúde</h2><p>O StrideBR não substitui avaliação médica, fisioterapêutica, nutricional ou orientação profissional. Informações, modelos e registros de treino são ferramentas de organização. Em caso de dor, mal-estar ou dúvida sobre segurança de uma atividade, interrompa a atividade e procure um adulto responsável ou profissional qualificado.</p>
<h2>5. Conteúdo do usuário</h2><p>Você continua responsável pelo conteúdo que cria, como cronogramas, exercícios personalizados, observações e links de referência. Não envie conteúdo ilegal, ofensivo, invasivo, enganoso ou que viole direitos de terceiros. Ao compartilhar um cronograma com um amigo, a visibilidade escolhida por você determina quem pode acessá-lo.</p>
<h2>6. Compartilhamento e colaboração</h2><p>Uma cópia compartilhada é um retrato da versão existente no momento do envio e pode se tornar independente. Recursos de cronogramas sincronizados, quando ativados, podem permitir edição colaborativa conforme as permissões do cronograma.</p>
<h2>7. Disponibilidade e alpha</h2><p>Não existe garantia de disponibilidade contínua durante a alpha. Backups e medidas de segurança são adotados conforme a evolução do projeto, mas testers não devem usar o StrideBR como único local para guardar informação insubstituível.</p>
<h2>8. Administração e moderação</h2><p>Moderadores, administradores e o proprietário podem agir sobre contas e conteúdo quando necessário para segurança, suporte, cumprimento destes Termos e manutenção do serviço. Ações administrativas sensíveis são registradas em log interno.</p>
<h2>9. Propriedade e código</h2><p>O código-fonte público do projeto pode ter licença própria indicada no repositório. A existência do código no GitHub não concede acesso às contas, ao banco de dados de produção nem aos dados privados dos usuários.</p>
<h2>10. Alterações</h2><p>Estes Termos podem mudar durante a evolução do StrideBR. O sistema registra a versão aceita no cadastro; mudanças relevantes podem exigir novo aceite no futuro.</p>
<h2>11. Contato</h2><p>Durante a alpha, dúvidas sobre conta, privacidade ou uso podem ser enviadas pelos canais de suporte/contato disponíveis no próprio site.</p>';
require dirname(__DIR__,3).'/src/layout/static_page.php';
