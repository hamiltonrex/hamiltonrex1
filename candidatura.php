<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");

include('config.php');

// Função para registrar logs
function logAction($conn, $tipo_evento, $usuario_id = null, $tabela_afetada = null, $registro_id = null, $mensagem = null, $detalhes = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO logs (tipo_evento, usuario_id, tabela_afetada, registro_id, mensagem, detalhes, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        error_log("Erro ao preparar a consulta de log: " . $conn->error);
        return false;
    }
    $stmt->bind_param("sississ", $tipo_evento, $usuario_id, $tabela_afetada, $registro_id, $mensagem, $detalhes, $ip_address);
    if (!$stmt->execute()) {
        error_log("Erro ao executar a consulta de log: " . $stmt->error);
        return false;
    }
    return true;
}


$vaga_id = $_GET['vaga_id'] ?? null;

if (!$vaga_id) {
    die("Vaga não encontrada.");
}

$stmt = $conn->prepare("SELECT vagas.*, empresas.nome_empresa FROM vagas INNER JOIN empresas ON vagas.empresa_id = empresas.id WHERE vagas.link_vaga = ?");
if (!$stmt) {
    die('Erro ao preparar a consulta de vaga: ' . $conn->error);
}
$stmt->bind_param("s", $vaga_id);
$stmt->execute();
$vaga_result = $stmt->get_result();

if ($vaga_result->num_rows == 0) {
    die("Vaga não encontrada.");
}

$vaga = $vaga_result->fetch_assoc();

