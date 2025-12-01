<?php
// Não precisa de 'conexao.php' ou 'verificar_sessao.php', 
// pois eles já foram carregados pelo 'PlenusFinanceiro.php'.

// Variáveis de sessão para facilitar
// $id_usuario_logado = $_SESSION['usuario_id'];
// $tipo_usuario_logado = $_SESSION['usuario_tipo'];
// $id_centro_custo_logado = $_SESSION['usuario_centro_custo_id']; // <-- Pego da sessão

// LÓGICA DE PROCESSAMENTO (POST)
// Detecta se um formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {

    // --- AÇÃO: SALVAR (Novo ou Edição) ---
    if ($_POST['acao'] == 'salvar') {
        // Coleta de dados do formulário
        $id = $_POST['id'] ?? null;
        $lancamento = $_POST['lancamento'];
        $descricao = $_POST['descricao'];
        $id_centro_custo = $_POST['id_centro_custo'];
        
        // Remove formatação dos valores antes de salvar
        $valor_orcado = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_orcado']);
        $valor_realizado = !empty($_POST['valor_realizado']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_realizado']) : 0.00;
        $valor_emergencia = !empty($_POST['valor_emergencia']) ? str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_emergencia']) : 0.00;
        
        $emergencia = $_POST['emergencia'];
        $programado = $_POST['programado'];
        $parcelas = $_POST['parcelas'] ?: 1; // Padrão 1 se vazio
        $situacao = $_POST['situacao'];
        // Trata data vazia
        $data_pagamento = !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null;

        try {
            if ($id) {
                // --- UPDATE (Atualizar) ---
                $sql = "UPDATE lancamentos SET 
                            lancamento = ?, descricao = ?, id_centro_custo = ?, valor_orcado = ?, 
                            valor_realizado = ?, emergencia = ?, valor_emergencia = ?, programado = ?, 
                            parcelas = ?, situacao = ?, data_pagamento = ?, id_usuario_ultima_alteracao = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $lancamento,
                    $descricao,
                    $id_centro_custo,
                    $valor_orcado,
                    $valor_realizado,
                    $emergencia,
                    $valor_emergencia,
                    $programado,
                    $parcelas,
                    $situacao,
                    $data_pagamento,
                    $id_usuario_logado,
                    $id
                ]);
                $sucesso_msg = "Lançamento atualizado com sucesso!";
            } else {
                // --- INSERT (Novo) ---
                // 'diferenca' é gerada, não entra no INSERT
                $sql = "INSERT INTO lancamentos 
                            (lancamento, descricao, id_centro_custo, valor_orcado, valor_realizado, 
                             emergencia, valor_emergencia, programado, parcelas, situacao, 
                             data_pagamento, id_usuario) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $lancamento,
                    $descricao,
                    $id_centro_custo,
                    $valor_orcado,
                    $valor_realizado,
                    $emergencia,
                    $valor_emergencia,
                    $programado,
                    $parcelas,
                    $situacao,
                    $data_pagamento,
                    $id_usuario_logado
                ]);
                $sucesso_msg = "Lançamento criado com sucesso!";
            }
            
            if (!isset($erro)) {
                header("Location: PlenusFinanceiro.php?pagina=lancamentos&sucesso=" . urlencode($sucesso_msg));
                exit;
            }
        } catch (PDOException $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }

    // --- AÇÃO: EXCLUIR ---
    if ($_POST['acao'] == 'excluir' && ($tipo_usuario_logado == 'master' || $tipo_usuario_logado == 'admin')) {
        $id = $_POST['id'];
        try {
            $sql = "DELETE FROM lancamentos WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            header("Location: PlenusFinanceiro.php?pagina=lancamentos&sucesso=Lançamento excluído com sucesso!");
            exit;
        } catch (PDOException $e) {
            $erro = "Erro ao excluir: " . $e->getMessage();
        }
    }
}


// LÓGICA DE EXIBIÇÃO (GET)
$acao = $_GET['acao'] ?? 'listar';

// --- Título da Página ---
echo "<div class='content-header'><h1>Gestão de Lançamentos</h1></div>";
echo '<div class="content-body">';

// REMOVIDAS AS MENSAGENS DE ERRO/SUCESSO EM HTML - AGORA SERÃO EXIBIDAS VIA SWEETALERT

