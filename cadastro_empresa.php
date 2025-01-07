<?php
// cadastro_empresa.php
include('auth.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_empresa = $_POST['nome_empresa'];
    $endereco = $_POST['endereco'];

    // Inserção usando prepared statement sem valor_pagamento
    $stmt = $conn->prepare("INSERT INTO empresas (nome_empresa, endereco) VALUES (?, ?)");
    $stmt->bind_param("ss", $nome_empresa, $endereco);

    if ($stmt->execute()) {
        $success = "Empresa cadastrada com sucesso!";
    } else {
        $error = "Erro ao cadastrar empresa: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastro de Empresas</title>
    <link rel="stylesheet" href="css/cadastro_empresa.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Cadastro de Empresas</h2>
            <?php if(isset($success)) { echo "<p class='success'>$success</p>"; } ?>
            <?php if(isset($error)) { echo "<p class='error'>$error</p>"; } ?>
            <form method="POST" action="">
                <input type="text" name="nome_empresa" placeholder="Nome da Empresa" required>
                <input type="text" name="endereco" placeholder="Endereço" required>
                <button type="submit">Cadastrar Empresa</button>
            </form>
        </main>
    </div>
</body>
</html>


