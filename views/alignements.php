<?php
include '../connexion/connexion.php';

# Vérification d'authentification
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

# Récupérer les alignements avec les informations liées
$sql_alignements = "
    SELECT 
        a.id, 
        a.date, 
        a.montant, 
        a.statut,
        a.created_at,
        a.charge_id,
        a.affectation_id,
        c.designation as charge_designation,
        m.nom as locataire_nom,
        m.prenom as locataire_prenom,
        m.postnom as locataire_postnom,
        b.numero as boutique_numero
    FROM aligements a
    LEFT JOIN charges c ON a.charge_id = c.id
    LEFT JOIN affectation aff ON a.affectation_id = aff.id
    LEFT JOIN membres m ON aff.membre = m.id
    LEFT JOIN boutiques b ON aff.boutique = b.id
    ORDER BY a.created_at DESC
";

try {
    $stmt_alignements = $pdo->query($sql_alignements);
    $alignements = $stmt_alignements->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des alignements: " . $e->getMessage());
    $alignements = [];
}

# Récupérer les charges pour les formulaires
$sql_charges = "SELECT id, designation FROM charges WHERE statut = 0 ORDER BY designation";
try {
    $stmt_charges = $pdo->query($sql_charges);
    $charges = $stmt_charges->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des charges: " . $e->getMessage());
    $charges = [];
}

# Récupérer les affectations actives pour l'autocomplétion
$sql_affectations = "
    SELECT 
        aff.id,
        m.nom as locataire_nom,
        m.prenom as locataire_prenom,
        m.postnom as locataire_postnom,
        b.numero as boutique_numero
    FROM affectation aff
    INNER JOIN membres m ON aff.membre = m.id
    INNER JOIN boutiques b ON aff.boutique = b.id
    WHERE aff.statut = 0
    ORDER BY m.nom, b.numero
";

try {
    $stmt_affectations = $pdo->query($sql_affectations);
    $affectations = $stmt_affectations->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des affectations: " . $e->getMessage());
    $affectations = [];
}

