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
$total_boutiques = 0;
$boutiques = [];

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']); // Supprimer le message après affichage
}

// Vérifier si c'est une requête AJAX pour récupérer les données d'une boutique (pour édition)
if (isset($_GET['action']) && $_GET['action'] == 'get_boutique' && isset($_GET['id'])) {
    $boutiqueId = intval($_GET['id']);
    try {
        $query = $pdo->prepare("SELECT id, nom, email, actif FROM boutiques WHERE id = ? AND statut = 0");
        $query->execute([$boutiqueId]);
        $boutique = $query->fetch(PDO::FETCH_ASSOC);

        if ($boutique) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'boutique' => $boutique]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Boutique non trouvée']);
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

// Compter le nombre total de boutiques
try {
    $countQuery = $pdo->query("SELECT COUNT(*) FROM boutiques WHERE statut = 0");
    $total_boutiques = $countQuery->fetchColumn();
    $totalPages = ceil($total_boutiques / $limit);

    // Requête paginée
    $query = $pdo->prepare("SELECT * FROM boutiques WHERE statut = 0 ORDER BY actif DESC, date_creation DESC LIMIT :limit OFFSET :offset");
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $boutiques = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les actifs pour la carte de stats
    $active_count_total = $pdo->query("SELECT COUNT(*) FROM boutiques WHERE actif = 1 AND statut = 0")->fetchColumn();

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des boutiques: " . $e->getMessage(),
        'type' => "error"
    ];
    $active_count_total = 0;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Gestion des boutiques - NGS (New Grace Service)</title>
    
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
        
        /* Mobile optimizations */
        .mobile-menu-button {
            display: none;
        }
        
        .mobile-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1000;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            overflow-y: auto;
        }
        
        .mobile-sidebar.active {
            transform: translateX(0);
        }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .mobile-overlay.active {
            display: block;
        }
        
        /* Responsive table */
        .responsive-table {
            display: block;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table-mobile-compact td {
            white-space: nowrap;
        }
        
        /* Card adjustments for mobile */
        .mobile-card {
            padding: 1rem;
        }
        
        /* Action buttons for mobile */
        .action-buttons-mobile {
            display: flex;
            gap: 0.25rem;
        }
        
        .action-buttons-mobile button {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
        }
        
        /* Mobile text */
        .mobile-text-sm {
            font-size: 0.875rem;
        }
        
        .mobile-text-xs {
            font-size: 0.75rem;
        }
        
        /* Grid adjustments */
        @media (max-width: 768px) {
            .mobile-menu-button {
                display: block;
            }
            
            .sidebar:not(.mobile-sidebar) {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            header {
                padding: 1rem !important;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            main {
                padding: 1rem !important;
            }
            
            .grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .stats-card {
                padding: 1rem !important;
                min-height: 120px;
            }
            
            .stats-card .text-3xl {
                font-size: 1.5rem;
            }
            
            .mobile-hide {
                display: none;
            }
            
            .mobile-show {
                display: block !important;
            }
            
            .mobile-flex-col {
                flex-direction: column;
            }
            
            .mobile-space-y-2 > * + * {
                margin-top: 0.5rem;
            }
            
            .mobile-gap-2 {
                gap: 0.5rem;
            }
            
            .mobile-gap-4 {
                gap: 1rem;
            }
            
            .mobile-w-full {
                width: 100%;
            }
            
            .mobile-text-center {
                text-align: center;
            }
        }
        
        @media (max-width: 640px) {
            .grid-cols-4 {
                grid-template-columns: 1fr !important;
            }
            
            .grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
            
            .header-title h1 {
                font-size: 1.25rem;
            }
            
            .header-title p {
                font-size: 0.875rem;
            }
            
            .stats-card .w-12 {
                width: 2.5rem;
                height: 2.5rem;
            }
            
            .stats-card .text-3xl {
                font-size: 1.25rem;
            }
            
            .badge {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            table th, table td {
                padding: 0.5rem 0.25rem;
            }
            
            .pagination-info {
                display: none;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .action-buttons button {
                width: 100%;
                justify-content: center;
                font-size: 0.75rem;
                padding: 0.375rem 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            
            .header-actions button {
                width: 100%;
                justify-content: center;
            }
            
            .mobile-sidebar {
                width: 100%;
            }
            
            .stats-card {
                min-height: 110px;
            }
            
            .stats-card .text-3xl {
                font-size: 1.125rem;
            }
            
            .search-container {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .table-container {
                margin-left: -0.5rem;
                margin-right: -0.5rem;
                width: calc(100% + 1rem);
            }
            
            .modal-content {
                width: 95%;
                padding: 1rem;
            }
            
            .action-buttons-mobile {
                flex-wrap: wrap;
            }
            
            .action-buttons-mobile button {
                flex: 1;
                min-width: 70px;
            }
        }
        
        @media (max-width: 360px) {
            .stats-card {
                padding: 0.75rem !important;
            }
            
            .stats-card .w-12 {
                width: 2rem;
                height: 2rem;
            }
            
            .stats-card .text-3xl {
                font-size: 1rem;
            }
            
            .action-buttons-mobile button {
                font-size: 0.7rem;
                padding: 0.25rem 0.375rem;
            }
        }
        
        /* Animation for mobile menu */
        @keyframes slideIn {
            from {
                transform: translateX(-100%);
            }
            to {
                transform: translateX(0);
            }
        }
        
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }
        
        /* Touch-friendly elements */
        button, a {
            min-height: 44px;
            min-width: 44px;
        }
        
        input, select, textarea {
            font-size: 16px !important; /* Prevents zoom on iOS */
        }
        
        /* Better scrolling on mobile */
        body {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Hide scrollbar on mobile for cleaner look */
        @media (max-width: 768px) {
            .sidebar-nav::-webkit-scrollbar {
                display: none;
            }
            
            .sidebar-nav {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        }
    </style>
</head>

<body class="font-inter min-h-screen bg-gray-50">
    <!-- Mobile Menu Button -->
    <button id="mobileMenuButton" class="mobile-menu-button fixed top-4 left-4 z-50 w-10 h-10 rounded-lg bg-white shadow-md flex items-center justify-center md:hidden">
        <i class="fas fa-bars text-gray-700"></i>
    </button>
    
    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="mobile-overlay md:hidden"></div>
    
    <div class="flex h-screen">
        <!-- Desktop Sidebar -->
        <aside class="hidden md:block w-64 gradient-bg text-white min-h-screen fixed left-0 top-0 h-full">
            <div class="p-6 border-b border-white/10">
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

            <div class="p-6 border-b border-white/10">
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

            <nav class="p-4 space-y-1">
                <a href="dashboard_pdg.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors relative">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-store w-5 text-white"></i>
                    <span>Boutiques</span>
                    <span class="notification-badge"><?= $total_boutiques ?></span>
                </a>
                <a href="produits.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Produits</span>
                </a>
                <a href="stocks.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-warehouse w-5 text-white"></i>
                    <span>Stocks</span>
                </a>
                <a href="transferts.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="utilisateurs.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users w-5 text-gray-300"></i>
                    <span>Utilisateurs</span>
                </a>
                <a href="rapports_pdg.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-chart-bar w-5 text-white"></i>
                    <span>Rapports</span>
                </a>
            </nav>

            <div class="p-4 border-t border-white/10 mt-auto">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200 transition-colors">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Mobile Sidebar -->
        <aside class="mobile-sidebar md:hidden gradient-bg text-white slide-in">
            <div class="p-6 border-b border-white/10 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center shadow-lg">
                        <span class="font-bold text-white text-lg font-display">NGS</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold">NGS</h1>
                        <p class="text-xs text-gray-300">Dashboard PDG</p>
                    </div>
                </div>
                <button id="closeMobileMenu" class="text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="p-6 border-b border-white/10">
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

            <nav class="p-4 space-y-1">
                <a href="dashboard_pdg.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors" onclick="closeMobileMenu()">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10" onclick="closeMobileMenu()">
                    <i class="fas fa-store w-5 text-white"></i>
                    <span>Boutiques</span>
                    <span class="notification-badge"><?= $total_boutiques ?></span>
                </a>
                <a href="produits.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors" onclick="closeMobileMenu()">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Produits</span>
                </a>
                <a href="stocks.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg" onclick="closeMobileMenu()">
                    <i class="fas fa-warehouse w-5 text-white"></i>
                    <span>Stocks</span>
                </a>
                <a href="transferts.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors" onclick="closeMobileMenu()">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="utilisateurs.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors" onclick="closeMobileMenu()">
                    <i class="fas fa-users w-5 text-gray-300"></i>
                    <span>Utilisateurs</span>
                </a>
                <a href="rapports_pdg.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg" onclick="closeMobileMenu()">
                    <i class="fas fa-chart-bar w-5 text-white"></i>
                    <span>Rapports PDG</span>
                </a>
            </nav>

            <div class="p-4 border-t border-white/10 mt-auto">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200 transition-colors" onclick="closeMobileMenu()">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <div class="main-content flex-1 md:ml-64">
            <header class="bg-white border-b border-gray-200 p-4 md:p-6 sticky top-0 z-30 shadow-sm">
                <div class="flex justify-between items-center header-content">
                    <div class="header-title">
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900 mobile-text-sm">Gestion des boutiques - NGS</h1>
                        <p class="text-gray-600 text-sm md:text-base mobile-text-xs">New Grace Service - Gérez les boutiques de votre entreprise</p>
                    </div>
                    <div class="flex items-center space-x-2 md:space-x-4 header-actions">
                        <button onclick="openBoutiqueModal()"
                            class="px-3 md:px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300 mobile-w-full md:w-auto">
                            <i class="fas fa-plus"></i>
                            <span class="hidden md:inline">Nouvelle boutique</span>
                            <span class="md:hidden">Nouveau</span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 md:p-6">
                <?php if ($message): ?>
                    <div class="mb-4 md:mb-6 fade-in relative z-10 animate-fade-in">
                        <div class="
                    <?php if ($message_type === 'success'): ?>bg-green-50 text-green-700 border border-green-200
                    <?php elseif ($message_type === 'error'): ?>bg-red-50 text-red-700 border border-red-200
                    <?php elseif ($message_type === 'warning'): ?>bg-yellow-50 text-yellow-700 border border-yellow-200
                    <?php else: ?>bg-blue-50 text-blue-700 border border-blue-200<?php endif; ?>
                    rounded-xl p-4 flex items-center justify-between shadow-soft">
                            <div class="flex items-center space-x-2 md:space-x-3">
                                <?php if ($message_type === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                                <?php elseif ($message_type === 'error'): ?>
                                    <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
                                <?php elseif ($message_type === 'warning'): ?>
                                    <i class="fas fa-exclamation-triangle text-yellow-600 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-blue-600 text-lg"></i>
                                <?php endif; ?>
                                <span class="mobile-text-sm"><?= htmlspecialchars($message) ?></span>
                            </div>
                            <button onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-4 md:mb-8">
                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-blue-500 animate-fade-in mobile-card">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-store text-blue-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-blue-600">Total</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $total_boutiques ?></h3>
                        <p class="text-gray-600 text-sm">Boutiques enregistrées</p>
                    </div>

                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-green-500 animate-fade-in mobile-card" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-green-100 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-green-600">Actives</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $active_count_total ?></h3>
                        <p class="text-gray-600 text-sm">Boutiques opérationnelles</p>
                    </div>

                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-red-500 animate-fade-in mobile-card" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-red-100 flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-red-600">Inactives</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $total_boutiques - $active_count_total ?></h3>
                        <p class="text-gray-600 text-sm">Boutiques désactivées</p>
                    </div>

                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-purple-500 animate-fade-in mobile-card" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-file-alt text-purple-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-purple-600">Pagination</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $page ?></h3>
                        <p class="text-gray-600 text-sm">sur <?= $totalPages ?> pages</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 mb-4 md:mb-6 animate-fade-in mobile-card" style="animation-delay: 0.4s">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 md:gap-4 search-container">
                        <div class="relative flex-1 max-w-lg">
                            <input type="text"
                                id="searchInput"
                                placeholder="Rechercher par Nom ou Email..."
                                class="w-full pl-10 md:pl-12 pr-4 py-2 md:py-3 border border-gray-300 rounded-lg md:rounded-xl focus:ring-2 focus:ring-secondary focus:border-secondary transition-all shadow-sm">
                            <i class="fas fa-search absolute left-3 md:left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>

                        <div class="flex items-center justify-between md:justify-start space-x-2 md:space-x-4">
                            <div class="text-xs md:text-sm text-gray-600 hidden md:flex items-center space-x-2">
                                <i class="fas fa-info-circle text-blue-500"></i>
                                <span>Page <?= $page ?> sur <?= $totalPages ?></span>
                            </div>
                            <div class="text-xs text-gray-600 md:hidden">
                                Page <?= $page ?>/<?= $totalPages ?>
                            </div>
                            <button onclick="refreshPage()" class="p-2 text-gray-600 hover:text-blue-600 transition-colors" title="Actualiser">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl md:rounded-2xl shadow-soft overflow-hidden animate-fade-in mobile-card" style="animation-delay: 0.5s">
                    <div class="px-4 md:px-6 py-3 md:py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-base md:text-lg font-semibold text-gray-900">Liste des boutiques - NGS</h2>
                    </div>

                    <div class="responsive-table table-container">
                        <table class="w-full min-w-[700px] mobile-text-sm" id="boutiquesTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider mobile-hide">Email</th>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($boutiques as $index => $boutique): ?>
                                    <tr class="boutique-row hover:bg-gray-50 transition-colors fade-in-row"
                                        data-boutique-name="<?= htmlspecialchars(strtolower($boutique['nom'])) ?>"
                                        data-boutique-email="<?= htmlspecialchars(strtolower($boutique['email'])) ?>"
                                        style="animation-delay: <?= $index * 0.05 ?>s">
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm font-medium text-gray-900">#<?= $boutique['id'] ?></td>
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm text-gray-900 boutique-name">
                                            <div class="flex items-center">
                                                <div class="w-6 h-6 md:w-8 md:h-8 rounded-full bg-blue-100 flex items-center justify-center mr-2 md:mr-3">
                                                    <i class="fas fa-store text-blue-600 text-xs"></i>
                                                </div>
                                                <span class="truncate max-w-[120px] md:max-w-none"><?= htmlspecialchars($boutique['nom']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm text-gray-900 boutique-email mobile-hide">
                                            <span class="truncate max-w-[150px] md:max-w-none inline-block"><?= htmlspecialchars($boutique['email']) ?></span>
                                        </td>
                                        
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap">
                                            <span class="status-badge <?= $boutique['actif'] ? 'status-active' : 'status-inactive' ?> inline-flex items-center text-xs">
                                                <?php if ($boutique['actif']): ?>
                                                    <i class="fas fa-circle text-[10px] mr-1"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-circle text-[10px] mr-1"></i>
                                                <?php endif; ?>
                                                <?= $boutique['actif'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm font-medium">
                                            <div class="flex space-x-1 md:space-x-2 action-buttons action-buttons-mobile">
                                                <button onclick="openBoutiqueModal(<?= $boutique['id'] ?>); return false;" 
                                                        class="action-btn inline-flex items-center px-2 md:px-3 py-1 md:py-2 border border-transparent text-xs md:text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    <span class="hidden md:inline">Modifier</span>
                                                    <span class="md:hidden">Edit</span>
                                                </button>
                                                <button onclick="openToggleModal(<?= $boutique['id'] ?>, '<?= htmlspecialchars(addslashes($boutique['nom'])) ?>', <?= $boutique['actif'] ?>); return false;"
                                                        class="action-btn inline-flex items-center px-2 md:px-3 py-1 md:py-2 border border-transparent text-xs md:text-sm leading-4 font-medium rounded-md text-white <?= $boutique['actif'] ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700' ?> focus:outline-none focus:ring-2 focus:ring-offset-2 <?= $boutique['actif'] ? 'focus:ring-orange-500' : 'focus:ring-green-500' ?> transition-colors">
                                                    <i class="fas fa-power-off mr-1"></i>
                                                    <span class="hidden md:inline"><?= $boutique['actif'] ? 'Désactiver' : 'Activer' ?></span>
                                                    <span class="md:hidden"><?= $boutique['actif'] ? 'Off' : 'On' ?></span>
                                                </button>
                                                <button onclick="openDeleteModal(<?= $boutique['id'] ?>, '<?= htmlspecialchars(addslashes($boutique['nom'])) ?>'); return false;"
                                                        class="action-btn inline-flex items-center px-2 md:px-3 py-1 md:py-2 border border-transparent text-xs md:text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                                                    <i class="fas fa-trash-alt mr-1"></i>
                                                    <span class="hidden md:inline">Supprimer</span>
                                                    <span class="md:hidden">Del</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="noResults" class="hidden text-center py-8 md:py-12">
                        <div class="bg-gray-50 rounded-xl md:rounded-2xl p-6 md:p-8 max-w-md mx-auto shadow-soft">
                            <i class="fas fa-search text-4xl md:text-6xl text-gray-400 mb-3 md:mb-4"></i>
                            <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">Aucun résultat trouvé</h3>
                            <p class="text-gray-600 text-sm">Aucune boutique ne correspond à votre recherche</p>
                        </div>
                    </div>

                    <?php if (empty($boutiques) && $page == 1): ?>
                        <div class="text-center py-8 md:py-12">
                            <div class="bg-gray-50 rounded-xl md:rounded-2xl p-6 md:p-8 max-w-md mx-auto shadow-soft">
                                <i class="fas fa-store text-4xl md:text-6xl text-gray-400 mb-3 md:mb-4"></i>
                                <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">Aucune boutique enregistrée</h3>
                                <p class="text-gray-600 text-sm mb-4">Commencez par ajouter votre première boutique</p>
                                <button onclick="openBoutiqueModal()"
                                    class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 mx-auto shadow-md text-sm">
                                    <i class="fas fa-plus"></i>
                                    <span>Ajouter une boutique</span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-4 md:px-6 py-3 md:py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 md:gap-0">
                                <div class="text-xs md:text-sm text-gray-700 pagination-info hidden sm:block">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_boutiques) ?></span> sur
                                    <span class="font-medium"><?= $total_boutiques ?></span> boutiques
                                </div>

                                <div class="flex items-center space-x-1 md:space-x-2">
                                    <a href="?page=<?= max(1, $page - 1) ?>"
                                        class="px-2 md:px-3 py-1 md:py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>

                                    <?php
                                    $startPage = max(1, $page - 1);
                                    $endPage = min($totalPages, $page + 1);

                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isActive = $i == $page;
                                    ?>
                                        <a href="?page=<?= $i ?>"
                                            class="px-2 md:px-3 py-1 md:py-2 rounded-lg text-xs md:text-sm font-medium transition-colors <?= $isActive ? 'bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php } ?>

                                    <a href="?page=<?= min($totalPages, $page + 1) ?>"
                                        class="px-2 md:px-3 py-1 md:py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">
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
    
    <!-- Boutique Modal -->
    <div id="boutiqueModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-4 md:p-6">
            <div class="flex justify-between items-center border-b pb-2 md:pb-3 mb-3 md:mb-4">
                <h3 class="text-lg md:text-xl font-bold text-gray-900" id="modalTitle">Ajouter une nouvelle boutique - NGS</h3>
                <button onclick="closeBoutiqueModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl md:text-2xl"></i>
                </button>
            </div>
            
            <form id="boutiqueForm" method="POST" action="../models/traitement/boutique-post.php">
                <input type="hidden" name="id" id="boutiqueId">

                <div class="space-y-3 md:space-y-4">
                    <div>
                        <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom de la boutique</label>
                        <input type="text" name="nom" id="nom" required
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-2 md:p-3"
                               placeholder="Ex: Boutique Paris Centre">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email de connexion</label>
                        <input type="email" name="email" id="email" required
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-2 md:p-3"
                               placeholder="Ex: contact@boutiqueparis.com">
                    </div>
                    <div id="passwordField">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <input type="password" name="password" id="password"
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-2 md:p-3"
                               placeholder="Laisser vide pour ne pas modifier">
                        <p class="text-xs text-gray-500 mt-1" id="passwordHint">Requis lors de l'ajout. Laisser vide lors de la modification pour conserver l'ancien.</p>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" name="actif" id="actif" value="1" checked
                               class="h-4 w-4 text-secondary border-gray-300 rounded focus:ring-secondary">
                        <label for="actif" class="text-sm font-medium text-gray-700">Boutique active</label>
                    </div>
                </div>

                <div class="mt-4 md:mt-6 flex justify-end space-x-2 md:space-x-3">
                    <button type="button" onclick="closeBoutiqueModal()"
                            class="px-3 md:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm">
                        Annuler
                    </button>
                    <button type="submit" name="ajouter_boutique" id="submitButton"
                            class="px-3 md:px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 transition-opacity shadow-md text-sm">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toggle Modal -->
    <div id="toggleModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-4 md:p-6">
            <div class="flex justify-between items-center border-b pb-2 md:pb-3 mb-3 md:mb-4">
                <h3 class="text-lg md:text-xl font-bold text-gray-900" id="toggleModalTitle">Confirmer l'action - NGS</h3>
                <button onclick="closeToggleModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl md:text-2xl"></i>
                </button>
            </div>
            
            <div class="text-center py-3 md:py-4">
                <i id="toggleIcon" class="fas fa-power-off text-4xl md:text-5xl mb-3 md:mb-4 text-gray-500"></i>
                <p class="text-base md:text-lg font-medium text-gray-800 mb-1 md:mb-2">Voulez-vous vraiment continuer ?</p>
                <p class="text-gray-600 text-sm md:text-base" id="toggleModalText">Le statut de la boutique **NomBoutique** va être modifié.</p>
            </div>

            <form id="toggleForm" method="POST" action="../models/traitement/boutique-post.php" class="mt-4 md:mt-6 flex justify-center space-x-2 md:space-x-3">
                <input type="hidden" name="id" id="toggleBoutiqueId">
                <button type="button" onclick="closeToggleModal()"
                        class="px-3 md:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm">
                    Annuler
                </button>
                <button type="submit" name="toggle_actif" id="toggleConfirmButton"
                        class="px-3 md:px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md text-sm">
                    Confirmer
                </button>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-4 md:p-6">
            <div class="flex justify-between items-center border-b pb-2 md:pb-3 mb-3 md:mb-4">
                <h3 class="text-lg md:text-xl font-bold text-gray-900">Confirmation de suppression - NGS</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl md:text-2xl"></i>
                </button>
            </div>
            
            <div class="text-center py-3 md:py-4">
                <i class="fas fa-trash-alt text-4xl md:text-5xl mb-3 md:mb-4 text-red-500"></i>
                <p class="text-base md:text-lg font-bold text-red-700 mb-1 md:mb-2">ATTENTION ! Suppression (Archivage)</p>
                <p class="text-gray-600 text-sm md:text-base mb-3 md:mb-4" id="deleteModalText">Vous êtes sur le point d'archiver la boutique **NomBoutique**. Elle ne sera plus visible, mais ses données resteront en base de données (Soft Delete).</p>
            </div>

            <form id="deleteForm" method="POST" action="../models/traitement/boutique-post.php" class="mt-4 md:mt-6 flex justify-center space-x-2 md:space-x-3">
                <input type="hidden" name="id" id="deleteBoutiqueId">
                <button type="button" onclick="closeDeleteModal()"
                        class="px-3 md:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm">
                    Annuler
                </button>
                <button type="submit" name="supprimer_boutique"
                        class="px-3 md:px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md text-sm">
                    Oui, Archiver
                </button>
            </form>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileSidebar = document.querySelector('.mobile-sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        const closeMobileMenuBtn = document.getElementById('closeMobileMenu');
        
        function openMobileMenu() {
            mobileSidebar.classList.add('active');
            mobileOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileMenu() {
            mobileSidebar.classList.remove('active');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (mobileMenuButton) {
            mobileMenuButton.addEventListener('click', openMobileMenu);
        }
        
        if (closeMobileMenuBtn) {
            closeMobileMenuBtn.addEventListener('click', closeMobileMenu);
        }
        
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', closeMobileMenu);
        }
        
        // Close menu on resize to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                closeMobileMenu();
            }
        });
        
        // Expose function globally for menu links
        window.closeMobileMenu = closeMobileMenu;
        
        // --- GESTION DE LA MODALE AJOUT/MODIF ---
        const boutiqueModal = document.getElementById('boutiqueModal');
        const modalTitle = document.getElementById('modalTitle');
        const boutiqueForm = document.getElementById('boutiqueForm');
        const submitButton = document.getElementById('submitButton');
        const boutiqueId = document.getElementById('boutiqueId');
        const passwordInput = document.getElementById('password');
        const passwordHint = document.getElementById('passwordHint');

        function openBoutiqueModal(id = null) {
            boutiqueForm.reset();
            closeMobileMenu();
            
            if (id) {
                // Mode Modification
                modalTitle.textContent = "Modifier la boutique #" + id + " - NGS";
                submitButton.textContent = "Modifier la boutique";
                boutiqueId.value = id;
                submitButton.name = 'modifier_boutique';
                passwordInput.required = false; 
                passwordHint.textContent = "Laisser vide pour conserver le mot de passe actuel.";

                fetch('boutiques.php?action=get_boutique&id=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('nom').value = data.boutique.nom;
                            document.getElementById('email').value = data.boutique.email;
                            document.getElementById('actif').checked = data.boutique.actif == 1;
                        } else {
                            alert(data.message);
                            closeBoutiqueModal();
                        }
                    })
                    .catch(error => {
                        console.error('Erreur AJAX:', error);
                        alert("Impossible de charger les données de la boutique.");
                        closeBoutiqueModal();
                    });

            } else {
                // Mode Ajout
                modalTitle.textContent = "Ajouter une nouvelle boutique - NGS";
                submitButton.textContent = "Enregistrer la boutique";
                boutiqueId.value = '';
                submitButton.name = 'ajouter_boutique';
                passwordInput.required = true; 
                passwordHint.textContent = "Requis lors de l'ajout.";
                document.getElementById('actif').checked = true;
            }

            boutiqueModal.classList.add('show');
        }

        function closeBoutiqueModal() {
            boutiqueModal.classList.remove('show');
        }

        // --- GESTION DE LA MODALE TOGGLE ---
        const toggleModal = document.getElementById('toggleModal');
        const toggleModalTitle = document.getElementById('toggleModalTitle');
        const toggleModalText = document.getElementById('toggleModalText');
        const toggleIcon = document.getElementById('toggleIcon');
        const toggleBoutiqueId = document.getElementById('toggleBoutiqueId');
        const toggleConfirmButton = document.getElementById('toggleConfirmButton');

        function openToggleModal(id, nom, actif) {
            const action = actif ? 'désactiver' : 'activer';
            const iconClass = actif ? 'text-orange-500' : 'text-green-500';
            const buttonColor = actif ? 'bg-gradient-to-r from-orange-600 to-orange-700' : 'bg-gradient-to-r from-green-600 to-green-700';
            const buttonText = actif ? 'Oui, Désactiver' : 'Oui, Activer';

            toggleModalTitle.textContent = "Confirmer la " + action + " - NGS";
            toggleModalText.innerHTML = `Le statut de la boutique <strong>${nom}</strong> va passer à <strong>${action}</strong>.<br>Voulez-vous confirmer cette action ?`;
            
            toggleIcon.className = `fas fa-power-off text-4xl md:text-5xl mb-3 md:mb-4 ${iconClass}`;
            
            toggleConfirmButton.textContent = buttonText;
            toggleConfirmButton.className = `px-3 md:px-4 py-2 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md text-sm ${buttonColor}`;

            toggleBoutiqueId.value = id;
            toggleModal.classList.add('show');
        }

        function closeToggleModal() {
            toggleModal.classList.remove('show');
        }

        // --- GESTION DE LA MODALE DELETE ---
        const deleteModal = document.getElementById('deleteModal');
        const deleteModalText = document.getElementById('deleteModalText');
        const deleteBoutiqueId = document.getElementById('deleteBoutiqueId');

        function openDeleteModal(id, nom) {
            deleteModalText.innerHTML = `Vous êtes sur le point d'archiver la boutique <strong>${nom}</strong>. Elle ne sera plus visible, mais ses données resteront en base de données (Soft Delete). Cette action est réversible uniquement par un administrateur système. Confirmez-vous ?`;
            deleteBoutiqueId.value = id;
            deleteModal.classList.add('show');
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
        }

        // --- GESTION DE LA RECHERCHE ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.boutique-row');
            let found = false;

            rows.forEach(row => {
                const name = row.dataset.boutiqueName;
                const email = row.dataset.boutiqueEmail;

                if (name.includes(searchTerm) || email.includes(searchTerm)) {
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

        // Close modals with escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeBoutiqueModal();
                closeToggleModal();
                closeDeleteModal();
            }
        });

        // Prevent body scroll when modals are open
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('show', function() {
                document.body.style.overflow = 'hidden';
            });
            
            modal.addEventListener('hide', function() {
                document.body.style.overflow = '';
            });
        });

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeBoutiqueModal();
                closeToggleModal();
                closeDeleteModal();
            }
        });

        // Touch optimization for mobile
        if ('ontouchstart' in window) {
            // Add touch feedback to buttons
            const buttons = document.querySelectorAll('button, a');
            buttons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.opacity = '0.8';
                });
                
                button.addEventListener('touchend', function() {
                    this.style.opacity = '1';
                });
            });
        }
    </script>
</body>
</html>