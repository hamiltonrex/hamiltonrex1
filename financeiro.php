<?php
include('auth.php');
include('config.php');

// Configuração para exibir datas em português
$formatter = new IntlDateFormatter('pt_BR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'EEE');





// Verificar se foi feita uma busca
$empresa_id = $_GET['empresa_id'] ?? null;
$data_inicio = $_GET['data_inicio'] ?? null;

// Configurar a semana (quinta a quarta-feira)
if ($data_inicio) {
    $data_inicio_obj = new DateTime($data_inicio);
    $dia_semana = $data_inicio_obj->format('N'); // Dia da semana (1 = segunda, 7 = domingo)

    // Ajustar para quinta-feira anterior, se necessário
    if ($dia_semana != 4) {
        $data_inicio_obj->modify('last thursday');
    }

    $data_inicio = $data_inicio_obj->format('Y-m-d');
    $data_fim = $data_inicio_obj->modify('+6 days')->format('Y-m-d');
} else {
    // Por padrão, mostra a semana atual (quinta-feira até quarta-feira)
    $hoje = new DateTime();
    $hoje->modify('last thursday');
    $data_inicio = $hoje->format('Y-m-d');
    $data_fim = $hoje->modify('+6 days')->format('Y-m-d');
}

// Obter lista de empresas para o dropdown
$empresas = $conn->query("SELECT id, nome_empresa FROM empresas");

$colaboradores_financeiro = [];
$dias_exibicao = [];
if ($empresa_id) {
    // Gerar os dias da semana entre o intervalo selecionado
    $periodo = new DatePeriod(
        new DateTime($data_inicio),
        new DateInterval('P1D'),
        (new DateTime($data_fim))->modify('+1 day')
    );

    foreach ($periodo as $data) {
        $dias_exibicao[] = ucfirst(mb_substr($formatter->format($data), 0, 3));
    }

    // Buscar dados do banco, somente presenças confirmadas
    $stmt = $conn->prepare("
        SELECT colaboradores.nome, colaboradores.cpf, colaboradores.pix, vagas.valor_diaria,
               presencas.data_presenca
        FROM presencas
        INNER JOIN colaboradores ON presencas.colaborador_id = colaboradores.id
        INNER JOIN vagas ON presencas.vaga_id = vagas.id
        WHERE vagas.empresa_id = ? 
        AND presencas.data_presenca BETWEEN ? AND ?
        AND presencas.presente = 1
        ORDER BY colaboradores.nome ASC, presencas.data_presenca ASC
    ");
    $stmt->bind_param("iss", $empresa_id, $data_inicio, $data_fim);
    $stmt->execute();
    $colaboradores_financeiro = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Financeiro</title>
    <link rel="stylesheet" href="css/hora_extra.css">
    <style>
        body {
            background-color: #000;
            color: #fff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #555;
        }
        th, td {
            text-align: center;
            padding: 10px;
        }
        th {
            background-color: #444;
            color: #fff;
        }
        .form-container {
            margin: 20px auto;
            text-align: center;
        }
        .form-container input, .form-container select, .form-container button {
            padding: 10px;
            margin: 5px;
            border: none;
            border-radius: 5px;
        }
        .form-container button {
            background-color: #007bff;
            color: #fff;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #0056b3;
        }
        h2, h3 {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2>Contabilidade de Pagamento</h2>

            <div class="form-container">
                <form method="GET" action="financeiro.php">
                    <label for="empresa_id">Selecione a Empresa:</label>
                    <select name="empresa_id" id="empresa_id" required>
                        <option value="">Escolha a empresa</option>
                        <?php while ($empresa = $empresas->fetch_assoc()) { ?>
                            <option value="<?php echo $empresa['id']; ?>" <?php if ($empresa_id == $empresa['id']) echo 'selected'; ?>>
                                <?php echo $empresa['nome_empresa']; ?>
                            </option>
                        <?php } ?>
                    </select>

                    <label for="data_inicio">Data Início (Quinta):</label>
                    <input type="date" name="data_inicio" id="data_inicio" value="<?php echo $data_inicio; ?>" required>

                    <button type="submit">Calcular</button>
                </form>

                <?php if ($empresa_id && $colaboradores_financeiro->num_rows > 0) { ?>
                    <form method="POST" action="exportar_excel_financeiro.php">
                        <input type="hidden" name="empresa_id" value="<?php echo $empresa_id; ?>">
                        <input type="hidden" name="data_inicio" value="<?php echo $data_inicio; ?>">
                        <input type="hidden" name="data_fim" value="<?php echo $data_fim; ?>">
                        <button type="submit">Exportar para Excel</button>
                    </form>
                <?php } ?>
            </div>

            <?php if ($empresa_id && $colaboradores_financeiro->num_rows > 0) { ?>
                <h3>Resumo de Pagamentos (<?php echo date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim)); ?>)</h3>
                <table>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Diária</th>
                        <?php foreach ($dias_exibicao as $dia) { echo "<th>$dia</th>"; } ?>
                        <th>Total</th>
                        <th>PIX</th>
                    </tr>
                    <?php
                    $colaboradores = [];
                    while ($colaborador = $colaboradores_financeiro->fetch_assoc()) {
                        $id = $colaborador['cpf']; // Garantir que CPF é único
                        if (!isset($colaboradores[$id])) {
                            $colaboradores[$id] = [
                                'nome' => $colaborador['nome'],
                                'cpf' => $colaborador['cpf'],
                                'pix' => $colaborador['pix'],
                                'valor_diaria' => $colaborador['valor_diaria'],
                                'presencas' => array_fill_keys($dias_exibicao, 0),
                                'total' => 0
                            ];
                        }

                        $data_presenca = ucfirst(mb_substr($formatter->format(new DateTime($colaborador['data_presenca'])), 0, 3));
                        if (in_array($data_presenca, $dias_exibicao)) {
                            $colaboradores[$id]['presencas'][$data_presenca] = 1;
                            $colaboradores[$id]['total'] += $colaborador['valor_diaria'];
                        }
                    }

                    foreach ($colaboradores as $colaborador) {
                        echo "<tr>";
                        echo "<td>{$colaborador['nome']}</td>";
                        echo "<td>{$colaborador['cpf']}</td>";
                        echo "<td>R$ " . number_format($colaborador['valor_diaria'], 2, ',', '.') . "</td>";
                        foreach ($dias_exibicao as $dia) {
                            echo "<td>" . ($colaborador['presencas'][$dia] ? '1' : '') . "</td>";
                        }
                        echo "<td>R$ " . number_format($colaborador['total'], 2, ',', '.') . "</td>";
                        echo "<td>{$colaborador['pix']}</td>";
                        echo "</tr>";
                    }
                    ?>
                </table>
            <?php } elseif ($empresa_id) { ?>
                <p style="text-align: center;">Nenhum resultado encontrado para o período selecionado.</p>
            <?php } ?>
        </main>
    </div>
</body>
</html>
