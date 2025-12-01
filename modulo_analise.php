<?php
// Permissões já verificadas
// $pdo e $tipo_usuario já estão disponíveis

// =======================================================
// 1. CONSULTA PARA O GRÁFICO DE LINHA (Timeline)
// =======================================================
// (Código SQL idêntico)
$sql_timeline = "SELECT 
                    DATE_FORMAT(data_pagamento, '%Y-%m') as mes_ano, 
                    SUM(CASE WHEN situacao = 'aprovado' THEN valor_realizado ELSE 0 END) as total_gasto_mes,
                    SUM(CASE WHEN situacao = 'pendente' THEN valor_orcado ELSE 0 END) as total_pendente_mes,
                    SUM(CASE WHEN situacao IN ('cancelado', 'reprovado') THEN valor_orcado ELSE 0 END) as total_cancelado_mes
                 FROM lancamentos 
                 WHERE 
                    data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY mes_ano
                 ORDER BY mes_ano ASC";
$stmt_timeline = $pdo->query($sql_timeline);
$timeline_data = $stmt_timeline->fetchAll();
// (Preparação dos dados idêntica)
$chart_labels_timeline = []; $chart_data_gasto = []; $chart_data_pendente = []; $chart_data_cancelado = [];
foreach ($timeline_data as $row) {
    $dateObj = DateTime::createFromFormat('!Y-m', $row['mes_ano']);
    $chart_labels_timeline[] = $dateObj->format('M/y');
    $chart_data_gasto[] = $row['total_gasto_mes'];
    $chart_data_pendente[] = $row['total_pendente_mes'];
    $chart_data_cancelado[] = $row['total_cancelado_mes'];
}


// =======================================================
// 2. CONSULTA PARA O RANKING (Tabela de Centros de Custo)
// =======================================================
// (Código SQL idêntico)
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

// =======================================================
// 3. CÁLCULO DOS TOTAIS GLOBAIS (Para os Cards)
// =======================================================
// (Código idêntico)
$global_total_aprovado = array_sum(array_column($ranking_data, 'total_aprovado'));
$global_total_pendente = array_sum(array_column($ranking_data, 'total_pendente'));
$global_total_cancelado = array_sum(array_column($ranking_data, 'total_cancelado_reprovado'));

// =======================================================
// 4. CÁLCULO DO ORÇAMENTO GLOBAL (Para o Card Disponível)
// =======================================================
// (Código idêntico)
$sql_orcado_global = "SELECT SUM(valor_anual) as total_anual, SUM(valor_mensal) as total_mensal FROM centro_custo";
$stmt_orcado_global = $pdo->query($sql_orcado_global);
$orcado_global = $stmt_orcado_global->fetch(PDO::FETCH_ASSOC);
$total_orcado_global_anual = $orcado_global['total_anual'] ?? 0;
$total_orcado_global_mensal = $orcado_global['total_mensal'] ?? 0;
$valor_disponivel_remanejavel = $total_orcado_global_anual - $global_total_aprovado;


// =======================================================
// 5. PREPARA DADOS PARA O GRÁFICO DE BARRAS (Ranking)
// =======================================================
// (Código idêntico)
$all_ccs_ranking = $ranking_data; 
$chart_labels_ranking = [];
$chart_data_ranking_aprovado = [];
foreach ($all_ccs_ranking as $cc) {
    if ($cc['total_aprovado'] > 0) { 
        $chart_labels_ranking[] = htmlspecialchars($cc['nome']);
        $chart_data_ranking_aprovado[] = $cc['total_aprovado'];
    }
}

// =======================================================
// 6. ✨ CONSULTAS ADICIONAIS PARA NOVOS INSIGHTS
// =======================================================

// 6.1. Total gasto (aprovado) no MÊS ATUAL (para Burn Rate)
$sql_gasto_mes_atual = "SELECT SUM(valor_realizado) FROM lancamentos 
                        WHERE situacao = 'aprovado' 
                        AND MONTH(data_pagamento) = MONTH(CURDATE()) 
                        AND YEAR(data_pagamento) = YEAR(CURDATE())";
$gasto_mes_atual = $pdo->query($sql_gasto_mes_atual)->fetchColumn() ?? 0;

// 6.2. Total gasto (aprovado) em EMERGÊNCIAS
$sql_gasto_emergencia = "SELECT SUM(valor_realizado) FROM lancamentos 
                         WHERE situacao = 'aprovado' AND emergencia = 'sim'";
$gasto_total_emergencia = $pdo->query($sql_gasto_emergencia)->fetchColumn() ?? 0;

