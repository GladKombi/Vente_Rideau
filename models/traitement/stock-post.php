<?php
include '../../connexion/connexion.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['ajouter_stock'])) {
            // Validation des données
            $boutique_id = (int)$_POST['boutique_id'];
            $produit_matricule = trim($_POST['produit_matricule']);
            $quantite = (float)$_POST['quantite'];
            $seuil_alerte_stock = (int)$_POST['seuil_alerte_stock'];
            $type_mouvement = 'approvisionnement'; // Toujours approvisionnement
            
            // Vérifier si la boutique existe et est active
            $boutique_check = $pdo->prepare("SELECT id FROM boutiques WHERE id = ? AND statut = 0 AND actif = 1");
            $boutique_check->execute([$boutique_id]);
            
            if (!$boutique_check->fetch()) {
                throw new Exception("Boutique non trouvée, inactive ou supprimée");
            }
            
            // Vérifier si le produit existe et est actif
            $produit_check = $pdo->prepare("SELECT matricule FROM produits WHERE matricule = ? AND statut = 0 AND actif = 1");
            $produit_check->execute([$produit_matricule]);
            
            if (!$produit_check->fetch()) {
                throw new Exception("Produit non trouvé, inactif ou supprimé");
            }
            
            // Vérifier si le stock existe déjà pour cette boutique et produit
            $existing_check = $pdo->prepare("SELECT id FROM stock WHERE boutique_id = ? AND produit_matricule = ? AND type_mouvement = 'approvisionnement' AND statut = 0");
            $existing_check->execute([$boutique_id, $produit_matricule]);
            
            if ($existing_check->fetch()) {
                throw new Exception("Un approvisionnement existe déjà pour ce produit dans cette boutique. Utilisez la modification.");
            }
            
            // Insérer le nouveau stock
            $query = $pdo->prepare("
                INSERT INTO stock (type_mouvement, boutique_id, produit_matricule, quantite, seuil_alerte_stock, date_creation, statut)
                VALUES (?, ?, ?, ?, ?, NOW(), 0)
            ");
            
            $query->execute([$type_mouvement, $boutique_id, $produit_matricule, $quantite, $seuil_alerte_stock]);
            
            $_SESSION['flash_message'] = [
                'text' => "Approvisionnement ajouté avec succès!",
                'type' => "success"
            ];
            
        } elseif (isset($_POST['modifier_stock'])) {
            $stock_id = (int)$_POST['stock_id'];
            $quantite = (float)$_POST['quantite'];
            $seuil_alerte_stock = (int)$_POST['seuil_alerte_stock'];
            
            // Vérifier si le stock existe
            $stock_check = $pdo->prepare("SELECT id FROM stock WHERE id = ? AND statut = 0 AND type_mouvement = 'approvisionnement'");
            $stock_check->execute([$stock_id]);
            
            if (!$stock_check->fetch()) {
                throw new Exception("Stock non trouvé, déjà archivé ou n'est pas un approvisionnement");
            }
            
            // Mettre à jour le stock
            $query = $pdo->prepare("
                UPDATE stock 
                SET quantite = ?, seuil_alerte_stock = ? 
                WHERE id = ? AND statut = 0
            ");
            
            $query->execute([$quantite, $seuil_alerte_stock, $stock_id]);
            
            $_SESSION['flash_message'] = [
                'text' => "Stock modifié avec succès!",
                'type' => "success"
            ];
            
        } elseif (isset($_POST['archiver_stock'])) {
            $stock_id = (int)$_POST['stock_id'];
            
            // Vérifier si c'est un approvisionnement
            $type_check = $pdo->prepare("SELECT type_mouvement FROM stock WHERE id = ?");
            $type_check->execute([$stock_id]);
            $type = $type_check->fetchColumn();
            
            if ($type !== 'approvisionnement') {
                throw new Exception("Seuls les approvisionnements peuvent être archivés depuis cette page");
            }
            
            // Archiver le stock (soft delete)
            $query = $pdo->prepare("UPDATE stock SET statut = 1 WHERE id = ?");
            $query->execute([$stock_id]);
            
            $_SESSION['flash_message'] = [
                'text' => "Stock archivé avec succès!",
                'type' => "success"
            ];
        }
        
        // Redirection
        header('Location: ../../views/stocks.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur: " . $e->getMessage(),
            'type' => "error"
        ];
        header('Location: ../../views/stocks.php');
        exit;
    }
}
?>