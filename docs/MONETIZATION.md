# Monetização do StrideBR

O StrideBR mantém publicidade e doações separadas dos dados esportivos. A configuração nasce desligada e só é ativada por variáveis de ambiente.

## Publicidade

```text
STRIDEBR_ADS_ENABLED=0
STRIDEBR_ADS_PLACEHOLDERS=0
STRIDEBR_ADS_DEV_PREVIEW=0
STRIDEBR_ADSENSE_CLIENT=
STRIDEBR_ADSENSE_SLOT_FOOTER=
STRIDEBR_ADSENSE_SLOT_RAIL_LEFT=
STRIDEBR_ADSENSE_SLOT_RAIL_RIGHT=
```

`STRIDEBR_ADS_PLACEHOLDERS=1` mostra os espaços reservados sem carregar rede de anúncios. `STRIDEBR_ADS_DEV_PREVIEW=1` permite a mesma prévia apenas fora de produção.

Para ativar AdSense:

1. Adicione o domínio no AdSense e conclua a verificação do site.
2. Crie os blocos usados pelo StrideBR.
3. Configure `STRIDEBR_ADSENSE_CLIENT` com o identificador `ca-pub-...`.
4. Configure os IDs dos blocos em `STRIDEBR_ADSENSE_SLOT_*`.
5. Publique um `ads.txt` válido na raiz pública usando `public/ads.txt.example` como modelo.
6. Defina `STRIDEBR_ADS_ENABLED=1`.
7. Depois de validar os anúncios reais, mantenha `STRIDEBR_ADS_PLACEHOLDERS=0`.

O carregamento do AdSense é centralizado em `public/assets/js/ads.js` e só ocorre depois de consentimento local para publicidade.

## Onde anúncios podem aparecer

Na primeira versão, anúncios ficam restritos a páginas públicas sem dados do atleta, como páginas institucionais, ajuda, roadmap e novidades. O componente não é carregado em páginas autenticadas, atividades, progresso, treinos, perfil/configurações, GPS, APIs, administração, autenticação, documentos legais nem na página de doação.

Essa separação evita enviar conteúdo de treino, atividade ou integração a uma rede de anúncios. Dados recebidos de Fitbit ou de qualquer outra conexão esportiva não podem ser usados para publicidade ou segmentação.

## Consentimento e privacidade

O controle local oferece `Somente essenciais` e `Permitir publicidade`. Antes de ativar uma rede real em produção, revise Política de Privacidade, Cookies e os requisitos de consentimento do fornecedor para as regiões atendidas.

## Doações

```text
STRIDEBR_DONATION_ENABLED=0
STRIDEBR_DONATION_PIX_KEY=
STRIDEBR_DONATION_PIX_NAME=
STRIDEBR_DONATION_URL=
```

A página `/pages/about/support-project.php` só publica meios de apoio quando `STRIDEBR_DONATION_ENABLED=1` e existe uma chave PIX ou URL HTTPS configurada. O link `Apoie o StrideBR` aparece no rodapé apenas quando a função está realmente ativa.

Doações são voluntárias e não alteram recursos, visibilidade, moderação ou acesso a dados.

## Instagram

```text
STRIDEBR_INSTAGRAM_URL=
```

Quando configurado, o Instagram aparece junto ao GitHub no rodapé.
