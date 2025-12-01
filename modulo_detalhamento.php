<?php
// dashboard_avancado.php
// Permissões já verificadas
// $pdo e $tipo_usuario já estão disponíveis

// =======================================================
// 1. CONSULTAS EXISTENTES (para manter compatibilidade)
// =======================================================
$sql_timeline = "SELECT 
                    DATE_FORMAT(data_pagamento, '%Y-%m') as mes_ano, 
                    SUM(CASE WHEN situacao = 'aprovado' THEN valor_realizado ELSE 0 END) as total_gasto_mes,
                    SUM(CASE WHEN situacao = 'pendente' THEN valor_orcado ELSE 0 END) as total_pendente_mes,
                    SUM(CASE WHEN situacao IN ('cancelado', 'reprovado') THEN valor_orcado ELSE 0 END) as total_cancelado_mes
                 FROM lancamentos 
                 WHERE data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY mes_ano
                 ORDER BY mes_ano ASC";
$stmt_timeline = $pdo->query($sql_timeline);
$timeline_data = $stmt_timeline->fetchAll();

$sql_ranking = "SELECT 
                    cc.nome, 
                    COUNT(l.id) as total_lancamentos,
                    SUM(CASE WHEN l.situacao = 'aprovado' THEN l.valor_realizado ELSE 0 END) as total_aprovado,
                    SUM(CASE WHEN l.situacao = 'pendente' THEN l.valor_orcado ELSE 0 END) as total_pendente,
                    SUM(CASE WHEN l.situacao IN ('cancelado', 'reprovado') THEN l.valor_orcado ELSE 0 END) as total_cancelado_reprovado
                FROM centro_custo cc
                LEFT JOIN lancamentos l ON cc.id = l.id_centro_custo
                GROUP BY cc.id, cc.nome
                ORDER BY total_aprovado DESC";
$stmt_ranking = $pdo->query($sql_ranking);
$ranking_data = $stmt_ranking->fetchAll();

$global_total_aprovado = array_sum(array_column($ranking_data, 'total_aprovado'));
$global_total_pendente = array_sum(array_column($ranking_data, 'total_pendente'));
$global_total_cancelado = array_sum(array_column($ranking_data, 'total_cancelado_reprovado'));

$sql_orcado_global = "SELECT SUM(valor_anual) as total_anual, SUM(valor_mensal) as total_mensal FROM centro_custo";
$stmt_orcado_global = $pdo->query($sql_orcado_global);
$orcado_global = $stmt_orcado_global->fetch(PDO::FETCH_ASSOC);
$total_orcado_global_anual = $orcado_global['total_anual'] ?? 0;
$total_orcado_global_mensal = $orcado_global['total_mensal'] ?? 0;
$valor_disponivel_remanejavel = $total_orcado_global_anual - $global_total_aprovado;

// =======================================================
// 2. NOVAS CONSULTAS PARA GRÁFICOS AVANÇADOS
// =======================================================

// 2.1. Gráfico de Pizza - Distribuição por Centro de Custo
$sql_distribuicao = "SELECT 
                        cc.nome,
                        SUM(CASE WHEN l.situacao = 'aprovado' THEN l.valor_realizado ELSE 0 END) as total_aprovado
                     FROM centro_custo cc
                     LEFT JOIN lancamentos l ON cc.id = l.id_centro_custo
                     GROUP BY cc.id, cc.nome
                     HAVING total_aprovado > 0
                     ORDER BY total_aprovado DESC";
$stmt_distribuicao = $pdo->query($sql_distribuicao);
$distribuicao_data = $stmt_distribuicao->fetchAll();

// 2.2. Gráfico de Rosca - Status dos Lançamentos
$sql_status = "SELECT 
                    CASE 
                        WHEN situacao = 'aprovado' THEN 'Aprovados'
                        WHEN situacao = 'pendente' THEN 'Pendentes'
                        WHEN situacao IN ('cancelado', 'reprovado') THEN 'Cancelados/Reprovados'
                    END as status_grupo,
                    COUNT(*) as quantidade,
                    SUM(CASE 
                        WHEN situacao = 'aprovado' THEN valor_realizado 
                        ELSE valor_orcado 
                    END) as valor_total
               FROM lancamentos 
               GROUP BY status_grupo";
$stmt_status = $pdo->query($sql_status);
$status_data = $stmt_status->fetchAll();

// 2.3. Gráfico de Barras Empilhadas - Evolução por Categoria
$sql_categorias_mensal = "SELECT 
                            DATE_FORMAT(data_pagamento, '%Y-%m') as mes_ano,
                            COALESCE(categoria, 'Sem Categoria') as categoria,
                            SUM(CASE WHEN situacao = 'aprovado' THEN valor_realizado ELSE 0 END) as total
                          FROM lancamentos 
                          WHERE data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          GROUP BY mes_ano, categoria
                          ORDER BY mes_ano ASC, total DESC";
