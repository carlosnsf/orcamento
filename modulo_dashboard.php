<?php
// $pdo, $tipo_usuario, $id_usuario_logado, $id_centro_custo_logado
// já estão disponíveis vindos do PlenusFinanceiro.php

$where_condicao = ""; // Cláusula WHERE para as consultas
$params = []; // Parâmetros para o PDO
$titulo_visao = "Visão Global (Todos os Centros de Custo)";

if ($tipo_usuario == 'operador') {
    $where_condicao = " WHERE id = ?";
    $params[] = $id_centro_custo_logado;
    $stmt_nome_cc = $pdo->prepare("SELECT nome FROM centro_custo WHERE id = ?");
    $stmt_nome_cc->execute([$id_centro_custo_logado]);
    $nome_cc = $stmt_nome_cc->fetchColumn();
    $titulo_visao = "Visão: " . htmlspecialchars($nome_cc);
}

// 1. BUSCAR VALORES DO(S) CENTRO(S) DE CUSTO (ORÇADO)
$sql_orcado = "SELECT SUM(valor_mensal) as total_mensal, SUM(valor_anual) as total_anual FROM centro_custo" . $where_condicao;
$stmt_orcado = $pdo->prepare($sql_orcado);
$stmt_orcado->execute($params);
$orcado_data = $stmt_orcado->fetch();

$total_orcado_mensal = $orcado_data['total_mensal'] ?? 0;
$total_orcado_anual = $orcado_data['total_anual'] ?? 0;

// 2. BUSCAR VALORES DOS LANÇAMENTOS (POR SITUAÇÃO)
if ($tipo_usuario == 'operador') {
    $where_condicao = " WHERE id_centro_custo = ?";
} else {
    $where_condicao = ""; // Reseta para admin/master
}

$sql_lancamentos = "SELECT situacao, 
                           SUM(valor_orcado) as total_orcado, 
                           SUM(valor_realizado) as total_realizado 
                    FROM lancamentos" 
                    . $where_condicao . 
                    " GROUP BY situacao";
                    
$stmt_lancamentos = $pdo->prepare($sql_lancamentos);
$stmt_lancamentos->execute($params);
$lancamentos_data = $stmt_lancamentos->fetchAll();

$total_aprovado = 0;
$total_pendente = 0;
$total_cancelado = 0;

foreach ($lancamentos_data as $row) {
    if ($row['situacao'] == 'aprovado') {
        $total_aprovado = $row['total_realizado'] ?? 0;
    } 
    else if ($row['situacao'] == 'pendente') {
        $total_pendente = $row['total_orcado'] ?? 0;
    }
    else if ($row['situacao'] == 'cancelado' || $row['situacao'] == 'reprovado') {
        $total_cancelado += $row['total_orcado'] ?? 0;
    }
}

// 3. CALCULAR OS VALORES FINAIS
$disponivel_mensal = $total_orcado_mensal - $total_aprovado;
$disponivel_anual = $total_orcado_anual - $total_aprovado;

// Dados para o Gráfico de Pizza (Consumo % Mensal)
$percent_consumido_mensal = 0;
$percent_restante_mensal = 100;

if ($total_orcado_mensal > 0) {
    $percent_consumido_mensal = round(($total_aprovado / $total_orcado_mensal) * 100, 2);
}
$percent_restante_mensal = 100 - $percent_consumido_mensal;

$chart_data_mensal = [$percent_consumido_mensal, $percent_restante_mensal];
$chart_labels_mensal = ['Consumido (Aprovado)', 'Disponível'];


// ✨ === NOVOS CÁLCULOS (ANUAL) === ✨
$percent_consumido_anual = 0;
$percent_restante_anual = 100;

if ($total_orcado_anual > 0) {
    // Usamos o *mesmo* $total_aprovado, mas comparamos com o orçamento ANUAL
    $percent_consumido_anual = round(($total_aprovado / $total_orcado_anual) * 100, 2);
}
$percent_restante_anual = 100 - $percent_consumido_anual;

$chart_data_anual = [$percent_consumido_anual, $percent_restante_anual];
$chart_labels_anual = ['Consumido (Aprovado)', 'Disponível'];
// ✨ === FIM DOS NOVOS CÁLCULOS === ✨

?>

<div class="content-header">
    <h1>Dashboard</h1>
    <p style="margin-top: -10px;"><?php echo $titulo_visao; ?></p>
</div>

<div class="content-body">
    
    <div class="dashboard-cards">
        <div class="card">
            <h3>Disponível Mensal</h3>
            <div class="valor disponivel">R$ <?php echo number_format($disponivel_mensal, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Disponível Anual</h3>
            <div class="valor disponivel">R$ <?php echo number_format($disponivel_anual, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Aprovado (Consumido)</h3>
            <div class="valor aprovado">R$ <?php echo number_format($total_aprovado, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Pendente (Aprovação)</h3>
            <div class="valor pendente">R$ <?php echo number_format($total_pendente, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Cancelado / Reprovado</h3>
            <div class="valor cancelado">R$ <?php echo number_format($total_cancelado, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Orçado Mensal (Total)</h3>
            <div class="valor">R$ <?php echo number_format($total_orcado_mensal, 2, ',', '.'); ?></div>
        </div>
    </div>
    
    <div class="charts-container">
    
        <div class="chart-wrapper">
            <h3>Consumo do Orçamento Mensal (%)</h3>
            <div class="chart-box">
                <canvas id="pieChartConsumoMensal"></canvas>
            </div>
        </div>
        
        <div class="chart-wrapper">
            <h3>Consumo do Orçamento Anual (%)</h3>
            <div class="chart-box">
                <canvas id="pieChartConsumoAnual"></canvas>
            </div>
        </div>
        
    </div>
    
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // --- GRÁFICO 1: MENSAL ---
    
    // Pega os dados MENSAL que o PHP preparou
    const dataConsumoMensal = <?php echo json_encode($chart_data_mensal); ?>;
    const labelsConsumoMensal = <?php echo json_encode($chart_labels_mensal); ?>;
    const ctxMensal = document.getElementById('pieChartConsumoMensal').getContext('2d');
    
    new Chart(ctxMensal, {
        type: 'pie',
        data: {
            labels: labelsConsumoMensal,
            datasets: [{
                data: dataConsumoMensal,
                backgroundColor: [
                    'rgba(0, 123, 255, 0.7)', // Consumido (Azul)
                    'rgba(40, 167, 69, 0.7)'  // Disponível (Verde)
                ],
                borderColor: ['#007bff', '#28a745'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw.toFixed(2) + '%';
                        }
                    }
                }
            }
        }
    });

    
    // --- ✨ GRÁFICO 2: ANUAL (NOVO) ✨ ---
    
    // Pega os dados ANUAL que o PHP preparou
    const dataConsumoAnual = <?php echo json_encode($chart_data_anual); ?>;
    const labelsConsumoAnual = <?php echo json_encode($chart_labels_anual); ?>;
    const ctxAnual = document.getElementById('pieChartConsumoAnual').getContext('2d');
    
    new Chart(ctxAnual, {
        type: 'pie',
        data: {
            labels: labelsConsumoAnual,
            datasets: [{
                data: dataConsumoAnual,
                backgroundColor: [
                    'rgba(4, 171, 248, 0.7)', // Consumido (Vermelho)
                    'rgba(40, 167, 69, 0.7)'  // Disponível (Verde)
                ],
                borderColor: ['#dc3545', '#28a745'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.raw.toFixed(2) + '%';
                        }
                    }
                }
            }
        }
    });
    
});
</script>