# Alpha readiness update

## 2026-09-02 — editor leve, edição modal e páginas de erro

- O carregamento sob demanda dos campos volta a montar corretamente seletores de trechos usando um catálogo leve de modalidades, sem carregar todas as versões de modelos esportivos.
- A edição de atividade usa o mesmo catálogo leve e abre como modal sobre o histórico de Atividades, preservando a página atual.
- Salvar ou apagar a partir da edição modal devolve o resultado à página de Atividades, atualiza o histórico sem navegação completa e fecha o modal.
- Em telas pequenas, os editores e painéis grandes ocupam a viewport inteira e ficam acima da navegação persistente.
- As páginas 403, 404 e 500 mantêm o fundo próprio, mas passam a usar proporções, bordas, botões, cabeçalho e superfícies coerentes com a interface principal do StrideBR.
- Nenhuma migration nova é necessária nesta rodada.

## 2026-09-02 — robustez, feedback e fluidez

- Avisos, erros, sucesso e ações com desfazer passam a compartilhar o mesmo sistema visual de toast do StrideBR, inclusive o fallback de restauração após remoção de atividade.
- O wrapper de rede diferencia timeout, falta de conexão e falha de comunicação sem apagar o conteúdo já preenchido.
- As páginas 403, 404 e 500 foram refeitas com a identidade visual do StrideBR, ações de retorno e referência de requisição quando disponível.
- Progresso navega entre áreas, meses e janelas de 1, 3, 6 e 12 meses sem recarregar a página inteira, preservando contexto e histórico do navegador.
- O carregamento dos campos do registro busca diretamente apenas o modelo solicitado e evita recarregar equipamentos, treinos e biblioteca de exercícios depois da primeira abertura.
- A edição deixa de consultar biblioteca e histórico de séries quando a modalidade não é de força.
- Arquivar meta oferece desfazer e atualiza as seções sem recarregar a página.
- Importação em lote usa concorrência limitada, progresso no botão e mantém os itens com falha disponíveis para correção.
- O arquivo `.env.example` volta a fazer parte do pacote de desenvolvimento, alinhado às versões legais e às integrações opcionais atuais.
- Nenhuma migration nova é necessária nesta rodada.

## 2026-09-02 — registro, histórico e compartilhamento

- O editor de registro passa a buscar apenas o modelo esportivo selecionado e carrega outros formatos sob demanda, evitando montar o catálogo inteiro de campos ao abrir a atividade.
- O histórico invalida o cache e atualiza em segundo plano após salvar ou importar atividades, inclusive ao voltar da tela de importação pelo histórico do navegador.
- A prévia pós-salvamento usa o mesmo renderizador do compartilhamento completo.
- O primeiro compartilhamento usa Story 9:16, rota quando disponível e fundo Azul profundo.
- A configuração usada em uma ação real de compartilhar, copiar ou baixar passa a ser o padrão da próxima atividade; apenas mexer no editor não altera o padrão.
- O export de dados da conta usa `pagina AS contexto` para feedbacks e o teste de segurança deixa de tratar o `.env` privado da instalação como arquivo indevido do pacote.


## 2026-09-02 — esporte, força, energia e conexões

- O seletor de esportes passa a navegar por categorias, mostrar primeiro as modalidades mais comuns e revelar o catálogo completo em “Mais esportes”, mantendo busca, favoritos e recentes.
- Progresso passa a usar hubs por família esportiva, com análises próprias para Força, Cardio, Atletismo, Raquetes, Esportes em equipe, Lutas e Precisão.
- Musculação registra exercícios e séries com carga, repetições e RIR, mostra referências da sessão anterior e permite reaproveitar explicitamente a última sessão.
- Atletismo de campo registra tentativas, marcas, falhas e vento quando aplicável, com melhores marcas e evolução.
- O motor de energia passa a estimar calorias por modalidade usando peso histórico e os melhores dados disponíveis, preservando calorias informadas pelo dispositivo.
- A camada de Conexões passa a importar automaticamente Strava, Polar Flow, Fitbit e Suunto, com tokens protegidos, deduplicação e sincronização periódica por runner.
- Garmin Connect fica preparado para entrada de atividades e saída de treinos/percursos sem depender de endpoints privados; a ativação final depende do acesso oficial ao Developer Program.
- Doações e anúncios públicos ficam configuráveis por ambiente e desligados por padrão; anúncios não são carregados nas áreas autenticadas com dados do atleta.
- A migration de metadados musculares passa a criar suas próprias dependências antes de preenchê-las, evitando falha pela ordem lexical das migrations.

