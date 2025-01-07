<?php
// login.php
include('config.php');

// Inicia a sessão apenas se ainda não estiver ativa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurações de segurança
$max_attempts = 5; // Número máximo de tentativas
$lock_time = 3600; // Tempo de bloqueio em segundos (1 hora)
$ip_address = $_SERVER['REMOTE_ADDR']; // Obtém o IP do usuário

// Verificar se o IP está bloqueado
$stmt_block = $conn->prepare("SELECT * FROM login_attempts WHERE ip_address = ? AND attempts >= ? AND last_attempt >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
$stmt_block->bind_param("sii", $ip_address, $max_attempts, $lock_time);
$stmt_block->execute();
$result_block = $stmt_block->get_result();

if ($result_block->num_rows > 0) {
    die("O acesso foi bloqueado temporariamente devido a muitas tentativas de login falhas.");
}

// Processa o login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Consulta ao banco de dados para verificar o login
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE username = ? AND senha = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    // Verifica se o usuário existe
    if ($result->num_rows === 1) {
        // Login bem-sucedido, reseta tentativas
        $stmt_reset = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt_reset->bind_param("s", $ip_address);
        $stmt_reset->execute();

        $_SESSION['username'] = $username;
        header("Location: index.php"); // Redireciona para a página principal
        exit();
    } else {
        // Incrementa as tentativas em caso de falha
        $stmt_check = $conn->prepare("SELECT * FROM login_attempts WHERE ip_address = ?");
        $stmt_check->bind_param("s", $ip_address);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $stmt_update = $conn->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE ip_address = ?");
            $stmt_update->bind_param("s", $ip_address);
            $stmt_update->execute();
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, NOW())");
            $stmt_insert->bind_param("s", $ip_address);
            $stmt_insert->execute();
        }

        $error = "Usuário ou senha inválidos!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Agência de Empregos</title>
    <link rel="stylesheet" href="css/politica-privacidade.css"> <!-- Link para o CSS -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (!getCookie("cookie_consent")) {
                document.getElementById("cookie-banner").style.display = "block";
            }
        });

        function acceptCookies() {
            setCookie("cookie_consent", "true", 30);
            document.getElementById("cookie-banner").style.display = "none";
        }

        function setCookie(name, value, days) {
            var expires = "";
            if (days) {
                var date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "")  + expires + "; path=/";
        }

        function getCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for(var i=0;i < ca.length;i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1,c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
            }
            return null;
        }
    </script>
    <style>
        /* CSS para o banner de cookies */
        #cookie-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #333333;
            color: #ffffff;
            padding: 15px;
            text-align: center;
            font-size: 14px;
            z-index: 1000;
        }

        #cookie-banner button {
            background-color: #03DAC6;
            color: #000;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }

        #cookie-banner button:hover {
            background-color: #018786;
        }
    </style>
</head>
<body>
    <!-- Banner de cookies -->
    <div id="cookie-banner" style="display: none;">
        <p>Este site usa cookies para melhorar a experiência do usuário. <button onclick="acceptCookies()">Aceitar Cookies</button></p>
    </div>

    <div class="container">
        <header>
            <h1>Bem Vindo Ao Painel</h1>
        </header>

        <nav>
            <a href="index.php">Início</a>
            <a href="lista_disponivel.php">Lista de Vagas</a>
            <a href="sobre.php">Sobre</a>
            <a href="politica-privacidade.php">Política de Privacidade</a>
            <a href="termos_de_uso.php">Termos de Uso</a> <!-- Link para a página de Termos de Uso -->
        </nav>

        <div class="login-container">
            <h1>Login</h1>
            <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Usuário" required>
                <input type="password" name="password" placeholder="Senha" required>
                <button type="submit">Entrar</button>
            </form>
        </div>

        <footer>
            <p>© 2024 - Todos os direitos reservados. <a href="sobre.php">Sobre nós</a></p>
        </footer>
    </div>
</body>
</html>

<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2864759313311428"
     crossorigin="anonymous"></script>
	 
	 
	 