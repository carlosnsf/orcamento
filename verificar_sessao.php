<?php
// 1. Inicia a sessão (DEVE ser a primeira coisa)
session_start();

// 2. Verifica se as variáveis de sessão existem
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, destrói qualquer sessão e redireciona para o login
    session_unset();
    session_destroy();
    header("Location: Login.php?erro=nao_logado"); // Adicionamos um erro 'nao_logado'
    exit;
}

// 3. Pega o tipo de usuário para facilitar o uso nas páginas
// (Já foi definido em 'processar_login.php')
$tipo_usuario = $_SESSION['usuario_tipo']; // Ex: 'master', 'admin', 'operador'

?>