This revision prepares StrideBR for a small closed alpha without requiring public-release infrastructure to be enabled immediately.

## Added

- Versioned Terms of Use and Privacy Policy acceptance on signup
- Optional forced re-acceptance when legal-document versions change
- Closed-alpha feedback form and admin feedback queue
- Feature flags for registration, invite-only signup, email verification, required verification, password reset, feedback and legal re-acceptance
- Invite generation/revocation in the admin user area
- Email verification and password-reset token flows, disabled by default
- Admin user search, edit, block/unblock, role management and owner-only hard deletion
- Session-version invalidation after sensitive account changes or blocks
- Security headers through Apache `.htaccess`
- Admin audit logging for new sensitive actions
- Security and alpha-testing documentation

## Database

Apply, in order:

1. `src/database/migrations/20260815_product_foundation.sql`
2. `src/database/migrations/20260815_alpha_readiness.sql`

For an existing database, `./scripts/migrate_product.sh` now runs both migrations.

## Email

Email verification and password reset stay disabled until transactional email is configured. Set:

- `STRIDEBR_APP_URL`
- `STRIDEBR_MAIL_FROM`
- `STRIDEBR_MAIL_FROM_NAME`

Then enable the corresponding feature flags in `/admin/`.

## Removed

The old `pg_config-local-template.php` and `pg_config-web-template.php` files were removed. Runtime configuration is only through `src/config/pg_config.php` and environment variables.

## 2026-08-26 — Planning & Activities Performance v2

- Histórico de atividades assíncrono com skeleton, busca, filtro e cursor sem reload.
- Detalhes e rotas das atividades carregados sob demanda.
- Biblioteca reutilizável **Meus treinos**.
- Vigência real para séries de cronograma, impedindo projeção de treinos para datas anteriores ao início.
- Exceções por ocorrência: mover, pular, alterar só uma data, esta e as próximas ou toda a série.
- Calendário mensal sem reload, com cache/prefetch e drag-and-drop no desktop.
- Preservação dos campos estendidos de exercícios em cópias, agenda e início de sessão.
- Migration `20260826_planning_performance_v2.sql`.

- 2026-08-26: Hotfix do histórico assíncrono de Atividades: estados loading/empty/error respeitam `hidden` e métricas dos cards recuperam o layout correto.

## 2026-08-27 — Calendar quick-create polish

- **Meus treinos** deixou de exibir formulários de criação/agendamento dentro de todos os cards.
- Novo card de treino abre um editor compacto somente quando solicitado.
- Clique em um dia do calendário mensal abre o editor rápido de treino, sem recarregar a página.
- O editor rápido aceita treino novo ou treino salvo, data, horário, recorrência e detalhes opcionais.
- **Editar exercícios** preserva o rascunho no navegador, abre o editor dedicado e retorna ao mesmo popup antes do salvamento final.
- Arrastar um treino salvo para um dia agora abre o mesmo editor rápido em vez de pedir horário por `prompt()`.
- Cards da biblioteca ganharam ações mais discretas e edição própria de exercícios.

## 2026-08-28 — Consistência visual e performance de atividades

