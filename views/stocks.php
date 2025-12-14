<?php
# Connexion à la base de données
include '../connexion/connexion.php';

// Vérification de l'authentification PDG
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    header('Location: ../login.php');
    exit;
}

// Initialisation des variables
$message = '';
$message_type = '';
$total_stocks = 0;
$stocks = [];

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer la liste des boutiques et produits pour les formulaires
try {
    $boutiques = $pdo->query("SELECT id, nom FROM boutiques WHERE statut = 0 AND actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    $produits = $pdo->query("SELECT matricule, designation FROM produits WHERE statut = 0 AND actif = 1 ORDER BY designation")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des données : " . $e->getMessage(),
        'type' => "error"
    ];
    $boutiques = [];
    $produits = [];
}

// Vérifier si c'est une requête AJAX pour récupérer les données d'un stock (pour édition)
if (isset($_GET['action']) && $_GET['action'] == 'get_stock' && isset($_GET['id'])) {
    $stockId = (int)$_GET['id'];
    try {
        $query = $pdo->prepare("
            SELECT s.*, 
                   b.nom as boutique_nom, 
                   p.designation as produit_designation 
            FROM stock s 
            JOIN boutiques b ON s.boutique_id = b.id 
            JOIN produits p ON s.produit_matricule = p.matricule 
            WHERE s.id = ? AND s.statut = 0
        ");
        $query->execute([$stockId]);
        $stock = $query->fetch(PDO::FETCH_ASSOC);

        if ($stock) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'stock' => $stock]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Stock non trouvé']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de base de données.']);
    }
    exit;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Compter le nombre total de stocks (avec statut=0)
