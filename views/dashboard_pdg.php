<?php
# Connexion à la Db
include '../connexion/connexion.php';

# Vérification de l'authentification PDG
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'PDG') {
    header('Location: ../login.php');
    exit;
}

// Récupérer les statistiques globales
$total_boutiques = 0;
$ca_total = 0;
$produits_total = 0;
$alertes_stock = 0;
$dernieres_ventes = [];
$boutiques_ca = [];
$produits_populaires = [];

try {
    // Nombre total de boutiques actives
    $stmt = $pdo->query("SELECT COUNT(*) as total_boutiques FROM boutiques WHERE statut = 0 AND actif = 1");
    $result = $stmt->fetch();
    $total_boutiques = $result['total_boutiques'] ?? 0;

    // Chiffre d'affaires total (somme des paiements)
    $stmt = $pdo->query("SELECT SUM(montant) as ca_total FROM paiements WHERE statut = 0");
    $result = $stmt->fetch();
    $ca_total = $result['ca_total'] ?? 0;

    // Nombre de produits distincts en stock (quantité > 0)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT produit_matricule) as produits_total 
        FROM stock 
        WHERE quantite > 0 AND statut = 0
    ");
    $result = $stmt->fetch();
    $produits_total = $result['produits_total'] ?? 0;

    // Alertes stock bas (quantité <= seuil d'alerte)
    $stmt = $pdo->query("
        SELECT COUNT(*) as alertes_stock 
        FROM stock 
        WHERE quantite <= seuil_alerte_stock 
        AND quantite > 0 
        AND statut = 0
    ");
    $result = $stmt->fetch();
    $alertes_stock = $result['alertes_stock'] ?? 0;

    // Derniers paiements
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.date,
            p.montant,
            p.commandes_id,
            (SELECT nom FROM boutiques WHERE id = (
                SELECT boutique_id FROM stock WHERE id = (
                    SELECT stock_id FROM commande_produits WHERE commande_id = p.commandes_id LIMIT 1
                ) LIMIT 1
            )) as boutique_nom
        FROM paiements p
        WHERE p.statut = 0
        ORDER BY p.date DESC 
        LIMIT 5
    ");
    $dernieres_ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Boutiques avec statistiques
    $stmt = $pdo->query("
        SELECT 
            b.*,
            (SELECT COUNT(*) FROM stock WHERE boutique_id = b.id AND quantite > 0 AND statut = 0) as produits_en_stock,
            (SELECT COUNT(*) FROM stock WHERE boutique_id = b.id AND quantite <= seuil_alerte_stock AND quantite > 0 AND statut = 0) as alertes_boutique
        FROM boutiques b 
        WHERE b.statut = 0 AND b.actif = 1
        ORDER BY b.nom
    ");
    $boutiques_ca = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Produits les plus vendus
    $stmt = $pdo->query("
        SELECT 
            p.matricule,
            p.designation,
            p.umProduit,
            COALESCE(SUM(cp.quantite), 0) as total_vendu,
            COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) as chiffre_affaires
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE cp.statut = 0
        GROUP BY p.matricule, p.designation, p.umProduit
        ORDER BY total_vendu DESC 
        LIMIT 5
    ");
    $produits_populaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur dashboard PDG: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des données: " . $e->getMessage();
}

// Calculer les tendances mensuelles
$ca_mois_courant = 0;
$ca_mois_precedent = 0;
$variation_ca = 0;

try {
    // CA du mois courant
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(montant), 0) as ca_mois 
        FROM paiements 
        WHERE statut = 0 
        AND MONTH(date) = MONTH(CURRENT_DATE()) 
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $result = $stmt->fetch();
    $ca_mois_courant = $result['ca_mois'] ?? 0;

    // CA du mois précédent
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(montant), 0) as ca_mois 
        FROM paiements 
        WHERE statut = 0 
        AND MONTH(date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
        AND YEAR(date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    ");
    $result = $stmt->fetch();
    $ca_mois_precedent = $result['ca_mois'] ?? 0;

    // Calculer la variation
    if ($ca_mois_precedent > 0) {
        $variation_ca = (($ca_mois_courant - $ca_mois_precedent) / $ca_mois_precedent) * 100;
    } elseif ($ca_mois_courant > 0) {
        $variation_ca = 100;
    }

} catch (PDOException $e) {
    error_log("Erreur calcul tendances: " . $e->getMessage());
}

// Calculer les produits actifs
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE statut = 0 AND actif = 1");
    $total_produits = $stmt->fetch()['total'] ?? 0;
    $pourcentage_produits = $total_produits > 0 ? ($produits_total / $total_produits) * 100 : 0;
} catch (Exception $e) {
    $total_produits = 0;
    $pourcentage_produits = 0;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Tableau de bord - PDG <?= htmlspecialchars($_SESSION['user_name'] ?? 'NGS') ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0A2540;
            --secondary: #7B61FF;
            --accent: #00D4AA;
        }
        
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #F8FAFC;
            overflow-x: hidden;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #0A2540 0%, #1E3A5F 100%);
        }
        
        .gradient-success {
            background: linear-gradient(90deg, #10B981 0%, #059669 100%); 
        }
        
        .gradient-warning {
            background: linear-gradient(90deg, #F59E0B 0%, #D97706 100%); 
        }
        
        .gradient-danger {
            background: linear-gradient(90deg, #EF4444 0%, #DC2626 100%); 
        }
        
        .gradient-info {
            background: linear-gradient(90deg, #3B82F6 0%, #1D4ED8 100%); 
        }
        
        .shadow-soft {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .badge-success { background-color: #D1FAE5; color: #065F46; }
        .badge-warning { background-color: #FEF3C7; color: #92400E; }
        .badge-danger { background-color: #FEE2E2; color: #991B1B; }
        
        .stats-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Mobile optimisations */
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
        
        @media (max-width: 768px) {
            .mobile-menu-button {
                display: block;
            }
            
            aside:not(.mobile-sidebar) {
                display: none;
            }
            
            .flex > .flex-1 {
                width: 100%;
                margin-left: 0;
            }
            
            header {
                padding: 1rem !important;
            }
            
            .header-actions {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .header-actions a {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }
            
            main {
                padding: 1rem !important;
            }
            
            .grid-cols-1 {
                grid-template-columns: 1fr !important;
            }
            
            .grid-cols-2 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .grid-cols-4 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .stats-card {
                padding: 1rem !important;
            }
            
            .stats-card .text-2xl {
                font-size: 1.5rem;
            }
            
            .mobile-hide {
                display: none;
            }
            
            .mobile-show {
                display: block;
            }
            
            .mobile-text-sm {
                font-size: 0.875rem;
            }
            
            .mobile-p-2 {
                padding: 0.5rem;
            }
            
            .mobile-p-3 {
                padding: 0.75rem;
            }
            
            .mobile-space-y-2 > * + * {
                margin-top: 0.5rem;
            }
            
            .mobile-flex-col {
                flex-direction: column;
            }
            
            .mobile-gap-2 {
                gap: 0.5rem;
            }
            
            .mobile-gap-4 {
                gap: 1rem;
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
            
            .stats-card {
                min-height: 120px;
            }
            
            .stats-card .w-12 {
                width: 2.5rem;
                height: 2.5rem;
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
        }
        
        @media (max-width: 480px) {
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            
            .header-actions a {
                width: 100%;
                justify-content: center;
                margin-bottom: 0.5rem;
            }
            
            .mobile-sidebar {
                width: 100%;
            }
            
            .stats-card .text-2xl {
                font-size: 1.25rem;
            }
            
            .product-info {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .product-info > div {
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Mobile Menu Button -->
    <div class="mobile-menu-button lg:hidden fixed top-4 left-4 z-50">
        <button id="mobileMenuToggle" class="w-10 h-10 rounded-lg bg-white shadow-md flex items-center justify-center">
            <i class="fas fa-bars text-gray-700"></i>
        </button>
    </div>
    
    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="mobile-overlay lg:hidden"></div>
    
    <div class="flex">
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:block w-64 gradient-bg text-white min-h-screen fixed left-0 top-0 h-full">
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center">
                        <span class="font-bold">NGS</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold truncate">NGS Rideaux</h1>
                        <p class="text-xs text-gray-300">Dashboard PDG</p>
                    </div>
                </div>
            </div>

            <nav class="p-4 space-y-1">
                <a href="dashboard_pdg.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-chart-line w-5"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-store w-5"></i>
                    <span>Boutiques</span>
                    <?php if ($total_boutiques > 0): ?>
                    <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $total_boutiques ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="produits.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-box w-5"></i>
                    <span>Produits</span>
                </a>
                <a href="stocks.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-warehouse w-5"></i>
                    <span>Stocks</span>
                    <?php if ($alertes_stock > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $alertes_stock ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="transferts.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="utilisateurs.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-users w-5"></i>
                    <span>Utilisateurs</span>
                </a>
                <a href="rapports_pdg.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-cog w-5"></i>
                    <span>Rapports</span>
                </a>
            </nav>

            <div class="p-4 border-t border-white/10 mt-auto absolute bottom-0 w-full">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Mobile Sidebar -->
        <aside class="mobile-sidebar lg:hidden gradient-bg text-white">
            <div class="p-6 border-b border-white/10 flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center">
                        <span class="font-bold">NGS</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold truncate">NGS Rideaux</h1>
                        <p class="text-xs text-gray-300">Dashboard PDG</p>
                    </div>
                </div>
                <button id="closeMobileMenu" class="text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <nav class="p-4 space-y-1">
                <a href="dashboard_pdg.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white/10" onclick="closeMobileMenu()">
                    <i class="fas fa-chart-line w-5"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5" onclick="closeMobileMenu()">
                    <i class="fas fa-store w-5"></i>
                    <span>Boutiques</span>
                    <?php if ($total_boutiques > 0): ?>
                    <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $total_boutiques ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="produits.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5" onclick="closeMobileMenu()">
                    <i class="fas fa-box w-5"></i>
                    <span>Produits</span>
                </a>
                <a href="stocks.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5" onclick="closeMobileMenu()">
                    <i class="fas fa-warehouse w-5"></i>
                    <span>Stocks</span>
                    <?php if ($alertes_stock > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $alertes_stock ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="transferts.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors" onclick="closeMobileMenu()">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="utilisateurs.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5" onclick="closeMobileMenu()">
                    <i class="fas fa-users w-5"></i>
                    <span>Utilisateurs</span>
                </a>
                <a href="rapports_pdg.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5" onclick="closeMobileMenu()">
                    <i class="fas fa-cog w-5"></i>
                    <span>Rapports</span>
                </a>
            </nav>

            <div class="p-4 border-t border-white/10 mt-8">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300" onclick="closeMobileMenu()">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <div class="flex-1 lg:ml-64">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 p-6 mobile-p-3">
                <div class="flex justify-between items-center mobile-flex-col mobile-gap-4">
                    <div class="header-title">
                        <h1 class="text-2xl font-bold text-gray-900 mobile-text-sm">Tableau de bord PDG</h1>
                        <p class="text-gray-600 mobile-text-sm">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <?= date('d/m/Y') ?> | 
                            <i class="fas fa-clock ml-2 mr-2"></i>
                            <span id="currentTime"><?= date('H:i') ?></span>
                        </p>
                    </div>
                    <div class="flex items-center space-x-4 header-actions">
                        <?php if ($alertes_stock > 0): ?>
                        <a href="stocks.php" 
                           class="px-4 py-2 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-lg hover:opacity-90 shadow-md flex items-center mobile-text-sm">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span class="mobile-hide">Alertes Stock</span>
                            <span class="ml-2 bg-white text-red-600 text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                <?= $alertes_stock ?>
                            </span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="nouvelle_boutique.php"
                           class="px-4 py-2 gradient-success text-white rounded-lg hover:opacity-90 shadow-md mobile-text-sm">
                            <i class="fas fa-plus mr-2"></i><span class="mobile-hide">Nouvelle boutique</span>
                        </a>
                    </div>
                </div>
            </header>

            <main class="p-6 mobile-p-2">
                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 mobile-gap-2">
                    <!-- Carte 1 - Boutiques -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in hover-lift mobile-card">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-store text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600 mobile-text-sm">Actives</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 mobile-text-sm"><?= $total_boutiques ?></h3>
                        <p class="text-gray-600 text-sm mobile-text-sm">Boutiques</p>
                    </div>
                    
                    <!-- Carte 2 - CA Total -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-green-500 animate-fade-in hover-lift mobile-card">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-green-600 mobile-text-sm">Total</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 mobile-text-sm"><?= number_format($ca_total, 2, ',', ' ') ?> $</h3>
                        <p class="text-gray-600 text-sm mobile-text-sm">Chiffre d'affaires</p>
                    </div>
                    
                    <!-- Carte 3 - Produits -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in hover-lift mobile-card">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600 mobile-text-sm">En stock</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 mobile-text-sm"><?= $produits_total ?></h3>
                        <p class="text-gray-600 text-sm mobile-text-sm">Produits disponibles</p>
                    </div>
                    
                    <!-- Carte 4 - Alertes -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-red-500 animate-fade-in hover-lift mobile-card">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-red-600 mobile-text-sm">À traiter</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2 mobile-text-sm"><?= $alertes_stock ?></h3>
                        <p class="text-gray-600 text-sm mobile-text-sm">Alertes stock</p>
                    </div>
                </div>

                <!-- Tableaux -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6 mobile-gap-2">
                    <!-- Dernières transactions -->
                    <div class="bg-white rounded-xl shadow-soft p-4 hover-lift mobile-p-3">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-bold text-gray-900 mobile-text-sm">Dernières transactions</h2>
                            <a href="paiements.php" class="text-sm text-purple-600 hover:text-purple-800 mobile-text-sm">
                                Voir tout
                            </a>
                        </div>
                        <div class="responsive-table">
                            <table class="w-full table-mobile-compact">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm">ID</th>
                                        <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm">Boutique</th>
                                        <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm">Montant</th>
                                        <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dernieres_ventes)): ?>
                                    <?php foreach ($dernieres_ventes as $vente): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-2 text-sm mobile-text-sm">#<?= htmlspecialchars($vente['id'] ?? 'N/A') ?></td>
                                        <td class="py-2 text-sm mobile-text-sm"><?= htmlspecialchars(substr($vente['boutique_nom'] ?? 'N/A', 0, 15)) ?><?= strlen($vente['boutique_nom'] ?? '') > 15 ? '...' : '' ?></td>
                                        <td class="py-2 text-sm font-medium text-green-600 mobile-text-sm">
                                            <?= number_format($vente['montant'] ?? 0, 2, ',', ' ') ?> $
                                        </td>
                                        <td class="py-2 text-sm text-gray-500 mobile-text-sm">
                                            <?= date('d/m', strtotime($vente['date'] ?? '')) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-gray-500 mobile-text-sm">
                                            <i class="fas fa-shopping-cart text-2xl text-gray-300 mb-2"></i>
                                            <p>Aucune transaction</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Produits populaires -->
                    <div class="bg-white rounded-xl shadow-soft p-4 hover-lift mobile-p-3">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-bold text-gray-900 mobile-text-sm">Top produits</h2>
                            <span class="text-sm text-gray-500 mobile-text-sm">Toutes ventes</span>
                        </div>
                        <div class="space-y-3 mobile-space-y-2">
                            <?php if (!empty($produits_populaires)): ?>
                            <?php foreach ($produits_populaires as $produit): ?>
                            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 mobile-p-2">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900 mobile-text-sm"><?= htmlspecialchars(substr($produit['designation'], 0, 20)) ?><?= strlen($produit['designation']) > 20 ? '...' : '' ?></h4>
                                    <div class="flex items-center gap-2 text-sm text-gray-500 product-info mobile-text-sm">
                                        <span class="mobile-hide">Réf: <?= htmlspecialchars(substr($produit['matricule'], 0, 8)) ?><?= strlen($produit['matricule']) > 8 ? '...' : '' ?></span>
                                        <span>Un: <?= $produit['umProduit'] == 'metres' ? 'm' : 'pcs' ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-lg font-bold text-green-600 mobile-text-sm">
                                        <?= $produit['total_vendu'] ?>
                                    </span>
                                    <div class="text-sm text-gray-600 mobile-text-sm">
                                        <?= number_format($produit['chiffre_affaires'], 0, ',', ' ') ?> $
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="text-center py-4 text-gray-500 mobile-text-sm">
                                <i class="fas fa-chart-line text-2xl text-gray-300 mb-2"></i>
                                <p>Aucune donnée</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Boutiques -->
                <div class="bg-white rounded-xl shadow-soft p-4 hover-lift mb-6 mobile-p-3">
                    <h2 class="text-lg font-bold text-gray-900 mb-4 mobile-text-sm">Boutiques</h2>
                    <div class="responsive-table">
                        <table class="w-full table-mobile-compact">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm">Boutique</th>
                                    <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm mobile-hide">Stock</th>
                                    <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm">Alertes</th>
                                    <th class="text-left py-2 text-sm font-medium text-gray-600 mobile-text-sm">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($boutiques_ca)): ?>
                                <?php foreach ($boutiques_ca as $boutique): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center mobile-hide">
                                                <i class="fas fa-store text-gray-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900 mobile-text-sm"><?= htmlspecialchars(substr($boutique['nom'], 0, 15)) ?><?= strlen($boutique['nom']) > 15 ? '...' : '' ?></h4>
                                                <p class="text-xs text-gray-500 mobile-hide"><?= htmlspecialchars(substr($boutique['email'], 0, 20)) ?>...</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 text-sm font-medium text-gray-900 mobile-hide mobile-text-sm">
                                        <?= $boutique['produits_en_stock'] ?? 0 ?>
                                    </td>
                                    <td class="py-2">
                                        <?php if (($boutique['alertes_boutique'] ?? 0) > 0): ?>
                                        <span class="badge badge-danger">
                                            <?= $boutique['alertes_boutique'] ?? 0 ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-success">
                                            OK
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2">
                                        <span class="badge <?= ($boutique['actif'] ?? 0) ? 'badge-success' : 'badge-danger' ?>">
                                            <?= ($boutique['actif'] ?? 0) ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500 mobile-text-sm">
                                        <i class="fas fa-store text-2xl text-gray-300 mb-2"></i>
                                        <p>Aucune boutique</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mobile-gap-2">
                    <a href="boutiques.php" 
                       class="p-3 rounded-xl border-2 border-green-200 hover:border-green-500 hover:bg-green-50 text-center mobile-p-2">
                        <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-plus text-green-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900 mobile-text-sm">Nouvelle boutique</span>
                    </a>
                    
                    <a href="produits.php" 
                       class="p-3 rounded-xl border-2 border-blue-200 hover:border-blue-500 hover:bg-blue-50 text-center mobile-p-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-box text-blue-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900 mobile-text-sm">Nouveau produit</span>
                    </a>
                    
                    <a href="stocks.php" 
                       class="p-3 rounded-xl border-2 border-purple-200 hover:border-purple-500 hover:bg-purple-50 text-center mobile-p-2">
                        <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-warehouse text-purple-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900 mobile-text-sm">Gestion stocks</span>
                    </a>
                    
                    <a href="rapports_pdg.php" 
                       class="p-3 rounded-xl border-2 border-yellow-200 hover:border-yellow-500 hover:bg-yellow-50 text-center mobile-p-2">
                        <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-chart-bar text-yellow-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900 mobile-text-sm">Rapports</span>
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mise à jour du temps
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        setInterval(updateClock, 60000);
        updateClock();
        
        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Mobile menu functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
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
            
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', openMobileMenu);
            }
            
            if (closeMobileMenuBtn) {
                closeMobileMenuBtn.addEventListener('click', closeMobileMenu);
            }
            
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMobileMenu);
            }
            
            // Close menu on resize to desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) {
                    closeMobileMenu();
                }
            });
            
            // Expose function globally for menu links
            window.closeMobileMenu = closeMobileMenu;
        });
        
        // Prevent body scroll when mobile menu is open
        document.addEventListener('touchmove', function(e) {
            if (document.querySelector('.mobile-sidebar.active')) {
                e.preventDefault();
            }
        }, { passive: false });
    </script>
</body>
</html>