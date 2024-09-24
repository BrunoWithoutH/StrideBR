<?php
    session_start();
    unset($_SESSION['EmailUsuario']);
    unset($_SESSION['SenhaUsuario']);
    header("Location: ../index.php");
?>