$colaboradores_stmt = $conn->prepare("
    SELECT DISTINCT colaboradores.nome, pontos_encontro.nome AS ponto_encontro
    FROM candidaturas
    INNER JOIN colaboradores ON candidaturas.colaborador_id = colaboradores.id
    LEFT JOIN pontos_encontro ON candidaturas.ponto_encontro_id = pontos_encontro.id
    WHERE candidaturas.vaga_id = ? 
    ORDER BY colaboradores.nome ASC
");
if (!$colaboradores_stmt) {
    die('Erro ao preparar a consulta de colaboradores: ' . $conn->error);
}
$colaboradores_stmt->bind_param("i", $vaga['id']);
$colaboradores_stmt->execute();
$colaboradores_result = $colaboradores_stmt->get_result();

$colaboradores_cadastrados = $colaboradores_result->num_rows;
$quantidade_total = $vaga['quantidade_colaboradores'];

$pontos_encontro_stmt = $conn->prepare("SELECT * FROM pontos_encontro ORDER BY nome ASC");
if (!$pontos_encontro_stmt) {
    die('Erro ao preparar a consulta de pontos de encontro: ' . $conn->error);
}
$pontos_encontro_stmt->execute();
$pontos_encontro_result = $pontos_encontro_stmt->get_result();

$pontos_encontro_count_stmt = $conn->prepare("
    SELECT pontos_encontro.nome AS ponto_encontro, COUNT(candidaturas.id) AS total_colaboradores
    FROM candidaturas
    INNER JOIN pontos_encontro ON candidaturas.ponto_encontro_id = pontos_encontro.id
    WHERE candidaturas.vaga_id = ?
    GROUP BY pontos_encontro.id
    ORDER BY pontos_encontro.nome ASC
");
if (!$pontos_encontro_count_stmt) {
    die('Erro ao preparar a consulta de contagem de pontos de encontro: ' . $conn->error);
}
$pontos_encontro_count_stmt->bind_param("i", $vaga['id']);
$pontos_encontro_count_stmt->execute();
$pontos_encontro_count_result = $pontos_encontro_count_stmt->get_result();

$vaga_fechada = $colaboradores_cadastrados >= $quantidade_total;

$success = null;
$error = null;
$registrar_novo = false;
$atualizar_cadastro = false;
$campo_nome_erro = '';
$campo_cpf_erro = '';
$campo_email_erro = '';
$campo_telefone_erro = '';
$campo_pix_erro = '';
$campo_pix_confirm_erro = '';
$campo_contrato_erro = '';

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);

    if (strlen($cpf) != 11) {
        return false;
    }

    if (preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }

    $soma1 = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma1 += $cpf[$i] * (10 - $i);
    }
    $resto1 = $soma1 % 11;
    $digito1 = ($resto1 < 2) ? 0 : 11 - $resto1;

    $soma2 = 0;
    for ($i = 0; $i < 10; $i++) {
        $soma2 += $cpf[$i] * (11 - $i);
    }
    $resto2 = $soma2 % 11;
    $digito2 = ($resto2 < 2) ? 0 : 11 - $resto2;

    return ($cpf[9] == $digito1 && $cpf[10] == $digito2);
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$vaga_fechada) {
    $cpf = $_POST['cpf'] ?? null;
    $ponto_encontro_id = $_POST['ponto_encontro'] ?? null;
    $nome = $_POST['nome'] ?? null;
    $email = $_POST['email'] ?? null;
    $telefone = $_POST['telefone'] ?? null;
    $pix = $_POST['pix'] ?? null;
    $pix_confirm = $_POST['pix_confirm'] ?? null;
    $aceito_contrato = isset($_POST['aceito_contrato']) ? true : false;

    if (!validarCPF($cpf)) {
        $error = "CPF inválido! Verifique o número do CPF.";
        $campo_cpf_erro = 'campo-erro';
    } else {
        $stmt = $conn->prepare("SELECT * FROM colaboradores WHERE cpf = ?");
        if (!$stmt) {
            die('Erro ao preparar a consulta de colaborador: ' . $conn->error);
        }
        $stmt->bind_param("s", $cpf);
        $stmt->execute();
        $colaborador_result = $stmt->get_result();

        if ($colaborador_result->num_rows == 0) {
            $registrar_novo = true;
        } else {
            $colaborador = $colaborador_result->fetch_assoc();
            $colaborador_id = $colaborador['id'];

            if ($colaborador['bloqueado'] == 1) {
                $error = "Você está bloqueado. Entre em contato com o administrador.";
            } else {
                $stmt = $conn->prepare("SELECT * FROM candidaturas WHERE vaga_id = ? AND colaborador_id = ?");
                if (!$stmt) {
                    die('Erro ao preparar a consulta de candidatura: ' . $conn->error);
                }
                $stmt->bind_param("ii", $vaga['id'], $colaborador_id);
                $stmt->execute();
                $candidatura_result = $stmt->get_result();

                if ($candidatura_result->num_rows == 0) {
                    if (empty($colaborador['telefone']) || empty($colaborador['email'])) {
                        $atualizar_cadastro = true;
                    } else {
                        $stmt = $conn->prepare("INSERT INTO candidaturas (vaga_id, colaborador_id, ponto_encontro_id) VALUES (?, ?, ?)");
                        if (!$stmt) {
                            die('Erro ao preparar a consulta de inserção de candidatura: ' . $conn->error);
                        }
                        $stmt->bind_param("iii", $vaga['id'], $colaborador_id, $ponto_encontro_id);
                        if ($stmt->execute()) {
                            $success = "Candidatura realizada com sucesso!";
                            $mensagem = "Colaborador {$colaborador['nome']} (CPF: {$cpf}) se inscreveu na vaga {$vaga['nome_empresa']} - Data: " . date('d/m/Y H:i', strtotime($vaga['data_vaga']));
                             logAction($conn, 'cadastro_candidatura', $colaborador_id, 'candidaturas', $stmt->insert_id, $mensagem);
                        } else {
                            $error = "Erro ao registrar candidatura: " . $stmt->error;
                            $mensagem_erro = "Erro ao realizar candidatura para o usuário: {$colaborador['nome']} - CPF: {$cpf} na vaga {$vaga['nome_empresa']}. Mensagem de erro: " . $stmt->error;
                             logAction($conn, 'erro_candidatura_vaga', $colaborador_id, 'candidaturas', null, $mensagem_erro, json_encode($_POST));
                        }
                    }
                } else {
                    $error = "Você já está registrado nesta vaga.";
                    $mensagem_ja_cadastrado = "Colaborador {$colaborador['nome']} já está cadastrado na vaga {$vaga['nome_empresa']}";
                   logAction($conn, 'cadastro_existente', $colaborador_id, 'candidaturas', null, $mensagem_ja_cadastrado);
                }
            }
        }
    }

    if ($registrar_novo && (!isset($error) || empty($error))) {
        $rg = $_POST['rg'] ?? null;


        if (empty($nome) || empty($rg) || empty($pix) || empty($pix_confirm) || empty($telefone) || empty($email)) {
            $error = "Todos os campos obrigatórios devem ser preenchidos!";
            if (empty($aceito_contrato)) {
                $campo_contrato_erro = 'campo-erro';
                $error = "Todos os campos obrigatórios devem ser preenchidos e você precisa aceitar o contrato!";
            }
            if (empty($pix)) {
                $campo_pix_erro = 'campo-erro';
            }
            if (empty($pix_confirm)) {
                $campo_pix_confirm_erro = 'campo-erro';
            }
            $mensagem_erro = "Erro ao cadastrar novo usuário: Campos obrigatórios faltando.";
            logAction($conn, 'erro_cadastro_colaborador', null, 'colaboradores', null, $mensagem_erro, json_encode($_POST));

        } else {
             if (!$aceito_contrato) {
                $error = "Você precisa aceitar o contrato para se inscrever.";
                $campo_contrato_erro = 'campo-erro';
            }

            if ($pix !== $pix_confirm) {
                $error = "Os campos PIX e Repetir Chave PIX não correspondem.";
                $mensagem_pix_invalido = "Erro ao cadastrar novo usuário: Os campos PIX e Repetir Chave PIX não correspondem.";
                logAction($conn, 'erro_cadastro_colaborador', null, 'colaboradores', null, $mensagem_pix_invalido, json_encode($_POST));
                $campo_pix_erro = 'campo-erro';
                $campo_pix_confirm_erro = 'campo-erro';
            } else {
                $nome_array = explode(" ", trim($nome));
                if (count($nome_array) < 2) {
                    $error = "Por favor, insira seu nome completo (Você precisa colocar o nome completo).";
                    $mensagem_nome_invalido = "Erro ao cadastrar novo usuário: Nome incompleto.";
                     logAction($conn, 'erro_cadastro_colaborador', null, 'colaboradores', null, $mensagem_nome_invalido, json_encode($_POST));
                    $campo_nome_erro = 'campo-erro';
                } elseif (!validarEmail($email)) {
                    $error = "Email inválido! Verifique o formato.";
                       $mensagem_email_invalido = "Erro ao cadastrar novo usuário: Email inválido.";
                      logAction($conn, 'erro_cadastro_colaborador', null, 'colaboradores', null, $mensagem_email_invalido, json_encode($_POST));
                    $campo_email_erro = 'campo-erro';
                } else {
                    $stmt = $conn->prepare("INSERT INTO colaboradores (nome, rg, cpf, pix, senha, email, telefone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt) {
                        die('Erro ao preparar a consulta de inserção de colaborador: ' . $conn->error);
                    }
                    $senha_hash = password_hash($cpf, PASSWORD_DEFAULT);
                    $stmt->bind_param("sssssss", $nome, $rg, $cpf, $pix, $senha_hash, $email, $telefone);
                    if ($stmt->execute()) {
                        $colaborador_id = $stmt->insert_id;
                        $mensagem = "Novo usuário cadastrado: {$nome} (CPF: {$cpf}, Email: {$email}, Telefone: {$telefone}, Chave PIX: {$pix}).";
                        logAction($conn, 'cadastro_novo_colaborador', $colaborador_id, 'colaboradores', $colaborador_id, $mensagem);

                        $stmt = $conn->prepare("INSERT INTO candidaturas (vaga_id, colaborador_id, ponto_encontro_id) VALUES (?, ?, ?)");
                        if (!$stmt) {
                            die('Erro ao preparar a consulta de inserção de candidatura (novo colaborador): ' . $conn->error);
                        }
                        $stmt->bind_param("iii", $vaga['id'], $colaborador_id, $ponto_encontro_id);
                        if ($stmt->execute()) {
                            $success = "Candidatura e cadastro realizados com sucesso!";
                            $mensagem_candidatura = "Colaborador {$nome} (CPF: {$cpf}) se inscreveu na vaga {$vaga['nome_empresa']} - Data: " . date('d/m/Y H:i', strtotime($vaga['data_vaga']));
                            logAction($conn, 'cadastro_candidatura_novo_colaborador', $colaborador_id, 'candidaturas', $stmt->insert_id, $mensagem_candidatura);
                        } else {
                            $error = "Erro ao registrar candidatura.";
                                $mensagem_erro_candidatura = "Erro ao realizar candidatura para o novo usuário: {$nome} - CPF: {$cpf} na vaga {$vaga['nome_empresa']}. Mensagem de erro: " . $stmt->error;
                             logAction($conn, 'erro_cadastro_candidatura', $colaborador_id, 'candidaturas', null, $mensagem_erro_candidatura, json_encode($_POST));
                        }
                    } else {
                        $error = "Erro ao registrar colaborador: " . $stmt->error;
                        $mensagem_erro_colaborador = "Erro ao registrar colaborador: " . $stmt->error;
                         logAction($conn, 'erro_cadastro_colaborador', null, 'colaboradores', null, $mensagem_erro_colaborador, json_encode($_POST));
                    }
                }
            }
        }
    }

    if ($atualizar_cadastro && (!isset($error) || empty($error))) {
        if (!empty($telefone) && !empty($email)) {
            if (!validarEmail($email)) {
                $error = "Email inválido! Verifique o formato.";
                    $mensagem_email_invalido = "Erro ao atualizar o cadastro: Email inválido.";
                 logAction($conn, 'erro_atualizacao_cadastro', $colaborador_id, 'colaboradores', $colaborador_id, $mensagem_email_invalido, json_encode($_POST));
                $campo_email_erro = 'campo-erro';
            } else {
                $stmt = $conn->prepare("UPDATE colaboradores SET telefone = ?, email = ? WHERE id = ?");
                if (!$stmt) {
                    die('Erro ao preparar a consulta de atualização de cadastro: ' . $conn->error);
                }
                $stmt->bind_param("ssi", $telefone, $email, $colaborador_id);
                if ($stmt->execute()) {
                    $stmt = $conn->prepare("INSERT INTO candidaturas (vaga_id, colaborador_id, ponto_encontro_id) VALUES (?, ?, ?)");
                    if (!$stmt) {
                        die('Erro ao preparar a consulta de inserção de candidatura (após atualização): ' . $conn->error);
                    }
                    $stmt->bind_param("iii", $vaga['id'], $colaborador_id, $ponto_encontro_id);
                    if ($stmt->execute()) {
                        $success = "Cadastro atualizado e candidatura realizada com sucesso!";
                         $mensagem_atualizado = "Cadastro atualizado e candidatura realizada para o usuario: {$colaborador['nome']}";
                        logAction($conn, 'atualizacao_cadastro_e_candidatura', $colaborador_id, 'candidaturas', $stmt->insert_id, $mensagem_atualizado);

                    } else {
                        $error = "Erro ao registrar candidatura.";
                        $mensagem_erro_candidatura = "Erro ao registrar candidatura para o usuário: {$colaborador['nome']}. Mensagem de erro: " . $stmt->error;
                         logAction($conn, 'erro_cadastro_candidatura', $colaborador_id, 'candidaturas', null, $mensagem_erro_candidatura, json_encode($_POST));
                    }
                } else {
                    $error = "Erro ao atualizar cadastro: " . $stmt->error;
                     $mensagem_erro_atualizar = "Erro ao atualizar cadastro para o usuário: {$colaborador['nome']}. Mensagem de erro: " . $stmt->error;
                    logAction($conn, 'erro_atualizacao_cadastro', $colaborador_id, 'colaboradores', $colaborador_id, $mensagem_erro_atualizar, json_encode($_POST));

                }
            }
        } else {
            $error = "Telefone e Email são obrigatórios para atualizar o cadastro.";
              $mensagem_campos_faltando = "Erro ao atualizar o cadastro: Telefone e Email são obrigatórios.";
             logAction($conn, 'erro_atualizacao_cadastro', $colaborador_id, 'colaboradores', $colaborador_id, $mensagem_campos_faltando, json_encode($_POST));
        }
    }
}

