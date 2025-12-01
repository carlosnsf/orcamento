<?php
// Inclui a conexão com o banco
//require_once 'conexaoBancoFinanceiro.php';

// VERIFICAÇÃO DE PERMISSÃO (redundante, mas seguro)
if ($tipo_usuario != 'admin' && $tipo_usuario != 'master') {
    echo "<h1>Acesso Negado</h1>";
    exit;
}

// LÓGICA DE PROCESSAMENTO (POST)
// Detecta se um formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    
    // --- AÇÃO: SALVAR (Novo ou Edição) ---
    if ($_POST['acao'] == 'salvar') {
        $nome = $_POST['nome'];
        $descricao = $_POST['descricao'];
        $valor_mensal = $_POST['valor_mensal'];
        $valor_anual = $_POST['valor_anual'];
        $id = $_POST['id'] ?? null; // Pega o ID se estiver editando

        // Validação simples
        if (empty($nome) || empty($valor_mensal) || empty($valor_anual)) {
            $erro = "Nome, Valor Mensal e Valor Anual são obrigatórios.";
        } else {
            try {
                if ($id) {
                    // --- UPDATE (Atualizar) ---
                    $sql = "UPDATE centro_custo SET nome = ?, descricao = ?, valor_mensal = ?, valor_anual = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $descricao, $valor_mensal, $valor_anual, $id]);
                } else {
                    // --- INSERT (Novo) ---
                    $sql = "INSERT INTO centro_custo (nome, descricao, valor_mensal, valor_anual) VALUES (?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $descricao, $valor_mensal, $valor_anual]);
                }
                // Redireciona para a lista após salvar
                header("Location: PlenusFinanceiro.php?pagina=centro_custo&sucesso=1");
                exit;
            } catch (PDOException $e) {
                $erro = "Erro ao salvar no banco de dados: " . $e->getMessage();
            }
        }
    }
    
    // --- AÇÃO: EXCLUIR ---
    if ($_POST['acao'] == 'excluir') {
        $id = $_POST['id'];
        try {
            $sql = "DELETE FROM centro_custo WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            header("Location: PlenusFinanceiro.php?pagina=centro_custo&sucesso=2");
            exit;
        } catch (PDOException $e) {
            // Tratar erro de chave estrangeira (se um usuário estiver ligado a este centro)
            $erro = "Erro ao excluir. Verifique se este centro de custo não está em uso.";
        }
    }
}


// LÓGICA DE EXIBIÇÃO (GET)
// Pega a ação da URL, o padrão é 'listar'
$acao = $_GET['acao'] ?? 'listar';

// --- Título da Página ---
echo "<div class='content-header'><h1>Gestão de Centros de Custo</h1></div>";
echo "<div class=\"content-body\">";

// REMOVI AS MENSAGENS DE ERRO/SUCESSO AQUI - AGORA SERÃO EXIBIDAS VIA SWEETALERT NO SCRIPT ABAIXO

// --- Roteador de Exibição ---
switch ($acao):

    // --- CASO: 'novo' ou 'editar' ---
    case 'novo':
    case 'editar':
        
        $centro = null;
        if ($acao == 'editar') {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM centro_custo WHERE id = ?");
            $stmt->execute([$id]);
            $centro = $stmt->fetch();
        }
        
        // O formulário usa os dados de $centro se for edição, ou valores padrão se for novo
