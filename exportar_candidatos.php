<?php
require 'vendor/autoload.php'; // Certifique-se de ter o PHPSpreadsheet instalado

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

include('auth.php');
include('config.php');

$vaga_id = $_GET['vaga_id'];

// Obter detalhes da vaga
$stmt = $conn->prepare("SELECT vagas.*, empresas.nome_empresa FROM vagas INNER JOIN empresas ON vagas.empresa_id = empresas.id WHERE vagas.id = ?");
$stmt->bind_param("i", $vaga_id);
$stmt->execute();
$vaga_result = $stmt->get_result();
$vaga = $vaga_result->fetch_assoc();

// Obter candidatos
$stmt = $conn->prepare("SELECT colaboradores.* FROM candidaturas INNER JOIN colaboradores ON candidaturas.colaborador_id = colaboradores.id WHERE candidaturas.vaga_id = ?");
$stmt->bind_param("i", $vaga_id);
$stmt->execute();
$candidatos = $stmt->get_result();

// Criar planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Nome da empresa e controle de horário
$nome_empresa = $vaga['nome_empresa'];
$data = date('d/m/Y', strtotime($vaga['data_vaga']));


// Definir cabeçalho com nome da empresa
$sheet->mergeCells("A1:L1");
$sheet->setCellValue("A1", "$nome_empresa");
$sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCE'); // Fundo verde claro

// Linha de informações de controle de horário e data
$sheet->mergeCells("A2:L2");
$sheet->setCellValue("A2", "Controle de Horário: $horario - Data: $data");
$sheet->getStyle("A2")->getFont()->setBold(true);
$sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE699'); // Fundo amarelo claro

// Cabeçalho da tabela com as novas colunas
$cabecalho = ["QTD", "", "NOME", "CPF", "E-MAIL", "CELULAR", "PIX", "RG", "ENTRADA", "PAUSA", "RETORNO", "SAÍDA", "ASSINATURA"];
$sheet->fromArray($cabecalho, NULL, "A3");

// Estilos do cabeçalho
$sheet->getStyle("A3:L3")->getFont()->setBold(true);
$sheet->getStyle("A3:L3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A3:L3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFB6D7A8'); // Fundo verde mais escuro
$sheet->getStyle("A3:L3")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Preencher dados dos candidatos com as novas colunas
$linha = 4;
$contador = 1;
while ($candidato = $candidatos->fetch_assoc()) {
    $dados = [
        $contador,
        "", // Espaço vazio para "",
        strtoupper($candidato['nome']), // Nome em maiúsculo
        $candidato['cpf'],
        $candidato['email'], // Campo E-mail
        $candidato['telefone'], // Campo Celular
        $candidato['pix'], // PIX do colaborador
        $candidato['rg'],
        "", "", "", "", "" // Colunas para Entrada, Pausa, Retorno, Saída e Assinatura
    ];
    $sheet->fromArray($dados, NULL, "A$linha");

    // Centralizar todos os valores na linha
    $sheet->getStyle("A$linha:L$linha")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A$linha:L$linha")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $linha++;
    $contador++;
}

// Ajustar largura das colunas
foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Exportar arquivo
$nome_arquivo = "Candidatos_Vaga_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="'. $nome_arquivo .'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
