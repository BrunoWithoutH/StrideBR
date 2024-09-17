<?php
    session_start();
    unset($_SESSION['UEmail']);
    unset($_SESSION['USenha']);
    header("Location: ../index.php");
?>