// 6.3. Total Orçado vs. Realizado (para Precisão)
$sql_precisao = "SELECT SUM(valor_orcado) as orcado, SUM(valor_realizado) as realizado 
                 FROM lancamentos WHERE situacao = 'aprovado' AND valor_orcado > 0";
$dados_precisao = $pdo->query($sql_precisao)->fetch(PDO::FETCH_ASSOC);


// =======================================================
// 7. CONSULTA PARA AS TABELAS DE ABAS
// =======================================================
// (Código SQL idêntico)
$sql_all_lancamentos = "SELECT l.*, cc.nome AS nome_centro_custo, u.nome AS nome_usuario 
                        FROM lancamentos l
                        LEFT JOIN centro_custo cc ON l.id_centro_custo = cc.id
                        LEFT JOIN usuarios u ON l.id_usuario = u.id
                        ORDER BY l.id DESC";
$stmt_all_lancamentos = $pdo->query($sql_all_lancamentos);
$all_lancamentos = $stmt_all_lancamentos->fetchAll();
// ✨ ATUALIZADO: Usando function() anônima para compatibilidade (Garantia)
$lancamentos_aprovados = array_filter($all_lancamentos, function($lanc) { return $lanc['situacao'] == 'aprovado'; });
$lancamentos_pendentes = array_filter($all_lancamentos, function($lanc) { return $lanc['situacao'] == 'pendente'; });
$lancamentos_cancelados_reprovados = array_filter($all_lancamentos, function($lanc) { return in_array($lanc['situacao'], ['cancelado', 'reprovado']); });


// =======================================================
// 8. ✨ GERAR INSIGHTS AUTOMÁTICOS (ATUALIZADO) COM PLANOS DE AÇÃO
// =======================================================
$insights_array = [];

// Array de planos de ação correspondentes
$planos_acao = [];

// --- Regra 1: Saúde do Orçamento (Disponível) ---
if ($total_orcado_global_anual > 0) {
    $percent_disponivel = ($valor_disponivel_remanejavel / $total_orcado_global_anual) * 100;
    if ($percent_disponivel < 10) {
        $insights_array[] = [
            'tipo' => 'alert', 
            'icone' => 'fa-solid fa-fire-flame-curved', 
            'texto' => '<strong>Orçamento Crítico:</strong> Menos de 10% (' . number_format($percent_disponivel, 1) . '%) do orçamento anual está disponível. Revisão de gastos é urgente.'
        ];
        $planos_acao[] = [
            'titulo' => 'Orçamento Crítico (<10% disponível)',
            'acoes' => [
                'Congelar novas aprovações por 48h para análise emergencial',
                'Reunir com gestores de todos os centros de custo para revisão obrigatória',
                'Priorizar apenas despesas essenciais (operações críticas)',
                'Analisar possibilidades de remanejamento entre centros de custo',
                'Elaborar relatório de justificativa para possível suplementação orçamentária'
            ]
        ];
    } elseif ($percent_disponivel < 25) {
        $insights_array[] = [
            'tipo' => 'warning', 
            'icone' => 'fa-solid fa-triangle-exclamation', 
            'texto' => '<strong>Atenção ao Orçamento:</strong> Menos de 25% (' . number_format($percent_disponivel, 1) . '%) do orçamento anual está disponível. Monitore novos gastos.'
        ];
        $planos_acao[] = [
            'titulo' => 'Atenção ao Orçamento (<25% disponível)',
            'acoes' => [
                'Implementar controle adicional para aprovações acima de R$ 1.000',
                'Solicitar justificativa detalhada para novas requisições',
                'Revisar trimestralmente os gastos por centro de custo',
                'Comunicar situação aos gestores sobre a necessidade de contenção',
                'Avaliar adiamento de despesas não urgentes para o próximo ciclo'
            ]
        ];
    }
}

// --- Regra 2: Gargalo de Aprovações (Pendentes) ---
if ($global_total_aprovado > 0 && ($global_total_pendente / $global_total_aprovado) > 0.5) {
    $insights_array[] = [
        'tipo' => 'warning', 
        'icone' => 'fa-solid fa-hourglass-half', 
        'texto' => '<strong>Gargalo de Aprovação:</strong> O valor pendente (R$ ' . number_format($global_total_pendente) . ') é mais de 50% do valor já aprovado. Revise os lançamentos pendentes.'
    ];
    $planos_acao[] = [
        'titulo' => 'Gargalo de Aprovação (Pendentes > 50% dos aprovados)',
        'acoes' => [
            'Agendar reunião de fluxo com equipe de aprovações',
            'Definir SLA (Service Level Agreement) para tempos de aprovação',
            'Implementar triagem por prioridade (valor/urgência)',
            'Automatizar aprovações para valores abaixo de limite pré-definido',
            'Designar backup para aprovações durante picos'
        ]
    ];
}

