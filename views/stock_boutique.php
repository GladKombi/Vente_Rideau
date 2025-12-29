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
$total_stocks = 0;
$stocks = [];
$boutique_info = null;

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

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Compter le nombre total de stocks pour cette boutique
try {
    $countQuery = $pdo->prepare("
        SELECT COUNT(*) 
        FROM stock s 
        WHERE s.boutique_id = ? 
          AND s.statut = 0
          AND s.quantite > 0
    ");
    $countQuery->execute([$boutique_id]);
    $total_stocks = $countQuery->fetchColumn();
    $totalPages = ceil($total_stocks / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

    // Requête paginée avec jointures - CORRIGÉE
    $query = $pdo->prepare("
        SELECT s.*, 
               p.designation as produit_designation,
               p.umProduit as produit_unite,
               p.description as produit_description
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE s.boutique_id = :boutique_id 
          AND s.statut = 0
          AND s.quantite > 0
        ORDER BY 
            CASE 
                WHEN s.quantite <= s.seuil_alerte_stock THEN 1
                ELSE 2
            END,
            s.date_creation DESC
        LIMIT :limit OFFSET :offset
    ");
    $query->bindValue(':boutique_id', $boutique_id, PDO::PARAM_INT);
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $stocks = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques pour cette boutique
    $queryStats = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.produit_matricule) as produits_differents,
            SUM(s.quantite) as quantite_totale,
            SUM(s.quantite * s.prix) as valeur_totale,
            COUNT(CASE WHEN s.quantite <= s.seuil_alerte_stock THEN 1 END) as produits_faible_stock
        FROM stock s 
        WHERE s.boutique_id = ? 
          AND s.statut = 0
          AND s.quantite > 0
    ");
    $queryStats->execute([$boutique_id]);
    $stats = $queryStats->fetch(PDO::FETCH_ASSOC);
    
    $produits_differents = $stats['produits_differents'] ?? 0;
    $quantite_totale = $stats['quantite_totale'] ?? 0;
    $valeur_totale = $stats['valeur_totale'] ?? 0;
    $produits_faible_stock = $stats['produits_faible_stock'] ?? 0;
    
    // Statistiques par type d'unité
    $queryStatsUnite = $pdo->prepare("
        SELECT 
            p.umProduit,
            COUNT(DISTINCT s.produit_matricule) as nombre_produits,
            SUM(s.quantite) as quantite_totale,
            SUM(s.quantite * s.prix) as valeur_totale
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE s.boutique_id = ? 
          AND s.statut = 0
          AND s.quantite > 0
        GROUP BY p.umProduit
    ");
    $queryStatsUnite->execute([$boutique_id]);
    $statsUnite = $queryStatsUnite->fetchAll(PDO::FETCH_ASSOC);
    
    $stats_metres = [];
    $stats_pieces = [];
    
    foreach ($statsUnite as $stat) {
        if ($stat['umProduit'] == 'metres') {
            $stats_metres = $stat;
        } else {
            $stats_pieces = $stat;
        }
    }

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des stocks: " . $e->getMessage(),
        'type' => "error"
    ];
    $produits_differents = 0;
    $quantite_totale = 0;
    $valeur_totale = 0;
    $produits_faible_stock = 0;
    $stocks = [];
    $total_stocks = 0;
    $totalPages = 1;
    $stats_metres = [];
    $stats_pieces = [];
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Stocks - <?= htmlspecialchars($boutique_info['nom']) ?> - NGS (New Grace Service)</title>
    
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

        .gradient-blue-btn {
            background: linear-gradient(90deg, #4F86F7 0%, #1A5A9C 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-blue-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
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

        .status-active {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-inactive {
            background-color: #FEE2E2;
            color: #991B1B;
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

        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            transition: background 0.3s ease;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) rgba(255, 255, 255, 0.05);
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

        html {
            scroll-behavior: smooth;
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

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(50%, -50%);
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .table-container {
            overflow-x: auto;
        }

        .stock-row:hover {
            background-color: #f9fafb;
        }

        .seuil-alerte {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .seuil-faible {
            background-color: #fef3c7;
            color: #92400e;
        }

        .seuil-ok {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-mouvement {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-approvisionnement {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-transfert {
            background-color: #f3e8ff;
            color: #7c3aed;
        }

        .prix-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .valeur-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background-color: #dcfce7;
            color: #166534;
        }
        
        /* Styles pour les badges d'unité */
        .badge-unite {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-metres {
            background-color: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
        }
        
        .badge-pieces {
            background-color: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }
        
        .badge-rideau {
            background-color: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }
        
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
        
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
        }
        
        .info-box p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }

        .boutique-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
            
            .nav-link {
                padding: 0.75rem 1rem;
            }
            
            .stats-grid {
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
                <a href="dashboard_boutique.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-chart-line w-5 text-white"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="stock_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-warehouse w-5 text-gray-300"></i>
                    <span>Mes stocks</span>
                    <?php if ($total_stocks > 0): ?>
                        <span class="notification-badge"><?= $total_stocks ?></span>
                    <?php endif; ?>
                </a>
                <a href="ventes_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-shopping-cart w-5 text-gray-300"></i>
                    <span>Ventes</span>
                </a>
                <a href="paiements.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-money-bill-wave w-5"></i>
                    <span>Paiements</span>
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
                                <h1 class="text-xl md:text-2xl font-bold text-white">Mes stocks - <?= htmlspecialchars($boutique_info['nom']) ?></h1>
                                <p class="text-gray-200 text-sm md:text-base">New Grace Service - Gestion des stocks de votre boutique</p>
                            </div>
                        </div>
                        
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <div class="flex items-center space-x-2 text-sm text-gray-200">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($boutique_info['email'] ?? 'Non spécifié') ?></span>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-gray-200">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Créée le <?= date('d/m/Y', strtotime($boutique_info['date_creation'])) ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="status-badge <?= $boutique_info['actif'] ? 'status-active' : 'status-inactive' ?>">
                                    <i class="fas fa-<?= $boutique_info['actif'] ? 'check-circle' : 'times-circle' ?> mr-1"></i>
                                    <?= $boutique_info['actif'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <button onclick="refreshPage()" 
                                class="px-4 py-2 bg-white/20 text-white rounded-lg hover:bg-white/30 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-sync-alt"></i>
                            <span>Actualiser</span>
                        </button>
                        <?php if ($produits_faible_stock > 0): ?>
                            <div class="relative">
                                <span class="px-4 py-2 bg-yellow-500 text-white rounded-lg flex items-center space-x-2 shadow-md">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span><?= $produits_faible_stock ?> alerte(s)</span>
                                </span>
                            </div>
                        <?php endif; ?>
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

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8 stats-grid">
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_stocks ?></h3>
                        <p class="text-gray-600">Stocks enregistrés</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-emerald-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-box-open text-emerald-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-emerald-600">Produits</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $produits_differents ?></h3>
                        <p class="text-gray-600">Types de produits</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-cyan-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-cyan-100 flex items-center justify-center">
                                <i class="fas fa-weight-hanging text-cyan-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-cyan-600">Quantité totale</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($quantite_totale, 3) ?></h3>
                        <p class="text-gray-600">Unités en stock</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Valeur stock</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($valeur_totale, 2) ?> $</h3>
                        <p class="text-gray-600">Valeur totale</p>
                    </div>
                </div>

                <!-- Statistiques détaillées par type d'unité -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 animate-fade-in" style="animation-delay: 0.4s">
                    <!-- Statistiques pour les mètres (rideaux) -->
                    <?php if (!empty($stats_metres)): ?>
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-ruler-combined text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900">Rideaux (mètres)</h3>
                                    <p class="text-sm text-gray-600">Statistiques pour les produits vendus au mètre</p>
                                </div>
                            </div>
                            <span class="badge-unite badge-metres">
                                <i class="fas fa-ruler-combined mr-1"></i>
                                Mètres
                            </span>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Nombre de produits:</span>
                                <span class="font-bold"><?= $stats_metres['nombre_produits'] ?? 0 ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Quantité totale:</span>
                                <span class="font-bold"><?= number_format($stats_metres['quantite_totale'] ?? 0, 3) ?> m</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Valeur totale:</span>
                                <span class="font-bold text-green-600"><?= number_format($stats_metres['valeur_totale'] ?? 0, 2) ?> $</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Statistiques pour les pièces -->
                    <?php if (!empty($stats_pieces)): ?>
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                                    <i class="fas fa-cube text-emerald-600"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900">Produits (pièces)</h3>
                                    <p class="text-sm text-gray-600">Statistiques pour les produits vendus à la pièce</p>
                                </div>
                            </div>
                            <span class="badge-unite badge-pieces">
                                <i class="fas fa-cube mr-1"></i>
                                Pièces
                            </span>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Nombre de produits:</span>
                                <span class="font-bold"><?= $stats_pieces['nombre_produits'] ?? 0 ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Quantité totale:</span>
                                <span class="font-bold"><?= number_format($stats_pieces['quantite_totale'] ?? 0, 3) ?> pce</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Valeur totale:</span>
                                <span class="font-bold text-green-600"><?= number_format($stats_pieces['valeur_totale'] ?? 0, 2) ?> $</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Alertes stock faible -->
                <?php if ($produits_faible_stock > 0): ?>
                    <div class="mb-6 animate-fade-in" style="animation-delay: 0.5s">
                        <div class="bg-yellow-50 border border-yellow-200 rounded-2xl p-4 md:p-6">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-exclamation-triangle text-yellow-600 text-xl mt-1"></i>
                                <div class="flex-1">
                                    <h3 class="font-bold text-yellow-800 mb-2">Alertes stock faible</h3>
                                    <p class="text-yellow-700 mb-3">
                                        <span class="font-bold"><?= $produits_faible_stock ?></span> produit(s) sont en dessous du seuil d'alerte.
                                        Pensez à réapprovisionner ces produits pour éviter les ruptures de stock.
                                    </p>
                                    <div class="flex items-center space-x-2 text-sm text-yellow-600">
                                        <i class="fas fa-lightbulb"></i>
                                        <span>Les produits avec stock faible sont affichés en premier dans le tableau ci-dessous.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in" style="animation-delay: 0.6s">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="relative flex-1 max-w-lg">
                            <input type="text"
                                id="searchInput"
                                placeholder="Rechercher par produit, matricule ou mouvement..."
                                class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-secondary focus:border-secondary transition-all shadow-sm">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-sm text-gray-600 hidden md:flex items-center space-x-2">
                                <i class="fas fa-info-circle text-blue-500"></i>
                                <span>Page <?= $page ?> sur <?= $totalPages ?></span>
                            </div>
                            <button onclick="refreshPage()" class="p-2 text-gray-600 hover:text-blue-600 transition-colors" title="Actualiser">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.7s">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 mb-2 md:mb-0">
                                Détail des stocks - <?= htmlspecialchars($boutique_info['nom']) ?>
                            </h2>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">
                                    <?= $produits_faible_stock ?> produit(s) en stock faible
                                </span>
                                <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="w-full min-w-[1000px]" id="stocksTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type mouvement</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seuil d'alerte</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php if (!empty($stocks)): ?>
                                    <?php foreach ($stocks as $index => $stock): ?>
                                        <?php 
                                        // Déterminer la classe du seuil d'alerte
                                        $seuilClass = $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'seuil-faible' : 'seuil-ok';
                                        $seuilText = $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'Faible' : 'OK';
                                        
                                        // Déterminer le type de produit
                                        $uniteClass = $stock['produit_unite'] == 'metres' ? 'badge-metres' : 'badge-pieces';
                                        $uniteText = $stock['produit_unite'] == 'metres' ? 'mètres' : 'pièces';
                                        $uniteIcon = $stock['produit_unite'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube';
                                        
                                        // Déterminer le type de mouvement
                                        $mouvementClass = $stock['type_mouvement'] == 'transfert' ? 'badge-transfert' : 'badge-approvisionnement';
                                        $mouvementText = $stock['type_mouvement'] == 'transfert' ? 'Transfert' : 'Approvisionnement';
                                        $mouvementIcon = $stock['type_mouvement'] == 'transfert' ? 'fas fa-exchange-alt' : 'fas fa-truck-loading';
                                        
                                        // Calculer la valeur
                                        $valeur = $stock['quantite'] * $stock['prix'];
                                        ?>
                                        <tr class="stock-row hover:bg-gray-50 transition-colors fade-in-row <?= $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'bg-yellow-50' : '' ?>"
                                            data-stock-id="<?= htmlspecialchars($stock['id']) ?>"
                                            data-produit-designation="<?= htmlspecialchars(strtolower($stock['produit_designation'])) ?>"
                                            data-produit-matricule="<?= htmlspecialchars(strtolower($stock['produit_matricule'])) ?>"
                                            data-type-mouvement="<?= htmlspecialchars(strtolower($stock['type_mouvement'])) ?>"
                                            data-unite="<?= htmlspecialchars(strtolower($stock['produit_unite'])) ?>"
                                            style="animation-delay: <?= $index * 0.05 ?>s">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-mono font-bold">#<?= htmlspecialchars($stock['id']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div>
                                                    <div class="flex items-center">
                                                        <span class="font-medium"><?= htmlspecialchars($stock['produit_designation']) ?></span>
                                                        <span class="badge-unite ml-2 <?= $uniteClass ?>">
                                                            <i class="<?= $uniteIcon ?> mr-1 text-xs"></i>
                                                            <?= $uniteText ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 font-mono mt-1">
                                                        <?= htmlspecialchars($stock['produit_matricule']) ?>
                                                    </div>
                                                    <?php if ($stock['produit_description']): ?>
                                                        <div class="text-xs text-gray-600 mt-1">
                                                            <?= htmlspecialchars(substr($stock['produit_description'], 0, 50)) ?>
                                                            <?php if (strlen($stock['produit_description']) > 50): ?>...<?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="badge-mouvement <?= $mouvementClass ?>">
                                                    <i class="<?= $mouvementIcon ?> mr-1"></i>
                                                    <?= $mouvementText ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-bold <?= $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'text-yellow-700' : '' ?>">
                                                        <?= number_format($stock['quantite'], 3) ?>
                                                    </span>
                                                    <span class="text-xs text-gray-500 ml-1">
                                                        <?= $uniteText ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="prix-badge">
                                                        <i class="fas fa-tag mr-1"></i>
                                                        <?= number_format($stock['prix'], 2) ?> $
                                                    </span>
                                                    <span class="text-xs text-gray-500 ml-1">
                                                        /<?= $uniteText ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="valeur-badge">
                                                        <i class="fas fa-dollar-sign mr-1"></i>
                                                        <?= number_format($valeur, 2) ?> $
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="seuil-alerte <?= $seuilClass ?>">
                                                        <i class="fas fa-<?= $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'exclamation-triangle' : 'check-circle' ?> mr-1"></i>
                                                        <?= $seuilText ?> (<?= $stock['seuil_alerte_stock'] ?>)
                                                    </span>
                                                    <span class="text-xs text-gray-500 ml-1">
                                                        <?= $uniteText ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d/m/Y H:i', strtotime($stock['date_creation'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-8 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun stock enregistré pour votre boutique</p>
                                                <p class="text-sm mt-2">
                                                    Votre boutique ne possède actuellement aucun produit en stock.
                                                    Contactez l'administration pour ajouter des produits à votre stock.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="noResults" class="hidden text-center py-12">
                        <div class="bg-gray-50 rounded-2xl p-8 max-w-md mx-auto shadow-soft">
                            <i class="fas fa-search text-6xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun résultat trouvé</h3>
                            <p class="text-gray-600">Aucun stock ne correspond à votre recherche</p>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 hidden sm:block">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_stocks) ?></span> sur
                                    <span class="font-medium"><?= $total_stocks ?></span> stocks
                                </div>

                                <div class="flex items-center space-x-2 mx-auto sm:mx-0">
                                    <a href="?page=<?= max(1, $page - 1) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>

                                    <?php
                                    $startPage = max(1, $page - 1);
                                    $endPage = min($totalPages, $page + 1);

                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isActive = $i == $page;
                                    ?>
                                        <a href="?page=<?= $i ?>"
                                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $isActive ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php } ?>

                                    <a href="?page=<?= min($totalPages, $page + 1) ?>"
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

        // --- GESTION DE LA RECHERCHE ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.stock-row');
            let found = false;

            rows.forEach(row => {
                const produitDesignation = row.dataset.produitDesignation;
                const produitMatricule = row.dataset.produitMatricule;
                const typeMouvement = row.dataset.typeMouvement;
                const unite = row.dataset.unite;

                if (produitDesignation.includes(searchTerm) || 
                    produitMatricule.includes(searchTerm) || 
                    typeMouvement.includes(searchTerm) ||
                    unite.includes(searchTerm)) {
                    row.style.display = '';
                    found = true;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('noResults').classList.toggle('hidden', found);
            document.getElementById('tableBody').classList.toggle('hidden', !found && searchTerm !== '');
        });

        // --- FONCTION DE RAFRAÎCHISSEMENT ---
        function refreshPage() {
            const button = event.target.closest('button');
            if (button) {
                button.classList.add('animate-spin');
                setTimeout(() => {
                    button.classList.remove('animate-spin');
                    window.location.reload();
                }, 500);
            }
        }

        // --- ANIMATION DES LIGNES AU CHARGEMENT ---
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.fade-in-row');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
            });
        });

        // --- GESTION DES EFFETS VISUELS ---
        document.querySelectorAll('.stats-card, .nav-link').forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // --- DÉTECTION DE LA CONNEXION ---
        window.addEventListener('online', function() {
            showNotification('Vous êtes reconnecté à internet', 'success');
        });

        window.addEventListener('offline', function() {
            showNotification('Vous êtes hors ligne', 'warning');
        });

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg text-white z-50 animate-fade-in ${
                type === 'success' ? 'bg-green-500' : 'bg-yellow-500'
            }`;
            notification.innerHTML = `
                <div class="flex items-center space-x-2">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // --- NAVIGATION CLAVIER ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });
    </script>
</body>
</html>