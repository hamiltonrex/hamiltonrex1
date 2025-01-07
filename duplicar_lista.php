<?php
// duplicar_lista.php
include('auth.php');
include('config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vaga_id = $_POST['vaga_id'];
    $nova_data = $_POST['data_vaga'];
    $novo_horario = $_POST['hora_vaga'];

    // Verifica se os campos obrigatórios estão presentes
    if (empty($vaga_id) || empty($nova_data) || empty($novo_horario)) {
        die('Erro: Todos os campos são obrigatórios.');
    }

    // Formata a data e hora da nova vaga
    $nova_data_hora = $nova_data . ' ' . $novo_horario;

    // Obtém a vaga original
    $stmt = $conn->prepare("SELECT * FROM vagas WHERE id = ?");
    $stmt->bind_param("i", $vaga_id);
    $stmt->execute();
    $vaga_result = $stmt->get_result();

    if ($vaga_result->num_rows == 0) {
        die('Erro: Vaga original não encontrada.');
    }

    $vaga_original = $vaga_result->fetch_assoc();

    // Insere a nova vaga com base na original
    $stmt_insert_vaga = $conn->prepare("INSERT INTO vagas (empresa_id, data_vaga, quantidade_colaboradores, valor_diaria, link_vaga) VALUES (?, ?, ?, ?, ?)");
    $novo_link_vaga = uniqid(); // Gera um novo link único para a vaga
    $stmt_insert_vaga->bind_param("issds", $vaga_original['empresa_id'], $nova_data_hora, $vaga_original['quantidade_colaboradores'], $vaga_original['valor_diaria'], $novo_link_vaga);

    if ($stmt_insert_vaga->execute()) {
        $nova_vaga_id = $stmt_insert_vaga->insert_id;

        // Duplica as candidaturas dos colaboradores para a nova vaga sem incluir registros de presença
        $stmt_candidatos = $conn->prepare("SELECT colaborador_id FROM candidaturas WHERE vaga_id = ?");
        $stmt_candidatos->bind_param("i", $vaga_id);
        $stmt_candidatos->execute();
        $candidatos_result = $stmt_candidatos->get_result();

        $stmt_insert_candidatura = $conn->prepare("INSERT INTO candidaturas (vaga_id, colaborador_id) VALUES (?, ?)");

        while ($candidato = $candidatos_result->fetch_assoc()) {
            // Insere apenas se não houver registros de presença para evitar duplicações indesejadas
            $stmt_check_presenca = $conn->prepare("SELECT * FROM presencas WHERE colaborador_id = ? AND data_presenca = ?");
            $stmt_check_presenca->bind_param("is", $candidato['colaborador_id'], $nova_data);
            $stmt_check_presenca->execute();
            $presenca_result = $stmt_check_presenca->get_result();

            if ($presenca_result->num_rows == 0) {
                $stmt_insert_candidatura->bind_param("ii", $nova_vaga_id, $candidato['colaborador_id']);
                $stmt_insert_candidatura->execute();
            }
        }

        // Exibe mensagem de sucesso e permanece na mesma página
        echo "<script>alert('Lista duplicada com sucesso para a nova data e hora.'); window.location.href = document.referrer;</script>";
    } else {
        echo "Erro ao duplicar a vaga: " . $stmt_insert_vaga->error;
    }
}
?>