// --- Regra 3: Concentração de Gasto (Top 1 CC) ---
if ($global_total_aprovado > 0 && !empty($ranking_data) && count($ranking_data) > 1) {
    $gasto_top_1_cc = $ranking_data[0]['total_aprovado'];
    $nome_top_1_cc = $ranking_data[0]['nome'];
    $percent_concentracao = ($gasto_top_1_cc / $global_total_aprovado) * 100;
    if ($percent_concentracao > 50) {
        $insights_array[] = [
            'tipo' => 'info', 
            'icone' => 'fa-solid fa-chart-pie', 
            'texto' => '<strong>Concentração de Gasto:</strong> O centro de custo "' . htmlspecialchars($nome_top_1_cc) . '" representa sozinho <strong>' . number_format($percent_concentracao, 0) . '%</strong> de todo o gasto aprovado do período.'
        ];
        $planos_acao[] = [
            'titulo' => 'Concentração de Gasto (Top 1 CC > 50%)',
            'acoes' => [
                'Auditar especificamente o centro de custo em questão',
                'Verificar se há desvios do planejamento original',
                'Analisar possibilidade de descentralização de algumas atividades',
                'Implementar controles adicionais para este centro específico',
                'Revisar alocação orçamentária para o próximo ciclo'
            ]
        ];
    }
}

// --- ✨ Regra 4: Ritmo de Gasto (Burn Rate) ---
$dia_atual = date('j');
$dias_no_mes = date('t');
$percent_tempo = ($dia_atual / $dias_no_mes) * 100;

if ($total_orcado_global_mensal > 0) {
    $percent_gasto_mensal = ($gasto_mes_atual / $total_orcado_global_mensal) * 100;
    $desvio_ritmo = $percent_gasto_mensal - $percent_tempo;

    if ($desvio_ritmo > 25) {
        $insights_array[] = [
            'tipo' => 'warning', 
            'icone' => 'fa-solid fa-gauge-high', 
            'texto' => '<strong>Ritmo Acelerado:</strong> Já se passaram ' . $dia_atual . ' dias (' . number_format($percent_tempo, 0) . '%) do mês, mas já foram consumidos ' . number_format($percent_gasto_mensal, 0) . '% do orçamento mensal. Reduza o ritmo de aprovações.'
        ];
        $planos_acao[] = [
            'titulo' => 'Ritmo Acelerado (Burn Rate > 25% acima do tempo)',
            'acoes' => [
                'Reduzir limite de aprovação temporário em 30%',
                'Revisar programação de pagamentos para os próximos 15 dias',
                'Comunicar aos departamentos sobre a necessidade de desacelerar gastos',
                'Implementar "sexta-feira sem gastos" (não essenciais)',
                'Revisar projeção para o restante do mês'
            ]
        ];
    }
}

// --- ✨ Regra 5: Custo de Emergência ---
if ($global_total_aprovado > 0) {
    $percent_emergencia = ($gasto_total_emergencia / $global_total_aprovado) * 100;
    if ($percent_emergencia > 15) {
        $insights_array[] = [
            'tipo' => 'warning', 
            'icone' => 'fa-solid fa-truck-medical', 
            'texto' => '<strong>Alto Custo de Emergência:</strong> ' . number_format($percent_emergencia, 0) . '% (R$ ' . number_format($gasto_total_emergencia) . ') de todos os gastos aprovados foram marcados como emergenciais. Revise o planejamento.'
        ];
        $planos_acao[] = [
            'titulo' => 'Alto Custo de Emergência (>15% do total)',
            'acoes' => [
                'Analisar causas-raiz das emergências recorrentes',
                'Criar reserva técnica específica para emergências',
                'Implementar processo de análise prévia para evitar "emergências evitáveis"',
                'Treinar equipes em planejamento preventivo',
                'Revisar contratos para incluir cláusulas de contingência'
            ]
        ];
    }
}