$stmt_categorias = $pdo->query($sql_categorias_mensal);
$categorias_data = $stmt_categorias->fetchAll();

// Organizar dados para gráfico empilhado
$categorias_por_mes = [];
$categorias_unicas = [];
foreach ($categorias_data as $row) {
    $mes = $row['mes_ano'];
    $categoria = $row['categoria'];
    $valor = $row['total'];
    
    if (!isset($categorias_por_mes[$mes])) {
        $categorias_por_mes[$mes] = [];
    }
    $categorias_por_mes[$mes][$categoria] = $valor;
    
    if (!in_array($categoria, $categorias_unicas)) {
        $categorias_unicas[] = $categoria;
    }
}

// 2.4. Gráfico de Área - Orçado vs Realizado (Burn Rate)
$sql_burn_rate = "SELECT 
                    DATE_FORMAT(data_pagamento, '%Y-%m') as mes_ano,
                    SUM(valor_orcado) as total_orcado,
                    SUM(CASE WHEN situacao = 'aprovado' THEN valor_realizado ELSE 0 END) as total_realizado
                  FROM lancamentos 
                  WHERE data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY mes_ano
                  ORDER BY mes_ano ASC";
$stmt_burn_rate = $pdo->query($sql_burn_rate);
$burn_rate_data = $stmt_burn_rate->fetchAll();

// 2.5. Gráfico de Radar - Performance Multi-dimensional
$sql_radar = "SELECT 
                cc.nome,
                -- Eficiência (Realizado/Orçado)
                COALESCE(SUM(CASE WHEN l.situacao = 'aprovado' THEN l.valor_realizado ELSE 0 END) / 
                NULLIF(SUM(CASE WHEN l.situacao IN ('aprovado', 'pendente') THEN l.valor_orcado ELSE 0 END), 0) * 100, 0) as eficiencia_percent,
                -- Velocidade de Aprovação (% aprovado)
                COALESCE(SUM(CASE WHEN l.situacao = 'aprovado' THEN 1 ELSE 0 END) / NULLIF(COUNT(l.id), 0) * 100, 0) as taxa_aprovacao,
                -- Custo de Emergência (%)
                COALESCE(SUM(CASE WHEN l.situacao = 'aprovado' AND l.emergencia = 'sim' THEN l.valor_realizado ELSE 0 END) / 
                NULLIF(SUM(CASE WHEN l.situacao = 'aprovado' THEN l.valor_realizado ELSE 0 END), 0) * 100, 0) as percent_emergencia,
                -- Precisão Orçamentária
                COALESCE(AVG(CASE WHEN l.situacao = 'aprovado' AND l.valor_orcado > 0 
                           THEN l.valor_realizado / l.valor_orcado * 100 END), 100) as precisao_percent
              FROM centro_custo cc
              LEFT JOIN lancamentos l ON cc.id = l.id_centro_custo
              GROUP BY cc.id, cc.nome
              HAVING COUNT(l.id) > 0
              LIMIT 5";
$stmt_radar = $pdo->query($sql_radar);
$radar_data = $stmt_radar->fetchAll();

// 2.6. Gráfico de Calor (Heatmap) - Padrões Temporais
$sql_heatmap = "SELECT 
                    DAYOFWEEK(data_pagamento) as dia_numero,
                    DAYNAME(data_pagamento) as dia_nome,
                    MONTH(data_pagamento) as mes_numero,
                    MONTHNAME(data_pagamento) as mes_nome,
                    COUNT(*) as quantidade_lancamentos,
                    SUM(CASE WHEN situacao = 'aprovado' THEN valor_realizado ELSE 0 END) as valor_total
                FROM lancamentos 
                WHERE data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY dia_numero, mes_numero
                ORDER BY mes_numero, dia_numero";
$stmt_heatmap = $pdo->query($sql_heatmap);
$heatmap_data = $stmt_heatmap->fetchAll();

// Organizar dados para heatmap
$heatmap_matrix = [];
$meses_heatmap = [];
$dias_semana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
foreach ($heatmap_data as $row) {
    $mes = $row['mes_nome'] . ' ' . date('Y', strtotime($row['mes_nome'] . ' 1'));
    $dia = $row['dia_numero'] - 1; // Ajustar para array 0-6
    $valor = $row['valor_total'];
    
    if (!in_array($mes, $meses_heatmap)) {
        $meses_heatmap[] = $mes;
    }
    
    $heatmap_matrix[$mes][$dia] = $valor;
}