if ($vaga_fechada) {
    $mensagem_vaga_fechada = "A lista {$vaga['nome_empresa']} na data de " . date('d/m/Y H:i', strtotime($vaga['data_vaga'])) . " foi finalizada.";
    logAction($conn, 'vaga_fechada', null, 'vagas', $vaga['id'], $mensagem_vaga_fechada);

}


$colaboradores_stmt->execute();
$colaboradores_result = $colaboradores_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Lista De Confirmação Operação - <?php echo htmlspecialchars($vaga['nome_empresa']); ?> - <?php echo date('d/m/Y H:i', strtotime($vaga['data_vaga'])); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/rex.css">
    <style>
        .local-encontro {
            color: red;
        }
    </style>
</head>
<body>
    <div class="candidatura-container">
          <div class="title-container">
        <h1>Lista De Confirmação Operação <?php echo htmlspecialchars($vaga['nome_empresa']); ?> - <?php echo date('d/m/Y H:i', strtotime($vaga['data_vaga'])); ?></h1>
         </div>
        <?php if (isset($success)) { echo "<div class='message-container'><p class='success'>" . htmlspecialchars($success) . "</p></div>"; } ?>
        <?php if (isset($error)) { echo "<div class='message-container'><p class='error'>" . htmlspecialchars($error) . "</p></div>"; } ?>

        <?php if ($vaga_fechada) { ?>
            <div class='message-container'><p class="error"><h1>Vagas alcançadas. Não é possível mais se cadastrar.</h1></p></div>
        <?php } else { ?>
            <?php if (!isset($error) || empty($error) || strpos($error, 'bloqueado') === false) { ?>
                <form method="POST" action="">
                    <?php if ($registrar_novo): ?>
                        <p class="error-message">Você não está registrado no sistema. Por favor, preencha o formulário abaixo:</p>
                        <input class="form-input <?php echo htmlspecialchars($campo_nome_erro); ?>" type="text" name="nome" placeholder="Nome Completo" value="<?php echo htmlspecialchars($nome ?? ''); ?>" required oninput="this.value = this.value.toUpperCase();">
                        <input class="form-input" type="text" name="rg" placeholder="RG (somente números e X)" value="<?php echo htmlspecialchars($rg ?? ''); ?>" required oninput="validateRG(event)">
                        <input class="form-input <?php echo htmlspecialchars($campo_cpf_erro); ?>" type="text" name="cpf" placeholder="CPF (somente números)" value="<?php echo htmlspecialchars($cpf ?? ''); ?>" readonly oninput="validateCPF(event)">
                        <input class="form-input <?php echo htmlspecialchars($campo_pix_erro); ?>" type="text" name="pix" placeholder="Chave PIX" value="<?php echo htmlspecialchars($pix ?? ''); ?>" required>
                        <input class="form-input <?php echo htmlspecialchars($campo_pix_confirm_erro); ?>" type="text" name="pix_confirm" placeholder="Repetir Chave PIX" value="<?php echo htmlspecialchars($pix_confirm ?? ''); ?>" required>
                        <input class="form-input <?php echo htmlspecialchars($campo_email_erro); ?>" type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        <input class="form-input <?php echo htmlspecialchars($campo_telefone_erro); ?>" type="text" name="telefone" placeholder="Telefone (DDD)9XXXX-XXXX" value="<?php echo htmlspecialchars($telefone ?? ''); ?>" required>
                        
                        <div class="contrato-container">
                            <label style="color: red;">
                                <input type="checkbox" name="aceito_contrato" <?php echo isset($aceito_contrato) && $aceito_contrato ? 'checked' : ''; ?> required> 
                                 Declaro que todas as informações fornecidas acima são verdadeiras e de minha inteira responsabilidade, não representando dados de terceiros, incluindo minha chave PIX
                            </label>
                            <?php if ($campo_contrato_erro): ?>
                                <p class="error-message">Você precisa aceitar o contrato para se inscrever.</p>
                            <?php endif; ?>
                        </div>
                        
                    <?php elseif ($atualizar_cadastro): ?>
                        <p class="error-message">Você precisa atualizar seu cadastro. Preencha os campos abaixo:</p>
                        <input class="form-input" type="text" name="nome" placeholder="Nome Completo" value="<?php echo htmlspecialchars($colaborador['nome']); ?>" readonly>
                        <input class="form-input" type="text" name="rg" placeholder="RG" value="<?php echo htmlspecialchars($colaborador['rg']); ?>" readonly>
                        <input class="form-input" type="text" name="cpf" placeholder="CPF" value="<?php echo htmlspecialchars($colaborador['cpf']); ?>" readonly oninput="validateCPF(event)">
                        <input class="form-input" type="text" name="pix" placeholder="Chave PIX" value="<?php echo htmlspecialchars($colaborador['pix']); ?>" readonly>
                        <input class="form-input <?php echo htmlspecialchars($campo_email_erro); ?>" type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($colaborador['email']); ?>" required>
                        <input class="form-input <?php echo htmlspecialchars($campo_telefone_erro); ?>" type="text" name="telefone" placeholder="Telefone (DDD)9XXXX-XXXX" value="<?php echo htmlspecialchars($colaborador['telefone']); ?>" required>
                    <?php else: ?>
                        <input class="form-input <?php echo htmlspecialchars($campo_cpf_erro); ?>" type="text" name="cpf" placeholder="CPF (somente números)" value="<?php echo htmlspecialchars($cpf ?? ''); ?>" required oninput="validateCPF(event)">
                    <?php endif; ?>
                    
                    <?php if (!$registrar_novo): ?>
                        <select class="form-input" name="ponto_encontro" required>
                            <option value="">Selecione o Ponto de Encontro</option>
                            <?php while ($ponto = $pontos_encontro_result->fetch_assoc()) { ?>
                                <option value="<?php echo htmlspecialchars($ponto['id']); ?>"><?php echo htmlspecialchars($ponto['nome']); ?></option>
                            <?php } ?>
                        </select>
                        <button type="submit">Enviar Confirmação</button>
                    <?php else: ?>
                        <select class="form-input" name="ponto_encontro" required>
                            <option value="">Selecione o Ponto de Encontro</option>
                            <?php while ($ponto = $pontos_encontro_result->fetch_assoc()) { ?>
                                <option value="<?php echo htmlspecialchars($ponto['id']); ?>"><?php echo htmlspecialchars($ponto['nome']); ?></option>
                            <?php } ?>
                        </select>
                        <button type="submit">Registrar e Enviar Confirmação</button>
                    <?php endif; ?>
                </form>
            <?php } ?>
        <?php } ?>

        <div class="colaboradores-container">
            <h2>Colaboradores Cadastrados</h2>
            <p class="quantidade-info"><?php echo htmlspecialchars($colaboradores_cadastrados); ?>/<?php echo htmlspecialchars($quantidade_total); ?> cadastrados</p>
            <ul class="colaboradores-list">
                <?php while ($colaborador = $colaboradores_result->fetch_assoc()) { ?>
                    <li><?php echo htmlspecialchars($colaborador['nome']); ?> - <span class="local-encontro">Local de Encontro:</span> <?php echo htmlspecialchars($colaborador['ponto_encontro']); ?></li>
                <?php } ?>
            </ul>
        </div>

        <div class="pontos-encontro-container">
            <h2>Pontos de Encontro Escolhidos</h2>
            <ul class="pontos-encontro-list">
                <?php while ($ponto = $pontos_encontro_count_result->fetch_assoc()) { ?>
                    <li>
                        <span><?php echo htmlspecialchars($ponto['ponto_encontro']); ?>:</span>
                        <?php echo htmlspecialchars($ponto['total_colaboradores']); ?> colaborador<?php echo ($ponto['total_colaboradores'] > 1) ? 'es' : ''; ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</body>
</html>

<script>
    function validateCPF(event) {
        event.target.value = event.target.value.replace(/[^0-9]/g, '');
    }

    function validateRG(event) {
        event.target.value = event.target.value.replace(/[^0-9xX]/g, '');
    }
</script>

<script async src="https://www.googletagmanager.com/gtag/js?id=G-BY2YHM4D63"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-BY2YHM4D63');
</script>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1092502767568075"
     crossorigin="anonymous"></script>
 <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XE8FE6P03F"></script>


<script async src="https://www.googletagmanager.com/gtag/js?id=G-XE8FE6P03F"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-XE8FE6P03F');
</script>

<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2864759313311428"
     crossorigin="anonymous"></script>

<ins class="adsbygoogle"
     style="display:block"
     data-ad-format="autorelaxed"
     data-ad-client="ca-pub-2864759313311428"
     data-ad-slot="3193103186"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>