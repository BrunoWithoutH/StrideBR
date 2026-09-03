# Compartilhamento de atividades v2

## Objetivo

O compartilhamento do StrideBR não depende de uma rota. A atividade continua compartilhável quando só possui métricas, e a rota passa a ser um elemento opcional do cartão. Treinos intervalados, voltas, tentativas e outros registros com múltiplas unidades podem ter uma rota própria e ser compartilhados em conjunto ou individualmente.

## Regras de produto

- Toda atividade registrada pode abrir o compartilhamento, com ou sem rota.
- Quando existe uma rota utilizável, a tela mostra **Exibir rota?**. Desmarcar essa opção remove o traçado do cartão sem alterar os dados originais.
- A ocultação de início/fim configurada pelo usuário é aplicada às imagens compartilháveis e à exportação de rota, nunca à rota privada armazenada.
- O preset **Dados** funciona sem rota e é o fallback quando um visual que exige rota não está disponível.
- **Copiar card** tenta colocar o PNG na área de transferência; navegadores sem suporte recebem orientação para usar **Baixar**.
- **Exportar rota como PNG** fica em Mais opções e produz uma imagem transparente só do traçado, respeitando a privacidade da rota.

## Trechos, tiros, voltas e tentativas

Modalidades que aceitam múltiplas unidades podem armazenar uma rota por unidade em `rotas_unidades_atividade`.

Cada unidade pode carregar:

- métricas próprias;
- rótulo e tipo;
- rota GeoJSON própria;
- distância do traçado;
- dados opcionais de elevação.

Na tela de detalhes, uma unidade com rota mostra uma prévia leve do traçado. No formulário de registro/edição, a rota da unidade fica dentro de uma seção recolhida para não tornar o formulário pesado.

### Privacidade de trechos

Para uma atividade com vários trechos, a distância de ocultação do início é aplicada ao primeiro trecho e a distância de ocultação do fim ao último. Trechos intermediários não são recortados por padrão. A rota original de cada unidade permanece privada no registro.

## Modos de compartilhamento

### Atividade

Gera um cartão da atividade inteira. A rota é opcional.

### Trechos — todos juntos

Gera uma única imagem com os trechos selecionados, suas métricas e, quando habilitado, mini-rotas das unidades que possuem traçado.

### Trechos — separados

Gera um cartão por trecho selecionado. O usuário pode escolher qual trecho aparece na prévia e então:

- baixar as imagens;
- compartilhar os arquivos via Web Share quando suportado;
- usar cada imagem separadamente em Stories ou montagens.

No app mobile futuro, o mesmo fluxo deverá oferecer **Salvar na galeria** para um ou vários cards sem depender do comportamento de downloads do navegador.

## Layout desktop e mobile web

### Desktop

A prévia permanece visível ao lado das opções. Personalização avançada fica no painel lateral.

### Mobile web

A prévia e as ações rápidas permanecem na tela principal. **Personalizar** abre um bottom sheet com opções de elementos, trechos, rota, foto e exportação. O sheet usa transição curta e respeita `prefers-reduced-motion`.

## App mobile futuro

O app deve usar o mesmo modelo de dados e API. Durante gravação GPS, marcar uma volta/tiro poderá associar diretamente o recorte de coordenadas à unidade correspondente. O app também deverá oferecer compartilhamento nativo e salvamento de vários cards na galeria.

## Migration

Aplicar:

```text
20260903_v1_rc.sql
```

Ela cria `rotas_unidades_atividade` e não altera as rotas gerais existentes.
