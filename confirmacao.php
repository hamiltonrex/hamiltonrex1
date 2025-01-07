<?php
// confirmacao.php
include('auth.php');
include('config.php');

// Obter lista de empresas para o dropdown
$empresas = $conn->query("SELECT id, nome_empresa FROM empresas");

// Variáveis para a busca
$empresa_id = $_GET['empresa_id'] ?? null;
$data_presenca = $_GET['data_presenca'] ?? null;

// Buscar vagas para a empresa e data selecionadas
$vagas = [];
if ($empresa_id && $data_presenca) {
    $stmt = $conn->prepare("
        SELECT v.id, v.data_vaga, IF(COUNT(p.id) > 0, 1, 0) AS confirmada
        FROM vagas v
        LEFT JOIN presencas p ON v.id = p.vaga_id
        WHERE v.empresa_id = ? 
        AND DATE(v.data_vaga) = ?
        GROUP BY v.id
    ");
    $stmt->bind_param("is", $empresa_id, $data_presenca);
    $stmt->execute();
    $vagas = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirmação de Presença - Seleção da Vaga</title>
    <link rel="stylesheet" href="css/confirmacao_presenca.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Confirmação de Presença</h2>
            
            <form method="GET" action="confirmacao.php" class="form-busca">
                <div class="form-control">
                    <label for="empresa_id">Selecione a Empresa:</label>
                    <select name="empresa_id" id="empresa_id" required>
                        <option value="">Escolha a empresa</option>
                        <?php while ($empresa = $empresas->fetch_assoc()) { ?>
                            <option value="<?php echo $empresa['id']; ?>" <?php if ($empresa_id == $empresa['id']) echo 'selected'; ?>>
                                <?php echo $empresa['nome_empresa']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-control">
                    <label for="data_presenca">Data:</label>
                    <input type="date" name="data_presenca" id="data_presenca" value="<?php echo $data_presenca; ?>" required>
                </div>
                
                <button type="submit" class="btn-buscar">Buscar Vagas</button>
            </form>

            <?php if ($empresa_id && $data_presenca && $vagas->num_rows > 0) { ?>
                <h3>Vagas Disponíveis para <?php echo $data_presenca; ?></h3>
                <ul class="vaga-lista">
                    <?php while ($vaga = $vagas->fetch_assoc()) { ?>
                        <li>
                            <a class="vaga-link <?php echo ($vaga['confirmada'] == 1) ? 'confirmada' : ''; ?>" href="confirmacao_presenca.php?vaga_id=<?php echo $vaga['id']; ?>">
                                Vaga ID: <?php echo $vaga['id']; ?> - <?php echo date('H:i', strtotime($vaga['data_vaga'])); ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } elseif ($empresa_id && $data_presenca) { ?>
                <p>Nenhuma vaga encontrada para esta data e empresa.</p>
            <?php } ?>
        </main>
    </div>
</body>
</html>
