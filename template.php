<?php
require 'vendor/autoload.php';  // Inclua o autoload do PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include('auth.php');
include('config.php');

// Obter dados de filtro
$empresa_id = $_GET['empresa_id'] ?? null;
$data_inicio = $_GET['data_inicio'] ?? null;
$data_fim = $_GET['data_fim'] ?? null;

// Verificar se os dados estão completos
if (!$empresa_id || !$data_inicio || !$data_fim) {
    die("Por favor, selecione uma empresa e um período.");
}

// Carregar o modelo de Excel
$templatePath = 'templates/modelo_financeiro.xlsx';
$spreadsheet = IOFactory::load($templatePath);

// Selecionar a planilha (aba) correta
$sheet = $spreadsheet->getActiveSheet();

// Consulta SQL para obter dados financeiros dos colaboradores
$stmt = $conn->prepare("
    SELECT colaboradores.nome, colaboradores.cpf, colaboradores.pix, vagas.valor_diaria, presencas.data_presenca, presencas.presente
    FROM colaboradores
    INNER JOIN candidaturas ON colaboradores.id = candidaturas.colaborador_id
    INNER JOIN vagas ON candidaturas.vaga_id = vagas.id
    LEFT JOIN presencas ON colaboradores.id = presencas.colaborador_id 
                        AND presencas.data_presenca BETWEEN ? AND ?
    WHERE vagas.empresa_id = ? 
    AND vagas.data_vaga BETWEEN ? AND ?
    ORDER BY colaboradores.nome, presencas.data_presenca
");
$stmt->bind_param("ssiss", $data_inicio, $data_fim, $empresa_id, $data_inicio, $data_fim);
$stmt->execute();
$result = $stmt->get_result();

// Configuração de células iniciais e mapeamento de dias da semana
$startRow = 9; // Linha inicial para os dados dos funcionários
$currentRow = $startRow;

// Processar cada colaborador e preencher no modelo
$colaboradores = [];
while ($row = $result->fetch_assoc()) {
    $nome = $row['nome'];
    $cpf = $row['cpf'];
    $diaria = $row['valor_diaria'];
    $dataPresenca = $row['data_presenca'];
    $presente = $row['presente'];

    // Organizar presenças
    $dataObj = new DateTime($dataPresenca);
    $diaSemana = $dataObj->format('D'); // Exemplo: Dom, Seg...

    // Preencher dados do colaborador
    if (!isset($colaboradores[$cpf])) {
        $colaboradores[$cpf] = [
            'nome' => $nome,
            'cpf' => $cpf,
            'diaria' => $diaria,
            'presencas' => [],
            'total' => 0,
            'pix' => $row['pix']
        ];
    }

    // Marcar presença e acumular o total
    $colaboradores[$cpf]['presencas'][$diaSemana] = $presente;
    if ($presente == 1) {
        $colaboradores[$cpf]['total'] += $diaria;
    }
}

// Inserir dados no Excel
foreach ($colaboradores as $colaborador) {
    $sheet->setCellValue("A{$currentRow}", $colaborador['nome']); // Nome
    $sheet->setCellValue("B{$currentRow}", $colaborador['cpf']);  // CPF
    $sheet->setCellValue("K{$currentRow}", 'R$ ' . number_format($colaborador['total'], 2, ',', '.')); // Total a Receber
    $sheet->setCellValue("R{$currentRow}", $colaborador['pix']);  // Chave PIX

    // Marcar presenças (ajuste conforme a coluna correspondente para cada dia)
    $dias = ['Dom' => 'H', 'Seg' => 'I', 'Ter' => 'J', 'Qua' => 'K', 'Qui' => 'L', 'Sex' => 'M', 'Sab' => 'N'];
    foreach ($dias as $dia => $coluna) {
        $presenca = isset($colaborador['presencas'][$dia]) ? '1' : '';
        $sheet->setCellValue("{$coluna}{$currentRow}", $presenca);
    }

    $currentRow++;
}

// Gerar o arquivo final para download
$writer = new Xlsx($spreadsheet);
$filename = 'Relatorio_Financeiro_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
