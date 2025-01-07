<?php
include('auth.php');
include('config.php');

// Captura o CPF do colaborador (usuário logado) que está fazendo login
$cpf = $_SESSION['cpf'] ?? null;  // Supondo que você esteja armazenando o CPF do usuário na sessão após o login

if (!$cpf) {
    die("Acesso negado. Favor fazer login.");
}

// Função para calcular o primeiro e o último dia da semana (quinta a quarta)
function getWeekRange($date) {
    // Determina o primeiro dia (quinta-feira) e o último dia (quarta-feira) da semana baseada na data fornecida
    $dateTime = new DateTime($date);
    $dayOfWeek = $dateTime->format('w'); // 0 - Domingo, 1 - Segunda, ..., 6 - Sábado

    // Ajusta para a quinta-feira da semana
    $dateTime->modify('-' . ($dayOfWeek + 3) . ' days'); // Vai para a quinta-feira dessa semana

    $startOfWeek = $dateTime->format('Y-m-d'); // Primeiro dia da semana (quinta)
    $dateTime->modify('+6 days');  // Avança até a quarta-feira dessa semana
    $endOfWeek = $dateTime->format('Y-m-d'); // Último dia da semana (quarta)

    return [$startOfWeek, $endOfWeek];
}

// Obter as datas da última semana (quinta-feira a quarta-feira)
$today = date('Y-m-d');
list($lastWeekStart, $lastWeekEnd) = getWeekRange($today);

// Obter as datas da semana passada (quinta-feira a quarta-feira)
$dateBeforeLast = date('Y-m-d', strtotime('-7 days', strtotime($today)));
list($beforeLastWeekStart, $beforeLastWeekEnd) = getWeekRange($dateBeforeLast);

// Calcular o valor total para cada semana
function getTotalForWeek($cpf, $startDate, $endDate) {
    global $conn;
    $stmt = $conn->prepare("SELECT SUM(vagas.valor_diaria) as total
                            FROM presencas
                            INNER JOIN vagas ON presencas.vaga_id = vagas.id
                            WHERE presencas.colaborador_id = (SELECT id FROM colaboradores WHERE cpf = ?)
                            AND presencas.data_presenca BETWEEN ? AND ?");
    $stmt->bind_param("sss", $cpf, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

// Obter o valor da última semana, semana anterior e da próxima segunda-feira
$totalLastWeek = getTotalForWeek($cpf, $lastWeekStart, $lastWeekEnd);
$totalBeforeLastWeek = getTotalForWeek($cpf, $beforeLastWeekStart, $beforeLastWeekEnd);

// Calcular o valor da próxima segunda-feira (assumindo que a presença será registrada na próxima segunda-feira)
$nextMonday = date('Y-m-d', strtotime('next Monday'));
$nextMondayValue = getTotalForWeek($cpf, $nextMonday, $nextMonday); // Pega o valor para o próximo dia útil

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumo de Ganhos - Colaborador</title>
    <link rel="stylesheet" href="css/style.css">  <!-- Ajuste conforme necessário -->
</head>
<body>
    <div class="container">
        <h2>Resumo de Ganhos</h2>
        <p><strong>Ganho da última semana (quinta a quarta):</strong> R$ <?php echo number_format($totalLastWeek, 2, ',', '.'); ?></p>
        <p><strong>Ganho da semana passada (quinta a quarta):</strong> R$ <?php echo number_format($totalBeforeLastWeek, 2, ',', '.'); ?></p>
        <p><strong>Valor previsto para próxima segunda-feira:</strong> R$ <?php echo number_format($nextMondayValue, 2, ',', '.'); ?></p>
    </div>
</body>
</html>
