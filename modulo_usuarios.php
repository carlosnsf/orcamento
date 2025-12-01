<?php
// Permissões já verificadas pelo PlenusFinanceiro.php
// $pdo e $id_usuario_logado já estão disponíveis

// LÓGICA DE PROCESSAMENTO (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    
    // --- AÇÃO: SALVAR (Novo ou Edição) ---
    if ($_POST['acao'] == 'salvar') {
        $id = $_POST['id'] ?? null;
        $nome = $_POST['nome'];
        $login = $_POST['login'];
        $tipo = $_POST['tipo'];
        $status = $_POST['status'];
        // Se tipo for 'operador', usa o ID, senão, null
        $centro_custo_id = ($tipo == 'operador' && !empty($_POST['centro_custo_id'])) ? $_POST['centro_custo_id'] : null;
        $senha = $_POST['senha'];

        try {
            if ($id) {
                // --- UPDATE (Atualizar) ---
                if (empty($senha)) {
                    // Atualiza SEM a senha
                    $sql = "UPDATE usuarios SET nome = ?, login = ?, tipo = ?, status = ?, centro_custo_id = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $login, $tipo, $status, $centro_custo_id, $id]);
                } else {
                    // Atualiza COM a senha
                    $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                    $sql = "UPDATE usuarios SET nome = ?, login = ?, tipo = ?, status = ?, centro_custo_id = ?, senha = ? WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $login, $tipo, $status, $centro_custo_id, $hash_senha, $id]);
                }
                $sucesso_msg = "Usuário atualizado com sucesso!";
            } else {
                // --- INSERT (Novo) ---
                if (empty($senha)) {
                    $erro = "A senha é obrigatória para criar um novo usuário.";
                } else {
                    $hash_senha = password_hash($senha, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO usuarios (nome, login, senha, tipo, status, centro_custo_id) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$nome, $login, $hash_senha, $tipo, $status, $centro_custo_id]);
                    $sucesso_msg = "Usuário criado com sucesso!";
                }
            }
            if (!isset($erro) && isset($sucesso_msg)) {
                // Redireciona para a lista
                header("Location: PlenusFinanceiro.php?pagina=usuarios&sucesso=" . urlencode($sucesso_msg));
                exit;
            }

        } catch (PDOException $e) {
            // Erro de login duplicado
            if ($e->errorInfo[1] == 1062) {
                $erro = "Erro: O login '$login' já está em uso.";
            } else {
                $erro = "Erro ao salvar: " . $e->getMessage();
            }
        }
    }
    
    // --- AÇÃO: MUDAR STATUS (Ativar/Desativar) ---
    if ($_POST['acao'] == 'mudar_status') {
        $id = $_POST['id'];
        $status_atual = $_POST['status_atual'];
        // Não pode desativar o próprio usuário
        if ($id == $id_usuario_logado) {
            $erro = "Você não pode desativar seu próprio usuário.";
        } else {
            $novo_status = ($status_atual == 'ativo') ? 'inativo' : 'ativo';
            $sql = "UPDATE usuarios SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$novo_status, $id]);
            header("Location: PlenusFinanceiro.php?pagina=usuarios&sucesso=Status alterado com sucesso!");
            exit;
        }
    }

    // --- AÇÃO: EXCLUIR ---
    if ($_POST['acao'] == 'excluir') {
        $id = $_POST['id'];
        // Não pode excluir o próprio usuário
        if ($id == $id_usuario_logado) {
            $erro = "Você não pode excluir seu próprio usuário.";
        } else {
            try {
                $sql = "DELETE FROM usuarios WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id]);
                header("Location: PlenusFinanceiro.php?pagina=usuarios&sucesso=Usuário excluído com sucesso!");
                exit;
            } catch (PDOException $e) {
                $erro = "Erro ao excluir. Verifique se o usuário não possui lançamentos associados.";
            }
        }
    }
}


// LÓGICA DE EXIBIÇÃO (GET)
$acao = $_GET['acao'] ?? 'listar';

// --- Título da Página ---
echo "<div class='content-header'><h1>Gestão de Usuários</h1></div>";
echo '<div class="content-body">';

// REMOVIDAS AS MENSAGENS DE ERRO/SUCESSO EM HTML - AGORA SERÃO EXIBIDAS VIA SWEETALERT

// --- Roteador de Exibição ---
switch ($acao):

    // --- CASO: 'novo' ou 'editar' ---
    case 'novo':
    case 'editar':
        
        $usuario = null;
        if ($acao == 'editar') {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch();
        }
        
        // Busca centros de custo para o dropdown
        $stmt_cc = $pdo->query("SELECT id, nome FROM centro_custo ORDER BY nome");
        $centros_custo = $stmt_cc->fetchAll();
        
