# StrideBR

O **StrideBR** é uma aplicação web focada em registrar e acompanhar atividades físicas, ajudando o usuário a manter um histórico de treinos e melhorar seu desempenho.
O sistema foi desenvolvido em **PHP** com **PostgreSQL/MySQL**, e possui funcionalidades que permitem o registro detalhado de corridas, ciclismo e outros esportes, além de gerenciamento avançado de treinos semanais.

---

## Funcionalidades

* **Registro de atividades físicas** com:

  * Tipo de atividade (corrida, ciclismo, etc.)
  * Data e hora
  * Duração (com precisão de segundos)
  * Distância
  * Ritmo médio
  * Elevação
  * Cálculo aproximado de calorias gastas

* **Cronograma semanal de treinos**

  * Estruturado em **dias da semana × períodos (manhã, tarde, noite)**
  * Cada célula é editável para personalizar os treinos
  * Possibilidade de expandir a célula para detalhar cada treino
  * Registro de **exercícios individuais** com:

    * Nome do exercício
    * Séries
    * Repetições
    * Descanso
    * Observações
    * Campos opcionais: Bloco, Cluster, Peso, Tipo de exercício, Link de referência
  * Organização visual: arrastar/excluir exercícios, marcar treinos como concluídos ou planejados
  * Funções adicionais: clonagem de treinos, exportação para PDF/CSV, estatísticas (volume de treino, tempo gasto)

* **Sistema de login e autenticação**

  * Redirecionamento automático para a página original após login

* **Cálculo automático de estatísticas**

  * Ritmo médio (pace)
  * Gasto calórico estimado baseado em peso, tempo e velocidade

* **Visualização e filtragem**

  * Filtrar por grupo muscular, tipo de treino ou tags personalizadas
  * Histórico de treinos e desempenho ao longo do tempo

---

## Tecnologias Utilizadas

* **Backend:** PHP (PDO)
* **Banco de Dados:** PostgreSQL / MySQL
* **Frontend:** HTML, CSS
* **Controle de versão:** Git + GitHub
* **Identificadores únicos:** [NanoID](https://github.com/ai/nanoid)