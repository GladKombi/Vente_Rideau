<?php
// api/get_realisation.php
header('Content-Type: application/json');

require_once '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier que l'utilisateur est bien connecté
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['pdg', 'boutique'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit;
}

$id = (int)$_GET['id'];

try {
    // Récupération de la réalisation
    $stmt = $pdo->prepare("
        SELECT r.*, b.nom as boutique_nom
        FROM realisations r
        JOIN boutiques b ON r.boutique_id = b.id
        WHERE r.id = ? AND r.statut = 0
    ");
    $stmt->execute([$id]);
    $realisation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$realisation) {
        echo json_encode(['success' => false, 'message' => 'Réalisation non trouvée']);
        exit;
    }

    // Récupérer les images supplémentaires
    $stmtImg = $pdo->prepare("SELECT id, image_url, ordre FROM realisation_images WHERE realisation_id = ? ORDER BY ordre ASC");
    $stmtImg->execute([$id]);
    $realisation['images_supp'] = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $realisation
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
