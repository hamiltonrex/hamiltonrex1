<?php
// vagas_colaborador.php
include('auth.php');
include('config.php');

$colaborador_id = $_GET['colaborador_id'] ?? 0;
$inicio_semana = $_GET['inicio_semana'] ?? date('Y-m-01'); // Primeiro dia do mês atual
$fim_semana = $_GET['fim_semana'] ?? date('Y-m-t'); // Último dia do mês atual

// Verifica se o ID do colaborador foi fornecido
if (!$colaborador_id) {
    die("ID do colaborador não fornecido.");
}

// Obter nome do colaborador
$stmt = $conn->prepare("SELECT nome FROM colaboradores WHERE id = ?");
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$colaborador = $stmt->get_result()->fetch_assoc();

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// Consulta para obter as vagas nas quais o colaborador se inscreveu no intervalo selecionado
$stmt = $conn->prepare("\n    SELECT vagas.id AS vaga_id, vagas.data_vaga, vagas.quantidade_colaboradores, vagas.valor_diaria, empresas.nome_empresa\n    FROM candidaturas\n    INNER JOIN vagas ON candidaturas.vaga_id = vagas.id\n    INNER JOIN empresas ON vagas.empresa_id = empresas.id\n    WHERE candidaturas.colaborador_id = ? AND vagas.data_vaga BETWEEN ? AND ?\n");
$stmt->bind_param("iss", $colaborador_id, $inicio_semana, $fim_semana);
$stmt->execute();
$vagas_inscritas = $stmt->get_result();

// Consulta para obter as vagas confirmadas (tabela 'presencas') no intervalo selecionado
$stmt = $conn->prepare("\n    SELECT vagas.id AS vaga_id, vagas.data_vaga, vagas.valor_diaria, empresas.nome_empresa\n    FROM presencas\n    INNER JOIN vagas ON presencas.vaga_id = vagas.id\n    INNER JOIN empresas ON vagas.empresa_id = empresas.id\n    WHERE presencas.colaborador_id = ? AND presencas.presente = 1 AND vagas.data_vaga BETWEEN ? AND ?\n");
$stmt->bind_param("iss", $colaborador_id, $inicio_semana, $fim_semana);
$stmt->execute();
$vagas_confirmadas = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Vagas do Colaborador - <?php echo htmlspecialchars($colaborador['nome']); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Vagas Inscritas - <?php echo htmlspecialchars($colaborador['nome']); ?></h2>

            <!-- Filtro de Intervalo de Datas -->
            <form method="GET">
                <input type="hidden" name="colaborador_id" value="<?php echo $colaborador_id; ?>">
                <div class="campo">
                    <label for="inicio_semana">Data Início:</label>
                    <input type="date" id="inicio_semana" name="inicio_semana" value="<?php echo $inicio_semana; ?>" required>
                </div>
                <div class="campo">
                    <label for="fim_semana">Data Fim:</label>
                    <input type="date" id="fim_semana" name="fim_semana" value="<?php echo $fim_semana; ?>" required>
                </div>
                <button type="submit" class="botao-filtro">Filtrar</button>
            </form>

            <!-- Tabela de Vagas Inscritas -->
            <table>
                <thead>
                    <tr>
                        <th>ID da Vaga</th>
                        <th>Empresa</th>
                        <th>Data e Hora</th>
                        <th>Quantidade</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vagas_inscritas->num_rows > 0): ?>
                        <?php while ($vaga = $vagas_inscritas->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $vaga['vaga_id']; ?></td>
                                <td><?php echo htmlspecialchars($vaga['nome_empresa']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($vaga['data_vaga'])); ?></td>
                                <td><?php echo $vaga['quantidade_colaboradores']; ?></td>
                                <td><a href="visualizar_candidatos.php?vaga_id=<?php echo $vaga['vaga_id']; ?>">Ver Candidatos</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Nenhuma vaga inscrita encontrada para o intervalo selecionado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Tabela de Vagas Confirmadas -->
            <h2>Vagas Confirmadas</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID da Vaga</th>
                        <th>Empresa</th>
                        <th>Data e Hora</th>
                        <th>Valor da Vaga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($vagas_confirmadas->num_rows > 0): ?>
                        <?php while ($vaga = $vagas_confirmadas->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $vaga['vaga_id']; ?></td>
                                <td><?php echo htmlspecialchars($vaga['nome_empresa']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($vaga['data_vaga'])); ?></td>
                                <td>R$ <?php echo number_format($vaga['valor_diaria'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Nenhuma vaga confirmada encontrada para o intervalo selecionado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>
    </div>
</body>
</html>
