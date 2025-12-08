<?php

# Paramètres de connexion en local
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "gestion_loyer";
# Paramètres de connexion en ligne
// $servername = "";
// $username = "";
// $password = "";
// $dbname = "";
# Demarer la session
session_start();
$error_message = "";
try {
	$pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
	// En cas d'erreur de connexion ou de requête, stocker le message
	$error_message = "Erreur de connexion à la base de données : " . $e->getMessage();
}