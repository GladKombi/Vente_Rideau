<?php
# Connexion à la base de données
include '../connexion/connexion.php';

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
$total_mouvements = 0;
$mouvements = [];
$boutique_info = null;
$solde_caisse = 0;

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer les informations de la boutique
try {
    $queryBoutique = $pdo->prepare("SELECT id, nom, email, date_creation, actif FROM boutiques WHERE id = ? AND statut = 0");
    $queryBoutique->execute([$boutique_id]);
    $boutique_info = $queryBoutique->fetch(PDO::FETCH_ASSOC);
    
    if (!$boutique_info) {
        $_SESSION['flash_message'] = [
            'text' => "Boutique introuvable ou supprimée",
            'type' => "error"
        ];
        header('Location: ../login.php');
        exit;
    }
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des informations de la boutique : " . $e->getMessage(),
        'type' => "error"
    ];
    header('Location: ../login.php');
    exit;
}

// --- TRAITEMENT DU FORMULAIRE D'AJOUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        if ($_POST['action'] === 'ajouter_mouvement') {
            try {
                $type_mouvement = $_POST['type_mouvement'] ?? '';
                $montant = (float)($_POST['montant'] ?? 0);
                $motif = $_POST['motif'] ?? '';
                
                // Validation des données
                if (!in_array($type_mouvement, ['entrée', 'sortie'])) {
                    throw new Exception("Type de mouvement invalide");
                }
                
                if ($montant <= 0) {
                    throw new Exception("Le montant doit être supérieur à 0");
                }
                
                if (empty($motif)) {
                    throw new Exception("Le motif est obligatoire");
                }
                
                // Enregistrement du mouvement
                $query = $pdo->prepare("
                    INSERT INTO mouvement_caisse (id_boutique, type_mouvement, montant, motif, date_mouvement, statut)
                    VALUES (?, ?, ?, ?, NOW(), 0)
                ");
                
                $query->execute([$boutique_id, $type_mouvement, $montant, $motif]);
                
                $_SESSION['flash_message'] = [
                    'text' => "Mouvement de caisse enregistré avec succès",
                    'type' => "success"
                ];
                
                header('Location: mouvements.php');
                exit;
                
            } catch (Exception $e) {
                $_SESSION['flash_message'] = [
                    'text' => "Erreur lors de l'enregistrement : " . $e->getMessage(),
                    'type' => "error"
                ];
            }
        }
        
        // --- MODIFICATION D'UN MOUVEMENT ---
        elseif ($_POST['action'] === 'modifier_mouvement') {
            try {
                $mouvement_id = (int)($_POST['mouvement_id'] ?? 0);
                $type_mouvement = $_POST['type_mouvement'] ?? '';
                $montant = (float)($_POST['montant'] ?? 0);
                $motif = $_POST['motif'] ?? '';
                
                // Validation des données
                if ($mouvement_id <= 0) {
                    throw new Exception("ID de mouvement invalide");
                }
                
                if (!in_array($type_mouvement, ['entrée', 'sortie'])) {
                    throw new Exception("Type de mouvement invalide");
                }
                
                if ($montant <= 0) {
                    throw new Exception("Le montant doit être supérieur à 0");
                }
                
                if (empty($motif)) {
                    throw new Exception("Le motif est obligatoire");
                }
                
                // Vérifier que le mouvement appartient à la boutique
                $checkQuery = $pdo->prepare("SELECT id FROM mouvement_caisse WHERE id = ? AND id_boutique = ? AND statut = 0");
                $checkQuery->execute([$mouvement_id, $boutique_id]);
                $mouvement_existe = $checkQuery->fetch();
                
                if (!$mouvement_existe) {
                    throw new Exception("Mouvement introuvable ou ne vous appartient pas");
                }
                
                // Mettre à jour le mouvement
                $query = $pdo->prepare("
                    UPDATE mouvement_caisse 
                    SET type_mouvement = ?, montant = ?, motif = ?
                    WHERE id = ? AND id_boutique = ? AND statut = 0
                ");
                
                $query->execute([$type_mouvement, $montant, $motif, $mouvement_id, $boutique_id]);
                
                $_SESSION['flash_message'] = [
                    'text' => "Mouvement de caisse modifié avec succès",
                    'type' => "success"
                ];
                
                header('Location: mouvements.php');
                exit;
                
            } catch (Exception $e) {
                $_SESSION['flash_message'] = [
                    'text' => "Erreur lors de la modification : " . $e->getMessage(),
                    'type' => "error"
                ];
            }
        }
        
        // --- SUPPRESSION D'UN MOUVEMENT ---
        elseif ($_POST['action'] === 'supprimer_mouvement') {
            try {
                $mouvement_id = (int)($_POST['mouvement_id'] ?? 0);
                
                if ($mouvement_id <= 0) {
                    throw new Exception("ID de mouvement invalide");
                }
                
                // Vérifier que le mouvement appartient à la boutique
                $checkQuery = $pdo->prepare("SELECT id FROM mouvement_caisse WHERE id = ? AND id_boutique = ? AND statut = 0");
                $checkQuery->execute([$mouvement_id, $boutique_id]);
                $mouvement_existe = $checkQuery->fetch();
                
                if (!$mouvement_existe) {
                    throw new Exception("Mouvement introuvable ou ne vous appartient pas");
                }
                
                // Marquer comme supprimé (soft delete)
                $query = $pdo->prepare("
                    UPDATE mouvement_caisse 
                    SET statut = 1
                    WHERE id = ? AND id_boutique = ? AND statut = 0
                ");
                
                $query->execute([$mouvement_id, $boutique_id]);
                
                $_SESSION['flash_message'] = [
                    'text' => "Mouvement de caisse supprimé avec succès",
                    'type' => "success"
                ];
                
                header('Location: mouvements.php');
                exit;
                
            } catch (Exception $e) {
                $_SESSION['flash_message'] = [
                    'text' => "Erreur lors de la suppression : " . $e->getMessage(),
                    'type' => "error"
                ];
            }
        }
    }
}

