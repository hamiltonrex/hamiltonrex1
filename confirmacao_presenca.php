<?php
// confirmacao_presenca.php
include('auth.php');
include('config.php');

$vaga_id = $_GET['vaga_id'] ?? null;

if (!$vaga_id) {
    die("Vaga não especificada.");
}

// Obter detalhes da vaga e lista de colaboradores associados com o estado de presença (1 = Sim, 0 = Não)
$stmt = $conn->prepare("SELECT colaboradores.id, colaboradores.nome, IFNULL(presencas.presente, 0) AS presente
                        FROM colaboradores
                        INNER JOIN candidaturas ON colaboradores.id = candidaturas.colaborador_id
                        LEFT JOIN presencas ON colaboradores.id = presencas.colaborador_id AND presencas.vaga_id = ?
                        WHERE candidaturas.vaga_id = ?
                        ORDER BY colaboradores.nome ASC");
$stmt->bind_param("ii", $vaga_id, $vaga_id);
$stmt->execute();
$result = $stmt->get_result();
$colaboradores = $result->fetch_all(MYSQLI_ASSOC);

// Contar o número de colaboradores
$total_colaboradores = count($colaboradores);

// Contar "Sim" e "Não"
$total_sim = 0;
$total_nao = 0;
foreach ($colaboradores as $colaborador) {
    if ($colaborador['presente'] == 1) {
        $total_sim++;
    } else {
        $total_nao++;
    }
}

// Verificar se a confirmação foi bem-sucedida
$sucesso = $_GET['sucesso'] ?? null;

// Verificar se foi enviado um CPF para adicionar novo colaborador
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cpf'])) {
    $cpf = $_POST['cpf'];

    // Verificar se o CPF já está registrado
    $stmt_check = $conn->prepare("SELECT * FROM colaboradores WHERE cpf = ?");
    $stmt_check->bind_param("s", $cpf);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows == 0) {
        // Se não estiver registrado, exibe a mensagem de erro
        $error = "Colaborador não registrado.";
    } else {
        // Se já registrado, adiciona à candidatura da vaga
        $colaborador = $result_check->fetch_assoc();
        $colaborador_id = $colaborador['id'];
        $stmt_candidatura = $conn->prepare("INSERT INTO candidaturas (vaga_id, colaborador_id) VALUES (?, ?)");
        $stmt_candidatura->bind_param("ii", $vaga_id, $colaborador_id);
        $stmt_candidatura->execute();
        header("Location: confirmacao_presenca.php?vaga_id=$vaga_id&sucesso=true");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Confirmação de Presença</title>
    <link rel="stylesheet" href="css/confirmacao_presenca.css">
    <script>
        // Esconder a mensagem de erro após 3 segundos
        window.onload = function() {
            const errorMessage = document.querySelector('.error-message');
            if (errorMessage) {
                setTimeout(function() {
                    errorMessage.style.display = 'none';
                }, 3000);  // 3000ms = 3 segundos
            }

            // Esconder a mensagem de sucesso após 5 segundos
            const sucessoMessage = document.querySelector('.success-message');
            if (sucessoMessage) {
                setTimeout(function() {
                    sucessoMessage.style.display = 'none';
                }, 5000);  // 5000ms = 5 segundos
            }
        };

        // Função para selecionar todos os "Sim"
        function selecionarTudoSim() {
            document.querySelectorAll('input[type="radio"][value="1"]').forEach(radio => radio.checked = true);
        }

        // Função para selecionar todos os "Não"
        function selecionarTudoNao() {
            document.querySelectorAll('input[type="radio"][value="0"]').forEach(radio => radio.checked = true);
        }
    </script>
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Confirmação de Presença</h2>

            <!-- Exibir o total de colaboradores -->
            <p>Total de colaboradores na lista: <?php echo $total_colaboradores; ?></p>

            <!-- Exibir o total de "Sim" e "Não" -->
            <p>Total de "Sim": <?php echo $total_sim; ?></p>
            <p>Total de "Não": <?php echo $total_nao; ?></p>

            <!-- Exibir mensagem de sucesso, caso exista -->
            <?php if ($sucesso) { echo "<p class='success-message'>Presença confirmada com sucesso!</p>"; } ?>
            
            <!-- Exibir a mensagem de erro se o colaborador não for encontrado -->
            <?php if (isset($error)) { echo "<p class='error-message'>$error</p>"; } ?>

            <!-- Formulário para adicionar colaborador -->
            <h3>Adicionar Colaborador (Caso não esteja na lista)</h3>
            <form method="POST">
                <input type="text" name="cpf" placeholder="Digite o CPF" required>
                <button type="submit">Adicionar Colaborador</button>
            </form>

            <!-- Caso o CPF esteja registrado, exibe a lista de colaboradores -->
            <h3>Lista de Colaboradores</h3>
            <form method="POST" action="salvar_presenca.php">
                <input type="hidden" name="vaga_id" value="<?php echo $vaga_id; ?>">

                <table>
                    <tr>
                        <th>#</th>
                        <th>Nome</th>
                        <th>Presente?</th>
                    </tr>
                    <?php foreach ($colaboradores as $index => $colaborador) { ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td> <!-- Aqui começa a numeração -->
                            <td><?php echo htmlspecialchars($colaborador['nome']); ?></td>
                            <td>
                                <input type="hidden" name="colaborador_id[]" value="<?php echo $colaborador['id']; ?>">
                                <label>
                                    <input type="radio" name="presente[<?php echo $colaborador['id']; ?>]" value="1" <?php echo ($colaborador['presente'] == 1) ? 'checked' : ''; ?>> Sim
                                </label>
                                <label>
                                    <input type="radio" name="presente[<?php echo $colaborador['id']; ?>]" value="0" <?php echo ($colaborador['presente'] == 0) ? 'checked' : ''; ?>> Não
                                </label>
                            </td>
                        </tr>
                    <?php } ?>
                </table>

                <div style="margin-top: 10px;">
                    <button type="button" onclick="selecionarTudoSim()">Selecionar tudo Sim</button>
                    <button type="button" onclick="selecionarTudoNao()">Selecionar tudo Não</button>
                </div>

                <button type="submit" style="margin-top: 20px;">Confirmar Presenças</button>
            </form>
        </main>
    </div>
</body>
</html>
