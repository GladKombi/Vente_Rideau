<?php
include '../../connexion/connexion.php';

// Vérification de l'authentification PDG
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    $_SESSION['flash_message'] = ['text' => 'Accès non autorisé', 'type' => 'error'];
    header('Location: ../../login.php');
    exit;
}

// Fonction pour générer le prochain matricule selon le type
function genererMatricule($pdo, $umProduit) {
    // Déterminer le préfixe selon l'unité
    $prefix = $umProduit == 'metres' ? 'Rid-' : 'Pcs-';
    
    // Récupérer le dernier matricule selon le type (produits non archivés)
    $query = $pdo->prepare("SELECT matricule FROM produits WHERE matricule LIKE ? AND statut = 0 ORDER BY matricule DESC LIMIT 1");
    $query->execute([$prefix . '%']);
    $lastMatricule = $query->fetchColumn();
    
    if ($lastMatricule) {
        // Extraire le numéro et l'incrémenter
        $lastNumber = intval(substr($lastMatricule, 4));
        $newNumber = $lastNumber + 1;
    } else {
        // Vérifier s'il existe des produits archivés pour continuer la séquence
        $queryArchived = $pdo->prepare("SELECT matricule FROM produits WHERE matricule LIKE ? ORDER BY matricule DESC LIMIT 1");
        $queryArchived->execute([$prefix . '%']);
        $lastArchivedMatricule = $queryArchived->fetchColumn();
        
        if ($lastArchivedMatricule) {
            $lastNumber = intval(substr($lastArchivedMatricule, 4));
            $newNumber = $lastNumber + 1;
        } else {
            // Premier produit de ce type
            $newNumber = 1;
        }
    }
    
    // Formater avec 3 chiffres
    return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

// --- AJOUT D'UN NOUVEAU PRODUIT ---
if (isset($_POST['ajouter_produit'])) {
    try {
        // Validation des données
        $designation = trim($_POST['designation']);
        $umProduit = $_POST['umProduit'] ?? 'pieces'; // Par défaut : pièces
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        // Vérifier que la désignation n'est pas vide
        if (empty($designation)) {
            $_SESSION['flash_message'] = [
                'text' => 'La désignation du produit est obligatoire',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Validation de l'unité de mesure
        if (!in_array($umProduit, ['metres', 'pieces'])) {
            $_SESSION['flash_message'] = [
                'text' => 'Unité de mesure invalide',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Vérifier si un produit avec la même désignation existe déjà (non archivé)
        $checkDesignationQuery = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE designation = ? AND statut = 0");
        $checkDesignationQuery->execute([$designation]);
        $designationExists = $checkDesignationQuery->fetchColumn();
        
        if ($designationExists > 0) {
            $_SESSION['flash_message'] = [
                'text' => 'Un produit avec cette désignation existe déjà',
                'type' => 'warning'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Générer le matricule automatiquement selon le type
        $matricule = genererMatricule($pdo, $umProduit);
        
        // Préparation de la requête d'insertion
        $query = $pdo->prepare("INSERT INTO produits (matricule, designation, umProduit, actif, statut) VALUES (?, ?, ?, ?, 0)");
        $result = $query->execute([$matricule, $designation, $umProduit, $actif]);
        
        if ($result && $query->rowCount() > 0) {
            $typeProduit = $umProduit == 'metres' ? 'rideau (au mètre)' : 'produit (à la pièce)';
            $_SESSION['flash_message'] = [
                'text' => 'Produit ajouté avec succès ! Matricule : ' . $matricule . ' - Type : ' . $typeProduit,
                'type' => 'success'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'text' => 'Erreur lors de l\'ajout du produit',
                'type' => 'error'
            ];
        }
        
    } catch (PDOException $e) {
        // Gestion spécifique des erreurs de contrainte d'unicité
        if ($e->getCode() == 23000) {
            $_SESSION['flash_message'] = [
                'text' => 'Ce matricule existe déjà. Veuillez réessayer.',
                'type' => 'error'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'text' => 'Erreur de base de données : ' . $e->getMessage(),
                'type' => 'error'
            ];
        }
    }
    
    header('Location: ../../views/produits.php');
    exit;
}

// --- MODIFICATION D'UN PRODUIT ---
if (isset($_POST['modifier_produit'])) {
    try {
        $matricule_original = $_POST['matricule_original'];
        $designation = trim($_POST['designation']);
        $umProduit = $_POST['umProduit'] ?? 'pieces';
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        // Vérifier que la désignation n'est pas vide
        if (empty($designation)) {
            $_SESSION['flash_message'] = [
                'text' => 'La désignation du produit est obligatoire',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Validation de l'unité de mesure
        if (!in_array($umProduit, ['metres', 'pieces'])) {
            $_SESSION['flash_message'] = [
                'text' => 'Unité de mesure invalide',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Vérifier que le produit existe et n'est pas archivé
        $checkQuery = $pdo->prepare("SELECT matricule, designation FROM produits WHERE matricule = ? AND statut = 0");
        $checkQuery->execute([$matricule_original]);
        $existingProduit = $checkQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingProduit) {
            $_SESSION['flash_message'] = [
                'text' => 'Le produit n\'existe pas ou a été archivé',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Vérifier si un autre produit a déjà cette désignation (exclure le produit actuel)
        $checkDuplicateQuery = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE designation = ? AND matricule != ? AND statut = 0");
        $checkDuplicateQuery->execute([$designation, $matricule_original]);
        $duplicateExists = $checkDuplicateQuery->fetchColumn();
        
        if ($duplicateExists > 0) {
            $_SESSION['flash_message'] = [
                'text' => 'Un autre produit utilise déjà cette désignation',
                'type' => 'warning'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Vérifier si l'unité a changé (ce qui nécessiterait un nouveau matricule)
        $currentProduitQuery = $pdo->prepare("SELECT umProduit FROM produits WHERE matricule = ?");
        $currentProduitQuery->execute([$matricule_original]);
        $currentUmProduit = $currentProduitQuery->fetchColumn();
        
        if ($currentUmProduit != $umProduit) {
            // L'unité a changé, nous devons générer un nouveau matricule
            $newMatricule = genererMatricule($pdo, $umProduit);
            
            // Vérifier si le nouveau matricule existe déjà
            $checkNewMatricule = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE matricule = ?");
            $checkNewMatricule->execute([$newMatricule]);
            $newMatriculeExists = $checkNewMatricule->fetchColumn();
            
            if ($newMatriculeExists > 0) {
                $_SESSION['flash_message'] = [
                    'text' => 'Erreur : le matricule généré existe déjà. Veuillez contacter l\'administrateur.',
                    'type' => 'error'
                ];
                header('Location: ../../views/produits.php');
                exit;
            }
            
            // Mise à jour avec nouveau matricule
            $query = $pdo->prepare("UPDATE produits SET matricule = ?, designation = ?, umProduit = ?, actif = ? WHERE matricule = ? AND statut = 0");
            $result = $query->execute([$newMatricule, $designation, $umProduit, $actif, $matricule_original]);
            
            if ($result && $query->rowCount() > 0) {
                $_SESSION['flash_message'] = [
                    'text' => 'Produit modifié avec succès ! Nouveau matricule : ' . $newMatricule . ' (Unité changée de ' . $currentUmProduit . ' à ' . $umProduit . ')',
                    'type' => 'success'
                ];
            } else {
                $_SESSION['flash_message'] = [
                    'text' => 'Aucune modification effectuée',
                    'type' => 'info'
                ];
            }
        } else {
            // L'unité n'a pas changé, mise à jour normale
            $query = $pdo->prepare("UPDATE produits SET designation = ?, umProduit = ?, actif = ? WHERE matricule = ? AND statut = 0");
            $result = $query->execute([$designation, $umProduit, $actif, $matricule_original]);
            
            if ($result && $query->rowCount() > 0) {
                $_SESSION['flash_message'] = [
                    'text' => 'Produit modifié avec succès !',
                    'type' => 'success'
                ];
            } else {
                $_SESSION['flash_message'] = [
                    'text' => 'Aucune modification effectuée',
                    'type' => 'info'
                ];
            }
        }
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => 'Erreur lors de la modification : ' . $e->getMessage(),
            'type' => 'error'
        ];
    }
    
    header('Location: ../../views/produits.php');
    exit;
}

// --- ACTIVATION/DÉSACTIVATION D'UN PRODUIT ---
if (isset($_POST['toggle_actif'])) {
    try {
        $matricule = $_POST['matricule'];
        
        // Vérifier que le produit existe et n'est pas archivé
        $checkQuery = $pdo->prepare("SELECT designation, actif FROM produits WHERE matricule = ? AND statut = 0");
        $checkQuery->execute([$matricule]);
        $produit = $checkQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$produit) {
            $_SESSION['flash_message'] = [
                'text' => 'Produit non trouvé ou archivé',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Inverser l'état actif (toggle)
        $nouvelEtat = $produit['actif'] ? 0 : 1;
        $action = $nouvelEtat ? 'activé' : 'désactivé';
        
        // Mise à jour de l'état actif
        $updateQuery = $pdo->prepare("UPDATE produits SET actif = ? WHERE matricule = ? AND statut = 0");
        $result = $updateQuery->execute([$nouvelEtat, $matricule]);
        
        if ($result && $updateQuery->rowCount() > 0) {
            $_SESSION['flash_message'] = [
                'text' => 'Produit "' . $produit['designation'] . '" ' . $action . ' avec succès !',
                'type' => 'success'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'text' => 'Erreur lors du changement d\'état',
                'type' => 'error'
            ];
        }
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => 'Erreur : ' . $e->getMessage(),
            'type' => 'error'
        ];
    }
    
    header('Location: ../../views/produits.php');
    exit;
}

// --- SUPPRESSION (ARCHIVAGE) D'UN PRODUIT ---
if (isset($_POST['supprimer_produit'])) {
    try {
        $matricule = $_POST['matricule'];
        
        // Vérifier que le produit existe et n'est pas déjà archivé
        $checkQuery = $pdo->prepare("SELECT designation, umProduit FROM produits WHERE matricule = ? AND statut = 0");
        $checkQuery->execute([$matricule]);
        $produit = $checkQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$produit) {
            $_SESSION['flash_message'] = [
                'text' => 'Produit non trouvé ou déjà archivé',
                'type' => 'warning'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Vérifier si le produit est utilisé dans des stocks
        $checkStockQuery = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE produit_matricule = ? AND statut = 0");
        $checkStockQuery->execute([$matricule]);
        $stockCount = $checkStockQuery->fetchColumn();
        
        if ($stockCount > 0) {
            $_SESSION['flash_message'] = [
                'text' => 'Impossible d\'archiver ce produit : il est utilisé dans ' . $stockCount . ' entrée(s) de stock.',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Soft Delete : Mise à jour du statut à 1 (archivé)
        $updateQuery = $pdo->prepare("UPDATE produits SET statut = 1 WHERE matricule = ?");
        $result = $updateQuery->execute([$matricule]);
        
        if ($result && $updateQuery->rowCount() > 0) {
            $typeProduit = $produit['umProduit'] == 'metres' ? 'rideau' : 'produit';
            $_SESSION['flash_message'] = [
                'text' => '📁 ' . ucfirst($typeProduit) . ' "' . $produit['designation'] . '" archivé avec succès !',
                'type' => 'success'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'text' => 'Erreur lors de l\'archivage',
                'type' => 'error'
            ];
        }
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => 'Erreur : ' . $e->getMessage(),
            'type' => 'error'
        ];
    }
    
    header('Location: ../../views/produits.php');
    exit;
}

// --- RESTAURATION D'UN PRODUIT ARCHIVÉ (optionnel) ---
if (isset($_POST['restaurer_produit'])) {
    try {
        $matricule = $_POST['matricule'];
        
        // Vérifier que le produit existe et est archivé
        $checkQuery = $pdo->prepare("SELECT designation, umProduit FROM produits WHERE matricule = ? AND statut = 1");
        $checkQuery->execute([$matricule]);
        $produit = $checkQuery->fetch(PDO::FETCH_ASSOC);
        
        if (!$produit) {
            $_SESSION['flash_message'] = [
                'text' => 'Produit non trouvé ou non archivé',
                'type' => 'warning'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Vérifier si un produit actif avec le même matricule existe (cas improbable)
        $checkActiveQuery = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE matricule = ? AND statut = 0");
        $checkActiveQuery->execute([$matricule]);
        $activeExists = $checkActiveQuery->fetchColumn();
        
        if ($activeExists > 0) {
            $_SESSION['flash_message'] = [
                'text' => 'Impossible de restaurer : un produit actif avec ce matricule existe déjà',
                'type' => 'error'
            ];
            header('Location: ../../views/produits.php');
            exit;
        }
        
        // Restauration : Mise à jour du statut à 0 (actif)
        $updateQuery = $pdo->prepare("UPDATE produits SET statut = 0, actif = 1 WHERE matricule = ?");
        $result = $updateQuery->execute([$matricule]);
        
        if ($result && $updateQuery->rowCount() > 0) {
            $typeProduit = $produit['umProduit'] == 'metres' ? 'rideau' : 'produit';
            $_SESSION['flash_message'] = [
                'text' => '🔄 ' . ucfirst($typeProduit) . ' "' . $produit['designation'] . '" restauré avec succès !',
                'type' => 'success'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'text' => 'Erreur lors de la restauration',
                'type' => 'error'
            ];
        }
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => 'Erreur : ' . $e->getMessage(),
            'type' => 'error'
        ];
    }
    
    header('Location: ../../views/produits.php');
    exit;
}

// Redirection par défaut si aucune action n'est reconnue
$_SESSION['flash_message'] = [
    'text' => '⚠️ Action non reconnue',
    'type' => 'error'
];
header('Location: ../../views/produits.php');
exit;
?>