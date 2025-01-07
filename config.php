<?php 
// config.php
session_start();

$servername = "localhost";
$username = "u422925957_mega";
$password = "jaguarE1131944937870";
$dbname = "u422925957_mega";

// Criação da conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificação da conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
?>
