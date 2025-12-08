<?php
// Récupération des boutiques
try {
    $stmt = $pdo->query("SELECT * FROM boutiques ORDER BY created_at DESC");
    $boutiques = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de récupération des boutiques: " . $e->getMessage());
    $boutiques = [];
}

// Récupération des catégories depuis la table 'categorie'
try {
    $stmt = $pdo->query("SELECT id, designation FROM categorie WHERE statut = 0 ORDER BY id ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de récupération des catégories: " . $e->getMessage());
    $categories = [];
}