try {
    $countQuery = $pdo->query("SELECT COUNT(*) FROM stock WHERE statut = 0 AND type_mouvement = 'approvisionnement'");
    $total_stocks = $countQuery->fetchColumn();
    $totalPages = ceil($total_stocks / $limit);

    // Requête paginée avec jointures - SEULEMENT LES APPROVISIONNEMENTS
    $query = $pdo->prepare("
        SELECT s.*, 
               b.nom as boutique_nom, 
               p.designation as produit_designation 
        FROM stock s 
        JOIN boutiques b ON s.boutique_id = b.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE s.statut = 0 
          AND s.type_mouvement = 'approvisionnement'
        ORDER BY s.date_creation DESC 
        LIMIT :limit OFFSET :offset
    ");
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $stocks = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer quelques statistiques - SEULEMENT LES APPROVISIONNEMENTS
    $total_quantite = $pdo->query("SELECT SUM(quantite) FROM stock WHERE statut = 0 AND type_mouvement = 'approvisionnement'")->fetchColumn();
    $total_quantite = $total_quantite ? $total_quantite : 0;
    
    // Compter le nombre de produits distincts en stock - SEULEMENT LES APPROVISIONNEMENTS
    $produits_en_stock = $pdo->query("SELECT COUNT(DISTINCT produit_matricule) FROM stock WHERE statut = 0 AND type_mouvement = 'approvisionnement'")->fetchColumn();
    
    // Compter le nombre de boutiques avec stock - SEULEMENT LES APPROVISIONNEMENTS
    $boutiques_avec_stock = $pdo->query("SELECT COUNT(DISTINCT boutique_id) FROM stock WHERE statut = 0 AND type_mouvement = 'approvisionnement'")->fetchColumn();

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des stocks: " . $e->getMessage(),
        'type' => "error"
    ];
    $total_quantite = 0;
    $produits_en_stock = 0;
    $boutiques_avec_stock = 0;
    $stocks = [];
    $total_stocks = 0;
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Gestion des stocks - NGS (New Grace Service)</title>
    
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
                        <p class="text-xs text-gray-300">New Grace Service - Dashboard PDG</p>
                    </div>
                </div>
            </div>

            <div class="sidebar-profile p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-yellow-500/20 border-2 border-yellow-500/30 flex items-center justify-center relative">
                        <i class="fas fa-crown text-yellow-500 text-lg"></i>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-900"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold truncate"><?= htmlspecialchars($_SESSION['user_name'] ?? 'PDG') ?></h3>
                        <p class="text-sm text-gray-300 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav p-4 space-y-1">
                <a href="dashboard_pdg.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors relative">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-store w-5 text-gray-300"></i>
                    <span>Boutiques</span>
                </a>
                <a href="rapports.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-bar w-5 text-gray-300"></i>
                    <span>Rapports</span>
                </a>
                <a href="produits.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Produits</span>
                </a>
                <a href="stocks.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-warehouse w-5 text-white"></i>
                    <span>Stocks</span>
                    <span class="notification-badge"><?= $total_stocks ?></span>
                </a>
                <a href="transferts.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="utilisateurs.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users w-5 text-gray-300"></i>
                    <span>Utilisateurs</span>
                </a>
                <a href="parametres.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-cog w-5 text-gray-300"></i>
                    <span>Paramètres</span>
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
            <header class="bg-white border-b border-gray-200 p-4 md:p-6 sticky top-0 z-30 shadow-sm">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Gestion des stocks - NGS</h1>
                        <p class="text-gray-600 text-sm md:text-base">New Grace Service - Suivi des stocks par boutique (Approvisionnements)</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="openStockModal()"
                            class="px-4 py-3 gradient-blue-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-plus"></i>
                            <span class="hidden md:inline">Nouveau stock</span>
                            <span class="md:hidden">Nouveau</span>
                        </button>
                        <a href="transferts.php"
                            class="px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="hidden md:inline">Voir transferts</span>
                            <span class="md:hidden">Transferts</span>
                        </a>
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

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_stocks ?></h3>
                        <p class="text-gray-600">Approvisionnements</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-green-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                                <i class="fas fa-weight text-green-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-green-600">Quantité totale</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($total_quantite, 3) ?></h3>
                        <p class="text-gray-600">Unités en stock</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-tshirt text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Produits</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $produits_en_stock ?></h3>
                        <p class="text-gray-600">Produits différents</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-orange-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center">
                                <i class="fas fa-store text-orange-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-orange-600">Boutiques</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $boutiques_avec_stock ?></h3>
                        <p class="text-gray-600">Boutiques avec stock</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in" style="animation-delay: 0.4s">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="relative flex-1 max-w-lg">
                            <input type="text"
                                id="searchInput"
                                placeholder="Rechercher par boutique, produit ou ID..."
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

                <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.5s">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Liste des stocks (Approvisionnements) - NGS</h2>
                    </div>

                    <div class="table-container">
                        <table class="w-full min-w-[800px]" id="stocksTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutique</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seuil d'alerte</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date de création</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php if (!empty($stocks)): ?>
                                    <?php foreach ($stocks as $index => $stock): ?>
                                        <?php 
                                        // Déterminer la classe du seuil d'alerte
                                        $seuilClass = $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'seuil-faible' : 'seuil-ok';
                                        $seuilText = $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'Faible' : 'OK';
                                        ?>
                                        <tr class="stock-row hover:bg-gray-50 transition-colors fade-in-row"
                                            data-stock-id="<?= htmlspecialchars($stock['id']) ?>"
                                            data-boutique-nom="<?= htmlspecialchars(strtolower($stock['boutique_nom'])) ?>"
                                            data-produit-designation="<?= htmlspecialchars(strtolower($stock['produit_designation'])) ?>"
                                            style="animation-delay: <?= $index * 0.05 ?>s">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-mono font-bold">#<?= htmlspecialchars($stock['id']) ?></span>
                                                    <span class="badge-mouvement badge-approvisionnement ml-2">
                                                        <i class="fas fa-truck-loading mr-1"></i>
                                                        Appro
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($stock['boutique_nom']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div>
                                                    <?= htmlspecialchars($stock['produit_designation']) ?>
                                                    <div class="text-xs text-gray-500">
                                                        <?= htmlspecialchars($stock['produit_matricule']) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="font-bold"><?= number_format($stock['quantite'], 3) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <span class="seuil-alerte <?= $seuilClass ?>">
                                                    <i class="fas fa-<?= $stock['quantite'] <= $stock['seuil_alerte_stock'] ? 'exclamation-triangle' : 'check-circle' ?> mr-1"></i>
                                                    <?= $seuilText ?> (<?= $stock['seuil_alerte_stock'] ?>)
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d/m/Y H:i', strtotime($stock['date_creation'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2 action-buttons">
                                                    <button onclick="openStockModal(<?= $stock['id'] ?>); return false;" 
                                                            class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                                        <i class="fas fa-edit mr-1"></i>
                                                        <span class="hidden md:inline">Modifier</span>
                                                    </button>
                                                    <button onclick="openDeleteModal(<?= $stock['id'] ?>, '<?= htmlspecialchars(addslashes($stock['boutique_nom'])) ?>', '<?= htmlspecialchars(addslashes($stock['produit_designation'])) ?>'); return false;"
                                                            class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                                                        <i class="fas fa-trash-alt mr-1"></i>
                                                        <span class="hidden md:inline">Archiver</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun approvisionnement enregistré</p>
                                                <p class="text-sm mt-2">Ajoutez des stocks en utilisant le bouton "Nouveau stock"</p>
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
                                    <span class="font-medium"><?= $total_stocks ?></span> approvisionnements
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

    <div id="stockModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Ajouter un nouveau stock - NGS</h3>
                <button onclick="closeStockModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="stockForm" method="POST" action="../models/traitement/stock-post.php">
                <input type="hidden" name="stock_id" id="stockId">
                <input type="hidden" name="type_mouvement" value="approvisionnement">

                <div class="space-y-4">
                    <div>
                        <label for="boutique_id" class="block text-sm font-medium text-gray-700 mb-1">Boutique *</label>
                        <select name="boutique_id" id="boutique_id" required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3">
                            <option value="">Sélectionnez une boutique</option>
                            <?php foreach ($boutiques as $boutique): ?>
                                <option value="<?= $boutique['id'] ?>"><?= htmlspecialchars($boutique['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="produit_matricule" class="block text-sm font-medium text-gray-700 mb-1">Produit *</label>
                        <select name="produit_matricule" id="produit_matricule" required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3">
                            <option value="">Sélectionnez un produit</option>
                            <?php foreach ($produits as $produit): ?>
                                <option value="<?= htmlspecialchars($produit['matricule']) ?>">
                                    <?= htmlspecialchars($produit['designation']) ?> (<?= htmlspecialchars($produit['matricule']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="quantite" class="block text-sm font-medium text-gray-700 mb-1">Quantité *</label>
                        <input type="number" name="quantite" id="quantite" required step="0.001" min="0"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                               placeholder="Ex: 10.500">
                    </div>
                    
                    <div>
                        <label for="seuil_alerte_stock" class="block text-sm font-medium text-gray-700 mb-1">Seuil d'alerte *</label>
                        <input type="number" name="seuil_alerte_stock" id="seuil_alerte_stock" required min="0"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                               placeholder="Ex: 5" value="5">
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-blue-700 font-medium">Note sur les stocks</p>
                                <p class="text-xs text-blue-600 mt-1">Ce formulaire permet d'ajouter des approvisionnements. Les transferts entre boutiques sont gérés dans la section dédiée.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeStockModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="ajouter_stock" id="submitButton"
                            class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                        Enregistrer le stock
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Confirmation d'archivage - NGS</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="text-center py-4">
                <i class="fas fa-archive text-5xl mb-4 text-red-500"></i>
                <p class="text-lg font-bold text-red-700 mb-2">ATTENTION ! Archivage du stock</p>
                <p class="text-gray-600 mb-4" id="deleteModalText">Vous êtes sur le point d'archiver le stock. Il ne sera plus visible, mais ses données resteront en base de données (Soft Delete).</p>
            </div>

            <form id="deleteForm" method="POST" action="../models/traitement/stock-post.php" class="mt-6 flex justify-center space-x-3">
                <input type="hidden" name="stock_id" id="deleteStockId">
                <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                    Annuler
                </button>
                <button type="submit" name="archiver_stock"
                        class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                    Oui, Archiver ce stock
                </button>
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

        // --- GESTION DE LA MODALE AJOUT/MODIF DE STOCK ---
        const stockModal = document.getElementById('stockModal');
        const modalTitle = document.getElementById('modalTitle');
        const stockForm = document.getElementById('stockForm');
        const submitButton = document.getElementById('submitButton');

        function openStockModal(stockId = null) {
            stockForm.reset();
            
            if (stockId) {
                // Mode Modification
                modalTitle.textContent = "Modifier le stock #" + stockId + " - NGS";
                submitButton.textContent = "Modifier le stock";
                submitButton.name = 'modifier_stock';
                document.getElementById('stockId').value = stockId;

                // Charger les données du stock via AJAX
                fetch('stocks.php?action=get_stock&id=' + stockId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('boutique_id').value = data.stock.boutique_id;
                            document.getElementById('produit_matricule').value = data.stock.produit_matricule;
                            document.getElementById('quantite').value = data.stock.quantite;
                            document.getElementById('seuil_alerte_stock').value = data.stock.seuil_alerte_stock;
                        } else {
                            alert(data.message);
                            closeStockModal();
                        }
                    })
                    .catch(error => {
                        console.error('Erreur AJAX:', error);
                        alert("Impossible de charger les données du stock.");
                        closeStockModal();
                    });

            } else {
                // Mode Ajout
                modalTitle.textContent = "Ajouter un nouveau stock - NGS";
                submitButton.textContent = "Enregistrer le stock";
                submitButton.name = 'ajouter_stock';
                document.getElementById('stockId').value = '';
            }

            stockModal.classList.add('show');
        }

        function closeStockModal() {
            stockModal.classList.remove('show');
        }

        // --- GESTION DE LA MODALE DELETE ---
        const deleteModal = document.getElementById('deleteModal');
        const deleteModalText = document.getElementById('deleteModalText');
        const deleteStockId = document.getElementById('deleteStockId');

        function openDeleteModal(stockId, boutiqueNom, produitDesignation) {
            deleteModalText.innerHTML = `Vous êtes sur le point d'archiver le stock #${stockId} (Boutique: <strong>${boutiqueNom}</strong>, Produit: <strong>${produitDesignation}</strong>). Il ne sera plus visible, mais ses données resteront en base de données (Soft Delete). Cette action est réversible uniquement par un administrateur système. Confirmez-vous ?`;
            deleteStockId.value = stockId;
            deleteModal.classList.add('show');
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
        }

        // --- GESTION DE LA RECHERCHE ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.stock-row');
            let found = false;

            rows.forEach(row => {
                const stockId = row.dataset.stockId;
                const boutiqueNom = row.dataset.boutiqueNom;
                const produitDesignation = row.dataset.produitDesignation;

                if (stockId.includes(searchTerm) || boutiqueNom.includes(searchTerm) || produitDesignation.includes(searchTerm)) {
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
            button.classList.add('animate-spin');
            setTimeout(() => {
                button.classList.remove('animate-spin');
                window.location.reload();
            }, 500);
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

        // --- VALIDATION DU FORMULAIRE ---
        stockForm.addEventListener('submit', function(e) {
            const quantite = document.getElementById('quantite').value;
            const seuil = document.getElementById('seuil_alerte_stock').value;
            
            if (quantite < 0) {
                e.preventDefault();
                alert('La quantité ne peut pas être négative.');
                return false;
            }
            
            if (seuil < 0) {
                e.preventDefault();
                alert('Le seuil d\'alerte ne peut pas être négatif.');
                return false;
            }
            
            return true;
        });

        // --- NAVIGATION CLAVIER ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (stockModal.classList.contains('show')) closeStockModal();
                if (deleteModal.classList.contains('show')) closeDeleteModal();
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });
    </script>
</body>
</html>