// --- ✨ Regra 6: Precisão Orçamentária ---
if (!empty($dados_precisao) && $dados_precisao['orcado'] > 0) {
    $diferenca_precisao = $dados_precisao['realizado'] - $dados_precisao['orcado'];
    $percent_diferenca = ($diferenca_precisao / $dados_precisao['orcado']) * 100;
    
    if ($percent_diferenca > 15) {
        $insights_array[] = [
            'tipo' => 'warning', 
            'icone' => 'fa-solid fa-bullseye', 
            'texto' => '<strong>Precisão Baixa (Custo):</strong> Os gastos realizados estão <strong>' . number_format($percent_diferenca, 0) . '% acima</strong> do que foi orçado. As estimativas precisam ser mais realistas.'
        ];
        $planos_acao[] = [
            'titulo' => 'Precisão Baixa (Realizado >15% acima do orçado)',
            'acoes' => [
                'Revisar metodologia de orçamentação',
                'Capacitar gestores em técnicas de estimativa',
                'Implementar histórico de precisão por centro de custo',
                'Ajustar margens de contingência baseadas em desempenho histórico',
                'Criar comitê de revisão de orçamentos antes da aprovação'
            ]
        ];
    } elseif ($percent_diferenca < -15) {
        $insights_array[] = [
            'tipo' => 'info', 
            'icone' => 'fa-solid fa-bullseye', 
            'texto' => '<strong>Precisão Baixa (Sobra):</strong> Os gastos realizados estão <strong>' . number_format(abs($percent_diferenca), 0) . '% abaixo</strong> do que foi orçado. O orçamento pode estar superestimado.'
        ];
        $planos_acao[] = [
            'titulo' => 'Precisão Baixa (Realizado >15% abaixo do orçado)',
            'acoes' => [
                'Revisar metodologia de orçamentação',
                'Capacitar gestores em técnicas de estimativa',
                'Implementar histórico de precisão por centro de custo',
                'Ajustar margens de contingência baseadas em desempenho histórico',
                'Criar comitê de revisão de orçamentos antes da aprovação'
            ]
        ];
    }
}

// --- ✨ Regra 7: Taxa de Rejeição ---
$total_lancamentos_geral = count($all_lancamentos);
$total_rejeitados = count($lancamentos_cancelados_reprovados);
if ($total_lancamentos_geral > 10) {
    $percent_rejeicao = ($total_rejeitados / $total_lancamentos_geral) * 100;
    if ($percent_rejeicao > 25) {
        $insights_array[] = [
            'tipo' => 'info', 
            'icone' => 'fa-solid fa-filter-circle-xmark', 
            'texto' => '<strong>Taxa de Rejeição Alta:</strong> ' . number_format($percent_rejeicao, 0) . '% de todas as solicitações foram canceladas ou reprovadas. Verifique o alinhamento das equipes com as regras de orçamento.'
        ];
        $planos_acao[] = [
            'titulo' => 'Taxa de Rejeição Alta (>25% das solicitações)',
            'acoes' => [
                'Analisar motivos das rejeições (categoria, valor, justificativa)',
                'Melhorar comunicação das regras orçamentárias',
                'Criar checklist pré-submissão para os solicitantes',
                'Implementar treinamento sobre políticas de gastos',
                'Revisar se regras estão claras e aplicáveis'
            ]
        ];
    }
}

// --- Regra 8: Sem dados ---
if (empty($insights_array) && empty($ranking_data) && empty($timeline_data)) {
    $insights_array[] = [
        'tipo' => 'info', 
        'icone' => 'fa-solid fa-info-circle', 
        'texto' => 'Não há dados suficientes para gerar insights. Comece a adicionar lançamentos.'
    ];
    $planos_acao[] = [
        'titulo' => 'Sistema sem Dados Suficientes',
        'acoes' => [
            'Incentivar primeiro lançamento com campanha interna',
            'Simplificar processo de inserção inicial',
            'Designar responsável por alimentação inicial',
            'Criar dados de exemplo para demonstração',
            'Oferecer suporte individualizado para primeiros usuários'
        ]
    ];
}

?>

<div class="content-header">
    <h1>Análise Gerencial</h1>
    <p style="margin-top: -10px;">Análise de tendências e performance dos Centros de Custo.</p>
</div>