// --- Roteador de Exibição ---
switch ($acao):

    // --- CASO: 'novo' ou 'editar' ---
    case 'novo':
    case 'editar':

        $lancamento = null;
        if ($acao == 'editar') {
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM lancamentos WHERE id = ?");
            $stmt->execute([$id]);
            $lancamento = $stmt->fetch();
        }

        // Busca centros de custo para o dropdown
        $stmt_cc = $pdo->query("SELECT id, nome FROM centro_custo ORDER BY nome");
        $centros_custo = $stmt_cc->fetchAll();

        ?>
        <h3><?php echo $acao == 'editar' ? 'Editar' : 'Novo'; ?> Lançamento</h3>

        <form id="form-lancamento" action="PlenusFinanceiro.php?pagina=lancamentos" method="POST" onsubmit="return validarFormLancamento(this)">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" value="<?php echo $lancamento['id'] ?? ''; ?>">

            <div class="form-group">
                <label for="lancamento">Lançamento (Título) *</label>
                <input type="text" id="lancamento" name="lancamento"
                    value="<?php echo htmlspecialchars($lancamento['lancamento'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="id_centro_custo">Centro de Custo *</label>
                <select id="id_centro_custo" name="id_centro_custo" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($centros_custo as $cc): ?>
                        <option value="<?php echo $cc['id']; ?>" <?php echo (isset($lancamento['id_centro_custo']) && $lancamento['id_centro_custo'] == $cc['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cc['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="descricao">Descrição</label>
                <textarea id="descricao"
                    name="descricao"><?php echo htmlspecialchars($lancamento['descricao'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="valor_orcado">Valor Orçado *</label>
                <input type="text" id="valor_orcado" name="valor_orcado" 
                    value="<?php echo isset($lancamento['valor_orcado']) ? 'R$ ' . number_format($lancamento['valor_orcado'], 2, ',', '.') : 'R$ 0,00'; ?>" 
                    required>
            </div>

            <div class="form-group">
                <label for="valor_realizado">Valor Realizado</label>
                <input type="text" id="valor_realizado" name="valor_realizado"
                    value="<?php echo isset($lancamento['valor_realizado']) && $lancamento['valor_realizado'] > 0 ? 'R$ ' . number_format($lancamento['valor_realizado'], 2, ',', '.') : 'R$ 0,00'; ?>">
            </div>

            <div class="form-group">
                <label for="data_pagamento">Data Pagamento/Realizado</label>
                <input type="date" id="data_pagamento" name="data_pagamento"
                    value="<?php echo htmlspecialchars($lancamento['data_pagamento'] ?? ''); ?>">
            </div>

<?php
            // VERIFICA SE O USUÁRIO É MASTER OU ADMIN
            // Usamos $tipo_usuario (definido globalmente em PlenusFinanceiro.php)
            if ($tipo_usuario == 'master' || $tipo_usuario == 'admin'):
            ?>
                <div class="form-group">
                    <label for="situacao">Situação *</label>
                    <select id="situacao" name="situacao" required>
                        <?php 
                        $situacoes = ['pendente', 'aprovado', 'reprovado', 'cancelado'];
                        $situacao_atual = $lancamento['situacao'] ?? 'pendente';
                        foreach ($situacoes as $s) {
                            $selected = ($s == $situacao_atual) ? 'selected' : '';
                            echo "<option value='$s' $selected>" . ucfirst($s) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            
            <?php else: ?>
                <input type="hidden" name="situacao" value="pendente">
            
            <?php endif; // Fim da verificação de permissão ?>

            <div class="form-group">
                <label for="programado">Programado? *</label>
                <select id="programado" name="programado" required>
                    <option value="nao" <?php echo (isset($lancamento['programado']) && $lancamento['programado'] == 'nao') ? 'selected' : ''; ?>>Não</option>
                    <option value="sim" <?php echo (isset($lancamento['programado']) && $lancamento['programado'] == 'sim') ? 'selected' : ''; ?>>Sim</option>
                </select>
            </div>

            <div class="form-group">
                <label for="parcelas">Parcelas</label>
                <input type="number" id="parcelas" name="parcelas" min="1" step="1"
                    value="<?php echo htmlspecialchars($lancamento['parcelas'] ?? '1'); ?>">
            </div>

            <div class="form-group">
                <label for="emergencia">Emergência? *</label>
                <select id="emergencia" name="emergencia" required>
                    <option value="nao" <?php echo (isset($lancamento['emergencia']) && $lancamento['emergencia'] == 'nao') ? 'selected' : ''; ?>>Não</option>
                    <option value="sim" <?php echo (isset($lancamento['emergencia']) && $lancamento['emergencia'] == 'sim') ? 'selected' : ''; ?>>Sim</option>
                </select>
            </div>

            <div class="form-group">
                <label for="valor_emergencia">Valor de Emergência</label>
                <input type="text" id="valor_emergencia" name="valor_emergencia"
                    value="<?php echo isset($lancamento['valor_emergencia']) && $lancamento['valor_emergencia'] > 0 ? 'R$ ' . number_format($lancamento['valor_emergencia'], 2, ',', '.') : 'R$ 0,00'; ?>">
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Salvar</button>
                <a href="PlenusFinanceiro.php?pagina=lancamentos" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
        <?php
        break; // Fim do case 'novo'/'editar'

    // --- CASO: 'listar' (Padrão) ---
    default:
        ?>
        <a href="PlenusFinanceiro.php?pagina=lancamentos&acao=novo" class="btn btn-primary">Novo Lançamento</a>

        <table style="font-size: 0.9em; margin-top: 1.5rem;">
            <thead>
                <tr>
                    <th>Lançamento</th>
                    <th>Centro de Custo</th>
                    <th>Situação</th>
                    <th>Orçado (R$)</th>
                    <th>Realizado (R$)</th>
                    <th>Diferença (R$)</th>
                    <th>Usuário (Criou)</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // --- LÓGICA DE LISTAGEM COM PERMISSÃO ---
                $sql = "SELECT l.*, cc.nome AS nome_centro_custo, u.nome AS nome_usuario 
                        FROM lancamentos l
                        LEFT JOIN centro_custo cc ON l.id_centro_custo = cc.id
                        LEFT JOIN usuarios u ON l.id_usuario = u.id";

                $params = [];

                // Se for 'operador', filtra pelo centro de custo dele
                if ($tipo_usuario_logado == 'operador') {
                    $sql .= " WHERE l.id_centro_custo = ?";
                    $params[] = $id_centro_custo_logado;
                }

                $sql .= " ORDER BY l.id DESC"; // Mais recentes primeiro
        
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                while ($row = $stmt->fetch()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['lancamento']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['nome_centro_custo']) . "</td>";
                    echo "<td>" . ucfirst(htmlspecialchars($row['situacao'])) . "</td>";
                    echo "<td>R$ " . number_format($row['valor_orcado'], 2, ',', '.') . "</td>";
                    echo "<td>R$ " . number_format($row['valor_realizado'], 2, ',', '.') . "</td>";
                    // 'diferenca' vem direto do banco (coluna gerada)
                    echo "<td>R$ " . number_format($row['diferenca'], 2, ',', '.') . "</td>";
                    echo "<td>" . htmlspecialchars($row['nome_usuario']) . "</td>";

                    echo "<td class='actions'>";

                    // AGORA, apenas admin/master podem editar OU excluir
                    if ($tipo_usuario_logado == 'master' || $tipo_usuario_logado == 'admin') {

                        // Link de Edição
                        echo "<a href='PlenusFinanceiro.php?pagina=lancamentos&acao=editar&id={$row['id']}' class='btn-edit'>Editar</a>";

                        // Formulário de Exclusão
                        echo "<form method='POST' style='display:inline;' onsubmit='return confirmarExclusaoLancamento(this, \"" . htmlspecialchars($row['lancamento']) . "\")'>
                                <input type='hidden' name='acao' value='excluir'>
                                <input type='hidden' name='id' value='{$row['id']}'>
                                <button type='submit' class='btn-delete' style='padding: 4px 8px; font-size: 0.9em; cursor:pointer;'>Excluir</button>
                              </form>";
                    } else {
                        // Se for 'operador', exibe um traço para indicar que não há ações
                        echo "—";
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

// SCRIPT PARA SWEETALERT E FORMATAÇÃO DE VALORES
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

    // Inicializa a formatação dos campos de valor
    inicializarFormatacaoValores();
});

// Função para formatar valor em moeda brasileira
function formatarMoeda(valor) {
    // Remove tudo que não é número
    let valorNumerico = valor.replace(/\D/g, '');
    
    // Se estiver vazio, retorna 0,00
    if (valorNumerico === '') return 'R$ 0,00';
    
    // Converte para número decimal
    let valorDecimal = (parseInt(valorNumerico) / 100).toFixed(2);
    
    // Formata com separadores
    let partes = valorDecimal.split('.');
    let parteInteira = partes[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    let parteDecimal = partes[1];
    
    return 'R$ ' + parteInteira + ',' + parteDecimal;
}

// Função para remover formatação antes de enviar
function removerFormatacaoMoeda(valorFormatado) {
    return valorFormatado.replace('R$ ', '').replace(/\./g, '').replace(',', '.');
}

// Função para inicializar a formatação nos campos de valor
function inicializarFormatacaoValores() {
    const camposValor = ['valor_orcado', 'valor_realizado', 'valor_emergencia'];
    
    camposValor.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            // Formata o valor inicial
            if (campo.value === '') {
                campo.value = 'R$ 0,00';
            }
            
            // Evento ao digitar
            campo.addEventListener('input', function(e) {
                let cursorPos = this.selectionStart;
                let valorOriginal = this.value;
                
                // Formata o valor
                this.value = formatarMoeda(valorOriginal);
                
                // Ajusta a posição do cursor
                let diff = this.value.length - valorOriginal.length;
                this.setSelectionRange(cursorPos + diff, cursorPos + diff);
            });
            
            // Evento ao focar - seleciona tudo para facilitar a digitação
            campo.addEventListener('focus', function() {
                this.select();
            });
            
            // Evento ao perder o foco - garante que está formatado
            campo.addEventListener('blur', function() {
                if (this.value === '') {
                    this.value = 'R$ 0,00';
                } else {
                    this.value = formatarMoeda(this.value);
                }
            });
        }
    });
}

// Função para confirmar exclusão com SweetAlert
function confirmarExclusaoLancamento(form, nomeLancamento) {
    Swal.fire({
        title: 'Excluir Lançamento',
        html: `<strong>"${nomeLancamento}"</strong><br><br>Tem certeza que deseja excluir este lançamento?<br>Esta ação não pode ser desfeita!`,
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

// Função para validar o formulário de lançamento
function validarFormLancamento(form) {
    const lancamento = document.getElementById('lancamento').value.trim();
    const centroCusto = document.getElementById('id_centro_custo').value;
    const valorOrcado = document.getElementById('valor_orcado').value;
    const parcelas = document.getElementById('parcelas').value;
    
    // Validações básicas
    if (!lancamento || !centroCusto || !valorOrcado) {
        Swal.fire({
            icon: 'warning',
            title: 'Campos obrigatórios',
            text: 'Por favor, preencha todos os campos obrigatórios (*).',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    // Validação de valor orçado
    const valorOrcadoNumerico = parseFloat(removerFormatacaoMoeda(valorOrcado));
    if (valorOrcadoNumerico <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Valor inválido',
            text: 'O valor orçado deve ser maior que zero.',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    // Validação de parcelas
    if (parcelas && (parseInt(parcelas) < 1 || isNaN(parseInt(parcelas)))) {
        Swal.fire({
            icon: 'warning',
            title: 'Parcelas inválidas',
            text: 'O número de parcelas deve ser pelo menos 1.',
            confirmButtonColor: '#3085d6'
        });
        return false;
    }
    
    // Validação de datas (se preenchida)
    const dataPagamento = document.getElementById('data_pagamento').value;
    if (dataPagamento) {
        const dataPag = new Date(dataPagamento);
        const hoje = new Date();
        if (dataPag > hoje) {
            Swal.fire({
                icon: 'question',
                title: 'Data futura',
                text: 'A data de pagamento é no futuro. Deseja continuar mesmo assim?',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, continuar',
                cancelButtonText: 'Corrigir data'
            }).then((result) => {
                if (!result.isConfirmed) {
                    return false;
                }
            });
        }
    }
    
    // Remove formatação dos campos de valor antes de enviar
    const camposParaFormatar = ['valor_orcado', 'valor_realizado', 'valor_emergencia'];
    camposParaFormatar.forEach(id => {
        const campo = document.getElementById(id);
        if (campo) {
            // Cria um campo hidden com o valor não formatado
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = campo.name;
            hiddenInput.value = removerFormatacaoMoeda(campo.value);
            
            // Remove o campo formatado e adiciona o não formatado
            campo.removeAttribute('name');
            form.appendChild(hiddenInput);
        }
    });
    
    // Se todas as validações passaram
    return true;
}

document.querySelector("form").addEventListener("submit", function (e) {
    const emergencia = document.getElementById("emergencia").value;
    const valorInput = document.getElementById("valor_emergencia").value;

    // Remover "R$" e formatar
    let valorNum = valorInput.replace("R$", "").replace(/\./g, "").replace(",", ".").trim();
    valorNum = parseFloat(valorNum) || 0;

    if (valorNum > 0 && emergencia !== "sim") {
        e.preventDefault(); // Impede envio
        
        Swal.fire({
            icon: 'error',
            title: 'Inconsistência nos dados',
            html: `
                <div style="text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ff6b6b; margin-bottom: 15px;"></i>
                    <p><strong>Valor de emergência:</strong> R$ ${valorNum.toFixed(2).replace('.', ',')}</p>
                    <p style="color: #dc3545; font-weight: bold;">
                        Quando há valor de emergência, o campo "Emergência" deve ser marcado como "SIM".
                    </p>
                </div>
            `,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Corrigir'
        });
        
        // Destaca os campos relevantes
        const emergenciaField = document.getElementById("emergencia");
        const valorField = document.getElementById("valor_emergencia");
        
        emergenciaField.style.borderColor = "#dc3545";
        valorField.style.borderColor = "#dc3545";
        
        // Foca no campo de emergência
        emergenciaField.focus();
        
        // Remove o destaque após alguns segundos
        setTimeout(() => {
            emergenciaField.style.borderColor = "";
            valorField.style.borderColor = "";
        }, 5000);
        
        return false;
    }
});
</script>