<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once dirname(__DIR__, 2) . '/src/config/pg_config.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
use Hidehalo\Nanoid\Client;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../user/cronogramatreinos.php");
    exit;
}

$idusuario = $_SESSION['IdUsuario'] ?? ($_POST['idusuario'] ?? null);
if (!$idusuario) {
    header("Location: ../user/cronogramatreinos.php?error=no_user");
    exit;
}

$dias = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
$turnos = ['Manhã','Tarde','Noite'];
$client = new Client();

try {
    $pdo->beginTransaction();

    $selectStmt = $pdo->prepare("SELECT idcronograma FROM cronogramas WHERE idusuario = :idusuario AND diasemanacronograma = :dia AND turnocronograma = :turno LIMIT 1");
    $insertStmt = $pdo->prepare("INSERT INTO cronogramas (idcronograma, idusuario, diasemanacronograma, turnocronograma, titulotreinocronograma) VALUES (:idcronograma, :idusuario, :dia, :turno, :titulo)");
    $updateStmt = $pdo->prepare("UPDATE cronogramas SET titulotreinocronograma = :titulo, datahoraregistrocronograma = CURRENT_TIMESTAMP WHERE idcronograma = :idcronograma");
    $deleteStmt = $pdo->prepare("DELETE FROM cronogramas WHERE idcronograma = :idcronograma");

    foreach ($turnos as $turno) {
        foreach ($dias as $dia) {
            $field = $dia . '_' . $turno;
            $titulo = isset($_POST[$field]) ? trim($_POST[$field]) : '';

            $selectStmt->execute([
                ':idusuario' => $idusuario,
                ':dia' => $dia,
                ':turno' => $turno
            ]);
            $existingId = $selectStmt->fetchColumn();

            if ($titulo !== '') {
                if ($existingId) {
                    $updateStmt->execute([
                        ':titulo' => $titulo,
                        ':idcronograma' => $existingId
                    ]);
                } else {
                    $idcronograma = $client->generateId(12);
                    $insertStmt->execute([
                        ':idcronograma' => $idcronograma,
                        ':idusuario' => $idusuario,
                        ':dia' => $dia,
                        ':turno' => $turno,
                        ':titulo' => $titulo
                    ]);
                }
            } else {
                if ($existingId) {
                    $deleteStmt->execute([':idcronograma' => $existingId]);
                }
            }
        }
    }

    $pdo->commit();
    header("Location: ../user/cronogramatreinos.php");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    header("Location: ../user/cronogramatreinos.php?error=save_failed");
    exit;
}