// 2.7. Gráfico de Barras Horizontais - Top 10 Maiores Lançamentos
$sql_top_lancamentos = "SELECT 
                            l.lancamento,
                            cc.nome as centro_custo,
                            l.valor_realizado as valor,
                            DATE_FORMAT(l.data_pagamento, '%d/%m/%Y') as data_pag,
                            l.situacao
                        FROM lancamentos l
                        LEFT JOIN centro_custo cc ON l.id_centro_custo = cc.id
                        WHERE l.situacao = 'aprovado' AND l.valor_realizado > 0
                        ORDER BY l.valor_realizado DESC
                        LIMIT 10";
$stmt_top_lancamentos = $pdo->query($sql_top_lancamentos);
$top_lancamentos_data = $stmt_top_lancamentos->fetchAll();

// 2.8. Gráfico de Bolhas - Risco vs Valor
$sql_bubble = "SELECT 
                    cc.nome,
                    COUNT(l.id) as quantidade,
                    SUM(CASE WHEN l.situacao = 'aprovado' THEN l.valor_realizado ELSE 0 END) as valor_total,
                    -- 'Risco' baseado em % de emergências
                    COALESCE(SUM(CASE WHEN l.emergencia = 'sim' THEN 1 ELSE 0 END) / NULLIF(COUNT(l.id), 0) * 100, 0) as risco_percent,
                    -- 'Desvio' baseado em diferença entre orçado e realizado
                    COALESCE(AVG(CASE WHEN l.situacao = 'aprovado' AND l.valor_orcado > 0 
                               THEN ABS(l.valor_realizado - l.valor_orcado) / l.valor_orcado * 100 END), 0) as desvio_percent
               FROM centro_custo cc
               LEFT JOIN lancamentos l ON cc.id = l.id_centro_custo
               GROUP BY cc.id, cc.nome
               HAVING COUNT(l.id) > 0 AND valor_total > 0
               LIMIT 15";
$stmt_bubble = $pdo->query($sql_bubble);
$bubble_data = $stmt_bubble->fetchAll();

// 2.9. Dados para Gráfico de Gauge
$saude_percent = $total_orcado_global_anual > 0 ? ($valor_disponivel_remanejavel / $total_orcado_global_anual) * 100 : 0;

// =======================================================
// 3. PREPARAÇÃO DOS DADOS PARA JAVASCRIPT
// =======================================================

// Dados existentes
$chart_labels_timeline = []; $chart_data_gasto = []; $chart_data_pendente = []; $chart_data_cancelado = [];
foreach ($timeline_data as $row) {
    $dateObj = DateTime::createFromFormat('!Y-m', $row['mes_ano']);
    $chart_labels_timeline[] = $dateObj->format('M/y');
    $chart_data_gasto[] = $row['total_gasto_mes'];
    $chart_data_pendente[] = $row['total_pendente_mes'];
    $chart_data_cancelado[] = $row['total_cancelado_mes'];
}

// Dados ranking
$chart_labels_ranking = [];
$chart_data_ranking_aprovado = [];
foreach ($ranking_data as $cc) {
    if ($cc['total_aprovado'] > 0) { 
        $chart_labels_ranking[] = htmlspecialchars($cc['nome']);
        $chart_data_ranking_aprovado[] = $cc['total_aprovado'];
    }
}

// Dados para gráfico de distribuição
$pie_labels = [];
$pie_data = [];
$pie_colors = [];
foreach ($distribuicao_data as $row) {
    $pie_labels[] = $row['nome'];
    $pie_data[] = $row['total_aprovado'];
    // Gerar cor aleatória para cada fatia
    $pie_colors[] = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

// Dados para gráfico de status
$doughnut_labels = [];
$doughnut_data = [];
$doughnut_colors = ['#28a745', '#ffc107', '#dc3545'];
foreach ($status_data as $row) {
    $doughnut_labels[] = $row['status_grupo'];
    $doughnut_data[] = $row['valor_total'];
}

// Dados para gráfico de categorias empilhadas
$stacked_labels = array_keys($categorias_por_mes);
$stacked_datasets = [];
$categoria_colors = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
];

foreach ($categorias_unicas as $index => $categoria) {
    $dataset = [
        'label' => $categoria,
        'data' => [],
        'backgroundColor' => $categoria_colors[$index % count($categoria_colors)],
        'borderColor' => $categoria_colors[$index % count($categoria_colors)],
        'borderWidth' => 1
    ];
    
    foreach ($stacked_labels as $mes) {
        $dataset['data'][] = $categorias_por_mes[$mes][$categoria] ?? 0;
    }
    
    $stacked_datasets[] = $dataset;
}