- O histórico de Atividades físicas deixa de montar antecipadamente campos de modelos, equipamentos e treinos; esses dados são carregados apenas ao abrir o registrador.
- Leaflet e o CSS do mapa passam a ser carregados sob demanda, somente quando uma rota precisa ser desenhada ou exibida.
- O drawer de detalhe não reserva mais células cinzas vazias: métricas únicas ocupam a largura disponível, linhas ímpares se ajustam e seções sem dados são omitidas.
- Métricas repetidas entre o resumo e uma seção de sessão deixam de ser mostradas duas vezes.
- Avisos comuns de sucesso/informação passam a ser texto compacto temporário, sem criar banners grandes na página.
- Botões primários, secundários e destrutivos recebem uma base visual única; overlays passam a seguir uma hierarquia comum de dropdown, drawer, modal e confirmação.
- Confirmações usadas nos fluxos principais deixam de depender da caixa nativa do navegador.
- A consulta global de treino em andamento é adiada até o navegador ficar ocioso.
- A validação de sessão passa a usar 60 segundos como TTL padrão e a configuração inicial do PostgreSQL usa um único round-trip.
- Respostas passam a expor `Server-Timing`; requests acima de 2 s são registrados no log PHP para facilitar diagnóstico no Alwaysdata.
- Nenhuma migration nova é necessária nesta rodada.

## 2026-08-28 — Agenda, compartilhamento e correções em lote

- Agenda mensal usa navegação parcial com cache/prefetch, cancelamento de requisições antigas e histórico do navegador, sem recarregar a página inteira ao trocar de mês ou cronograma.
- Cronogramas, Agenda mensal e Treinador/atletas passam a compartilhar uma navegação de planejamento consistente.
- Atividades concluídas fora da data planejada podem ter somente a data histórica planejada corrigida, sem alterar a rotina futura.
- Reconciliação passa a respeitar a data planejada explicitamente associada ao registro e exceções redundantes deixam de ganhar aparência de ajuste.
- Editor de treino não recebe mais o bloco genérico de recuperação de rascunho que podia sobrepor campos do modal.
- Compartilhamento ganha modelo padrão persistente no dispositivo, personalização recolhida, composição respeitando áreas seguras de Stories, mapa azul-acinzentado em tela cheia e opção transparente/foto.
- Renderização do mapa do compartilhamento usa tiles em baixa resolução intermediária, cache e timeout curto; quando o mapa não responde, a rota limpa continua disponível.
- Seletor de modalidades de Atividades físicas fica mais denso e usa múltiplas colunas no desktop; edição usa o mesmo padrão visual.
- Histórico de Atividades físicas ganha seleção múltipla e edição em lote para modalidade, duração, visibilidade e exclusão.
- Nenhuma migration nova é necessária nesta rodada.

## 2026-08-28 — Perfil, formulários e criação em modal
- Registro de atividade física alinhado no desktop, com campos contextuais distribuídos pela mesma linha e controles de hora consistentes.
- Modal de novo treino recebeu estilos completos para inputs, selects, textarea, horários e seleção de treinos salvos.
- Criação e importação de cronogramas agora acontecem em modal, com upload de arquivo em área própria e sem inserir blocos no fluxo da página.
- Perfil ganhou banner com cor base persistente e imagem opcional; a cor funciona como fallback quando a imagem não é usada.
- Configurações de perfil foram reorganizadas em seções mais densas no desktop, mantendo uma coluna no mobile.
- Perfil ganhou até quatro destaques configuráveis, incluindo métricas automáticas e valores personalizados.
- Não há migration nova; banner e destaques ficam em `preferenciasusuario`.
## 2026-08-28 — Histórico flexível e duração em lote

- Edição em lote de Atividades físicas separa duração em **não alterar**, **definir** e **limpar**, preservando a duração existente ao trocar somente a modalidade.
- Atividades sem duração deixam o campo ausente na interface; nenhuma duração padrão é inventada.
- Definir duração em lote atualiza a duração estrutural do registro e os campos compatíveis do modelo; limpar remove ambos.
- Planejamento e realização de um treino concluído passam a ter data e hora independentes e corrigíveis sem alterar a rotina futura.
- Correções históricas podem desacoplar um registro da ocorrência originalmente associada, impedindo que um treino usado por engano consuma uma ocorrência futura.
- Diferença apenas de horário no mesmo dia não é tratada como treino realizado fora do planejamento.
- Exceções redundantes deixam de receber aparência especial; o pontilhado fica reservado a diferenças reais de planejamento.
- Reagendamento persiste data, início, fim e duração nos escopos **Só este**, **Este e os próximos** e **Todos**.
- Nova migration `20260828_schedule_history_flexibility.sql` adiciona a hora planejada histórica aos registros de atividade e sessões de treino.


## 2026-08-28 — Consistência visual e paleta

