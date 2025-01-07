<?php
include('config.php');

// Obter a data selecionada no calendário ou usar a data atual por padrão
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

// Consulta para obter todas as vagas com informações da empresa e data/hora para a data selecionada
$sql_vagas = "
    SELECT vagas.id AS vaga_id, vagas.data_vaga, empresas.nome_empresa 
    FROM vagas 
    INNER JOIN empresas ON vagas.empresa_id = empresas.id
    WHERE DATE(vagas.data_vaga) = ?
    ORDER BY vagas.data_vaga ASC
";
$stmt_vagas = $conn->prepare($sql_vagas);
$stmt_vagas->bind_param("s", $data_selecionada);
$stmt_vagas->execute();
$vagas_result = $stmt_vagas->get_result();

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programção dos Pontos de Encontro</title>
    <link rel="stylesheet" href="css/rota.css"> <!-- Seu CSS personalizado -->
</head>
<body>
    <div class="content-container">
        <h1>Programção dos Pontos de Encontro</h1>

        <!-- Formulário para selecionar a data -->
        <form method="GET" action="rota.php">
            <label for="data">Selecionar Data:</label>
            <input type="date" id="data" name="data" value="<?php echo htmlspecialchars($data_selecionada); ?>">
            <button type="submit">Buscar</button>
        </form>

        <?php if ($vagas_result && $vagas_result->num_rows > 0): ?>
            <?php while ($vaga = $vagas_result->fetch_assoc()): ?>
                <?php
                // Obter todos os pontos de encontro escolhidos pelos colaboradores para essa vaga
                $vaga_id = $vaga['vaga_id'];
                $sql_pontos = "
                    SELECT pontos_encontro.nome AS ponto_encontro, COUNT(candidaturas.id) AS total_colaboradores
                    FROM candidaturas
                    INNER JOIN pontos_encontro ON candidaturas.ponto_encontro_id = pontos_encontro.id
                    WHERE candidaturas.vaga_id = ?
                    GROUP BY pontos_encontro.id
                    ORDER BY pontos_encontro.nome ASC
                ";
                $stmt_pontos = $conn->prepare($sql_pontos);
                $stmt_pontos->bind_param("i", $vaga_id);
                $stmt_pontos->execute();
                $pontos_result = $stmt_pontos->get_result();
                ?>

                <div class="vaga-container">
                    <h2><?php echo htmlspecialchars($vaga['nome_empresa']); ?> - <?php echo date('d/m/Y H:i', strtotime($vaga['data_vaga'])); ?></h2>
                    
                    <?php if ($pontos_result && $pontos_result->num_rows > 0): ?>
                        <ul class="pontos-encontro-list">
                            <?php while ($ponto = $pontos_result->fetch_assoc()): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($ponto['ponto_encontro']); ?>:</strong> <?php echo $ponto['total_colaboradores']; ?> colaborador(es)
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>Nenhum ponto de encontro foi selecionado para esta vaga.</p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Nenhuma vaga encontrada para a data selecionada.</p>
        <?php endif; ?>
    </div>
</body>
</html>
