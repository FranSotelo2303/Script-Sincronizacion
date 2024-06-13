<?php
require 'config.php';
require 'functions.php'; // Incluir funciones comunes

session_start(); // Inicia sesión al principio del script
date_default_timezone_set('America/Bogota'); // Establece la zona horaria de Bogotá, Colombia

if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    // Iniciar la sesión si no ha sido iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Limpiar todas las variables de sesión
    $_SESSION = array();
    session_destroy(); // Destruir la sesión
    // Redirigir para reiniciar completamente el estado
    header('Location: index.php');
    exit;
}

$pacienteEncontrado = false; // Estado inicial de la búsqueda de paciente
$documentoId = '';

// Buscar paciente por documentoId
if (isset($_POST['buscarPaciente']) || (isset($_SESSION['documentoId']) && !empty($_SESSION['documentoId']))) {
    $documentoId = isset($_POST['documentoId']) ? $conn->real_escape_string($_POST['documentoId']) : $_SESSION['documentoId'];
    if (!preg_match('/^\d{1,10}$/', $documentoId)) {
        echo "<div class='alert alert-danger' role='alert'>Documento inválido. Asegúrate de ingresar un número válido, no mayor a 10 dígitos.</div>";
    } else {
        $sql = "SELECT * FROM pacientes WHERE documentoId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $documentoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $pacienteEncontrado = $result->num_rows > 0;
        if ($pacienteEncontrado) {
            $paciente = $result->fetch_assoc();
            $_SESSION['documentoId'] = $documentoId; // Almacena el ID del paciente en la sesión
        }
    }
}

// Variables para la paginación
$documentosPorPagina = 4; // Cantidad de documentos por página
$paginaActual = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Página actual
$offset = ($paginaActual - 1) * $documentosPorPagina; // Calcula el offset

