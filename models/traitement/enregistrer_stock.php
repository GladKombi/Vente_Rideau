<?php
// Désactiver l'affichage des erreurs pour ne pas polluer la réponse JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// S'assurer que la réponse est bien en JSON
header('Content-Type: application/json');

// Vérifier la session
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    $_SESSION['flash_message'] = ['text' => 'Accès non autorisé', 'type' => 'error'];
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé', 'redirect' => 'stock_boutique.php']);
    exit;
}

$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    $_SESSION['flash_message'] = ['text' => 'Session invalide', 'type' => 'error'];
    echo json_encode(['success' => false, 'message' => 'Session invalide', 'redirect' => 'stock_boutique.php']);
    exit;
}

// Vérifier la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = ['text' => 'Méthode non autorisée', 'type' => 'error'];
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée', 'redirect' => 'stock_boutique.php']);
    exit;
}

try {
    // Récupérer les données du formulaire
    $produit_matricule = trim($_POST['produit_matricule'] ?? '');
    $numero_rideau_id = isset($_POST['numero_rideau_id']) ? (int)$_POST['numero_rideau_id'] : null;
    $quantite = isset($_POST['quantite']) ? (float)$_POST['quantite'] : 0;
    $prix = isset($_POST['prix']) ? (float)$_POST['prix'] : 0;
    $seuil_alerte_stock = isset($_POST['seuil_alerte_stock']) ? (int)$_POST['seuil_alerte_stock'] : 5;
    $type_mouvement = 'approvisionnement';

    // Vérifier les données obligatoires
    if (empty($produit_matricule)) {
        $_SESSION['flash_message'] = ['text' => 'Le produit est obligatoire', 'type' => 'error'];
        echo json_encode(['success' => false, 'message' => 'Le produit est obligatoire', 'redirect' => 'stock_boutique.php']);
        exit;
    }

    if ($quantite <= 0) {
        $_SESSION['flash_message'] = ['text' => 'La quantité doit être supérieure à 0', 'type' => 'error'];
        echo json_encode(['success' => false, 'message' => 'La quantité doit être supérieure à 0', 'redirect' => 'stock_boutique.php']);
        exit;
    }

    if ($prix <= 0) {
        $_SESSION['flash_message'] = ['text' => 'Le prix doit être supérieur à 0', 'type' => 'error'];
        echo json_encode(['success' => false, 'message' => 'Le prix doit être supérieur à 0', 'redirect' => 'stock_boutique.php']);
        exit;
    }

    if ($seuil_alerte_stock <= 0) {
        $seuil_alerte_stock = 5;
    }

    // Vérifier que le produit existe et est actif
    $checkProduit = $pdo->prepare("SELECT p.matricule, p.designation, p.umProduit FROM produits p WHERE p.matricule = ? AND p.statut = 0 AND p.actif = 1");
    $checkProduit->execute([$produit_matricule]);
    $produit = $checkProduit->fetch(PDO::FETCH_ASSOC);

    if (!$produit) {
        $_SESSION['flash_message'] = ['text' => 'Produit invalide ou inexistant', 'type' => 'error'];
        echo json_encode(['success' => false, 'message' => 'Produit invalide ou inexistant', 'redirect' => 'stock_boutique.php']);
        exit;
    }

    // Vérifier que la boutique existe
    $checkBoutique = $pdo->prepare("SELECT id FROM boutiques WHERE id = ? AND statut = 0 AND actif = 1");
    $checkBoutique->execute([$boutique_id]);
    if (!$checkBoutique->fetch()) {
        $_SESSION['flash_message'] = ['text' => 'Boutique invalide', 'type' => 'error'];
        echo json_encode(['success' => false, 'message' => 'Boutique invalide', 'redirect' => 'stock_boutique.php']);
        exit;
    }

    // Si le produit est en mètres, vérifier le numéro de rideau
    $numero_selectionne = null;
    if ($produit['umProduit'] === 'metres') {
        if (empty($numero_rideau_id) || $numero_rideau_id <= 0) {
            $_SESSION['flash_message'] = ['text' => 'Le numéro de rideau est obligatoire pour les produits en mètres', 'type' => 'error'];
            echo json_encode(['success' => false, 'message' => 'Le numéro de rideau est obligatoire pour les produits en mètres', 'redirect' => 'stock_boutique.php']);
            exit;
        }

        // Vérifier que le numéro de rideau existe, est disponible et appartient à la boutique
        $checkNumero = $pdo->prepare("
            SELECT id, numero_rideau, est_utilise 
            FROM numeros_rideaux 
            WHERE id = ? AND boutique_id = ? AND est_utilise = 0 AND actif = 1
        ");
        $checkNumero->execute([$numero_rideau_id, $boutique_id]);
        $numero = $checkNumero->fetch(PDO::FETCH_ASSOC);

        if (!$numero) {
            $_SESSION['flash_message'] = ['text' => 'Numéro de rideau invalide, déjà utilisé ou non disponible', 'type' => 'error'];
            echo json_encode(['success' => false, 'message' => 'Numéro de rideau invalide, déjà utilisé ou non disponible', 'redirect' => 'stock_boutique.php']);
            exit;
        }

        $numero_selectionne = $numero;
    } else {
        // Pour les produits en pièces, le numéro de rideau doit être null
        $numero_rideau_id = null;
    }

    // Démarrer une transaction
    $pdo->beginTransaction();

    // Insérer le stock
    $insert = $pdo->prepare("
        INSERT INTO stock (
            type_mouvement, 
            boutique_id, 
            produit_matricule, 
            numero_rideau_id, 
            quantite, 
            prix, 
            seuil_alerte_stock,
            statut
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0)
    ");

    $insert->execute([
        $type_mouvement,
        $boutique_id,
        $produit_matricule,
        $numero_rideau_id,
        $quantite,
        $prix,
        $seuil_alerte_stock
    ]);

    $stock_id = $pdo->lastInsertId();

    // Si un numéro de rideau a été utilisé, le marquer comme utilisé
    if ($numero_rideau_id && $numero_rideau_id > 0) {
        $updateNumero = $pdo->prepare("UPDATE numeros_rideaux SET est_utilise = 1 WHERE id = ?");
        $updateNumero->execute([$numero_rideau_id]);
    }

    // Valider la transaction
    $pdo->commit();

    // Construire le message de succès
    $message = "Stock enregistré avec succès !";
    if ($numero_selectionne) {
        $message .= " Numéro de rideau attribué : " . $numero_selectionne['numero_rideau'];
    }

    $_SESSION['flash_message'] = ['text' => $message, 'type' => 'success'];

    // Retourner la réponse
    echo json_encode([
        'success' => true,
        'message' => $message,
        'stock_id' => $stock_id,
        'produit' => $produit['designation'],
        'numero_rideau' => $numero_selectionne ? $numero_selectionne['numero_rideau'] : null,
        'redirect' => 'stock_boutique.php'
    ]);
} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Erreur enregistrement stock: " . $e->getMessage());
    $_SESSION['flash_message'] = ['text' => 'Erreur base de données: ' . $e->getMessage(), 'type' => 'error'];
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données: ' . $e->getMessage(),
        'redirect' => 'stock_boutique.php'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Erreur enregistrement stock: " . $e->getMessage());
    $_SESSION['flash_message'] = ['text' => $e->getMessage(), 'type' => 'error'];
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'redirect' => 'stock_boutique.php'
    ]);
}