<div class="content-body">
    
    <?php if (!empty($insights_array)): ?>
        <div class="insights-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3>Insights Rápidos</h3>
                <?php if (!empty($planos_acao)): ?>
                    <button type="button" class="btn btn-secondary" onclick="mostrarTodosPlanos()" style="font-size: 14px;">
                        <i class="fa-solid fa-list-check"></i> Ver Todos os Planos de Ação
                    </button>
                <?php endif; ?>
            </div>
            <?php foreach ($insights_array as $index => $insight): ?>
                <div class="insight-item insight-<?php echo $insight['tipo']; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas <?php echo $insight['icone']; ?>"></i>
                            <p style="margin: 0;"><?php echo $insight['texto']; ?></p>
                        </div>
                        <?php if (isset($planos_acao[$index])): ?>
                            <button type="button" class="btn-plano-acao" onclick="mostrarPlanoAcao(<?php echo $index; ?>)">
                                <i class="fa-solid fa-lightbulb"></i> Ver Plano de Ação
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-cards">
        <div class="card">
            <h3>Disponível (Orçado Anual - Aprovado)</h3>
            <div class="valor" style="color: #28a745;">R$ <?php echo number_format($valor_disponivel_remanejavel, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Total Aprovado (Realizado)</h3>
            <div class="valor aprovado">R$ <?php echo number_format($global_total_aprovado, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Total Pendente (Orçado)</h3>
            <div class="valor pendente">R$ <?php echo number_format($global_total_pendente, 2, ',', '.'); ?></div>
        </div>
        <div class="card">
            <h3>Total Cancelado/Reprov. (Orçado)</h3>
            <div class="valor cancelado">R$ <?php echo number_format($global_total_cancelado, 2, ',', '.'); ?></div>
        </div>
    </div>
    
    <!-- Modal para mostrar plano de ação -->
<div id="modalPlanoAcao" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-list-check"></i> Plano de Ação</h3>
            <span class="modal-close" onclick="fecharModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="planoAcaoConteudo">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="fecharModal()">Fechar</button>
            <button type="button" class="btn btn-primary" onclick="imprimirPlanoAcao()">
                <i class="fa-solid fa-print"></i> Imprimir
            </button>
        </div>
    </div>
</div>

<style>
/* Estilos para o modal */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    margin: auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.modal-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
}

.modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #ffcc00;
}

.modal-body {
    padding: 30px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    text-align: right;
}

/* Estilos para o botão de plano de ação */
.btn-plano-acao {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
    margin-left: 15px;
}

