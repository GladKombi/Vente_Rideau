<?php
// paiements.php
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

// Initialisation des variables
$message = '';
$message_type = '';
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Début du mois par défaut
$date_fin = $_GET['date_fin'] ?? date('Y-m-t'); // Fin du mois par défaut
$commande_id_filter = $_GET['commande_id'] ?? '';
$statut_filter = $_GET['statut'] ?? '';

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// --- TRAITEMENT DES FORMULAIRES ---

// Ajouter un paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_paiement'])) {
    try {
        // Date automatique : aujourd'hui
        $date_paiement = date('Y-m-d');
        $commande_id = (int)$_POST['commande_id'];
        $montant = (float)$_POST['montant'];
        
        // Vérifier que la commande appartient à la boutique et n'est pas cash
        $query = $pdo->prepare("
            SELECT c.id, c.etat,
                   (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) 
                    FROM commande_produits cp 
                    WHERE cp.commande_id = c.id AND cp.statut = 0) as total_commande
            FROM commandes c
            WHERE c.id = ? AND c.boutique_id = ? AND c.statut = 0 AND c.etat != 'payee'
        ");
        $query->execute([$commande_id, $boutique_id]);
        $commande = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande) {
            $_SESSION['flash_message'] = [
                'text' => "Commande non trouvée, déjà payée ou vous n'avez pas les droits d'accès.",
                'type' => "error"
            ];
            header("Location: paiements.php");
            exit;
        }
        
        // Calculer le total déjà payé pour cette commande
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0) as total_paye
            FROM paiements 
            WHERE commandes_id = ? AND statut = 0
        ");
        $query->execute([$commande_id]);
        $total_paye = $query->fetchColumn();
        
        // Vérifier que le montant est positif
        if ($montant <= 0) {
            $_SESSION['flash_message'] = [
                'text' => "Le montant doit être supérieur à 0.",
                'type' => "error"
            ];
            header("Location: paiements.php");
            exit;
        }
        
        // Calculer le montant restant à payer
        $total_commande = (float)$commande['total_commande'];
        $montant_restant = $total_commande - $total_paye;
        
        // Vérifier si le paiement ne dépasse pas le montant restant
        if ($montant > $montant_restant) {
            $_SESSION['flash_message'] = [
                'text' => "Le montant du paiement (" . number_format($montant, 3) . " $) dépasse le montant restant à payer (" . number_format($montant_restant, 3) . " $).",
                'type' => "error"
            ];
            header("Location: paiements.php");
            exit;
        }
        
        // Insérer le paiement
        $query = $pdo->prepare("
            INSERT INTO paiements (date, commandes_id, montant, statut)
            VALUES (?, ?, ?, 0)
        ");
        $query->execute([$date_paiement, $commande_id, $montant]);
        
        // Récupérer l'ID du paiement inséré
        $paiement_id = $pdo->lastInsertId();
        
        // Vérifier si la commande est maintenant entièrement payée
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0) as nouveau_total_paye
            FROM paiements 
            WHERE commandes_id = ? AND statut = 0
        ");
        $query->execute([$commande_id]);
        $nouveau_total_paye = $query->fetchColumn();
        
        if ($nouveau_total_paye >= $total_commande) {
            // Marquer la commande comme payée
            $query = $pdo->prepare("
                UPDATE commandes 
                SET etat = 'payee'
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            $query->execute([$commande_id, $boutique_id]);
        }
        
        // Stocker un message flash pour la page du reçu
        $_SESSION['recu_message'] = [
            'text' => "Paiement de " . number_format($montant, 3) . " $ ajouté avec succès pour la commande #{$commande_id} !",
            'type' => "success"
        ];
        
        // Rediriger vers le reçu avec impression automatique
        header("Location: imprimer_recu.php?id={$paiement_id}&auto_print=1");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de l'ajout du paiement: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: paiements.php");
        exit;
    }
}

