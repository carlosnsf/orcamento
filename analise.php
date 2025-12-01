<?php
// Inicia a sessão (boa prática)
session_start();

// --- 1. CONEXÃO COM O BANCO DE DADOS ---
// Inclui seu arquivo de conexão, que agora usa PDO e cria a variável $pdo
require_once 'conexaoBancoFinanceiro.php';

// O arquivo 'conexaoBanco.php' (que você forneceu) já lida com erros de conexão
// usando try/catch e PDOException.
// Também já define o charset (no DSN) e o modo FETCH_ASSOC (nas opções).

// --- 2. CONSULTAS SQL PARA O DASHBOARD ---

// Inicializa as variáveis de dados para evitar erros no JavaScript caso uma consulta falhe
$kpi_data = [
    'total_orcado' => 0,
    'total_realizado' => 0,
    'total_diferenca' => 0,
    'total_emergencia' => 0
];
$data_centro_custo = [];
$data_situacao = [];
$data_evolucao = [];

// Usamos um try/catch para todas as consultas, pois o PDO está em modo EXCEPTION
try {

    // --- CONSULTA 1: KPIs (Cards Principais) ---
    // Usamos a coluna 'diferenca' que já é calculada (STORED GENERATED)
    $sql_kpi = "SELECT 
                    SUM(valor_orcado) as total_orcado, 
                    SUM(valor_realizado) as total_realizado,
                    SUM(diferenca) as total_diferenca,
                    SUM(CASE WHEN emergencia = 'sim' THEN valor_emergencia ELSE 0 END) as total_emergencia
                FROM lancamentos 
                WHERE situacao = 'aprovado'"; // Apenas soma o que foi aprovado

    $stmt_kpi = $pdo->query($sql_kpi);
    // fetch() usa FETCH_ASSOC por padrão (definido no conexaoBanco.php)
    $fetched_kpi = $stmt_kpi->fetch(); 
    if ($fetched_kpi) {
        // Combina os resultados com os padrões para garantir que todas as chaves existam
        // e que valores NULL do SUM() se tornem 0.
        $kpi_data = array_merge($kpi_data, array_filter($fetched_kpi, fn($v) => $v !== null));
    }


    // --- CONSULTA 2: Gastos por Centro de Custo (Top 5) ---
    // !!! IMPORTANTE: Esta consulta assume que você tem uma tabela 'centros_custo' com 'id' e 'nome'.
    $sql_centro_custo = "SELECT 
                            cc.nome as label,  -- 'nome' da tabela 'centros_custo'
                            SUM(l.valor_realizado) as valor
                        FROM lancamentos l
                        LEFT JOIN centro_custo cc ON l.id_centro_custo = cc.id
                        WHERE l.situacao = 'aprovado'
                        GROUP BY cc.nome
                        ORDER BY valor DESC
                        LIMIT 5";

    $stmt_centro_custo = $pdo->query($sql_centro_custo);
    // Loop direto, fetch() retorna false no final
    while($row = $stmt_centro_custo->fetch()) {
        $data_centro_custo[] = $row;
    }

    // --- CONSULTA 3: Lançamentos por Situação ---
    $sql_situacao = "SELECT 
                        situacao as label, 
                        COUNT(*) as valor
                     FROM lancamentos
                     GROUP BY situacao";

    $stmt_situacao = $pdo->query($sql_situacao);
    while($row = $stmt_situacao->fetch()) {
        $data_situacao[] = $row;
    }

    // --- CONSULTA 4: Evolução dos Gastos (Últimos 6 meses) ---
    $sql_evolucao = "SELECT 
                        DATE_FORMAT(data_pagamento, '%Y-%m') as mes_ano,
                        SUM(valor_realizado) as valor
                     FROM lancamentos
                     WHERE situacao = 'aprovado' AND data_pagamento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                     GROUP BY mes_ano
                     ORDER BY mes_ano ASC";

    $stmt_evolucao = $pdo->query($sql_evolucao);
    while($row = $stmt_evolucao->fetch()) {
        $data_evolucao[] = $row;
    }

} catch (\PDOException $e) {
    // Em caso de erro em QUALQUER consulta, exibe uma mensagem amigável.
    // Os arrays de dados ficarão vazios, e os gráficos mostrarão "Nenhum dado encontrado".
    // Em produção, você deve logar este erro ($e->getMessage()) em vez de exibi-lo.
    die("<h1>Erro ao consultar o banco de dados.</h1><p>Por favor, tente novamente mais tarde.</p><!-- Erro: " . htmlspecialchars($e->getMessage()) . " -->");
}