.btn-plano-acao:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Estilos para o conteúdo do plano de ação */
.plano-item {
    background: #f8f9fa;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.plano-titulo {
    color: #2c3e50;
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.plano-acoes {
    margin: 0;
    padding-left: 20px;
}

.plano-acoes li {
    margin-bottom: 10px;
    padding-left: 5px;
    color: #34495e;
    line-height: 1.5;
}

.plano-acoes li strong {
    color: #2c3e50;
}

.prioridade-alta {
    border-left-color: #e74c3c;
}

.prioridade-media {
    border-left-color: #f39c12;
}

.prioridade-baixa {
    border-left-color: #3498db;
}

.btn {
    padding: 10px 20px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
    margin-left: 10px;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}
</style>

    <div class="chart-wrapper" style="margin-bottom: 2rem;">
        <h3>Acompanhamento de Status (Últimos 12 Meses)</h3>
        <div style="width: 100%;">
            <canvas id="lineChartTimeline"></canvas>
        </div>
    </div>
    
    <div class="chart-wrapper" style="margin-bottom: 2rem;">
        <h3>Gastos Aprovados por Centro de Custo</h3>
        <div style="width: 100%;">
            <canvas id="barChartRanking"></canvas>
        </div>
    </div>

    <div class="table-wrapper" style="margin-bottom: 2rem;">
        <h3>Performance por Centro de Custo (Completo)</h3>
        <table style="margin-top: 1.5rem;">
            <thead>
                <tr>
                    <th>Centro de Custo</th>
                    <th>Gastos Aprovados (R$)</th>
                    <th>Lançamentos (Qtd)</th>
                    <th>Valor Pendente (R$)</th>
                    <th>Valor Cancelado/Reprov. (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking_data as $row): ?>
                    <tr>
                        <td data-label="Centro de Custo"><?php echo htmlspecialchars($row['nome']); ?></td>
                        <td data-label="Gastos Aprovados" style="font-weight: bold;"><?php echo "R$ " . number_format($row['total_aprovado'], 2, ',', '.'); ?></td>
                        <td data-label="Lançamentos (Qtd)"><?php echo $row['total_lancamentos']; ?></td>
                        <td data-label="Valor Pendente"><?php echo "R$ " . number_format($row['total_pendente'], 2, ',', '.'); ?></td>
                        <td data-label="Valor Cancelado"><?php echo "R$ " . number_format($row['total_cancelado_reprovado'], 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="tabs-container">
        <h3>Lista Detalhada de Lançamentos</h3>
        <nav class="tab-nav">
            <button class="tab-btn active" data-tab="aprovados">Aprovados (<?php echo count($lancamentos_aprovados); ?>)</button>
            <button class="tab-btn" data-tab="pendentes">Pendentes (<?php echo count($lancamentos_pendentes); ?>)</button>
            <button class="tab-btn" data-tab="cancelados">Cancelados/Reprov. (<?php echo count($lancamentos_cancelados_reprovados); ?>)</button>
        </nav>
        
        <div id="tab-aprovados" class="tab-content active">
           <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Lançamento</th><th>Centro Custo</th><th>Usuário</th><th>Valor Realizado (R$)</th><th>Valor Orçado (R$)</th></th><th>Data Pag.</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lancamentos_aprovados as $lanc): ?>
                        <tr>
                            <td data-label="Lançamento"><?php echo htmlspecialchars($lanc['lancamento']); ?></td>
                            <td data-label="Centro Custo"><?php echo htmlspecialchars($lanc['nome_centro_custo']); ?></td>
                            <td data-label="Usuário"><?php echo htmlspecialchars($lanc['nome_usuario']); ?></td>
                            <td data-label="Valor Realizado"><?php echo number_format($lanc['valor_realizado'], 2, ',', '.'); ?></td>
                            <td data-label="Valor Orçado"><?php echo number_format($lanc['valor_orcado'], 2, ',', '.'); ?></td>
                            <td data-label="Data Pag."><?php echo $lanc['data_pagamento'] ? date('d/m/Y', strtotime($lanc['data_pagamento'])) : 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lancamentos_aprovados)) echo "<tr><td colspan='5'>Nenhum lançamento aprovado.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="tab-pendentes" class="tab-content">
           <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Lançamento</th><th>Centro Custo</th><th>Usuário</th><th>Valor Orçado (R$)</th><th>Programado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lancamentos_pendentes as $lanc): ?>
                        <tr>
                            <td data-label="Lançamento"><?php echo htmlspecialchars($lanc['lancamento']); ?></td>
                            <td data-label="Centro Custo"><?php echo htmlspecialchars($lanc['nome_centro_custo']); ?></td>
                            <td data-label="Usuário"><?php echo htmlspecialchars($lanc['nome_usuario']); ?></td>
                            <td data-label="Valor Orçado"><?php echo number_format($lanc['valor_orcado'], 2, ',', '.'); ?></td>
                            <td data-label="Programado"><?php echo ucfirst($lanc['programado']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lancamentos_pendentes)) echo "<tr><td colspan='5'>Nenhum lançamento pendente.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="tab-cancelados" class="tab-content">
           <div class="table-wrapper">
                 <table>
                    <thead>
                        <tr><th>Lançamento</th><th>Centro Custo</th><th>Usuário</th><th>Valor Orçado (R$)</th><th>Situação</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lancamentos_cancelados_reprovados as $lanc): ?>
                        <tr>
                            <td data-label="Lançamento"><?php echo htmlspecialchars($lanc['lancamento']); ?></td>
                            <td data-label="Centro Custo"><?php echo htmlspecialchars($lanc['nome_centro_custo']); ?></td>
                            <td data-label="Usuário"><?php echo htmlspecialchars($lanc['nome_usuario']); ?></td>
                            <td data-label="Valor Orçado"><?php echo number_format($lanc['valor_orcado'], 2, ',', '.'); ?></td>
                            <td data-label="Situação"><?php echo ucfirst($lanc['situacao']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lancamentos_cancelados_reprovados)) echo "<tr><td colspan='5'>Nenhum lançamento cancelado ou reprovado.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
</div>

<script>

    // Armazenar planos de ação em JavaScript
const planosAcao = <?php echo json_encode($planos_acao); ?>;

// Função para mostrar plano de ação específico
function mostrarPlanoAcao(index) {
    const plano = planosAcao[index];
    if (!plano) return;
    
    let prioridadeClass = 'prioridade-media';
    if (plano.titulo.includes('Crítico') || plano.titulo.includes('Acelerado') || plano.titulo.includes('Urgente')) {
        prioridadeClass = 'prioridade-alta';
    } else if (plano.titulo.includes('Info') || plano.titulo.includes('Sistema')) {
        prioridadeClass = 'prioridade-baixa';
    }
    
    const conteudo = `
        <div class="plano-item ${prioridadeClass}">
            <h4 class="plano-titulo">
                <i class="fa-solid fa-bullseye"></i> ${plano.titulo}
            </h4>
            <p><strong>Ações Recomendadas:</strong></p>
            <ol class="plano-acoes">
                ${plano.acoes.map((acao, i) => 
                    `<li><strong>${i + 1}.</strong> ${acao}</li>`
                ).join('')}
            </ol>
            <div style="margin-top: 20px; padding: 15px; background: #e8f4fd; border-radius: 5px;">
                <p><strong>Próximos Passos:</strong></p>
                <p>1. Designar responsável para cada ação<br>
                2. Definir prazos de implementação<br>
                3. Agendar reunião de acompanhamento em 7 dias<br>
                4. Documentar resultados e ajustes necessários</p>
            </div>
        </div>
    `;
    
    document.getElementById('planoAcaoConteudo').innerHTML = conteudo;
    document.getElementById('modalPlanoAcao').style.display = 'flex';
    
    // Adicionar listener para fechar com ESC
    document.addEventListener('keydown', fecharComESC);
}

