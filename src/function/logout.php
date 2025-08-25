<?php
    session_start();
    unset($_SESSION['EmailUsuario']);
    unset($_SESSION['SenhaUsuario']);
    header("Location: ../../public/index.php");
?>