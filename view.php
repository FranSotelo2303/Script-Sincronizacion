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

// Configurar headers para mostrar el contenido en el navegador
header('Content-Type: ' . $document['file_type']); // Asegurar el tipo de contenido correcto
header('Content-Disposition: inline; filename="' . $document['file_name'] . '"'); // Configurar para visualización en línea

echo $document['file_content']; // Enviar contenido del archivo al navegador
?>
