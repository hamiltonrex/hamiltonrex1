<?php
include('auth.php');
include('config.php');
require 'vendor/autoload.php'; // PHPSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Verifica se os parâmetros necessários foram enviados
if (!isset($_POST['empresa_id'], $_POST['data_inicio'], $_POST['data_fim'])) {
    die("Parâmetros faltando.");
}

$empresa_id = $_POST['empresa_id'];
$data_inicio = $_POST['data_inicio'];
$data_fim = $_POST['data_fim'];

// Configura a planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Financeiro");

// Cabeçalhos iniciais
$headers = ["NOME", "CPF", "DIÁRIA"];

// Configuração para exibir os dias da semana no intervalo selecionado
$dias_exibicao = [];
$periodo = new DatePeriod(
    new DateTime($data_inicio),
    new DateInterval('P1D'),
    (new DateTime($data_fim))->modify('+1 day')
);

$formatter = new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'E');
foreach ($periodo as $data) {
    $dia = ucfirst(mb_substr($formatter->format($data), 0, 3));
    $dias_exibicao[] = $dia;
    $headers[] = $dia;
}

// Adiciona "TOTAL" e "PIX" no cabeçalho
$headers[] = "TOTAL";
$headers[] = "PIX";

// Escreve os cabeçalhos na planilha
$colIndex = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue("{$colIndex}1", $header);
    $colIndex++;
}

// Aplica estilo aos cabeçalhos
$sheet->getStyle("A1:{$colIndex}1")->getFont()->setBold(true);
$sheet->getStyle("A1:{$colIndex}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A1:{$colIndex}1")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Consulta os dados dos colaboradores
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
$result = $stmt->get_result();

// Processa os dados por colaborador
$colaboradores = [];
$totalGeral = 0; // Variável para acumular o total geral
while ($row = $result->fetch_assoc()) {
    $colaborador_id = $row['cpf']; // Usa CPF como identificador único
    if (!isset($colaboradores[$colaborador_id])) {
        $colaboradores[$colaborador_id] = [
            'nome' => strtoupper($row['nome']), // Nome em maiúsculas
            'cpf' => $row['cpf'],
            'pix' => $row['pix'],
            'valor_diaria' => $row['valor_diaria'],
            'presencas' => array_fill_keys($dias_exibicao, 0), // Inicializa com dias vazios
            'total' => 0
        ];
    }

    if ($row['data_presenca']) {
        $dataPresencaFormatada = ucfirst(mb_substr($formatter->format(new DateTime($row['data_presenca'])), 0, 3));
        if (in_array($dataPresencaFormatada, $dias_exibicao)) {
            $colaboradores[$colaborador_id]['presencas'][$dataPresencaFormatada] = 1; // Marca presença
            $colaboradores[$colaborador_id]['total'] += $row['valor_diaria']; // Adiciona ao total
        }
    }
    $totalGeral += $row['valor_diaria']; // Acumula o total geral
}

// Preenche a planilha com os dados processados
$rowIndex = 2;
foreach ($colaboradores as $colaborador) {
    $sheet->setCellValue("A{$rowIndex}", $colaborador['nome']);
    $sheet->setCellValue("B{$rowIndex}", $colaborador['cpf']);
    $sheet->setCellValue("C{$rowIndex}", 'R$ ' . number_format($colaborador['valor_diaria'], 2, ',', '.'));

    $colIndex = 'D';
    foreach ($dias_exibicao as $dia) {
        $sheet->setCellValue("{$colIndex}{$rowIndex}", $colaborador['presencas'][$dia] ? '1' : '');
        $colIndex++;
    }

    // Escreve o Total e o PIX
    $sheet->setCellValue("{$colIndex}{$rowIndex}", 'R$ ' . number_format($colaborador['total'], 2, ',', '.'));
    $sheet->setCellValue(++$colIndex . "{$rowIndex}", $colaborador['pix']);

    // Centraliza os dados da linha
    $sheet->getStyle("A{$rowIndex}:{$colIndex}{$rowIndex}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowIndex++;
}

// Adiciona o total geral na última linha
$rowIndex++;
$sheet->setCellValue("A{$rowIndex}", "TOTAL GERAL");
$sheet->mergeCells("A{$rowIndex}:B{$rowIndex}");
$sheet->setCellValue("C{$rowIndex}", 'R$ ' . number_format($totalGeral, 2, ',', '.'));
$sheet->getStyle("A{$rowIndex}:C{$rowIndex}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Ajusta a largura das colunas para que o conteúdo caiba corretamente
foreach (range('A', $colIndex) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Salva o arquivo como um Excel para download
$writer = new Xlsx($spreadsheet);
$filename = 'Financeiro_Export_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
?>
