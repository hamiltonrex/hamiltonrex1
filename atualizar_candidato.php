<?php
include('config.php');

// Verifica se os dados foram recebidos via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $rg = $_POST['rg'];
    $cpf = $_POST['cpf'];
    $pix = $_POST['pix'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'];

    // Atualiza os dados do colaborador no banco de dados
    $stmt = $conn->prepare("UPDATE colaboradores SET nome = ?, rg = ?, cpf = ?, pix = ?, telefone = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $nome, $rg, $cpf, $pix, $telefone, $email, $id);

    if ($stmt->execute()) {
        echo "Sucesso";
    } else {
        echo "Erro: " . $stmt->error;
    }
}
?>


