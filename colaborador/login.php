<?php
// colaborador/login.php
session_start();
include('../config.php'); // Conexão com o banco de dados

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cpf = $_POST['cpf'];
    $senha = $_POST['senha'];

    $stmt = $conn->prepare("SELECT * FROM colaboradores WHERE cpf = ? AND senha = ?");
    $stmt->bind_param("ss", $cpf, $senha);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $colaborador = $result->fetch_assoc();
        $_SESSION['colaborador_id'] = $colaborador['id'];
        header("Location: historico.php");
        exit;
    } else {
        $erro = "CPF ou senha incorretos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login do Colaborador</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Login do Colaborador</h2>
        <form method="POST" action="">
            <label>CPF:</label>
            <input type="text" name="cpf" required>
            <label>Senha:</label>
            <input type="password" name="senha" required>
            <button type="submit">Entrar</button>
        </form>
        <?php if (isset($erro)) { echo "<p class='error-message'>$erro</p>"; } ?>
    </div>
</body>
</html>
