<?php
# get_stocks_boutique.php - Récupère les stocks disponibles par boutique
session_start();
include '../connexion.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer l'ID de la boutique
$boutique_id = isset($_GET['boutique_id']) ? (int)$_GET['boutique_id'] : 0;

if ($boutique_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID boutique invalide']);
    exit;
}

try {
    // Récupérer les stocks disponibles pour cette boutique
    $query = $pdo->prepare("
        SELECT s.produit_matricule, 
               p.designation, 
               p.umProduit,
               SUM(s.quantite) as quantite,
               (SELECT SUM(quantite) FROM stock WHERE produit_matricule = s.produit_matricule AND statut = 0) as quantite_totale
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE s.boutique_id = ? 
          AND s.statut = 0
          AND s.quantite > 0
        GROUP BY s.produit_matricule, p.designation, p.umProduit
        HAVING quantite > 0
        ORDER BY p.designation
    ");
    
    $query->execute([$boutique_id]);
    $stocks = $query->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stocks' => [
            $boutique_id => $stocks
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
}