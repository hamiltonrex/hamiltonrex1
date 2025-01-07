<?php
// Inicia a sessão
session_start();
include('auth.php'); // Verifica se o usuário está autenticado
include('config.php');

// Exclui ponto de encontro
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Deleta o ponto de encontro do banco de dados
    $stmt = $conn->prepare("DELETE FROM pontos_encontro WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        $success = "Ponto de encontro excluído com sucesso!";
    } else {
        $error = "Erro ao excluir ponto de encontro: " . $stmt->error;
    }
}

// Adiciona novo ponto de encontro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['nome'])) {
    $nome = $_POST['nome'];  // Nome do ponto de encontro

    // Inserir o ponto de encontro no banco de dados
    $stmt = $conn->prepare("INSERT INTO pontos_encontro (nome) VALUES (?)");
    $stmt->bind_param("s", $nome);

    if ($stmt->execute()) {
        $success = "Ponto de encontro adicionado com sucesso!";
    } else {
        $error = "Erro ao adicionar ponto de encontro: " . $stmt->error;
    }
}

// Busca todos os pontos de encontro cadastrados
$stmt = $conn->prepare("SELECT * FROM pontos_encontro");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pontos de Encontro</title>
    <link rel="stylesheet" href="css/ponto_encontro.css">
</head>
<body>

    <div class="container">
        <h1>Pontos de Encontro</h1>

        <?php if (isset($success)) { echo "<p class='success'>$success</p>"; } ?>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>

        <!-- Formulário para adicionar ponto de encontro -->
        <form method="POST" action="">
            <input type="text" name="nome" placeholder="Nome do Ponto de Encontro" required>
            <button type="submit">Adicionar Ponto</button>
        </form>

        <h2>Pontos de Encontro Disponíveis</h2>
        <ul>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <li>
                    <?php echo htmlspecialchars($row['nome']); ?>
                    <!-- Botão para excluir ponto de encontro -->
                    <a href="?delete_id=<?php echo $row['id']; ?>" class="delete-btn" onclick="return confirm('Tem certeza que deseja excluir este ponto de encontro?')">Excluir</a>
                </li>
            <?php } ?>
        </ul>
    </div>
</body>
</html>
