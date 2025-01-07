<?php
include('config.php');

// Consultar colaboradores com CPF ou RG que contenham pontos, vírgulas ou hífens
$query = "SELECT id, nome, cpf, rg FROM colaboradores WHERE cpf REGEXP '[.,-]' OR rg REGEXP '[.,-]'";
$result = $conn->query($query);

// Verificar se houve erro na consulta SQL
if (!$result) {
    die("Erro na consulta SQL: " . $conn->error);
}

// Verificar se o botão de correção foi clicado
if (isset($_POST['corrigir'])) {
    // Limpar CPF e RG de todos os colaboradores
    $update_query = "UPDATE colaboradores
                     SET cpf = REPLACE(REPLACE(REPLACE(cpf, '.', ''), ',', ''), '-', ''),
                         rg = REPLACE(REPLACE(REPLACE(rg, '.', ''), ',', ''), '-', '')";
    if ($conn->query($update_query) === TRUE) {
        $success = "Todos os CPFs e RGs foram corrigidos com sucesso!";
    } else {
        $error = "Erro ao corrigir os CPFs e RGs: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ponto de Correção - CPF e RG</title>
    <link rel="stylesheet" href="css/rex.css">
    <style>
        .error {
            color: red;
            font-size: 18px;
        }
        .success {
            color: green;
            font-size: 18px;
        }
        .colaboradores-container {
            margin-top: 20px;
        }
        .colaboradores-list {
            list-style-type: none;
            padding: 0;
        }
        .colaboradores-list li {
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }
        button {
            background-color: #008CBA;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background-color: #006F8F;
        }
    </style>
</head>
<body>
    <div class="candidatura-container">
        <h1>Ponto de Correção - Colaboradores com CPF ou RG inválido</h1>

        <?php if (isset($success)) { echo "<p class='success'>$success</p>"; } ?>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>

        <div class="colaboradores-container">
            <h2>Colaboradores com CPF ou RG inválido</h2>
            <ul class="colaboradores-list">
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<li>Nome: " . $row['nome'] . " - CPF: " . $row['cpf'] . " - RG: " . $row['rg'] . "</li>";
                    }
                } else {
                    echo "<li>Nenhum colaborador encontrado com CPF ou RG inválido.</li>";
                }
                ?>
            </ul>
        </div>

        <!-- Botão para corrigir todos os CPFs e RGs -->
        <form method="POST" action="">
            <button type="submit" name="corrigir">Corrigir Todos os CPFs e RGs</button>
        </form>
    </div>
</body>
</html>
