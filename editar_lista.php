<?php
include('auth.php');
include('config.php');

// Obter o ID da vaga
$vaga_id = $_GET['vaga_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Atualizar dados da vaga
    $nova_data = $_POST['data_vaga'];
    $nova_quantidade = $_POST['quantidade_colaboradores'];

    $stmt = $conn->prepare("UPDATE vagas SET data_vaga = ?, quantidade_colaboradores = ? WHERE id = ?");
    $stmt->bind_param("sii", $nova_data, $nova_quantidade, $vaga_id);

    if ($stmt->execute()) {
        echo "<script>alert('Vaga atualizada com sucesso!'); window.location.href='listar_candidatos.php';</script>";
    } else {
        echo "<script>alert('Erro ao atualizar a vaga.');</script>";
    }
}

// Buscar dados da vaga atual
$stmt = $conn->prepare("SELECT * FROM vagas WHERE id = ?");
$stmt->bind_param("i", $vaga_id);
$stmt->execute();
$vaga = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Lista</title>
    <link rel="stylesheet" href="css/editar_lista.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Editar Lista</h2>
            <form method="POST" action="">
                <label for="data_vaga">Data e Hora:</label>
                <input type="datetime-local" name="data_vaga" id="data_vaga" value="<?php echo date('Y-m-d\TH:i', strtotime($vaga['data_vaga'])); ?>" required>

                <label for="quantidade_colaboradores">Quantidade:</label>
                <input type="number" name="quantidade_colaboradores" id="quantidade_colaboradores" value="<?php echo htmlspecialchars($vaga['quantidade_colaboradores']); ?>" required>

                <button type="submit">Salvar Alterações</button>
            </form>
        </main>
    </div>
</body>
</html>
