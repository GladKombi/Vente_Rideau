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
    $queryBoutiques = $pdo->prepare("SELECT id, nom FROM boutiques WHERE statut = 0 AND actif = 1 ORDER BY nom");
    $queryBoutiques->execute();
    $boutiques = $queryBoutiques->fetchAll(PDO::FETCH_ASSOC);
    
    $queryProduits = $pdo->prepare("SELECT matricule, designation, umProduit FROM produits WHERE statut = 0 AND actif = 1 ORDER BY designation");
    $queryProduits->execute();
    $produits = $queryProduits->fetchAll(PDO::FETCH_ASSOC);
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
                   p.designation as produit_designation,
                   p.umProduit as produit_unite
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
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE statut = 0 AND type_mouvement = 'approvisionnement'");
    $countQuery->execute();
    $total_stocks = $countQuery->fetchColumn();
    $totalPages = ceil($total_stocks / $limit);

    // Requête paginée avec jointures - SEULEMENT LES APPROVISIONNEMENTS
    $query = $pdo->prepare("
        SELECT s.*, 
               b.nom as boutique_nom, 
               p.designation as produit_designation,
               p.umProduit as produit_unite
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
    $queryQuantite = $pdo->prepare("SELECT SUM(quantite) FROM stock WHERE statut = 0 AND type_mouvement = 'approvisionnement'");
    $queryQuantite->execute();
    $total_quantite = $queryQuantite->fetchColumn();
    $total_quantite = $total_quantite ? $total_quantite : 0;
    
    // Calculer la valeur totale du stock (quantité * prix du stock)
    $queryValeur = $pdo->prepare("
        SELECT SUM(s.quantite * s.prix) 
        FROM stock s 
        WHERE s.statut = 0 AND s.type_mouvement = 'approvisionnement'
    ");
    $queryValeur->execute();
    $total_valeur_stock = $queryValeur->fetchColumn();
    $total_valeur_stock = $total_valeur_stock ? $total_valeur_stock : 0;
    
    // Compter les produits par type d'unité
    $queryStatsUnite = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN p.umProduit = 'metres' THEN s.produit_matricule END) as produits_metres,
            COUNT(DISTINCT CASE WHEN p.umProduit = 'pieces' THEN s.produit_matricule END) as produits_pieces,
            SUM(CASE WHEN p.umProduit = 'metres' THEN s.quantite ELSE 0 END) as quantite_metres,
            SUM(CASE WHEN p.umProduit = 'pieces' THEN s.quantite ELSE 0 END) as quantite_pieces
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE s.statut = 0 AND s.type_mouvement = 'approvisionnement'
    ");
    $queryStatsUnite->execute();
    $statsUnite = $queryStatsUnite->fetch(PDO::FETCH_ASSOC);
    
    $produits_metres = $statsUnite['produits_metres'] ?? 0;
    $produits_pieces = $statsUnite['produits_pieces'] ?? 0;
    $quantite_metres = $statsUnite['quantite_metres'] ?? 0;
    $quantite_pieces = $statsUnite['quantite_pieces'] ?? 0;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des stocks: " . $e->getMessage(),
        'type' => "error"
    ];
    $total_quantite = 0;
    $total_valeur_stock = 0;
    $produits_metres = 0;
    $produits_pieces = 0;
    $quantite_metres = 0;
    $quantite_pieces = 0;
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
        
        /* Styles pour l'affichage des unités dans les champs */
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
            margin-top: 8px;
        }
        
        .info-box p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
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
                <a href="rapports_pdg.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-chart-bar w-5 text-white"></i>
                    <span>Rapports PDG</span>
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

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8 stats-grid">
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

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-cyan-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-cyan-100 flex items-center justify-center">
                                <i class="fas fa-ruler-combined text-cyan-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-cyan-600">Rideaux (m)</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($quantite_metres, 3) ?></h3>
                        <p class="text-gray-600"><?= $produits_metres ?> produits</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-emerald-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-cube text-emerald-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-emerald-600">Produits (pce)</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($quantite_pieces, 3) ?></h3>
                        <p class="text-gray-600"><?= $produits_pieces ?> produits</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Valeur stock</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($total_valeur_stock, 2) ?> $</h3>
                        <p class="text-gray-600">Valeur totale</p>
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
                        <table class="w-full min-w-[1000px]" id="stocksTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutique</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seuil d'alerte</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
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
                                        
                                        // Déterminer si c'est un rideau ou un produit
                                        $isRideau = substr($stock['produit_matricule'], 0, 3) === 'Rid';
                                        $uniteClass = $stock['produit_unite'] == 'metres' ? 'badge-metres' : 'badge-pieces';
                                        $uniteText = $stock['produit_unite'] == 'metres' ? 'mètres' : 'pièces';
                                        $uniteIcon = $stock['produit_unite'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube';
                                        $typeClass = $isRideau ? 'badge-rideau' : 'badge-pieces';
                                        $typeText = $isRideau ? 'Rideau' : 'Produit';
                                        $typeIcon = $isRideau ? 'fas fa-window-maximize' : 'fas fa-box';
                                        ?>
                                        <tr class="stock-row hover:bg-gray-50 transition-colors fade-in-row"
                                            data-stock-id="<?= htmlspecialchars($stock['id']) ?>"
                                            data-boutique-nom="<?= htmlspecialchars(strtolower($stock['boutique_nom'])) ?>"
                                            data-produit-designation="<?= htmlspecialchars(strtolower($stock['produit_designation'])) ?>"
                                            data-produit-unite="<?= htmlspecialchars(strtolower($stock['produit_unite'])) ?>"
                                            data-prix="<?= htmlspecialchars($stock['prix']) ?>"
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
                                                    <div class="flex items-center">
                                                        <span class="font-medium"><?= htmlspecialchars($stock['produit_designation']) ?></span>
                                                        <span class="badge-unite ml-2 <?= $uniteClass ?>">
                                                            <i class="<?= $uniteIcon ?> mr-1 text-xs"></i>
                                                            <?= $uniteText ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center mt-1">
                                                        <span class="text-xs text-gray-500 font-mono">
                                                            <?= htmlspecialchars($stock['produit_matricule']) ?>
                                                        </span>
                                                        <span class="badge-unite ml-2 <?= $typeClass ?>">
                                                            <i class="<?= $typeIcon ?> mr-1 text-xs"></i>
                                                            <?= $typeText ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-bold"><?= number_format($stock['quantite'], 3) ?></span>
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
                                        <td colspan="9" class="px-6 py-8 text-center">
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
                <input type="hidden" name="unite_produit" id="uniteProduit">

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
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                                onchange="updateUniteInfo()">
                            <option value="">Sélectionnez un produit</option>
                            <?php foreach ($produits as $produit): 
                                $uniteText = $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                $isRideau = substr($produit['matricule'], 0, 3) === 'Rid';
                                $typeText = $isRideau ? 'Rideau' : 'Produit';
                            ?>
                                <option value="<?= htmlspecialchars($produit['matricule']) ?>" 
                                        data-unite="<?= htmlspecialchars($produit['umProduit']) ?>"
                                        data-type="<?= htmlspecialchars($typeText) ?>">
                                    <?= htmlspecialchars($produit['designation']) ?> 
                                    (<?= htmlspecialchars($produit['matricule']) ?> - <?= $uniteText ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="uniteInfo" class="info-box hidden">
                            <p id="uniteText"></p>
                        </div>
                    </div>
                    
                    <div>
                        <label for="prix" class="block text-sm font-medium text-gray-700 mb-1">Prix d'achat ($) *</label>
                        <div class="input-with-unite">
                            <input type="number" name="prix" id="prix" required step="0.001" min="0"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                                   placeholder="Ex: 45.500">
                            <span id="prixUniteLabel" class="unite-label">$ / unité</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Prix d'achat unitaire pour ce stock</p>
                    </div>
                    
                    <div>
                        <label for="quantite" class="block text-sm font-medium text-gray-700 mb-1">Quantité *</label>
                        <div class="input-with-unite">
                            <input type="number" name="quantite" id="quantite" required step="0.001" min="0"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                                   placeholder="Ex: 10.500">
                            <span id="quantiteUniteLabel" class="unite-label">unités</span>
                        </div>
                        <div id="quantiteInfo" class="mt-2 text-sm text-gray-600 hidden">
                            <p id="quantiteExplication"></p>
                        </div>
                    </div>
                    
                    <div>
                        <label for="seuil_alerte_stock" class="block text-sm font-medium text-gray-700 mb-1">Seuil d'alerte *</label>
                        <div class="input-with-unite">
                            <input type="number" name="seuil_alerte_stock" id="seuil_alerte_stock" required min="0"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                                   placeholder="Ex: 5" value="5">
                            <span id="seuilUniteLabel" class="unite-label">unités</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Alerte lorsque la quantité tombe en dessous de ce seuil</p>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-blue-700 font-medium">Note sur les unités de mesure</p>
                                <ul class="text-xs text-blue-600 mt-1 list-disc pl-4 space-y-1">
                                    <li><strong>Mètres :</strong> Utilisé pour les rideaux (vente au mètre linéaire)</li>
                                    <li><strong>Pièces :</strong> Utilisé pour les autres produits (coussin, accessoires...)</li>
                                    <li>L'unité est définie au niveau du produit et ne peut pas être modifiée ici</li>
                                </ul>
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
        const produitSelect = document.getElementById('produit_matricule');
        const uniteProduitInput = document.getElementById('uniteProduit');
        const prixUniteLabel = document.getElementById('prixUniteLabel');
        const quantiteUniteLabel = document.getElementById('quantiteUniteLabel');
        const seuilUniteLabel = document.getElementById('seuilUniteLabel');
        const uniteInfo = document.getElementById('uniteInfo');
        const uniteText = document.getElementById('uniteText');
        const quantiteInfo = document.getElementById('quantiteInfo');
        const quantiteExplication = document.getElementById('quantiteExplication');

        // Fonction pour mettre à jour les informations d'unité
        function updateUniteInfo() {
            const selectedOption = produitSelect.options[produitSelect.selectedIndex];
            const unite = selectedOption ? selectedOption.getAttribute('data-unite') : '';
            const type = selectedOption ? selectedOption.getAttribute('data-type') : '';
            
            if (unite) {
                // Mettre à jour le champ caché
                uniteProduitInput.value = unite;
                
                // Mettre à jour les labels d'unité
                const uniteDisplay = unite === 'metres' ? 'mètres' : 'pièces';
                prixUniteLabel.textContent = `$ / ${uniteDisplay}`;
                quantiteUniteLabel.textContent = uniteDisplay;
                seuilUniteLabel.textContent = uniteDisplay;
                
                // Afficher les informations supplémentaires
                uniteInfo.classList.remove('hidden');
                uniteText.innerHTML = `<strong>Type :</strong> ${type} | <strong>Unité :</strong> ${uniteDisplay}`;
                
                // Mettre à jour l'explication de la quantité
                quantiteInfo.classList.remove('hidden');
                if (unite === 'metres') {
                    quantiteExplication.textContent = "Pour les rideaux : indiquez la longueur totale en mètres (décimal autorisé : ex: 2.50)";
                } else {
                    quantiteExplication.textContent = "Pour les autres produits : indiquez le nombre de pièces (entier recommandé)";
                }
            } else {
                // Réinitialiser si aucun produit sélectionné
                uniteProduitInput.value = '';
                prixUniteLabel.textContent = '$ / unité';
                quantiteUniteLabel.textContent = 'unités';
                seuilUniteLabel.textContent = 'unités';
                uniteInfo.classList.add('hidden');
                quantiteInfo.classList.add('hidden');
            }
        }

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
                            document.getElementById('prix').value = data.stock.prix;
                            document.getElementById('quantite').value = data.stock.quantite;
                            document.getElementById('seuil_alerte_stock').value = data.stock.seuil_alerte_stock;
                            
                            // Mettre à jour les informations d'unité
                            updateUniteInfo();
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
                
                // Réinitialiser les informations d'unité
                updateUniteInfo();
            }

            stockModal.classList.add('show');
        }

        function closeStockModal() {
            stockModal.classList.remove('show');
        }

        // Écouter les changements sur le select de produit
        produitSelect.addEventListener('change', updateUniteInfo);

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
                const produitUnite = row.dataset.produitUnite;
                const prix = row.dataset.prix;

                if (stockId.includes(searchTerm) || 
                    boutiqueNom.includes(searchTerm) || 
                    produitDesignation.includes(searchTerm) ||
                    produitUnite.includes(searchTerm) ||
                    prix.includes(searchTerm)) {
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
            const quantite = parseFloat(document.getElementById('quantite').value);
            const seuil = parseFloat(document.getElementById('seuil_alerte_stock').value);
            const prix = parseFloat(document.getElementById('prix').value);
            const produit = document.getElementById('produit_matricule').value;
            const unite = document.getElementById('uniteProduit').value;
            
            // Validation basique
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
            
            if (prix < 0) {
                e.preventDefault();
                alert('Le prix ne peut pas être négatif.');
                return false;
            }
            
            // Validation spécifique selon l'unité
            if (unite === 'pieces') {
                // Pour les pièces, la quantité doit être un nombre entier (ou au moins vérifier qu'elle n'est pas trop précise)
                if (quantite % 1 !== 0) {
                    e.preventDefault();
                    alert('Pour les produits à la pièce, la quantité doit être un nombre entier.');
                    return false;
                }
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

        // --- CALCUL AUTOMATIQUE DE LA VALEUR ---
        document.getElementById('quantite').addEventListener('input', calculerValeur);
        document.getElementById('prix').addEventListener('input', calculerValeur);

        function calculerValeur() {
            const quantite = parseFloat(document.getElementById('quantite').value) || 0;
            const prix = parseFloat(document.getElementById('prix').value) || 0;
            const valeur = quantite * prix;
            
            // Mettre à jour un éventuel affichage de la valeur
            const valeurElement = document.getElementById('valeur-calcul');
            if (valeurElement) {
                valeurElement.textContent = valeur.toFixed(2) + ' $';
            }
        }
    </script>
</body>
</html>