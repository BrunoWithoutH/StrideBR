<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
use Hidehalo\Nanoid\Client;

if (isset($_SESSION['EmailUsuario']) && isset($_SESSION['SenhaUsuario'])) {
    $estalogado = true;
    $user = $_SESSION['NomeUsuario'];
    $idusuario = $_SESSION['IdUsuario'];
    $idcronograma = $_GET['idcronograma'] ?? null;
} else {
    $_SESSION['previous_page'] = "../../public/user/cronogramatreinos.php";
    header('Location: ../login.php');
    exit;
}

$exerciciosstmt = $pdo->prepare("
    SELECT 
        exercicios_cronograma.idexercicio,
        exercicios_cronograma.idcronograma,
        nomeexercicio, 
        seriesexercicio, 
        repeticoesexercicio, 
        blocoexercicio, 
        clusterexercicio, 
        descansoexercicio, 
        observacoesexercicio, 
        cargaexercicio, 
        ordemexercicio
    FROM exercicios_cronograma
    WHERE exercicios_cronograma.idcronograma = :idcronograma
    ORDER BY ordemexercicio ASC
");
$exerciciosstmt->execute([':idcronograma' => $idcronograma]);
$treinos = $exerciciosstmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client = new Client();
    $idcronograma = $_POST['idcronograma'];
    $nomeexercicios = $_POST['nomeexercicio'] ?? [];
    $seriesexercicios = $_POST['seriesexercicio'] ?? [];
    $repeticoesexercicios = $_POST['repeticoesexercicio'] ?? [];
    $cargaexercicios = $_POST['cargaexercicio'] ?? [];
    $blocoexercicios = $_POST['blocoexercicio'] ?? [];
    $clusterexercicios = $_POST['clusterexercicio'] ?? [];
    $descansoexercicios = $_POST['descansoexercicio'] ?? [];
    $observacoesexercicios = $_POST['observacoesexercicio'] ?? [];
    $idexercicios = $_POST['idexercicio'] ?? [];
    $toDelete = $_POST['delete'] ?? [];

    $updateStmt = $pdo->prepare("UPDATE exercicios_cronograma SET nomeexercicio = :nome, seriesexercicio = :series, repeticoesexercicio = :reps, cargaexercicio = :carga, blocoexercicio = :bloco, clusterexercicio = :cluster, descansoexercicio = :descanso, observacoesexercicio = :obs WHERE idexercicio = :idexercicio");
    $insertStmt = $pdo->prepare("INSERT INTO exercicios_cronograma (idexercicio, idcronograma, nomeexercicio, seriesexercicio, repeticoesexercicio, cargaexercicio, blocoexercicio, clusterexercicio, descansoexercicio, observacoesexercicio) VALUES (:idexercicio, :idcronograma, :nome, :series, :reps, :carga, :bloco, :cluster, :descanso, :obs)");
    $deleteStmt = $pdo->prepare("DELETE FROM exercicios_cronograma WHERE idexercicio = :idexercicio");

    foreach ($toDelete as $delId) {
        if ($delId) {
            $deleteStmt->execute([':idexercicio' => $delId]);
            if ($deleteStmt->rowCount() === 0) {
                error_log("Delete failed for idexercicio: $delId");
            }
        }
    }

    foreach ($nomeexercicios as $i => $nome) {
        $nome = trim($nome);
        $series = trim($seriesexercicios[$i] ?? '');
        $reps = trim($repeticoesexercicios[$i] ?? '');
        $carga = trim($cargaexercicios[$i] ?? '');
        $bloco = trim($blocoexercicios[$i] ?? '');
        $cluster = trim($clusterexercicios[$i] ?? '');
        $descanso = trim($descansoexercicios[$i] ?? '');
        $obs = trim($observacoesexercicios[$i] ?? '');
        $idex = $idexercicios[$i] ?? '';

        if (in_array($idex, $toDelete, true)) {
            continue;
        }

    
        if ($idex && $nome !== '') {
            $updateStmt->execute([
                ':nome' => $nome,
                ':series' => $series,
                ':reps' => $reps,
                ':carga' => $carga,
                ':bloco' => $bloco,
                ':cluster' => $cluster,
                ':descanso' => $descanso,
                ':obs' => $obs,
                ':idexercicio' => $idex
            ]);
            if ($updateStmt->rowCount() === 0 && $idex) {
                error_log("Update failed for idexercicio: $idex");
            }
        }

        elseif (!$idex && $nome !== '') {
            $newId = $client->generateId(12);
            $insertStmt->execute([
                ':idexercicio' => $newId,
                ':idcronograma' => $idcronograma,
                ':nome' => $nome,
                ':series' => $series,
                ':reps' => $reps,
                ':carga' => $carga,
                ':bloco' => $bloco,
                ':cluster' => $cluster,
                ':descanso' => $descanso,
                ':obs' => $obs
            ]);
        }
    }

    if (isset($_POST['titulocronograma'])) {
        $novoTitulo = trim($_POST['titulocronograma']);
        $updateTituloStmt = $pdo->prepare("UPDATE cronogramas SET titulotreinocronograma = :titulo WHERE idcronograma = :idcronograma");
        $updateTituloStmt->execute([
            ':titulo' => $novoTitulo,
            ':idcronograma' => $idcronograma
        ]);
    }

    header("Location: exercicioscronograma.php?idcronograma=" . urlencode($idcronograma));
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Exercícios do Cronograma</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
        input[type="text"], input[type="number"] { width: 95%; }
        textarea { width: 95%; height: 50px; }
        .delete-btn { color: red; cursor: pointer; }
    </style>
</head>
<body>
    <form action="exercicioscronograma.php?idcronograma=<?php echo htmlspecialchars($idcronograma); ?>" method="POST">
        <input type="hidden" name="idusuario" value="<?php echo htmlspecialchars($idusuario); ?>">
        <input type="hidden" name="idcronograma" value="<?php echo htmlspecialchars($idcronograma); ?>">
        <?php
        $tituloStmt = $pdo->prepare("SELECT titulotreinocronograma FROM cronogramas WHERE idcronograma = :idcronograma LIMIT 1");
        $tituloStmt->execute([':idcronograma' => $idcronograma]);
        $titulo = $tituloStmt->fetchColumn();
        ?>
        <input type='text' name='titulocronograma' value="<?php echo htmlspecialchars($titulo ?? ''); ?>">
        <table id="exercicios-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exercício</th>
                    <th>Séries</th>
                    <th>Repetições</th>
                    <th>Carga</th>
                    <th>Bloco</th>
                    <th>Cluster</th>
                    <th>Descanso</th>
                    <th>Observações</th>
                    <th>Excluir</th>
                </tr>
            </thead>
            <tbody id="exercicios-tbody">
                <?php foreach ($treinos as $index => $treino):
                    $numero = $index + 1;
                ?>
                <tr>
                    <td><?php echo $numero; ?></td>
                    <td>
                        <input type="hidden" name="idexercicio[]" value="<?php echo htmlspecialchars($treino['idexercicio'] ?? ''); ?>">
                        <input type='text' name='nomeexercicio[]' value='<?php echo htmlspecialchars($treino['nomeexercicio'] ?? ''); ?>'>
                    </td>
                    <td><input type='text' name='seriesexercicio[]' value='<?php echo htmlspecialchars($treino['seriesexercicio'] ?? ''); ?>'></td>
                    <td><input type='text' name='repeticoesexercicio[]' value='<?php echo htmlspecialchars($treino['repeticoesexercicio'] ?? ''); ?>'></td>
                    <td><input type='text' name='cargaexercicio[]' value='<?php echo htmlspecialchars($treino['cargaexercicio'] ?? ''); ?>'></td>
                    <td><input type='text' name='blocoexercicio[]' value='<?php echo htmlspecialchars($treino['blocoexercicio'] ?? ''); ?>'></td>
                    <td><input type='text' name='clusterexercicio[]' value='<?php echo htmlspecialchars($treino['clusterexercicio'] ?? ''); ?>'></td>
                    <td><input type='text' name='descansoexercicio[]' value='<?php echo htmlspecialchars($treino['descansoexercicio'] ?? ''); ?>'></td>
                    <td><textarea name='observacoesexercicio[]'><?php echo htmlspecialchars($treino['observacoesexercicio'] ?? ''); ?></textarea></td>
                    <td>
                        <input type="checkbox" name="delete[]" value="<?php echo htmlspecialchars($treino['idexercicio'] ?? ''); ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="linha-vazia">
                    <td><?php echo count($treinos) + 1; ?></td>
                    <td>
                        <input type="hidden" name="idexercicio[]" value="">
                        <input type='text' name='nomeexercicio[]' placeholder="Nome do novo exercício" value=''>
                    </td>
                    <td><input type='text' name='seriesexercicio[]' placeholder="Séries" value=''></td>
                    <td><input type='text' name='repeticoesexercicio[]' placeholder="Repetições" value=''></td>
                    <td><input type='text' name='cargaexercicio[]' placeholder="Carga" value=''></td>
                    <td><input type='text' name='blocoexercicio[]' placeholder="Bloco" value=''></td>
                    <td><input type='text' name='clusterexercicio[]' placeholder="Cluster" value=''></td>
                    <td><input type='text' name='descansoexercicio[]' placeholder="Descanso" value=''></td>
                    <td><textarea name='observacoesexercicio[]' placeholder="Adicione uma observação do exercício"></textarea></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        <button type="submit">Salvar Alterações</button>
    </form>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const tbody = document.getElementById('exercicios-tbody');

    function addNovaLinhaSeNecessario() {
        const linhas = tbody.querySelectorAll('tr');
        const ultimaLinha = linhas[linhas.length - 1];
        const inputs = ultimaLinha.querySelectorAll('input[type="text"], textarea');
        let algumPreenchido = false;
        inputs.forEach(input => {
            if (input.value.trim() !== '') {
                algumPreenchido = true;
            }
        });
        if (algumPreenchido) {
            const novaLinha = ultimaLinha.cloneNode(true);
            novaLinha.querySelectorAll('input, textarea').forEach(el => {
                if (el.type === 'hidden') {
                    el.value = '';
                } else {
                    el.value = '';
                }
            });
            tbody.appendChild(novaLinha);
        }
    }

    tbody.addEventListener('input', function(e) {
        const linhas = tbody.querySelectorAll('tr');
        const ultimaLinha = linhas[linhas.length - 1];
        if (ultimaLinha.contains(e.target)) {
            addNovaLinhaSeNecessario();
        }
    });
});
</script>
</body>
</html>