?>
        <h3><?php echo $acao == 'editar' ? 'Editar' : 'Novo'; ?> Centro de Custo</h3>
        
        <form action="PlenusFinanceiro.php?pagina=centro_custo" method="POST" id="formCentroCusto">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" value="<?php echo $centro['id'] ?? ''; ?>">
            
            <div class="form-group">
                <label for="nome">Nome *</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($centro['nome'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao"><?php echo htmlspecialchars($centro['descricao'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="valor_mensal">Valor Mensal *</label>
                <input type="number" id="valor_mensal" name="valor_mensal" step="0.01" value="<?php echo htmlspecialchars($centro['valor_mensal'] ?? '0.00'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="valor_anual">Valor Anual *</label>
                <input type="number" id="valor_anual" name="valor_anual" step="0.01" value="<?php echo htmlspecialchars($centro['valor_anual'] ?? '0.00'); ?>" required>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="PlenusFinanceiro.php?pagina=centro_custo" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
<?php
        break; // Fim do case 'novo'/'editar'

    // --- CASO: 'listar' (Padrão) ---
    default:
?>
        <a href="PlenusFinanceiro.php?pagina=centro_custo&acao=novo" class="btn btn-primary">Novo Centro de Custo</a>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Valor Mensal</th>
                    <th>Valor Anual</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Busca todos os centros de custo no banco
                $stmt = $pdo->query("SELECT id, nome, valor_mensal, valor_anual FROM centro_custo ORDER BY nome");
                while ($row = $stmt->fetch()) {
                    echo "<tr>";
                    echo "<td>{$row['id']}</td>";
                    echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
                    // Formata os valores como moeda
                    echo "<td>R$ " . number_format($row['valor_mensal'], 2, ',', '.') . "</td>";
                    echo "<td>R$ " . number_format($row['valor_anual'], 2, ',', '.') . "</td>";
                    echo "<td class='actions'>";
                    // Link de Edição
                    echo "<a href='PlenusFinanceiro.php?pagina=centro_custo&acao=editar&id={$row['id']}' class='btn-edit'>Editar</a>";
                    // Formulário de Exclusão (para segurança)
                    echo "<form onsubmit='return confirmarExclusao(this);' method='POST' style='display:inline;'>
                            <input type='hidden' name='acao' value='excluir'>
                            <input type='hidden' name='id' value='{$row['id']}'>
                            <button type='submit' class='btn-delete' style='padding: 4px 8px; font-size: 0.9em; cursor:pointer;'>Excluir</button>
                          </form>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
<?php
        break; // Fim do case 'listar'

endswitch; // Fim do switch($acao)

echo "</div>"; // Fim do .content-body

// SCRIPT PARA SWEETALERT - Deve vir depois do conteúdo
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Função para exibir mensagens de sucesso/erro via SweetAlert
document.addEventListener('DOMContentLoaded', function() {
    // Verifica se há mensagens de erro do PHP
    <?php if (isset($erro)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Erro!',
        text: '<?php echo addslashes($erro); ?>',
        confirmButtonColor: '#d33'
    });
    <?php endif; ?>

    // Verifica se há mensagens de sucesso na URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('sucesso')) {
        const sucesso = urlParams.get('sucesso');
        
        if (sucesso === '1') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Centro de Custo salvo com sucesso!',
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                // Remove o parâmetro sucesso da URL para evitar exibição novamente
                const newUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, newUrl);
            });
        } else if (sucesso === '2') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Centro de Custo excluído com sucesso!',
                showConfirmButton: false,
                timer: 2000
            }).then(() => {
                // Remove o parâmetro sucesso da URL
                const newUrl = window.location.href.split('?')[0];
                window.history.replaceState({}, document.title, newUrl);
            });
        }
    }
});

// Função para confirmar exclusão com SweetAlert
function confirmarExclusao(form) {
    Swal.fire({
        title: 'Tem certeza?',
        text: "Você está prestes a excluir este Centro de Custo. Esta ação não pode ser desfeita!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Se confirmado, envia o formulário
            form.submit();
        }
    });
    
    // Retorna false para prevenir o envio padrão do formulário
    return false;
}

// Validação do formulário antes de enviar (opcional)
document.getElementById('formCentroCusto')?.addEventListener('submit', function(e) {
    const nome = document.getElementById('nome').value.trim();
    const valorMensal = document.getElementById('valor_mensal').value;
    const valorAnual = document.getElementById('valor_anual').value;
    
    if (!nome || !valorMensal || !valorAnual) {
        e.preventDefault(); // Impede o envio
        Swal.fire({
            icon: 'warning',
            title: 'Campos obrigatórios',
            text: 'Por favor, preencha todos os campos obrigatórios (*).',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    // Converte para número para validação
    const valorMensalNum = parseFloat(valorMensal);
    const valorAnualNum = parseFloat(valorAnual);
    
    if (valorMensalNum < 0 || valorAnualNum < 0) {
        e.preventDefault(); // Impede o envio
        Swal.fire({
            icon: 'warning',
            title: 'Valores inválidos',
            text: 'Os valores não podem ser negativos.',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
});
</script>