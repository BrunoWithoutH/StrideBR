<?php
session_start();
include_once("../function/config.php");

// Verifica se o usuário está logado
if (!isset($_SESSION['UEmail'])) {
    header('Location: ../login.php');
    exit;
}

$email = $_SESSION['UEmail'];
$sql = "SELECT ID FROM usuarios WHERE Email = '$email'";
$result = $conexao->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $usuario_id = $row['ID'];
} else {
    echo "Erro: Usuário não encontrado.";
    exit;
}

// Verifica se um ID foi passado na URL
if (!isset($_GET['id'])) {
    echo "Erro: ID de atividade não especificado.";
    exit;
}

$atividade_id = $_GET['id'];

// Busca os dados da atividade no banco de dados
$sql = "SELECT * FROM atividades_fisicas WHERE id = '$atividade_id' AND usuario_id = '$usuario_id'";
$result = $conexao->query($sql);

if ($result->num_rows > 0) {
    $atividade = $result->fetch_assoc();
} else {
    echo "Erro: Atividade não encontrada.";
    exit;
}

// Função para formatar data para o input
function formatar_data_input($data)
{
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    return $data_obj ? $data_obj->format('Y-m-d') : $data;
}

// Processa o envio do formulário de edição
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_atividade = $_POST['tipo_atividade'];
    $data_atividade = $_POST['data_atividade'];
    $hora_atividade = $_POST['hora_atividade'];
    $duracao = !empty($_POST['duracao']) ? $_POST['duracao'] : NULL;
    $distancia = !empty($_POST['distancia']) ? $_POST['distancia'] : NULL;
    $peso = isset($_POST['Peso']) ? $_POST['Peso'] : NULL;

    // Atualiza a atividade no banco de dados
    $sql = "UPDATE atividades_fisicas SET 
                        tipo_atividade = '$tipo_atividade', 
                        data_atividade = '$data_atividade', 
                        hora_atividade = '$hora_atividade', 
                        duracao = '$duracao', 
                        distancia = '$distancia', 
                        calorias = '$calorias' 
                    WHERE id = '$atividade_id' AND usuario_id = '$usuario_id'";

    if ($conexao->query($sql) === TRUE) {
        header("Location: atividades.php");
        exit;
    } else {
        echo "Erro ao atualizar a atividade: " . $conexao->error;
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