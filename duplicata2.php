<?php
include('auth.php');
include('config.php');

// Detectar duplicados no banco de dados
$query_duplicados = "
    SELECT 
        c1.id AS id1, c2.id AS id2,
        c1.nome AS nome1, c2.nome AS nome2,
        c1.cpf AS cpf1, c2.cpf AS cpf2,
        c1.rg AS rg1, c2.rg AS rg2
    FROM colaboradores c1
    INNER JOIN colaboradores c2 
        ON c1.id != c2.id 
        AND (
            c1.cpf = c2.cpf 
            OR c1.rg = c2.rg
            OR c1.nome = c2.nome
        )
    ORDER BY c1.nome, c1.cpf, c1.rg
";

$result_duplicados = $conn->query($query_duplicados);

$duplicados = [];
if ($result_duplicados->num_rows > 0) {
    while ($row = $result_duplicados->fetch_assoc()) {
        $duplicados[] = $row;
    }
}

// Resolver duplicados (fundir registros)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_duplicatas'])) {
    foreach ($_POST['duplicatas'] as $duplicata) {
        $ids = explode(',', $duplicata); // IDs de duplicados enviados
        $principal_id = min($ids); // Selecionar o menor ID como principal
        $duplicado_id = max($ids); // ID duplicado a ser fundido

        // Atualizar todas as referências de tabelas relacionadas para o ID principal
        $tables_to_update = ['presencas', 'candidaturas', 'vagas'];
        foreach ($tables_to_update as $table) {
            $conn->query("UPDATE $table SET colaborador_id = $principal_id WHERE colaborador_id = $duplicado_id");
        }

        // Excluir o registro duplicado
        $conn->query("DELETE FROM colaboradores WHERE id = $duplicado_id");
    }

    echo "<script>alert('Duplicatas resolvidas com sucesso!'); window.location.href='duplicata2.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Duplicados</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #222;
            color: #fff;
            margin: 0;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            border: 1px solid #444;
            padding: 10px;
            text-align: center;
        }
        table th {
            background-color: #333;
        }
        .button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 10px 0;
            cursor: pointer;
        }
        .button:hover {
            background-color: #45a049;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
        }
        .no-duplicates {
            text-align: center;
            color: #ccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerenciar Duplicados</h1>

        <?php if (!empty($duplicados)) { ?>
            <form method="POST" action="duplicata2.php">
                <table>
                    <thead>
                        <tr>
                            <th>ID 1</th>
                            <th>Nome 1</th>
                            <th>CPF 1</th>
                            <th>RG 1</th>
                            <th>ID 2</th>
                            <th>Nome 2</th>
                            <th>CPF 2</th>
                            <th>RG 2</th>
                            <th>Selecionar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicados as $duplicado) { ?>
                            <tr>
                                <td><?php echo $duplicado['id1']; ?></td>
                                <td><?php echo strtoupper($duplicado['nome1']); ?></td>
                                <td><?php echo $duplicado['cpf1']; ?></td>
                                <td><?php echo $duplicado['rg1']; ?></td>
                                <td><?php echo $duplicado['id2']; ?></td>
                                <td><?php echo strtoupper($duplicado['nome2']); ?></td>
                                <td><?php echo $duplicado['cpf2']; ?></td>
                                <td><?php echo $duplicado['rg2']; ?></td>
                                <td>
                                    <input type="checkbox" name="duplicatas[]" value="<?php echo $duplicado['id1'] . ',' . $duplicado['id2']; ?>">
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <button type="submit" name="resolve_duplicatas" class="button">Resolver Duplicatas</button>
            </form>
        <?php } else { ?>
            <p class="no-duplicates">Nenhum registro duplicado encontrado.</p>
        <?php } ?>
    </div>
</body>
</html>