// Manejar la carga del archivo si se encuentra un paciente
if (isset($_POST['subirArchivo']) && isset($_FILES['pdf_file'])) {
    $documentoId = $_SESSION['documentoId'];
    $file = $_FILES['pdf_file'];
    $fileName = $documentoId . ' - ' . $file['name'];
    $fileType = $file['type'];
    $docType = $_POST['doc_type'];
    $fileSize = $file['size'];
    $fileContent = file_get_contents($file['tmp_name']);
    $uploadDate = date('Y-m-d H:i:s');

    // Validación del tipo de archivo
    if ($fileType != 'application/pdf') {
        echo "<div class='alert alert-danger' role='alert'>Solo se permiten archivos PDF.</div>";
    }
    // Validación del tamaño del archivo (4MB en este caso)
    elseif ($fileSize > 4194304) {
        echo "<div class='alert alert-danger' role='alert'>El tamaño del archivo excede el límite de 4MB.</div>";
    } else {
        $sql = "INSERT INTO files (file_name, file_type, doc_type, file_size, file_content, upload_date, pacienteId) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "<div class='alert alert-danger' role='alert'>Error preparando la sentencia: " . $conn->error . "</div>";
            exit;
        }

        $null = NULL; // Placeholder para el contenido binario
        $stmt->bind_param("sssibss", $fileName, $fileType, $docType, $fileSize, $null, $uploadDate, $documentoId);
        $stmt->send_long_data(4, $fileContent);

        if ($stmt->execute()) {
            echo "<div class='alert alert-success' role='alert'>¡Archivo subido exitosamente!</div>";
        } else {
            echo "<div class='alert alert-danger' role='alert'>Error al subir el archivo: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// Carga de documentos con paginación
if ($pacienteEncontrado) {
    // Cuenta total de documentos
    $sqlCount = "SELECT COUNT(*) AS total FROM files WHERE pacienteId = ?";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->bind_param("s", $documentoId);
    $stmtCount->execute();
    $resultCount = $stmtCount->get_result();
    $totalDocumentos = $resultCount->fetch_assoc()['total'];
    $totalPaginas = ceil($totalDocumentos / $documentosPorPagina);

    // Consulta para obtener documentos
    $sqlFiles = "SELECT * FROM files WHERE pacienteId = ? LIMIT ? OFFSET ?";
    $stmtFiles = $conn->prepare($sqlFiles);
    $stmtFiles->bind_param("sii", $documentoId, $documentosPorPagina, $offset);
    $stmtFiles->execute();
    $filesResult = $stmtFiles->get_result();
}
// Recibir los valores de filtrado desde GET
$filterType = isset($_GET['filterType']) ? $_GET['filterType'] : '';
$filterDate = isset($_GET['filterDate']) ? $_GET['filterDate'] : '';

// Preparar las condiciones de filtro básicas
$sqlConditions = " WHERE pacienteId = ?";
$params = array($documentoId);
$types = "s"; // tipos de parámetros para bind_param

// Agregar condiciones para el tipo de documento
if (!empty($filterType)) {
    $sqlConditions .= " AND doc_type = ?";
    $params[] = $filterType;
    $types .= "s"; // agregar un tipo string al bind_param
}

// Agregar condiciones para la fecha
if (!empty($filterDate)) {
    $sqlConditions .= " AND DATE(upload_date) = ?";
    $params[] = $filterDate;
    $types .= "s"; // agregar un tipo string al bind_param
}

// Consulta SQL con posibles filtros
$sqlFiles = "SELECT * FROM files" . $sqlConditions . " LIMIT ? OFFSET ?";
$params[] = $documentosPorPagina;
$params[] = $offset;
$types .= "ii";

$stmtFiles = $conn->prepare($sqlFiles);
$stmtFiles->bind_param($types, ...$params);
$stmtFiles->execute();
$filesResult = $stmtFiles->get_result();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Estilo usando bootstrap -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Usado para la alerta de eliminación -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Gestión Historias Clínicas</title>
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">
            <a href="index.php?reset=true" style="text-decoration: none; color: inherit;">Gestión de Documentos para Pacientes</a>
        </h1>

        <form action="index.php" method="post" enctype="multipart/form-data" class="mb-4">
            <div class="form-group">
                <label for="documentoId">Documento del Paciente:</label>
                <input type="text" class="form-control" name="documentoId" id="documentoId" value="<?php echo isset($_SESSION['documentoId']) ? $_SESSION['documentoId'] : ''; ?>" required>
                <button type="submit" name="buscarPaciente" class="btn btn-info mt-2">Buscar Paciente</button>
            </div>
        </form>

        <?php if ($pacienteEncontrado) : ?>
            <form action="index.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="documentoId" value="<?php echo $documentoId; ?>" pattern="\d*" maxlength="10">
                <div class="form-group">
                    <label for="doc_type">Tipo de Documento:</label>
                    <select name="doc_type" id="doc_type" class="form-control">
                        <option value="Historia Clínica">Historia Clínica</option>
                        <option value="Resultados Exámenes">Resultados Exámenes</option>
                        <option value="Citación">Citación</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pdf_file">Selecciona un archivo PDF:</label>
                    <input type="file" class="form-control-file" name="pdf_file" id="pdf_file" accept=".pdf" required>
                    <button type="submit" name="subirArchivo" class="btn btn-primary mt-2">Subir Archivo</button>
                </div>
            </form>
            <!-- Mostrar documentos asociados al paciente -->
            <?php if ($filesResult->num_rows > 0) : ?>
                <h2 class='mt-5'>Documentos Asociados:</h2>
                <!-- Formulario de filtros -->
                <form action="index.php" method="get" class="mb-4">
                    <h3 class='mt-5'>Filtrar por:</h3>
                    <div class="form-row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="filterType">Tipo:</label>
                                <select name="filterType" id="filterType" class="form-control">
                                    <option value="">Todos</option>
                                    <option value="Historia Clínica">Historia Clínica</option>
                                    <option value="Resultados Exámenes">Resultados Exámenes</option>
                                    <option value="Citación">Citación</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="filterDate">Fecha de subida:</label>
                                <input type="date" class="form-control" name="filterDate" id="filterDate">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </form>
                <table class='table'>
                    <thead class='thead-dark'>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Fecha de Subida</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $contador = 1; ?>
                        <?php
                        // Calcular el valor inicial del contador para la página actual
                        $contador = (($paginaActual - 1) * $documentosPorPagina) + 1;
                        while ($file = $filesResult->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo $contador; ?></td>
                                <td><?php echo htmlspecialchars($file["file_name"]); ?></td>
                                <td><?php echo htmlspecialchars($file["doc_type"]); ?></td>
                                <td><?php echo date('d-m-Y H:i:s', strtotime($file['upload_date'])); ?></td>
                                <td>
                                    <a href='download.php?id=<?php echo $file["id"]; ?>' class='btn btn-success'>Descargar</a>
                                    <a href='view.php?id=<?php echo $file["id"]; ?>' target='_blank' class='btn btn-info'>Ver</a>
                                    <button onclick="confirmDelete('<?php echo $file['id']; ?>')" class='btn btn-danger'>Eliminar</button>
                                </td>
                            </tr>
                            <?php $contador++;
                            ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <!-- Paginación -->
                <nav aria-label="Page navigation example">
                    <ul class="pagination ">
                        <?php if ($paginaActual > 1) : ?>
                            <li class="page-item"><a class="page-link" href="index.php?page=<?php echo $paginaActual - 1; ?>">&laquo;</a></li>
                        <?php endif; ?>
                        <?php if ($paginaActual > 3) : ?>
                            <li class="page-item"><a class="page-link" href="index.php?page=1">1</a></li>
                            <li class="page-item"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php for ($i = max($paginaActual - 2, 1), $j = 0; $i <= min($paginaActual + 2, $totalPaginas) && $j < 5; $i++, $j++) : ?>
                            <li class="page-item <?php if ($paginaActual == $i) echo 'active'; ?>"><a class="page-link" href="index.php?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($paginaActual < $totalPaginas - 2) : ?>
                            <li class="page-item"><span class="page-link">...</span></li>
                            <li class="page-item"><a class="page-link" href="index.php?page=<?php echo $totalPaginas; ?>"><?php echo $totalPaginas; ?></a></li>
                        <?php endif; ?>
                        <?php if ($paginaActual < $totalPaginas) : ?>
                            <li class="page-item"><a class="page-link" href="index.php?page=<?php echo $paginaActual + 1; ?>">&raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php else : ?>
                <div class="alert alert-info">No hay documentos asociados a este paciente.</div>
            <?php endif; ?>
        <?php elseif (isset($_POST['buscarPaciente'])) : ?>
            <div class="alert alert-danger">No se encontró ningún paciente con la identificación proporcionada.</div>
        <?php endif; ?>
    </div>

    <!-- jQuery y Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/confirmDelete.js"></script>
</body>

</html>