// Dados para burn rate
$burn_labels = [];
$burn_orcado = [];
$burn_realizado = [];
foreach ($burn_rate_data as $row) {
    $dateObj = DateTime::createFromFormat('!Y-m', $row['mes_ano']);
    $burn_labels[] = $dateObj->format('M/y');
    $burn_orcado[] = $row['total_orcado'];
    $burn_realizado[] = $row['total_realizado'];
}

// Dados para radar
$radar_labels = [];
$radar_datasets = [];
$radar_colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'];
foreach ($radar_data as $index => $row) {
    $radar_labels[] = $row['nome'];
    
    $dataset = [
        'label' => $row['nome'],
        'data' => [
            min($row['eficiencia_percent'], 100),
            min($row['taxa_aprovacao'], 100),
            min($row['percent_emergencia'], 100),
            min($row['precisao_percent'], 100)
        ],
        'backgroundColor' => substr($radar_colors[$index % count($radar_colors)], 0, 7) . '20',
        'borderColor' => $radar_colors[$index % count($radar_colors)],
        'borderWidth' => 2
    ];
    
    $radar_datasets[] = $dataset;
}

// Dados para heatmap
$heatmap_values = [];
foreach ($meses_heatmap as $mes) {
    $row = [];
    for ($i = 0; $i < 7; $i++) {
        $row[] = $heatmap_matrix[$mes][$i] ?? 0;
    }
    $heatmap_values[] = $row;
}

// Dados para top lançamentos
$horizontal_labels = [];
$horizontal_data = [];
foreach ($top_lancamentos_data as $row) {
    $label = substr($row['lancamento'], 0, 30) . (strlen($row['lancamento']) > 30 ? '...' : '');
    $horizontal_labels[] = $label;
    $horizontal_data[] = $row['valor'];
}

// Dados para bubble chart - VERSÃO CORRIGIDA
$bubble_datasets = [];
$bubble_labels = [];
$bubble_x = [];
$bubble_y = [];
$bubble_r = [];
$bubble_colors = [];

