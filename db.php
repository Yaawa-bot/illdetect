<?php
$host = 'localhost';
$dbname = 'illdetect';  // Remplacez par votre nom de BD
$username = 'root';                 // Votre utilisateur BD
$password = '';                     // Votre mot de passe BD

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de connexion BD: ' . $e->getMessage()]);
    exit;
}
?>