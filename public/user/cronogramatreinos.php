<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
use Hidehalo\Nanoid\Client;

if (isset($_SESSION['EmailUsuario']) || isset($_SESSION['SenhaUsuario'])) {
    $estalogado = TRUE;
    $user = $_SESSION['NomeUsuario'];
    $idusuario = $_SESSION['IdUsuario'];
} else {
    $_SESSION['previous_page'] = "../../public/user/cronogramatreinos.php";
    header('Location: ../login.php');
    exit;
}

$dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$turnos = ['Manhã', 'Tarde', 'Noite'];

$stmt = $pdo->prepare("SELECT idcronograma, diasemanacronograma AS dia, turnocronograma AS turno, titulotreinocronograma AS titulo 
                       FROM cronogramas 
                       WHERE idusuario = :idusuario");
$stmt->execute([':idusuario' => $idusuario]);
$treinos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$treinosMap = [];
foreach ($treinos as $t) {
    $treinosMap[$t['dia']][$t['turno']] = [
        'idcronograma' => $t['idcronograma'],
        'titulo' => $t['titulo']
    ];
}

$exerciciosPorCronograma = [];
if (!empty($treinos)) {
    $ids = array_column($treinos, 'idcronograma');
    if ($ids) {
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT idcronograma, nomeexercicio, seriesexercicio, repeticoesexercicio, cargaexercicio, blocoexercicio, clusterexercicio, descansoexercicio, observacoesexercicio
                FROM exercicios_cronograma
                WHERE idcronograma IN ($in)
                ORDER BY ordemexercicio ASC";
        $stmtEx = $pdo->prepare($sql);
        $stmtEx->execute($ids);
        foreach ($stmtEx->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            $exerciciosPorCronograma[$ex['idcronograma']][] = $ex;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/favicons/favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/cronogramas.css">
    <title>Cronograma | StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="../index.php"><img src="../assets/img/StrideBRLogo.png" alt="StrideBR" class="logoSTBR"></a>
                        <div class="dropdown">
                            <button class="dropbtn">Início<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="../home.php" class="NavItem">Painel principal</a>
                                <a href="../calendario.php" class="NavItem">Calendário de corridas</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Treinos<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="cronogramatreinos.php" class="NavItem">Seu Cronograma de Treinos</a>
                                <a href="atividades.php" class="NavItem">Atividades</a>
                                <a href="ferramentastreino.php" class="NavItem">Treino</a>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="dropbtn">Ajuda<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="" class="NavItem">Suporte StrideBR</a>
                                <a href="" class="NavItem">FAQ</a>
                            </div>
                        </div>
                        <div class="usersection">
                            <?php if ($estalogado): ?>
                                <div class="dropdown" style="float:right;">
                                    <button class="dropbtnimg"><img class="userimage" src="../assets/img/userdefault.svg" alt="Usuário"></button>
                                    <div class="dropdown-content" style="right: 0;">
                                        <a href="" class="NavItem">Configurações</a>
                                        <a href="../function/logout.php">Sair</a>
                                    </div>
                                </div>

                            <?php else: ?>
                                <a href="../login.php"><button class="LogButton">Entrar</button></a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </section>
            </div>
        </div>
        <div class="row">
            <h1 class="textcenter">Cronograma de Treinos</h1>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <form action="../../src/function/salvarcronograma.php" method="POST">
                    <input type="hidden" name="idusuario" value="<?php echo htmlspecialchars($idusuario); ?>">
                    <table class="cronogramatable">
                        <thead>
                            <tr>
                                <th></th>
                                <?php foreach ($dias as $dia): ?>
                                    <th><?php echo $dia; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($turnos as $turno): ?>
                                <tr>
                                    <th><?php echo $turno; ?></th>
                                    <?php foreach ($dias as $dia): ?>
                                        <?php
                                        $cell = isset($treinosMap[$dia][$turno]) ? $treinosMap[$dia][$turno] : null;
                                        $idcronograma = $cell ? $cell['idcronograma'] : null;
                                        $titulo = $cell ? htmlspecialchars($cell['titulo']) : '';
                                        $modalexId = $dia . "_" . $turno;
                                        ?>
                                        <td>
                                            <div class="cell-title">
                                                <textarea name="<?php echo $dia . '_' . $turno; ?>" placeholder=""><?php echo $titulo; ?></textarea>
                                                <div class="btn-group">
                                                    <button type="button" class="expand-btn uil uil-expand-arrows-alt" data-target="modal-<?php echo $modalexId; ?>"></button>
                                                    <?php if ($idcronograma): ?>
                                                        <a href="exercicioscronograma.php?idcronograma=<?php echo urlencode($idcronograma); ?>">
                                                            <button type="button" class="edit-btn uil uil-pen icon"></button>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="exercicioscronograma.php?dia=<?php echo urlencode($dia); ?>&turno=<?php echo urlencode($turno); ?>">
                                                            <button type="button" class="edit-btn uil uil-pen icon"></button>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <!-- MODAL INDIVIDUAL PARA ESTA CÉLULA -->
                                            <div class="modal" id="modal-<?php echo $modalexId; ?>">
                                                <div class="modal-content">
                                                    <span class="close">&times;</span>
                                                    <h3>Exercícios</h3>
                                                    <?php if ($idcronograma && !empty($exerciciosPorCronograma[$idcronograma])): ?>
                                                        <div class="exerciciosmodal" id="<?php echo $modalexId; ?>">
                                                            <table class="table table-sm table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Exercício</th>
                                                                        <th>Séries</th>
                                                                        <th>Repetições</th>
                                                                        <th>Carga</th>
                                                                        <th>Bloco</th>
                                                                        <th>Cluster</th>
                                                                        <th>Descanso</th>
                                                                        <th>Observações</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($exerciciosPorCronograma[$idcronograma] as $ex): ?>
                                                                        <tr>
                                                                            <td><?php echo htmlspecialchars($ex['nomeexercicio'] ?? ''); ?></td>
                                                                            <td><?php echo htmlspecialchars($ex['seriesexercicio'] ?? ''); ?></td>
                                                                            <td><?php echo htmlspecialchars($ex['repeticoesexercicio'] ?? ''); ?></td>
                                                                            <td><?php echo htmlspecialchars($ex['cargaexercicio'] ?? ''); ?></td>
                                                                            <td><?php echo htmlspecialchars($ex['blocoexercicio'] ?? ''); ?></td>
                                                                            <td><?php echo htmlspecialchars($ex['clusterexercicio'] ?? ''); ?></td>
                                                                            <td><?php echo htmlspecialchars($ex['descansoexercicio'] ?? ''); ?></td>
                                                                            <td><?php echo htmlspecialchars($ex['observacoesexercicio'] ?? ''); ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="exerciciosmodal" id="<?php echo $modalexId; ?>">
                                                            Seus treinos aparecerão aqui.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <!-- FIM DO MODAL INDIVIDUAL -->
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="save-btn textcenter" type="submit" name="submit">Salvar</button>
                </form>

            </div>
        </div>
    </div>
    <footer class="textcenter footer">
        <p>© 2024 StrideBR. Todos os direitos reservados.</p>
    </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../assets/js/cronogramas.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>

</html>