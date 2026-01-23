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
$total_produits = 0;
$produits = [];

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']); // Supprimer le message après affichage
}

// Vérifier si c'est une requête AJAX pour récupérer les données d'un produit (pour édition)
if (isset($_GET['action']) && $_GET['action'] == 'get_produit' && isset($_GET['matricule'])) {
    $produitMatricule = $_GET['matricule'];
    try {
        $query = $pdo->prepare("SELECT matricule, designation, umProduit, actif FROM produits WHERE matricule = ? AND statut = 0");
        $query->execute([$produitMatricule]);
        $produit = $query->fetch(PDO::FETCH_ASSOC);

        if ($produit) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'produit' => $produit]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
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

// Compter le nombre total de produits
try {
    $countQuery = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut = 0");
    $total_produits = $countQuery->fetchColumn();
    $totalPages = ceil($total_produits / $limit);

    // Requête paginée
    $query = $pdo->prepare("SELECT *, 
        CASE 
            WHEN umProduit = 'metres' THEN 'mètres'
            WHEN umProduit = 'pieces' THEN 'pièces'
            ELSE umProduit 
        END as umProduitDisplay 
        FROM produits WHERE statut = 0 ORDER BY actif DESC, date_creation DESC LIMIT :limit OFFSET :offset");
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $produits = $query->fetchAll(PDO::FETCH_ASSOC);

    // Compter les actifs pour la carte de stats
    $active_count_total = $pdo->query("SELECT COUNT(*) FROM produits WHERE actif = 1 AND statut = 0")->fetchColumn();

    // Statistiques par type de produit
    $produits_metres = $pdo->query("SELECT COUNT(*) FROM produits WHERE umProduit = 'metres' AND statut = 0")->fetchColumn();
    $produits_pieces = $pdo->query("SELECT COUNT(*) FROM produits WHERE umProduit = 'pieces' AND statut = 0")->fetchColumn();
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des produits: " . $e->getMessage(),
        'type' => "error"
    ];
    $active_count_total = 0;
    $produits_metres = 0;
    $produits_pieces = 0;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Gestion des produits - NGS (New Grace Service)</title>

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
        
        /* Badge styles */
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
        
        /* Unit options */
        .unit-option {
            position: relative;
            cursor: pointer;
        }

        .unit-option input[type="radio"]:checked~.checkmark {
            display: flex;
        }

        .checkmark {
            display: none;
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            background: #7B61FF;
            border-radius: 50%;
            color: white;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }

        .unit-option input[type="radio"]:checked~.unit-content {
            border-color: #7B61FF;
            background-color: rgba(123, 97, 255, 0.05);
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
            
            /* Unit options responsive */
            .grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
            
            .unit-option {
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 640px) {
            .grid-cols-4 {
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
            
            /* Table specific */
            .table-mobile-compact th:nth-child(3),
            .table-mobile-compact td:nth-child(3) {
                display: none;
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
            
            /* Hide type column on very small screens */
            .table-mobile-compact th:nth-child(4),
            .table-mobile-compact td:nth-child(4) {
                display: none;
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
            
            /* Unit options very small */
            .unit-option .unit-content {
                padding: 0.75rem !important;
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
                <a href="boutiques.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-store w-5 text-gray-300"></i>
                    <span>Boutiques</span>
                </a>
                <a href="produits.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-box w-5 text-white"></i>
                    <span>Produits</span>
                    <span class="notification-badge"><?= $total_produits ?></span>
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
                    <span>Rapports PDG</span>
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
                <a href="boutiques.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors" onclick="closeMobileMenu()">
                    <i class="fas fa-store w-5 text-gray-300"></i>
                    <span>Boutiques</span>
                </a>
                <a href="produits.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10" onclick="closeMobileMenu()">
                    <i class="fas fa-box w-5 text-white"></i>
                    <span>Produits</span>
                    <span class="notification-badge"><?= $total_produits ?></span>
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
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900 mobile-text-sm">Gestion des produits - NGS</h1>
                        <p class="text-gray-600 text-sm md:text-base mobile-text-xs">New Grace Service - Catalogue des produits</p>
                    </div>
                    <div class="flex items-center space-x-2 md:space-x-4 header-actions">
                        <button onclick="openProduitModal()"
                            class="px-3 md:px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300 mobile-w-full md:w-auto">
                            <i class="fas fa-plus"></i>
                            <span class="hidden md:inline">Nouveau produit</span>
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
                                <i class="fas fa-boxes text-blue-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-blue-600">Total</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $total_produits ?></h3>
                        <p class="text-gray-600 text-sm">Produits enregistrés</p>
                    </div>

                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-green-500 animate-fade-in mobile-card" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-green-100 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-green-600">Actifs</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $active_count_total ?></h3>
                        <p class="text-gray-600 text-sm">Produits disponibles</p>
                    </div>

                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-cyan-500 animate-fade-in mobile-card" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-cyan-100 flex items-center justify-center">
                                <i class="fas fa-ruler-combined text-cyan-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-cyan-600">Mètres</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $produits_metres ?></h3>
                        <p class="text-gray-600 text-sm">Rideaux (au mètre)</p>
                    </div>

                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-emerald-500 animate-fade-in mobile-card" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-cube text-emerald-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-emerald-600">Pièces</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $produits_pieces ?></h3>
                        <p class="text-gray-600 text-sm">Produits divers</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 mb-4 md:mb-6 animate-fade-in mobile-card" style="animation-delay: 0.4s">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 md:gap-4 search-container">
                        <div class="relative flex-1 max-w-lg">
                            <input type="text"
                                id="searchInput"
                                placeholder="Rechercher par Matricule ou Désignation..."
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
                        <h2 class="text-base md:text-lg font-semibold text-gray-900">Liste des produits - NGS</h2>
                    </div>

                    <div class="responsive-table table-container">
                        <table class="w-full min-w-[700px] mobile-text-sm table-mobile-compact" id="produitsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matricule</th>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider mobile-hide">Unité</th>
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <!-- <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th> -->
                                    <th class="px-3 md:px-6 py-2 md:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($produits as $index => $produit):
                                    $isRideau = substr($produit['matricule'], 0, 3) === 'Rid';
                                ?>
                                    <tr class="produit-row hover:bg-gray-50 transition-colors fade-in-row"
                                        data-produit-matricule="<?= htmlspecialchars(strtolower($produit['matricule'])) ?>"
                                        data-produit-designation="<?= htmlspecialchars(strtolower($produit['designation'])) ?>"
                                        style="animation-delay: <?= $index * 0.05 ?>s">
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm font-medium text-gray-900 produit-matricule">
                                            <div class="flex items-center">
                                                <div class="w-6 h-6 md:w-8 md:h-8 rounded-full <?= $isRideau ? 'bg-indigo-100' : 'bg-emerald-100' ?> flex items-center justify-center mr-2 md:mr-3">
                                                    <i class="fas fa-tag <?= $isRideau ? 'text-indigo-600' : 'text-emerald-600' ?> text-xs"></i>
                                                </div>
                                                <div>
                                                    <span class="font-mono font-bold text-xs md:text-sm truncate max-w-[100px] md:max-w-none"><?= htmlspecialchars($produit['matricule']) ?></span>
                                                    <div class="text-xs text-gray-500 mobile-text-xs">
                                                        <?= date('d/m/Y', strtotime($produit['date_creation'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 md:px-6 py-2 md:py-4 text-xs md:text-sm text-gray-900 produit-designation">
                                            <span class="truncate max-w-[150px] md:max-w-none inline-block"><?= htmlspecialchars($produit['designation']) ?></span>
                                        </td>
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm text-gray-900 mobile-hide">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $produit['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                                <i class="<?= $produit['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1 text-xs"></i>
                                                <?= $produit['umProduitDisplay'] ?>
                                            </span>
                                        </td>
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium badge-rideau">
                                                <i class="fas <?= $isRideau ? 'fa-window-maximize' : 'fa-box' ?> mr-1 text-xs"></i>
                                                <?= $isRideau ? 'Rideau' : 'Produit' ?>
                                            </span>
                                        </td>
                                        <!-- <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap">
                                            <span class="status-badge <?= $produit['actif'] ? 'status-active' : 'status-inactive' ?> inline-flex items-center text-xs">
                                                <i class="fas fa-circle text-[10px] md:text-xs mr-1"></i>
                                                <?= $produit['actif'] ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td> -->
                                        <td class="px-3 md:px-6 py-2 md:py-4 whitespace-nowrap text-xs md:text-sm font-medium">
                                            <div class="flex space-x-1 md:space-x-2 action-buttons action-buttons-mobile">
                                                <button onclick="openProduitModal('<?= htmlspecialchars(addslashes($produit['matricule'])) ?>'); return false;"
                                                    class="action-btn inline-flex items-center px-2 md:px-3 py-1 md:py-2 border border-transparent text-xs md:text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    <span class="hidden md:inline">Modifier</span>
                                                    <span class="md:hidden">Edit</span>
                                                </button>
                                                <button onclick="openToggleModal('<?= htmlspecialchars(addslashes($produit['matricule'])) ?>', '<?= htmlspecialchars(addslashes($produit['designation'])) ?>', <?= $produit['actif'] ?>); return false;"
                                                    class="action-btn inline-flex items-center px-2 md:px-3 py-1 md:py-2 border border-transparent text-xs md:text-sm leading-4 font-medium rounded-md text-white <?= $produit['actif'] ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700' ?> focus:outline-none focus:ring-2 focus:ring-offset-2 <?= $produit['actif'] ? 'focus:ring-orange-500' : 'focus:ring-green-500' ?> transition-colors">
                                                    <i class="fas fa-power-off mr-1"></i>
                                                    <span class="hidden md:inline"><?= $produit['actif'] ? 'Désactiver' : 'Activer' ?></span>
                                                    <span class="md:hidden"><?= $produit['actif'] ? 'Off' : 'On' ?></span>
                                                </button>
                                                <button onclick="openDeleteModal('<?= htmlspecialchars(addslashes($produit['matricule'])) ?>', '<?= htmlspecialchars(addslashes($produit['designation'])) ?>'); return false;"
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
                            <p class="text-gray-600 text-sm">Aucun produit ne correspond à votre recherche</p>
                        </div>
                    </div>

                    <?php if (empty($produits) && $page == 1): ?>
                        <div class="text-center py-8 md:py-12">
                            <div class="bg-gray-50 rounded-xl md:rounded-2xl p-6 md:p-8 max-w-md mx-auto shadow-soft">
                                <i class="fas fa-boxes text-4xl md:text-6xl text-gray-400 mb-3 md:mb-4"></i>
                                <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">Aucun produit enregistré</h3>
                                <p class="text-gray-600 text-sm mb-4">Commencez par ajouter votre premier produit</p>
                                <button onclick="openProduitModal()"
                                    class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 mx-auto shadow-md text-sm">
                                    <i class="fas fa-plus"></i>
                                    <span>Ajouter un produit</span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-4 md:px-6 py-3 md:py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 md:gap-0">
                                <div class="text-xs md:text-sm text-gray-700 pagination-info hidden sm:block">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_produits) ?></span> sur
                                    <span class="font-medium"><?= $total_produits ?></span> produits
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

    <!-- Produit Modal -->
    <div id="produitModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-4 md:p-6">
            <div class="flex justify-between items-center border-b pb-2 md:pb-3 mb-3 md:mb-4">
                <h3 class="text-lg md:text-xl font-bold text-gray-900" id="modalTitle">Ajouter un nouveau produit - NGS</h3>
                <button onclick="closeProduitModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl md:text-2xl"></i>
                </button>
            </div>

            <form id="produitForm" method="POST" action="../models/traitement/produit-post.php">
                <input type="hidden" name="matricule_original" id="matriculeOriginal">

                <div class="space-y-3 md:space-y-4">
                    <div id="matriculeField" style="display: none;">
                        <label for="matricule" class="block text-sm font-medium text-gray-700 mb-1">Matricule</label>
                        <input type="text" name="matricule" id="matricule" readonly
                            class="w-full border-gray-300 bg-gray-100 rounded-lg shadow-sm p-2 md:p-3 cursor-not-allowed">
                        <p class="text-xs text-gray-500 mt-1">Identifiant unique du produit (généré automatiquement)</p>
                    </div>

                    <div>
                        <label for="designation" class="block text-sm font-medium text-gray-700 mb-1">Désignation du produit *</label>
                        <input type="text" name="designation" id="designation" required
                            class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-2 md:p-3"
                            placeholder="Ex: Rideau en velours rouge, Coussin décoratif...">
                    </div>

                    <!-- CHAMP : Unité de mesure -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unité de mesure *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
                            <label class="unit-option">
                                <input type="radio" name="umProduit" value="metres" class="sr-only" id="umMetres">
                                <div class="unit-content border-2 border-gray-300 rounded-lg p-3 md:p-4 hover:border-blue-500 transition-colors h-full">
                                    <div class="flex items-center space-x-2 md:space-x-3">
                                        <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-ruler-combined text-blue-600 text-sm md:text-base"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 text-sm md:text-base">Mètres</p>
                                            <p class="text-xs text-gray-500">Pour les rideaux</p>
                                        </div>
                                    </div>
                                    <div class="checkmark">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="mt-2 text-xs text-blue-600">
                                        Matricule: Rid-XXX
                                    </div>
                                </div>
                            </label>
                            <label class="unit-option">
                                <input type="radio" name="umProduit" value="pieces" class="sr-only" id="umPieces" checked>
                                <div class="unit-content border-2 border-gray-300 rounded-lg p-3 md:p-4 hover:border-green-500 transition-colors h-full">
                                    <div class="flex items-center space-x-2 md:space-x-3">
                                        <div class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-green-100 flex items-center justify-center">
                                            <i class="fas fa-box text-green-600 text-sm md:text-base"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 text-sm md:text-base">Pièces</p>
                                            <p class="text-xs text-gray-500">Autres produits</p>
                                        </div>
                                    </div>
                                    <div class="checkmark">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="mt-2 text-xs text-green-600">
                                        Matricule: Pcs-XXX
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <input type="checkbox" name="actif" id="actif" value="1" checked
                            class="h-4 w-4 text-secondary border-gray-300 rounded focus:ring-secondary">
                        <label for="actif" class="text-sm font-medium text-gray-700">Produit actif (disponible à la vente)</label>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 md:p-4">
                        <div class="flex items-start space-x-2 md:space-x-3">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-blue-700 font-medium">Informations importantes</p>
                                <ul class="text-xs text-blue-600 mt-1 list-disc pl-4 space-y-1">
                                    <li>Le matricule sera généré automatiquement selon l'unité choisie</li>
                                    <li><strong>Mètres :</strong> Pour les rideaux (vente au mètre linéaire) → Matricule: Rid-001, Rid-002...</li>
                                    <li><strong>Pièces :</strong> Pour les autres produits → Matricule: Pcs-001, Pcs-002...</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 md:mt-6 flex justify-end space-x-2 md:space-x-3">
                    <button type="button" onclick="closeProduitModal()"
                        class="px-3 md:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm">
                        Annuler
                    </button>
                    <button type="submit" name="ajouter_produit" id="submitButton"
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
                <p class="text-gray-600 text-sm md:text-base" id="toggleModalText">Le statut du produit **Designation** va être modifié.</p>
            </div>

            <form id="toggleForm" method="POST" action="../models/traitement/produit-post.php" class="mt-4 md:mt-6 flex justify-center space-x-2 md:space-x-3">
                <input type="hidden" name="matricule" id="toggleProduitMatricule">
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
                <p class="text-gray-600 text-sm md:text-base mb-3 md:mb-4" id="deleteModalText">Vous êtes sur le point d'archiver le produit **Designation**. Il ne sera plus visible, mais ses données resteront en base de données (Soft Delete).</p>
            </div>

            <form id="deleteForm" method="POST" action="../models/traitement/produit-post.php" class="mt-4 md:mt-6 flex justify-center space-x-2 md:space-x-3">
                <input type="hidden" name="matricule" id="deleteProduitMatricule">
                <button type="button" onclick="closeDeleteModal()"
                    class="px-3 md:px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors text-sm">
                    Annuler
                </button>
                <button type="submit" name="supprimer_produit"
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
        const produitModal = document.getElementById('produitModal');
        const modalTitle = document.getElementById('modalTitle');
        const produitForm = document.getElementById('produitForm');
        const submitButton = document.getElementById('submitButton');
        const matriculeOriginal = document.getElementById('matriculeOriginal');
        const matriculeInput = document.getElementById('matricule');
        const matriculeField = document.getElementById('matriculeField');

        function openProduitModal(matricule = null) {
            produitForm.reset();
            closeMobileMenu();
            
            if (matricule) {
                // Mode Modification
                modalTitle.textContent = "Modifier le produit " + matricule + " - NGS";
                submitButton.textContent = "Modifier le produit";
                submitButton.name = 'modifier_produit';
                matriculeOriginal.value = matricule;

                // Afficher le champ matricule en lecture seule
                matriculeField.style.display = 'block';
                matriculeInput.value = matricule;
                matriculeInput.readOnly = true;

                fetch('produits.php?action=get_produit&matricule=' + encodeURIComponent(matricule))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('designation').value = data.produit.designation;
                            document.getElementById('actif').checked = data.produit.actif == 1;

                            // Définir l'unité de mesure
                            if (data.produit.umProduit) {
                                if (data.produit.umProduit === 'metres') {
                                    document.getElementById('umMetres').checked = true;
                                } else {
                                    document.getElementById('umPieces').checked = true;
                                }
                                // Forcer l'affichage des checkmarks
                                updateUnitOptions();
                            }
                        } else {
                            alert(data.message);
                            closeProduitModal();
                        }
                    })
                    .catch(error => {
                        console.error('Erreur AJAX:', error);
                        alert("Impossible de charger les données du produit.");
                        closeProduitModal();
                    });

            } else {
                // Mode Ajout
                modalTitle.textContent = "Ajouter un nouveau produit - NGS";
                submitButton.textContent = "Enregistrer le produit";
                submitButton.name = 'ajouter_produit';
                matriculeOriginal.value = '';

                // Cacher le champ matricule (généré automatiquement)
                matriculeField.style.display = 'none';
                document.getElementById('actif').checked = true;
                // Par défaut : pièces
                document.getElementById('umPieces').checked = true;
                updateUnitOptions();
            }

            produitModal.classList.add('show');
        }

        function closeProduitModal() {
            produitModal.classList.remove('show');
        }

        // Fonction pour mettre à jour l'affichage des options d'unité
        function updateUnitOptions() {
            document.querySelectorAll('.unit-option input[type="radio"]').forEach(radio => {
                if (radio.checked) {
                    const parent = radio.closest('.unit-option');
                    const content = parent.querySelector('.unit-content');
                    // Réinitialiser tous les styles
                    document.querySelectorAll('.unit-option .unit-content').forEach(content => {
                        content.classList.remove('border-blue-500', 'border-green-500', 'bg-blue-50', 'bg-green-50');
                    });
                    // Appliquer le style approprié
                    if (radio.value === 'metres') {
                        content.classList.add('border-blue-500', 'bg-blue-50');
                    } else {
                        content.classList.add('border-green-500', 'bg-green-50');
                    }
                }
            });
        }

        // Gestion des changements d'état pour les options d'unité
        document.querySelectorAll('.unit-option input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', updateUnitOptions);
        });

        // Initialiser les options d'unité au chargement
        document.addEventListener('DOMContentLoaded', updateUnitOptions);

        // --- GESTION DE LA MODALE TOGGLE ---
        const toggleModal = document.getElementById('toggleModal');
        const toggleModalTitle = document.getElementById('toggleModalTitle');
        const toggleModalText = document.getElementById('toggleModalText');
        const toggleIcon = document.getElementById('toggleIcon');
        const toggleProduitMatricule = document.getElementById('toggleProduitMatricule');
        const toggleConfirmButton = document.getElementById('toggleConfirmButton');

        function openToggleModal(matricule, designation, actif) {
            const action = actif ? 'désactiver' : 'activer';
            const iconClass = actif ? 'text-orange-500' : 'text-green-500';
            const buttonColor = actif ? 'bg-gradient-to-r from-orange-600 to-orange-700' : 'bg-gradient-to-r from-green-600 to-green-700';
            const buttonText = actif ? 'Oui, Désactiver' : 'Oui, Activer';

            toggleModalTitle.textContent = "Confirmer la " + action + " - NGS";
            toggleModalText.innerHTML = `Le statut du produit <strong>${designation}</strong> (${matricule}) va passer à <strong>${action}</strong>.<br>Voulez-vous confirmer cette action ?`;

            toggleIcon.className = `fas fa-power-off text-4xl md:text-5xl mb-3 md:mb-4 ${iconClass}`;

            toggleConfirmButton.textContent = buttonText;
            toggleConfirmButton.className = `px-3 md:px-4 py-2 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md text-sm ${buttonColor}`;

            toggleProduitMatricule.value = matricule;
            toggleModal.classList.add('show');
        }

        function closeToggleModal() {
            toggleModal.classList.remove('show');
        }

        // --- GESTION DE LA MODALE DELETE ---
        const deleteModal = document.getElementById('deleteModal');
        const deleteModalText = document.getElementById('deleteModalText');
        const deleteProduitMatricule = document.getElementById('deleteProduitMatricule');

        function openDeleteModal(matricule, designation) {
            deleteModalText.innerHTML = `Vous êtes sur le point d'archiver le produit <strong>${designation}</strong> (${matricule}). Il ne sera plus visible, mais ses données resteront en base de données (Soft Delete). Cette action est réversible uniquement par un administrateur système. Confirmez-vous ?`;
            deleteProduitMatricule.value = matricule;
            deleteModal.classList.add('show');
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
        }

        // --- GESTION DE LA RECHERCHE ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.produit-row');
            let found = false;

            rows.forEach(row => {
                const matricule = row.dataset.produitMatricule;
                const designation = row.dataset.produitDesignation;

                if (matricule.includes(searchTerm) || designation.includes(searchTerm)) {
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
                closeProduitModal();
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
                closeProduitModal();
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
            
            // Improve touch for unit options
            const unitOptions = document.querySelectorAll('.unit-option');
            unitOptions.forEach(option => {
                option.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                option.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }
    </script>
</body>

</html>