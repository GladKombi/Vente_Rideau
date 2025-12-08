<?php

// Récupération des affectations avec jointures (requêtes préparées)
try {
    $stmt = $pdo->prepare("SELECT a.*, m.nom as membre_nom, m.postnom as membre_postnom, m.prenom as membre_prenom, m.telephone as membre_telephone, b.numero as boutique_numero, c.designation as categorie_nom FROM affectation a
        LEFT JOIN membres m ON a.membre = m.id
        LEFT JOIN boutiques b ON a.boutique = b.id
        LEFT JOIN categorie c ON b.categorie = c.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erreur de récupération des affectations: " . $e->getMessage());
    $affectations = [];
}

// Récupération des membres actifs (requêtes préparées)
try {
    $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom, telephone FROM membres WHERE statut = 0 ORDER BY nom, postnom");
    $stmt->execute();
    $membres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erreur de récupération des membres: " . $e->getMessage());
    $membres = [];
}

// Récupération des boutiques actives et libres (requêtes préparées)
try {
    $stmt = $pdo->prepare("SELECT b.id, b.numero, c.designation as categorie_nom FROM boutiques b LEFT JOIN categorie c ON b.categorie = c.id WHERE b.statut = 0 AND b.etat = 'libre' ORDER BY b.numero");
    $stmt->execute();
    $boutiques = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erreur de récupération des boutiques: " . $e->getMessage());
    $boutiques = [];
}

// Date automatique (aujourd'hui)
$date_automatique = date('Y-m-d');