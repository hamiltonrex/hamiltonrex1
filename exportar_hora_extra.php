<?php
require 'vendor/autoload.php'; // PHPSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

include('auth.php');
include('config.php');

// Exibir erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Verificar se os parâmetros necessários foram passados
if (!isset($_GET['empresa_id']) || !isset($_GET['semana_inicio']) || !isset($_GET['semana_fim'])) {
    die("Erro: Parâmetros 'empresa_id', 'semana_inicio' e 'semana_fim' são obrigatórios.");
}

$empresa_id = $_GET['empresa_id'];
$semana_inicio = $_GET['semana_inicio'];
$semana_fim = $_GET['semana_fim'];

// Consulta para buscar os dados necessários
$query = "
    SELECT 
        colaboradores.nome,
        colaboradores.cpf,
        presencas.data_presenca AS data,
        vagas.valor_diaria AS diaria,
        vagas.data_vaga AS entrada
    FROM presencas
    INNER JOIN colaboradores ON presencas.colaborador_id = colaboradores.id
    INNER JOIN vagas ON presencas.vaga_id = vagas.id
    WHERE presencas.presente = 1
    AND vagas.empresa_id = ?
    AND presencas.data_presenca BETWEEN ? AND ?
    ORDER BY presencas.data_presenca ASC, vagas.data_vaga ASC, colaboradores.nome ASC";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Erro na consulta SQL: " . $conn->error);
}

$stmt->bind_param("iss", $empresa_id, $semana_inicio, $semana_fim);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    die("Nenhum registro encontrado para os parâmetros fornecidos.");
}

// Criar a planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Cabeçalho da empresa
$sheet->mergeCells("A1:F1");
$sheet->setCellValue("A1", "Relatório de Horas Extras");
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCE');

// Cabeçalho das colunas
$cabecalho = ["Data", "Nome", "CPF", "Entrada", "Diária", "Total a Pagar"];
$sheet->fromArray($cabecalho, NULL, "A2");

// Estilizar o cabeçalho
$sheet->getStyle("A2:F2")->getFont()->setBold(true);
$sheet->getStyle("A2:F2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A2:F2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE699');
$sheet->getStyle("A2:F2")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Preencher os dados
$linha = 3;
while ($row = $result->fetch_assoc()) {
    $total = $row['diaria']; // Valor total (caso precise de cálculo, ajuste aqui)
    $dados = [
        date('d/m/Y', strtotime($row['data'])), // Data
        strtoupper($row['nome']), // Nome
        $row['cpf'], // CPF
        date('H:i:s', strtotime($row['entrada'])), // Entrada
        'R$ ' . number_format($row['diaria'], 2, ',', '.'), // Diária
        'R$ ' . number_format($total, 2, ',', '.') // Total a Pagar
    ];
    $sheet->fromArray($dados, NULL, "A$linha");

    // Estilizar as células da linha
    $sheet->getStyle("A$linha:F$linha")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("A$linha:F$linha")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $linha++;
}

// Ajustar largura das colunas
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Exportar a planilha
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Relatorio_Horas_Extras.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
