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
        $query = $pdo->prepare("SELECT matricule, designation, actif FROM produits WHERE matricule = ? AND statut = 0");
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

// Fonction pour générer le prochain matricule
function genererMatricule($pdo) {
    // Récupérer le dernier matricule
    $query = $pdo->query("SELECT matricule FROM produits WHERE matricule LIKE 'Rid-%' ORDER BY matricule DESC LIMIT 1");
    $lastMatricule = $query->fetchColumn();
    
    if ($lastMatricule) {
        // Extraire le numéro et l'incrémenter
        $lastNumber = intval(substr($lastMatricule, 4)); // "Rid-001" -> "001" -> 1
        $newNumber = $lastNumber + 1;
    } else {
        // Premier produit
        $newNumber = 1;
    }
    
    // Formater avec 3 chiffres
    return 'Rid-' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

// Récupérer le prochain matricule pour l'affichage
$prochainMatricule = genererMatricule($pdo);

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
    $query = $pdo->prepare("SELECT * FROM produits WHERE statut = 0 ORDER BY actif DESC, date_creation DESC LIMIT :limit OFFSET :offset");
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $produits = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Compter les actifs pour la carte de stats
    $active_count_total = $pdo->query("SELECT COUNT(*) FROM produits WHERE actif = 1 AND statut = 0")->fetchColumn();

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des produits: " . $e->getMessage(),
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
        
        /* NOUVELLES STYLES POUR LA SIDEBAR AVEC DÉFILEMENT */
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

        /* Personnalisation de la scrollbar pour la sidebar */
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

        /* Pour Firefox */
        .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) rgba(255, 255, 255, 0.05);
        }

        /* Animation de transition pour les liens de la sidebar */
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

        /* Amélioration de la zone de contenu principal */
        .main-content {
            height: 100vh;
            overflow-y: auto;
        }

        /* Smooth scroll pour tout le document */
        html {
            scroll-behavior: smooth;
        }

        /* Animation pour le bouton mobile */
        .mobile-menu-btn {
            transition: transform 0.3s ease;
        }

        .mobile-menu-btn.active {
            transform: rotate(90deg);
        }

        /* Effet de fondu pour les éléments de la table */
        .fade-in-row {
            animation: fadeInRow 0.5s ease-out forwards;
            opacity: 0;
        }

        @keyframes fadeInRow {
            to {
                opacity: 1;
            }
        }

        /* Hover effects pour les cartes de statistiques */
        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        /* Loading animation */
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

        /* Badge de notification */
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

        /* Style pour les boutons d'action */
        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar-nav {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
            
            .nav-link {
                padding: 0.75rem 1rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
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
        
        /* Style pour l'affichage du prochain matricule */
        .next-matricule {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .matricule-code {
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 5px;
            display: inline-block;
            margin-top: 5px;
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
                <a href="produits.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-box w-5 text-white"></i>
                    <span>Produits</span>
                    <span class="notification-badge"><?= $total_produits ?></span>
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
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Gestion des produits - NGS</h1>
                        <p class="text-gray-600 text-sm md:text-base">New Grace Service - Catalogue de rideaux</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="openProduitModal()"
                            class="px-4 py-3 gradient-blue-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-plus"></i>
                            <span class="hidden md:inline">Nouveau rideau</span>
                            <span class="md:hidden">Nouveau</span>
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

                <!-- <div class="next-matricule animate-fade-in">
                    <div class="flex items-center justify-center space-x-2">
                        <i class="fas fa-tags text-xl"></i>
                        <span class="font-medium">Prochain matricule disponible :</span>
                    </div>
                    <div class="matricule-code"><?= htmlspecialchars($prochainMatricule) ?></div>
                    <p class="text-sm mt-2 opacity-90">Le matricule sera généré automatiquement lors de l'ajout d'un nouveau produit</p>
                </div> -->

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_produits ?></h3>
                        <p class="text-gray-600">Rideaux enregistrés</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-green-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-green-600">Actifs</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $active_count_total ?></h3>
                        <p class="text-gray-600">Rideaux disponibles</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-red-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                                <i class="fas fa-times-circle text-red-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-red-600">Inactifs</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_produits - $active_count_total ?></h3>
                        <p class="text-gray-600">Rideaux désactivés</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Pagination</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $page ?></h3>
                        <p class="text-gray-600">sur <?= $totalPages ?> pages</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in" style="animation-delay: 0.4s">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="relative flex-1 max-w-lg">
                            <input type="text"
                                id="searchInput"
                                placeholder="Rechercher par Matricule ou Désignation..."
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
                        <h2 class="text-lg font-semibold text-gray-900">Liste des rideaux - NGS</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px]" id="produitsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matricule</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                                    <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Création</th> -->
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php foreach ($produits as $index => $produit): ?>
                                    <tr class="produit-row hover:bg-gray-50 transition-colors fade-in-row"
                                        data-produit-matricule="<?= htmlspecialchars(strtolower($produit['matricule'])) ?>"
                                        data-produit-designation="<?= htmlspecialchars(strtolower($produit['designation'])) ?>"
                                        style="animation-delay: <?= $index * 0.05 ?>s">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 produit-matricule">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                                                    <i class="fas fa-tag text-indigo-600 text-xs"></i>
                                                </div>
                                                <span class="font-mono font-bold"><?= htmlspecialchars($produit['matricule']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 produit-designation">
                                            <?= htmlspecialchars($produit['designation']) ?>
                                        </td>
                                        <!-- <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y', strtotime($produit['date_creation'])) ?></td> -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="status-badge <?= $produit['actif'] ? 'status-active' : 'status-inactive' ?> inline-flex items-center">
                                                <i class="fas fa-circle text-xs mr-1"></i>
                                                <?= $produit['actif'] ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex space-x-2 action-buttons">
                                                <button onclick="openProduitModal('<?= htmlspecialchars(addslashes($produit['matricule'])) ?>'); return false;" 
                                                        class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    <span class="hidden md:inline">Modifier</span>
                                                </button>
                                                <button onclick="openToggleModal('<?= htmlspecialchars(addslashes($produit['matricule'])) ?>', '<?= htmlspecialchars(addslashes($produit['designation'])) ?>', <?= $produit['actif'] ?>); return false;"
                                                        class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white <?= $produit['actif'] ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700' ?> focus:outline-none focus:ring-2 focus:ring-offset-2 <?= $produit['actif'] ? 'focus:ring-orange-500' : 'focus:ring-green-500' ?> transition-colors">
                                                    <i class="fas fa-power-off mr-1"></i>
                                                    <span class="hidden md:inline"><?= $produit['actif'] ? 'Désactiver' : 'Activer' ?></span>
                                                </button>
                                                <button onclick="openDeleteModal('<?= htmlspecialchars(addslashes($produit['matricule'])) ?>', '<?= htmlspecialchars(addslashes($produit['designation'])) ?>'); return false;"
                                                        class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                                                    <i class="fas fa-trash-alt mr-1"></i>
                                                    <span class="hidden md:inline">Supprimer</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="noResults" class="hidden text-center py-12">
                        <div class="bg-gray-50 rounded-2xl p-8 max-w-md mx-auto shadow-soft">
                            <i class="fas fa-search text-6xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun résultat trouvé</h3>
                            <p class="text-gray-600">Aucun produit ne correspond à votre recherche</p>
                        </div>
                    </div>

                    <?php if (empty($produits) && $page == 1): ?>
                        <div class="text-center py-12">
                            <div class="bg-gray-50 rounded-2xl p-8 max-w-md mx-auto shadow-soft">
                                <i class="fas fa-tshirt text-6xl text-gray-400 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun rideau enregistré</h3>
                                <p class="text-gray-600 mb-4">Commencez par ajouter votre premier rideau</p>
                                <button onclick="openProduitModal()"
                                    class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 mx-auto shadow-md">
                                    <i class="fas fa-plus"></i>
                                    <span>Ajouter un rideau</span>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 hidden sm:block">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_produits) ?></span> sur
                                    <span class="font-medium"><?= $total_produits ?></span> produits
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
    
    <div id="produitModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900" id="modalTitle">Ajouter un nouveau rideau - NGS</h3>
                <button onclick="closeProduitModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="produitForm" method="POST" action="../models/traitement/produit-post.php">
                <input type="hidden" name="matricule_original" id="matriculeOriginal">

                <div class="space-y-4">
                    <div id="matriculeField" style="display: none;">
                        <label for="matricule" class="block text-sm font-medium text-gray-700 mb-1">Matricule</label>
                        <input type="text" name="matricule" id="matricule" readonly
                               class="w-full border-gray-300 bg-gray-100 rounded-lg shadow-sm p-3 cursor-not-allowed">
                        <p class="text-xs text-gray-500 mt-1">Identifiant unique du rideau (généré automatiquement)</p>
                    </div>
                    
                    <div>
                        <label for="designation" class="block text-sm font-medium text-gray-700 mb-1">Désignation du rideau *</label>
                        <input type="text" name="designation" id="designation" required
                               class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                               placeholder="Ex: Rideau en velours rouge, Rideau occultant noir...">
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <input type="checkbox" name="actif" id="actif" value="1" checked
                               class="h-4 w-4 text-secondary border-gray-300 rounded focus:ring-secondary">
                        <label for="actif" class="text-sm font-medium text-gray-700">Rideau actif (disponible à la vente)</label>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-blue-700 font-medium">Le matricule sera généré automatiquement</p>
                                <p class="text-xs text-blue-600 mt-1">Format : Rid-001, Rid-002, etc.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeProduitModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="ajouter_produit" id="submitButton"
                            class="px-4 py-2 gradient-blue-btn text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                        Enregistrer le rideau
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="toggleModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900" id="toggleModalTitle">Confirmer l'action - NGS</h3>
                <button onclick="closeToggleModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="text-center py-4">
                <i id="toggleIcon" class="fas fa-power-off text-5xl mb-4 text-gray-500"></i>
                <p class="text-lg font-medium text-gray-800 mb-2">Voulez-vous vraiment continuer ?</p>
                <p class="text-gray-600" id="toggleModalText">Le statut du rideau **Designation** va être modifié.</p>
            </div>

            <form id="toggleForm" method="POST" action="../models/traitement/produit-post.php" class="mt-6 flex justify-center space-x-3">
                <input type="hidden" name="matricule" id="toggleProduitMatricule">
                <button type="button" onclick="closeToggleModal()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                    Annuler
                </button>
                <button type="submit" name="toggle_actif" id="toggleConfirmButton"
                        class="px-4 py-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                    Confirmer
                </button>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Confirmation de suppression - NGS</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="text-center py-4">
                <i class="fas fa-trash-alt text-5xl mb-4 text-red-500"></i>
                <p class="text-lg font-bold text-red-700 mb-2">ATTENTION ! Suppression (Archivage)</p>
                <p class="text-gray-600 mb-4" id="deleteModalText">Vous êtes sur le point d'archiver le rideau **Designation**. Il ne sera plus visible, mais ses données resteront en base de données (Soft Delete).</p>
            </div>

            <form id="deleteForm" method="POST" action="../models/traitement/produit-post.php" class="mt-6 flex justify-center space-x-3">
                <input type="hidden" name="matricule" id="deleteProduitMatricule">
                <button type="button" onclick="closeDeleteModal()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                    Annuler
                </button>
                <button type="submit" name="supprimer_produit"
                        class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                    Oui, Archiver ce rideau
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

        // --- DÉTECTION DU SCROLL DANS LA SIDEBAR ---
        const sidebarNav = document.querySelector('.sidebar-nav');
        
        sidebarNav.addEventListener('scroll', function() {
            if (this.scrollTop > 10) {
                this.classList.add('scrolling');
            } else {
                this.classList.remove('scrolling');
            }
        });

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

        // --- AMÉLIORATION DU COMPORTEMENT DES LIENS ---
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Fermer la sidebar sur mobile après clic
                if (window.innerWidth < 768) {
                    setTimeout(() => {
                        toggleSidebar();
                    }, 200);
                }
            });
        });

        // --- GESTION DU SCROLL RESTAURATION ---
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        // --- NAVIGATION CLAVIER ---
        document.addEventListener('keydown', function(e) {
            // Ctrl+B ou Cmd+B pour ouvrir/fermer la sidebar
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }
            
            // Échap pour fermer la sidebar
            if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
                toggleSidebar();
            }
            
            // Ctrl+F pour focus la recherche
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        // --- AUTO-HIDE DE LA SCROLLBAR QUAND PAS BESOIN ---
        function checkSidebarScroll() {
            const nav = document.querySelector('.sidebar-nav');
            if (nav.scrollHeight <= nav.clientHeight) {
                nav.style.overflowY = 'hidden';
            } else {
                nav.style.overflowY = 'auto';
            }
        }

        window.addEventListener('load', checkSidebarScroll);
        window.addEventListener('resize', checkSidebarScroll);

        // --- SMOOTH SCROLL POUR LA SIDEBAR ---
        sidebarNav.style.scrollBehavior = 'smooth';

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
            
            if (matricule) {
                // Mode Modification
                modalTitle.textContent = "Modifier le rideau " + matricule + " - NGS";
                submitButton.textContent = "Modifier le rideau";
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
                modalTitle.textContent = "Ajouter un nouveau rideau - NGS";
                submitButton.textContent = "Enregistrer le rideau";
                submitButton.name = 'ajouter_produit';
                matriculeOriginal.value = '';
                
                // Cacher le champ matricule (généré automatiquement)
                matriculeField.style.display = 'none';
                document.getElementById('actif').checked = true;
            }

            produitModal.classList.add('show');
        }

        function closeProduitModal() {
            produitModal.classList.remove('show');
        }

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
            toggleModalText.innerHTML = `Le statut du rideau <strong>${designation}</strong> (${matricule}) va passer à <strong>${action}</strong>.<br>Voulez-vous confirmer cette action ?`;
            
            toggleIcon.className = `fas fa-power-off text-5xl mb-4 ${iconClass}`;
            
            toggleConfirmButton.textContent = buttonText;
            toggleConfirmButton.className = `px-4 py-2 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md ${buttonColor}`;

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
            deleteModalText.innerHTML = `Vous êtes sur le point d'archiver le rideau <strong>${designation}</strong> (${matricule}). Il ne sera plus visible, mais ses données resteront en base de données (Soft Delete). Cette action est réversible uniquement par un administrateur système. Confirmez-vous ?`;
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
    </script>
</body>
</html>