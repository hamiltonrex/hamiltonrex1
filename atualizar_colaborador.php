<?php
include('config.php');

if (isset($_POST['id']) && isset($_POST['nome']) && isset($_POST['rg']) && isset($_POST['cpf']) && isset($_POST['pix'])) {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $rg = $_POST['rg'];
    $cpf = $_POST['cpf'];
    $pix = $_POST['pix'];

    $stmt = $conn->prepare("UPDATE colaboradores SET nome = ?, rg = ?, cpf = ?, pix = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $nome, $rg, $cpf, $pix, $id);

    if ($stmt->execute()) {
        echo "Sucesso";
    } else {
        echo "Erro: " . $stmt->error;
    }
} else {
    echo "Dados incompletos.";
}
?>