// A conexão PDO será fechada automaticamente no fim do script.
// $pdo = null; // Descomente se quiser fechar explicitamente

// --- 3. PREPARA OS DADOS PARA O JAVASCRIPT ---
// Converte os arrays PHP em strings JSON seguras para o JS
$json_kpi_data = json_encode($kpi_data);
$json_centro_custo = json_encode($data_centro_custo);
$json_situacao = json_encode($data_situacao);
$json_evolucao = json_encode($data_evolucao);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Relatórios Financeiros</title>
    
    <!-- 1. Carregando o Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- 2. Carregando a biblioteca de gráficos (Chart.js) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- 3. Carregando a biblioteca de ícones (Lucide) -->
    <script src="https://unpkg.com/lucide-react@latest/dist/umd/lucide.min.js"></script>

    <!-- Configuração da fonte Inter -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6; /* bg-gray-100 */
        }
    </style>
</head>
<body class="bg-gray-100">

    <div class="container mx-auto p-4 md:p-8">
        
        <!-- Cabeçalho -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Dashboard de Relatórios</h1>
            <p class="text-gray-500">Análise da tabela 'lancamentos'</p>
        </header>

        <!-- Seção de KPIs (Cards) -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4">
                <div class="p-3 bg-blue-100 rounded-full">
                    <i data-lucide="target" class="text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Orçado (Aprovado)</p>
                    <p class="text-2xl font-bold text-gray-800" id="kpi-orcado">Carregando...</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4">
                <div class="p-3 bg-green-100 rounded-full">
                    <i data-lucide="check-circle" class="text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Realizado (Aprovado)</p>
                    <p class="text-2xl font-bold text-gray-800" id="kpi-realizado">Carregando...</p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4">
                <div class="p-3 bg-yellow-100 rounded-full">
                    <i data-lucide="wallet" class="text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Saldo (Diferença)</p>
                    <p class="text-2xl font-bold text-gray-800" id="kpi-diferenca">Carregando...</p>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-lg flex items-center space-x-4">
                <div class="p-3 bg-red-100 rounded-full">
                    <i data-lucide="alert-triangle" class="text-red-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Gastos Emergenciais</p>
                    <p class="text-2xl font-bold text-red-600" id="kpi-emergencia">Carregando...</p>
                </div>
            </div>

        </section>

        <!-- Seção de Gráficos -->
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Top 5 Centros de Custo</h2>
                <div class="w-full h-80 flex justify-center items-center">
                    <canvas id="centroCustoChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Lançamentos por Situação</h2>
                <div class="w-full h-80 flex justify-center items-center">
                    <canvas id="situacaoChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg lg:col-span-2">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Evolução dos Gastos (Últimos 6 Meses)</h2>
                <div class="w-full h-80">
                    <canvas id="evolucaoChart"></canvas>
                </div>
            </div>

        </section>

    </div>

    <script>
        // --- 4. INJETANDO OS DADOS DO PHP NO JAVASCRIPT ---
        // Aqui o PHP "imprime" os dados que buscamos do banco
        const kpiData = <?php echo $json_kpi_data; ?>;
        const dataCentroCusto = <?php echo $json_centro_custo; ?>;
        const dataSituacao = <?php echo $json_situacao; ?>;
        const dataEvolucao = <?php echo $json_evolucao; ?>;
        // --- FIM DA INJEÇÃO DE DADOS ---


        // Função para formatar números como moeda (R$)
        const formatCurrency = (value) => {
            // Converte o valor para número, caso venha como string do PHP
            const numberValue = parseFloat(value) || 0;
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(numberValue);
        };
        
        // Função para capitalizar a primeira letra
        const capitalize = (s) => {
            if (typeof s !== 'string' || !s) return '';
            return s.charAt(0).toUpperCase() + s.slice(1);
        }

        // Espera o DOM carregar para criar os gráficos
        document.addEventListener('DOMContentLoaded', () => {

            // Ativa os ícones do Lucide
            lucide.createIcons();

            // 1. Atualiza os KPIs (Cards)
            document.getElementById('kpi-orcado').textContent = formatCurrency(kpiData.total_orcado);
            document.getElementById('kpi-realizado').textContent = formatCurrency(kpiData.total_realizado);
            document.getElementById('kpi-diferenca').textContent = formatCurrency(kpiData.total_diferenca);
            
            // Muda a cor do saldo se for negativo
            const kpiDiferencaEl = document.getElementById('kpi-diferenca');
            if (parseFloat(kpiData.total_diferenca) < 0) {
                kpiDiferencaEl.classList.remove('text-gray-800');
                kpiDiferencaEl.classList.add('text-red-600');
            } else {
                kpiDiferencaEl.classList.remove('text-gray-800');
                kpiDiferencaEl.classList.add('text-green-600');
            }

            document.getElementById('kpi-emergencia').textContent = formatCurrency(kpiData.total_emergencia);


            // 2. Gráfico de Centro de Custo (Donut)
            const ctxCentroCusto = document.getElementById('centroCustoChart').getContext('2d');
            if (dataCentroCusto.length > 0) {
                new Chart(ctxCentroCusto, {
                    type: 'doughnut',
                    data: {
                        labels: dataCentroCusto.map(d => d.label || 'Não categorizado'), // Adiciona um fallback
                        datasets: [{
                            label: 'Valor Gasto',
                            data: dataCentroCusto.map(d => d.valor),
                            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#6366f1'],
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: (context) => `${context.label}: ${formatCurrency(context.raw)}`
                                }
                            }
                        }
                    }
                });
            } else {
                document.getElementById('centroCustoChart').parentElement.innerHTML = '<p class="text-gray-500 text-center">Nenhum dado encontrado para Centros de Custo.</p>';
            }

            // 3. Gráfico de Situação (Pie)
            const ctxSituacao = document.getElementById('situacaoChart').getContext('2d');
            const situacaoLabels = dataSituacao.map(d => d.label);
            const situacaoColors = situacaoLabels.map(label => {
                const colorMap = {
                    'aprovado': '#22c55e',
                    'pendente': '#f59e0b',
                    'cancelado': '#6b7280',
                    'reprovado': '#ef4444'
                };
                return colorMap[label] || '#9ca3af'; // Cor padrão
            });

            if (dataSituacao.length > 0) {
                new Chart(ctxSituacao, {
                    type: 'pie',
                    data: {
                        labels: situacaoLabels.map(d => capitalize(d)),
                        datasets: [{
                            label: 'Nº de Lançamentos',
                            data: dataSituacao.map(d => d.valor),
                            backgroundColor: situacaoColors,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            } else {
                 document.getElementById('situacaoChart').parentElement.innerHTML = '<p class="text-gray-500 text-center">Nenhum dado encontrado para Situação.</p>';
            }

            // 4. Gráfico de Evolução (Line)
            const ctxEvolucao = document.getElementById('evolucaoChart').getContext('2d');
            if (dataEvolucao.length > 0) {
                new Chart(ctxEvolucao, {
                    type: 'line',
                    data: {
                        labels: dataEvolucao.map(d => {
                            const [year, month] = d.mes_ano.split('-');
                            return new Date(year, month - 1).toLocaleString('pt-BR', { month: 'short', year: '2-digit' });
                        }),
                        datasets: [{
                            label: 'Valor Realizado',
                            data: dataEvolucao.map(d => d.valor),
                            borderColor: '#3b82f6',
                            backgroundColor: '#3b82f633',
                            fill: true,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: (context) => `Total Gasto: ${formatCurrency(context.raw)}`
                                }
                            }
                        },
                        scales: {
                            y: { ticks: { callback: (value) => formatCurrency(value) } }
                        }
                    }
                });
            } else {
                 document.getElementById('evolucaoChart').parentElement.innerHTML = '<p class="text-gray-500 text-center">Nenhum dado encontrado para a Evolução de Gastos.</p>';
            }

        });
    </script>

</body>
</html>