- Histórico de Atividades físicas mantém a seta de abertura alinhada ao fim da linha mesmo quando a atividade não possui métricas resumidas.
- Campos de texto, busca, data, número, seleção, textarea e upload passam a compartilhar uma base visual única; componentes especializados continuam podendo sobrescrever a base localmente.
- Configurações de perfil ganham ação explícita **Voltar ao perfil** junto ao cabeçalho.
- `ui-refresh.css` passa a declarar uma paleta-base semântica para superfícies, textos, bordas, marca, sucesso, aviso, informação, perigo e foco.
- Auditoria completa de cores foi adicionada em `docs/design/`, incluindo todos os tokens de cor encontrados no código e a proposta de paleta oficial.
- Nenhuma migration nova é necessária nesta rodada.

## 2026-08-28 — Modais empilhados, duração opcional e metas de força

- Confirmações passam a usar `dialog.showModal()` quando disponível, garantindo camada superior mesmo quando acionadas de dentro de outro modal.
- Ações abertas a partir do preview de treino respeitam uma hierarquia explícita de camadas; registro rápido fecha o preview antes de abrir.
- Preview de treino criado dinamicamente carrega seus dados por API, evitando o modal vazio que exigia F5 após adicionar um treino.
- Registro rápido de musculação aceita duração vazia ou zero; sem duração, nenhum campo de tempo é inventado na atividade.
- Edição em lote preserva a duração existente ao trocar somente a modalidade.
- Metas ampliam `periodo` e `metrica` no banco, corrigindo o erro de `VARCHAR(12)` com períodos como `personalizado`.
- Metas ganham **Carga máxima em exercício**, com seleção do exercício e progresso calculado pelas cargas de séries concluídas.
- Nova migration `20260828_goals_flexibility_v2.sql`.

## 2026-08-30 — Performance RC pass

- A tela de Atividades passa a renderizar o primeiro lote do histórico e o resumo diretamente no HTML, eliminando a dependência de uma chamada assíncrona para substituir skeletons após hard reload.
- O skeleton fica restrito a fallbacks reais de carregamento; conteúdo já visível é preservado durante filtros e atualizações.
- O resumo dos últimos 7 dias filtra primeiro os registros do usuário e só depois agrega métricas, evitando varrer valores de atividades fora do período.
- A montagem dos cards do histórico reduz consultas ao PostgreSQL ao carregar os valores necessários em lote.
- Detalhes deixam de ser abertos automaticamente e de sofrer prefetch por hover/foco, evitando rajadas de processos PHP em hospedagem compartilhada.
- Histórico e detalhes deixam de repetir requests automaticamente após timeout; as APIs usam limites menores de statement/lock para liberar recursos mais cedo.
- O resumo visual do histórico usa um seletor próprio, evitando colisão com o resumo do formulário de registro.
- Foi adicionado um teste estático específico para impedir regressões no fluxo de performance de Atividades.

## 2026-08-30 — Desktop UX e Home contextual

- A Home passa a ter **Hoje** como primeiro contexto: treino planejado, treino em andamento, treino concluído, descanso ou dia sem planejamento.
- Treinos previstos para o dia podem ser iniciados diretamente da Home, preservando a ocorrência planejada no registro da sessão.
- O painel abaixo de Hoje passa a ser personalizável: Progresso, Metas, Próximos treinos e Atividades recentes podem ser reordenados e ocultados.
- A personalização é persistida em `preferenciasusuario`, sincronizando entre navegadores sem migration nova.
- O onboarding troca configuração obrigatória por cinco etapas curtas e opcionais, incluindo esportes, objetivo, experiência, frequência e métricas de interesse.
- Respostas do onboarding são mescladas às preferências existentes em vez de sobrescrever personalizações anteriores.
- Interações globais ganham motion curto e suporte explícito a `prefers-reduced-motion`.
- Foi adicionado teste estático específico para o fluxo de Desktop UX.
## 2026-08-30 — Fechamento Desktop/RC

