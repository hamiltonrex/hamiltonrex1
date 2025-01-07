<?php
// index.php

// Habilitar exibição de erros para diagnóstico (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('config.php'); // Conexão com o banco de dados
include('auth.php');   // Verificação de autenticação e session_start()

// Função para formatar CPF e RG (pode ser utilizada conforme necessidade)
function formatarCpfRg($valor) {
    return preg_replace('/\D/', '', $valor); // Remove qualquer caractere não numérico
}

// Verificar se o formulário foi enviado para fundir duplicados
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $duplicados_cpf = $_POST['duplicados_cpf'] ?? []; // Duplicados por CPF
    $duplicados_rg = $_POST['duplicados_rg'] ?? [];   // Duplicados por RG

    // Função para fundir duplicados
    function fundirDuplicados($conn, $duplicados, $tipo) {
        foreach ($duplicados as $chave => $ids) {
            if (empty($ids)) continue;

            // Assegurar que $ids é uma string antes de usar explode
            if (!is_string($ids)) {
                continue;
            }

            $ids_array = explode(',', $ids);
            $ids_array = array_map('intval', $ids_array); // Converter para inteiros
            $principal_id = min($ids_array); // Sempre usa o menor ID como principal
            $outros_ids = array_diff($ids_array, [$principal_id]);

            if (count($outros_ids) > 0) {
                $outros_ids_str = implode(',', $outros_ids);

                // Atualizar as referências nas tabelas relacionadas
                $tabelas_relacionadas = ['presencas', 'candidaturas']; // Adicione outras tabelas relacionadas aqui
                foreach ($tabelas_relacionadas as $tabela) {
                    // Preparar a consulta
                    $sql = "UPDATE $tabela SET colaborador_id = ? WHERE colaborador_id IN ($outros_ids_str)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param('i', $principal_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        die("Erro ao preparar a consulta para a tabela $tabela: " . $conn->error);
                    }
                }

                // Aqui você pode adicionar lógica para unir outros campos, se necessário
                // Por exemplo, unificar emails, telefones, etc.

                // Remover os registros duplicados da tabela `colaboradores`
                $sql = "DELETE FROM colaboradores WHERE id IN ($outros_ids_str)";
                if (!$conn->query($sql)) {
                    die("Erro ao remover duplicados: " . $conn->error);
                }
            }
        }
    }

    // Fundir duplicados por CPF
    if (!empty($duplicados_cpf)) {
        fundirDuplicados($conn, $duplicados_cpf, 'CPF');
    }

    // Fundir duplicados por RG
    if (!empty($duplicados_rg)) {
        fundirDuplicados($conn, $duplicados_rg, 'RG');
    }

    $success = "Duplicados fundidos com sucesso!";
}

// Função para buscar duplicados com base em um campo específico (CPF ou RG)
function buscarDuplicados($conn, $campo) {
    $campo_limpo = ($campo == 'cpf') ? "REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '')" : "REPLACE(REPLACE(REPLACE(rg, '.', ''), '-', ''), ' ', '')";
    $sql = "
        SELECT $campo_limpo AS chave_limpa, GROUP_CONCAT(id) AS ids, GROUP_CONCAT(nome) AS nomes, GROUP_CONCAT($campo) AS valores
        FROM colaboradores
        GROUP BY chave_limpa
        HAVING COUNT(*) > 1
        ORDER BY chave_limpa
    ";
    return $conn->query($sql);
}

// Buscar CPFs duplicados no banco
$duplicados_cpf_result = buscarDuplicados($conn, 'cpf');

