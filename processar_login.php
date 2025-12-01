<?php
// 1. Iniciar a sessão
// DEVE ser a primeira coisa no arquivo.
// Isso permite que o PHP lembre do usuário entre as páginas.
session_start();

// 2. Incluir o arquivo de conexão
// Agora temos acesso à variável $pdo
require_once 'conexaoBancoFinanceiro.php';

// 3. Verificar se o formulário foi enviado (método POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 4. Obter os dados do formulário com segurança
    $login = $_POST['login'];
    $senha_digitada = $_POST['senha'];

    // 5. Validação básica (só para garantir)
    if (empty($login) || empty($senha_digitada)) {
        // Redireciona de volta para o login com uma mensagem de erro
        header("Location: login.php?erro=campos_vazios");
        exit;
    }

    try {
        // 6. Preparar a consulta SQL
        // Usamos um 'placeholder' (?) para evitar Injeção de SQL.
        // !!! ATENÇÃO: Troque 'usuarios' pelo nome real da sua tabela !!!
        $sql = "SELECT * FROM usuarios WHERE login = ?";
        $stmt = $pdo->prepare($sql);
        
        // 7. Executar a consulta, passando o login do usuário
        $stmt->execute([$login]);
        
        // 8. Buscar o usuário no banco
        $usuario = $stmt->fetch();

        // 9. VERIFICAR O USUÁRIO E A SENHA
        // $usuario -> Verifica se o usuário foi encontrado
        // password_verify() -> Compara a senha digitada com o HASH salvo no banco
        if ($usuario && password_verify($senha_digitada, $usuario['senha'])) {
            
            // 10. VERIFICAR O STATUS (baseado na sua imagem)
            if ($usuario['status'] == 'ativo') {
                
                // 11. SUCESSO! Login válido e usuário ativo.
                // Armazenamos os dados do usuário na SESSÃO
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_tipo'] = $usuario['tipo']; // Ex: 'admin', 'operador'
                $_SESSION['usuario_centro_custo_id'] = $usuario['centro_custo_id']; // <-- ADICIONE ESTA LINHA
                $_SESSION['logado'] = true;

                // 12. Redirecionar para a página principal
                header("Location: PlenusFinanceiro.php");
                exit; // Encerra o script

            } else {
                // Usuário existe, mas está inativo
                header("Location: login.php?erro=usuario_inativo");
                exit;
            }

        } else {
            // Usuário não encontrado ou senha incorreta
            // Usamos uma mensagem genérica por segurança
            header("Location: login.php?erro=login_invalido");
            exit;
        }

    } catch (PDOException $e) {
        // Captura erros de banco de dados
        // Em um sistema real, você deve 'logar' esse erro, não mostrá-lo.
        header("Location: login.php?erro=db_error");
        exit;
    }

} else {
    // Se alguém tentar acessar este arquivo diretamente pela URL
    // Redireciona de volta para o login
    header("Location: login.php");
    exit;
}
?>