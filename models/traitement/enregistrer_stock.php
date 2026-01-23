<?php
require_once '../../connexion/connexion.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$response = ['success' => false, 'message' => ''];

try {
    // Vérification des données POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    $boutique_id = $_POST['boutique_id'] ?? null;
    $produit_matricule = $_POST['produit_matricule'] ?? null;
    $quantite = $_POST['quantite'] ?? null;
    $prix = $_POST['prix'] ?? null;
    $seuil_alerte_stock = $_POST['seuil_alerte_stock'] ?? 5;

    // Validation des données
    if (!$boutique_id || !$produit_matricule || !$quantite || !$prix) {
        throw new Exception('Tous les champs obligatoires doivent être remplis');
    }

    // Validation des valeurs numériques
    if (!is_numeric($quantite) || $quantite <= 0) {
        throw new Exception('La quantité doit être un nombre positif');
    }

    if (!is_numeric($prix) || $prix <= 0) {
        throw new Exception('Le prix doit être un nombre positif');
    }

    // Vérifier si le produit existe
    $stmt = $pdo->prepare("SELECT * FROM produits WHERE matricule = ? AND statut = 0");
    $stmt->execute([$produit_matricule]);
    $produit = $stmt->fetch();

    if (!$produit) {
        throw new Exception('Produit introuvable');
    }

    // Vérifier si la boutique existe
    $stmt = $pdo->prepare("SELECT * FROM boutiques WHERE id = ? AND statut = 0");
    $stmt->execute([$boutique_id]);
    $boutique = $stmt->fetch();

    if (!$boutique) {
        throw new Exception('Boutique introuvable');
    }

    // Insérer le stock
    $stmt = $pdo->prepare("
        INSERT INTO stock 
        (type_mouvement, boutique_id, produit_matricule, quantite, prix, seuil_alerte_stock, date_creation, statut)
        VALUES ('approvisionnement', ?, ?, ?, ?, ?, NOW(), 0)
    ");

    $stmt->execute([
        $boutique_id,
        $produit_matricule,
        $quantite,
        $prix,
        $seuil_alerte_stock
    ]);

    $response['success'] = true;
    $response['message'] = 'Stock enregistré avec succès';
    $response['redirect'] = 'stock_boutique.php';

} catch (PDOException $e) {
    $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    error_log("Erreur PDO: " . $e->getMessage());
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Retourner la réponse en JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>