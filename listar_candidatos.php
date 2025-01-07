<?php
include('auth.php');
include('config.php');

// Obter data e empresa selecionadas (padrão: data de hoje e todas as empresas)
$data_selecionada = $_GET['data_selecionada'] ?? date('Y-m-d');
$empresa_selecionada = $_GET['empresa_selecionada'] ?? '';

// Buscar lista de empresas para o filtro
$stmt_empresas = $conn->query("SELECT id, nome_empresa FROM empresas");
$empresas = $stmt_empresas->fetch_all(MYSQLI_ASSOC);

// Consulta principal com filtros de data e empresa
$sql = "SELECT vagas.*, empresas.nome_empresa, 
               (SELECT COUNT(*) FROM candidaturas WHERE candidaturas.vaga_id = vagas.id) AS total_colaboradores
        FROM vagas
        INNER JOIN empresas ON vagas.empresa_id = empresas.id
        WHERE DATE(vagas.data_vaga) = ?";

$params = [$data_selecionada];
$types = "s";

if (!empty($empresa_selecionada)) {
    $sql .= " AND empresas.id = ?";
    $params[] = $empresa_selecionada;
    $types .= "i";
}

$sql .= " ORDER BY vagas.data_vaga ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$vagas = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Candidatos</title>
    <link rel="stylesheet" href="css/listar_candidatos.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Vagas Disponíveis</h2>

            <!-- Formulário para filtro -->
            <form method="GET" action="listar_candidatos.php">
                <label for="empresa_selecionada">Empresa:</label>
                <select name="empresa_selecionada" id="empresa_selecionada">
                    <option value="">Todas</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?php echo $empresa['id']; ?>" <?php echo ($empresa_selecionada == $empresa['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($empresa['nome_empresa']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="data_selecionada">Data:</label>
                <input type="date" name="data_selecionada" id="data_selecionada" value="<?php echo htmlspecialchars($data_selecionada); ?>">
                
                <button type="submit">Filtrar</button>
            </form>

            <?php if ($vagas && $vagas->num_rows > 0): ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Empresa</th>
                        <th>Data e Hora</th>
                        <th>Quantidade</th>
                        <th>Colaboradores Cadastrados</th>
                        <th>Ações</th>
                    </tr>
                    <?php while($vaga = $vagas->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $vaga['id']; ?></td>
                        <td><?php echo htmlspecialchars($vaga['nome_empresa']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($vaga['data_vaga'])); ?></td>
                        <td><?php echo $vaga['quantidade_colaboradores']; ?></td>
                        <td><?php echo $vaga['total_colaboradores']; ?></td>
                        <td>
                            <a href="editar_lista.php?vaga_id=<?php echo $vaga['id']; ?>">Editar</a> |
                            <a href="visualizar_candidatos.php?vaga_id=<?php echo $vaga['id']; ?>">Ver Colaboradores</a> |
                            <a href="confirmacao_presenca.php?vaga_id=<?php echo $vaga['id']; ?>">Confirma Presença</a> |
                            <a href="deletar_lista.php?delete_id=<?php echo $vaga['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir esta lista?');">Excluir</a>
                        </td>
                    </tr>
                    <?php } ?>
                </table>
            <?php else: ?>
                <p>Nenhuma vaga encontrada para os filtros selecionados.</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
