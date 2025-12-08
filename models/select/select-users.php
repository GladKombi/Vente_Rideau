<?php
// Récupération des utilisateurs
$stmt = $pdo->query("SELECT * FROM utilisateurs WHERE statut=0 ORDER BY created_at DESC");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
