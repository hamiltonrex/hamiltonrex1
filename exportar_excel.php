<?php
// exportar_excel.php
include('auth.php');
include('config.php');
require 'vendor/autoload.php'; // Certifique-se de que o PHPSpreadsheet está instalado e carregado

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
$headers = ["Nome", "CPF", "Diária"];

// Configuração para exibir apenas os dias da semana selecionados entre data_inicio e data_fim
$dias_exibicao = [];
$periodo = new DatePeriod(
    new DateTime($data_inicio),
    new DateInterval('P1D'),
    (new DateTime($data_fim))->modify('+1 day')
);

$formatter = new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, null, null, 'E');
foreach ($periodo as $data) {
    $dia = ucfirst($formatter->format($data));
    $dias_exibicao[] = $dia;
    $headers[] = $dia;
}

$headers[] = "Total a Receber";
$headers[] = "PIX";

// Escreve os cabeçalhos na planilha
$colIndex = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue("{$colIndex}1", $header);
    $colIndex++;
}

// Busca os dados dos colaboradores
$stmt = $conn->prepare("
    SELECT colaboradores.id, colaboradores.nome, colaboradores.cpf, colaboradores.pix, vagas.valor_diaria,
           presencas.data_presenca, presencas.presente
    FROM colaboradores
    INNER JOIN candidaturas ON colaboradores.id = candidaturas.colaborador_id
    INNER JOIN vagas ON candidaturas.vaga_id = vagas.id
    LEFT JOIN presencas ON colaboradores.id = presencas.colaborador_id 
                        AND presencas.data_presenca BETWEEN ? AND ?
    WHERE vagas.empresa_id = ? 
    AND vagas.data_vaga BETWEEN ? AND ?
    ORDER BY colaboradores.nome
");
$stmt->bind_param("ssiss", $data_inicio, $data_fim, $empresa_id, $data_inicio, $data_fim);
$stmt->execute();
$result = $stmt->get_result();

// Processa os dados por colaborador
$colaboradores = [];
while ($row = $result->fetch_assoc()) {
    $colaborador_id = $row['id'];
    if (!isset($colaboradores[$colaborador_id])) {
        $colaboradores[$colaborador_id] = [
            'nome' => $row['nome'],
            'cpf' => $row['cpf'],
            'pix' => $row['pix'],
            'valor_diaria' => $row['valor_diaria'],
            'presencas' => array_fill_keys($dias_exibicao, '') // Inicializa com dias vazios
        ];
    }
    if ($row['data_presenca'] && $row['presente'] == 1) {
        $dataPresencaFormatada = ucfirst($formatter->format(new DateTime($row['data_presenca'])));
        $colaboradores[$colaborador_id]['presencas'][$dataPresencaFormatada] = 1; // Marca presença
    }
}

// Preenche a planilha com os dados processados
$row = 2;
foreach ($colaboradores as $colaborador) {
    $sheet->setCellValue("A{$row}", $colaborador['nome']);
    $sheet->setCellValue("B{$row}", $colaborador['cpf']);
    $sheet->setCellValue("C{$row}", 'R$ ' . number_format($colaborador['valor_diaria'], 2, ',', '.'));

    $total_a_receber = 0;
    $colIndex = 'D';
    foreach ($dias_exibicao as $dia) {
        $presente = $colaborador['presencas'][$dia];
        $sheet->setCellValue("{$colIndex}{$row}", $presente);
        if ($presente) {
            $total_a_receber += $colaborador['valor_diaria'];
        }
        $colIndex++;
    }

    // Escreve o total a receber e o PIX
    $sheet->setCellValue("{$colIndex}{$row}", 'R$ ' . number_format($total_a_receber, 2, ',', '.'));
    $sheet->setCellValue(++$colIndex . "{$row}", $colaborador['pix']);
    
    $row++;
}

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
