# Eventos esportivos

O calendário público usa dados cadastrados em `stridebr.eventos_esportivos`. A administração pode criar e editar eventos em `/admin/events.php`, adicionar fontes e enviar imagens.

## Dados úteis ao importar uma fonte externa

Quando um evento vier de uma página HTML, PDF, regulamento ou calendário externo, preserve somente informações que a fonte realmente informa:

- título;
- modalidade ou tipo do evento;
- data e hora de início e término;
- cidade, estado, local e endereço;
- organizador;
- distâncias ou categorias anunciadas;
- prazo de inscrição;
- URL oficial;
- URL de inscrição;
- descrição curta;
- uma ou mais fontes usadas para conferir os dados.

Não invente preço, percurso, regulamento ou detalhes ausentes. O StrideBR exibe as fontes para o usuário confirmar informações que podem mudar.

## Imagens

O painel aceita JPG, PNG e WebP de até 6 MB por arquivo e no máximo 8 imagens por evento. Antes de publicar uma imagem obtida de outra página, confirme que ela pode ser reutilizada ou prefira material oficial disponibilizado para divulgação.

## Inserções geradas a partir de arquivos

Se uma página, HTML salvo ou PDF for usado para montar vários eventos, gere uma nova migration ou um script SQL separado com `INSERT ... ON CONFLICT` apropriado. Não altere migrations antigas já aplicadas em produção.

Depois de importar, confira manualmente os eventos em `/admin/events.php` antes de publicá-los.


## Importador do painel administrativo

O painel `/admin/events.php` aceita importação por JSON ou CSV. A importação possui uma etapa de prévia e não publica os eventos automaticamente: itens válidos entram como `rascunho` para revisão manual.

JSON pode ser uma lista direta ou um objeto com a chave `eventos`:

```json
{
  "eventos": [
    {
      "titulo": "Corrida exemplo",
      "data_inicio": "2026-09-20 08:00",
      "cidade": "Porto Alegre",
      "estado": "RS",
      "modalidade": "Corrida",
      "distancias": ["5 km", "10 km"],
      "url_oficial": "https://exemplo.invalid/evento"
    }
  ]
}
```

CSV aceita vírgula, ponto e vírgula ou tabulação. Use a primeira linha como cabeçalho. O importador reconhece nomes comuns equivalentes para os campos, mas a prévia deve ser revisada antes da confirmação.

A prévia separa itens novos, possíveis duplicados e linhas que precisam de correção. A detecção de duplicados considera título, data e cidade e também impede duplicatas repetidas dentro do próprio arquivo importado. O limite atual é de 200 eventos e 1 MB por importação.