?>
        <h3><?php echo $acao == 'editar' ? 'Editar' : 'Novo'; ?> Usuário</h3>
        
        <form id="form-usuario" action="PlenusFinanceiro.php?pagina=usuarios" method="POST" onsubmit="return validarFormUsuario(this, '<?php echo $acao; ?>')">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" value="<?php echo $usuario['id'] ?? ''; ?>">
            
            <div class="form-group">
                <label for="nome">Nome *</label>
                <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="login">Login *</label>
                <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($usuario['login'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha *</label>
                <input type="password" id="senha" name="senha" <?php echo ($acao == 'novo') ? 'required' : ''; ?>>
                <?php if ($acao == 'editar'): ?>
                    <small>Deixe em branco para não alterar a senha.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="tipo">Tipo de Usuário *</label>
                <select id="tipo" name="tipo" required>
                    <?php 
                    $tipos = ['master', 'admin', 'operador'];
                    $tipo_atual = $usuario['tipo'] ?? 'operador';
                    foreach ($tipos as $t) {
                        $selected = ($t == $tipo_atual) ? 'selected' : '';
                        echo "<option value='$t' $selected>" . ucfirst($t) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status *</label>
                <select id="status" name="status" required>
                    <option value="ativo" <?php echo (isset($usuario['status']) && $usuario['status'] == 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                    <option value="inativo" <?php echo (isset($usuario['status']) && $usuario['status'] == 'inativo') ? 'selected' : ''; ?>>Inativo</option>
                </select>
            </div>

            <div class="form-group" id="campo-centro-custo" style="display: none;">
                <label for="centro_custo_id">Centro de Custo (para Operador)</label>
                <select id="centro_custo_id" name="centro_custo_id">
                    <option value="">Nenhum</option>
                    <?php 
                    $cc_atual = $usuario['centro_custo_id'] ?? null;
                    foreach ($centros_custo as $cc) {
                        $selected = ($cc['id'] == $cc_atual) ? 'selected' : '';
                        echo "<option value='{$cc['id']}' $selected>" . htmlspecialchars($cc['nome']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="PlenusFinanceiro.php?pagina=usuarios" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
<?php
        break; // Fim do case 'novo'/'editar'
    
    // --- CASO: 'logs_acesso' ---
    case 'logs_acesso':
        echo "<h3>Logs de Acesso</h3>";
        echo "<p>Este módulo registrará as datas e horários de login de cada usuário.</p>";
        echo "";
        echo "<br><a href='PlenusFinanceiro.php?pagina=usuarios' class='btn btn-secondary'>Voltar</a>";
        break;

    // --- CASO: 'listar' (Padrão) ---
    default:
?>
        <a href="PlenusFinanceiro.php?pagina=usuarios&acao=novo" class="btn btn-primary" style="margin-right: 10px;">Novo Usuário</a>
        <a href="PlenusFinanceiro.php?pagina=usuarios&acao=logs_acesso" class="btn btn-secondary">Logs de Acesso</a>
        
        <table style="font-size: 0.9em; margin-top: 1.5rem;">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Login</th>
                    <th>Tipo</th>
                    <th>Centro de Custo</th>
                    <th>Status</th>
                    <th style="width: 250px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Busca todos os usuários e o nome do centro de custo (se houver)
                $sql = "SELECT u.*, cc.nome AS nome_centro_custo 
                        FROM usuarios u 
                        LEFT JOIN centro_custo cc ON u.centro_custo_id = cc.id 
                        ORDER BY u.nome";
                $stmt = $pdo->query($sql);

                while ($row = $stmt->fetch()) {
                    $e_o_usuario_logado = ($row['id'] == $id_usuario_logado);
                    
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['nome']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['login']) . "</td>";
                    echo "<td>" . ucfirst($row['tipo']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['nome_centro_custo'] ?? 'N/A') . "</td>";
                    // Status formatado
                    $cor_status = $row['status'] == 'ativo' ? 'green' : 'red';
                    echo "<td><strong style='color:$cor_status;'>" . ucfirst($row['status']) . "</strong></td>";
                    
                    echo "<td class='actions'>";
                    
                    // 1. Botão EDITAR
                    echo "<a href='PlenusFinanceiro.php?pagina=usuarios&acao=editar&id={$row['id']}' class='btn-edit' style='background-color:#007bff; color:white;'>Editar</a>";
                    
                    // 2. Botão ATIVAR/DESATIVAR
                    if (!$e_o_usuario_logado) { // Só pode alterar status de OUTROS
                        $btn_status_texto = $row['status'] == 'ativo' ? 'Desativar' : 'Ativar';
                        $btn_status_cor = $row['status'] == 'ativo' ? '#f0ad4e' : '#5cb85c';
                        echo "<form method='POST' style='display:inline; margin: 0 5px;' onsubmit='return confirmarMudancaStatus(this, \"{$row['nome']}\", \"{$btn_status_texto}\")'>
                                <input type='hidden' name='acao' value='mudar_status'>
                                <input type='hidden' name='id' value='{$row['id']}'>
                                <input type='hidden' name='status_atual' value='{$row['status']}'>
                                <button type='submit' style='background-color:$btn_status_cor; color:white; border:none; padding: 4px 8px; font-size: 0.9em; cursor:pointer; border-radius: 4px;'>
                                    $btn_status_texto
                                </button>
                              </form>";
                    }
                    
                    // 3. Botão EXCLUIR
                    if (!$e_o_usuario_logado) { // Só pode excluir OUTROS
                        echo "<form method='POST' style='display:inline;' onsubmit='return confirmarExclusao(this, \"{$row['nome']}\")'>
                                <input type='hidden' name='acao' value='excluir'>
                                <input type='hidden' name='id' value='{$row['id']}'>
                                <button type='submit' class='btn-delete'>Excluir</button>
                              </form>";
                    }
                    
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
        const sucessoMsg = urlParams.get('sucesso');
        
        Swal.fire({
            icon: 'success',
            title: 'Sucesso!',
            text: decodeURIComponent(sucessoMsg),
            showConfirmButton: false,
            timer: 2000
        }).then(() => {
            // Remove o parâmetro sucesso da URL para evitar exibição novamente
            const newUrl = window.location.href.split('?')[0];
            window.history.replaceState({}, document.title, newUrl);
        });
    }

    // Script para mostrar/ocultar centro de custo baseado no tipo de usuário
    var tipoSelect = document.getElementById('tipo');
    var ccCampo = document.getElementById('campo-centro-custo');

    if (tipoSelect && ccCampo) {
        function toggleCentroCusto() {
            if (tipoSelect.value === 'operador') {
                ccCampo.style.display = 'block';
            } else {
                ccCampo.style.display = 'none';
                document.getElementById('centro_custo_id').value = '';
            }
        }
        // Verifica ao carregar a página (para edições)
        toggleCentroCusto();
        // Verifica ao mudar o select
        tipoSelect.addEventListener('change', toggleCentroCusto);
    }
});

// Função para confirmar exclusão com SweetAlert
function confirmarExclusao(form, nomeUsuario) {
    Swal.fire({
        title: 'Excluir Usuário',
        html: `<strong>${nomeUsuario}</strong><br><br>Tem certeza que deseja excluir este usuário?<br>Esta ação não pode ser desfeita!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, excluir!',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Se confirmado, envia o formulário
            form.submit();
        }
    });
    
    // Retorna false para prevenir o envio padrão do formulário
    return false;
}

// Função para confirmar mudança de status
function confirmarMudancaStatus(form, nomeUsuario, acao) {
    Swal.fire({
        title: 'Alterar Status',
        html: `<strong>${nomeUsuario}</strong><br><br>Tem certeza que deseja <strong>${acao.toLowerCase()}</strong> este usuário?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: `Sim, ${acao}!`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    
    return false;
}

// Função para validar o formulário de usuário
function validarFormUsuario(form, acao) {
    const nome = document.getElementById('nome').value.trim();
    const login = document.getElementById('login').value.trim();
    const senha = document.getElementById('senha').value;
    const tipo = document.getElementById('tipo').value;
    
    // Validações básicas
    if (!nome || !login || !tipo) {
        Swal.fire({
            icon: 'warning',
            title: 'Campos obrigatórios',
            text: 'Por favor, preencha todos os campos obrigatórios (*).',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    // Validação especial para novo usuário (senha obrigatória)
    if (acao === 'novo' && !senha) {
        Swal.fire({
            icon: 'warning',
            title: 'Senha obrigatória',
            text: 'A senha é obrigatória para criar um novo usuário.',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    // Validação de senha (se preenchida)
    if (senha && senha.length < 6) {
        Swal.fire({
            icon: 'warning',
            title: 'Senha muito curta',
            text: 'A senha deve ter pelo menos 6 caracteres.',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    // Validação especial para operador
    if (tipo === 'operador') {
        const centroCustoId = document.getElementById('centro_custo_id').value;
        if (!centroCustoId) {
            Swal.fire({
                icon: 'question',
                title: 'Centro de Custo',
                text: 'Este usuário é do tipo Operador mas não tem um Centro de Custo associado. Deseja continuar mesmo assim?',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
            return false;
        }
    }
    
    // Se todas as validações passaram
    return true;
}
</script>