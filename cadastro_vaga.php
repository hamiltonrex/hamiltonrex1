<?php
// cadastro_vaga.php
include('auth.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $empresa_id = $_POST['empresa_id'];
    $data_vaga = $_POST['data_vaga'];
    $quantidade_colaboradores = $_POST['quantidade_colaboradores'];
    $valor_diaria = $_POST['valor_diaria']; // Novo campo para o valor da diária
    
    // Converter data e hora para o formato do MySQL
    $data_vaga = str_replace('T', ' ', $data_vaga);

    // Gerar identificador único para a vaga
    $link_vaga = "e" . bin2hex(random_bytes(10));

    // Inserir a vaga no banco de dados com o novo campo
    $stmt = $conn->prepare("INSERT INTO vagas (empresa_id, data_vaga, quantidade_colaboradores, valor_diaria, link_vaga) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isids", $empresa_id, $data_vaga, $quantidade_colaboradores, $valor_diaria, $link_vaga);

    if ($stmt->execute()) {
        // Gerar link completo para exibição
        $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $full_link = $protocol . $host . "/candidatura.php?vaga_id=" . $link_vaga;
        $success = "Vaga cadastrada com sucesso! Link: <a href='$full_link' target='_blank'>$full_link</a>";
    } else {
        $error = "Erro ao cadastrar vaga: " . $stmt->error;
    }
}

// Obter empresas para o select
$empresas = $conn->query("SELECT * FROM empresas");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Vagas</title>
    <link rel="stylesheet" href="css/cadastro_vaga.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Cadastro de Vagas</h2>
            <?php if(isset($success)) { echo "<p class='success'>$success</p>"; } ?>
            <?php if(isset($error)) { echo "<p class='error'>$error</p>"; } ?>
            <form method="POST" action="">
                <label for="empresa_id">Empresa:</label>
                <select name="empresa_id" id="empresa_id" required>
                    <?php while($empresa = $empresas->fetch_assoc()) { ?>
                        <option value="<?php echo $empresa['id']; ?>"><?php echo htmlspecialchars($empresa['nome_empresa']); ?></option>
                    <?php } ?>
                </select>
                <label for="data_vaga">Data e Hora da Vaga:</label>
                <input type="datetime-local" name="data_vaga" id="data_vaga" required>
                <label for="quantidade_colaboradores">Quantidade de Colaboradores:</label>
                <input type="number" name="quantidade_colaboradores" id="quantidade_colaboradores" required>
                <label for="valor_diaria">Valor da Diária:</label>
                <input type="number" step="0.01" name="valor_diaria" id="valor_diaria" required>
                <button type="submit">Cadastrar Vaga</button>
            </form>
        </main>
    </div>
</body>
</html>
