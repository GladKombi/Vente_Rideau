<?php
# Connexion à la base de données
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
$total_commandes = 0;
$commandes = [];

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// NOUVELLE VERSION : Générer un numéro de facture unique sans date
function generateNumeroFacture($pdo, $boutique_id) {
    $prefix = 'FACT-B' . $boutique_id . '-';
    
    // Trouver le dernier numéro de facture pour cette boutique (toutes factures confondues)
    $query = $pdo->prepare("
        SELECT numero_facture 
        FROM commandes 
        WHERE numero_facture LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $likePattern = $prefix . '%';
    $query->execute([$likePattern]);
    $lastFacture = $query->fetch(PDO::FETCH_ASSOC);
    
    if ($lastFacture && !empty($lastFacture['numero_facture'])) {
        // Extraire le numéro incrémental du dernier numéro (ex: FACT-B1-001)
        $matches = [];
        if (preg_match('/-(\d+)$/', $lastFacture['numero_facture'], $matches)) {
            $lastNumber = (int)$matches[1];
            $nextNumber = $lastNumber + 1;
        } else {
            // Si le format est incorrect, prendre le prochain numéro basé sur le comptage
            $countQuery = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM commandes 
                WHERE numero_facture LIKE ?
            ");
            $countQuery->execute([$likePattern]);
            $result = $countQuery->fetch(PDO::FETCH_ASSOC);
            $nextNumber = ($result['count'] ?? 0) + 1;
        }
    } else {
        // Première facture pour cette boutique
        $nextNumber = 1;
    }
    
    return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}

// --- TRAITEMENT DU FORMULAIRE D'AJOUT/MODIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajouter_commande'])) {
        // Ajouter une nouvelle commande
        try {
            $numero_facture = generateNumeroFacture($pdo, $boutique_id);
            
            // Vérifier que le numéro n'existe pas déjà (sécurité supplémentaire)
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE numero_facture = ?");
            $checkQuery->execute([$numero_facture]);
            
            if ($checkQuery->fetchColumn() > 0) {
                // Si le numéro existe déjà, on prend le suivant
                $matches = [];
                if (preg_match('/-(\d+)$/', $numero_facture, $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                    $prefix = substr($numero_facture, 0, strrpos($numero_facture, '-') + 1);
                    $numero_facture = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                }
            }
            
            $query = $pdo->prepare("
                INSERT INTO commandes (numero_facture, client_nom, boutique_id, date_commande)
                VALUES (?, ?, ?, NOW())
            ");
            
            $query->execute([
                $numero_facture,
                $_POST['client_nom'] ?? '',
                $boutique_id
            ]);
            
            $commande_id = $pdo->lastInsertId();
            
            $_SESSION['flash_message'] = [
                'text' => "Commande #$numero_facture créée avec succès !",
                'type' => "success"
            ];
            
            // Rediriger vers la page de détails de la commande pour ajouter des produits
            header("Location: commande_details.php?id=$commande_id");
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = [
                'text' => "Erreur lors de la création de la commande: " . $e->getMessage(),
                'type' => "error"
            ];
        }
    }
    
    elseif (isset($_POST['modifier_commande'])) {
        // Modifier une commande existante
        try {
            $query = $pdo->prepare("
                UPDATE commandes 
                SET client_nom = ?
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            
            $query->execute([
                $_POST['client_nom'] ?? '',
                $_POST['commande_id'],
                $boutique_id
            ]);
            
            $_SESSION['flash_message'] = [
                'text' => "Commande modifiée avec succès !",
                'type' => "success"
            ];
            
            header("Location: ventes_boutique.php");
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = [
                'text' => "Erreur lors de la modification: " . $e->getMessage(),
                'type' => "error"
            ];
        }
    }
    
    elseif (isset($_POST['changer_etat'])) {
        // Changer l'état de la commande (brouillon -> payée)
        try {
            $query = $pdo->prepare("
                UPDATE commandes 
                SET etat = ?
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            
            $query->execute([
                $_POST['nouvel_etat'],
                $_POST['commande_id'],
                $boutique_id
            ]);
            
            $etat_text = $_POST['nouvel_etat'] == 'payee' ? 'payée' : 'brouillon';
            $_SESSION['flash_message'] = [
                'text' => "Commande marquée comme $etat_text !",
                'type' => "success"
            ];
            
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = [
                'text' => "Erreur lors du changement d'état: " . $e->getMessage(),
                'type' => "error"
            ];
        }
    }
    
    elseif (isset($_POST['archiver_commande'])) {
        // Archiver une commande (soft delete)
        try {
            $query = $pdo->prepare("
                UPDATE commandes 
                SET statut = 1
                WHERE id = ? AND boutique_id = ? AND statut = 0
            ");
            
            $query->execute([
                $_POST['commande_id'],
                $boutique_id
            ]);
            
            $_SESSION['flash_message'] = [
                'text' => "Commande archivée avec succès !",
                'type' => "success"
            ];
            
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = [
                'text' => "Erreur lors de l'archivage: " . $e->getMessage(),
                'type' => "error"
            ];
        }
    }
}

// Vérifier si c'est une requête AJAX pour récupérer les données d'une commande
if (isset($_GET['action']) && $_GET['action'] == 'get_commande' && isset($_GET['id'])) {
    $commandeId = (int)$_GET['id'];
    try {
        $query = $pdo->prepare("
            SELECT c.*, 
                   b.nom as boutique_nom
            FROM commandes c
            JOIN boutiques b ON c.boutique_id = b.id
            WHERE c.id = ? AND c.boutique_id = ? AND c.statut = 0
        ");
        $query->execute([$commandeId, $boutique_id]);
        $commande = $query->fetch(PDO::FETCH_ASSOC);

        if ($commande) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'commande' => $commande]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
    }
    exit;
}

// Filtres
$filter_etat = $_GET['etat'] ?? '';
$filter_date_debut = $_GET['date_debut'] ?? '';
$filter_date_fin = $_GET['date_fin'] ?? '';
$search_term = $_GET['search'] ?? '';

// Construire la requête avec filtres
$whereConditions = ["c.boutique_id = ?", "c.statut = 0"];
$params = [$boutique_id];

if ($filter_etat) {
    $whereConditions[] = "c.etat = ?";
    $params[] = $filter_etat;
}

if ($filter_date_debut) {
    $whereConditions[] = "DATE(c.date_commande) >= ?";
    $params[] = $filter_date_debut;
}

if ($filter_date_fin) {
    $whereConditions[] = "DATE(c.date_commande) <= ?";
    $params[] = $filter_date_fin;
}

if ($search_term) {
    $whereConditions[] = "(c.numero_facture LIKE ? OR c.client_nom LIKE ?)";
    $searchPattern = "%$search_term%";
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$whereClause = implode(' AND ', $whereConditions);

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Compter le nombre total de commandes
try {
    // Compter le total
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM commandes c WHERE $whereClause");
    $countQuery->execute($params);
    $total_commandes = $countQuery->fetchColumn();
    $totalPages = ceil($total_commandes / $limit);

    // --- CORRECTION COMPLÈTE : Utilisation uniquement de paramètres positionnels ---
    
    // Construire la requête SQL complète
    $sql = "
        SELECT c.*, 
               b.nom as boutique_nom,
               (SELECT COUNT(*) FROM commande_produits cp WHERE cp.commande_id = c.id AND cp.statut = 0) as nb_produits,
               (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) FROM commande_produits cp WHERE cp.commande_id = c.id AND cp.statut = 0) as montant_total
        FROM commandes c
        JOIN boutiques b ON c.boutique_id = b.id
        WHERE $whereClause
        ORDER BY c.date_commande DESC 
        LIMIT ? OFFSET ?
    ";
    
    // Ajouter les paramètres de pagination à la fin du tableau de paramètres
    $allParams = array_merge($params, [$limit, $offset]);
    
    // Préparer et exécuter la requête
    $query = $pdo->prepare($sql);
    
    // Solution pour éviter l'erreur avec LIMIT/OFFSET :
    // 1. Préparer la requête avec des marqueurs ?
    // 2. Exécuter avec tous les paramètres
    // 3. Utiliser bindValue avec types explicits
    $query = $pdo->prepare($sql);
    
    // Lier tous les paramètres avec bindValue pour spécifier les types
    $paramIndex = 1;
    foreach ($params as $param) {
        // Déterminer le type en fonction de la valeur
        if (is_int($param)) {
            $query->bindValue($paramIndex, $param, PDO::PARAM_INT);
        } elseif (is_bool($param)) {
            $query->bindValue($paramIndex, $param, PDO::PARAM_BOOL);
        } elseif (is_null($param)) {
            $query->bindValue($paramIndex, $param, PDO::PARAM_NULL);
        } else {
            $query->bindValue($paramIndex, $param, PDO::PARAM_STR);
        }
        $paramIndex++;
    }
    
    // Pour les paramètres de pagination, utiliser PDO::PARAM_INT
    $query->bindValue($paramIndex, $limit, PDO::PARAM_INT);
    $query->bindValue($paramIndex + 1, $offset, PDO::PARAM_INT);
    
    // Exécuter la requête
    $query->execute();
    
    $commandes = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les statistiques
    $statsQuery = $pdo->prepare("
        SELECT 
            COUNT(*) as total_commandes,
            SUM(CASE WHEN etat = 'payee' THEN 1 ELSE 0 END) as commandes_payees,
            SUM(CASE WHEN etat = 'brouillon' THEN 1 ELSE 0 END) as commandes_brouillon,
            COALESCE(
                (SELECT SUM(cp.quantite * cp.prix_unitaire) 
                 FROM commande_produits cp 
                 JOIN commandes c2 ON cp.commande_id = c2.id 
                 WHERE c2.boutique_id = ? AND c2.statut = 0 AND c2.etat = 'payee' AND cp.statut = 0),
                0
            ) as chiffre_affaires
        FROM commandes 
        WHERE boutique_id = ? AND statut = 0
    ");
    
    $statsQuery->execute([$boutique_id, $boutique_id]);
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
    
    $chiffre_affaires = $stats['chiffre_affaires'] ?? 0;
    $commandes_payees = $stats['commandes_payees'] ?? 0;
    $commandes_brouillon = $stats['commandes_brouillon'] ?? 0;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des commandes: " . $e->getMessage(),
        'type' => "error"
    ];
    // Pour déboguer
    error_log("SQL Error in ventes_boutique.php: " . $e->getMessage());
    error_log("SQL Query: " . $sql ?? '');
    error_log("Params: " . print_r($allParams ?? $params, true));
    
    $chiffre_affaires = 0;
    $commandes_payees = 0;
    $commandes_brouillon = 0;
    $commandes = [];
    $total_commandes = 0;
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Gestion des Ventes - Boutique NGS</title>
    
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

        .gradient-accent {
            background: linear-gradient(90deg, #7B61FF 0%, #00D4AA 100%);
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

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .table-container {
            overflow-x: auto;
        }

        .commande-row:hover {
            background-color: #f9fafb;
        }

        .etat-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .montant-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            background-color: #DBEAFE;
            color: #1E40AF;
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
        <!-- Sidebar pour boutique (similaire à celle du PDG mais adaptée) -->
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
                <a href="ventes_boutique.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-shopping-cart w-5 text-white"></i>
                    <span>Ventes</span>
                    <span class="notification-badge"><?= $total_commandes ?></span>
                </a>
                <a href="paiements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users w-5 text-gray-300"></i>
                    <span>paiements</span>
                </a>
                <a href="stock_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Mes Stocks</span>
                </a>
                <a href="transferts-boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-truck-loading w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="rapports_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-bar w-5 text-gray-300"></i>
                    <span>Rapports</span>
                </a>
                <a href="mouvements.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-exchange-alt w-5 text-white"></i>
                    <span>Mouvements Caisse</span>
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
                        <h1 class="text-2xl font-bold text-gray-900">Gestion des Ventes - Boutique</h1>
                        <p class="text-gray-600">Nouvelle Grace Service - Interface de vente</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="openCommandeModal()"
                            class="px-4 py-3 gradient-green-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-plus"></i>
                            <span>Nouvelle vente</span>
                        </button>
                        <a href="rapports_boutique.php"
                            class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-chart-line"></i>
                            <span>Statistiques</span>
                        </a>
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

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-green-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-green-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-green-600">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_commandes ?></h3>
                        <p class="text-gray-600">Commandes</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-check-circle text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Payées</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $commandes_payees ?></h3>
                        <p class="text-gray-600">Commandes réglées</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-yellow-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
                                <i class="fas fa-edit text-yellow-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-yellow-600">Brouillons</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $commandes_brouillon ?></h3>
                        <p class="text-gray-600">Commandes en attente</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Chiffre d'affaires</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($chiffre_affaires, 2) ?> $</h3>
                        <p class="text-gray-600">Total des ventes</p>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in">
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">État</label>
                            <select name="etat" class="w-full border-gray-300 rounded-lg p-3">
                                <option value="">Tous les états</option>
                                <option value="brouillon" <?= $filter_etat == 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                                <option value="payee" <?= $filter_etat == 'payee' ? 'selected' : '' ?>>Payée</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                            <input type="date" name="date_debut" value="<?= $filter_date_debut ?>"
                                   class="w-full border-gray-300 rounded-lg p-3">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                            <input type="date" name="date_fin" value="<?= $filter_date_fin ?>"
                                   class="w-full border-gray-300 rounded-lg p-3">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>"
                                   placeholder="Numéro facture ou client..."
                                   class="w-full border-gray-300 rounded-lg p-3">
                        </div>
                        
                        <div class="md:col-span-4 flex justify-end space-x-3 mt-2">
                            <a href="ventes_boutique.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">
                                Réinitialiser
                            </a>
                            <button type="submit" class="px-4 py-2 gradient-green-btn text-white rounded-lg">
                                <i class="fas fa-filter mr-2"></i>Filtrer
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tableau des commandes -->
                <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Liste des commandes</h2>
                    </div>

                    <div class="table-container">
                        <table class="w-full min-w-[800px]">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facture</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">État</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produits</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($commandes)): ?>
                                    <?php foreach ($commandes as $index => $commande): ?>
                                        <tr class="commande-row hover:bg-gray-50 transition-colors fade-in-row"
                                            style="animation-delay: <?= $index * 0.05 ?>s">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-gray-900">
                                                    <?= htmlspecialchars($commande['numero_facture']) ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : '<span class="text-gray-400">Non renseigné</span>' ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="etat-badge <?= $commande['etat'] == 'payee' ? 'status-payee' : 'status-brouillon' ?>">
                                                    <i class="fas fa-<?= $commande['etat'] == 'payee' ? 'check-circle' : 'edit' ?> mr-1"></i>
                                                    <?= $commande['etat'] == 'payee' ? 'Payée' : 'Brouillon' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="font-medium"><?= $commande['nb_produits'] ?? 0 ?></span> produit(s)
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="montant-badge">
                                                    <?= number_format($commande['montant_total'] ?? 0, 2) ?> $
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2 action-buttons">
                                                    <a href="commande_details.php?id=<?= $commande['id'] ?>"
                                                       class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                        <i class="fas fa-eye mr-1"></i>
                                                        Voir
                                                    </a>
                                                    
                                                    <?php if ($commande['etat'] == 'brouillon'): ?>
                                                        <button onclick="changerEtat(<?= $commande['id'] ?>, 'payee')"
                                                                class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                                            <i class="fas fa-check mr-1"></i>
                                                            Payer
                                                        </button>
                                                    <?php else: ?>
                                                        <button onclick="changerEtat(<?= $commande['id'] ?>, 'brouillon')"
                                                                class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700">
                                                            <i class="fas fa-undo mr-1"></i>
                                                            Brouillon
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button onclick="openDeleteModal(<?= $commande['id'] ?>, '<?= htmlspecialchars(addslashes($commande['numero_facture'])) ?>')"
                                                            class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                                                        <i class="fas fa-archive mr-1"></i>
                                                        Archiver
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                                                <p class="text-lg">Aucune commande enregistrée</p>
                                                <p class="text-sm mt-2">Créez votre première vente en utilisant le bouton "Nouvelle vente"</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_commandes) ?></span> sur
                                    <span class="font-medium"><?= $total_commandes ?></span> commandes
                                </div>

                                <div class="flex items-center space-x-2">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>

                                    <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $i == $page ? 'bg-gradient-to-r from-green-600 to-green-700 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>

                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal pour créer/modifier une commande -->
    <div id="commandeModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Nouvelle vente - Boutique NGS</h3>
                <button onclick="closeCommandeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="commandeForm" method="POST" action="">
                <input type="hidden" name="commande_id" id="commandeId">
                
                <div class="space-y-4">
                    <div>
                        <label for="client_nom" class="block text-sm font-medium text-gray-700 mb-1">Nom du client (optionnel)</label>
                        <input type="text" name="client_nom" id="client_nom"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3"
                               placeholder="Ex: Jean Dupont">
                    </div>
                    
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-green-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-green-700 font-medium">Note sur les ventes</p>
                                <p class="text-xs text-green-600 mt-1">
                                    Le numéro de facture sera généré automatiquement au format <strong>FACT-B<?= $boutique_id ?>-XXX</strong>.
                                    Exemple: FACT-B<?= $boutique_id ?>-001, FACT-B<?= $boutique_id ?>-002, etc.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeCommandeModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="ajouter_commande" id="submitButton"
                            class="px-4 py-2 gradient-green-btn text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                        Créer la vente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour modifier une commande -->
    <div id="editModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Modifier la commande</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="editForm" method="POST" action="">
                <input type="hidden" name="commande_id" id="editCommandeId">
                
                <div class="space-y-4">
                    <div>
                        <label for="edit_client_nom" class="block text-sm font-medium text-gray-700 mb-1">Nom du client</label>
                        <input type="text" name="client_nom" id="edit_client_nom"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-green-500 focus:border-green-500 p-3">
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="modifier_commande"
                            class="px-4 py-2 gradient-green-btn text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                        Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour archiver une commande -->
    <div id="deleteModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Confirmation d'archivage</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="text-center py-4">
                <i class="fas fa-archive text-5xl mb-4 text-red-500"></i>
                <p class="text-lg font-bold text-red-700 mb-2">ARCHIVAGE DE COMMANDE</p>
                <p class="text-gray-600 mb-4" id="deleteModalText">
                    Vous êtes sur le point d'archiver cette commande.
                </p>
                <p class="text-sm text-gray-500">
                    La commande sera marquée comme archivée et ne sera plus visible dans les listes normales.
                </p>
            </div>

            <form id="deleteForm" method="POST" action="" class="mt-6 flex justify-center space-x-3">
                <input type="hidden" name="commande_id" id="deleteCommandeId">
                <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                    Annuler
                </button>
                <button type="submit" name="archiver_commande"
                        class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                    Confirmer l'archivage
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal pour nouvelle commande
        const commandeModal = document.getElementById('commandeModal');
        const editModal = document.getElementById('editModal');
        const deleteModal = document.getElementById('deleteModal');

        function openCommandeModal() {
            commandeModal.classList.add('show');
        }

        function closeCommandeModal() {
            commandeModal.classList.remove('show');
        }

        function openEditModal(commandeId) {
            // Charger les données de la commande
            fetch(`ventes_boutique.php?action=get_commande&id=${commandeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editCommandeId').value = commandeId;
                        document.getElementById('edit_client_nom').value = data.commande.client_nom || '';
                        editModal.classList.add('show');
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Impossible de charger les données de la commande.');
                });
        }

        function closeEditModal() {
            editModal.classList.remove('show');
        }

        function openDeleteModal(commandeId, numeroFacture) {
            document.getElementById('deleteCommandeId').value = commandeId;
            document.getElementById('deleteModalText').innerHTML = 
                `Vous êtes sur le point d'archiver la commande <strong>${numeroFacture}</strong>.`;
            deleteModal.classList.add('show');
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
        }

        // Changer l'état d'une commande
        function changerEtat(commandeId, nouvelEtat) {
            if (confirm('Êtes-vous sûr de vouloir changer l\'état de cette commande ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = 'commande_id';
                inputId.value = commandeId;
                
                const inputEtat = document.createElement('input');
                inputEtat.type = 'hidden';
                inputEtat.name = 'nouvel_etat';
                inputEtat.value = nouvelEtat;
                
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'changer_etat';
                inputAction.value = '1';
                
                form.appendChild(inputId);
                form.appendChild(inputEtat);
                form.appendChild(inputAction);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Gestion de l'animation des lignes
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.fade-in-row');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
            });
        });

        // Navigation clavier
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (commandeModal.classList.contains('show')) closeCommandeModal();
                if (editModal.classList.contains('show')) closeEditModal();
                if (deleteModal.classList.contains('show')) closeDeleteModal();
            }
        });

        // Rafraîchissement automatique des statistiques
        function refreshStats() {
            fetch('api/commandes_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Mettre à jour les statistiques si nécessaire
                    console.log('Stats mises à jour:', data);
                })
                .catch(error => console.error('Erreur de rafraîchissement:', error));
        }

        // Rafraîchir toutes les 5 minutes
        setInterval(refreshStats, 300000);
    </script>
</body>
</html>