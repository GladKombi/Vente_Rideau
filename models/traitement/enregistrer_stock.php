<?php

// Inclure la connexion à la base de données
include '../../connexion/connexion.php'; // Chemin relatif depuis le dossier models

// Définir le type de contenu JSON
header('Content-Type: application/json');

// Vérification de l'authentification BOUTIQUE
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé. Veuillez vous reconnecter.'
    ]);
    exit;
}

// Vérifier la connexion PDO
if (!isset($pdo) || !$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit;
}

// Récupérer l'ID de la boutique depuis la session
$boutique_id = $_SESSION['boutique_id'] ?? null;

if (!$boutique_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Boutique non identifiée'
    ]);
    exit;
}

// Vérifier que les données sont envoyées en POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Vérifier que boutique_id correspond
$boutique_id_form = $_POST['boutique_id'] ?? null;

if ($boutique_id_form != $boutique_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur d\'authentification de la boutique'
    ]);
    exit;
}

// Récupérer et valider les données
$produit_matricule = trim($_POST['produit_matricule'] ?? '');
$quantite = isset($_POST['quantite']) ? floatval(str_replace(',', '.', $_POST['quantite'])) : 0;
$prix = isset($_POST['prix']) ? floatval(str_replace(',', '.', $_POST['prix'])) : 0;
$seuil_alerte_stock = isset($_POST['seuil_alerte_stock']) ? intval($_POST['seuil_alerte_stock']) : 5;

// Validation des données
$errors = [];

if (empty($produit_matricule)) {
    $errors[] = 'Le produit est requis';
}

if ($quantite <= 0) {
    $errors[] = 'La quantité doit être supérieure à 0';
}

if ($prix <= 0) {
    $errors[] = 'Le prix doit être supérieur à 0';
}

if ($seuil_alerte_stock < 1) {
    $errors[] = 'Le seuil d\'alerte doit être au moins de 1';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode(', ', $errors)
    ]);
    exit;
}

try {
    // Vérifier si le produit existe
    $stmt = $pdo->prepare("
        SELECT matricule, designation, umProduit 
        FROM produits 
        WHERE matricule = ? AND statut = 0 AND actif = TRUE
    ");
    $stmt->execute([$produit_matricule]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produit) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé ou inactif'
        ]);
        exit;
    }

    // Vérifier si le produit existe déjà en stock AVEC LE MÊME PRIX
    $stmt = $pdo->prepare("
        SELECT id, quantite, prix 
        FROM stock 
        WHERE boutique_id = ? 
        AND produit_matricule = ? 
        AND prix = ?
        AND statut = 0
    ");
    $stmt->execute([$boutique_id, $produit_matricule, $prix]);
    $stock_existant_meme_prix = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stock_existant_meme_prix) {
        // Si le produit existe avec le même prix, on met à jour la quantité
        $nouvelle_quantite = $stock_existant_meme_prix['quantite'] + $quantite;

        $stmt = $pdo->prepare("
            UPDATE stock 
            SET quantite = ?, 
                seuil_alerte_stock = GREATEST(?, seuil_alerte_stock),
                date_creation = NOW()
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $nouvelle_quantite,
            $seuil_alerte_stock,
            $stock_existant_meme_prix['id']
        ]);

        if ($result) {
            $operation = 'mis à jour (même prix)';
            $stock_id = $stock_existant_meme_prix['id'];
            $quantite_ajoutee = $quantite;
            $prix_utilise = $prix;
        } else {
            throw new Exception('Échec de la mise à jour du stock existant');
        }
    } else {
        // Vérifier s'il existe déjà un stock avec un prix différent
        $stmt = $pdo->prepare("
            SELECT id, quantite, prix 
            FROM stock 
            WHERE boutique_id = ? 
            AND produit_matricule = ? 
            AND statut = 0
        ");
        $stmt->execute([$boutique_id, $produit_matricule]);
        $stock_existant_prix_different = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stock_existant_prix_different) {
            // Il existe déjà un stock avec un prix différent, on crée un nouveau stock
            $stmt = $pdo->prepare("
                INSERT INTO stock (
                    boutique_id, 
                    produit_matricule, 
                    quantite, 
                    prix, 
                    type_mouvement,
                    seuil_alerte_stock,
                    date_creation
                ) VALUES (?, ?, ?, ?, 'approvisionnement', ?, NOW())
            ");

            $result = $stmt->execute([
                $boutique_id,
                $produit_matricule,
                $quantite,
                $prix,
                $seuil_alerte_stock
            ]);

            if ($result) {
                $operation = 'créé (nouveau prix)';
                $stock_id = $pdo->lastInsertId();
                $quantite_ajoutee = $quantite;
                $prix_utilise = $prix;
                
                // Message informatif sur le prix différent
                $ancien_prix = $stock_existant_prix_different['prix'];
                $message_info = "Un stock existe déjà pour ce produit avec un prix différent (ancien prix: {$ancien_prix} \$, nouveau prix: {$prix} \$).";
            } else {
                throw new Exception('Échec de la création d\'un nouveau stock (prix différent)');
            }
        } else {
            // Aucun stock existant, création d'un nouveau stock
            $stmt = $pdo->prepare("
                INSERT INTO stock (
                    boutique_id, 
                    produit_matricule, 
                    quantite, 
                    prix, 
                    type_mouvement,
                    seuil_alerte_stock,
                    date_creation
                ) VALUES (?, ?, ?, ?, 'approvisionnement', ?, NOW())
            ");

            $result = $stmt->execute([
                $boutique_id,
                $produit_matricule,
                $quantite,
                $prix,
                $seuil_alerte_stock
            ]);

            if ($result) {
                $operation = 'créé (premier stock)';
                $stock_id = $pdo->lastInsertId();
                $quantite_ajoutee = $quantite;
                $prix_utilise = $prix;
                $message_info = "Premier stock créé pour ce produit.";
            } else {
                throw new Exception('Échec de la création du premier stock');
            }
        }
    }

    // Préparer le message de succès
    $unite = ($produit['umProduit'] == 'metres') ? 'mètres' : 'pièces';
    $quantite_formatee = number_format($quantite_ajoutee, 3);
    $prix_formate = number_format($prix_utilise, 2);
    
    // Message final
    $message_final = "✅ Stock $operation avec succès ! <br>
                     <strong>$quantite_formatee $unite</strong> de <strong>{$produit['designation']}</strong> ajoutés.<br>
                     Prix unitaire: <strong>$prix_formate \$</strong> | 
                     ID Stock: <strong>#$stock_id</strong>";
    
    // Ajouter l'info sur le prix différent si nécessaire
    if (isset($message_info) && !empty($message_info)) {
        $message_final .= "<br><small><i class='fas fa-info-circle'></i> $message_info</small>";
    }

    // Stocker le message dans la session
    $_SESSION['flash_message'] = [
        'text' => $message_final,
        'type' => 'success'
    ];

    // Réponse JSON avec redirection
    echo json_encode([
        'success' => true,
        'message' => 'Stock enregistré avec succès',
        'redirect' => '../views/stock_boutique.php'
    ]);
} catch (PDOException $e) {
    // Journaliser l'erreur
    error_log('Erreur PDO lors de l\'enregistrement du stock: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Journaliser l'erreur
    error_log('Erreur générale lors de l\'enregistrement du stock: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}