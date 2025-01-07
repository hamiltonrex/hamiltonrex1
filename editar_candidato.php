<?php
include('auth.php');
include('config.php');

// Variável para armazenar a busca
$query = $_GET['query'] ?? '';

// Preparar a consulta para buscar os colaboradores por nome, RG, CPF ou PIX
$sql = "SELECT * FROM colaboradores WHERE nome LIKE ? OR rg LIKE ? OR cpf LIKE ? OR pix LIKE ?";

// Prepara a consulta
$stmt = $conn->prepare($sql);

// Adiciona a wildcard (%) ao redor da pesquisa
$searchTerm = "%" . $query . "%";

// Bind os parâmetros de busca (nome, rg, cpf, pix)
$stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);

// Executa a consulta
$stmt->execute();

// Obtém os resultados
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Colaborador</title>
    <link rel="stylesheet" href="css/editar_candidato.css"> <!-- Seu CSS -->
    <script src="js/editar_candidato.js" defer></script> <!-- Script de Modal e Atualização -->
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <div class="content-container">
                <h1>Buscar Colaborador</h1>
                <form method="GET" action="buscar_colaborador.php">
                    <input type="text" name="query" placeholder="Buscar por nome, RG, CPF ou PIX" value="<?php echo htmlspecialchars($query); ?>">
                    <button type="submit">Buscar</button>
                </form>

                <?php if ($result && $result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>RG</th>
                                <th>CPF</th>
                                <th>PIX</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($colaborador = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="vagas_colaborador.php?colaborador_id=<?php echo $colaborador['id']; ?>">
                                        <?php echo htmlspecialchars($colaborador['nome']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($colaborador['rg']); ?></td>
                                <td><?php echo htmlspecialchars($colaborador['cpf']); ?></td>
                                <td><?php echo htmlspecialchars($colaborador['pix']); ?></td>
                                <td>
                                    <button type="button" onclick="openModal(<?php echo htmlspecialchars(json_encode($colaborador)); ?>)">Editar</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php elseif (!empty($query)): ?>
                    <p class="no-results">Nenhum colaborador encontrado para a busca "<?php echo htmlspecialchars($query); ?>"</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal de Edição -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">×</span>
            <h2>Editar Colaborador</h2>
            <form id="editForm">
                <input type="hidden" id="editId" name="id">
                <label for="editNome">Nome:</label>
                <input type="text" id="editNome" name="nome" required>
                <label for="editRg">RG:</label>
                <input type="text" id="editRg" name="rg" required>
                <label for="editCpf">CPF:</label>
                <input type="text" id="editCpf" name="cpf" required>
                <label for="editPix">PIX:</label>
                <input type="text" id="editPix" name="pix" required>
                <button type="submit">Salvar Alterações</button>
            </form>
        </div>
    </div>

</body>
</html>
