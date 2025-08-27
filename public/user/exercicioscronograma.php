<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once("../../src/config/pg_config.php");
require_once __DIR__ . "/../../vendor/autoload.php";
use Hidehalo\Nanoid\Client;

if (isset($_SESSION['EmailUsuario']) && isset($_SESSION['SenhaUsuario'])) {
    $estalogado = true;
    $user = $_SESSION['NomeUsuario'];
    $idusuario = $_SESSION['IdUsuario'];
} else {
    $_SESSION['previous_page'] = "../../public/user/cronogramatreinos.php";
    header('Location: ../login.php');
    exit;
}

$treinosMap = [];

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
    <form action="../../src/function/salvarexercicio.php" method="POST">
        <input type="hidden" name="idusuario" value="<?php echo htmlspecialchars($idusuario); ?>">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exercício</th>
                    <th>Séries</th>
                    <th>Repetições</th>
                    <th>Bloco</th>
                    <th>Cluster</th>
                    <th>Descanso</th>
                    <th>Observações</th>
                    <th>Excluir</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php
                        $numero = 0;
                        $numero++;
                    echo "<td>{$numero}</td>";

                    ?>
                    <td><input type="text" name=""></td>
                    <td><input type="number" name=""></td>
                    <td><input type="text" name=""></td>
                    <td><input type="text" name=""></td>
                    <td><input type="text" name=""></td>
                    <td><input type="text" name=""></td>
                    <td><textarea name=""></textarea></td>
                    <td>—</td>
                </tr>
            </tbody>
            <button type="">Adicionar Linha</button>
            <button type="submit">Salvar Alterações</button>
        </table>

<!-- <table class="table table-striped table-bordered">
          <thead>
            <tr>
              <th>#</th>
              <th>Nome do Livro</th>
               <th class="text-center">Capa</th>
              <th class="text-center">Preço</th>
              <th class="text-center">Status</th>
              <th class="text-center">Editar</th>
              <th class="text-center">Excluir</th>
            </tr>
          </thead>
          <tbody>
            <?php
                // $sql = "SELECT * FROM anuncio WHERE anuncio.id_usuario='$idlogado'";
                // $query = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
                // $numero = 0;

                // while ($row = mysqli_fetch_assoc($query)) {
                //   $numero++;
                //   echo "<tr>";
                //   echo "<td>{$numero}</td>";
                 
                //   echo "<td>{$row['nome_livro']}</td>";

                  
                //   echo "<td><img src=\"img/{$row['foto']}\" width=\"80\" height=\"110\"></td>";

                //   echo "<td>{$row['preco']}</td>";

                //    echo "<td>{$row['status']}</td>";
                
                  
                //   echo "<td class='text-center'><a class='btn btn-outline-dark mt-auto' href='form_edita_anuncio.php?id_anu={$row['id_anuncio']}'>Editar</a></td>";
                  
                //   echo "<td class='text-center'><a class='btn btn-outline-dark mt-auto btn-excluir' href='excluir_anuncio.php?id_anu={$row['id_anuncio']}'>Excluir</a></td>";
                  
                //   echo "</tr>";
                // }
            ?>
          </tbody>
        </table> -->
    </form>
</body>
</html>