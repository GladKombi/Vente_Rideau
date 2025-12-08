<?php
// Récupération des membres
$stmt = $pdo->query("SELECT * FROM membres ORDER BY created_at DESC");
$membres = $stmt->fetchAll(PDO::FETCH_ASSOC);