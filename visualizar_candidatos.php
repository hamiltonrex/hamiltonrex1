<?php
// visualizar_candidatos.php
include('auth.php');
include('config.php');

// Verifica se o parâmetro 'vaga_id' está presente
if (!isset($_GET['vaga_id']) || empty($_GET['vaga_id'])) {
    die('Erro: ID da vaga não especificado.');
}

$vaga_id = $_GET['vaga_id'];

// Obter detalhes da vaga
$stmt = $conn->prepare("SELECT vagas.*, empresas.nome_empresa FROM vagas INNER JOIN empresas ON vagas.empresa_id = empresas.id WHERE vagas.id = ?");
$stmt->bind_param("i", $vaga_id);
$stmt->execute();
$vaga_result = $stmt->get_result();
$vaga = $vaga_result->fetch_assoc();

// Verifica se a vaga foi encontrada
if (!$vaga) {
    die('Erro: Detalhes da vaga não encontrados.');
}

// Obter candidatos
$stmt = $conn->prepare("SELECT colaboradores.* FROM candidaturas INNER JOIN colaboradores ON candidaturas.colaborador_id = colaboradores.id WHERE candidaturas.vaga_id = ?");
$stmt->bind_param("i", $vaga_id);
$stmt->execute();
$candidatos = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Candidatos</title>
    <link rel="stylesheet" href="css/visualizar_candidatos.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <div class="content-container">
                <h1>Visualizar Candidatos</h1>
                <h2>Candidatos para a Vaga <?php echo htmlspecialchars($vaga_id); ?></h2>
                <p><strong>Empresa:</strong> <?php echo htmlspecialchars($vaga['nome_empresa']); ?></p>
                <p><strong>Data e Hora:</strong> <?php echo date('d/m/Y H:i', strtotime($vaga['data_vaga'])); ?></p>
                <p><strong>Link para cadastro:</strong> 
                    <a href="https://mega.financeiros.online/candidatura.php?vaga_id=<?php echo htmlspecialchars($vaga['link_vaga']); ?>" target="_blank">
                        https://mega.financeiros.online/candidatura.php?vaga_id=<?php echo htmlspecialchars($vaga['link_vaga']); ?>
                    </a>
                </p>
                <div class="action-buttons">
                    <button onclick="openDuplicateModal()">Duplicar Lista</button>
                    <a class="export-button" href="exportar_candidatos.php?vaga_id=<?php echo $vaga_id; ?>">Exportar para Excel</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>RG</th>
                            <th>Email</th>
                             
                    </thead>
                    <tbody>
                        <?php while($candidato = $candidatos->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($candidato['nome']); ?></td>
                            <td><?php echo htmlspecialchars($candidato['cpf']); ?></td>
                            <td><?php echo htmlspecialchars($candidato['rg']); ?></td>
                           
                            <td>
                                <a href="excluir_candidato.php?id=<?php echo $candidato['id']; ?>&vaga_id=<?php echo $vaga_id; ?>" onclick="return confirm('Tem certeza que deseja excluir este candidato?')">Excluir</a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal para duplicar lista -->
    <div id="duplicateModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeDuplicateModal()">&times;</span>
            <h3>Duplicar Lista</h3>
            <form id="duplicateForm" action="duplicar_lista.php" method="POST">
                <input type="hidden" name="vaga_id" value="<?php echo $vaga_id; ?>">
                <label>Data da Nova Vaga:</label>
                <input type="date" name="data_vaga" required>
                <label>Hora da Nova Vaga:</label>
                <input type="time" name="hora_vaga" required>
                <button type="submit">Duplicar</button>
            </form>
        </div>
    </div>

    <script>
        function openDuplicateModal() {
            document.getElementById('duplicateModal').style.display = 'flex';
        }

        function closeDuplicateModal() {
            document.getElementById('duplicateModal').style.display = 'none';
        }
    </script>
</body>
</html>
