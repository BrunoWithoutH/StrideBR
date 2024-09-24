<?php
include_once("../config.php");

// Conectar ao banco de dados
$conn = $conexao;

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$user_id = 1; // Defina o ID do usuário de acordo com a lógica da sua aplicação

$dias_semana = ["Domingo", "Segunda", "Terca", "Quarta", "Quinta", "Sexta", "Sabado"];
$periodos = ["Manha", "Tarde", "Noite"];

foreach ($dias_semana as $dia) {
    foreach ($periodos as $periodo) {
        $campo = $dia . "_" . $periodo;
        if (isset($_POST[$campo])) {
            $treino = $_POST[$campo];
            $sql_insert = "INSERT INTO cronograma_treinos (IdUsuario, DiaSemanaCronograma, TurnoCronograma, TextoCronograma) VALUES (?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE TextoCronograma=VALUES(TextoCronograma)";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                $stmt_insert->bind_param("isss", $user_id, $dia, $periodo, $treino);
                if (!$stmt_insert->execute()) {
                    echo "Erro na execução: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                echo "Erro na preparação: " . $conn->error;
            }
        }
    }
}

$conn->close();
header('Location: cronogramatreinos.php');
?>