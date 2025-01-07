<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('config.php');

// Define o número de logs por página
$logs_por_pagina = 10;

// Obtém o número da página atual da URL
$pagina_atual = $_GET['pagina'] ?? 1;
$pagina_atual = max(1, intval($pagina_atual)); // Garante que a página seja pelo menos 1

// Obtém os filtros da URL
$tipo_filtro = $_GET['tipo_filtro'] ?? '';
$busca_termo = $_GET['busca_termo'] ?? '';

// Prepara a consulta SQL para obter os logs paginados
$sql_where = "WHERE 1=1";
$sql_bind_params = [];

if (!empty($tipo_filtro)) {
    $sql_where .= " AND tipo_evento = ?";
    $sql_bind_params[] = $tipo_filtro;
}

if (!empty($busca_termo)) {
    $sql_where .= " AND (mensagem LIKE ? OR detalhes LIKE ?)";
    $sql_bind_params[] = "%" . $busca_termo . "%";
    $sql_bind_params[] = "%" . $busca_termo . "%";
}

$sql_base = "SELECT * FROM logs " . $sql_where . " ORDER BY data_hora DESC LIMIT ?, ?";
$stmt = $conn->prepare($sql_base);
if (!$stmt) {
    die('Erro ao preparar a consulta de logs: ' . $conn->error);
}

$sql_bind_params[] = ($pagina_atual - 1) * $logs_por_pagina;
$sql_bind_params[] = $logs_por_pagina;

$types = str_repeat('s', count($sql_bind_params) - 2) . 'ii';
$stmt->bind_param($types, ...$sql_bind_params);
$stmt->execute();
$result = $stmt->get_result();


// Consulta para obter o número total de logs (com filtros)
$sql_count = "SELECT COUNT(*) as total FROM logs " . $sql_where;
$total_logs_stmt = $conn->prepare($sql_count);
if (!$total_logs_stmt) {
    die('Erro ao preparar a consulta de contagem de logs: ' . $conn->error);
}

$types_count = str_repeat('s', count($sql_bind_params) - 2);
if (!empty($types_count)) {
  $total_logs_stmt->bind_param($types_count, ...array_slice($sql_bind_params, 0, count($sql_bind_params) - 2));
}
$total_logs_stmt->execute();
$total_logs_result = $total_logs_stmt->get_result();
$total_logs_row = $total_logs_result->fetch_assoc();
$total_logs = $total_logs_row['total'];

// Calcula o número total de páginas
$total_paginas = ceil($total_logs / $logs_por_pagina);