// Modifier un paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_paiement'])) {
    try {
        $paiement_id = (int)$_POST['paiement_id'];
        $montant = (float)$_POST['montant'];
        
        // Vérifier que le paiement appartient à une commande de la boutique
        $query = $pdo->prepare("
            SELECT p.id, p.commandes_id, p.montant as ancien_montant,
                   (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) 
                    FROM commande_produits cp 
                    WHERE cp.commande_id = p.commandes_id AND cp.statut = 0) as total_commande
            FROM paiements p
            JOIN commandes c ON p.commandes_id = c.id
            WHERE p.id = ? AND c.boutique_id = ? AND p.statut = 0
        ");
        $query->execute([$paiement_id, $boutique_id]);
        $paiement_info = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$paiement_info) {
            $_SESSION['flash_message'] = [
                'text' => "Paiement non trouvé ou vous n'avez pas les droits d'accès.",
                'type' => "error"
            ];
            header("Location: paiements.php");
            exit;
        }
        
        // Calculer le total déjà payé pour cette commande (sans l'ancien montant)
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0) - ? as total_paye_sans_ancien
            FROM paiements 
            WHERE commandes_id = ? AND statut = 0
        ");
        $query->execute([$paiement_info['ancien_montant'], $paiement_info['commandes_id']]);
        $total_paye_sans_ancien = $query->fetchColumn();
        
        // Vérifier que le montant est positif
        if ($montant <= 0) {
            $_SESSION['flash_message'] = [
                'text' => "Le montant doit être supérieur à 0.",
                'type' => "error"
            ];
            header("Location: paiements.php");
            exit;
        }
        
        // Calculer le montant restant à payer après modification
        $total_commande = (float)$paiement_info['total_commande'];
        $montant_restant = $total_commande - $total_paye_sans_ancien;
        
        // Vérifier si le nouveau montant ne dépasse pas le montant restant
        if ($montant > $montant_restant) {
            $_SESSION['flash_message'] = [
                'text' => "Le nouveau montant (" . number_format($montant, 3) . " $) dépasse le montant restant à payer (" . number_format($montant_restant, 3) . " $).",
                'type' => "error"
            ];
            header("Location: paiements.php");
            exit;
        }
        
        // Mettre à jour le paiement
        $query = $pdo->prepare("
            UPDATE paiements 
            SET montant = ?
            WHERE id = ? AND statut = 0
        ");
        $query->execute([$montant, $paiement_id]);
        
        // Vérifier le statut de la commande après modification
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0) as nouveau_total_paye
            FROM paiements 
            WHERE commandes_id = ? AND statut = 0
        ");
        $query->execute([$paiement_info['commandes_id']]);
        $nouveau_total_paye = $query->fetchColumn();
        
        // Mettre à jour le statut de la commande
        if ($nouveau_total_paye >= $total_commande) {
            $query = $pdo->prepare("
                UPDATE commandes 
                SET etat = 'payee'
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            $query->execute([$paiement_info['commandes_id'], $boutique_id]);
        } else {
            $query = $pdo->prepare("
                UPDATE commandes 
                SET etat = 'brouillon'
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            $query->execute([$paiement_info['commandes_id'], $boutique_id]);
        }
        
        $_SESSION['flash_message'] = [
            'text' => "Paiement #{$paiement_id} modifié avec succès !",
            'type' => "success"
        ];
        
        header("Location: paiements.php");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de la modification du paiement: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: paiements.php");
        exit;
    }
}

