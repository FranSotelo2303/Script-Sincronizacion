<?php
require 'functions.php'; // Incluir las funciones comunes

// Verificar si el ID es válido y está presente
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Invalid request'); // Terminar ejecución si la solicitud es inválida
}

$document = getDocumentById(intval($_GET['id'])); // Obtener el documento usando la función definida

if (!$document) {
    echo "File not found."; // Mostrar mensaje si no se encuentra el archivo
    exit;
}

// Configurar headers para la descarga del archivo
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream'); // Este tipo de contenido sugiere que el archivo debe ser descargado
header('Content-Disposition: attachment; filename="' . basename($document['file_name']) . '"'); // Forzar descarga
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $document['file_size']); // Asegurar el tamaño correcto del archivo

echo $document['file_content']; // Enviar contenido del archivo al navegador
?>
