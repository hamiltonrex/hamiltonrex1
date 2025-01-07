<?php
// excluir_candidato.php
include('auth.php');
include('config.php');

// Verifica se o ID do candidato e o ID da vaga foram passados
if (!isset($_GET['id']) || !isset($_GET['vaga_id'])) {
    die('Erro: Parâmetros faltando.');
}

$colaborador_id = $_GET['id'];
$vaga_id = $_GET['vaga_id'];

// Prepara a exclusão do candidato da vaga
$stmt = $conn->prepare("DELETE FROM candidaturas WHERE colaborador_id = ? AND vaga_id = ?");
$stmt->bind_param("ii", $colaborador_id, $vaga_id);

if ($stmt->execute()) {
    // Registro de log para exclusão de candidato
    $acao_log = "Excluiu o colaborador ID: $colaborador_id da vaga ID: $vaga_id";
    $stmt_log = $conn->prepare("INSERT INTO logs (usuario, acao) VALUES (?, ?)");
    $stmt_log->bind_param("ss", $_SESSION['usuario'], $acao_log);
    $stmt_log->execute();
    $stmt_log->close();

    // Redireciona de volta para a página da lista de candidatos com mensagem de sucesso
    echo "<script>alert('Candidato excluído com sucesso.'); window.location.href = 'visualizar_candidatos.php?vaga_id=" . $vaga_id . "';</script>";
} else {
    echo "Erro ao excluir candidato: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
