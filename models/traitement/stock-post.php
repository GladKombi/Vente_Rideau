<?php
# Connexion à la base de données
include '../../connexion/connexion.php';

// Vérification de l'authentification PDG
// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
//     $_SESSION['flash_message'] = [
//         'text' => "Accès non autorisé. Veuillez vous connecter qu syste avant d'accedez.",
//         'type' => "error"
//     ];
//     header('Location: ../../login.php');
//     exit;
// }

// Initialisation des variables
$message = '';
$message_type = '';

// --- AJOUTER UN NOUVEAU STOCK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_stock'])) {
    try {
        $boutique_id = (int)$_POST['boutique_id'];
        $produit_matricule = trim($_POST['produit_matricule']);
        $quantite = (float)$_POST['quantite'];
        $prix = (float)$_POST['prix'];
        $seuil_alerte_stock = (int)$_POST['seuil_alerte_stock'];
        
        // Validation des données
        if ($boutique_id <= 0) {
            throw new Exception("Boutique invalide.");
        }
        
        if (empty($produit_matricule)) {
            throw new Exception("Matricule du produit requis.");
        }
        
        if ($quantite <= 0) {
            throw new Exception("La quantité doit être supérieure à 0.");
        }
        
        if ($prix <= 0) {
            throw new Exception("Le prix doit être supérieur à 0.");
        }
        
        if ($seuil_alerte_stock < 0) {
            throw new Exception("Le seuil d'alerte ne peut pas être négatif.");
        }
        
        // Vérifier que la boutique existe et est active
        $queryBoutique = $pdo->prepare("SELECT id, nom FROM boutiques WHERE id = ? AND statut = 0 AND actif = 1");
        $queryBoutique->execute([$boutique_id]);
        $boutique = $queryBoutique->fetch();
        
        if (!$boutique) {
            throw new Exception("Boutique non trouvée ou inactive.");
        }
        
        // Vérifier que le produit existe et est actif
        $queryProduit = $pdo->prepare("SELECT matricule, designation FROM produits WHERE matricule = ? AND statut = 0 AND actif = 1");
        $queryProduit->execute([$produit_matricule]);
        $produit = $queryProduit->fetch();
        
        if (!$produit) {
            throw new Exception("Produit non trouvé ou inactif.");
        }
        
        // Vérifier s'il existe déjà un stock pour ce produit dans cette boutique
        $queryExisting = $pdo->prepare("
            SELECT id, quantite, prix 
            FROM stock 
            WHERE boutique_id = ? 
              AND produit_matricule = ? 
              AND type_mouvement = 'approvisionnement'
              AND statut = 0
        ");
        $queryExisting->execute([$boutique_id, $produit_matricule]);
        $existingStock = $queryExisting->fetch(PDO::FETCH_ASSOC);
        
        if ($existingStock) {
            // Mettre à jour le stock existant
            $newQuantite = $existingStock['quantite'] + $quantite;
            
            // Calculer le prix moyen si on veut fusionner les prix
            // Ici, on garde simplement le prix existant pour la cohérence
            $newPrix = $existingStock['prix'];
            
            $queryUpdate = $pdo->prepare("
                UPDATE stock 
                SET quantite = ?, 
                    date_creation = CURRENT_TIMESTAMP
                WHERE id = ? AND statut = 0
            ");
            $queryUpdate->execute([$newQuantite, $existingStock['id']]);
            
            $_SESSION['flash_message'] = [
                'text' => "Stock existant mis à jour avec succès ! ($quantite unités ajoutées)",
                'type' => "success"
            ];
        } else {
            // Créer un nouveau stock
            $queryInsert = $pdo->prepare("
                INSERT INTO stock (
                    type_mouvement,
                    boutique_id,
                    produit_matricule,
                    quantite,
                    prix,
                    seuil_alerte_stock,
                    date_creation,
                    statut
                ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 0)
            ");
            $queryInsert->execute([
                'approvisionnement',
                $boutique_id,
                $produit_matricule,
                $quantite,
                $prix,
                $seuil_alerte_stock
            ]);
            
            $_SESSION['flash_message'] = [
                'text' => "Nouveau stock ajouté avec succès !",
                'type' => "success"
            ];
        }
        
        header('Location: ../../views/stocks.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'ajout du stock: " . $e->getMessage(),
            'type' => "error"
        ];
        header('Location: ../../views/stocks.php');
        exit;
    }
}

// --- MODIFIER UN STOCK EXISTANT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_stock'])) {
    try {
        $stock_id = (int)$_POST['stock_id'];
        $boutique_id = (int)$_POST['boutique_id'];
        $produit_matricule = trim($_POST['produit_matricule']);
        $quantite = (float)$_POST['quantite'];
        $prix = (float)$_POST['prix'];
        $seuil_alerte_stock = (int)$_POST['seuil_alerte_stock'];
        
        // Validation des données
        if ($stock_id <= 0) {
            throw new Exception("ID de stock invalide.");
        }
        
        if ($boutique_id <= 0) {
            throw new Exception("Boutique invalide.");
        }
        
        if (empty($produit_matricule)) {
            throw new Exception("Matricule du produit requis.");
        }
        
        if ($quantite <= 0) {
            throw new Exception("La quantité doit être supérieure à 0.");
        }
        
        if ($prix <= 0) {
            throw new Exception("Le prix doit être supérieur à 0.");
        }
        
        if ($seuil_alerte_stock < 0) {
            throw new Exception("Le seuil d'alerte ne peut pas être négatif.");
        }
        
        // Vérifier que le stock existe et n'est pas archivé
        $queryStock = $pdo->prepare("
            SELECT id 
            FROM stock 
            WHERE id = ? AND statut = 0
        ");
        $queryStock->execute([$stock_id]);
        $stock = $queryStock->fetch();
        
        if (!$stock) {
            throw new Exception("Stock non trouvé ou déjà archivé.");
        }
        
        // Vérifier que la boutique existe et est active
        $queryBoutique = $pdo->prepare("SELECT id FROM boutiques WHERE id = ? AND statut = 0 AND actif = 1");
        $queryBoutique->execute([$boutique_id]);
        if (!$queryBoutique->fetch()) {
            throw new Exception("Boutique non trouvée ou inactive.");
        }
        
        // Vérifier que le produit existe et est actif
        $queryProduit = $pdo->prepare("SELECT matricule FROM produits WHERE matricule = ? AND statut = 0 AND actif = 1");
        $queryProduit->execute([$produit_matricule]);
        if (!$queryProduit->fetch()) {
            throw new Exception("Produit non trouvé ou inactif.");
        }
        
        // Mettre à jour le stock
        $queryUpdate = $pdo->prepare("
            UPDATE stock 
            SET boutique_id = ?,
                produit_matricule = ?,
                quantite = ?,
                prix = ?,
                seuil_alerte_stock = ?,
                date_creation = CURRENT_TIMESTAMP
            WHERE id = ? AND statut = 0
        ");
        $queryUpdate->execute([
            $boutique_id,
            $produit_matricule,
            $quantite,
            $prix,
            $seuil_alerte_stock,
            $stock_id
        ]);
        
        $_SESSION['flash_message'] = [
            'text' => "Stock #{$stock_id} modifié avec succès !",
            'type' => "success"
        ];
        
        header('Location: ../../views/stocks.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de la modification du stock: " . $e->getMessage(),
            'type' => "error"
        ];
        header('Location: ../../views/stocks.php');
        exit;
    }
}

// --- ARCHIVER UN STOCK (SOFT DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archiver_stock'])) {
    try {
        $stock_id = (int)$_POST['stock_id'];
        
        if ($stock_id <= 0) {
            throw new Exception("ID de stock invalide.");
        }
        
        // Vérifier que le stock existe et n'est pas déjà archivé
        $queryStock = $pdo->prepare("
            SELECT id 
            FROM stock 
            WHERE id = ? AND statut = 0
        ");
        $queryStock->execute([$stock_id]);
        $stock = $queryStock->fetch();
        
        if (!$stock) {
            throw new Exception("Stock non trouvé ou déjà archivé.");
        }
        
        // Archiver le stock (soft delete)
        $queryUpdate = $pdo->prepare("
            UPDATE stock 
            SET statut = 1 
            WHERE id = ? AND statut = 0
        ");
        $queryUpdate->execute([$stock_id]);
        
        $_SESSION['flash_message'] = [
            'text' => "Stock #{$stock_id} archivé avec succès !",
            'type' => "success"
        ];
        
        header('Location: ../../views/stocks.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'archivage du stock: " . $e->getMessage(),
            'type' => "error"
        ];
        header('Location: ../../views/stocks.php');
        exit;
    }
}

// --- GESTION DES TRANSFERTS ENTRE BOUTIQUES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transferer_stock'])) {
    try {
        $stock_id = (int)$_POST['stock_id'];
        $boutique_destination_id = (int)$_POST['boutique_destination_id'];
        $quantite_transferee = (float)$_POST['quantite_transferee'];
        
        if ($stock_id <= 0) {
            throw new Exception("ID de stock invalide.");
        }
        
        if ($boutique_destination_id <= 0) {
            throw new Exception("Boutique de destination invalide.");
        }
        
        if ($quantite_transferee <= 0) {
            throw new Exception("La quantité transférée doit être supérieure à 0.");
        }
        
        // Récupérer les informations du stock source
        $queryStockSource = $pdo->prepare("
            SELECT s.*, b.nom as boutique_nom, p.designation as produit_designation
            FROM stock s 
            JOIN boutiques b ON s.boutique_id = b.id 
            JOIN produits p ON s.produit_matricule = p.matricule 
            WHERE s.id = ? AND s.statut = 0
        ");
        $queryStockSource->execute([$stock_id]);
        $stockSource = $queryStockSource->fetch(PDO::FETCH_ASSOC);
        
        if (!$stockSource) {
            throw new Exception("Stock source non trouvé ou archivé.");
        }
        
        // Vérifier que la quantité disponible est suffisante
        if ($stockSource['quantite'] < $quantite_transferee) {
            throw new Exception("Quantité insuffisante en stock. Disponible: " . $stockSource['quantite'] . " unités");
        }
        
        // Vérifier que la boutique de destination existe
        $queryBoutiqueDest = $pdo->prepare("SELECT id, nom FROM boutiques WHERE id = ? AND statut = 0 AND actif = 1");
        $queryBoutiqueDest->execute([$boutique_destination_id]);
        $boutiqueDest = $queryBoutiqueDest->fetch(PDO::FETCH_ASSOC);
        
        if (!$boutiqueDest) {
            throw new Exception("Boutique de destination non trouvée ou inactive.");
        }
        
        // Vérifier si la boutique de destination a déjà un stock pour ce produit
        $queryStockDest = $pdo->prepare("
            SELECT id, quantite, prix 
            FROM stock 
            WHERE boutique_id = ? 
              AND produit_matricule = ? 
              AND type_mouvement = 'approvisionnement'
              AND statut = 0
        ");
        $queryStockDest->execute([$boutique_destination_id, $stockSource['produit_matricule']]);
        $stockDest = $queryStockDest->fetch(PDO::FETCH_ASSOC);
        
        // Démarrer une transaction
        $pdo->beginTransaction();
        
        try {
            // 1. Réduire le stock source
            $nouvelle_quantite_source = $stockSource['quantite'] - $quantite_transferee;
            $queryUpdateSource = $pdo->prepare("
                UPDATE stock 
                SET quantite = ?
                WHERE id = ? AND statut = 0
            ");
            $queryUpdateSource->execute([$nouvelle_quantite_source, $stock_id]);
            
            // 2. Créer un enregistrement de transfert dans la table stock
            $queryInsertTransfert = $pdo->prepare("
                INSERT INTO stock (
                    type_mouvement,
                    boutique_id,
                    produit_matricule,
                    quantite,
                    prix,
                    seuil_alerte_stock,
                    date_creation,
                    statut
                ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 0)
            ");
            $queryInsertTransfert->execute([
                'transfert',
                $boutique_destination_id,
                $stockSource['produit_matricule'],
                $quantite_transferee,
                $stockSource['prix'],
                $stockSource['seuil_alerte_stock']
            ]);
            
            // 3. Mettre à jour ou créer le stock destination
            if ($stockDest) {
                // Mettre à jour le stock existant
                $nouvelle_quantite_dest = $stockDest['quantite'] + $quantite_transferee;
                $queryUpdateDest = $pdo->prepare("
                    UPDATE stock 
                    SET quantite = ?
                    WHERE id = ? AND statut = 0
                ");
                $queryUpdateDest->execute([$nouvelle_quantite_dest, $stockDest['id']]);
            } else {
                // Créer un nouveau stock pour la destination
                $queryInsertDest = $pdo->prepare("
                    INSERT INTO stock (
                        type_mouvement,
                        boutique_id,
                        produit_matricule,
                        quantite,
                        prix,
                        seuil_alerte_stock,
                        date_creation,
                        statut
                    ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 0)
                ");
                $queryInsertDest->execute([
                    'approvisionnement',
                    $boutique_destination_id,
                    $stockSource['produit_matricule'],
                    $quantite_transferee,
                    $stockSource['prix'],
                    $stockSource['seuil_alerte_stock']
                ]);
            }
            
            // Valider la transaction
            $pdo->commit();
            
            $_SESSION['flash_message'] = [
                'text' => "Transfert de {$quantite_transferee} unités effectué avec succès vers '{$boutiqueDest['nom']}' !",
                'type' => "success"
            ];
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            $pdo->rollBack();
            throw $e;
        }
        
        header('Location: ../../views/stocks.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors du transfert: " . $e->getMessage(),
            'type' => "error"
        ];
        header('Location: ../../views/stocks.php');
        exit;
    }
}

// --- AJUSTER LA QUANTITÉ DE STOCK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajuster_stock'])) {
    try {
        $stock_id = (int)$_POST['stock_id'];
        $nouvelle_quantite = (float)$_POST['nouvelle_quantite'];
        $raison = trim($_POST['raison'] ?? 'Ajustement de stock');
        
        if ($stock_id <= 0) {
            throw new Exception("ID de stock invalide.");
        }
        
        if ($nouvelle_quantite < 0) {
            throw new Exception("La quantité ne peut pas être négative.");
        }
        
        // Vérifier que le stock existe
        $queryStock = $pdo->prepare("
            SELECT quantite, boutique_id, produit_matricule, prix
            FROM stock 
            WHERE id = ? AND statut = 0
        ");
        $queryStock->execute([$stock_id]);
        $stock = $queryStock->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock) {
            throw new Exception("Stock non trouvé ou archivé.");
        }
        
        // Mettre à jour la quantité
        $queryUpdate = $pdo->prepare("
            UPDATE stock 
            SET quantite = ?,
                date_creation = CURRENT_TIMESTAMP
            WHERE id = ? AND statut = 0
        ");
        $queryUpdate->execute([$nouvelle_quantite, $stock_id]);
        
        $difference = $nouvelle_quantite - $stock['quantite'];
        $typeMessage = $difference > 0 ? "ajoutée" : "retirée";
        
        $_SESSION['flash_message'] = [
            'text' => "Stock ajusté avec succès ! " . abs($difference) . " unités $typeMessage.",
            'type' => "success"
        ];
        
        header('Location: ../../views/stocks.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'ajustement du stock: " . $e->getMessage(),
            'type' => "error"
        ];
        header('Location: ../../views/stocks.php');
        exit;
    }
}

// Si aucune action valide n'est détectée, rediriger vers la page stocks
header('Location: ../../views/stocks.php');
exit;