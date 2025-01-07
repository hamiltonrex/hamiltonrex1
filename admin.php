<?php
// admin.php
session_start();

// Autenticação básica para acesso à página
if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== 'hamilton' || $_SERVER['PHP_AUTH_PW'] !== 'jaguarE11') {
    header('WWW-Authenticate: Basic realm="Admin Area"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Acesso não autorizado';
    exit;
}

include('config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_table'])) {
        $table = $_POST['table_name'];
        if ($table) {
            $sql = "DELETE FROM `$table`;";
            if ($conn->query($sql) === TRUE) {
                echo "<p>Tabela '$table' limpa com sucesso.</p>";
            } else {
                echo "<p>Erro ao limpar a tabela '$table': " . $conn->error . "</p>";
            }
        }
    } elseif (isset($_POST['reset_database'])) {
        // Reseta todas as tabelas
        $tables = ['candidaturas', 'colaboradores', 'empresas', 'presencas', 'usuarios', 'vagas'];
        foreach ($tables as $table) {
            $sql = "DELETE FROM `$table`;";
            if ($conn->query($sql) !== TRUE) {
                echo "<p>Erro ao limpar a tabela '$table': " . $conn->error . "</p>";
            }
        }
        echo "<p>Banco de dados resetado com sucesso.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Área Administrativa</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="admin-container">
        <h1>Bem-vindo à Área Administrativa</h1>
        <form method="POST" action="">
            <label for="table_name">Escolha uma tabela para limpar:</label>
            <select name="table_name" id="table_name">
                <option value="candidaturas">Candidaturas</option>
                <option value="colaboradores">Colaboradores</option>
                <option value="empresas">Empresas</option>
                <option value="presencas">Presenças</option>
                <option value="usuarios">Usuários</option>
                <option value="vagas">Vagas</option>
            </select>
            <button type="submit" name="delete_table">Limpar Tabela</button>
        </form>
        
        <form method="POST" action="">
            <button type="submit" name="reset_database" style="background-color: red; color: white;">Resetar Banco de Dados</button>
        </form>
    </div>
</body>
</html>