foreach ($bubble_data as $row) {
    $bubble_labels[] = $row['nome'];
    $bubble_x[] = $row['quantidade'];
    $bubble_y[] = $row['risco_percent'];
    $bubble_r[] = min(30, max(5, $row['valor_total'] / 10000));
    $bubble_colors[] = $row['risco_percent'] > 30 ? '#ff6384' : 
                       ($row['risco_percent'] > 15 ? '#ffce56' : '#36a2eb');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Avançado - Análise Gerencial</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard-container {
            max-width: 100%;
            margin: 0 auto;
        }

        .dashboard-header {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .dashboard-header h1 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 2.2rem;
        }

        .dashboard-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .metric-title {
            color: #7f8c8d;
            font-size: 0.95rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .metric-value {
            color: #2c3e50;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .metric-value.aprovado { color: #28a745; }
        .metric-value.pendente { color: #ffc107; }
        .metric-value.cancelado { color: #dc3545; }
        .metric-value.disponivel { color: #17a2b8; }

        .metric-trend {
            font-size: 0.9rem;
            color: #95a5a6;
        }

        .metric-trend.positive { color: #28a745; }
        .metric-trend.negative { color: #dc3545; }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }

        .chart-container:hover {
            transform: translateY(-3px);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-title {
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .chart-actions {
            display: flex;
            gap: 10px;
        }

        .chart-btn {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.85rem;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s;
        }

        .chart-btn:hover {
            background: #e9ecef;
            color: #495057;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .insights-panel {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .insights-title {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .insight-item {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            border-radius: 8px;
            padding: 15px;
        }

        .insight-item.warning { border-left-color: #ffc107; }
        .insight-item.danger { border-left-color: #dc3545; }
        .insight-item.success { border-left-color: #28a745; }

        .insight-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .insight-text {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .tabs-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .tabs-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .tabs-nav {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px 20px;
            font-size: 0.95rem;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            background: #f8f9fa;
        }

        .tab-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .tab-content {
            padding: 25px;
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid #e9ecef;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            color: #6c757d;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .gauge-container {
            text-align: center;
            padding: 20px 0;
        }

        .gauge-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 20px 0;
        }

        .gauge-label {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .gauge-status {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 15px;
        }

        .status-good { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-danger { background: #f8d7da; color: #721c24; }

        .heatmap-container {
            overflow-x: auto;
        }

        .heatmap-table {
            border-collapse: collapse;
            margin: 0 auto;
        }

        .heatmap-cell {
            width: 40px;
            height: 40px;
            text-align: center;
            font-size: 0.8rem;
            border-radius: 4px;
            position: relative;
        }

        .heatmap-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 600;
            color: white;
            mix-blend-mode: difference;
        }

        .legend {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container {
                padding: 15px;
            }
            
            .metric-card {
                padding: 15px;
            }
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
            font-style: italic;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            color: #95a5a6;
        }

        .fab-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1000;
        }

        .fab-button:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Cabeçalho -->
        <div class="dashboard-header">
            <h1><i class="fas fa-chart-line"></i> Dashboard Avançado</h1>
            <p>Análise completa e visualizações interativas dos dados financeiros</p>
        </div>

        <!-- Métricas Principais -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-title">Disponível para Realocar</div>
                <div class="metric-value disponivel">R$ <?php echo number_format($valor_disponivel_remanejavel, 2, ',', '.'); ?></div>
                <div class="metric-trend">
                    <?php 
                    $percent_disponivel = $total_orcado_global_anual > 0 ? ($valor_disponivel_remanejavel / $total_orcado_global_anual) * 100 : 0;
                    echo number_format($percent_disponivel, 1) . '% do orçamento anual';
                    ?>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Total Aprovado (Realizado)</div>
                <div class="metric-value aprovado">R$ <?php echo number_format($global_total_aprovado, 2, ',', '.'); ?></div>
                <div class="metric-trend">
                    <?php 
                    $percent_aprovado = $total_orcado_global_anual > 0 ? ($global_total_aprovado / $total_orcado_global_anual) * 100 : 0;
                    echo number_format($percent_aprovado, 1) . '% do orçamento anual';
                    ?>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Total Pendente</div>
                <div class="metric-value pendente">R$ <?php echo number_format($global_total_pendente, 2, ',', '.'); ?></div>
                <div class="metric-trend">
                    <?php 
                    $relacao_pendente = $global_total_aprovado > 0 ? ($global_total_pendente / $global_total_aprovado) * 100 : 0;
                    echo number_format($relacao_pendente, 1) . '% em relação aos aprovados';
                    ?>
                </div>
            </div>
            
            <div class="metric-card">
                <div class="metric-title">Total Cancelado/Reprovado</div>
                <div class="metric-value cancelado">R$ <?php echo number_format($global_total_cancelado, 2, ',', '.'); ?></div>
                <div class="metric-trend">
                    <?php 
                    $percent_cancelado = $total_orcado_global_anual > 0 ? ($global_total_cancelado / $total_orcado_global_anual) * 100 : 0;
                    echo number_format($percent_cancelado, 1) . '% do orçamento anual';
                    ?>
                </div>
            </div>
        </div>

        <!-- Insights Rápidos -->
        <div class="insights-panel">
            <h2 class="insights-title"><i class="fas fa-lightbulb"></i> Insights Automáticos</h2>
            <div class="insights-grid">
                <?php
                // Insight 1: Saúde do Orçamento
                if ($percent_disponivel < 10) {
                    echo '<div class="insight-item danger">
                            <div class="insight-title">Orçamento Crítico</div>
                            <div class="insight-text">Apenas ' . number_format($percent_disponivel, 1) . '% do orçamento está disponível. Necessária revisão urgente.</div>
                          </div>';
                } elseif ($percent_disponivel < 25) {
                    echo '<div class="insight-item warning">
                            <div class="insight-title">Atenção ao Orçamento</div>
                            <div class="insight-text">' . number_format($percent_disponivel, 1) . '% disponível. Recomenda-se monitorar novos gastos.</div>
                          </div>';
                }
                
                // Insight 2: Concentração
                if (!empty($ranking_data) && $ranking_data[0]['total_aprovado'] > 0) {
                    $percent_top1 = ($ranking_data[0]['total_aprovado'] / $global_total_aprovado) * 100;
                    if ($percent_top1 > 50) {
                        echo '<div class="insight-item warning">
                                <div class="insight-title">Concentração de Gastos</div>
                                <div class="insight-text">' . htmlspecialchars($ranking_data[0]['nome']) . ' concentra ' . number_format($percent_top1, 0) . '% dos gastos.</div>
                              </div>';
                    }
                }
                
                // Insight 3: Pendentes vs Aprovados
                if ($global_total_aprovado > 0 && ($global_total_pendente / $global_total_aprovado) > 0.5) {
                    echo '<div class="insight-item warning">
                            <div class="insight-title">Volume de Pendências</div>
                            <div class="insight-text'>O valor pendente é ' . number_format(($global_total_pendente / $global_total_aprovado) * 100, 0) . '% dos valores aprovados.</div>
                          </div>';
                }
                ?>
            </div>
        </div>

        <!-- Grid de Gráficos -->
        <div class="charts-grid">
            <!-- Gráfico 1: Distribuição por Centro de Custo (Pizza) -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Distribuição por Centro de Custo</div>
                    <div class="chart-actions">
                        <button class="chart-btn" onclick="downloadChart('pieChart')"><i class="fas fa-download"></i></button>
                        <button class="chart-btn" onclick="toggleLegend('pieChart')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="pieChart"></canvas>
                </div>
                <?php if (empty($distribuicao_data)): ?>
                    <div class="no-data">Não há dados para exibir</div>
                <?php endif; ?>
            </div>

            <!-- Gráfico 2: Status dos Lançamentos (Rosca) -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Status dos Lançamentos</div>
                    <div class="chart-actions">
                        <button class="chart-btn" onclick="downloadChart('doughnutChart')"><i class="fas fa-download"></i></button>
                        <button class="chart-btn" onclick="toggleLegend('doughnutChart')"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="doughnutChart"></canvas>
                </div>
                <?php if (empty($status_data)): ?>
                    <div class="no-data">Não há dados para exibir</div>
                <?php endif; ?>
            </div>

            <!-- Gráfico 3: Orçado vs Realizado (Área) -->
            <div class="chart-container full-width">
                <div class="chart-header">
                    <div class="chart-title">Orçado vs Realizado (Burn Rate)</div>
                    <div class="chart-actions">
                        <button class="chart-btn" onclick="downloadChart('areaChart')"><i class="fas fa-download"></i></button>
                        <button class="chart-btn" onclick="toggleData('areaChart')"><i class="fas fa-chart-area"></i></button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="areaChart"></canvas>
                </div>
                <?php if (empty($burn_rate_data)): ?>
                    <div class="no-data">Não há dados para exibir</div>
                <?php endif; ?>
            </div>

            <!-- Gráfico 4: Performance Radar -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Performance Multidimensional</div>
                    <div class="chart-actions">
                        <button class="chart-btn" onclick="downloadChart('radarChart')"><i class="fas fa-download"></i></button>
                        <button class="chart-btn" onclick="toggleScale('radarChart')"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="radarChart"></canvas>
                </div>
                <?php if (empty($radar_data)): ?>
                    <div class="no-data">Não há dados para exibir</div>
                <?php endif; ?>
            </div>

            <!-- Gráfico 5: Top 10 Maiores Lançamentos -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Top 10 Maiores Lançamentos</div>
                    <div class="chart-actions">
                        <button class="chart-btn" onclick="downloadChart('horizontalBarChart')"><i class="fas fa-download"></i></button>
                        <button class="chart-btn" onclick="toggleOrientation('horizontalBarChart')"><i class="fas fa-exchange-alt"></i></button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="horizontalBarChart"></canvas>
                </div>
                <?php if (empty($top_lancamentos_data)): ?>
                    <div class="no-data">Não há dados para exibir</div>
                <?php endif; ?>
            </div>

            <!-- Gráfico 6: Saúde do Orçamento (Gauge) -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Saúde do Orçamento</div>
                    <div class="chart-actions">
                        <button class="chart-btn" onclick="downloadChart('gaugeChart')"><i class="fas fa-download"></i></button>
                        <button class="chart-btn" onclick="refreshGauge()"><i class="fas fa-redo"></i></button>
                    </div>
                </div>
                <div class="gauge-container">
                    <canvas id="gaugeChart"></canvas>
                    <div class="gauge-value"><?php echo number_format($saude_percent, 1); ?>%</div>
                    <div class="gauge-label">Disponível do Orçamento</div>
                    <?php
                    $status_class = '';
                    $status_text = '';
                    if ($saude_percent >= 25) {
                        $status_class = 'status-good';
                        $status_text = 'Saudável';
                    } elseif ($saude_percent >= 10) {
                        $status_class = 'status-warning';
                        $status_text = 'Atenção';
                    } else {
                        $status_class = 'status-danger';
                        $status_text = 'Crítico';
                    }
                    ?>
                    <div class="gauge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                </div>
            </div>

            <!-- Gráfico 7: Categorias Empilhadas -->
            <div class="chart-container full-width">
                <div class="chart-header">
                    <div class="chart-title">Evolução por Categoria</div>
                    <div class="chart-actions">
                        <button class="chart-btn" onclick="downloadChart('stackedBarChart')"><i class="fas fa-download"></i></button>
                        <button class="chart-btn" onclick="toggleStacked('stackedBarChart')"><i class="fas fa-layer-group"></i></button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="stackedBarChart"></canvas>
                </div>
                <?php if (empty($categorias_data)): ?>
                    <div class="no-data">Não há dados para exibir</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs para Tabelas Detalhadas -->
        <div class="tabs-container">
            <div class="tabs-header">
                <h3>Detalhamento por Centro de Custo</h3>
            </div>
            <div class="tabs-nav">
                <button class="tab-btn active" data-tab="ranking">Ranking Completo</button>
                <button class="tab-btn" data-tab="top">Top Lançamentos</button>
                <button class="tab-btn" data-tab="analise">Análise de Risco</button>
            </div>
            
            <div id="tab-ranking" class="tab-content active">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Centro de Custo</th>
                                <th>Gastos Aprovados</th>
                                <th>Lançamentos</th>
                                <th>Pendente</th>
                                <th>Cancelado/Reprovado</th>
                                <th>% do Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking_data as $row): 
                                $percent_total = $global_total_aprovado > 0 ? ($row['total_aprovado'] / $global_total_aprovado) * 100 : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td>R$ <?php echo number_format($row['total_aprovado'], 2, ',', '.'); ?></td>
                                <td><?php echo $row['total_lancamentos']; ?></td>
                                <td>R$ <?php echo number_format($row['total_pendente'], 2, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($row['total_cancelado_reprovado'], 2, ',', '.'); ?></td>
                                <td><?php echo number_format($percent_total, 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="tab-top" class="tab-content">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Lançamento</th>
                                <th>Centro de Custo</th>
                                <th>Valor</th>
                                <th>Data Pagamento</th>
                                <th>Situação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_lancamentos_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['lancamento']); ?></td>
                                <td><?php echo htmlspecialchars($row['centro_custo']); ?></td>
                                <td>R$ <?php echo number_format($row['valor'], 2, ',', '.'); ?></td>
                                <td><?php echo $row['data_pag']; ?></td>
                                <td><?php echo ucfirst($row['situacao']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="tab-analise" class="tab-content">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Centro de Custo</th>
                                <th>Quantidade</th>
                                <th>Valor Total</th>
                                <th>Risco (%)</th>
                                <th>Desvio (%)</th>
                                <th>Classificação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bubble_data as $row): 
                                $classificacao = '';
                                $cor = '';
                                if ($row['risco_percent'] > 30) {
                                    $classificacao = 'Alto Risco';
                                    $cor = 'danger';
                                } elseif ($row['risco_percent'] > 15) {
                                    $classificacao = 'Médio Risco';
                                    $cor = 'warning';
                                } else {
                                    $classificacao = 'Baixo Risco';
                                    $cor = 'success';
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nome']); ?></td>
                                <td><?php echo $row['quantidade']; ?></td>
                                <td>R$ <?php echo number_format($row['valor_total'], 2, ',', '.'); ?></td>
                                <td><?php echo number_format($row['risco_percent'], 1); ?>%</td>
                                <td><?php echo number_format($row['desvio_percent'], 1); ?>%</td>
                                <td><span class="gauge-status status-<?php echo $cor; ?>" style="padding: 3px 10px; font-size: 0.8rem;"><?php echo $classificacao; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Botão Flutuante para Exportar -->
    <div class="fab-button" onclick="exportDashboard()">
        <i class="fas fa-file-export"></i>
    </div>

    <script>
        // Dados PHP convertidos para JavaScript
        const pieData = {
            labels: <?php echo json_encode($pie_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($pie_data); ?>,
                backgroundColor: <?php echo json_encode($pie_colors); ?>,
                borderWidth: 1,
                borderColor: '#fff'
            }]
        };

        const doughnutData = {
            labels: <?php echo json_encode($doughnut_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($doughnut_data); ?>,
                backgroundColor: <?php echo json_encode($doughnut_colors); ?>,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        };

        const stackedData = {
            labels: <?php echo json_encode($stacked_labels); ?>,
            datasets: <?php echo json_encode($stacked_datasets); ?>
        };

        const areaData = {
            labels: <?php echo json_encode($burn_labels); ?>,
            datasets: [{
                label: 'Orçado',
                data: <?php echo json_encode($burn_orcado); ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }, {
                label: 'Realizado',
                data: <?php echo json_encode($burn_realizado); ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        };

        const radarData = {
            labels: ['Eficiência', 'Taxa Aprovação', '% Emergência', 'Precisão'],
            datasets: <?php echo json_encode($radar_datasets); ?>
        };

        const horizontalBarData = {
            labels: <?php echo json_encode($horizontal_labels); ?>,
            datasets: [{
                label: 'Valor (R$)',
                data: <?php echo json_encode($horizontal_data); ?>,
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 1
            }]
        };

        // Configurações dos gráficos
        const chartConfigs = {
            pieChart: {
                type: 'pie',
                data: pieData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: R$ ${value.toLocaleString('pt-BR')} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            },

            doughnutChart: {
                type: 'doughnut',
                data: doughnutData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: R$ ${value.toLocaleString('pt-BR')} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            },

            stackedBarChart: {
                type: 'bar',
                data: stackedData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: R$ ${value.toLocaleString('pt-BR')}`;
                                }
                            }
                        }
                    }
                }
            },

            areaChart: {
                type: 'line',
                data: areaData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: R$ ${value.toLocaleString('pt-BR')}`;
                                }
                            }
                        }
                    }
                }
            },

            radarChart: {
                type: 'radar',
                data: radarData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: ${value.toFixed(1)}%`;
                                }
                            }
                        }
                    }
                }
            },

            horizontalBarChart: {
                type: 'bar',
                data: horizontalBarData,
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `R$ ${context.raw.toLocaleString('pt-BR')}`;
                                }
                            }
                        }
                    }
                }
            }
        };

        // Inicializar todos os gráficos
        document.addEventListener('DOMContentLoaded', function() {
            // Criar instâncias dos gráficos
            window.charts = {};
            for (const [chartId, config] of Object.entries(chartConfigs)) {
                const canvas = document.getElementById(chartId);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    window.charts[chartId] = new Chart(ctx, config);
                }
            }

            // Criar gráfico de gauge manualmente
            createGaugeChart();
            
            // Configurar tabs
            setupTabs();
        });

        function createGaugeChart() {
            const canvas = document.getElementById('gaugeChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const value = <?php echo $saude_percent; ?>;
            
            // Configurações do gauge
            canvas.width = 300;
            canvas.height = 200;
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 1.5;
            const radius = Math.min(canvas.width, canvas.height * 1.5) / 3;
            
            // Desenhar gauge
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Arco de fundo
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, Math.PI, 2 * Math.PI, false);
            ctx.lineWidth = 20;
            ctx.strokeStyle = '#e9ecef';
            ctx.stroke();
            
            // Arco de valor
            const endAngle = Math.PI + (value / 100) * Math.PI;
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, Math.PI, endAngle, false);
            ctx.lineWidth = 20;
            
            // Cor baseada no valor
            let gaugeColor;
            if (value >= 25) {
                gaugeColor = '#28a745';
            } else if (value >= 10) {
                gaugeColor = '#ffc107';
            } else {
                gaugeColor = '#dc3545';
            }
            
            ctx.strokeStyle = gaugeColor;
            ctx.stroke();
            
            // Adicionar ponteiro
            const pointerAngle = endAngle;
            const pointerLength = radius * 0.7;
            const pointerX = centerX + Math.cos(pointerAngle) * pointerLength;
            const pointerY = centerY + Math.sin(pointerAngle) * pointerLength;
            
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.lineTo(pointerX, pointerY);
            ctx.lineWidth = 4;
            ctx.strokeStyle = '#2c3e50';
            ctx.stroke();
            
            // Adicionar ponto central
            ctx.beginPath();
            ctx.arc(centerX, centerY, 8, 0, 2 * Math.PI);
            ctx.fillStyle = '#2c3e50';
            ctx.fill();
        }

        function setupTabs() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetTab = button.getAttribute('data-tab');
                    
                    // Remover classe active de todos
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Adicionar classe active ao selecionado
                    button.classList.add('active');
                    document.getElementById(`tab-${targetTab}`).classList.add('active');
                });
            });
        }

        // Funções de utilidade
        function downloadChart(chartId) {
            const chart = window.charts[chartId];
            if (chart) {
                const link = document.createElement('a');
                link.download = `${chartId}_${new Date().toISOString().split('T')[0]}.png`;
                link.href = chart.toBase64Image();
                link.click();
            }
        }

        function toggleLegend(chartId) {
            const chart = window.charts[chartId];
            if (chart) {
                chart.options.plugins.legend.display = !chart.options.plugins.legend.display;
                chart.update();
            }
        }

        function refreshGauge() {
            createGaugeChart();
        }

        function exportDashboard() {
            // Criar um novo window para impressão
            const printWindow = window.open('', '_blank');
            const dashboardContent = document.querySelector('.dashboard-container').innerHTML;
            
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Dashboard Avançado - Exportação</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 40px; }
                        .metric-card { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px; }
                        .chart-container { page-break-inside: avoid; margin: 20px 0; }
                        h1, h2, h3 { color: #2c3e50; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Dashboard Avançado - Exportação</h1>
                    <p>Exportado em: ${new Date().toLocaleString('pt-BR')}</p>
                    ${dashboardContent}
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Esperar conteúdo carregar antes de imprimir
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }
    </script>
</body>
</html>