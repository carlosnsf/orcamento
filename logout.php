<?php
session_start();      // Inicia a sessão
session_unset();    // Limpa todas as variáveis da sessão
session_destroy();  // Destrói a sessão
header("Location: Login.php"); // Redireciona para o login
exit;
?>