// Função para mostrar todos os planos de ação
function mostrarTodosPlanos() {
    let conteudo = '<div style="margin-bottom: 20px;">';
    conteudo += '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px; border-radius: 8px; color: white;">';
    conteudo += '<i class="fa-solid fa-list-check fa-2x"></i>';
    conteudo += '<div><h3 style="margin: 0; color: white;">Plano de Ação Completo</h3>';
    conteudo += '<p style="margin: 5px 0 0 0; opacity: 0.9;">Priorize as ações por nível de criticidade</p></div>';
    conteudo += '</div>';
    
    planosAcao.forEach((plano, index) => {
        let prioridadeClass = 'prioridade-media';
        let prioridadeText = 'Média';
        let prioridadeIcon = 'fa-solid fa-circle-exclamation';
        
        if (plano.titulo.includes('Crítico') || plano.titulo.includes('Acelerado') || plano.titulo.includes('Urgente')) {
            prioridadeClass = 'prioridade-alta';
            prioridadeText = 'Alta';
            prioridadeIcon = 'fa-solid fa-circle-exclamation';
        } else if (plano.titulo.includes('Info') || plano.titulo.includes('Sistema')) {
            prioridadeClass = 'prioridade-baixa';
            prioridadeText = 'Baixa';
            prioridadeIcon = 'fa-solid fa-circle-info';
        }
        
        conteudo += `
            <div class="plano-item ${prioridadeClass}" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <h4 class="plano-titulo" style="margin: 0;">
                        <i class="${prioridadeIcon}"></i> ${plano.titulo}
                    </h4>
                    <span style="background: #2c3e50; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold;">
                        <i class="fa-solid fa-flag"></i> Prioridade ${prioridadeText}
                    </span>
                </div>
                <ol class="plano-acoes" style="margin-top: 15px;">
                    ${plano.acoes.map((acao, i) => 
                        `<li><strong>${i + 1}.</strong> ${acao}</li>`
                    ).join('')}
                </ol>
            </div>
        `;
    });
    
    // Adicionar seção de implementação
    conteudo += `
        <div style="background: #f0f7ff; padding: 20px; border-radius: 8px; margin-top: 30px; border: 2px dashed #4a90e2;">
            <h4 style="margin-top: 0; color: #2c3e50;">
                <i class="fa-solid fa-gears"></i> Processo de Implementação Recomendado
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="background: white; padding: 15px; border-radius: 5px;">
                    <h5 style="margin: 0 0 10px 0; color: #e74c3c;"><i class="fa-solid fa-arrow-up-wide-short"></i> Priorização</h5>
                    <p>Classificar por criticidade:<br>Vermelho > Amarelo > Verde</p>
                </div>
                <div style="background: white; padding: 15px; border-radius: 5px;">
                    <h5 style="margin: 0 0 10px 0; color: #3498db;"><i class="fa-solid fa-user-tie"></i> Responsabilidade</h5>
                    <p>Designar dono para cada plano de ação</p>
                </div>
                <div style="background: white; padding: 15px; border-radius: 5px;">
                    <h5 style="margin: 0 0 10px 0; color: #2ecc71;"><i class="fa-solid fa-calendar-check"></i> Prazos</h5>
                    <p>Estabelecer cronograma realista</p>
                </div>
                <div style="background: white; padding: 15px; border-radius: 5px;">
                    <h5 style="margin: 0 0 10px 0; color: #9b59b6;"><i class="fa-solid fa-chart-line"></i> Monitoramento</h5>
                    <p>Acompanhar indicadores de melhoria</p>
                </div>
            </div>
            <p style="margin-top: 15px; font-style: italic; color: #7f8c8d;">
                <i class="fa-solid fa-clock"></i> Frequência sugerida: Revisar insights e planos de ação semanalmente
            </p>
        </div>
    `;
    
    conteudo += '</div>';
    document.getElementById('planoAcaoConteudo').innerHTML = conteudo;
    document.getElementById('modalPlanoAcao').style.display = 'flex';
    
    // Adicionar listener para fechar com ESC
    document.addEventListener('keydown', fecharComESC);
}

