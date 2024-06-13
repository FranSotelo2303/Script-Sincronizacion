<?php
require 'config.php'; // Incluir configuración de la base de datos

// Función para buscar un paciente por documento ID
function findPatientById($documentoId)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM pacientes WHERE documentoId = ?");
    $stmt->bind_param("s", $documentoId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();  // Devuelve el paciente encontrado o null
}

// Función para manejar la subida de archivos
function handleFileUpload($file, $documentoId, $docType)
{
    global $conn;
    $fileName = $documentoId . ' - ' . $file['name'];
    $fileType = $file['type'];
    $fileSize = $file['size'];
    $fileContent = file_get_contents($file['tmp_name']);
    $uploadDate = date('Y-m-d H:i:s');

    if ($fileType != 'application/pdf') {
        return ['success' => false, 'message' => 'Solo se permiten archivos PDF.'];
    }

    $stmt = $conn->prepare("INSERT INTO files (file_name, file_type, doc_type, file_size, file_content, upload_date, pacienteId) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $null = NULL; // Placeholder para el contenido binario
    $stmt->bind_param("sssibss", $fileName, $fileType, $docType, $fileSize, $null, $uploadDate, $documentoId);
    $stmt->send_long_data(4, $fileContent);

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Archivo subido exitosamente'];
    } else {
        return ['success' => false, 'message' => 'Error al subir el archivo: ' . $stmt->error];
    }
}

// Función para obtener la cuenta total de documentos y preparar la paginación
function getDocumentCount($documentoId)
{
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM files WHERE pacienteId = ?");
    $stmt->bind_param("s", $documentoId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'];  // Devuelve el total de documentos
}

// Función para obtener los documentos paginados
function getPaginatedDocuments($documentoId, $limit, $offset, $filterType = '', $filterDate = '')
{
    global $conn;
    $params = [$documentoId];
    $types = "s";

    $sqlConditions = " WHERE pacienteId = ?";
    if (!empty($filterType)) {
        $sqlConditions .= " AND doc_type = ?";
        $params[] = $filterType;
        $types .= "s";
    }
    if (!empty($filterDate)) {
        $sqlConditions .= " AND DATE(upload_date) = ?";
        $params[] = $filterDate;
        $types .= "s";
    }
    $sqlConditions .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare("SELECT * FROM files" . $sqlConditions);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();  // Devuelve el resultado de la consulta
}

// Función para obtener un documento por ID
function getDocumentById($id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Función para eliminar un documento
function deleteDocument($id)
{
    global $conn;
    $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

function cleanInput($data) {
    $data = trim($data);        // Elimina espacios en blanco del principio y el final
    $data = stripslashes($data);  // Elimina las barras invertidas
    $data = htmlspecialchars($data);  // Convierte caracteres especiales en entidades HTML
    return $data;
}

