<?php

// --- Configurações do Banco de Dados ---
// Altere para os dados do seu ambiente
$db_host = 'localhost';      // Onde o MySQL está rodando (normalmente localhost)
$db_name = 'plenus_financeiro'; // O nome do banco de dados
$db_user = 'root';           // Seu usuário do MySQL
$db_pass = '';      // Sua senha do MySQL
$db_charset = 'utf8mb4';     // Charset recomendado
// ----------------------------------------

// DSN (Data Source Name) - Define a string de conexão
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";

// Opções do PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna resultados como arrays associativos
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Desabilita emulação (para segurança)
];

try {
     // Cria a instância da conexão PDO
     $pdo = new PDO($dsn, $db_user, $db_pass, $options);
     
     // Você não precisa de uma mensagem de "conectado" aqui.
     // Se o script chegar ao fim sem erros, a conexão está pronta.
     
} catch (\PDOException $e) {
     // Em caso de erro na conexão, exibe uma mensagem e encerra o script
     // Em produção, você não deve exibir detalhes do erro,
     // mas sim logar o erro em um arquivo.
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// A variável $pdo está pronta para ser usada em outros arquivos
// que incluírem este (ex: require_once 'conexao.php';)

?>