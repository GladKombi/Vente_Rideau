<?php
session_start();
include '../connexion/connexion.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

header('Content-Type: application/json');

if (isset($_GET['action']) && $_GET['action'] == 'get_stock' && isset($_GET['id'])) {
    $stockId = $_GET['id'];
    try {
        $query = $pdo->prepare("
            SELECT s.*, 
                   b.nom as boutique_nom, 
                   p.designation as produit_designation 
            FROM stock s 
            JOIN boutiques b ON s.boutique_id = b.id 
            JOIN produits p ON s.produit_id = p.matricule 
            WHERE s.id = ? AND s.statut = 0
        ");
        $query->execute([$stockId]);
        $stock = $query->fetch(PDO::FETCH_ASSOC);

        if ($stock) {
            echo json_encode(['success' => true, 'stock' => $stock]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Stock non trouvé']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
    }
    exit;
}
?>