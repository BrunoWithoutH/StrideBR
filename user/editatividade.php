<?php
session_start();
require_once("../function/pg_config.php"); // Usando PDO agora

if (!isset($_SESSION['UEmail'])) {
    header('Location: ../login.php');
    exit;
}

$email = $_SESSION['UEmail'];
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE emailusuario = :email");
$stmt->execute([':email' => $email]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    echo "Erro: Usuário não encontrado.";
    exit;
}
$usuario_id = $usuario['id'];

if (!isset($_GET['id'])) {
    echo "Erro: ID de atividade não especificado.";
    exit;
}
$atividade_id = $_GET['id'];

// Obter dados da atividade
$stmt = $pdo->prepare("SELECT * FROM atividades_fisicas WHERE id = :id AND usuario_id = :usuario_id");
$stmt->execute([
    ':id' => $atividade_id,
    ':usuario_id' => $usuario_id
]);
$atividade = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$atividade) {
    echo "Erro: Atividade não encontrada.";
    exit;
}

function formatar_data_input($data) {
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    return $data_obj ? $data_obj->format('Y-m-d') : $data;
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_atividade = $_POST['tipo_atividade'];
    $data_atividade = $_POST['data_atividade'];
    $hora_atividade = $_POST['hora_atividade'];
    $duracao = !empty($_POST['duracao']) ? $_POST['duracao'] : null;
    $distancia = !empty($_POST['distancia']) ? $_POST['distancia'] : null;
    $peso = !empty($_POST['Peso']) ? $_POST['Peso'] : null;

    // Cálculo de calorias (exemplo simples — ajuste conforme necessidade)
    $calorias = null;
    if ($peso && $duracao) {
        $calorias = round(($peso * 0.0175 * 8) * $duracao); // Exemplo baseado em MET
    }

    $update = $pdo->prepare("UPDATE atividades_fisicas SET
        tipo_atividade = :tipo_atividade,
        data_atividade = :data_atividade,
        hora_atividade = :hora_atividade,
        duracao = :duracao,
        distancia = :distancia,
        calorias = :calorias
        WHERE id = :id AND usuario_id = :usuario_id");

    $ok = $update->execute([
        ':tipo_atividade' => $tipo_atividade,
        ':data_atividade' => $data_atividade,
        ':hora_atividade' => $hora_atividade,
        ':duracao' => $duracao,
        ':distancia' => $distancia,
        ':calorias' => $calorias,
        ':id' => $atividade_id,
        ':usuario_id' => $usuario_id
    ]);

    if ($ok) {
        header("Location: atividades.php");
        exit;
    } else {
        echo "Erro ao atualizar a atividade.";
    }
}

$estalogado = isset($_SESSION['UEmail']) && isset($_SESSION['USenha']);
$user = $estalogado ? $_SESSION['UNome'] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/favicons/fav.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU90FeRpokÿmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.0/css/line.css">
    <link rel="stylesheet" href="../assets/css/atividades.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <title>StrideBR</title>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <section class="header">
                    <nav>
                        <a href="../index.php"><img src="../assets/img/StrideBRLogo.png" alt="StrideBR"
                                class="logoCR"></a>
                        <div class="dropdown">
                            <button class="dropbtn">Início<i class="uil uil-angle-down"></i></button>
                            <div class="dropdown-content">
                                <a href="../home.php" class="NavItem">Painel principal</a>
                                <a href="calendario.php" class="NavItem">Calendário de corridas</a>
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
        <div class="col-sm-12">
            <form class="AtividadeForm" style="display: block;" id="formulario" action="#" method="POST">
                <span class="title">Editar atividade</span>
                <a href="../function/apagaratividade.php?id=<?php echo $atividade_id; ?>" class="uil uil-trash-alt"></a>
                <div class="input-field tipo">
                    <select name="tipo_atividade" class="tipo_atividade" required>
                        <option class="select" disabled>Tipo de Atividade:</option>
                        <optgroup label="Caminhada e Corrida">
                            <option value="Caminhada" <?php echo ($atividade['tipo_atividade'] == 'Caminhada') ? 'selected' : ''; ?>>Caminhada</option>
                            <option value="Caminhada Leve" <?php echo ($atividade['tipo_atividade'] == 'Caminhada Leve') ? 'selected' : ''; ?>>Caminhada Leve</option>
                            <option value="Caminhada esportiva" <?php echo ($atividade['tipo_atividade'] == 'Caminhada esportiva') ? 'selected' : ''; ?>>Caminhada esportiva</option>
                            <option value="Caminhada vigorosa" <?php echo ($atividade['tipo_atividade'] == 'Caminhada vigorosa') ? 'selected' : ''; ?>>Caminhada vigorosa</option>
                            <option value="Corrida" <?php echo ($atividade['tipo_atividade'] == 'Corrida') ? 'selected' : ''; ?>>Corrida</option>
                            <option value="Marcha Atlética" <?php echo ($atividade['tipo_atividade'] == 'Marcha Atlética') ? 'selected' : ''; ?>>Marcha Atlética</option>
                            <option value="Trilha" <?php echo ($atividade['tipo_atividade'] == 'Trilha') ? 'selected' : ''; ?>>Trilha</option>
                        </optgroup>
                        <optgroup label="Ciclismo">
                            <option value="Ciclismo de pista" <?php echo ($atividade['tipo_atividade'] == 'Ciclismo de pista') ? 'selected' : ''; ?>>Ciclismo de pista</option>
                            <option value="Ciclismo de rua" <?php echo ($atividade['tipo_atividade'] == 'Ciclismo de rua') ? 'selected' : ''; ?>>Ciclismo de rua</option>
                            <option value="Mountain Bike" <?php echo ($atividade['tipo_atividade'] == 'Mountain Bike') ? 'selected' : ''; ?>>Mountain Bike</option>
                            <option value="Downhill" <?php echo ($atividade['tipo_atividade'] == 'Downhill') ? 'selected' : ''; ?>>Downhill</option>
                            <option value="Bicicross" <?php echo ($atividade['tipo_atividade'] == 'Bicicross') ? 'selected' : ''; ?>>Bicicross</option>
                            <option value="BMX" <?php echo ($atividade['tipo_atividade'] == 'BMX') ? 'selected' : ''; ?>>BMX</option>
                        </optgroup>
                        <optgroup label="Natação">
                            <option value="Natação Intensa" <?php echo ($atividade['tipo_atividade'] == 'Natação Intensa') ? 'selected' : ''; ?>>Natação Intensa</option>
                            <option value="Natação Recreativa" <?php echo ($atividade['tipo_atividade'] == 'Natação Recreativa') ? 'selected' : ''; ?>>Natação Recreativa</option>
                        </optgroup>
                        <optgroup label="Esportes de raquete">
                            <option value="Tênis" <?php echo ($atividade['tipo_atividade'] == 'Tênis') ? 'selected' : ''; ?>>Tênis</option>
                            <option value="Tênis de mesa" <?php echo ($atividade['tipo_atividade'] == 'Tênis de mesa') ? 'selected' : ''; ?>>Tênis de mesa</option>
                            <option value="Badminton" <?php echo ($atividade['tipo_atividade'] == 'Badminton') ? 'selected' : ''; ?>>Badminton</option>
                            <option value="Padel" <?php echo ($atividade['tipo_atividade'] == 'Padel') ? 'selected' : ''; ?>>Padel</option>
                            <option value="Squash" <?php echo ($atividade['tipo_atividade'] == 'Squash') ? 'selected' : ''; ?>>Squash</option>
                            <option value="Beach Tennis" <?php echo ($atividade['tipo_atividade'] == 'Beach Tennis') ? 'selected' : ''; ?>>Beach Tennis</option>
                            <option value="Raquetebol" <?php echo ($atividade['tipo_atividade'] == 'Raquetebol') ? 'selected' : ''; ?>>Raquetebol</option>
                            <option value="Pickleball" <?php echo ($atividade['tipo_atividade'] == 'Pickleball') ? 'selected' : ''; ?>>Pickleball</option>
                            <option value="Frescobol" <?php echo ($atividade['tipo_atividade'] == 'Frescobol') ? 'selected' : ''; ?>>Frescobol</option>
                            <option value="Gym Racket" <?php echo ($atividade['tipo_atividade'] == 'Gym Racket') ? 'selected' : ''; ?>>Gym Racket</option>
                        </optgroup>
                        <optgroup label="Outros">
                            <option value="Ioga" <?php echo ($atividade['tipo_atividade'] == 'Ioga') ? 'selected' : ''; ?>>Ioga</option>
                        </optgroup>
                    </select>

                    <i class="uil uil-grid icon"></i>
                </div>

                <div class="input-field">
                    <input type="date" id="data_atividade" name="data_atividade" value="<?php echo formatar_data_input($atividade['data_atividade']); ?>" required>
                    <i class="uil uil-calendar-alt icon"></i>
                </div>

                <div class="input-field">
                    <input type="text" id="hora_atividade" name="hora_atividade" placeholder="Hora da Atividade (opcional)" value="<?php echo htmlspecialchars($atividade['hora_atividade']); ?>">
                    <i class="uil uil-clock icon"></i>
                </div>

                <div class="input-field">
                    <input type="number" name="duracao" placeholder="Duração em Min (opcional):" value="<?php echo htmlspecialchars($atividade['duracao']); ?>">
                    <i class="uil uil-stopwatch icon"></i>
                </div>

                <div class="input-field">
                    <input type="number" step="0.01" placeholder="Distância em Km (opcional):" name="distancia" value="<?php echo htmlspecialchars($atividade['distancia']); ?>">
                    <i class="uil uil-ruler icon"></i>
                </div>

                <div class="checkbox-text">
                    <div class="checkbox-content">
                        <input type="checkbox" id="checkPeso" onclick="togglePesoInput()" <?php echo $atividade['calorias'] ? 'checked' : ''; ?>>
                        <label for="checkPeso" class="text">Mostrar gasto calórico aproximado</label>
                    </div>
                </div>

                <div class="input-field" id="pesoField" style="<?php echo $atividade['calorias'] ? '' : 'display: none;'; ?>">
                    <input type="text" id="Peso" name="Peso" placeholder="Insira seu peso" value="<?php echo htmlspecialchars($atividade['Peso'] ?? ''); ?>">
                    <i class="uil uil-weight icon"></i>
                </div>

                <div class="input-field button">
                    <button type="submit" class="submit">Salvar Alterações</button>
                </div>
            </form>

        </div>
        <footer class="textcenter footer">
            <p>Feito Por Bruno Evaristo Pinheiro - 2024</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="../assets/js/atividades.js"></script>
</body>

</html>