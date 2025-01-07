<?php
// salvar_presenca.php
include('auth.php');
include('config.php');

// Verifica se todos os dados necessários foram enviados
if (!isset($_POST['vaga_id']) || !isset($_POST['colaborador_id']) || !isset($_POST['presente'])) {
    die("Dados incompletos.");
}

$vaga_id = $_POST['vaga_id'];
$colaborador_ids = $_POST['colaborador_id'];
$presencas = $_POST['presente'];

// Obter a data da vaga para associar a presença
$stmt = $conn->prepare("SELECT data_vaga FROM vagas WHERE id = ?");
$stmt->bind_param("i", $vaga_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Vaga não encontrada.");
}

$data_vaga = $result->fetch_assoc()['data_vaga'];

// Loop pelos colaboradores e atualizar ou inserir presença para cada um
foreach ($colaborador_ids as $colaborador_id) {
    $presente = isset($presencas[$colaborador_id]) ? $presencas[$colaborador_id] : null;

    // Verifica se a presença foi marcada e executa a inserção ou atualização
    if ($presente !== null) {
        // Usa REPLACE INTO para inserir ou atualizar a presença do colaborador para a data da vaga
        $stmt = $conn->prepare("REPLACE INTO presencas (colaborador_id, data_presenca, presente, vaga_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $colaborador_id, $data_vaga, $presente, $vaga_id);

        if (!$stmt->execute()) {
            echo "Erro ao salvar presença para o colaborador $colaborador_id: " . $stmt->error;
        }
    }
}

// Redireciona de volta para a página de confirmação de presença com uma mensagem de sucesso
header("Location: confirmacao_presenca.php?vaga_id=$vaga_id&sucesso=true"); // Corrigido 'status' para 'sucesso'
exit;
?>
