<?php
// deletar_lista.php
include('auth.php');
include('config.php');

// Verifica se o ID da vaga foi passado na URL
$delete_id = $_GET['delete_id'] ?? null;

if (!$delete_id) {
    die("ID da vaga não fornecido.");
}

// Inicia a transação para garantir que a exclusão seja feita de forma segura
$conn->begin_transaction();

try {
    // Excluir as presenças relacionadas à vaga
    $stmt = $conn->prepare("DELETE FROM presencas WHERE vaga_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();

    // Excluir as candidaturas relacionadas à vaga
    $stmt = $conn->prepare("DELETE FROM candidaturas WHERE vaga_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();

    // Excluir a vaga
    $stmt = $conn->prepare("DELETE FROM vagas WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();

    // Commit da transação
    $conn->commit();

    // Mensagem de sucesso
    $message = "Lista de candidatos e vaga excluída com sucesso!";
} catch (Exception $e) {
    // Caso ocorra algum erro, desfaz a transação
    $conn->rollback();
    $message = "Erro ao excluir a lista: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Excluir Lista de Candidatos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Excluir Lista de Candidatos</h2>

            <!-- Exibir mensagem de sucesso ou erro -->
            <?php if (isset($message)) { echo "<p class='success'>$message</p>"; } ?>

            <a href="listar_candidatos.php">Voltar para a lista de vagas</a>
        </main>
    </div>
</body>
</html>
