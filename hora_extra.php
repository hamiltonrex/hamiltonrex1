<?php
include('auth.php');
include('config.php');

// Buscar empresas para o campo de seleção
$empresas = $conn->query("SELECT id, nome_empresa FROM empresas");

// Variáveis para filtros
$empresa_id = $_GET['empresa_id'] ?? null;
$semana_inicio = $_GET['semana_inicio'] ?? null;
$semana_fim = $_GET['semana_fim'] ?? null;

// Inicialização da variável de colaboradores
$colaboradores = [];

// Buscar colaboradores confirmados na semana e na empresa selecionada
if ($empresa_id && $semana_inicio && $semana_fim) {
    $query = "
        SELECT 
            presencas.data_presenca AS data,
            colaboradores.nome AS nome,
            colaboradores.cpf AS cpf,
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
    $stmt->bind_param("iss", $empresa_id, $semana_inicio, $semana_fim);
    $stmt->execute();
    $colaboradores = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Hora Extra</title>
    <link rel="stylesheet" href="css/hora_extra.css">
</head>
<body>
    <div class="dashboard">
        <?php include('sidebar.php'); ?>
        <main>
            <h2 class="titulo-pagina">Relatório de Hora Extra</h2>

            <!-- Formulário de Filtros -->
            <form method="GET" action="hora_extra.php" class="filtro-busca">
                <div class="campo">
                    <label for="empresa_id">Empresa:</label>
                    <select name="empresa_id" id="empresa_id" required>
                        <option value="">Selecione a empresa</option>
                        <?php while ($empresa = $empresas->fetch_assoc()) { ?>
                            <option value="<?php echo $empresa['id']; ?>" <?php if ($empresa_id == $empresa['id']) echo 'selected'; ?>>
                                <?php echo $empresa['nome_empresa']; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="semana_inicio">Data Início:</label>
                    <input type="date" id="semana_inicio" name="semana_inicio" value="<?php echo $semana_inicio; ?>" required>
                </div>
                <div class="campo">
                    <label for="semana_fim">Data Fim:</label>
                    <input type="date" id="semana_fim" name="semana_fim" value="<?php echo $semana_fim; ?>" required>
                </div>
                <button type="submit" class="botao-filtro">Filtrar</button>
            </form>

            <!-- Botão de Exportação -->
            <?php if ($colaboradores && $colaboradores->num_rows > 0) { ?>
                <form method="GET" action="exportar_hora_extra.php" class="form-exportar">
                    <input type="hidden" name="empresa_id" value="<?php echo $empresa_id; ?>">
                    <input type="hidden" name="semana_inicio" value="<?php echo $semana_inicio; ?>">
                    <input type="hidden" name="semana_fim" value="<?php echo $semana_fim; ?>">
                    <button type="submit" class="botao-exportar">Exportar para Excel</button>
                </form>
            <?php } ?>

            <!-- Tabela de Resultados -->
            <?php if ($colaboradores && $colaboradores->num_rows > 0) { ?>
                <table class="tabela">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>MATR.</th>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>OPERAÇÃO</th>
                            <th>Entrada</th>
                            <th>Tempo Pausa</th>
                            <th>Saída</th>
                            <th>Diária</th>
                            <th>Total a Pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($colaborador = $colaboradores->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($colaborador['data'])); ?></td>
                                <td><?php echo ''; ?></td> <!-- Matrícula vazia -->
                                <td><?php echo strtoupper($colaborador['nome']); ?></td>
                                <td><?php echo $colaborador['cpf']; ?></td>
                                <td></td> <!-- Operação vazia -->
                                <td><?php echo date('H:i:s', strtotime($colaborador['entrada'])); ?></td> <!-- Horário de entrada -->
                                <td></td> <!-- Tempo Pausa vazio -->
                                <td></td> <!-- Saída vazio -->
                                <td>R$ <?php echo number_format($colaborador['diaria'], 2, ',', '.'); ?></td>
                                <td></td> <!-- Total a pagar vazio -->
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else if ($_GET) { ?>
                <p class="mensagem-aviso">Nenhum registro encontrado para os filtros selecionados.</p>
            <?php } ?>
        </main>
    </div>
</body>
</html>