// Função para fechar modal
function fecharModal() {
    document.getElementById('modalPlanoAcao').style.display = 'none';
    document.removeEventListener('keydown', fecharComESC);
}

// Função para fechar com tecla ESC
function fecharComESC(event) {
    if (event.key === 'Escape') {
        fecharModal();
    }
}

// Função para imprimir plano de ação
function imprimirPlanoAcao() {
    const modalContent = document.querySelector('.modal-content').cloneNode(true);
    const printWindow = window.open('', '_blank');
    
    // Remover botões do conteúdo de impressão
    const footer = modalContent.querySelector('.modal-footer');
    if (footer) footer.remove();
    
    // Adicionar data de impressão
    const data = new Date();
    const dataFormatada = data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR');
    
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Plano de Ação - Análise Gerencial</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                h1 { color: #2c3e50; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
                .plano-item { margin-bottom: 20px; padding: 15px; border-left: 4px solid #667eea; background: #f8f9fa; }
                .plano-acoes li { margin-bottom: 8px; }
                .header-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .prioridade-alta { border-left-color: #e74c3c; }
                .prioridade-media { border-left-color: #f39c12; }
                .prioridade-baixa { border-left-color: #3498db; }
                @media print {
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header-info">
                <div>
                    <h1>Plano de Ação - Análise Gerencial</h1>
                    <p>Gerado em: ${dataFormatada}</p>
                </div>

            </div>
            ${modalContent.innerHTML}
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
                <p>Documento gerado automaticamente pelo Sistema de Análise Gerencial</p>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    
    // Esperar conteúdo carregar antes de imprimir
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Fechar modal ao clicar fora do conteúdo
window.onclick = function(event) {
    const modal = document.getElementById('modalPlanoAcao');
    if (event.target === modal) {
        fecharModal();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    
    // --- 1. GRÁFICO DE LINHA (Timeline) ---
    const labelsTimeline = <?php echo json_encode($chart_labels_timeline ?? []); ?>;
    const dataGasto = <?php echo json_encode($chart_data_gasto ?? []); ?>;
    const dataPendente = <?php echo json_encode($chart_data_pendente ?? []); ?>;
    const dataCancelado = <?php echo json_encode($chart_data_cancelado ?? []); ?>;
    const ctxLine = document.getElementById('lineChartTimeline');
    
    if (ctxLine) {
        new Chart(ctxLine.getContext('2d'), {
            type: 'line',
            data: {
                labels: labelsTimeline,
                datasets: [
                {
                    label: 'Aprovado (Realizado)',
                    data: dataGasto,
                    borderColor: 'rgba(0, 123, 255, 1)',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    fill: true,
                    tension: 0.1
                },
                {
                    label: 'Pendente (Orçado)',
                    data: dataPendente,
                    borderColor: 'rgba(255, 193, 7, 1)',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    fill: false,
                    tension: 0.1
                },
                {
                    label: 'Cancelado/Reprov. (Orçado)',
                    data: dataCancelado,
                    borderColor: 'rgba(220, 53, 69, 1)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    fill: false,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true, position: 'top', },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.raw || 0;
                                return label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value, index, values) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }

    // --- 2. GRÁFICO DE COLUNAS (Ranking) ---
    const labelsRanking = <?php echo json_encode($chart_labels_ranking ?? []); ?>;
    const dataRanking = <?php echo json_encode($chart_data_ranking_aprovado ?? []); ?>;
    const ctxBar = document.getElementById('barChartRanking');
    
    if (ctxBar) {
        new Chart(ctxBar.getContext('2d'), {
            type: 'bar', // Gráfico "em pé" (colunas)
            data: {
                labels: labelsRanking,
                datasets: [{
                    label: 'Gasto Aprovado (R$)',
                    data: dataRanking,
                    backgroundColor: 'rgba(0, 123, 255, 0.7)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' Gasto Aprovado: R$ ' + context.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    x: { },
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
    }

    // --- 3. SCRIPT PARA AS ABAS ---
 const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetTab = button.getAttribute('data-tab');
            
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            button.classList.add('active');
            const targetContent = document.getElementById('tab-' + targetTab);
            if(targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
});
</script>