# Préparer les données pour JavaScript
$affectations_js = [];
foreach ($affectations as $affectation) {
    $affectations_js[] = [
        'id' => $affectation['id'],
        'locataire_nom' => $affectation['locataire_nom'],
        'locataire_prenom' => $affectation['locataire_prenom'],
        'locataire_postnom' => $affectation['locataire_postnom'],
        'boutique_numero' => $affectation['boutique_numero']
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alignements des Charges - GestionLoyer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        .sidebar {
            transition: all 0.3s;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar.collapsed {
            margin-left: -16rem;
        }
        .main-content {
            transition: all 0.3s;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .navbar-gradient {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        .btn-gradient {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        .btn-gradient:hover {
            background: linear-gradient(90deg, #5a6fd8 0%, #6a4190 100%);
        }
        .card-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .hover-lift {
            transition: all 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .modal-backdrop {
            background: rgba(0, 0, 0, 0.5);
        }
        .tab-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .tab-inactive {
            background: white;
            color: #667eea;
        }
        .search-result-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .search-result-item:hover {
            background-color: #f3f4f6;
            transform: translateX(5px);
        }
        
        /* Styles pour les barres de défilement */
        .scrollable-menu {
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
        }
        
        /* Pour WebKit (Chrome, Safari) */
        .scrollable-menu::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollable-menu::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
        
        .scrollable-menu::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .scrollable-menu::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }
        
        /* Pour Firefox */
        .scrollable-menu {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
        }
        
        /* Style spécifique pour la sidebar */
        .sidebar-content {
            height: calc(100vh - 6rem);
            overflow-y: auto;
            padding-bottom: 6rem; /* Espace pour le pied de page */
        }
        
        /* Style pour le menu utilisateur */
        .user-menu-scroll {
            max-height: 200px;
            overflow-y: auto;
        }
        
        /* Style pour le menu latéral sur petits écrans */
        @media (max-height: 700px) {
            .sidebar-content {
                height: calc(100vh - 4rem);
                padding-bottom: 4rem;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Barre de navigation supérieure -->
    <nav class="navbar-gradient text-white fixed w-full z-10 shadow-lg">
        <div class="flex items-center justify-between p-4">
            <!-- Logo et nom de l'application -->
            <div class="flex items-center">
                <button id="sidebarToggle" class="mr-4 text-white hover:text-gray-200 transition-colors duration-200">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="text-xl font-bold">La veranda</h1>
            </div>

            <!-- Barre de recherche -->
            <div class="hidden md:flex items-center">
                <div class="relative">
                    <input type="text" placeholder="Rechercher..." class="glass-effect text-white rounded-lg py-2 px-4 pl-10 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 placeholder-white placeholder-opacity-70">
                    <i class="fas fa-search absolute left-3 top-3 text-white text-opacity-70"></i>
                </div>
            </div>

            <!-- Menu utilisateur -->
            <div class="relative">
                <button id="userMenuButton" class="flex items-center text-white hover:text-gray-200 focus:outline-none transition-colors duration-200">
                    <i class="fas fa-user-circle text-xl"></i>
                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                </button>
                <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 text-gray-700 z-20 user-menu-scroll scrollable-menu">
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200"><i class="fas fa-cog mr-2 text-purple-500"></i>Paramètres</a>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200"><i class="fas fa-history mr-2 text-purple-500"></i>Journal d'activité</a>
                    <div class="border-t my-1"></div>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200"><i class="fas fa-sign-out-alt mr-2 text-purple-500"></i>Déconnexion</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16">
        <!-- Barre latérale -->
        <div id="sidebar" class="sidebar text-white w-64 min-h-screen fixed shadow-xl">
            <div class="sidebar-content p-4 scrollable-menu">
                <!-- En-tête de la barre latérale -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Principal</h2>
                    <ul class="mt-2">
                        <li>
                            <a href="dashboard.php" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Tableau de bord
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Section Interface -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Gestion</h2>
                    <ul class="mt-2">
                        <li class="mb-1">
                            <a href="boutiques.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-store mr-3"></i>
                                    Boutiques
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="membres.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-users mr-3"></i>
                                    Locataires
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="affectations.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-link mr-3"></i>
                                    Affectations
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="contrats.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-contract mr-3"></i>
                                    Contrats
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="paiements.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                                    Paiements
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="charges.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-money-bill-wave mr-3"></i>
                                    Charges
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="alignements.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-euro-sign mr-3"></i>
                                    Alignements
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="utilisateurs.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-user-cog mr-3"></i>
                                    Utilisateurs
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-chart-line mr-3"></i>
                                    Rapports
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Section Addons -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Outils</h2>
                    <ul class="mt-2">
                        <li class="mb-1">
                            <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <i class="fas fa-chart-pie mr-3"></i>
                                Statistiques
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <i class="fas fa-table mr-3"></i>
                                Documents
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Pied de page de la barre latérale -->
            <div class="absolute bottom-0 left-0 right-0 p-4 glass-effect rounded-t-lg">
                <div class="text-sm text-white text-opacity-70">Connecté en tant que :</div>
                <div class="font-semibold">Administrateur</div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div id="mainContent" class="main-content ml-64 p-6 w-full">
            <!-- En-tête et fil d'Ariane -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Alignements</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Alignements</span>
                    </div>
                </div>
                <button id="btnAjouter" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Nouvel alignement
                </button>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card text-white rounded-xl p-5 hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Total alignements</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($alignements); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-euro-sign text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Alignements actifs</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $actives = array_filter($alignements, function($alignement) {
                                        return $alignement['statut'] == 0;
                                    });
                                    echo count($actives);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-check-circle text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Montant moyen</p>
                            <p class="text-lg font-bold mt-1">
                                <?php 
                                    if (!empty($alignements)) {
                                        $total = array_sum(array_column($alignements, 'montant'));
                                        $moyenne = $total / count($alignements);
                                        echo number_format($moyenne, 2, ',', ' ') . ' €';
                                    } else {
                                        echo '0,00 €';
                                    }
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-calculator text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Dernier ajout</p>
                            <p class="text-lg font-bold mt-1">
                                <?php 
                                    if (!empty($alignements)) {
                                        $dernier = $alignements[0];
                                        echo date('d/m/Y', strtotime($dernier['created_at']));
                                    } else {
                                        echo 'Aucun';
                                    }
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-history text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Rechercher un alignement..." 
                                   class="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des alignements -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#ID</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Charge</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Locataire</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Boutique</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="alignementTableBody" class="bg-white divide-y divide-gray-200">
                            <?php if (empty($alignements)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-500">
                                            <i class="fas fa-euro-sign text-4xl mb-4 text-gray-300"></i>
                                            <h3 class="text-lg font-medium text-gray-700">Aucun alignement enregistré</h3>
                                            <p class="text-gray-500 mt-2">Commencez par créer votre premier alignement</p>
                                            <button id="btnAjouterEmpty" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center mt-4 shadow-lg hover-lift transition-all duration-300">
                                                <i class="fas fa-plus mr-2"></i> Nouvel alignement
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alignements as $alignement): ?>
                                <tr class="hover:bg-purple-50 transition-colors duration-200 alignement-row">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            #<?php echo $alignement['id']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('d/m/Y', strtotime($alignement['date'])); ?>
                                        </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($alignement['charge_designation'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php 
                                            $nom_complet = trim(($alignement['locataire_nom'] ?? '') . ' ' . ($alignement['locataire_postnom'] ?? '') . ' ' . ($alignement['locataire_prenom'] ?? ''));
                                            echo htmlspecialchars($nom_complet ?: 'Global');
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($alignement['boutique_numero'] ?? 'Global'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-green-600">
                                        <?php echo number_format($alignement['montant'], 2, ',', ' '); ?> €
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $alignement['statut'] == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $alignement['statut'] == 0 ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-purple-600 hover:text-purple-800 mr-3 edit-alignement transition-colors duration-200" 
                                            data-id="<?php echo $alignement['id']; ?>"
                                            data-date="<?php echo $alignement['date']; ?>"
                                            data-montant="<?php echo $alignement['montant']; ?>"
                                            data-affectation-id="<?php echo $alignement['affectation_id'] ?? ''; ?>"
                                            data-charge-id="<?php echo $alignement['charge_id'] ?? ''; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($alignement['statut'] == 0): ?>
                                    <button class="text-red-600 hover:text-red-800 delete-alignement transition-colors duration-200" 
                                            data-id="<?php echo $alignement['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="text-green-600 hover:text-green-800 reactiver-alignement transition-colors duration-200" 
                                            data-id="<?php echo $alignement['id']; ?>">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="noResults" class="hidden p-8 text-center">
                <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-700">Aucun alignement trouvé</h3>
                <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
            </div>
        </div>

        <!-- Modal pour ajouter/modifier un alignement -->
        <div id="alignementModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
                <div class="mt-3">
                    <!-- En-tête du modal -->
                    <div class="flex justify-between items-center pb-3 border-b">
                        <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Nouvel alignement</h3>
                        <button id="closeModal" class="text-gray-400 hover:text-gray-500 transition-colors duration-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Onglets pour le type d'alignement -->
                    <div class="flex border-b mt-4 rounded-lg overflow-hidden">
                        <button id="tabGlobal" class="flex-1 py-3 px-4 text-center font-medium tab-active transition-colors duration-200">
                            <i class="fas fa-globe mr-2"></i>Alignement Global
                        </button>
                        <button id="tabSpecifique" class="flex-1 py-3 px-4 text-center font-medium tab-inactive transition-colors duration-200">
                            <i class="fas fa-user mr-2"></i>Alignement Spécifique
                        </button>
                    </div>

                    <!-- Formulaire -->
                    <form id="alignementForm" action="../models/traitement/alignement-post.php" method="POST">
                        <input type="hidden" id="action" name="action" value="ajouter">
                        <input type="hidden" id="alignementId" name="id" value="">
                        <input type="hidden" id="typeAlignement" name="type_alignement" value="global">
                        
                        <div class="grid grid-cols-1 gap-6 mt-4">
                            <!-- Section Alignement Global -->
                            <div id="sectionGlobal" class="space-y-4">
                                <div>
                                    <label for="charge_id_global" class="block text-sm font-medium text-gray-700">Charge *</label>
                                    <select id="charge_id_global" name="charge_id" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                        <option value="">Sélectionnez une charge</option>
                                        <?php foreach ($charges as $charge): ?>
                                            <option value="<?php echo $charge['id']; ?>">
                                                <?php echo htmlspecialchars($charge['designation']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                                    <div class="flex items-start">
                                        <i class="fas fa-info-circle text-blue-500 mr-2 mt-0.5"></i>
                                        <p class="text-sm text-blue-700">
                                            Cet alignement sera appliqué à <strong>toutes les affectations actives</strong> de cette charge avec le même montant.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Section Alignement Spécifique -->
                            <div id="sectionSpecifique" class="space-y-4 hidden">
                                <div>
                                    <label for="searchAffectation" class="block text-sm font-medium text-gray-700">Rechercher une affectation *</label>
                                    <div class="relative">
                                        <input type="text" id="searchAffectation" 
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                               placeholder="Tapez le nom du locataire ou le numéro de boutique...">
                                        <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                                        <div id="searchResults" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto scrollable-menu">
                                            <!-- Les résultats apparaîtront ici -->
                                        </div>
                                    </div>
                                    <input type="hidden" id="affectation_id" name="affectation_id">
                                    <div id="selectedAffectation" class="hidden mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                        <!-- L'affectation sélectionnée apparaîtra ici -->
                                    </div>
                                </div>
                                
                                <!-- AJOUT: Champ pour sélectionner la charge dans l'alignement spécifique -->
                                <div>
                                    <label for="charge_id_specifique" class="block text-sm font-medium text-gray-700">Charge *</label>
                                    <select id="charge_id_specifique" name="charge_id"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                        <option value="">Sélectionnez une charge</option>
                                        <?php foreach ($charges as $charge): ?>
                                            <option value="<?php echo $charge['id']; ?>">
                                                <?php echo htmlspecialchars($charge['designation']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                    <div class="flex items-start">
                                        <i class="fas fa-info-circle text-green-500 mr-2 mt-0.5"></i>
                                        <p class="text-sm text-green-700">
                                            Tapez le <strong>nom du locataire</strong> (ex: "Dupont") ou le <strong>numéro de boutique</strong> (ex: "101") pour trouver l'affectation.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Champ montant -->
                            <div>
                                <label for="montant" class="block text-sm font-medium text-gray-700">Montant (€) *</label>
                                <input type="number" id="montant" name="montant" required step="0.01" min="0.01"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                    placeholder="0.00">
                            </div>

                            <!-- Information sur la date automatique -->
                            <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                                <div class="flex items-start">
                                    <i class="fas fa-calendar-alt text-purple-500 mr-2 mt-0.5"></i>
                                    <p class="text-sm text-purple-700">
                                        <strong>La date sera automatiquement définie à aujourd'hui</strong> lors de l'enregistrement.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions du modal -->
                        <div class="flex justify-end space-x-3 pt-4 border-t mt-6">
                            <button type="button" id="cancelBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <button type="submit" id="saveBtn" class="btn-gradient text-white px-4 py-2 rounded-lg shadow hover-lift transition-all duration-300">
                                <i class="fas fa-save mr-2"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal de confirmation de suppression -->
        <div id="deleteModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-2xl bg-white">
                <div class="mt-3 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Confirmer la suppression</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Êtes-vous sûr de vouloir désactiver cet alignement ?
                        </p>
                    </div>
                    <div class="flex justify-center space-x-3 mt-4">
                        <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                            Annuler
                        </button>
                        <form id="deleteForm" action="../models/traitement/alignement-post.php" method="POST" class="inline">
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="id" id="deleteAlignementId" value="">
                            <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-200 shadow hover-lift">
                                <i class="fas fa-trash mr-2"></i> Désactiver
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de réactivation -->
        <div id="reactivateModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-2xl bg-white">
                <div class="mt-3 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mt-2">Confirmer la réactivation</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500">
                            Êtes-vous sûr de vouloir réactiver cet alignement ?
                        </p>
                    </div>
                    <div class="flex justify-center space-x-3 mt-4">
                        <button id="cancelReactivate" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                            Annuler
                        </button>
                        <form id="reactivateForm" action="../models/traitement/alignement-post.php" method="POST" class="inline">
                            <input type="hidden" name="action" value="reactiver">
                            <input type="hidden" name="id" id="reactivateAlignementId" value="">
                            <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors duration-200 shadow hover-lift">
                                <i class="fas fa-undo mr-2"></i> Réactiver
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toastify JS -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<script>
    // Vérifie si un message de session est présent
    <?php if (isset($_SESSION['message'])): ?>
        Toastify({
            text: "<?= htmlspecialchars($_SESSION['message']['text']) ?>",
            duration: 3000,
            gravity: "top",
            position: "right",
            stopOnFocus: true,
            style: {
                background: "linear-gradient(to right, <?= ($_SESSION['message']['type'] == 'success') ? '#22c55e, #16a34a' : '#ef4444, #dc2626' ?>)",
            },
            onClick: function() {}
        }).showToast();

        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    // Données des affectations pour l'autocomplétion
    const affectationsData = <?php echo json_encode($affectations_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    // Éléments du DOM
    const alignementModal = document.getElementById('alignementModal');
    const deleteModal = document.getElementById('deleteModal');
    const reactivateModal = document.getElementById('reactivateModal');
    const alignementForm = document.getElementById('alignementForm');
    const modalTitle = document.getElementById('modalTitle');
    const btnAjouter = document.getElementById('btnAjouter');
    const btnAjouterEmpty = document.getElementById('btnAjouterEmpty');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const cancelDelete = document.getElementById('cancelDelete');
    const cancelReactivate = document.getElementById('cancelReactivate');
    const tabGlobal = document.getElementById('tabGlobal');
    const tabSpecifique = document.getElementById('tabSpecifique');
    const sectionGlobal = document.getElementById('sectionGlobal');
    const sectionSpecifique = document.getElementById('sectionSpecifique');
    const typeAlignement = document.getElementById('typeAlignement');
    const chargeIdSelect = document.getElementById('charge_id_global');
    const chargeIdSelectSpecifique = document.getElementById('charge_id_specifique');
    const searchAffectation = document.getElementById('searchAffectation');
    const searchResults = document.getElementById('searchResults');
    const affectationIdInput = document.getElementById('affectation_id');
    const selectedAffectation = document.getElementById('selectedAffectation');
    const deleteAlignementIdInput = document.getElementById('deleteAlignementId');
    const reactivateAlignementIdInput = document.getElementById('reactivateAlignementId');

    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        setupEventListeners();
        setupSearch();
        setupAffectationSearch();
    });

    // Configurer les écouteurs d'événements
    function setupEventListeners() {
        // Boutons pour ajouter un alignement
        btnAjouter.addEventListener('click', showModal);
        if (btnAjouterEmpty) {
            btnAjouterEmpty.addEventListener('click', showModal);
        }
        
        // Fermer le modal
        closeModal.addEventListener('click', hideModal);
        cancelBtn.addEventListener('click', hideModal);
        
        // Fermer le modal de suppression
        cancelDelete.addEventListener('click', hideDeleteModal);
        
        // Fermer le modal de réactivation
        cancelReactivate.addEventListener('click', hideReactivateModal);
        
        // Navigation des onglets
        tabGlobal.addEventListener('click', switchToGlobal);
        tabSpecifique.addEventListener('click', switchToSpecifique);
        
        // Boutons d'édition
        document.querySelectorAll('.edit-alignement').forEach(button => {
            button.addEventListener('click', function() {
                const alignementId = this.getAttribute('data-id');
                const montant = this.getAttribute('data-montant');
                const affectationId = this.getAttribute('data-affectation-id');
                const chargeId = this.getAttribute('data-charge-id');
                
                editAlignement(alignementId, montant, affectationId, chargeId);
            });
        });
        
        // Boutons de suppression
        document.querySelectorAll('.delete-alignement').forEach(button => {
            button.addEventListener('click', function() {
                const alignementId = this.getAttribute('data-id');
                showDeleteModal(alignementId);
            });
        });

        // Boutons de réactivation
        document.querySelectorAll('.reactiver-alignement').forEach(button => {
            button.addEventListener('click', function() {
                const alignementId = this.getAttribute('data-id');
                showReactivateModal(alignementId);
            });
        });
        
        // Toggle de la barre latérale
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('ml-64');
            mainContent.classList.toggle('ml-0');
        });

        // Toggle du menu utilisateur
        document.getElementById('userMenuButton').addEventListener('click', function() {
            const userMenu = document.getElementById('userMenu');
            userMenu.classList.toggle('hidden');
        });

        // Fermer le menu utilisateur en cliquant ailleurs
        document.addEventListener('click', function(event) {
            const userMenuButton = document.getElementById('userMenuButton');
            const userMenu = document.getElementById('userMenu');
            
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
        
        // Validation du formulaire
        alignementForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const type = typeAlignement.value;
            const montant = document.getElementById('montant').value;
            
            let errors = [];
            
            // Validation des champs obligatoires
            if (!montant || montant <= 0) {
                errors.push('Le montant doit être supérieur à 0');
            }
            
            if (type === 'global' && !chargeIdSelect.value) {
                errors.push('La charge est obligatoire pour un alignement global');
            }
            
            if (type === 'specifique') {
                if (!affectationIdInput.value) {
                    errors.push('Veuillez sélectionner une affectation pour un alignement spécifique');
                }
                if (!chargeIdSelectSpecifique.value) {
                    errors.push('La charge est obligatoire pour un alignement spécifique');
                }
            }
            
            if (errors.length > 0) {
                Toastify({
                    text: "Erreurs: " + errors.join(', '),
                    duration: 5000,
                    gravity: "top",
                    position: "right",
                    style: {
                        background: "linear-gradient(to right, #ef4444, #dc2626)",
                    },
                }).showToast();
                return;
            }
            
            // Soumission du formulaire si validation OK
            this.submit();
        });
    }

    // Configurer la recherche dans le tableau
    function setupSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', filterAlignements);
        }
    }

    // Configurer la recherche d'affectations
    function setupAffectationSearch() {
        if (!searchAffectation) return;

        searchAffectation.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }
            
            const results = affectationsData.filter(affectation => {
                const nomComplet = (
                    affectation.locataire_nom + ' ' + 
                    affectation.locataire_postnom + ' ' + 
                    affectation.locataire_prenom
                ).toLowerCase();
                
                const boutique = affectation.boutique_numero.toLowerCase();
                
                // Recherche par nom ou par numéro de boutique
                return nomComplet.includes(searchTerm) || 
                       boutique.includes(searchTerm) ||
                       affectation.locataire_nom.toLowerCase().includes(searchTerm) ||
                       affectation.locataire_prenom.toLowerCase().includes(searchTerm) ||
                       affectation.locataire_postnom.toLowerCase().includes(searchTerm);
            });
            
            displaySearchResults(results);
        });
        
        // Fermer les résultats quand on clique ailleurs
        document.addEventListener('click', function(event) {
            if (!searchAffectation.contains(event.target) && !searchResults.contains(event.target)) {
                searchResults.classList.add('hidden');
            }
        });
    }

    // Afficher les résultats de recherche
    function displaySearchResults(results) {
        searchResults.innerHTML = '';
        
        if (results.length === 0) {
            const noResult = document.createElement('div');
            noResult.className = 'p-3 text-gray-500';
            noResult.textContent = 'Aucune affectation trouvée';
            searchResults.appendChild(noResult);
            searchResults.classList.remove('hidden');
            return;
        }
        
        results.forEach(affectation => {
            const nomComplet = `${affectation.locataire_nom} ${affectation.locataire_postnom} ${affectation.locataire_prenom}`;
            const item = document.createElement('div');
            item.className = 'p-3 border-b border-gray-100 search-result-item';
            item.innerHTML = `
                <div class="font-medium text-gray-800">${nomComplet}</div>
                <div class="text-sm text-gray-600">
                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-2">
                        <i class="fas fa-store mr-1"></i>${affectation.boutique_numero}
                    </span>
                </div>
            `;
            
            item.addEventListener('click', function() {
                selectAffectation(affectation);
            });
            
            searchResults.appendChild(item);
        });
        
        searchResults.classList.remove('hidden');
    }

    // Sélectionner une affectation
    function selectAffectation(affectation) {
        const nomComplet = `${affectation.locataire_nom} ${affectation.locataire_postnom} ${affectation.locataire_prenom}`;
        
        searchAffectation.value = nomComplet;
        affectationIdInput.value = affectation.id;
        
        selectedAffectation.innerHTML = `
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-2"></i>
                        <div>
                            <div class="font-medium text-green-700">${nomComplet}</div>
                            <div class="text-sm text-green-600 mt-1">
                                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-2">
                                    <i class="fas fa-store mr-1"></i>Boutique ${affectation.boutique_numero}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="clearAffectationSelection()" class="text-red-500 hover:text-red-700 ml-2">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        selectedAffectation.classList.remove('hidden');
        searchResults.classList.add('hidden');
    }

    // Effacer la sélection d'affectation (fonction globale)
    window.clearAffectationSelection = function() {
        searchAffectation.value = '';
        affectationIdInput.value = '';
        selectedAffectation.classList.add('hidden');
        selectedAffectation.innerHTML = '';
        searchResults.classList.add('hidden');
    }

    // Filtrer les alignements dans le tableau
    function filterAlignements() {
        const searchTerm = searchInput.value.toLowerCase();
        const rows = document.querySelectorAll('.alignement-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const matchesSearch = rowText.includes(searchTerm);
            
            if (matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Afficher/masquer le message "Aucun résultat"
        const noResults = document.getElementById('noResults');
        if (visibleCount === 0) {
            noResults.classList.remove('hidden');
            document.getElementById('alignementTableBody').style.display = 'none';
        } else {
            noResults.classList.add('hidden');
            document.getElementById('alignementTableBody').style.display = '';
        }
    }

    // Basculer vers l'onglet Global
    function switchToGlobal() {
        tabGlobal.classList.add('tab-active');
        tabGlobal.classList.remove('tab-inactive');
        tabSpecifique.classList.add('tab-inactive');
        tabSpecifique.classList.remove('tab-active');
        
        sectionGlobal.classList.remove('hidden');
        sectionSpecifique.classList.add('hidden');
        
        typeAlignement.value = 'global';
        chargeIdSelect.required = true;
        chargeIdSelectSpecifique.required = false;
        affectationIdInput.required = false;
        
        // Réinitialiser la sélection d'affectation
        clearAffectationSelection();
    }

    // Basculer vers l'onglet Spécifique
    function switchToSpecifique() {
        tabSpecifique.classList.add('tab-active');
        tabSpecifique.classList.remove('tab-inactive');
        tabGlobal.classList.add('tab-inactive');
        tabGlobal.classList.remove('tab-active');
        
        sectionSpecifique.classList.remove('hidden');
        sectionGlobal.classList.add('hidden');
        
        typeAlignement.value = 'specifique';
        chargeIdSelect.required = false;
        chargeIdSelectSpecifique.required = true;
        affectationIdInput.required = true;
    }

    // Afficher le modal d'ajout
    function showModal() {
        modalTitle.textContent = 'Nouvel alignement';
        document.getElementById('action').value = 'ajouter';
        alignementForm.reset();
        document.getElementById('alignementId').value = '';
        
        // Réinitialiser les champs spécifiques
        clearAffectationSelection();
        chargeIdSelectSpecifique.value = '';
        
        // Par défaut sur l'onglet Global
        switchToGlobal();
        
        alignementModal.classList.remove('hidden');
    }

    // Masquer le modal
    function hideModal() {
        alignementModal.classList.add('hidden');
    }

    // Afficher le modal de confirmation de suppression
    function showDeleteModal(alignementId) {
        deleteAlignementIdInput.value = alignementId;
        deleteModal.classList.remove('hidden');
    }

    // Masquer le modal de confirmation de suppression
    function hideDeleteModal() {
        deleteModal.classList.add('hidden');
    }

    // Afficher le modal de confirmation de réactivation
    function showReactivateModal(alignementId) {
        reactivateAlignementIdInput.value = alignementId;
        reactivateModal.classList.remove('hidden');
    }

    // Masquer le modal de confirmation de réactivation
    function hideReactivateModal() {
        reactivateModal.classList.add('hidden');
    }

    // Éditer un alignement
    function editAlignement(alignementId, montant, affectationId, chargeId) {
        modalTitle.textContent = 'Modifier l\'alignement';
        document.getElementById('action').value = 'modifier';
        document.getElementById('alignementId').value = alignementId;
        document.getElementById('montant').value = montant;
        
        // Déterminer le type d'alignement
        if (chargeId && !affectationId) {
            switchToGlobal();
            document.getElementById('charge_id_global').value = chargeId;
        } else if (affectationId) {
            switchToSpecifique();
            document.getElementById('charge_id_specifique').value = chargeId;
            
            // Trouver l'affectation correspondante
            const affectation = affectationsData.find(a => a.id == affectationId);
            if (affectation) {
                selectAffectation(affectation);
            }
        }
        
        alignementModal.classList.remove('hidden');
    }

    // Navigation au clavier
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideModal();
            hideDeleteModal();
            hideReactivateModal();
        }
    });
</script>
</body>
</html>