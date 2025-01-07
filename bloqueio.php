<?php


// Exibir erros para desenvolvimento (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");

// Inclusão de arquivos de configuração e componentes
include('config.php'); // Conexão com o banco de dados
include('sidebar.php'); // Sidebar do admin

// Função para sanitizar entradas e lidar com valores nulos
function sanitizarEntrada($dados) {
    return htmlspecialchars(trim($dados ?? ''));
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bloqueio de Colaboradores</title>
    <link rel="stylesheet" href="css/bloqueio.css">
</head>
<body>
    <div class="content-container">
        <h1>Colaboradores Bloqueados</h1>
        
        <?php
        // Buscar colaboradores bloqueados
        $sql_bloqueados = "SELECT nome, cpf, rg, email FROM colaboradores WHERE bloqueado = 1";
        $result_bloqueados = $conn->query($sql_bloqueados);

        if ($result_bloqueados && $result_bloqueados->num_rows > 0) {
            echo "<table>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>RG</th>
                        <th>Email</th>
                    </tr>";
            while ($row = $result_bloqueados->fetch_assoc()) {
                echo "<tr>
                        <td>" . sanitizarEntrada($row['nome']) . "</td>
                        <td>" . sanitizarEntrada($row['cpf']) . "</td>
                        <td>" . sanitizarEntrada($row['rg']) . "</td>
                        <td>" . sanitizarEntrada($row['email']) . "</td>
                    </tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Nenhum colaborador bloqueado encontrado.</p>";
        }
        ?>
        
        <h1>Buscar Colaboradores</h1>
        <!-- Formulário de Busca -->
        <form method="GET" action="">
            <input type="text" name="buscar" placeholder="Digite CPF, RG ou Nome" required>
            <button type="submit">Buscar</button>
        </form>
        
        <?php
        // Buscar colaboradores com base no input
        if (isset($_GET['buscar'])) {
            $busca = sanitizarEntrada($_GET['buscar']);
            $sql_busca = "SELECT * FROM colaboradores WHERE cpf LIKE ? OR rg LIKE ? OR nome LIKE ?";
            $stmt = $conn->prepare($sql_busca);

            if ($stmt) {
                $busca_like = "%" . $busca . "%";
                $stmt->bind_param("sss", $busca_like, $busca_like, $busca_like);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    echo "<table>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>RG</th>
                                <th>Email</th>
                                <th>Bloqueado</th>
                                <th>Ações</th>
                            </tr>";
                    while ($row = $result->fetch_assoc()) {
                        $bloqueado = $row['bloqueado'] == 1 ? "Sim" : "Não";
                        $acao = $row['bloqueado'] == 1 ? "Desbloquear" : "Bloquear";

                        echo "<tr>
                                <td>" . sanitizarEntrada($row['nome']) . "</td>
                                <td>" . sanitizarEntrada($row['cpf']) . "</td>
                                <td>" . sanitizarEntrada($row['rg']) . "</td>
                                <td>" . sanitizarEntrada($row['email']) . "</td>
                                <td>" . $bloqueado . "</td>
                                <td>
                                    <form method='POST' action=''>
                                        <input type='hidden' name='cpf' value='" . sanitizarEntrada($row['cpf']) . "'>
                                        <button type='submit' name='acao' value='" . $acao . "'>" . $acao . "</button>
                                    </form>
                                </td>
                              </tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>Nenhum colaborador encontrado.</p>";
                }
            }
        }

        // Bloquear ou desbloquear colaborador
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cpf']) && isset($_POST['acao'])) {
            $cpf = sanitizarEntrada($_POST['cpf']);
            $acao = sanitizarEntrada($_POST['acao']);
            $status = ($acao == "Bloquear") ? 1 : 0;

            $sql_update = "UPDATE colaboradores SET bloqueado = ? WHERE cpf = ?";
            $stmt = $conn->prepare($sql_update);
            if ($stmt) {
                $stmt->bind_param("is", $status, $cpf);
                if ($stmt->execute()) {
                    echo "<p class='success-message'>Ação realizada com sucesso!</p>";
                    echo "<script>setTimeout(() => location.reload(), 2000);</script>";
                } else {
                    echo "<p class='error-message'>Erro ao atualizar status.</p>";
                }
            }
        }
        ?>
    </div>
</body>
</html>
