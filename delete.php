<?php
require 'functions.php'; // Incluir funciones comunes

if (isset($_GET['id'])) {
    deleteDocument(intval($_GET['id'])); // Usar función para eliminar
    header('Location: index.php'); // Redireccionar al índice
    exit;
}
?>
