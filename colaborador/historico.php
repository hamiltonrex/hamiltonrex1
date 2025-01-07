<?php
// colaborador/historico.php
session_start();
include('../config.php');

// Verifica se o colaborador está logado
if (!isset($_SESSION['colaborador_id'])) {
    header("Location: login.php");
    exit;
}

$colaborador_id = $_SESSION['colaborador_id'];

// Obter dados do colaborador
$stmt = $conn->prepare("SELECT nome, rg, cpf, pix FROM colaboradores WHERE id = ?");
$stmt->bind_param("i", $colaborador_id);
$stmt->execute();
$colaborador = $stmt->get_result()->fetch_assoc();

if (!$colaborador) {
    die("Colaborador não encontrado.");
}

// Gerar semanas disponíveis, considerando semanas de quinta a quarta-feira
$semanas = [];
for ($i = 0; $i < 10; $i++) {
    $inicio = new DateTime("this thursday -$i week");
    $fim = new DateTime("next wednesday -$i week");
    $semanas[] = [
        'inicio' => $inicio->format('Y-m-d'),
        'fim' => $fim->format('Y-m-d'),
        'label' => $inicio->format('d/m/Y') . ' (Quinta-feira) - ' . $fim->format('d/m/Y') . ' (Quarta-feira)'
    ];
}

// Determinar intervalo da semana selecionada
$semana_selecionada = $_GET['semana'] ?? null;
if ($semana_selecionada) {
    [$inicio_semana, $fim_semana] = explode('|', $semana_selecionada);
} else {
    $inicio_semana = $semanas[0]['inicio'];
    $fim_semana = $semanas[0]['fim'];
}

// Consultar os dias trabalhados com presença confirmada
$query = $conn->prepare("
    SELECT v.data_vaga, v.valor_diaria, e.nome_empresa
    FROM presencas p
    INNER JOIN vagas v ON p.vaga_id = v.id
    INNER JOIN empresas e ON v.empresa_id = e.id
    WHERE p.colaborador_id = ? AND p.presente = 1 AND v.data_vaga BETWEEN ? AND ?
");
$query->bind_param("iss", $colaborador_id, $inicio_semana, $fim_semana);
$query->execute();
$resultados = $query->get_result();

$trabalhos = [];
while ($row = $resultados->fetch_assoc()) {
    $trabalhos[] = $row;
}

// Função para formatar data com o dia da semana
function formatarData($data) {
    $diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    $timestamp = strtotime($data);
    $diaSemana = $diasSemana[date('w', $timestamp)];
    return date('d/m/Y', $timestamp) . " ({$diaSemana})";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Ganhos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="historico-container">
        <h2>Dados do Colaborador</h2>
        <p><strong>Nome:</strong> <?php echo htmlspecialchars($colaborador['nome']); ?></p>
        <p><strong>CPF:</strong> <?php echo htmlspecialchars($colaborador['cpf']); ?></p>
        <p><strong>RG:</strong> <?php echo htmlspecialchars($colaborador['rg']); ?></p>
        <p><strong>PIX:</strong> <?php echo htmlspecialchars($colaborador['pix']); ?></p>

        <h2>Histórico de Ganhos</h2>
        <form method="GET">
            <label for="semana">Selecione a Semana:</label>
            <select name="semana" id="semana" onchange="this.form.submit()">
                <?php foreach ($semanas as $semana): ?>
                    <option value="<?php echo $semana['inicio'] . '|' . $semana['fim']; ?>" <?php echo ($inicio_semana === $semana['inicio']) ? 'selected' : ''; ?>>
                        <?php echo $semana['label']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Empresa</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($trabalhos)): ?>
                    <?php foreach ($trabalhos as $trabalho): ?>
                        <tr>
                            <td><?php echo formatarData($trabalho['data_vaga']); ?></td>
                            <td><?php echo htmlspecialchars($trabalho['nome_empresa']); ?></td>
                            <td>R$ <?php echo number_format($trabalho['valor_diaria'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">Nenhum registro encontrado para a semana selecionada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
