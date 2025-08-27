<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once("../../src/config/pg_config.php");
require_once __DIR__ . "/../../vendor/autoload.php";
use Hidehalo\Nanoid\Client;

if (isset($_SESSION['EmailUsuario']) || isset($_SESSION['SenhaUsuario'])) {
    $estalogado = TRUE;
    $user = $_SESSION['NomeUsuario'];
    $idusuario = $_SESSION['IdUsuario'];
} else {
    $_SESSION['previous_page'] = "../user/cronogramatreinos.php";
    header('Location: login.php');
    exit;
    $estalogado = FALSE;
}

$dias = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$turnos = ['Manhã','Tarde','Noite'];

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
    <style> 
        table { border-collapse: collapse; width: 100%; } 
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; } 
        textarea { width: 100%; height: 60px; resize: none; } 
        button { margin-top: 10px; padding: 8px 16px; } 
        .expand-content { display: none; margin-top: 5px; font-size: 0.9em; }
        .expand-btn { cursor: pointer; margin-left: 5px; font-size: 14px; }
        .cell-title { display: flex; justify-content: space-between; align-items: center; }
    </style>
    <script>
        function toggleExpand(id) {
            const el = document.getElementById(id);
            el.style.display = (el.style.display === 'block') ? 'none' : 'block';
        }
    </script>
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
                <form action="../../src/function/salvartreino.php" method="POST">
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
                                            $expandId = $dia . "_" . $turno;
                                        ?>
                                        <td>
                                            <div class="cell-title" style="justify-content: space-between; align-items: center;">
                                                <textarea name="<?php echo $dia . '_' . $turno; ?>" placeholder="-"><?php echo $titulo; ?></textarea>
                                                <button type="button" class="expand-btn" onclick="toggleExpand('<?php echo $expandId; ?>')">+</button>
                                            </div>
                                            <div class="expand-content" id="<?php echo $expandId; ?>">
                                                <?php if ($idcronograma): ?>
                                                    <p><a href="exercicioscronograma.php?idcronograma=<?php echo urlencode($idcronograma); ?>">Editar/Ver Exercícios</a></p>
                                                <?php else: ?>
                                                    <p><a href="exercicioscronograma.php?dia=<?php echo urlencode($dia); ?>&turno=<?php echo urlencode($turno); ?>">Adicionar Treino</a></p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="submit">Salvar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>