// --- RÉCUPÉRATION DES FILTRES ---
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Premier jour du mois par défaut
$date_fin = $_GET['date_fin'] ?? date('Y-m-d'); // Aujourd'hui par défaut
$type_filtre = $_GET['type'] ?? 'tous'; // 'tous', 'entrée', 'sortie'
$search_term = $_GET['search'] ?? '';

// Validation des dates
if (!empty($date_debut) && !empty($date_fin)) {
    if (strtotime($date_debut) > strtotime($date_fin)) {
        // Inverser les dates si début > fin
        $temp = $date_debut;
        $date_debut = $date_fin;
        $date_fin = $temp;
    }
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Préparation des conditions de recherche
$conditions = ["mc.id_boutique = ?", "mc.statut = 0"];
$params = [$boutique_id];

// Filtre par période
if (!empty($date_debut) && !empty($date_fin)) {
    $conditions[] = "DATE(mc.date_mouvement) BETWEEN ? AND ?";
    $params[] = $date_debut;
    $params[] = $date_fin;
}

// Filtre par type
if ($type_filtre !== 'tous') {
    $conditions[] = "mc.type_mouvement = ?";
    $params[] = $type_filtre;
}

// Filtre par recherche
if (!empty($search_term)) {
    $conditions[] = "(mc.motif LIKE ?)";
    $params[] = "%$search_term%";
}

// Compter le nombre total de mouvements
try {
    $countQuery = $pdo->prepare("
        SELECT COUNT(*) 
        FROM mouvement_caisse mc
        WHERE " . implode(" AND ", $conditions)
    );
    $countQuery->execute($params);
    $total_mouvements = $countQuery->fetchColumn();
    
    $totalPages = ceil($total_mouvements / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
    
    // Requête paginée
    $sql = "
        SELECT mc.*
        FROM mouvement_caisse mc
        WHERE " . implode(" AND ", $conditions) . "
        ORDER BY mc.date_mouvement DESC
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $mouvements = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le solde de caisse pour la période sélectionnée
    $querySolde = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN mc.type_mouvement = 'entrée' THEN mc.montant ELSE 0 END) AS total_entrees,
            SUM(CASE WHEN mc.type_mouvement = 'sortie' THEN mc.montant ELSE 0 END) AS total_sorties
        FROM mouvement_caisse mc
        WHERE " . implode(" AND ", $conditions)
    );
    $querySolde->execute($params);
    $solde_data = $querySolde->fetch(PDO::FETCH_ASSOC);
    
    $total_entrees = $solde_data['total_entrees'] ?? 0;
    $total_sorties = $solde_data['total_sorties'] ?? 0;
    $solde_caisse = $total_entrees - $total_sorties;
    
    // Statistiques par type pour la période
    $queryStats = $pdo->prepare("
        SELECT 
            mc.type_mouvement,
            COUNT(*) as nombre_mouvements,
            SUM(mc.montant) as montant_total
        FROM mouvement_caisse mc
        WHERE " . implode(" AND ", $conditions) . "
        GROUP BY mc.type_mouvement
        ORDER BY mc.type_mouvement
    ");
    $queryStats->execute($params);
    $stats = $queryStats->fetchAll(PDO::FETCH_ASSOC);
    
    $stats_entrees = [];
    $stats_sorties = [];
    
    foreach ($stats as $stat) {
        if ($stat['type_mouvement'] == 'entrée') {
            $stats_entrees = $stat;
        } else {
            $stats_sorties = $stat;
        }
    }
    
    // Dernier mouvement
    $queryDernierMouvement = $pdo->prepare("
        SELECT mc.*
        FROM mouvement_caisse mc
        WHERE mc.id_boutique = ? AND mc.statut = 0
        ORDER BY mc.date_mouvement DESC
        LIMIT 1
    ");
    $queryDernierMouvement->execute([$boutique_id]);
    $dernier_mouvement = $queryDernierMouvement->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des mouvements : " . $e->getMessage(),
        'type' => "error"
    ];
    $mouvements = [];
    $total_mouvements = 0;
    $totalPages = 1;
    $solde_caisse = 0;
    $total_entrees = 0;
    $total_sorties = 0;
    $stats_entrees = [];
    $stats_sorties = [];
    $dernier_mouvement = null;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Mouvements de Caisse - <?= htmlspecialchars($boutique_info['nom']) ?> - NGS (New Grace Service)</title>
    
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

        .sidebar {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar-header, .sidebar-profile, .sidebar-footer {
            flex-shrink: 0;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }

        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            padding-left: 1.25rem;
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: var(--accent);
            border-radius: 0 4px 4px 0;
        }

        .main-content {
            height: 100vh;
            overflow-y: auto;
        }

        .mobile-menu-btn {
            transition: transform 0.3s ease;
        }

        .mobile-menu-btn.active {
            transform: rotate(90deg);
        }

        .fade-in-row {
            animation: fadeInRow 0.5s ease-out forwards;
            opacity: 0;
        }

        @keyframes fadeInRow {
            to {
                opacity: 1;
            }
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

        .mouvement-row:hover {
            background-color: #f9fafb;
        }

        /* Styles pour les badges de mouvement */
        .badge-mouvement {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-entree {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .badge-sortie {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .montant-entree {
            color: #059669;
            font-weight: 700;
        }

        .montant-sortie {
            color: #dc2626;
            font-weight: 700;
        }

        .solde-positif {
            color: #059669;
        }

        .solde-negatif {
            color: #dc2626;
        }

        .solde-neutre {
            color: #6b7280;
        }

        .date-badge {
            background-color: #f3f4f6;
            color: #374151;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
        }

        .filter-card {
            transition: all 0.3s ease;
        }

        .filter-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .export-btn {
            background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .boutique-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
        }

        /* Styles pour les modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 999;
        }

        .modal-overlay.show {
            display: block;
        }

        .modal-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 1000;
            display: none;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-container.show {
            display: block;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .btn-entree {
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-sortie {
            background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-entree:hover, .btn-sortie:hover {
            opacity: 0.9;
        }

        /* Styles pour le modal de confirmation */
        .confirmation-modal {
            max-width: 400px;
        }

        .confirmation-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .confirmation-warning {
            color: #f59e0b;
        }

        .confirmation-danger {
            color: #dc2626;
        }

        @media (max-width: 768px) {
            .modal-container {
                width: 95%;
                margin: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .nav-link {
                padding: 0.75rem 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="font-inter min-h-screen bg-gray-50">
    <button id="mobileMenuButton" class="mobile-menu-btn md:hidden fixed top-4 left-4 z-50 p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-full shadow-lg hover:shadow-xl transition-shadow">
        <i class="fas fa-bars"></i>
    </button>

    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <div class="flex h-screen">
        <aside id="sidebar" class="sidebar w-64 gradient-bg text-white flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:sticky md:top-0 md:h-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:z-auto">
            <div class="sidebar-header p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center shadow-lg">
                        <span class="font-bold text-white text-lg font-display">NGS</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold">NGS</h1>
                        <p class="text-xs text-gray-300">New Grace Service - Dashboard Boutique</p>
                    </div>
                </div>
            </div>

            <div class="sidebar-profile p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-blue-500/20 border-2 border-blue-500/30 flex items-center justify-center relative">
                        <i class="fas fa-store text-blue-500 text-lg"></i>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-900"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold truncate"><?= htmlspecialchars($boutique_info['nom']) ?></h3>
                        <p class="text-sm text-gray-300 truncate"><?= htmlspecialchars($boutique_info['email'] ?? '') ?></p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav p-4 space-y-1">
                <a href="dashboard_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="stock_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-warehouse w-5 text-gray-300"></i>
                    <span>Mes stocks</span>
                </a>
                <a href="ventes_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-shopping-cart w-5 text-gray-300"></i>
                    <span>Ventes</span>
                </a>
                <a href="paiements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-money-bill-wave w-5 text-gray-300"></i>
                    <span>Paiements</span>
                </a>
                <a href="mouvements.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-exchange-alt w-5 text-white"></i>
                    <span>Mouvements Caisse</span>
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
            <header class="boutique-header p-4 md:p-6 sticky top-0 z-30 shadow-lg">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center space-x-3">
                            <div>
                                <h1 class="text-xl md:text-2xl font-bold text-white">Mouvements de Caisse - <?= htmlspecialchars($boutique_info['nom']) ?></h1>
                                <p class="text-gray-200 text-sm md:text-base">New Grace Service - Gestion des entrées/sorties de caisse</p>
                            </div>
                        </div>
                        
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <div class="flex items-center space-x-2 text-sm text-gray-200">
                                <i class="fas fa-calculator"></i>
                                <span>Solde période: <strong class="<?= $solde_caisse > 0 ? 'solde-positif' : ($solde_caisse < 0 ? 'solde-negatif' : 'solde-neutre') ?>"><?= number_format($solde_caisse, 2) ?> $</strong></span>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-gray-200">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Période: <?= date('d/m/Y', strtotime($date_debut)) ?> - <?= date('d/m/Y', strtotime($date_fin)) ?></span>
                            </div>
                            <?php if ($dernier_mouvement): ?>
                            <div class="flex items-center space-x-2 text-sm text-gray-200">
                                <i class="fas fa-history"></i>
                                <span>Dernier: <?= date('d/m/Y H:i', strtotime($dernier_mouvement['date_mouvement'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <button onclick="openAjoutModal()" 
                                class="px-4 py-2 bg-white/20 text-white rounded-lg hover:bg-white/30 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-plus-circle"></i>
                            <span>Nouveau Mouvement</span>
                        </button>
                        <button onclick="exportMouvements()" 
                                class="px-4 py-2 bg-white/20 text-white rounded-lg hover:bg-white/30 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-file-export"></i>
                            <span>Exporter</span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 md:p-6">
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

                <!-- Statistiques principales -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8 stats-grid">
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-emerald-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-sign-in-alt text-emerald-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-emerald-600">Entrées</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2 montant-entree"><?= number_format($total_entrees, 2) ?> $</h3>
                        <p class="text-gray-600"><?= $stats_entrees['nombre_mouvements'] ?? 0 ?> mouvement(s)</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-red-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                                <i class="fas fa-sign-out-alt text-red-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-red-600">Sorties</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2 montant-sortie"><?= number_format($total_sorties, 2) ?> $</h3>
                        <p class="text-gray-600"><?= $stats_sorties['nombre_mouvements'] ?? 0 ?> mouvement(s)</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 <?= $solde_caisse >= 0 ? 'border-green-500' : 'border-red-500' ?> animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl <?= $solde_caisse >= 0 ? 'bg-green-100' : 'bg-red-100' ?> flex items-center justify-center">
                                <i class="<?= $solde_caisse >= 0 ? 'fas fa-check-circle text-green-600' : 'fas fa-exclamation-circle text-red-600' ?> text-xl"></i>
                            </div>
                            <span class="text-sm font-medium <?= $solde_caisse >= 0 ? 'text-green-600' : 'text-red-600' ?>">Solde</span>
                        </div>
                        <h3 class="text-3xl font-bold mb-2 <?= $solde_caisse > 0 ? 'solde-positif' : ($solde_caisse < 0 ? 'solde-negatif' : 'solde-neutre') ?>">
                            <?= number_format($solde_caisse, 2) ?> $
                        </h3>
                        <p class="text-gray-600">Pour la période sélectionnée</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_mouvements ?></h3>
                        <p class="text-gray-600">Mouvements</p>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in filter-card">
                    <form method="GET" action="mouvements.php" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 filters-grid">
                            <!-- Date début -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt mr-2"></i>Date début
                                </label>
                                <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                            </div>
                            
                            <!-- Date fin -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-calendar-alt mr-2"></i>Date fin
                                </label>
                                <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                            </div>
                            
                            <!-- Type de mouvement -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-filter mr-2"></i>Type
                                </label>
                                <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                    <option value="tous" <?= $type_filtre === 'tous' ? 'selected' : '' ?>>Tous les mouvements</option>
                                    <option value="entrée" <?= $type_filtre === 'entrée' ? 'selected' : '' ?>>Entrées uniquement</option>
                                    <option value="sortie" <?= $type_filtre === 'sortie' ? 'selected' : '' ?>>Sorties uniquement</option>
                                </select>
                            </div>
                            
                            <!-- Recherche par motif -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-search mr-2"></i>Recherche
                                </label>
                                <div class="relative">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" 
                                           placeholder="Rechercher par motif..."
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-4 border-t border-gray-100">
                            <div class="text-sm text-gray-600">
                                <i class="fas fa-info-circle mr-2"></i>
                                <?= $total_mouvements ?> résultat(s) trouvé(s)
                            </div>
                            <div class="flex items-center space-x-3">
                                <button type="button" onclick="resetFilters()" 
                                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-redo mr-2"></i>Réinitialiser
                                </button>
                                <button type="submit" 
                                        class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:opacity-90 transition-all">
                                    <i class="fas fa-filter mr-2"></i>Appliquer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Liste des mouvements -->
                <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.4s">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 mb-2 md:mb-0">
                                Historique des mouvements
                            </h2>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">
                                    Page <?= $page ?> sur <?= $totalPages ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="w-full min-w-[800px]" id="mouvementsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Motif</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (!empty($mouvements)): ?>
                                    <?php foreach ($mouvements as $index => $mouvement): ?>
                                        <?php
                                        $type_class = $mouvement['type_mouvement'] == 'entrée' ? 'badge-entree' : 'badge-sortie';
                                        $montant_class = $mouvement['type_mouvement'] == 'entrée' ? 'montant-entree' : 'montant-sortie';
                                        $montant_prefix = $mouvement['type_mouvement'] == 'entrée' ? '+' : '-';
                                        ?>
                                        <tr class="mouvement-row hover:bg-gray-50 transition-colors fade-in-row"
                                            data-mouvement-id="<?= $mouvement['id'] ?>"
                                            data-type-mouvement="<?= htmlspecialchars($mouvement['type_mouvement']) ?>"
                                            data-montant="<?= $mouvement['montant'] ?>"
                                            data-motif="<?= htmlspecialchars($mouvement['motif']) ?>"
                                            style="animation-delay: <?= $index * 0.05 ?>s">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <span class="font-mono font-bold">#<?= htmlspecialchars($mouvement['id']) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex flex-col">
                                                    <span class="font-medium"><?= date('d/m/Y', strtotime($mouvement['date_mouvement'])) ?></span>
                                                    <span class="text-xs text-gray-500"><?= date('H:i', strtotime($mouvement['date_mouvement'])) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="badge-mouvement <?= $type_class ?>">
                                                    <i class="fas fa-<?= $mouvement['type_mouvement'] == 'entrée' ? 'sign-in-alt' : 'sign-out-alt' ?> mr-2"></i>
                                                    <?= htmlspecialchars(ucfirst($mouvement['type_mouvement'])) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="<?= $montant_class ?> font-bold text-lg">
                                                    <?= $montant_prefix ?><?= number_format($mouvement['montant'], 2) ?> $
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900">
                                                <?= htmlspecialchars($mouvement['motif']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                
                                                <button onclick="editerMouvement(<?= $mouvement['id'] ?>, '<?= $mouvement['type_mouvement'] ?>', <?= $mouvement['montant'] ?>, '<?= htmlspecialchars($mouvement['motif']) ?>')" 
                                                        class="action-btn px-3 py-1 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition-colors mr-2"
                                                        title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="confirmerSuppression(<?= $mouvement['id'] ?>)" 
                                                        class="action-btn px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-exchange-alt text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun mouvement de caisse enregistré</p>
                                                <p class="text-sm mt-2">
                                                    Aucun mouvement de caisse n'a été enregistré pour la période sélectionnée.
                                                </p>
                                                <button onclick="openAjoutModal()" class="mt-4 px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:opacity-90 transition-all">
                                                    <i class="fas fa-plus-circle mr-2"></i>Ajouter un mouvement
                                                </button>
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
                                <div class="text-sm text-gray-700 hidden sm:block">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_mouvements) ?></span> sur
                                    <span class="font-medium"><?= $total_mouvements ?></span> mouvements
                                </div>

                                <div class="flex items-center space-x-2 mx-auto sm:mx-0">
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>

                                    <?php
                                    $startPage = max(1, $page - 1);
                                    $endPage = min($totalPages, $page + 1);

                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isActive = $i == $page;
                                    ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $isActive ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php } ?>

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

    <!-- Modal pour ajouter un mouvement -->
    <div id="modalAjout" class="modal-overlay"></div>
    <div id="modalAjoutContent" class="modal-container">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-plus-circle mr-2 text-blue-600"></i>
                    Nouveau mouvement de caisse
                </h3>
                <button onclick="closeAjoutModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form method="POST" action="mouvements.php" id="formAjoutMouvement">
                <input type="hidden" name="action" value="ajouter_mouvement">
                
                <div class="space-y-4 mb-6">
                    <!-- Type de mouvement -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Type de mouvement <span class="text-red-500">*</span>
                        </label>
                        <div class="flex space-x-4">
                            <label class="flex-1">
                                <input type="radio" name="type_mouvement" value="entrée" checked 
                                       class="hidden peer" onchange="toggleMontantPrefix('ajout')">
                                <div class="btn-entree py-3 px-4 rounded-lg text-center cursor-pointer peer-checked:ring-2 peer-checked:ring-green-500 transition-all">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Entrée
                                </div>
                            </label>
                            <label class="flex-1">
                                <input type="radio" name="type_mouvement" value="sortie" 
                                       class="hidden peer" onchange="toggleMontantPrefix('ajout')">
                                <div class="btn-sortie py-3 px-4 rounded-lg text-center cursor-pointer peer-checked:ring-2 peer-checked:ring-red-500 transition-all">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Sortie
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Montant -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Montant ($) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span id="montantPrefixAjout" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-green-600 font-bold">+</span>
                            <input type="number" name="montant" step="0.01" min="0.01" required
                                   placeholder="0.00"
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        </div>
                    </div>
                    
                    <!-- Motif -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Motif <span class="text-red-500">*</span>
                        </label>
                        <textarea name="motif" rows="3" required
                                  placeholder="Décrivez la raison de ce mouvement de caisse..."
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all resize-none"></textarea>
                    </div>
                </div>
                
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeAjoutModal()" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:opacity-90 transition-all">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour modifier un mouvement -->
    <div id="modalModifier" class="modal-overlay"></div>
    <div id="modalModifierContent" class="modal-container">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-gray-900">
                    <i class="fas fa-edit mr-2 text-yellow-600"></i>
                    Modifier le mouvement
                </h3>
                <button onclick="closeModifierModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <form method="POST" action="mouvements.php" id="formModifierMouvement">
                <input type="hidden" name="action" value="modifier_mouvement">
                <input type="hidden" name="mouvement_id" id="mouvement_id_modifier">
                
                <div class="space-y-4 mb-6">
                    <!-- Type de mouvement -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Type de mouvement <span class="text-red-500">*</span>
                        </label>
                        <div class="flex space-x-4">
                            <label class="flex-1">
                                <input type="radio" name="type_mouvement" value="entrée" 
                                       id="type_entree_modifier"
                                       class="hidden peer" onchange="toggleMontantPrefix('modifier')">
                                <div class="btn-entree py-3 px-4 rounded-lg text-center cursor-pointer peer-checked:ring-2 peer-checked:ring-green-500 transition-all">
                                    <i class="fas fa-sign-in-alt mr-2"></i>Entrée
                                </div>
                            </label>
                            <label class="flex-1">
                                <input type="radio" name="type_mouvement" value="sortie" 
                                       id="type_sortie_modifier"
                                       class="hidden peer" onchange="toggleMontantPrefix('modifier')">
                                <div class="btn-sortie py-3 px-4 rounded-lg text-center cursor-pointer peer-checked:ring-2 peer-checked:ring-red-500 transition-all">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Sortie
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Montant -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Montant ($) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span id="montantPrefixModifier" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-green-600 font-bold">+</span>
                            <input type="number" name="montant" id="montant_modifier" step="0.01" min="0.01" required
                                   placeholder="0.00"
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                        </div>
                    </div>
                    
                    <!-- Motif -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Motif <span class="text-red-500">*</span>
                        </label>
                        <textarea name="motif" id="motif_modifier" rows="3" required
                                  placeholder="Décrivez la raison de ce mouvement de caisse..."
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all resize-none"></textarea>
                    </div>
                </div>
                
                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModifierModal()" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-gradient-to-r from-yellow-600 to-orange-600 text-white rounded-lg hover:opacity-90 transition-all">
                        <i class="fas fa-save mr-2"></i>Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="modalConfirmation" class="modal-overlay"></div>
    <div id="modalConfirmationContent" class="modal-container confirmation-modal">
        <div class="p-6 text-center">
            <div class="confirmation-icon confirmation-danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h3 class="text-xl font-bold text-gray-900 mb-4">Confirmer la suppression</h3>
            
            <p class="text-gray-600 mb-6">
                Êtes-vous sûr de vouloir supprimer ce mouvement de caisse ?<br>
                <span class="font-medium">Cette action est irréversible.</span>
            </p>
            
            <form method="POST" action="mouvements.php" id="formSuppressionMouvement">
                <input type="hidden" name="action" value="supprimer_mouvement">
                <input type="hidden" name="mouvement_id" id="mouvement_id_suppression">
                
                <div class="flex items-center justify-center space-x-3 pt-4">
                    <button type="button" onclick="closeConfirmationModal()" 
                            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 transition-all">
                        <i class="fas fa-trash mr-2"></i>Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- GESTION DE LA SIDEBAR MOBILE ---
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
            mobileMenuButton.classList.toggle('active');
        }

        mobileMenuButton.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // --- GESTION DES LIENS ACTIFS ---
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // --- GESTION DES MODALS ---
        // Modal d'ajout
        function openAjoutModal() {
            document.getElementById('modalAjout').classList.add('show');
            document.getElementById('modalAjoutContent').classList.add('show');
            // Réinitialiser le formulaire
            document.getElementById('formAjoutMouvement').reset();
            toggleMontantPrefix('ajout');
        }

        function closeAjoutModal() {
            document.getElementById('modalAjout').classList.remove('show');
            document.getElementById('modalAjoutContent').classList.remove('show');
        }

        // Modal de modification
        let mouvementEnCoursEdition = null;

        function editerMouvement(id, type, montant, motif) {
            mouvementEnCoursEdition = id;
            
            // Remplir le formulaire avec les données du mouvement
            document.getElementById('mouvement_id_modifier').value = id;
            document.getElementById('montant_modifier').value = montant;
            document.getElementById('motif_modifier').value = motif;
            
            // Sélectionner le bon type de mouvement
            if (type === 'entrée') {
                document.getElementById('type_entree_modifier').checked = true;
            } else {
                document.getElementById('type_sortie_modifier').checked = true;
            }
            
            // Mettre à jour le préfixe du montant
            toggleMontantPrefix('modifier');
            
            // Ouvrir le modal
            document.getElementById('modalModifier').classList.add('show');
            document.getElementById('modalModifierContent').classList.add('show');
        }

        function closeModifierModal() {
            document.getElementById('modalModifier').classList.remove('show');
            document.getElementById('modalModifierContent').classList.remove('show');
            mouvementEnCoursEdition = null;
        }

        // Modal de confirmation de suppression - UNIQUEMENT CETTE CONFIRMATION
        function confirmerSuppression(id) {
            document.getElementById('mouvement_id_suppression').value = id;
            document.getElementById('modalConfirmation').classList.add('show');
            document.getElementById('modalConfirmationContent').classList.add('show');
        }

        function closeConfirmationModal() {
            document.getElementById('modalConfirmation').classList.remove('show');
            document.getElementById('modalConfirmationContent').classList.remove('show');
        }

        // Fermer les modals en cliquant à l'extérieur
        document.getElementById('modalAjout').addEventListener('click', function(e) {
            if (e.target === this) closeAjoutModal();
        });

        document.getElementById('modalModifier').addEventListener('click', function(e) {
            if (e.target === this) closeModifierModal();
        });

        document.getElementById('modalConfirmation').addEventListener('click', function(e) {
            if (e.target === this) closeConfirmationModal();
        });

        // Gérer le préfixe du montant selon le type
        function toggleMontantPrefix(type) {
            const prefixId = type === 'ajout' ? 'montantPrefixAjout' : 'montantPrefixModifier';
            const typeInputName = type === 'ajout' ? 'type_mouvement' : 'type_mouvement';
            const formType = type === 'ajout' ? 'ajout' : 'modifier';
            
            const typeEntree = document.querySelector(`input[name="${typeInputName}"][value="entrée"]`).checked;
            const prefix = document.getElementById(prefixId);
            
            if (typeEntree) {
                prefix.textContent = '+';
                prefix.className = 'absolute left-3 top-1/2 transform -translate-y-1/2 text-green-600 font-bold';
            } else {
                prefix.textContent = '-';
                prefix.className = 'absolute left-3 top-1/2 transform -translate-y-1/2 text-red-600 font-bold';
            }
        }

        // --- FONCTIONNALITÉS DES MOUVEMENTS ---
        function voirMouvement(id) {
            // À implémenter : afficher les détails du mouvement
            // Pour l'instant, on redirige vers la page avec un focus
            window.location.hash = 'mouvement-' + id;
        }

        // --- EXPORT DES DONNÉES ---
        function exportMouvements() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            // Créer un formulaire pour l'export
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = 'export_mouvements.php';
            
            // Ajouter tous les paramètres de filtre
            for (const [key, value] of params.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            // Ajouter le formulaire à la page et le soumettre
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // --- RÉINITIALISATION DES FILTRES ---
        function resetFilters() {
            window.location.href = 'mouvements.php';
        }

        // --- ANIMATION DES LIGNES ---
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.fade-in-row');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
            });
            
            // Initialiser le préfixe du montant
            toggleMontantPrefix('ajout');
            toggleMontantPrefix('modifier');
        });

        // --- VALIDATION DES FORMULAIRES ---
        document.getElementById('formAjoutMouvement').addEventListener('submit', function(e) {
            const montant = this.querySelector('input[name="montant"]').value;
            const motif = this.querySelector('textarea[name="motif"]').value;
            
            if (parseFloat(montant) <= 0) {
                e.preventDefault();
                alert('Le montant doit être supérieur à 0');
                return false;
            }
            
            if (motif.trim() === '') {
                e.preventDefault();
                alert('Le motif est obligatoire');
                return false;
            }
            
            return true;
        });

        document.getElementById('formModifierMouvement').addEventListener('submit', function(e) {
            const montant = this.querySelector('input[name="montant"]').value;
            const motif = this.querySelector('textarea[name="motif"]').value;
            
            if (parseFloat(montant) <= 0) {
                e.preventDefault();
                alert('Le montant doit être supérieur à 0');
                return false;
            }
            
            if (motif.trim() === '') {
                e.preventDefault();
                alert('Le motif est obligatoire');
                return false;
            }
            
            return true;
        });

        // SUPPRESSION DU DOUBLE CONFIRMATION - SEULEMENT LE MODAL
        document.getElementById('formSuppressionMouvement').addEventListener('submit', function(e) {
            // La suppression se fait directement sans confirmation supplémentaire
            return true;
        });

        // --- RECHERCHE EN TEMPS RÉEL ---
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(() => {
                    // Soumettre le formulaire après un délai
                    this.form.submit();
                }, 500);
            });
        }

        // --- GESTION DES TOUCHES CLAVIER ---
        document.addEventListener('keydown', function(e) {
            // Échappe pour fermer les modals
            if (e.key === 'Escape') {
                closeAjoutModal();
                closeModifierModal();
                closeConfirmationModal();
                
                if (!sidebar.classList.contains('-translate-x-full')) {
                    toggleSidebar();
                }
            }
        });
    </script>
</body>
</html>