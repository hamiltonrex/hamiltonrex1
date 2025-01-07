<?php
include('auth.php');
include('config.php');

// Função para calcular a data de quinta-feira da semana atual
function get_quinta_feria() {
    $data = new DateTime();
    $data->modify('last thursday');
    return $data->format('d');  // Dia do mês de quinta-feira
}

// Função para calcular a data de quarta-feira da semana atual
function get_quarta_feria() {
    $data = new DateTime();
    $data->modify('next wednesday');
    return $data->format('d');  // Dia do mês de quarta-feira
}

// Obtém o número total de listas
$stmt_listas = $conn->query("SELECT COUNT(*) as total_listas FROM vagas");
$total_listas = $stmt_listas->fetch_assoc()['total_listas'];

// Obtém o número total de colaboradores
$stmt_colaboradores = $conn->query("SELECT COUNT(*) as total_colaboradores FROM colaboradores");
$total_colaboradores = $stmt_colaboradores->fetch_assoc()['total_colaboradores'];

// Obtém o número de colaboradores ativos (trabalharam no último mês)
$stmt_ativos = $conn->query("SELECT COUNT(DISTINCT colaborador_id) as total_ativos FROM presencas WHERE data_presenca >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) AND presente = 1");
$total_ativos = $stmt_ativos->fetch_assoc()['total_ativos'];

// Calcula o número de colaboradores não ativos
$total_nao_ativos = $total_colaboradores - $total_ativos;

// Calcula a receita total por vaga
$stmt_receita = $conn->query("SELECT v.id, v.data_vaga, v.valor_diaria, COUNT(c.colaborador_id) as total_colaboradores, (v.valor_diaria * COUNT(c.colaborador_id)) as receita_total FROM vagas v LEFT JOIN candidaturas c ON v.id = c.vaga_id GROUP BY v.id");
$receita_por_vaga = $stmt_receita->fetch_all(MYSQLI_ASSOC);

// Top 5 Colaboradores da Semana (De Quinta a Quarta)
$stmt_top_colaboradores = $conn->query("SELECT colaborador_id, COUNT(vaga_id) AS total_cadastros
FROM candidaturas
WHERE WEEK(data_candidatura, 5) = WEEK(CURDATE(), 5)  -- Semana começa na quinta-feira
AND YEARWEEK(data_candidatura, 5) = YEARWEEK(CURDATE(), 5)  -- Ano e semana também devem coincidir
GROUP BY colaborador_id
ORDER BY total_cadastros DESC
LIMIT 5");
$top_colaboradores = $stmt_top_colaboradores->fetch_all(MYSQLI_ASSOC);

// Top 5 Colaboradores com Mais Presença (De Quinta a Quarta)
$stmt_mais_presenca = $conn->query("SELECT colaborador_id, COUNT(id) AS total_presencas
FROM presencas
WHERE presente = 1
AND WEEK(data_presenca, 5) = WEEK(CURDATE(), 5)  -- Semana começa na quinta-feira
AND YEARWEEK(data_presenca, 5) = YEARWEEK(CURDATE(), 5)  -- Ano e semana também devem coincidir
GROUP BY colaborador_id
ORDER BY total_presencas DESC
LIMIT 5");
$mais_presenca = $stmt_mais_presenca->fetch_all(MYSQLI_ASSOC);

// Calculando as datas de Quinta a Quarta para exibir no frontend
$quinta_feria = get_quinta_feria();
$quarta_feria = get_quarta_feria();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-BY2YHM4D63"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-BY2YHM4D63');
    </script>
    <script id="usercentrics-cmp" src="https://app.usercentrics.eu/browser-ui/latest/loader.js" data-settings-id="OK2w1TmQWJ4hYK" async></script>
    <meta charset="UTF-8">
    <title>Painel Administrativo</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h1>Bem-vindo, <?php echo $_SESSION['username']; ?>!</h1>

            <div>
                <h2>Estatísticas Gerais</h2>
                <p>Total de listas: <?php echo $total_listas; ?></p>
                <p>Total de colaboradores: <?php echo $total_colaboradores; ?></p>
                <p>Colaboradores ativos (último mês): <?php echo $total_ativos; ?></p>
                <p>Colaboradores não ativos (último mês): <?php echo $total_nao_ativos; ?></p>
            </div>

            <!-- Exibindo o Top 5 Colaboradores da Semana (Quinta a Quarta) -->
            <div>
                <h2>Top 5 Colaboradores da Semana</h2>
                <p><small>Contabilizando de Qui a Qua: <?php echo $quinta_feria; ?> a <?php echo $quarta_feria; ?></small></p>
                <?php if ($top_colaboradores): ?>
                    <ol>
                    <?php foreach ($top_colaboradores as $colaborador): ?>
                        <?php
                        $stmt_nome_colaborador = $conn->query("SELECT nome FROM colaboradores WHERE id = " . $colaborador['colaborador_id']);
                        $colaborador_nome = $stmt_nome_colaborador->fetch_assoc()['nome'];
                        ?>
                        <li><?php echo $colaborador_nome . " - " . $colaborador['total_cadastros'] . " cadastro(s)"; ?></li>
                    <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p>Nenhum colaborador se cadastrou essa semana.</p>
                <?php endif; ?>
            </div>

            <!-- Exibindo o Top 5 Colaboradores com Mais Presença (Quinta a Quarta) -->
            <div>
                <h2>Top 5 Colaboradores com Mais Presença</h2>
                <p><small>Contabilizando de Qui a Qua: <?php echo $quinta_feria; ?> a <?php echo $quarta_feria; ?></small></p>
                <?php if ($mais_presenca): ?>
                    <ol>
                    <?php foreach ($mais_presenca as $presenca): ?>
                        <?php
                        $stmt_nome_colaborador_presenca = $conn->query("SELECT nome FROM colaboradores WHERE id = " . $presenca['colaborador_id']);
                        $colaborador_nome_presenca = $stmt_nome_colaborador_presenca->fetch_assoc()['nome'];
                        ?>
                        <li><?php echo $colaborador_nome_presenca . " - " . $presenca['total_presencas'] . " presença(s)"; ?></li>
                    <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p>Não há registros de presença para exibir.</p>
                <?php endif; ?>
            </div>

            <div>
                <h2>Gráficos</h2>
                <canvas id="graficoPresenca"></canvas>
                <canvas id="graficoReceita"></canvas>
            </div>
        </main>
    </div>

    <script>
        // Dados para o gráfico de frequência de presença
        const ctxPresenca = document.getElementById('graficoPresenca').getContext('2d');
        const graficoPresenca = new Chart(ctxPresenca, {
            type: 'bar',
            data: {
                labels: ['Ativos', 'Não Ativos'],
                datasets: [{
                    label: 'Frequência de Presença',
                    data: [<?php echo $total_ativos; ?>, <?php echo $total_nao_ativos; ?>],
                    backgroundColor: ['#03DAC6', '#CF6679']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Dados para o gráfico de receita por vaga
        const ctxReceita = document.getElementById('graficoReceita').getContext('2d');
        const labelsReceita = [
            <?php foreach ($receita_por_vaga as $vaga) { echo "'Vaga ID " . $vaga['id'] . "',"; } ?>
        ];
        const dataReceita = [
            <?php foreach ($receita_por_vaga as $vaga) { echo $vaga['receita_total'] . ","; } ?>
        ];
        const graficoReceita = new Chart(ctxReceita, {
            type: 'bar',
            data: {
                labels: labelsReceita,
                datasets: [{
                    label: 'Receita por Vaga',
                    data: dataReceita,
                    backgroundColor: '#BB86FC'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>