//Obtém todos os tipos de eventos para o filtro
$tipos_eventos_stmt = $conn->prepare("SELECT DISTINCT tipo_evento FROM logs ORDER BY tipo_evento");
if (!$tipos_eventos_stmt) {
    die('Erro ao preparar a consulta de tipos de eventos: ' . $conn->error);
}
$tipos_eventos_stmt->execute();
$tipos_eventos_result = $tipos_eventos_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Logs do Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Exo 2', sans-serif;
            margin: 0;
            background-color: #f4f4f4;
            color: #333;
            display: flex; /* Use flexbox para o layout lateral */
            min-height: 100vh;
        }
        /* Estilo do menu lateral */
         .sidebar {
            width: 250px;
            background-color: #343a40;
             color: #fff;
            padding-top: 20px;
              transition: width 0.3s ease;
        }
         .sidebar.collapsed {
            width: 80px;
        }
        .sidebar h2 {
             padding-left: 15px;
           margin-bottom: 20px;
           color: #fff;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
             margin: 0;
        }
        .sidebar li a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #fff;
              transition: background-color 0.3s ease;
             white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar li a:hover {
            background-color: #495057;
        }
        .sidebar li a i {
            margin-right: 10px;
        }
        /* Estilo do conteúdo principal */
        .content {
            flex: 1;
            padding: 20px;
            display: flex;
            flex-direction: column;
             transition: margin-left 0.3s ease;
              margin-left: 250px; /* Para que o menu lateral não se sobreponha */
         }
        .content.collapsed {
            margin-left: 80px;
        }
       .toggle-button {
            padding: 10px 15px;
            background-color: #343a40;
            color: white;
             border: none;
             cursor: pointer;
             text-align: left;
        }
         .toggle-button i {
           margin-right: 10px;
        }
        /* Estilo do título principal */
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        /* Estilo do formulário de filtro */
        .filter-form {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px; /* Espaçamento entre os elementos */
         }

        .filter-form select,
        .filter-form input[type="text"] {
            padding: 8px;
            border: 1px solid #ddd;
             border-radius: 4px;
        }

        .filter-form button {
           padding: 8px 12px;
           background-color: #343a40;
           color: white;
           border: none;
           border-radius: 4px;
           cursor: pointer;
              transition: background-color 0.3s ease;
        }
          .filter-form button:hover {
            background-color: #495057;
         }

        /* Estilo da tabela */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        /* Estilo da paginação */
        .pagination {
            display: flex;
            justify-content: center;
             margin-top: 20px;
         }
        .pagination a {
            padding: 8px 12px;
            margin: 0 4px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            background-color: white;
        }
        .pagination a.active {
            background-color: #2348A8;
             color: white;
        }
        .pagination a:hover {
            background-color: #ddd;
        }
        pre {
        white-space: pre-wrap;
        word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
         <button class="toggle-button" id="toggleButton">
              <i class="fas fa-bars"></i>
           </button>
         <h2>Menu</h2>
          <ul>
               <li><a href="candidatura.php?vaga_id=seu-link-da-vaga-aqui"><i class="fas fa-list"></i> Lista de Confirmação</a></li>
               <li><a href="log.php"><i class="fas fa-history"></i> Logs do Sistema</a></li>
          </ul>
     </div>
     <div class="content" id="content">
          <h1>Logs do Sistema</h1>

        <form class="filter-form" method="GET" action="">
              <select name="tipo_filtro">
                <option value="">Todos os Tipos</option>
                    <?php while ($tipo = $tipos_eventos_result->fetch_assoc()) { ?>
                            <option value="<?php echo htmlspecialchars($tipo['tipo_evento']); ?>" <?php if ($tipo_filtro == $tipo['tipo_evento']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($tipo['tipo_evento']); ?>
                            </option>
                    <?php } ?>
               </select>
                <input type="text" name="busca_termo" placeholder="Buscar por mensagem/detalhes" value="<?php echo htmlspecialchars($busca_termo); ?>">
            <button type="submit">Filtrar</button>
        </form>


        <table>
              <thead>
                <tr>
                    <th>ID</th>
                    <th>Data e Hora</th>
                    <th>Tipo de Evento</th>
                    <th>Usuário ID</th>
                    <th>Tabela Afetada</th>
                    <th>Registro ID</th>
                    <th>Mensagem</th>
                    <th>Detalhes</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                     <tr>
                           <td><?php echo htmlspecialchars($row['id']); ?></td>
                           <td><?php echo htmlspecialchars($row['data_hora']); ?></td>
                           <td><?php echo htmlspecialchars($row['tipo_evento']); ?></td>
                           <td><?php echo htmlspecialchars($row['usuario_id'] ?? 'N/A'); ?></td>
                           <td><?php echo htmlspecialchars($row['tabela_afetada'] ?? 'N/A'); ?></td>
                           <td><?php echo htmlspecialchars($row['registro_id'] ?? 'N/A'); ?></td>
                           <td><?php echo htmlspecialchars($row['mensagem'] ?? 'N/A'); ?></td>
                           <td>
                             <?php
                            $detalhes = $row['detalhes'];
                             if ($detalhes) {
                                 $decoded_detalhes = json_decode($detalhes, true);
                                 if ($decoded_detalhes !== null) {
                                     echo '<pre>';
                                        print_r($decoded_detalhes);
                                     echo '</pre>';
                                 }else {
                                   echo htmlspecialchars($detalhes);
                                 }
                             } else {
                                 echo 'N/A';
                              } ?>
                           </td>
                            <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                     </tr>
                 <?php } ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_paginas; $i++) { ?>
                <a href="log.php?pagina=<?php echo $i; ?>&tipo_filtro=<?php echo htmlspecialchars($tipo_filtro); ?>&busca_termo=<?php echo htmlspecialchars($busca_termo); ?>" <?php if ($i == $pagina_atual) echo 'class="active"'; ?>>
                    <?php echo $i; ?>
                 </a>
            <?php } ?>
        </div>
     </div>
     <script>
        const toggleButton = document.getElementById('toggleButton');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');

        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        });
    </script>
</body>
</html>