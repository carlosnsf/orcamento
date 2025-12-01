<?php
// 1. INCLUI O VERIFICADOR DE SESSÃO
// Se o usuário não estiver logado, ele já será redirecionado daqui
require_once 'verificar_sessao.php';

// 2. INCLUI A CONEXÃO COM O BANCO
// Isso torna a variável $pdo disponível para todos os módulos
require_once 'conexaoBancoFinanceiro.php';

// 3. === DEFINIÇÕES GLOBAIS DE SESSÃO === (ADICIONE ESTE BLOCO)
// Define as variáveis de sessão que todos os módulos podem precisar
$id_usuario_logado = $_SESSION['usuario_id'];
$tipo_usuario_logado = $_SESSION['usuario_tipo'];
$id_centro_custo_logado = $_SESSION['usuario_centro_custo_id'] ?? null;

// 4. Mini-roteador: decide qual página carregar no conteúdo principal
// Se 'pagina' não for definido na URL, o padrão é 'inicio'
$pagina = $_GET['pagina'] ?? 'inicio';

// Usamos isso para destacar o link ativo no menu
function is_active($link_pagina, $pagina_atual)
{
    return $link_pagina == $pagina_atual ? 'class="active"' : '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PlenusFinanceiro - Dashboard</title>
    <link rel="stylesheet" href="style-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<aside class="sidebar">
        <div class="sidebar-header">
            
            <a href="PlenusFinanceiro.php" class="sidebar-title-link">
                <h2>PlenusFinanceiro</h2>
            </a>
            
            <p>Bem-vindo(a), <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></p>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="?pagina=lancamentos" <?php echo is_active('lancamentos', $pagina); ?>>
                    Lançamentos
                </a>
            </li>

            <?php if ($tipo_usuario == 'admin' || $tipo_usuario == 'master'): ?>
                <li>
                    <a href="?pagina=centro_custo" <?php echo is_active('centro_custo', $pagina); ?>>
                        Centro de Custo
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($tipo_usuario == 'master'): ?>
                <li>
                    <a href="?pagina=usuarios" <?php echo is_active('usuarios', $pagina); ?>>
                        Usuários
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($tipo_usuario == 'admin' || $tipo_usuario == 'master'): ?>
                <li>
                    <a href="?pagina=analise" <?php echo is_active('analise', $pagina); ?>>
                        Análises
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($tipo_usuario == 'master'): ?>
                <li>
                    <a href="?pagina=logs" <?php echo is_active('logs', $pagina); ?>>
                        Logs do Sistema
                    </a>
                </li>
            <?php endif; ?>

            <li class="logout">
                <a href="logout.php">Sair do Sistema</a>
            </li>
        </ul>
    </aside>

    <main class="main-content">

        <?php
        switch ($pagina):

            // --- PÁGINA INICIAL (DASHBOARD) ---
            case 'inicio':
                include 'modulo_dashboard.php';
                break;

            // --- PÁGINA DE LANÇAMENTOS ---
            case 'lancamentos':
                // Carrega o módulo de lançamentos
                include 'modulo_lancamentos.php';
                break;

            // --- PÁGINA DE CENTRO DE CUSTO ---
            case 'centro_custo':
                // A verificação de permissão é a primeira barreira
                if ($tipo_usuario == 'admin' || $tipo_usuario == 'master'):

                    // Em vez de 'echo', vamos 'incluir' o arquivo do módulo
                    include 'modulo_centro_custo.php';

                else:
                    // Se não tiver permissão, exibe acesso negado
                    echo "<div class'content-header'><h1>Acesso Negado</h1></div>";
                    echo "<div class='content-body'><p>Você não tem permissão para acessar este módulo.</p></div>";
                endif;
                break;

            // --- ✨ NOVA ROTA (ANÁLISE) ✨ ---
            case 'analise':
                // Dupla verificação de segurança
                if ($tipo_usuario == 'admin' || $tipo_usuario == 'master'):
                    include 'modulo_analise.php';
                else:
                    echo "<div class='content-header'><h1>Acesso Negado</h1></div>";
                endif;
                break;

                            // --- ✨ NOVA ROTA (ANÁLISE) ✨ ---
            case 'detalhamento':
                // Dupla verificação de segurança
                if ($tipo_usuario == 'admin' || $tipo_usuario == 'master'):
                    include 'modulo_detalhamento.php';
                else:
                    echo "<div class='content-header'><h1>Acesso Negado</h1></div>";
                endif;
                break;

            // --- PÁGINA DE USUÁRIOS ---
            case 'usuarios':
                // Verificação de permissão estrita
                if ($tipo_usuario == 'master'):

                    // Carrega o módulo de usuários
                    include 'modulo_usuarios.php';

                else:
                    // Se não for 'master', exibe acesso negado
                    echo "<div class='content-header'><h1>Acesso Negado</h1></div>";
                    echo "<div class='content-body'><p>Você não tem permissão para acessar este módulo.</p></div>";
                endif;
                break;

            // --- PÁGINA DE LOGS DO SISTEMA ---
            case 'logs':
                if ($tipo_usuario == 'master'):
                    echo "<div class='content-header'><h1>Logs do Sistema</h1></div>";
                    echo "<div class='content-body'>";
                    echo "<p>Aqui será exibido o log completo de alterações (Antes e Depois).</p>";
                    echo "";
                    echo "</div>";
                else:
                    echo "<h1>Acesso Negado</h1>";
                endif;
                break;

            // --- PÁGINA PADRÃO (ERRO 404) ---
            default:
                echo "<div class='content-header'><h1>Página Não Encontrada</h1></div>";
                echo "<div class='content-body'><p>A página que você tentou acessar não existe.</p></div>";
                break;
        endswitch;
        ?>
    </main>

    <script>
    /**
     * Formata um campo de input para o padrão de moeda BRL (R$)
     * A lógica é "digitar centavos": 12345 -> R$ 123,45
     */
    function formatarMoeda(input) {
        // 1. Pega o valor e limpa tudo que não for dígito
        let valor = input.value.replace(/\D/g, '');

        // 2. Converte para número, dividindo por 100 para ter os centavos
        // (Ex: "12345" -> 123.45)
        let valorNumerico = parseFloat(valor) / 100;

        // 3. Se não for um número válido (ex: campo vazio), trata como 0.00
        if (isNaN(valorNumerico)) {
            valorNumerico = 0.00;
        }

        // 4. Formata o número para o padrão de moeda BRL
        const formatador = new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 2
        });

        // 5. Atualiza o valor no campo
        input.value = formatador.format(valorNumerico);
    }

    /**
     * "Limpa" os campos de moeda antes de enviar o formulário
     * (Ex: "R$ 1.234,56" -> "1234.56")
     */
    function desformatarMoeda(form) {
        // Pega todos os campos de moeda dentro do formulário
        const camposMoeda = form.querySelectorAll('input[name="valor_mensal"], input[name="valor_anual"]');
        
        camposMoeda.forEach(input => {
            let valor = input.value;
            
            // 1. Remove o "R$" e espaços
            valor = valor.replace('R$', '').trim();
            // 2. Remove os pontos de milhar
            valor = valor.replace(/\./g, '');
            // 3. Substitui a vírgula do centavo por ponto
            valor = valor.replace(',', '.');

            // 4. Atualiza o valor do campo para o número limpo
            input.value = valor;
        });

        // 5. Permite que o formulário seja enviado
        return true; 
    }
    </script>

    

</body>

</html>