- Progresso passa a priorizar consistência, tendências, marcas pessoais e comparação com o próprio histórico.
- Comparação A/B de atividades usa por padrão a última atividade do mesmo esporte e oferece referências de rota e distância semelhantes; musculação separa séries, repetições, volume e carga.
- Novas atividades respeitam padrões de visibilidade e de ocultação opcional do início/fim da rota; a rota original permanece privada e completa, enquanto o cartão compartilhável usa a versão recortada.
- Exclusão de atividade passa a ser reversível por 30 minutos e consultas de uso normal ignoram registros nesse estado; a exportação integral da conta preserva deliberadamente a janela de restauração.
- Cronogramas sincronizados em modo leitura recebem notificações agregadas quando o proprietário altera treino, ocorrência, exercícios ou estrutura do cronograma.
- Painel do treinador só consulta e exibe resumo de cronogramas/atividades quando o atleta concedeu as permissões correspondentes.
- Analytics de produto usa allowlist de eventos, respeita opt-out e não envia rota, notas, cargas nem conteúdo pessoal livre.
- Submissões POST ganham chave de idempotência no servidor e nos fluxos JavaScript; formulários são bloqueados durante o envio para reduzir duplicações acidentais.
- Landing, FAQ, Sobre, Roadmap, Novidades, Termos, Privacidade, Cookies e página de apoio voluntário foram atualizados para o estado da Release Candidate.
- Nova migration `20260830_desktop_release_candidate_features.sql` adiciona soft delete, privacidade de rota, notificações, analytics e índices/feature flags associados.
- A suíte estática ganha um checklist específico de fechamento para impedir regressões nesses itens.


- Auditoria final do fechamento desktop adicionada em `docs/DESKTOP_RC_FINAL_AUDIT_20260830.md`, com checklist do que foi implementado e das validações que ainda dependem do Alwaysdata.

## 2026-08-30 — Compartilhamento v2 e rotas por trecho

- Compartilhamento deixa de exigir rota; atividades só com métricas ganham cartão completo e preset próprio de dados.
- Atividades com rota exibem a escolha explícita **Exibir rota?**, preservando a rota original e aplicando a proteção de início/fim apenas à versão compartilhável.
- Ações de saída incluem copiar o card, baixar, compartilhar via sistema quando disponível e exportar o traçado como PNG transparente.
- Modalidades com múltiplas unidades passam a aceitar uma rota opcional por trecho, tiro, volta ou tentativa.
- Trechos podem ser compartilhados em uma única montagem ou como imagens individuais, com métricas e mini-rotas próprias.
- Detalhes da atividade mostram uma prévia leve do traçado de cada unidade que possui rota.
- Personalização do compartilhamento em mobile web usa bottom sheet, sem mudar o fluxo denso do desktop.
- Exportação integral da conta inclui unidades e suas rotas.
- Nova migration `20260830_activity_sharing_v2.sql`.

## 2026-08-30 — GPS Web v1 e cadastro com IKEA effect

- Nova tela de gravação GPS Web deixa explícito que a precisão do navegador é estimada e inferior à garantia esperada de um app nativo ou relógio GPS.
- Gravação usa geolocalização de alta precisão, filtro conservador de ruído/saltos, qualidade do sinal, distância, ritmo/velocidade e elevação quando fornecida pelo aparelho.
- Lacunas longas entre amostras deixam de inventar deslocamento em linha reta; retomadas após pausa também reancoram posição e altitude.
- Estado da atividade é local-first em IndexedDB, continua independente da internet e pode ser recuperado depois de reload/fechamento acidental.
- Wake Lock é usado como melhor esforço para manter a tela ligada, sem prometer gravação confiável em background/tela bloqueada.
- Metas opcionais de distância/tempo podem finalizar automaticamente com confirmação adicional para distância; sem meta, o encerramento permanece manual.
- Tiros/trechos podem ser marcados durante a gravação e cada um preserva seu recorte de rota para o Compartilhamento v2.
- Revisão permite corrigir distância, duração e elevação antes de salvar, mantendo separada a distância originalmente medida pelo GPS Web.
- Salvamento usa chave única por gravação para tornar retries idempotentes e evitar duplicatas quando a resposta do servidor se perde.
- Home, Atividades e menu Criar ganham acesso ao gravador e corrida rápida em um toque.
- Cadastro passa a aplicar o IKEA effect antes das credenciais: esportes, objetivos, experiência, frequência, métricas e defaults são opcionais e aparecem antes de nome/e-mail/senha; a pessoa pode pular direto para a conta.
- Nova migration `20260830_gps_web.sql`; documentação e auditoria em `docs/GPS_WEB_V1.md` e `docs/GPS_WEB_V1_AUDIT_20260830.md`.
## 2026-08-30 — Onboarding e UX writing

