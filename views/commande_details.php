<?php
// commande_details.php
include '../connexion/connexion.php';

// Vérification de l'authentification BOUTIQUE
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de la boutique connectée
$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

// Récupération de l'ID de la commande
$commande_id = $_GET['id'] ?? null;
if (!$commande_id) {
    header('Location: ventes_boutique.php');
    exit;
}

// Initialisation des variables
$message = '';
$message_type = '';

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Vérifier que la commande appartient à la boutique connectée
try {
    $query = $pdo->prepare("
        SELECT c.*, b.nom as boutique_nom
        FROM commandes c
        JOIN boutiques b ON c.boutique_id = b.id
        WHERE c.id = ? AND c.boutique_id = ? AND c.statut = 0
    ");
    $query->execute([$commande_id, $boutique_id]);
    $commande = $query->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        $_SESSION['flash_message'] = [
            'text' => "Commande non trouvée ou vous n'avez pas les droits d'accès.",
            'type' => "error"
        ];
        header('Location: ventes_boutique.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur de base de données: " . $e->getMessage(),
        'type' => "error"
    ];
    header('Location: ventes_boutique.php');
    exit;
}

// Récupérer les produits de cette commande (avec statut = 0 seulement)
try {
    $query = $pdo->prepare("
        SELECT 
            cp.id as commande_produit_id,
            cp.quantite,
            cp.prix_unitaire,
            s.id as stock_id,
            s.quantite as stock_initial,
            p.matricule,
            p.designation,
            p.umProduit,
            (cp.quantite * cp.prix_unitaire) as total_ligne
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE cp.commande_id = ? 
          AND cp.statut = 0
          AND s.statut = 0
          AND p.statut = 0
        ORDER BY cp.id DESC
    ");
    $query->execute([$commande_id]);
    $produits_commande = $query->fetchAll(PDO::FETCH_ASSOC);

    // Calculer le total de la commande
    $total_commande = 0;
    foreach ($produits_commande as $produit) {
        $total_commande += $produit['total_ligne'];
    }
} catch (PDOException $e) {
    $produits_commande = [];
    $total_commande = 0;
    error_log("Erreur récupération produits commande: " . $e->getMessage());
}

// Récupérer les paiements de cette commande
try {
    $query = $pdo->prepare("
        SELECT p.* 
        FROM paiements p
        WHERE p.commandes_id = ? 
          AND p.statut = 0
        ORDER BY p.date DESC
    ");
    $query->execute([$commande_id]);
    $paiements = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le total payé
    $total_paye = 0;
    foreach ($paiements as $paiement) {
        $total_paye += $paiement['montant'];
    }
    
    // Calculer le reste à payer
    $reste_a_payer = $total_commande - $total_paye;
    
} catch (PDOException $e) {
    $paiements = [];
    $total_paye = 0;
    $reste_a_payer = $total_commande;
    error_log("Erreur récupération paiements: " . $e->getMessage());
}

// Récupérer les produits disponibles en stock pour cette boutique
try {
    $query = $pdo->prepare("
        SELECT 
            s.id as stock_id,
            s.quantite as stock_initial,
            s.prix,
            s.seuil_alerte_stock,
            p.matricule,
            p.designation,
            p.umProduit,
            -- Calcul du stock déjà commandé pour ce produit
            COALESCE(
                (SELECT SUM(cp2.quantite) 
                 FROM commande_produits cp2 
                 WHERE cp2.stock_id = s.id 
                   AND cp2.statut = 0
                   AND cp2.commande_id != ?), 
                0
            ) as deja_commande
        FROM stock s
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ? 
          AND s.statut = 0 
          AND p.statut = 0
          AND p.actif = 1
        ORDER BY p.designation
    ");
    $query->execute([$commande_id, $boutique_id]);
    $produits_base = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le stock disponible pour chaque produit
    $produits_disponibles = [];
    foreach ($produits_base as $produit) {
        $stock_disponible = $produit['stock_initial'] - $produit['deja_commande'];
        
        // Ne garder que les produits avec stock disponible > 0
        if ($stock_disponible > 0) {
            $produit['stock_disponible'] = $stock_disponible;
            $produit['niveau_stock'] = ($stock_disponible <= $produit['seuil_alerte_stock']) ? 'faible' : 'ok';
            $produits_disponibles[] = $produit;
        }
    }
    
} catch (PDOException $e) {
    $produits_disponibles = [];
    error_log("Erreur récupération produits disponibles: " . $e->getMessage());
}

// --- TRAITEMENT DES FORMULAIRES ---

// Ajouter un produit à la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_produit'])) {
    try {
        // Vérifier que le stock est disponible ET appartient à la boutique
        $stock_id = (int)$_POST['stock_id'];
        $quantite_demandee = (float)$_POST['quantite'];

        // Récupérer les informations du stock AVEC vérification boutique
        $query = $pdo->prepare("
            SELECT s.quantite as stock_initial, s.prix, p.matricule, p.designation, p.umProduit
            FROM stock s
            JOIN produits p ON s.produit_matricule = p.matricule
            WHERE s.id = ? 
              AND s.boutique_id = ?  -- Vérification supplémentaire
              AND s.statut = 0
        ");
        $query->execute([$stock_id, $boutique_id]);
        $stock = $query->fetch(PDO::FETCH_ASSOC);

        if (!$stock) {
            $_SESSION['flash_message'] = [
                'text' => "Stock non trouvé ou non disponible dans votre boutique.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }

        // Vérifier la validité de la quantité selon l'unité
        $umProduit = $stock['umProduit'];
        if ($umProduit == 'pieces') {
            // Pour les pièces, la quantité doit être un entier
            if (fmod($quantite_demandee, 1) != 0) {
                $_SESSION['flash_message'] = [
                    'text' => "Pour les produits à la pièce, la quantité doit être un nombre entier.",
                    'type' => "error"
                ];
                header("Location: commande_details.php?id=$commande_id");
                exit;
            }
        }

        // Calculer le stock déjà commandé POUR CETTE COMMANDE AUSSI
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(quantite), 0) as deja_commande_dans_cette_commande
            FROM commande_produits 
            WHERE stock_id = ? 
              AND commande_id = ? 
              AND statut = 0
        ");
        $query->execute([$stock_id, $commande_id]);
        $deja_dans_cette_commande = $query->fetchColumn();

        // Calculer le stock déjà commandé dans les AUTRES commandes
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(quantite), 0) as deja_commande_autres
            FROM commande_produits 
            WHERE stock_id = ? 
              AND commande_id != ? 
              AND statut = 0
        ");
        $query->execute([$stock_id, $commande_id]);
        $deja_dans_autres_commandes = $query->fetchColumn();

        // Calculer le stock total disponible
        $stock_total_disponible = $stock['stock_initial'] - $deja_dans_autres_commandes;
        
        // Vérifier si on a assez de stock pour cette commande (en incluant ce qui est déjà dans cette commande)
        $stock_disponible_pour_cette_commande = $stock_total_disponible + $deja_dans_cette_commande;
        
        if ($quantite_demandee > $stock_disponible_pour_cette_commande) {
            $uniteTexte = $umProduit == 'metres' ? 'mètres' : 'pièces';
            $_SESSION['flash_message'] = [
                'text' => "Stock insuffisant. Disponible: " . number_format($stock_total_disponible, 3) . " " . $uniteTexte,
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }

        // Utiliser le prix du formulaire ou celui du stock
        $prix_unitaire = $_POST['prix_unitaire'] ? (float)$_POST['prix_unitaire'] : $stock['prix'];

        if ($prix_unitaire <= 0) {
            $_SESSION['flash_message'] = [
                'text' => "Le prix unitaire doit être supérieur à 0.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }

        // Ajouter le produit à la commande
        $query = $pdo->prepare("
            INSERT INTO commande_produits (commande_id, stock_id, quantite, prix_unitaire)
            VALUES (?, ?, ?, ?)
        ");
        $query->execute([
            $commande_id,
            $stock_id,
            $quantite_demandee,
            $prix_unitaire
        ]);

        $_SESSION['flash_message'] = [
            'text' => "Produit '{$stock['designation']}' ajouté à la commande !",
            'type' => "success"
        ];

        header("Location: commande_details.php?id=$commande_id");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'ajout du produit: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}

// Retirer un produit de la commande (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retirer_produit'])) {
    try {
        $commande_produit_id = (int)$_POST['commande_produit_id'];

        // Récupérer les informations du produit avant suppression
        $query = $pdo->prepare("
            SELECT cp.quantite, cp.stock_id, p.designation
            FROM commande_produits cp
            JOIN stock s ON cp.stock_id = s.id
            JOIN produits p ON s.produit_matricule = p.matricule
            WHERE cp.id = ? AND cp.statut = 0
        ");
        $query->execute([$commande_produit_id]);
        $produit = $query->fetch(PDO::FETCH_ASSOC);

        if ($produit) {
            // Mettre à jour le statut du produit de commande (soft delete)
            $query = $pdo->prepare("
                UPDATE commande_produits 
                SET statut = 1 
                WHERE id = ?
            ");
            $query->execute([$commande_produit_id]);

            $_SESSION['flash_message'] = [
                'text' => "Produit '{$produit['designation']}' retiré de la commande !",
                'type' => "success"
            ];
        }

        header("Location: commande_details.php?id=$commande_id");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors du retrait du produit: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}

// Modifier la quantité d'un produit dans la commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_quantite'])) {
    try {
        $commande_produit_id = (int)$_POST['commande_produit_id'];
        $nouvelle_quantite = (float)$_POST['nouvelle_quantite'];

        if ($nouvelle_quantite <= 0) {
            $_SESSION['flash_message'] = [
                'text' => "La quantité doit être supérieure à 0.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }

        // Récupérer l'ancienne quantité et les infos du stock
        $query = $pdo->prepare("
            SELECT cp.quantite as ancienne_quantite, cp.stock_id, p.designation, p.umProduit
            FROM commande_produits cp
            JOIN stock s ON cp.stock_id = s.id
            JOIN produits p ON s.produit_matricule = p.matricule
            WHERE cp.id = ? AND cp.statut = 0
        ");
        $query->execute([$commande_produit_id]);
        $produit = $query->fetch(PDO::FETCH_ASSOC);

        if (!$produit) {
            $_SESSION['flash_message'] = [
                'text' => "Produit non trouvé dans la commande.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }

        // Vérifier la validité de la quantité selon l'unité
        $umProduit = $produit['umProduit'];
        if ($umProduit == 'pieces') {
            // Pour les pièces, la quantité doit être un entier
            if (fmod($nouvelle_quantite, 1) != 0) {
                $_SESSION['flash_message'] = [
                    'text' => "Pour les produits à la pièce, la quantité doit être un nombre entier.",
                    'type' => "error"
                ];
                header("Location: commande_details.php?id=$commande_id");
                exit;
            }
        }

        // Récupérer le stock initial
        $query = $pdo->prepare("
            SELECT s.quantite as stock_initial
            FROM stock s
            WHERE s.id = ?
        ");
        $query->execute([$produit['stock_id']]);
        $stock_info = $query->fetch(PDO::FETCH_ASSOC);

        // Calculer le stock déjà commandé dans les AUTRES commandes
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(quantite), 0) as deja_commande_autres
            FROM commande_produits 
            WHERE stock_id = ? 
              AND commande_id != ? 
              AND id != ?
              AND statut = 0
        ");
        $query->execute([$produit['stock_id'], $commande_id, $commande_produit_id]);
        $deja_dans_autres_commandes = $query->fetchColumn();

        // Calculer le stock disponible pour les autres commandes
        $stock_disponible_pour_nouvelle = $stock_info['stock_initial'] - $deja_dans_autres_commandes;

        // Vérifier si on a assez de stock pour la nouvelle quantité
        if ($nouvelle_quantite > $stock_disponible_pour_nouvelle) {
            $uniteTexte = $umProduit == 'metres' ? 'mètres' : 'pièces';
            $_SESSION['flash_message'] = [
                'text' => "Stock insuffisant pour cette modification. Stock disponible: " . number_format($stock_disponible_pour_nouvelle, 3) . " " . $uniteTexte,
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }

        // Mettre à jour la quantité dans la commande
        $query = $pdo->prepare("
            UPDATE commande_produits 
            SET quantite = ?
            WHERE id = ? AND statut = 0
        ");
        $query->execute([$nouvelle_quantite, $commande_produit_id]);

        $uniteTexte = $umProduit == 'metres' ? 'mètres' : 'pièces';
        $_SESSION['flash_message'] = [
            'text' => "Quantité du produit '{$produit['designation']}' modifiée avec succès ! (Nouvelle quantité: " . number_format($nouvelle_quantite, 3) . " " . $uniteTexte . ")",
            'type' => "success"
        ];

        header("Location: commande_details.php?id=$commande_id");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de la modification: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}

// Modifier le prix unitaire d'un produit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_prix'])) {
    try {
        $commande_produit_id = (int)$_POST['commande_produit_id'];
        $nouveau_prix = (float)$_POST['nouveau_prix'];

        if ($nouveau_prix < 0) {
            $_SESSION['flash_message'] = [
                'text' => "Le prix ne peut pas être négatif.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }

        $query = $pdo->prepare("
            UPDATE commande_produits 
            SET prix_unitaire = ?
            WHERE id = ? AND statut = 0
        ");
        $query->execute([$nouveau_prix, $commande_produit_id]);

        $_SESSION['flash_message'] = [
            'text' => "Prix modifié avec succès !",
            'type' => "success"
        ];

        header("Location: commande_details.php?id=$commande_id");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de la modification du prix: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}

// Enregistrer un paiement CASH (montant total automatique)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enregistrer_paiement_cash'])) {
    try {
        // Vérifier si la commande a déjà été payée
        if ($commande['etat'] == 'payee') {
            $_SESSION['flash_message'] = [
                'text' => "Cette commande a déjà été payée.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }
        
        // Vérifier qu'il y a des produits dans la commande
        if (empty($produits_commande)) {
            $_SESSION['flash_message'] = [
                'text' => "Impossible d'enregistrer un paiement : la commande ne contient aucun produit.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }
        
        // Vérifier qu'il reste un montant à payer
        if ($reste_a_payer <= 0) {
            $_SESSION['flash_message'] = [
                'text' => "Le montant total de la commande a déjà été payé.",
                'type' => "error"
            ];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }
        
        // Enregistrer le paiement du montant total
        $montant = $reste_a_payer; // Payer tout le reste à payer
        $date_paiement = date('Y-m-d');
        
        $query = $pdo->prepare("
            INSERT INTO paiements (`date`, `commandes_id`, `montant`, `statut`)
            VALUES (?, ?, ?, '0')
        ");
        $query->execute([$date_paiement,$commande_id, $montant]);
        
        // Marquer la commande comme payée
        $query = $pdo->prepare("
            UPDATE commandes 
            SET etat = 'payee'
            WHERE id = ? AND boutique_id = ? AND statut = 0
        ");
        $query->execute([$commande_id, $boutique_id]);
        
        $_SESSION['flash_message'] = [
            'text' => "Paiement CASH de " . number_format($montant, 2) . " $ enregistré avec succès ! Redirection vers la facture...",
            'type' => "success"
        ];
        
        // Rediriger vers la page facture-cash.php avec l'ID de la commande
        header("Location: facture-cash.php?id=$commande_id");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'enregistrement du paiement CASH: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}

// Annuler un paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_paiement'])) {
    try {
        $paiement_id = (int)$_POST['paiement_id'];
        
        // Marquer le paiement comme annulé (soft delete)
        $query = $pdo->prepare("
            UPDATE paiements 
            SET statut = 1
            WHERE id = ? AND commandes_id = ?
        ");
        $query->execute([$paiement_id, $commande_id]);
        
        // Recalculer le statut de la commande
        $query = $pdo->prepare("
            SELECT SUM(montant) as total_paye 
            FROM paiements 
            WHERE commandes_id = ? AND statut = 0
        ");
        $query->execute([$commande_id]);
        $nouveau_total_paye = $query->fetchColumn() ?? 0;
        
        if ($nouveau_total_paye < $total_commande) {
            // Si le total payé est inférieur au total, remettre en brouillon
            $query = $pdo->prepare("
                UPDATE commandes 
                SET etat = 'brouillon'
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            $query->execute([$commande_id, $boutique_id]);
        }
        
        $_SESSION['flash_message'] = [
            'text' => "Paiement annulé avec succès !",
            'type' => "success"
        ];
        
        header("Location: commande_details.php?id=$commande_id");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'annulation du paiement: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}

// Finaliser la commande (changer l'état en payée)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finaliser_commande'])) {
    try {
        $query = $pdo->prepare("
            UPDATE commandes 
            SET etat = 'payee'
            WHERE id = ? AND boutique_id = ? AND statut = 0
        ");
        $query->execute([$commande_id, $boutique_id]);

        $_SESSION['flash_message'] = [
            'text' => "Commande #{$commande_id} marquée comme payée !",
            'type' => "success"
        ];

        header("Location: commande_details.php?id=$commande_id");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de la finalisation: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}

// Annuler la commande (supprimer tous les produits et remettre en stock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_commande'])) {
    try {
        // Marquer tous les produits de la commande comme supprimés
        $query = $pdo->prepare("
            UPDATE commande_produits 
            SET statut = 1 
            WHERE commande_id = ? AND statut = 0
        ");
        $query->execute([$commande_id]);

        // Archiver la commande
        $query = $pdo->prepare("
            UPDATE commandes 
            SET statut = 1
            WHERE id = ? AND boutique_id = ? AND statut = 0
        ");
        $query->execute([$commande_id, $boutique_id]);

        $_SESSION['flash_message'] = [
            'text' => "Commande #{$commande_id} annulée !",
            'type' => "success"
        ];

        header("Location: ventes_boutique.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'annulation: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Détails Commande #<?= $commande_id ?> - Boutique NGS</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0A2540;
            --secondary: #7B61FF;
            --accent: #00D4AA;
            --light: #F8FAFC;
            --dark: #1E293B;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
        }

        .font-display {
            font-family: 'Outfit', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #0A2540 0%, #1E3A5F 100%);
        }

        .gradient-green-btn {
            background: linear-gradient(90deg, #10B981 0%, #059669 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-green-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .gradient-blue-btn {
            background: linear-gradient(90deg, #4F86F7 0%, #1A5A9C 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-blue-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .gradient-purple-btn {
            background: linear-gradient(90deg, #8B5CF6 0%, #7C3AED 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-purple-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .slide-down {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-payee {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-brouillon {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .stock-ok {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .stock-faible {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .total-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .table-container {
            overflow-x: auto;
        }

        .produit-row:hover {
            background-color: #f9fafb;
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }
        
        /* Styles pour les unités de mesure */
        .unite-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .unite-metres {
            background-color: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
        }
        
        .unite-pieces {
            background-color: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }
        
        .type-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .type-rideau {
            background-color: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }
        
        .type-produit {
            background-color: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }
        
        /* Styles pour les champs avec unité */
        .input-with-unite {
            position: relative;
        }
        
        .input-with-unite input {
            padding-right: 60px;
        }
        
        .unite-label {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            color: #6b7280;
            pointer-events: none;
        }
        
        .step-info {
            display: block;
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        /* Styles pour l'impression */
        @media print {
            /* Masquer les éléments non nécessaires pour l'impression */
            aside.sidebar,
            .main-content header,
            .bg-white.rounded-2xl.shadow-soft.p-6.animate-fade-in:first-child,
            button,
            form,
            .modal,
            .flex.justify-between.items-center:last-child,
            .bg-white.rounded-2xl.shadow-soft.p-6.animate-fade-in.mt-6,
            .mt-6.flex.justify-between.items-center,
            .print\:hidden {
                display: none !important;
            }
            
            /* Afficher seulement le tableau des produits et le total */
            body {
                background-color: white !important;
                font-size: 12pt !important;
                padding: 20px !important;
            }
            
            .main-content {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            
            /* Style pour la facture */
            .facture-header {
                display: block !important;
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }
            
            .facture-header h1 {
                font-size: 24pt !important;
                margin-bottom: 10px;
                color: #000 !important;
            }
            
            .facture-header p {
                font-size: 11pt !important;
                margin: 5px 0;
                color: #000 !important;
            }
            
            /* Tableau pour l'impression */
            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 10pt !important;
                margin-top: 20px !important;
            }
            
            th, td {
                border: 1px solid #ddd !important;
                padding: 8px !important;
                text-align: left !important;
                color: #000 !important;
            }
            
            th {
                background-color: #f2f2f2 !important;
                font-weight: bold !important;
            }
            
            .total-impression {
                display: block !important;
                text-align: right;
                margin-top: 30px;
                font-size: 12pt !important;
                font-weight: bold !important;
                border-top: 2px solid #000;
                padding-top: 10px;
                color: #000 !important;
            }
            
            /* Informations de la facture */
            .infos-facture {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 20px !important;
                margin-bottom: 30px !important;
                padding: 15px !important;
                border: 1px solid #000 !important;
                color: #000 !important;
            }
            
            /* Supprimer les ombres et effets pour l'impression */
            * {
                box-shadow: none !important;
                text-shadow: none !important;
                color: #000 !important;
            }
            
            /* Afficher les éléments d'impression */
            .print\:block {
                display: block !important;
            }
            
            .print\:grid {
                display: grid !important;
            }
            
            /* Paiements pour l'impression */
            .paiements-print {
                display: block !important;
                margin-top: 30px;
                border-top: 1px solid #000;
                padding-top: 15px;
            }
            
            .paiements-print h4 {
                font-weight: bold;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="font-inter min-h-screen bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar boutique -->
        <aside class="sidebar w-64 gradient-bg text-white flex flex-col sticky top-0 h-full">
            <div class="sidebar-header p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center shadow-lg">
                        <span class="font-bold text-white text-lg font-display">NGS</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold">Boutique</h1>
                        <p class="text-xs text-gray-300">Interface de vente</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav p-4 space-y-1">
                <a href="dashboard_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="ventes_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-shopping-cart w-5 text-gray-300"></i>
                    <span>Ventes</span>
                </a>
                <a href="stock_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Stock boutique</span>
                </a>
                <a href="rapports_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-bar w-5 text-gray-300"></i>
                    <span>Rapports</span>
                </a>
            </nav>

            <div class="sidebar-footer p-4 border-t border-white/10">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200 transition-colors">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <div class="main-content flex-1 overflow-y-auto">
            <header class="bg-white border-b border-gray-200 p-6 sticky top-0 z-30 shadow-sm print:hidden">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Détails de la commande</h1>
                        <p class="text-gray-600">
                            Facture: <span class="font-bold"><?= htmlspecialchars($commande['numero_facture']) ?></span> | 
                            Client: <span class="font-bold"><?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : 'Non renseigné' ?></span> | 
                            Date: <span class="font-bold"><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></span>
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="ventes_boutique.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Retour
                        </a>
                        
                        <?php if ($commande['etat'] == 'brouillon'): ?>
                            <form method="POST" action="" class="inline" onsubmit="return confirm('Marquer cette commande comme payée ?');">
                                <button type="submit" name="finaliser_commande" 
                                        class="px-4 py-2 gradient-green-btn text-white rounded-lg hover:opacity-90 shadow-md">
                                    <i class="fas fa-check mr-2"></i>Marquer comme payée
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="status-badge status-payee text-lg">
                                <i class="fas fa-check-circle mr-2"></i>Payée
                            </span>
                        <?php endif; ?>
                        
                        <button onclick="openAnnulationModal()"
                                class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 shadow-md">
                            <i class="fas fa-times mr-2"></i>Annuler
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <?php if ($message): ?>
                    <div class="mb-6 fade-in relative z-10 animate-fade-in print:hidden">
                        <div class="
                            <?php if ($message_type === 'success'): ?>bg-green-50 text-green-700 border border-green-200
                            <?php elseif ($message_type === 'error'): ?>bg-red-50 text-red-700 border border-red-200
                            <?php elseif ($message_type === 'warning'): ?>bg-yellow-50 text-yellow-700 border border-yellow-200
                            <?php else: ?>bg-blue-50 text-blue-700 border border-blue-200<?php endif; ?>
                            rounded-xl p-4 flex items-center justify-between shadow-soft">
                            <div class="flex items-center space-x-3">
                                <?php if ($message_type === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                                <?php elseif ($message_type === 'error'): ?>
                                    <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
                                <?php elseif ($message_type === 'warning'): ?>
                                    <i class="fas fa-exclamation-triangle text-yellow-600 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-blue-600 text-lg"></i>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($message) ?></span>
                            </div>
                            <button onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- FORMULAIRE D'AJOUT DE PRODUIT (GAUCHE) -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 animate-fade-in print:hidden">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Ajouter un produit</h3>
                        
                        <?php if (empty($produits_disponibles)): ?>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-3"></i>
                                    <div>
                                        <p class="text-sm text-yellow-700 font-medium">Aucun produit disponible en stock</p>
                                        <p class="text-xs text-yellow-600 mt-1">
                                            Tous les produits sont déjà commandés ou épuisés.
                                            <br>
                                            Boutique ID: <?= $boutique_id ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="formAjoutProduit">
                            <div class="space-y-4">
                                <!-- Sélection du produit -->
                                <div>
                                    <label for="stock_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Produit disponible
                                    </label>
                                    <select id="stock_id" name="stock_id" required 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                            <?= empty($produits_disponibles) ? 'disabled' : '' ?>
                                            onchange="updateUniteInfo()">
                                        <option value=""><?= empty($produits_disponibles) ? 'Aucun produit disponible' : 'Sélectionnez un produit' ?></option>
                                        <?php foreach ($produits_disponibles as $produit): 
                                            $uniteText = $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                            $typeText = substr($produit['matricule'], 0, 3) === 'Rid' ? 'Rideau' : 'Produit';
                                        ?>
                                            <option value="<?= $produit['stock_id'] ?>" 
                                                    data-stock="<?= $produit['stock_disponible'] ?>"
                                                    data-prix="<?= $produit['prix'] ?>"
                                                    data-unite="<?= $produit['umProduit'] ?>"
                                                    data-type="<?= $typeText ?>"
                                                    data-niveau="<?= $produit['niveau_stock'] ?>">
                                                <?= htmlspecialchars($produit['designation']) ?> 
                                                (<?= number_format($produit['stock_disponible'], 3) ?> <?= $uniteText ?>)
                                                - <?= number_format($produit['prix'], 2) ?> $
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="stock-info" class="mt-2 text-sm hidden">
                                        <div class="flex items-center space-x-2">
                                            <span id="stock-quantite" class="font-medium"></span>
                                            <span id="unite-badge" class="unite-badge"></span>
                                            <span id="type-badge" class="type-badge ml-1"></span>
                                            <span id="stock-niveau" class="stock-badge"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Prix unitaire -->
                                <div>
                                    <label for="prix_unitaire" class="block text-sm font-medium text-gray-700 mb-2">
                                        Prix unitaire ($)
                                    </label>
                                    <div class="input-with-unite">
                                        <input type="number" id="prix_unitaire" name="prix_unitaire" step="0.01" min="0.01"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                               placeholder="Prix unitaire"
                                               <?= empty($produits_disponibles) ? 'disabled' : '' ?>>
                                        <span id="prix-unite-label" class="unite-label">$ / unité</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Laissez vide pour utiliser le prix par défaut du stock</p>
                                </div>
                                
                                <!-- Quantité -->
                                <div>
                                    <label for="quantite" class="block text-sm font-medium text-gray-700 mb-2">
                                        Quantité
                                    </label>
                                    <div class="input-with-unite">
                                        <input type="number" id="quantite" name="quantite" required step="0.001" min="0.001"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                               placeholder="Quantité"
                                               <?= empty($produits_disponibles) ? 'disabled' : '' ?>>
                                        <span id="quantite-unite-label" class="unite-label">unités</span>
                                    </div>
                                    <span id="step-info" class="step-info">Incrément: 0.001</span>
                                    <div class="flex justify-between mt-1">
                                        <span class="text-xs text-gray-500">Max: <span id="max-quantite">0</span> <span id="max-unite">unités</span></span>
                                        <button type="button" onclick="setMaxQuantite()" 
                                                class="text-xs text-blue-600 hover:text-blue-800"
                                                <?= empty($produits_disponibles) ? 'disabled' : '' ?>>
                                            Utiliser le maximum disponible
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Bouton d'ajout -->
                                <div class="pt-2">
                                    <button type="submit" name="ajouter_produit" 
                                            class="w-full py-3 gradient-green-btn text-white rounded-lg hover:opacity-90 shadow-md transition-all"
                                            <?= empty($produits_disponibles) ? 'disabled' : '' ?>>
                                        <i class="fas fa-plus mr-2"></i>
                                        <?= empty($produits_disponibles) ? 'Aucun produit disponible' : 'Ajouter à la commande' ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Statistiques rapides -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Stock disponible</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="text-2xl font-bold text-gray-900">
                                        <?= count($produits_disponibles) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Produits disponibles</div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <div class="text-2xl font-bold text-gray-900">
                                        <?php 
                                            $total_stock_disponible = 0;
                                            foreach ($produits_disponibles as $produit) {
                                                $total_stock_disponible += $produit['stock_disponible'];
                                            }
                                            echo number_format($total_stock_disponible, 3);
                                        ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Unités disponibles</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TABLEAU DES PRODUITS DE LA COMMANDE (DROITE) -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 animate-fade-in lg:col-span-2">
                        <!-- En-tête de facture (visible uniquement à l'impression) -->
                        <div class="facture-header hidden print:block">
                            <h1 class="text-3xl font-bold">FACTURE N°<?= htmlspecialchars($commande['numero_facture']) ?></h1>
                            <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                            <p><strong>Boutique:</strong> <?= htmlspecialchars($commande['boutique_nom']) ?></p>
                            <p><strong>Client:</strong> <?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : 'Non renseigné' ?></p>
                        </div>
                        
                        <!-- Informations facture pour impression -->
                        <div class="infos-facture hidden print:grid">
                            <div>
                                <h4 class="font-bold mb-2">ÉMETTEUR</h4>
                                <p><?= htmlspecialchars($commande['boutique_nom']) ?></p>
                                <p>Commande #<?= $commande_id ?></p>
                                <p>Facture: <?= htmlspecialchars($commande['numero_facture']) ?></p>
                            </div>
                            <div>
                                <h4 class="font-bold mb-2">CLIENT</h4>
                                <p><?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : 'Non renseigné' ?></p>
                                <p>Date: <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                                <p>État: <?= $commande['etat'] == 'payee' ? 'Payée' : 'Brouillon' ?></p>
                            </div>
                        </div>

                        <div class="flex justify-between items-center mb-4 print:hidden">
                            <h3 class="text-lg font-bold text-gray-900">Produits de la commande</h3>
                            <div class="text-sm text-gray-600">
                                <?= count($produits_commande) ?> produit(s)
                            </div>
                        </div>
                        
                        <?php if (!empty($produits_commande)): ?>
                            <div class="table-container">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Référence</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider print:hidden">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php 
                                        $index = 1;
                                        foreach ($produits_commande as $produit): 
                                            $uniteText = $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                            $isRideau = substr($produit['matricule'], 0, 3) === 'Rid';
                                            $typeText = $isRideau ? 'Rideau' : 'Produit';
                                            $uniteClass = $produit['umProduit'] == 'metres' ? 'unite-metres' : 'unite-pieces';
                                            $typeClass = $isRideau ? 'type-rideau' : 'type-produit';
                                        ?>
                                            <tr class="produit-row hover:bg-gray-50 transition-colors">
                                                <td class="px-4 py-4 text-sm text-gray-900"><?= $index++ ?></td>
                                                <td class="px-4 py-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($produit['designation']) ?>
                                                    </div>
                                                    <div class="flex items-center space-x-2 mt-1">
                                                        <span class="unite-badge <?= $uniteClass ?> text-xs">
                                                            <i class="fas fa-<?= $produit['umProduit'] == 'metres' ? 'ruler-combined' : 'cube' ?> mr-1"></i>
                                                            <?= $uniteText ?>
                                                        </span>
                                                        <span class="type-badge <?= $typeClass ?> text-xs">
                                                            <i class="fas fa-<?= $isRideau ? 'window-maximize' : 'box' ?> mr-1"></i>
                                                            <?= $typeText ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-gray-900">
                                                    <?= htmlspecialchars($produit['matricule'] ?? 'N/A') ?>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center space-x-2">
                                                        <span class="text-sm text-gray-900 font-medium">
                                                            <?= number_format($produit['quantite'], 3) ?>
                                                        </span>
                                                        <span class="text-xs text-gray-500">
                                                            <?= $uniteText ?>
                                                        </span>
                                                        <button onclick="openModifierQuantiteModal(<?= $produit['commande_produit_id'] ?>, <?= $produit['quantite'] ?>, '<?= $produit['umProduit'] ?>')"
                                                                class="text-blue-600 hover:text-blue-800 text-sm print:hidden">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <div class="flex items-center space-x-2">
                                                        <span class="text-sm text-gray-900 font-medium">
                                                            <?= number_format($produit['prix_unitaire'], 2) ?> $
                                                        </span>
                                                        <span class="text-xs text-gray-500">
                                                            /<?= $uniteText ?>
                                                        </span>
                                                        <button onclick="openModifierPrixModal(<?= $produit['commande_produit_id'] ?>, <?= $produit['prix_unitaire'] ?>)"
                                                                class="text-blue-600 hover:text-blue-800 text-sm print:hidden">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-4 text-sm text-gray-900 font-bold">
                                                    <?= number_format($produit['total_ligne'], 2) ?> $
                                                </td>
                                                <td class="px-4 py-4 print:hidden">
                                                    <div class="flex space-x-2">
                                                        <button onclick="openRetirerProduitModal(
                                                            <?= $produit['commande_produit_id'] ?>, 
                                                            '<?= htmlspecialchars(addslashes($produit['designation'])) ?>',
                                                            '<?= number_format($produit['quantite'], 3) ?>',
                                                            '<?= $uniteText ?>',
                                                            '<?= number_format($produit['prix_unitaire'], 2) ?> $'
                                                        )"
                                                                class="px-3 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-sm transition-colors action-btn">
                                                            <i class="fas fa-trash-alt mr-1"></i>Retirer
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Résumé financier -->
                            <div class="mt-6 pt-6 border-t border-gray-200">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div class="bg-blue-50 p-4 rounded-lg">
                                        <div class="text-sm text-blue-700 font-medium mb-1">Total de la commande</div>
                                        <div class="text-2xl font-bold text-blue-900"><?= number_format($total_commande, 2) ?> $</div>
                                    </div>
                                    <div class="bg-green-50 p-4 rounded-lg">
                                        <div class="text-sm text-green-700 font-medium mb-1">Total payé</div>
                                        <div class="text-2xl font-bold text-green-900"><?= number_format($total_paye, 2) ?> $</div>
                                    </div>
                                    <div class="bg-<?= $reste_a_payer > 0 ? 'yellow' : 'green' ?>-50 p-4 rounded-lg">
                                        <div class="text-sm text-<?= $reste_a_payer > 0 ? 'yellow' : 'green' ?>-700 font-medium mb-1">
                                            <?= $reste_a_payer > 0 ? 'Reste à payer' : 'Total payé' ?>
                                        </div>
                                        <div class="text-2xl font-bold text-<?= $reste_a_payer > 0 ? 'yellow' : 'green' ?>-900">
                                            <?= number_format($reste_a_payer, 2) ?> $
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Boutons d'action -->
                                <div class="flex justify-between items-center">
                                    <div class="text-sm text-gray-500 print:hidden">
                                        <?= count($produits_commande) ?> produit(s) - TVA non applicable
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-gray-900 total-impression">
                                            TOTAL: <span class="text-2xl text-blue-700"><?= number_format($total_commande, 2) ?> $</span>
                                        </div>
                                        <div class="text-sm text-gray-500 mt-1 print:hidden">
                                            HT: <?= number_format($total_commande, 2) ?> $
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- NOUVEAU : Boutons pour paiement et impression -->
                                <div class="mt-6 pt-6 border-t border-gray-200 flex justify-between print:hidden">
                                    <div>
                                        <?php if ($commande['etat'] == 'brouillon' && $reste_a_payer > 0): ?>
                                        <button onclick="openPaiementModal()"
                                                class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:opacity-90 shadow-md transition-all">
                                            <i class="fas fa-money-bill-wave mr-2"></i>Enregistrer paiement CASH
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <button onclick="imprimerFacture()" 
                                                class="px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:opacity-90 shadow-md transition-all">
                                            <i class="fas fa-print mr-2"></i>Imprimer la facture
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Section des paiements -->
                            <?php if (!empty($paiements)): ?>
                                <div class="mt-6 pt-6 border-t border-gray-200 print:hidden">
                                    <h4 class="text-lg font-bold text-gray-900 mb-4">Paiements enregistrés</h4>
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Méthode</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($paiements as $paiement): ?>
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="px-4 py-4 text-sm text-gray-900">
                                                            <?= date('d/m/Y', strtotime($paiement['date'])) ?>
                                                        </td>
                                                        <td class="px-4 py-4 text-sm text-green-700 font-bold">
                                                            <?= number_format($paiement['montant'], 2) ?> $
                                                        </td>
                                                        <td class="px-4 py-4 text-sm">
                                                            <?php if (isset($paiement['methode_paiement'])): ?>
                                                                <span class="font-medium <?= $paiement['methode_paiement'] == 'cash' ? 'text-green-600' : 'text-blue-600' ?>">
                                                                    <?= strtoupper($paiement['methode_paiement']) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-gray-500">Non spécifié</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-4 py-4">
                                                            <form method="POST" action="" 
                                                                  onsubmit="return confirm('Annuler ce paiement ?');"
                                                                  class="inline">
                                                                <input type="hidden" name="paiement_id" value="<?= $paiement['id'] ?>">
                                                                <button type="submit" name="annuler_paiement" 
                                                                        class="px-3 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-sm transition-colors">
                                                                    <i class="fas fa-times mr-1"></i>Annuler
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Paiements pour l'impression -->
                            <div class="paiements-print hidden print:block">
                                <h4>Paiements</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>Méthode</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paiements as $paiement): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($paiement['date'])) ?></td>
                                                <td><?= number_format($paiement['montant'], 2) ?> $</td>
                                                <td><?= isset($paiement['methode_paiement']) ? strtoupper($paiement['methode_paiement']) : 'Non spécifié' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!empty($paiements)): ?>
                                            <tr>
                                                <td><strong>Total payé:</strong></td>
                                                <td colspan="2"><strong><?= number_format($total_paye, 2) ?> $</strong></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Reste à payer:</strong></td>
                                                <td colspan="2"><strong><?= number_format($reste_a_payer, 2) ?> $</strong></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="text-gray-400 mb-4">
                                    <i class="fas fa-shopping-cart text-5xl"></i>
                                </div>
                                <h4 class="text-gray-600 font-medium">Aucun produit dans cette commande</h4>
                                <p class="text-gray-500 text-sm mt-1">
                                    <?php if (empty($produits_disponibles)): ?>
                                        Tous les produits sont déjà commandés ou épuisés.
                                    <?php else: ?>
                                        Ajoutez des produits à partir du formulaire à gauche.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Informations de la commande (simplifiées) -->
                <div class="bg-white rounded-2xl shadow-soft p-6 animate-fade-in mt-6 print:hidden">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Informations de la commande</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Informations boutique</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Boutique:</span>
                                    <span class="text-sm font-medium"><?= htmlspecialchars($commande['boutique_nom']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">N° Commande:</span>
                                    <span class="text-sm font-medium"><?= $commande_id ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Facture:</span>
                                    <span class="text-sm font-medium"><?= htmlspecialchars($commande['numero_facture']) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Client</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Nom:</span>
                                    <span class="text-sm font-medium">
                                        <?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : 'Non renseigné' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Statut & Dates</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">État:</span>
                                    <span class="text-sm font-medium">
                                        <?php if ($commande['etat'] == 'payee'): ?>
                                            <span class="status-badge status-payee">Payée</span>
                                        <?php else: ?>
                                            <span class="status-badge status-brouillon">Brouillon</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Date création:</span>
                                    <span class="text-sm font-medium"><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Actions rapides -->
                <div class="mt-6 flex justify-between items-center print:hidden">
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Commande <?= $commande['etat'] == 'brouillon' ? 'en cours de modification' : 'finalisée' ?>
                    </div>
                    <div class="flex space-x-3">
                        <a href="ventes_boutique.php" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Retour aux ventes
                        </a>
                        
                        <?php if ($commande['etat'] == 'brouillon'): ?>
                            <form method="POST" action="" onsubmit="return confirm('Finaliser cette commande ?');" class="inline">
                                <button type="submit" name="finaliser_commande" 
                                        class="px-4 py-2 gradient-green-btn text-white rounded-lg hover:opacity-90 shadow-md">
                                    <i class="fas fa-check mr-2"></i>Marquer comme payée
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal de confirmation d'annulation -->
    <div id="annulationModal" class="modal">
        <div class="modal-content slide-down">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Annuler la commande</h3>
                <p class="text-gray-600 mb-6">
                    Êtes-vous sûr de vouloir annuler cette commande ? 
                    <br><br>
                    <span class="font-medium text-red-600">
                        Cette action est irréversible. Les produits seront de nouveau disponibles pour d'autres commandes.
                    </span>
                </p>
                
                <div class="flex justify-end space-x-3">
                    <button onclick="closeAnnulationModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <form method="POST" action="" class="inline">
                        <button type="submit" name="annuler_commande" 
                                class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 shadow-md">
                            <i class="fas fa-times mr-2"></i>Oui, annuler la commande
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de modification de quantité -->
    <div id="modifierQuantiteModal" class="modal">
        <div class="modal-content slide-down">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Modifier la quantité</h3>
                <form method="POST" action="" id="formModifierQuantite">
                    <input type="hidden" id="modifier_quantite_id" name="commande_produit_id">
                    <input type="hidden" id="modifier_quantite_unite" name="quantite_unite">
                    
                    <div class="mb-6">
                        <label for="nouvelle_quantite" class="block text-sm font-medium text-gray-700 mb-2">
                            Nouvelle quantité (<span id="unite-label">unités</span>)
                        </label>
                        <div class="input-with-unite">
                            <input type="number" id="nouvelle_quantite" name="nouvelle_quantite" required step="0.001" min="0.001"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <span id="modal-quantite-unite-label" class="unite-label">unités</span>
                        </div>
                        <span id="modal-step-info" class="step-info">Incrément: 0.001</span>
                        <p class="text-xs text-gray-500 mt-1">Quantité actuelle: <span id="quantite_actuelle"></span> <span id="quantite_unite"></span></p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModifierQuantiteModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="modifier_quantite" 
                                class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 shadow-md">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de modification de prix -->
    <div id="modifierPrixModal" class="modal">
        <div class="modal-content slide-down">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Modifier le prix unitaire</h3>
                <form method="POST" action="" id="formModifierPrix">
                    <input type="hidden" id="modifier_prix_id" name="commande_produit_id">
                    
                    <div class="mb-6">
                        <label for="nouveau_prix" class="block text-sm font-medium text-gray-700 mb-2">
                            Nouveau prix unitaire ($)
                        </label>
                        <input type="number" id="nouveau_prix" name="nouveau_prix" required step="0.01" min="0"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <p class="text-xs text-gray-500 mt-1">Prix actuel: <span id="prix_actuel"></span> $</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModifierPrixModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="modifier_prix" 
                                class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 shadow-md">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmation pour retirer un produit -->
    <div id="retirerProduitModal" class="modal">
        <div class="modal-content slide-down">
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-trash-alt text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Retirer le produit</h3>
                    <p class="text-gray-600" id="modal-produit-nom"></p>
                    <p class="text-gray-500 text-sm mt-2" id="modal-produit-details"></p>
                </div>
                
                <form method="POST" action="" id="formRetirerProduit">
                    <input type="hidden" id="modal_commande_produit_id" name="commande_produit_id">
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <i class="fas fa-exclamation-triangle text-yellow-600 mr-3 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-yellow-700 font-medium">Êtes-vous sûr de vouloir retirer ce produit de la commande ?</p>
                                <p class="text-xs text-yellow-600 mt-1">Cette action supprimera la ligne du produit de la facture.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRetirerProduitModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="retirer_produit" 
                                class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 shadow-md transition-all flex items-center">
                            <i class="fas fa-trash-alt mr-2"></i>Oui, retirer le produit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal pour enregistrer un paiement CASH -->
    <div id="paiementModal" class="modal">
        <div class="modal-content slide-down">
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-money-bill-wave text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Paiement CASH</h3>
                    <p class="text-gray-600">Enregistrer un paiement en espèces pour le montant total de la commande</p>
                </div>
                
                <!-- Résumé du paiement -->
                <div class="bg-gray-50 rounded-xl p-5 mb-6">
                    <div class="text-center">
                        <div class="text-sm text-gray-600 font-medium mb-1">Montant à payer</div>
                        <div class="text-3xl font-bold text-gray-900">
                            <?= number_format($reste_a_payer, 2) ?> $
                        </div>
                    </div>
                    
                    <?php if ($total_paye > 0): ?>
                    <div class="mt-4 pt-4 border-t border-gray-200 text-center">
                        <div class="text-sm text-gray-600">
                            Déjà payé : <span class="font-bold text-green-600"><?= number_format($total_paye, 2) ?> $</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closePaiementModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <form method="POST" action="" id="formPaiementCash">
                        <button type="submit" name="enregistrer_paiement_cash" 
                                class="px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg hover:opacity-90 shadow-md transition-all flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>Confirmer le paiement
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Fonction pour mettre à jour les informations d'unité
        function updateUniteInfo() {
            const select = document.getElementById('stock_id');
            const selectedOption = select.options[select.selectedIndex];
            const stockInfo = document.getElementById('stock-info');
            const maxQuantiteSpan = document.getElementById('max-quantite');
            const maxUniteSpan = document.getElementById('max-unite');
            const prixUniteLabel = document.getElementById('prix-unite-label');
            const quantiteUniteLabel = document.getElementById('quantite-unite-label');
            const stepInfo = document.getElementById('step-info');
            const quantiteInput = document.getElementById('quantite');
            const prixInput = document.getElementById('prix_unitaire');
            
            if (select.value) {
                const stockDisponible = parseFloat(selectedOption.getAttribute('data-stock'));
                const niveauStock = selectedOption.getAttribute('data-niveau');
                const prixDefaut = parseFloat(selectedOption.getAttribute('data-prix'));
                const unite = selectedOption.getAttribute('data-unite');
                const type = selectedOption.getAttribute('data-type');
                
                // Mettre à jour les informations de stock
                document.getElementById('stock-quantite').textContent = 
                    `Stock disponible: ${stockDisponible.toFixed(3)}`;
                
                // Mettre à jour les badges d'unité et de type
                const uniteBadge = document.getElementById('unite-badge');
                const uniteText = unite === 'metres' ? 'mètres' : 'pièces';
                const uniteIcon = unite === 'metres' ? 'fa-ruler-combined' : 'fa-cube';
                uniteBadge.innerHTML = `<i class="fas ${uniteIcon} mr-1"></i>${uniteText}`;
                uniteBadge.className = `unite-badge ${unite === 'metres' ? 'unite-metres' : 'unite-pieces'}`;
                
                const typeBadge = document.getElementById('type-badge');
                const typeIcon = type === 'Rideau' ? 'fa-window-maximize' : 'fa-box';
                typeBadge.innerHTML = `<i class="fas ${typeIcon} mr-1"></i>${type}`;
                typeBadge.className = `type-badge ${type === 'Rideau' ? 'type-rideau' : 'type-produit'}`;
                
                const niveauBadge = document.getElementById('stock-niveau');
                niveauBadge.textContent = niveauStock === 'faible' ? 'Stock faible' : 'Stock OK';
                niveauBadge.className = 'stock-badge ' + (niveauStock === 'faible' ? 'stock-faible' : 'stock-ok');
                
                stockInfo.classList.remove('hidden');
                maxQuantiteSpan.textContent = stockDisponible.toFixed(3);
                maxUniteSpan.textContent = uniteText;
                
                // Mettre à jour les labels d'unité
                prixUniteLabel.textContent = `$ / ${uniteText}`;
                quantiteUniteLabel.textContent = uniteText;
                
                // IMPORTANT: Mettre à jour l'incrément et le pattern selon l'unité
                if (unite === 'pieces') {
                    // Pour les pièces: entier seulement
                    quantiteInput.step = 1;
                    quantiteInput.min = 1;
                    quantiteInput.pattern = "[0-9]*";
                    quantiteInput.inputMode = "numeric";
                    stepInfo.textContent = "Incrément: 1 (nombre entier)";
                    // Réinitialiser la valeur pour s'assurer qu'elle est entière
                    if (quantiteInput.value) {
                        quantiteInput.value = Math.floor(parseFloat(quantiteInput.value));
                    }
                } else {
                    // Pour les mètres: décimal avec 3 décimales
                    quantiteInput.step = 0.001;
                    quantiteInput.min = 0.001;
                    quantiteInput.pattern = "[0-9]*\\.?[0-9]*";
                    quantiteInput.inputMode = "decimal";
                    stepInfo.textContent = "Incrément: 0.001";
                }
                
                // Mettre le prix par défaut si le champ est vide
                if (!prixInput.value) {
                    prixInput.value = prixDefaut.toFixed(2);
                }
                
                // Limiter la quantité maximale
                quantiteInput.max = stockDisponible;
                quantiteInput.value = ''; // Réinitialiser la quantité
                
                // Forcer la validation pour les pièces
                if (unite === 'pieces') {
                    quantiteInput.oninput = function() {
                        // Valider que c'est un entier
                        let value = this.value;
                        if (value && !Number.isInteger(parseFloat(value))) {
                            this.value = Math.floor(value);
                        }
                    };
                } else {
                    quantiteInput.oninput = null;
                }
            } else {
                stockInfo.classList.add('hidden');
                maxQuantiteSpan.textContent = '0';
                maxUniteSpan.textContent = 'unités';
                prixUniteLabel.textContent = '$ / unité';
                quantiteUniteLabel.textContent = 'unités';
                stepInfo.textContent = 'Incrément: 0.001';
                // Réinitialiser les contraintes
                quantiteInput.step = 0.001;
                quantiteInput.min = 0.001;
                quantiteInput.pattern = null;
                quantiteInput.inputMode = "text";
                quantiteInput.oninput = null;
            }
        }
        
        // Fonction d'impression améliorée
        function imprimerFacture() {
            const originalTitle = document.title;
            document.title = "Facture_" + <?= $commande_id ?> + "_" + new Date().toISOString().slice(0, 10);
            
            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    document.title = originalTitle;
                }, 1000);
            }, 100);
        }
        
        // Gérer l'événement afterprint pour restaurer l'interface
        window.onafterprint = function() {
            console.log("Impression terminée");
        };
        
        // Fonctions pour les modals
        function openAnnulationModal() {
            document.getElementById('annulationModal').classList.add('show');
        }
        
        function closeAnnulationModal() {
            document.getElementById('annulationModal').classList.remove('show');
        }
        
        function openModifierQuantiteModal(commandeProduitId, quantiteActuelle, unite) {
            document.getElementById('modifier_quantite_id').value = commandeProduitId;
            document.getElementById('modifier_quantite_unite').value = unite;
            document.getElementById('nouvelle_quantite').value = quantiteActuelle;
            document.getElementById('quantite_actuelle').textContent = quantiteActuelle;
            
            // Mettre à jour les labels d'unité
            const uniteText = unite === 'metres' ? 'mètres' : 'pièces';
            document.getElementById('unite-label').textContent = uniteText;
            document.getElementById('quantite_unite').textContent = uniteText;
            document.getElementById('modal-quantite-unite-label').textContent = uniteText;
            
            // Mettre à jour l'incrément
            const step = unite === 'pieces' ? 1 : 0.001;
            document.getElementById('nouvelle_quantite').step = step;
            document.getElementById('nouvelle_quantite').min = step;
            document.getElementById('modal-step-info').textContent = `Incrément: ${step}`;
            
            // Ajouter la validation pour les pièces
            if (unite === 'pieces') {
                document.getElementById('nouvelle_quantite').oninput = function() {
                    let value = this.value;
                    if (value && !Number.isInteger(parseFloat(value))) {
                        this.value = Math.floor(value);
                    }
                };
            } else {
                document.getElementById('nouvelle_quantite').oninput = null;
            }
            
            document.getElementById('modifierQuantiteModal').classList.add('show');
            // Focus sur le champ de quantité
            setTimeout(() => {
                document.getElementById('nouvelle_quantite').focus();
                document.getElementById('nouvelle_quantite').select();
            }, 100);
        }
        
        function closeModifierQuantiteModal() {
            document.getElementById('modifierQuantiteModal').classList.remove('show');
        }
        
        function openModifierPrixModal(commandeProduitId, prixActuel) {
            document.getElementById('modifier_prix_id').value = commandeProduitId;
            document.getElementById('nouveau_prix').value = prixActuel;
            document.getElementById('prix_actuel').textContent = prixActuel;
            document.getElementById('modifierPrixModal').classList.add('show');
            // Focus sur le champ de prix
            setTimeout(() => {
                document.getElementById('nouveau_prix').focus();
                document.getElementById('nouveau_prix').select();
            }, 100);
        }
        
        function closeModifierPrixModal() {
            document.getElementById('modifierPrixModal').classList.remove('show');
        }
        
        // Fonctions pour le modal de retrait de produit
        function openRetirerProduitModal(commandeProduitId, designation, quantite, unite, prix) {
            document.getElementById('modal_commande_produit_id').value = commandeProduitId;
            document.getElementById('modal-produit-nom').textContent = `« ${designation} »`;
            document.getElementById('modal-produit-details').textContent = 
                `Quantité: ${quantite} ${unite} | Prix: ${prix}`;
            
            document.getElementById('retirerProduitModal').classList.add('show');
        }
        
        function closeRetirerProduitModal() {
            document.getElementById('retirerProduitModal').classList.remove('show');
        }
        
        // Fonctions pour le modal de paiement CASH
        function openPaiementModal() {
            // Vérifier s'il y a des produits dans la commande
            if (<?= count($produits_commande) ?> === 0) {
                alert("Impossible d'enregistrer un paiement : la commande ne contient aucun produit.");
                return;
            }
            
            // Vérifier si la commande est déjà payée
            if (<?= $commande['etat'] == 'payee' ? 'true' : 'false' ?>) {
                alert("Cette commande a déjà été payée.");
                return;
            }
            
            // Vérifier s'il reste un montant à payer
            if (<?= $reste_a_payer ?> <= 0) {
                alert("Le montant total de la commande a déjà été payé.");
                return;
            }
            
            document.getElementById('paiementModal').classList.add('show');
        }
        
        function closePaiementModal() {
            document.getElementById('paiementModal').classList.remove('show');
        }
        
        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }
        
        // Mise à jour des informations de stock lors de la sélection d'un produit
        document.getElementById('stock_id').addEventListener('change', updateUniteInfo);
        
        // Fonction pour utiliser la quantité maximale disponible
        function setMaxQuantite() {
            const selectedOption = document.getElementById('stock_id').options[document.getElementById('stock_id').selectedIndex];
            if (selectedOption && selectedOption.value) {
                const stockDisponible = parseFloat(selectedOption.getAttribute('data-stock'));
                const unite = selectedOption.getAttribute('data-unite');
                
                if (unite === 'pieces') {
                    // Pour les pièces, prendre l'entier inférieur
                    document.getElementById('quantite').value = Math.floor(stockDisponible);
                } else {
                    document.getElementById('quantite').value = stockDisponible;
                }
            }
        }
        
        // Validation du formulaire d'ajout de produit
        document.getElementById('formAjoutProduit').addEventListener('submit', function(e) {
            const quantiteInput = document.getElementById('quantite');
            const stockDisponible = parseFloat(document.getElementById('max-quantite').textContent);
            const quantiteDemandee = parseFloat(quantiteInput.value);
            const selectedOption = document.getElementById('stock_id').options[document.getElementById('stock_id').selectedIndex];
            const unite = selectedOption ? selectedOption.getAttribute('data-unite') : '';
            
            if (!quantiteInput.value || quantiteDemandee <= 0) {
                e.preventDefault();
                alert('La quantité doit être supérieure à 0');
                quantiteInput.focus();
                quantiteInput.select();
                return;
            }
            
            if (quantiteDemandee > stockDisponible) {
                e.preventDefault();
                const uniteText = unite === 'metres' ? 'mètres' : 'pièces';
                alert(`Quantité demandée (${quantiteDemandee}) supérieure au stock disponible (${stockDisponible} ${uniteText})`);
                quantiteInput.focus();
                quantiteInput.select();
                return;
            }
            
            // Validation spécifique selon l'unité
            if (unite === 'pieces') {
                // Pour les pièces, vérifier que c'est un entier
                if (!Number.isInteger(quantiteDemandee)) {
                    e.preventDefault();
                    alert('Pour les produits à la pièce, la quantité doit être un nombre entier.');
                    quantiteInput.focus();
                    quantiteInput.select();
                    return;
                }
                
                // S'assurer que c'est un entier positif
                if (quantiteDemandee < 1) {
                    e.preventDefault();
                    alert('Pour les produits à la pièce, la quantité doit être au moins 1.');
                    quantiteInput.focus();
                    quantiteInput.select();
                    return;
                }
            }
        });
        
        // Validation du formulaire de modification de quantité
        document.getElementById('formModifierQuantite').addEventListener('submit', function(e) {
            const nouvelleQuantite = parseFloat(document.getElementById('nouvelle_quantite').value);
            const unite = document.getElementById('modifier_quantite_unite').value;
            
            if (!nouvelleQuantite || nouvelleQuantite <= 0) {
                e.preventDefault();
                alert('La quantité doit être supérieure à 0');
                document.getElementById('nouvelle_quantite').focus();
                document.getElementById('nouvelle_quantite').select();
                return;
            }
            
            // Validation spécifique selon l'unité
            if (unite === 'pieces') {
                // Pour les pièces, vérifier que c'est un entier
                if (!Number.isInteger(nouvelleQuantite)) {
                    e.preventDefault();
                    alert('Pour les produits à la pièce, la quantité doit être un nombre entier.');
                    document.getElementById('nouvelle_quantite').focus();
                    document.getElementById('nouvelle_quantite').select();
                    return;
                }
                
                // S'assurer que c'est un entier positif
                if (nouvelleQuantite < 1) {
                    e.preventDefault();
                    alert('Pour les produits à la pièce, la quantité doit être au moins 1.');
                    document.getElementById('nouvelle_quantite').focus();
                    document.getElementById('nouvelle_quantite').select();
                    return;
                }
            }
        });
        
        // Validation du formulaire de modification de prix
        document.getElementById('formModifierPrix').addEventListener('submit', function(e) {
            const nouveauPrix = parseFloat(document.getElementById('nouveau_prix').value);
            
            if (nouveauPrix < 0) {
                e.preventDefault();
                alert('Le prix ne peut pas être négatif');
                document.getElementById('nouveau_prix').focus();
                document.getElementById('nouveau_prix').select();
                return;
            }
        });
        
        // Auto-focus sur le premier champ du formulaire d'ajout
        document.addEventListener('DOMContentLoaded', function() {
            // Si des produits sont disponibles, initialiser l'affichage du stock
            if (document.querySelector('#stock_id option[value]')) {
                const select = document.getElementById('stock_id');
                if (select.value) {
                    select.dispatchEvent(new Event('change'));
                }
            }
            
            // Auto-focus sur le sélecteur de produit
            if (document.getElementById('stock_id') && document.getElementById('stock_id').options.length > 1) {
                setTimeout(() => {
                    document.getElementById('stock_id').focus();
                }, 500);
            }
        });
        
        // Confirmation avant d'annuler la commande
        document.querySelector('form[action*="annuler_commande"]')?.addEventListener('submit', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir annuler cette commande ? Tous les produits seront de nouveau disponibles pour d\'autres commandes.')) {
                e.preventDefault();
            }
        });
        
        // Touche Échap pour fermer les modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAnnulationModal();
                closeModifierQuantiteModal();
                closeModifierPrixModal();
                closeRetirerProduitModal();
                closePaiementModal();
            }
        });
        
        // Raccourci clavier pour enregistrer un paiement: Ctrl+P
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p' && !e.shiftKey) {
                e.preventDefault();
                if (<?= $reste_a_payer ?> > 0) {
                    openPaiementModal();
                }
            }
        });
    </script>
</body>
</html>