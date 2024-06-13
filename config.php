<?php
// Configuración de conexión a la base de datos
$host = "localhost";
$username = "root";
$password = "";
$dbname = "file_upload";

// Crear conexión
$conn = new mysqli($host, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
