<?php
include('config.php');

// Definir o fuso horário para evitar erros com as datas
date_default_timezone_set('America/Sao_Paulo');

// Consulta as vagas disponíveis (somente as futuras)
$query = "SELECT vagas.*, empresas.nome_empresa, 
          (SELECT COUNT(*) FROM candidaturas WHERE candidaturas.vaga_id = vagas.id) AS total_colaboradores
          FROM vagas 
          INNER JOIN empresas ON vagas.empresa_id = empresas.id
          WHERE vagas.data_vaga > NOW()
          ORDER BY vagas.data_vaga ASC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Vagas Disponíveis</title>
    <link rel="stylesheet" href="css/lista_disponivel.css"> <!-- CSS para a página -->
</head>
<body>
    <!-- Cabeçalho -->
    <header>
        <h1>Lista de Vagas Disponíveis</h1>
    </header>

    <!-- Menu de navegação -->
    <nav>
            <a href="index.php">Início</a>
            <a href="lista_disponivel.php">Lista de Vagas</a>
            <a href="sobre.php">Sobre</a>
            <a href="politica-privacidade.php">Política de Privacidade</a>
            <a href="termos_de_uso.php">Termos de Uso</a> <!-- Página de Termos de Uso -->
    </nav>

    <!-- Container das vagas -->
    <div class="vaga-container">
        <?php
        // Verifica se há vagas disponíveis
        if ($result->num_rows > 0) {
            while ($vaga = $result->fetch_assoc()) {
                $vaga_id = $vaga['link_vaga'];
                $vaga_data = new DateTime($vaga['data_vaga']);
                $data_formatada = $vaga_data->format('d/m/Y H:i');
                $total_colaboradores = $vaga['total_colaboradores'];
                $quantidade_colaboradores = $vaga['quantidade_colaboradores'];
                $vaga_em_vermelho = ($total_colaboradores >= $quantidade_colaboradores) ? true : false;
                
                // Verifica se a vaga já passou do horário
                if ($vaga_data > new DateTime()) {
                    ?>
                    <div class="vaga-card <?php echo $vaga_em_vermelho ? 'vaga-cheia' : ''; ?>">
                        <h3><?php echo htmlspecialchars($vaga['nome_empresa']); ?> - <?php echo $data_formatada; ?></h3>
                        <p><strong>Quantidade de Colaboradores:</strong> <?php echo $vaga['quantidade_colaboradores']; ?></p>
                        <p><strong>Valor da Diária:</strong> R$ <?php echo number_format($vaga['valor_diaria'], 2, ',', '.'); ?></p>
                        <a class="link-vaga" href="candidatura.php?vaga_id=<?php echo $vaga_id; ?>" target="_blank">Cadastrar-se</a>
                    </div>
                    <?php
                }
            }
        } else {
            echo "<p class='no-vagas'>Nenhuma vaga disponível no momento.</p>";
        }
        ?>
    </div>

    <!-- Rodapé -->
    <footer>
        <p>© 2024 - Todos os direitos reservados. <a href="sobre.php">Sobre nós</a></p>
    </footer>
</body>
</html>