// Supprimer un paiement (soft delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_paiement'])) {
    try {
        $paiement_id = (int)$_POST['paiement_id'];
        
        // Vérifier que le paiement appartient à une commande de la boutique
        $query = $pdo->prepare("
            SELECT p.id, p.commandes_id, p.montant as ancien_montant,
                   (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) 
                    FROM commande_produits cp 
                    WHERE cp.commande_id = p.commandes_id AND cp.statut = 0) as total_commande
            FROM paiements p
            JOIN commandes c ON p.commandes_id = c.id
            WHERE p.id = ? AND c.boutique_id = ? AND p.statut = 0
        ");
        $query->execute([$paiement_id, $boutique_id]);
        $paiement_info = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$paiement_info) {
            $_SESSION['flash_message'] = [
                'text' => "Paiement non trouvé ou vous n'avez pas les droits d'accès.",
                'type' => "error"
            ];
            header("Location: paiements.php");
            exit;
        }
        
        // Soft delete du paiement
        $query = $pdo->prepare("
            UPDATE paiements 
            SET statut = 1
            WHERE id = ?
        ");
        $query->execute([$paiement_id]);
        
        // Recalculer le statut de la commande après suppression
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0) as total_paye_apres_suppression
            FROM paiements 
            WHERE commandes_id = ? AND statut = 0
        ");
        $query->execute([$paiement_info['commandes_id']]);
        $total_paye_apres_suppression = $query->fetchColumn();
        
        $total_commande = (float)$paiement_info['total_commande'];
        
        if ($total_paye_apres_suppression >= $total_commande) {
            $query = $pdo->prepare("
                UPDATE commandes 
                SET etat = 'payee'
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            $query->execute([$paiement_info['commandes_id'], $boutique_id]);
        } else {
            $query = $pdo->prepare("
                UPDATE commandes 
                SET etat = 'brouillon'
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            $query->execute([$paiement_info['commandes_id'], $boutique_id]);
        }
        
        $_SESSION['flash_message'] = [
            'text' => "Paiement #{$paiement_id} supprimé avec succès !",
            'type' => "success"
        ];
        
        header("Location: paiements.php");
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de la suppression du paiement: " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: paiements.php");
        exit;
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---

// Récupérer les paiements avec filtres et calculs
try {
    // Construction de la requête avec filtres
    $sql = "
        SELECT 
            p.id as paiement_id,
            p.date,
            p.montant,
            p.statut,
            c.id as commande_id,
            c.numero_facture,
            c.client_nom,
            c.date_commande,
            c.etat as commande_etat,
            b.nom as boutique_nom
        FROM paiements p
        JOIN commandes c ON p.commandes_id = c.id
        JOIN boutiques b ON c.boutique_id = b.id
        WHERE c.boutique_id = ?
    ";
    
    $params = [$boutique_id];
    
    // Ajout des filtres
    if (!empty($date_debut)) {
        $sql .= " AND p.date >= ?";
        $params[] = $date_debut;
    }
    
    if (!empty($date_fin)) {
        $sql .= " AND p.date <= ?";
        $params[] = $date_fin;
    }
    
    if (!empty($commande_id_filter)) {
        $sql .= " AND c.id = ?";
        $params[] = $commande_id_filter;
    }
    
    if ($statut_filter !== '') {
        if ($statut_filter !== 'all') {
            $sql .= " AND p.statut = ?";
            $params[] = $statut_filter;
        }
        // Si 'all', on ne filtre pas par statut
    } else {
        $sql .= " AND p.statut = 0"; // Par défaut, seulement les paiements actifs
    }
    
    $sql .= " ORDER BY p.date DESC, p.id DESC";
    
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $paiements = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Pour chaque paiement, calculer les totaux de la commande
    foreach ($paiements as &$paiement) {
        // Calculer le total de la commande
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) as total
            FROM commande_produits cp
            WHERE cp.commande_id = ? AND cp.statut = 0
        ");
        $query->execute([$paiement['commande_id']]);
        $paiement['total_commande'] = (float)$query->fetchColumn();
        
        // Calculer le total payé pour cette commande
        $query = $pdo->prepare("
            SELECT COALESCE(SUM(montant), 0) as total_paye
            FROM paiements 
            WHERE commandes_id = ? AND statut = 0
        ");
        $query->execute([$paiement['commande_id']]);
        $paiement['total_paye'] = (float)$query->fetchColumn();
        
        $paiement['reste_a_payer'] = $paiement['total_commande'] - $paiement['total_paye'];
        
        // Déterminer le statut de paiement
        if ($paiement['reste_a_payer'] <= 0) {
            $paiement['statut_paiement'] = 'payee';
        } elseif ($paiement['total_paye'] > 0) {
            $paiement['statut_paiement'] = 'partiel';
        } else {
            $paiement['statut_paiement'] = 'impayee';
        }
    }
    unset($paiement); // Détruire la référence
    
    // Calcul des totaux
    $total_paiements = 0;
    $total_paiements_actifs = 0;
    $total_paiements_supprimes = 0;
    
    foreach ($paiements as $paiement) {
        $total_paiements += $paiement['montant'];
        if ($paiement['statut'] == 0) {
            $total_paiements_actifs += $paiement['montant'];
        } else {
            $total_paiements_supprimes += $paiement['montant'];
        }
    }
    
} catch (PDOException $e) {
    $paiements = [];
    $total_paiements = 0;
    $total_paiements_actifs = 0;
    $total_paiements_supprimes = 0;
    error_log("Erreur récupération paiements: " . $e->getMessage());
}

// Récupérer la liste des commandes pour le filtre et le formulaire
// IMPORTANT: Seulement les commandes non payées (etat = 'brouillon') et avec reste à payer > 0
try {
    $query = $pdo->prepare("
        SELECT 
            c.id, 
            c.numero_facture, 
            c.client_nom, 
            c.date_commande,
            c.etat,
            (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) 
             FROM commande_produits cp 
             WHERE cp.commande_id = c.id AND cp.statut = 0) as total_commande,
            (SELECT COALESCE(SUM(p.montant), 0) 
             FROM paiements p 
             WHERE p.commandes_id = c.id AND p.statut = 0) as total_paye
        FROM commandes c
        WHERE c.boutique_id = ? 
          AND c.statut = 0 
          AND c.etat = 'brouillon'
        ORDER BY c.date_commande DESC
        LIMIT 100
    ");
    $query->execute([$boutique_id]);
    $commandes = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Ajouter le reste à payer pour chaque commande et filtrer celles avec reste > 0
    $commandes_avec_reste = [];
    foreach ($commandes as &$commande) {
        $commande['reste_a_payer'] = $commande['total_commande'] - $commande['total_paye'];
        if ($commande['reste_a_payer'] > 0) {
            $commandes_avec_reste[] = $commande;
        }
    }
    unset($commande);
    
} catch (PDOException $e) {
    $commandes = [];
    $commandes_avec_reste = [];
    error_log("Erreur récupération commandes: " . $e->getMessage());
}

// Récupérer les statistiques par mois
try {
    $query = $pdo->prepare("
        SELECT 
            DATE_FORMAT(p.date, '%Y-%m') as mois,
            COUNT(p.id) as nombre_paiements,
            SUM(p.montant) as total_mois,
            MIN(p.date) as date_debut_mois,
            MAX(p.date) as date_fin_mois
        FROM paiements p
        JOIN commandes c ON p.commandes_id = c.id
        WHERE c.boutique_id = ? AND p.statut = 0
        GROUP BY DATE_FORMAT(p.date, '%Y-%m')
        ORDER BY mois DESC
        LIMIT 12
    ");
    $query->execute([$boutique_id]);
    $stats_mois = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats_mois = [];
    error_log("Erreur récupération stats mois: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Gestion des Paiements - Boutique NGS</title>
    
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

        .gradient-orange-btn {
            background: linear-gradient(90deg, #F59E0B 0%, #D97706 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-orange-btn:hover {
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

        .status-actif {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-supprime {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .status-paiement-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .status-payee {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-partiel {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .status-impayee {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .total-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .table-container {
            overflow-x: auto;
        }

        .paiement-row:hover {
            background-color: #f9fafb;
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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
                <a href="paiements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-money-bill-wave w-5 text-white"></i>
                    <span class="font-medium">Paiements</span>
                </a>
                <a href="mouvements.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-exchange-alt w-5 text-white"></i>
                    <span>Mouvements Caisse</span>
                </a>
                <a href="stock_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Stock boutique</span>
                </a>
                <a href="transferts-boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-truck-loading w-5 text-gray-300"></i>
                    <span>Transferts</span>
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
            <header class="bg-white border-b border-gray-200 p-6 sticky top-0 z-30 shadow-sm">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Gestion des Paiements</h1>
                        <p class="text-gray-600">
                            Suivi et gestion des paiements clients
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="openAjoutModal()"
                                class="px-4 py-2 gradient-purple-btn text-white rounded-lg hover:opacity-90 shadow-md"
                                <?= empty($commandes_avec_reste) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus mr-2"></i>Nouveau paiement
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <?php if ($message): ?>
                    <div class="mb-6 fade-in relative z-10 animate-fade-in">
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

                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-2xl shadow-soft p-6 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-90">Total Paiements Actifs</p>
                                <p class="text-2xl font-bold mt-2"><?= number_format($total_paiements_actifs, 3) ?> $</p>
                                <p class="text-xs opacity-90 mt-1"><?= count(array_filter($paiements, fn($p) => $p['statut'] == 0)) ?> paiement(s)</p>
                            </div>
                            <div class="text-4xl opacity-80">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-2xl shadow-soft p-6 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-90">Total Général</p>
                                <p class="text-2xl font-bold mt-2"><?= number_format($total_paiements, 3) ?> $</p>
                                <p class="text-xs opacity-90 mt-1"><?= count($paiements) ?> paiement(s) total</p>
                            </div>
                            <div class="text-4xl opacity-80">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-2xl shadow-soft p-6 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-90">Paiements Supprimés</p>
                                <p class="text-2xl font-bold mt-2"><?= number_format($total_paiements_supprimes, 3) ?> $</p>
                                <p class="text-xs opacity-90 mt-1"><?= count(array_filter($paiements, fn($p) => $p['statut'] == 1)) ?> paiement(s)</p>
                            </div>
                            <div class="text-4xl opacity-80">
                                <i class="fas fa-trash-alt"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-gradient-to-r from-purple-500 to-violet-600 text-white rounded-2xl shadow-soft p-6 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm opacity-90">Commandes à Payer</p>
                                <p class="text-2xl font-bold mt-2"><?= count($commandes_avec_reste) ?></p>
                                <p class="text-xs opacity-90 mt-1">
                                    <?= array_sum(array_column($commandes_avec_reste, 'reste_a_payer')) > 0 ? 
                                       number_format(array_sum(array_column($commandes_avec_reste, 'reste_a_payer')), 3) . ' $ total' : 
                                       'Toutes payées' ?>
                                </p>
                            </div>
                            <div class="text-4xl opacity-80">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Filtres de recherche</h3>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-2">
                                Date de début
                            </label>
                            <input type="date" id="date_debut" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                        
                        <div>
                            <label for="date_fin" class="block text-sm font-medium text-gray-700 mb-2">
                                Date de fin
                            </label>
                            <input type="date" id="date_fin" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                        
                        <div>
                            <label for="commande_id" class="block text-sm font-medium text-gray-700 mb-2">
                                N° Commande
                            </label>
                            <select id="commande_id" name="commande_id" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                <option value="">Toutes les commandes</option>
                                <?php foreach ($commandes_avec_reste as $commande): ?>
                                    <option value="<?= $commande['id'] ?>" <?= $commande_id_filter == $commande['id'] ? 'selected' : '' ?>>
                                        #<?= $commande['id'] ?> - <?= htmlspecialchars($commande['client_nom']) ?> 
                                        (<?= number_format($commande['reste_a_payer'], 3) ?> $ restant)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="statut" class="block text-sm font-medium text-gray-700 mb-2">
                                Statut
                            </label>
                            <select id="statut" name="statut" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                <option value="" <?= $statut_filter === '' ? 'selected' : '' ?>>Actifs seulement</option>
                                <option value="0" <?= $statut_filter === '0' ? 'selected' : '' ?>>Actifs</option>
                                <option value="1" <?= $statut_filter === '1' ? 'selected' : '' ?>>Supprimés</option>
                                <option value="all" <?= $statut_filter === 'all' ? 'selected' : '' ?>>Tous</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-4 flex justify-end space-x-3 pt-4">
                            <button type="submit" 
                                    class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 shadow-md">
                                <i class="fas fa-search mr-2"></i>Appliquer les filtres
                            </button>
                            <a href="paiements.php" 
                               class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                                <i class="fas fa-times mr-2"></i>Réinitialiser
                            </a>
                        </div>
                    </form>
                    
                    <!-- Boutons rapides pour les périodes -->
                    <div class="flex space-x-2 mt-4">
                        <button type="button" onclick="setCurrentMonth()" class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            Ce mois
                        </button>
                        <button type="button" onclick="setPreviousMonth()" class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            Mois précédent
                        </button>
                        <button type="button" onclick="setAllDates()" class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            Toutes dates
                        </button>
                    </div>
                </div>

                <!-- Statistiques par mois -->
                <?php if (!empty($stats_mois)): ?>
                    <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Paiements par mois</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mois</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre de paiements</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total du mois</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moyenne par paiement</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($stats_mois as $stat): 
                                        $mois_nom = date('F Y', strtotime($stat['mois'] . '-01'));
                                        $moyenne = $stat['nombre_paiements'] > 0 ? $stat['total_mois'] / $stat['nombre_paiements'] : 0;
                                    ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                                <?= $mois_nom ?>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-900">
                                                <?= $stat['nombre_paiements'] ?> paiement(s)
                                            </td>
                                            <td class="px-4 py-4 text-sm font-bold text-gray-900">
                                                <?= number_format($stat['total_mois'], 3) ?> $
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-900">
                                                <?= number_format($moyenne, 3) ?> $
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tableau des paiements -->
                <div class="bg-white rounded-2xl shadow-soft p-6 animate-fade-in">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-gray-900">Liste des paiements</h3>
                        <div class="text-sm text-gray-600">
                            <?= count($paiements) ?> paiement(s) trouvé(s)
                        </div>
                    </div>
                    
                    <?php if (!empty($paiements)): ?>
                        <div class="table-container">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commande</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Commande</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Déjà Payé</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reste à Payer</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut Paiement</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($paiements as $paiement): ?>
                                        <tr class="paiement-row hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                                #<?= $paiement['paiement_id'] ?>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-900">
                                                <?= date('d/m/Y', strtotime($paiement['date'])) ?>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    Commande #<?= $paiement['commande_id'] ?>
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Facture: <?= htmlspecialchars($paiement['numero_facture']) ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-900">
                                                <?= htmlspecialchars($paiement['client_nom'] ?? 'Non renseigné') ?>
                                            </td>
                                            <td class="px-4 py-4 text-sm font-bold text-gray-900">
                                                <?= number_format($paiement['montant'], 3) ?> $
                                            </td>
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900">
                                                <?= number_format($paiement['total_commande'], 3) ?> $
                                            </td>
                                            <td class="px-4 py-4 text-sm font-bold text-green-700">
                                                <?= number_format($paiement['total_paye'], 3) ?> $
                                            </td>
                                            <td class="px-4 py-4 text-sm font-bold <?= $paiement['reste_a_payer'] > 0 ? 'text-red-700' : 'text-green-700' ?>">
                                                <?= number_format($paiement['reste_a_payer'], 3) ?> $
                                            </td>
                                            <td class="px-4 py-4">
                                                <?php if ($paiement['statut_paiement'] == 'payee'): ?>
                                                    <span class="status-paiement-badge status-payee">
                                                        <i class="fas fa-check-circle mr-1"></i>Payée
                                                    </span>
                                                <?php elseif ($paiement['statut_paiement'] == 'partiel'): ?>
                                                    <span class="status-paiement-badge status-partiel">
                                                        <i class="fas fa-exclamation-circle mr-1"></i>Partiel
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-paiement-badge status-impayee">
                                                        <i class="fas fa-times-circle mr-1"></i>Impayée
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4">
                                                <?php if ($paiement['statut'] == 0): ?>
                                                    <span class="status-badge status-actif">
                                                        <i class="fas fa-check-circle mr-1"></i>Actif
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge status-supprime">
                                                        <i class="fas fa-times-circle mr-1"></i>Supprimé
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="flex space-x-2">
                                                    <?php if ($paiement['statut'] == 0): ?>
                                                        <!-- MODIFICATION ICI : Simple lien pour imprimer le reçu -->
                                                        <a href="imprimer_recu.php?id=<?= $paiement['paiement_id'] ?>" target="_blank"
                                                                class="px-3 py-1 bg-orange-50 text-orange-700 hover:bg-orange-100 rounded-lg text-sm transition-colors action-btn inline-flex items-center">
                                                            <i class="fas fa-print mr-1"></i>Reçu
                                                        </a>
                                                        <button onclick="openModifierModal(<?= $paiement['paiement_id'] ?>, '<?= $paiement['date'] ?>', <?= $paiement['montant'] ?>, <?= $paiement['commande_id'] ?>)"
                                                                class="px-3 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg text-sm transition-colors action-btn">
                                                            <i class="fas fa-edit mr-1"></i>Modifier
                                                        </button>
                                                        <form method="POST" action="" 
                                                              onsubmit="return confirm('Supprimer ce paiement ? Cette action est réversible.');"
                                                              class="inline">
                                                            <input type="hidden" name="paiement_id" value="<?= $paiement['paiement_id'] ?>">
                                                            <button type="submit" name="supprimer_paiement" 
                                                                    class="px-3 py-1 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-sm transition-colors action-btn">
                                                                <i class="fas fa-trash-alt mr-1"></i>Supprimer
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-sm text-gray-400">Actions désactivées</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-4 py-4 text-sm font-bold text-gray-900 text-right">
                                            Totaux :
                                        </td>
                                        <td class="px-4 py-4 text-sm font-bold text-gray-900">
                                            <?= number_format($total_paiements, 3) ?> $
                                        </td>
                                        <td colspan="7"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Pagination (optionnelle) -->
                        <div class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                Affichage de <?= count($paiements) ?> paiement(s)
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-money-bill-wave text-5xl"></i>
                            </div>
                            <h4 class="text-gray-600 font-medium">Aucun paiement trouvé</h4>
                            <p class="text-gray-500 text-sm mt-1">
                                <?php if ($date_debut || $date_fin || $commande_id_filter || $statut_filter): ?>
                                    Aucun paiement ne correspond à vos critères de recherche.
                                <?php else: ?>
                                    Commencez par ajouter votre premier paiement.
                                <?php endif; ?>
                            </p>
                            <div class="mt-6">
                                <button onclick="openAjoutModal()"
                                        class="px-4 py-2 gradient-purple-btn text-white rounded-lg hover:opacity-90 shadow-md"
                                        <?= empty($commandes_avec_reste) ? 'disabled' : '' ?>>
                                    <i class="fas fa-plus mr-2"></i>Ajouter un paiement
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Informations sur les statuts -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium text-blue-900">Information sur les statuts</h4>
                                <p class="text-sm text-blue-700 mt-1">
                                    • <span class="status-badge status-actif">Actif</span> : Paiement enregistré et comptabilisé<br>
                                    • <span class="status-badge status-supprime">Supprimé</span> : Paiement archivé (soft delete), non comptabilisé dans les totaux actifs
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                        <div class="flex items-start">
                            <i class="fas fa-money-check-alt text-green-600 mt-1 mr-3"></i>
                            <div>
                                <h4 class="font-medium text-green-900">Statuts de paiement commande</h4>
                                <p class="text-sm text-green-700 mt-1">
                                    • <span class="status-paiement-badge status-payee">Payée</span> : Commande entièrement payée<br>
                                    • <span class="status-paiement-badge status-partiel">Partiel</span> : Acompte versé<br>
                                    • <span class="status-paiement-badge status-impayee">Impayée</span> : Aucun paiement enregistré
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal d'ajout de paiement -->
    <div id="ajoutModal" class="modal">
        <div class="modal-content slide-down">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Nouveau paiement</h3>
                <form method="POST" action="" id="formAjoutPaiement">
                    <div class="space-y-4">
                        <!-- Date automatique - affichage seulement -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Date du paiement
                            </label>
                            <div class="px-4 py-2 bg-gray-50 rounded-lg border border-gray-200">
                                <span class="text-sm text-gray-900 font-medium">
                                    <?= date('d/m/Y') ?> (Date automatique)
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">La date d'aujourd'hui est automatiquement enregistrée</p>
                        </div>
                        
                        <!-- Commande associée -->
                        <div>
                            <label for="ajout_commande_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Commande associée *
                            </label>
                            <select id="ajout_commande_id" name="commande_id" required 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                    onchange="updateMontantRestant()">
                                <option value="">Sélectionnez une commande</option>
                                <?php foreach ($commandes_avec_reste as $commande): ?>
                                    <option value="<?= $commande['id'] ?>" 
                                            data-total="<?= $commande['total_commande'] ?>"
                                            data-paye="<?= $commande['total_paye'] ?>"
                                            data-reste="<?= $commande['reste_a_payer'] ?>">
                                        #<?= $commande['id'] ?> - <?= htmlspecialchars($commande['client_nom']) ?> 
                                        (Total: <?= number_format($commande['total_commande'], 3) ?> $, 
                                        Reste: <?= number_format($commande['reste_a_payer'], 3) ?> $)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="info-commande" class="mt-2 text-sm hidden">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <span class="text-gray-600">Total commande:</span>
                                        <span id="info-total" class="font-bold ml-1"></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-600">Déjà payé:</span>
                                        <span id="info-paye" class="font-bold text-green-600 ml-1"></span>
                                    </div>
                                    <div class="col-span-2">
                                        <span class="text-gray-600">Reste à payer:</span>
                                        <span id="info-reste" class="font-bold text-red-600 ml-1"></span>
                                    </div>
                                </div>
                            </div>
                            <?php if (empty($commandes_avec_reste)): ?>
                                <p class="text-xs text-red-500 mt-1">
                                    Aucune commande avec reste à payer. Toutes les commandes sont payées ou aucune commande n'existe.
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Montant -->
                        <div>
                            <label for="ajout_montant" class="block text-sm font-medium text-gray-700 mb-2">
                                Montant ($) *
                            </label>
                            <input type="number" id="ajout_montant" name="montant" required step="0.001" min="0.001"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="0.000"
                                   oninput="validateMontantAjout()">
                            <p class="text-xs text-gray-500 mt-1">Format décimal avec 3 chiffres après la virgule</p>
                            <div id="montant-validation" class="text-xs mt-1 hidden">
                                <span id="validation-message"></span>
                            </div>
                        </div>
                        
                        <!-- Note sur l'impression automatique -->
                        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-600 mt-0.5 mr-2"></i>
                                <div>
                                    <p class="text-sm text-blue-700 font-medium">Le reçu sera automatiquement imprimé après l'ajout.</p>
                                    <p class="text-xs text-blue-600 mt-1">Après l'enregistrement, vous serez redirigé vers la page d'impression du reçu.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeAjoutModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="ajouter_paiement" 
                                class="px-4 py-2 gradient-purple-btn text-white rounded-lg hover:opacity-90 shadow-md"
                                <?= empty($commandes_avec_reste) ? 'disabled' : '' ?>>
                            <i class="fas fa-plus mr-2"></i>Ajouter le paiement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal de modification de paiement -->
    <div id="modifierModal" class="modal">
        <div class="modal-content slide-down">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Modifier le paiement</h3>
                <form method="POST" action="" id="formModifierPaiement">
                    <input type="hidden" id="modifier_paiement_id" name="paiement_id">
                    <input type="hidden" id="modifier_commande_id" name="commande_id">
                    <input type="hidden" id="modifier_ancien_montant" name="ancien_montant">
                    
                    <div class="space-y-4">
                        <!-- Date automatique - affichage seulement -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Date du paiement
                            </label>
                            <div class="px-4 py-2 bg-gray-50 rounded-lg border border-gray-200">
                                <span id="modifier_date_display" class="text-sm text-gray-900 font-medium"></span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">La date ne peut pas être modifiée</p>
                        </div>
                        
                        <!-- Information commande -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Commande associée
                            </label>
                            <div class="px-4 py-2 bg-gray-50 rounded-lg border border-gray-200">
                                <div id="modifier_commande_info" class="text-sm text-gray-900"></div>
                                <div id="modifier_commande_totaux" class="text-xs text-gray-600 mt-1"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">La commande ne peut pas être modifiée</p>
                        </div>
                        
                        <!-- Montant -->
                        <div>
                            <label for="modifier_montant" class="block text-sm font-medium text-gray-700 mb-2">
                                Montant ($) *
                            </label>
                            <input type="number" id="modifier_montant" name="montant" required step="0.001" min="0.001"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                   placeholder="0.000"
                                   oninput="validateMontantModification()">
                            <div id="modifier-montant-validation" class="text-xs mt-1 hidden">
                                <span id="modifier-validation-message"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModifierModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="modifier_paiement" 
                                class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 shadow-md">
                            <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Variables pour stocker les infos de commande
        let commandesData = <?= json_encode($commandes_avec_reste) ?>;
        let paiementsData = <?= json_encode($paiements) ?>;
        
        // Fonctions pour les modals
        function openAjoutModal() {
            document.getElementById('ajoutModal').classList.add('show');
            // Focus sur le premier champ
            setTimeout(() => {
                document.getElementById('ajout_commande_id').focus();
            }, 100);
        }
        
        function closeAjoutModal() {
            document.getElementById('ajoutModal').classList.remove('show');
            // Réinitialiser les champs
            document.getElementById('ajout_commande_id').selectedIndex = 0;
            document.getElementById('ajout_montant').value = '';
            document.getElementById('info-commande').classList.add('hidden');
            document.getElementById('montant-validation').classList.add('hidden');
        }
        
        function openModifierModal(paiementId, date, montant, commandeId) {
            document.getElementById('modifier_paiement_id').value = paiementId;
            document.getElementById('modifier_montant').value = montant;
            document.getElementById('modifier_commande_id').value = commandeId;
            document.getElementById('modifier_ancien_montant').value = montant;
            
            // Formater la date pour l'affichage
            const dateObj = new Date(date);
            const dateFormatted = dateObj.toLocaleDateString('fr-FR');
            document.getElementById('modifier_date_display').textContent = dateFormatted + ' (Date originale)';
            
            // Trouver les informations de la commande
            const commande = commandesData.find(c => c.id == commandeId);
            if (commande) {
                document.getElementById('modifier_commande_info').innerHTML = `
                    Commande #${commande.id} - ${commande.client_nom}<br>
                    Facture: ${commande.numero_facture}
                `;
                document.getElementById('modifier_commande_totaux').innerHTML = `
                    Total: ${parseFloat(commande.total_commande).toFixed(3)} $ | 
                    Déjà payé: <span class="text-green-600">${parseFloat(commande.total_paye).toFixed(3)} $</span> | 
                    Reste: <span class="text-red-600">${parseFloat(commande.reste_a_payer).toFixed(3)} $</span>
                `;
            }
            
            document.getElementById('modifierModal').classList.add('show');
            // Focus sur le champ montant
            setTimeout(() => {
                document.getElementById('modifier_montant').focus();
                document.getElementById('modifier_montant').select();
            }, 100);
        }
        
        function closeModifierModal() {
            document.getElementById('modifierModal').classList.remove('show');
            document.getElementById('modifier-montant-validation').classList.add('hidden');
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
        
        // Mettre à jour les informations de la commande sélectionnée
        function updateMontantRestant() {
            const select = document.getElementById('ajout_commande_id');
            const selectedOption = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('info-commande');
            
            if (selectedOption.value) {
                const total = selectedOption.getAttribute('data-total');
                const paye = selectedOption.getAttribute('data-paye');
                const reste = selectedOption.getAttribute('data-reste');
                
                document.getElementById('info-total').textContent = parseFloat(total).toFixed(3) + ' $';
                document.getElementById('info-paye').textContent = parseFloat(paye).toFixed(3) + ' $';
                document.getElementById('info-reste').textContent = parseFloat(reste).toFixed(3) + ' $';
                
                infoDiv.classList.remove('hidden');
                
                // Mettre à jour le max du champ montant
                document.getElementById('ajout_montant').max = reste;
                
                // Remplir automatiquement le montant restant
                if (parseFloat(reste) > 0) {
                    document.getElementById('ajout_montant').value = parseFloat(reste);
                    validateMontantAjout();
                }
            } else {
                infoDiv.classList.add('hidden');
            }
            
            // Valider le montant
            validateMontantAjout();
        }
        
        // Validation du montant pour l'ajout
        function validateMontantAjout() {
            const select = document.getElementById('ajout_commande_id');
            const selectedOption = select.options[select.selectedIndex];
            const montantInput = document.getElementById('ajout_montant');
            const montant = parseFloat(montantInput.value);
            const validationDiv = document.getElementById('montant-validation');
            const messageSpan = document.getElementById('validation-message');
            
            if (!selectedOption.value || !montant) {
                validationDiv.classList.add('hidden');
                return;
            }
            
            const reste = parseFloat(selectedOption.getAttribute('data-reste'));
            
            if (montant > reste) {
                validationDiv.classList.remove('hidden');
                validationDiv.className = 'text-xs mt-1 text-red-600';
                messageSpan.textContent = `Le montant dépasse le reste à payer (${reste.toFixed(3)} $)`;
            } else if (montant <= 0) {
                validationDiv.classList.remove('hidden');
                validationDiv.className = 'text-xs mt-1 text-red-600';
                messageSpan.textContent = 'Le montant doit être supérieur à 0';
            } else {
                validationDiv.classList.remove('hidden');
                validationDiv.className = 'text-xs mt-1 text-green-600';
                messageSpan.textContent = `Montant valide. Reste après paiement: ${(reste - montant).toFixed(3)} $`;
            }
        }
        
        // Validation du montant pour la modification
        function validateMontantModification() {
            const commandeId = document.getElementById('modifier_commande_id').value;
            const ancienMontant = parseFloat(document.getElementById('modifier_ancien_montant').value);
            const nouveauMontant = parseFloat(document.getElementById('modifier_montant').value);
            const validationDiv = document.getElementById('modifier-montant-validation');
            const messageSpan = document.getElementById('modifier-validation-message');
            
            if (!commandeId || !nouveauMontant) {
                validationDiv.classList.add('hidden');
                return;
            }
            
            // Trouver la commande
            const commande = commandesData.find(c => c.id == commandeId);
            if (!commande) {
                validationDiv.classList.add('hidden');
                return;
            }
            
            // Calculer le reste disponible pour la modification
            // On soustrait l'ancien montant du total payé pour avoir ce qui a été payé sans ce paiement
            const totalPayeSansAncien = parseFloat(commande.total_paye) - ancienMontant;
            const resteDisponible = parseFloat(commande.total_commande) - totalPayeSansAncien;
            
            if (nouveauMontant > resteDisponible) {
                validationDiv.classList.remove('hidden');
                validationDiv.className = 'text-xs mt-1 text-red-600';
                messageSpan.textContent = `Le nouveau montant dépasse le reste disponible (${resteDisponible.toFixed(3)} $)`;
            } else if (nouveauMontant <= 0) {
                validationDiv.classList.remove('hidden');
                validationDiv.className = 'text-xs mt-1 text-red-600';
                messageSpan.textContent = 'Le montant doit être supérieur à 0';
            } else {
                validationDiv.classList.remove('hidden');
                validationDiv.className = 'text-xs mt-1 text-green-600';
                const nouveauReste = resteDisponible - nouveauMontant;
                messageSpan.textContent = `Montant valide. Reste après modification: ${nouveauReste.toFixed(3)} $`;
            }
        }
        
        // Validation des formulaires
        document.getElementById('formAjoutPaiement').addEventListener('submit', function(e) {
            const select = document.getElementById('ajout_commande_id');
            const selectedOption = select.options[select.selectedIndex];
            const montant = parseFloat(document.getElementById('ajout_montant').value);
            
            if (!selectedOption.value) {
                e.preventDefault();
                alert('Veuillez sélectionner une commande');
                select.focus();
                return;
            }
            
            const reste = parseFloat(selectedOption.getAttribute('data-reste'));
            
            if (montant <= 0) {
                e.preventDefault();
                alert('Le montant doit être supérieur à 0');
                document.getElementById('ajout_montant').focus();
                document.getElementById('ajout_montant').select();
                return;
            }
            
            if (montant > reste) {
                e.preventDefault();
                alert(`Le montant du paiement (${montant.toFixed(3)} $) dépasse le montant restant à payer (${reste.toFixed(3)} $)`);
                document.getElementById('ajout_montant').focus();
                document.getElementById('ajout_montant').select();
            }
        });
        
        document.getElementById('formModifierPaiement').addEventListener('submit', function(e) {
            const nouveauMontant = parseFloat(document.getElementById('modifier_montant').value);
            
            if (nouveauMontant <= 0) {
                e.preventDefault();
                alert('Le montant doit être supérieur à 0');
                document.getElementById('modifier_montant').focus();
                document.getElementById('modifier_montant').select();
            }
        });
        
        // Auto-focus et validation
        document.addEventListener('DOMContentLoaded', function() {
            // Écouter les changements sur le champ montant d'ajout
            document.getElementById('ajout_montant').addEventListener('input', validateMontantAjout);
            document.getElementById('ajout_commande_id').addEventListener('change', updateMontantRestant);
            
            // Si on ouvre le modal d'ajout via URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('ajouter')) {
                openAjoutModal();
            }
            
            // Touche Échap pour fermer les modals
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeAjoutModal();
                    closeModifierModal();
                }
            });
        });
        
        // Fonctions pour les filtres rapides de date
        function setCurrentMonth() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            document.getElementById('date_debut').value = formatDate(firstDay);
            document.getElementById('date_fin').value = formatDate(lastDay);
        }
        
        function setPreviousMonth() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
            
            document.getElementById('date_debut').value = formatDate(firstDay);
            document.getElementById('date_fin').value = formatDate(lastDay);
        }
        
        function setAllDates() {
            document.getElementById('date_debut').value = '';
            document.getElementById('date_fin').value = '';
        }
        
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    </script>
</body>
</html>