- Cadastro com layout estável entre etapas, skip fora do rodapé e resumo condicional.
- Lista de esportes com busca, categorias e ícones do StrideBR.
- Objetivos e preferências de Progresso revisados.
- Login alinhado visualmente ao cadastro.
- Progresso passa a abrir Configurações para ajustar preferências.
- Copy redundante reduzida em fluxos centrais.
- Comparar movido para o cabeçalho do detalhe da atividade.
- Atalhos GPS passam a usar a iconografia do site.


## 2026-08-30 — Auditoria de UX, copy e consistência

- Home, notificações, treinador, configurações, compartilhamento, comparação, equipamentos, biblioteca e fluxos auxiliares tiveram copy redundante reduzida.
- Estados públicos deixam de expor instruções de migration quando Eventos ou Metas não estão disponíveis.
- `Conta e segurança` passa a usar o mesmo nome no título e no cabeçalho da página.
- Navegação mobile encurta `Atividades físicas` para `Atividades` e normaliza `Gravar com GPS`, `Importar e exportar` e `Perfil e preferências`.
- Landing troca justificativas abstratas por uma lista curta de capacidades concretas.
- Novo teste estático impede a volta das principais frases redundantes removidas e de inconsistências simples de nomenclatura.
## 2026-08-30 — Compartilhamento compacto e consistência de navegação

- Atividades sem rota passam a abrir em um cartão compacto, com composição própria e conteúdo concentrado.
- Compartilhamento ganha formatos Story, Retrato 4:5, Quadrado e Compacto, além dos fundos escuro, claro, foto e transparente.
- Código interno de treino deixa de entrar nos cartões compartilhados; foco permanece como contexto quando disponível.
- Títulos e focos longos passam a quebrar linha dentro do canvas em vez de ultrapassar a imagem.
- Exportar rota como PNG passa para o menu de três pontos; Copiar card, Baixar e Compartilhar permanecem como ações principais.
- Trechos separados respeitam o formato selecionado ao gerar os PNGs.
- Navegação principal coloca Progresso no topo, mantém Metas dentro de Progresso e trata Biblioteca/Exercícios como parte de Treinos.
- Biblioteca mantém uma única área com tabs Treinos e Exercícios.
- Recuperação de senha, redefinição, reenvio e confirmação de e-mail passam a usar a mesma família visual de Login/Cadastro.
## 2026-08-30 — Compartilhamento retrato e espaçamento de páginas

- O formato Compacto deixa de ser paisagem e passa a usar proporção 4:5, com composição própria mais densa.
- No compacto com rota, métricas ficam concentradas antes do traçado; no transparente, tipografia, rota e marca recebem escala maior para uso como overlay.
- Atividades sem rota mantêm o compacto, agora com conteúdo agrupado no centro em vez de grandes vazios verticais.
- Trechos no formato compacto usam grade retrato de até duas colunas.
- Prévia do compartilhamento passa a respeitar 4:5 também no Compacto.
- Biblioteca, Atividades e editores de exercícios recebem espaçamento superior e gutters alinhados às demais páginas de trabalho.
- Corrigido um bloco de CSS do aviso de GPS Web que continha quebras de linha literais e podia ser ignorado pelo navegador.

## 2026-08-30 — Activity workspace + share polish

- O compartilhamento compacto ganhou versões vertical e horizontal, métricas em blocos, Sporticon da modalidade e rota maior, sem mapa-base por padrão.
- O modo transparente usa elementos maiores e rota ampliada para funcionar melhor como overlay em fotos.
- Corrigido o detalhe de atividade: mapa e perfil de elevação agora recebem os dados da rota no próprio componente e recalculam o layout ao abrir/redimensionar.
- Biblioteca consolidada em uma única página com tabs Treinos/Exercícios trocadas por JavaScript, animação curta e history/URL preservados.
- Comparar atividades e Importar/Exportar agora abrem dentro do shell de Atividades, sem navegação completa da página, mantendo back/forward do navegador.