// Buscar RGs duplicados no banco
$duplicados_rg_result = buscarDuplicados($conn, 'rg');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Duplicados</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #000;
            color: #fff;
            margin: 0;
            padding: 0;
        }

        .duplicados-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #333;
            border-radius: 8px;
            background-color: #222;
        }

        h1 {
            text-align: center;
            color: #fff;
        }

        h2 {
            margin-top: 40px;
            color: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table th, table td {
            padding: 10px;
            text-align: left;
            border: 1px solid #444;
        }

        table th {
            background-color: #444;
            color: #fff;
        }

        table tr:nth-child(even) {
            background-color: #333;
        }

        table tr:nth-child(odd) {
            background-color: #222;
        }

        .success {
            color: #4caf50;
            font-weight: bold;
            text-align: center;
        }

        button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #0056b3;
        }

        .highlight {
            color: #007bff;
            font-weight: bold;
        }

        .checkbox-container {
            text-align: center;
        }

        .checkbox-container input {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="duplicados-container">
        <h1>Gerenciar Duplicados</h1>

        <?php if (isset($success)) { ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php } ?>

        <!-- Duplicados por CPF -->
        <h2>Duplicados por CPF</h2>
        <?php if ($duplicados_cpf_result && $duplicados_cpf_result->num_rows > 0) { ?>
            <form method="POST" action="index.php">
                <table>
                    <thead>
                        <tr>
                            <th>CPF</th>
                            <th>Nome</th>
                            <th>RG</th>
                            <th>IDs (Duplicados)</th>
                            <th>Selecionar para Fundir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $duplicados_cpf_result->fetch_assoc()) { 
                            $ids = explode(',', $row['ids']);
                            $nomes = explode(',', $row['nomes']);
                            $rgs = explode(',', $row['valores']); // RGs
                            ?>
                            <tr>
                                <td class="highlight"><?php echo htmlspecialchars($row['chave_limpa']); ?></td>
                                <td>
                                    <ul>
                                        <?php foreach ($nomes as $nome) { ?>
                                            <li><?php echo htmlspecialchars($nome); ?></li>
                                        <?php } ?>
                                    </ul>
                                </td>
                                <td>
                                    <ul>
                                        <?php foreach ($rgs as $rg) { ?>
                                            <li><?php echo htmlspecialchars($rg); ?></li>
                                        <?php } ?>
                                    </ul>
                                </td>
                                <td>
                                    <ul>
                                        <?php foreach ($ids as $id) { ?>
                                            <li>ID: <?php echo htmlspecialchars($id); ?></li>
                                        <?php } ?>
                                    </ul>
                                </td>
                                <td class="checkbox-container">
                                    <input type="checkbox" name="duplicados_cpf[<?php echo htmlspecialchars($row['chave_limpa']); ?>]" value="<?php echo htmlspecialchars($row['ids']); ?>">
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
        <?php } else { ?>
            <p class="success">Nenhum duplicado de CPF encontrado!</p>
        <?php } ?>

        <!-- Duplicados por RG -->
        <h2>Duplicados por RG</h2>
        <?php if ($duplicados_rg_result && $duplicados_rg_result->num_rows > 0) { ?>
            <table>
                <thead>
                    <tr>
                        <th>RG</th>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>IDs (Duplicados)</th>
                        <th>Selecionar para Fundir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $duplicados_rg_result->fetch_assoc()) { 
                        $ids = explode(',', $row['ids']);
                        $nomes = explode(',', $row['nomes']);
                        $cpfs = explode(',', $row['valores']); // CPFs
                        ?>
                        <tr>
                            <td class="highlight"><?php echo htmlspecialchars($row['chave_limpa']); ?></td>
                            <td>
                                <ul>
                                    <?php foreach ($nomes as $nome) { ?>
                                        <li><?php echo htmlspecialchars($nome); ?></li>
                                    <?php } ?>
                                </ul>
                            </td>
                            <td>
                                <ul>
                                    <?php foreach ($cpfs as $cpf) { ?>
                                        <li><?php echo htmlspecialchars($cpf); ?></li>
                                    <?php } ?>
                                </ul>
                            </td>
                            <td>
                                <ul>
                                    <?php foreach ($ids as $id) { ?>
                                        <li>ID: <?php echo htmlspecialchars($id); ?></li>
                                    <?php } ?>
                                </ul>
                            </td>
                            <td class="checkbox-container">
                                <input type="checkbox" name="duplicados_rg[<?php echo htmlspecialchars($row['chave_limpa']); ?>]" value="<?php echo htmlspecialchars($row['ids']); ?>">
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <p class="success">Nenhum duplicado de RG encontrado!</p>
        <?php } ?>

        <?php if (($duplicados_cpf_result && $duplicados_cpf_result->num_rows > 0) || ($duplicados_rg_result && $duplicados_rg_result->num_rows > 0)) { ?>
                <button type="submit">Fundir Duplicados Selecionados</button>
            </form>
        <?php } ?>
    </div>
</body>
</html>

<!-- Scripts de Publicidade e Outros -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1092502767568075"
     crossorigin="anonymous"></script>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XE8FE6P03F"></script>
<center>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-XE8FE6P03F');
    </script>
</center>