- 2026-08-31: compartilhamento recebeu layout viewport-first responsivo; notebook 1366x768 agora usa scroll do painel completo em vez de comprimir Aparência, e mobile/tablet divide a altura entre seletor, prévia e controles sem overflow.

## Ajustes de hospedagem e autenticação — 2026-08-31

- A página pública de apoio/doações foi removida da aplicação, navegação, sitemap e variáveis de ambiente para manter compatibilidade com o plano gratuito da hospedagem. A implementação anterior permanece arquivada somente no repositório em `docs/archive/support-project/`.
- Login com Google, idioma inglês e tema claro/escuro continuam incluídos nesta rodada.

## 2026-08-31 — Google sign-in feature flag

- Added `GOOGLE_OAUTH_ENABLED` as an explicit server-side switch for Google sign-in.
- Google sign-in is disabled by default, even when OAuth credentials are already configured.
- Disabling it hides the Google button and blocks new OAuth starts/exchanges without removing linked identities or credentials.
- Email/password authentication remains available regardless of the Google switch.

## 2026-08-31 — Aparência, idioma e notificações

- Aparência agora usa uma preferência única por usuário (`Sistema`, `Claro` ou `Escuro`) e aplica o tema antes do CSS para evitar o clarão de modo claro entre páginas.
- `Sistema` acompanha `prefers-color-scheme`; se a preferência não puder ser detectada, o fallback é claro.
- Idioma ganhou modo `Automático`: português quando o idioma preferido do navegador/dispositivo é português e inglês nos demais casos.
- Idioma e aparência saíram do login, cadastro e cabeçalho e ficaram concentrados em Personalização nas preferências.
- O modo escuro passou a cobrir superfícies que ainda permaneciam brancas, especialmente Histórico, resumo e detalhes de Atividades físicas.
- O sino de notificações ganhou contraste fixo no cabeçalho e um dropdown com as notificações recentes.
- Textos centrais de Atividades físicas e navegação receberam tradução estrutural para evitar telas parcialmente em português e inglês.

## 2026-08-31 — Fechamento de tema e idioma

- Aparência passou a oferecer somente `Claro` e `Escuro`, com `Claro` como padrão determinístico.
- A preferência de tema é aplicada no `<head>` antes dos stylesheets para evitar flash claro ao navegar com o modo escuro selecionado.
- O modo escuro recebeu uma varredura de superfícies legadas no dashboard, cronogramas, atividades, biblioteca, importação/exportação, modais e formulários.
- Idioma automático continua baseado no idioma preferido do navegador: português para `pt*` e inglês nos demais casos.
- A tradução de interface em inglês foi ampliada para navegação, dashboard, atividades, cronogramas, metas, biblioteca, equipamentos, eventos, conta e configurações, preservando conteúdo criado pelo usuário.
- `Editar perfil` e `Configurações` passam a ter destinos separados na navegação, reutilizando o mesmo mecanismo de persistência.
- A página pública de apoio financeiro continua removida do site e permanece apenas arquivada em `docs/archive/support-project/`.

## 2026-08-31 — Salvamento manual de atividades

- O registro manual aceita atividade sem métricas e combinações parciais como apenas distância, apenas duração ou distância + duração.
- O salvamento básico deixou de depender rigidamente das colunas novas de trechos; quando necessário, usa compatibilidade com o schema disponível e continua persistindo os valores normais.
- Atualização de preferência/último uso da modalidade e analytics passaram a ser operações auxiliares: falhas nelas não desfazem nem reportam como falha uma atividade já salva.
- A criação de atividade vazia e parcial ganhou testes executáveis com PDO simulado em schema atual e legado, incluindo falha proposital de metadado auxiliar.

## 2026-09-01 — Sport hub, strength progress, connections and funding foundation

- Progresso passa a separar visão geral, Força, Cardio e Raquetes.
- Sessões de força registram carga e repetições por série e alimentam histórico por exercício, volume, 1RM estimado, frequência e distribuição muscular.
- Progresso de carga ganha tendência visual pelas sessões recentes.
- Fitbit entra na camada de conexões com OAuth, sincronização de exercícios e leitura de TCX quando disponível.
- Atividades recém-criadas abrem um compartilhamento rápido com Story padrão e acesso ao editor completo.
- Publicidade e doações ganham configuração central, desligada por padrão; anúncios ficam fora das áreas autenticadas e de dados esportivos.
- Política de Privacidade e Cookies passam a descrever conexões externas e a fundação opcional de publicidade.

## 2026-09-02 — PostgreSQL integration hotfix

- corrigido `eventosSlugUnico()` para não usar parâmetro `NULL` ambíguo no PostgreSQL com prepared statements nativos;
- teste de energia de força agora fornece peso de referência explícito, refletindo a regra do produto de não inventar peso para estimar kcal;
- teste de rotas atualizado para o comportamento real de soft delete: a rota é preservada durante a janela de restauração de 30 minutos e continua indisponível enquanto a atividade está excluída.

## 2026-09-02 — Seletor esportivo e períodos de Progresso

- O seletor global de esportes passa a abrir acima do conteúdo da página em desktop, sem ser recortado por tabelas, históricos ou cards com `overflow`.
- Progresso ganha mês de referência e janelas de 1, 3, 6 e 12 meses, com navegação entre meses e retorno rápido ao período atual.
- Trocar período preserva a área aberta, a modalidade de Cardio e o exercício selecionado em Força.
- Resumos, comparações, distribuição por modalidade, calendário de força e dashboards especializados passam a respeitar o período escolhido.
- Gráficos semanais são ancorados no mês de referência, permitindo revisar períodos passados sem misturar semanas atuais.
- Marcas pessoais gerais permanecem históricas e são identificadas como independentes do filtro de período.
- Estados vazios e textos de Atletismo foram ajustados para deixar claro quando os dados são do período selecionado ou do histórico até a data de referência.

## 2026-09-02 — Polimento de interação e atualização sem reload

- Feedbacks não críticos das páginas autenticadas passam a usar toasts globais do StrideBR, com estados de sucesso, aviso, informação e erro, em vez de avisos soltos por página.
- Formulários tradicionais ganham estado visual de envio e bloqueio contra clique duplo enquanto a requisição está em andamento.
- O salvamento AJAX de nova atividade fica restrito ao workspace de Atividades; a tela separada de edição volta a seguir seu fluxo normal sem interpretar HTML como resposta JSON.
- Campos incompatíveis com a modalidade passam a ser filtrados também no backend ao salvar e editar atividades, evitando persistência de carga, séries, repetições ou RIR onde não fazem sentido.
- Metas passam a criar, editar, arquivar e reativar atualizando as seções Ativas e Histórico sem recarregar a página inteira.
- Cronogramas deixam de usar `location.reload()` após editar, mover ou corrigir um treino e atualizam somente a visão mensal, semanal ou agenda que estiver aberta.
- Atualizações parciais do cronograma preservam posição de rolagem, zoom da semana e os controles delegados dos elementos substituídos.

## 2026-09-02 — Sistema global de cantos

- Os raios de interface foram consolidados em quatro níveis globais: 4, 8, 12 e 16 px, com aliases semânticos para controles, menus, cards e modais.
- Círculos reais continuam usando 50% e pills reais usam radius full; valores intermediários legados foram removidos dos componentes.
- Navegação, formulários, cards, popovers, modais, drawers, sheets, progresso, cronogramas, atividades, compartilhamento, autenticação e páginas de erro passam a compartilhar os mesmos tokens geométricos.
- Navegadores com suporte a `corner-shape` recebem cantos `squircle` como progressive enhancement, preservando `border-radius` circular como fallback.
- Superfícies visualmente aninhadas do compartilhamento e pós-salvamento passam a derivar o raio interno por `max(0px, calc(outer - inset))`, evitando ajustes manuais inconsistentes.
- Modais de atividade continuam sem radius quando ocupam a viewport inteira no celular; sheets mantêm apenas os cantos superiores arredondados.
