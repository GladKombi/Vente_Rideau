<?php
// Démarrer la session
session_start();

// Connexion à la base de données
$host = 'localhost';
$dbname = 'gestion_loyer';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

// Récupération des catégories
$stmt = $pdo->query("SELECT * FROM categorie ORDER BY created_at DESC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le nombre de boutiques par catégorie
$boutiquesParCategorie = [];
try {
    $stmt = $pdo->query("SELECT categorie, COUNT(*) as count FROM boutiques WHERE statut = 0 GROUP BY categorie");
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($resultats as $resultat) {
        $boutiquesParCategorie[$resultat['categorie']] = $resultat['count'];
    }
} catch (PDOException $e) {
    // Si la table boutiques n'existe pas encore, on continue sans les compteurs
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - GestionLoyer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Ajouter Toastify CSS -->
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
        
        /* Barres de défilement personnalisées */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        /* Styles pour les menus avec défilement */
        .scrollable-menu {
            max-height: 300px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .scrollable-sidebar {
            max-height: calc(100vh - 180px);
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
        }
        
        .sidebar-section {
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            margin-bottom: 1rem;
        }
        
        .user-menu-scroll {
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Scrollbar pour Firefox */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
        }
        
        /* Tableau avec défilement */
        .table-scroll {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Modal scroll */
        .modal-scroll {
            max-height: 70vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 10px;
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
                <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 text-gray-700 z-20 user-menu-scroll">
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-user mr-2 text-purple-500"></i>Profil
                    </a>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-cog mr-2 text-purple-500"></i>Paramètres
                    </a>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-bell mr-2 text-purple-500"></i>Notifications
                    </a>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-history mr-2 text-purple-500"></i>Journal d'activité
                    </a>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-question-circle mr-2 text-purple-500"></i>Aide
                    </a>
                    <div class="border-t my-1"></div>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-sign-out-alt mr-2 text-purple-500"></i>Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16">
        <!-- Barre latérale -->
        <div id="sidebar" class="sidebar text-white w-64 min-h-screen fixed shadow-xl">
            <div class="p-4">
                <!-- En-tête de la barre latérale -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Principal</h2>
                    <ul class="mt-2 sidebar-section">
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
                    <ul class="mt-2 sidebar-section">
                        <li class="mb-1">
                            <a href="boutiques.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-store mr-3"></i>
                                    Boutiques
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">14</span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="categories.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-tags mr-3"></i>
                                    Catégories
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo count($categories); ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="membres.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-users mr-3"></i>
                                    Locataires
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">8</span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                                    Paiements
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">42</span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="utilisateurs.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-user-cog mr-3"></i>
                                    Utilisateurs
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">3</span>
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
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-contract mr-3"></i>
                                    Contrats
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">12</span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-building mr-3"></i>
                                    Immeubles
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">5</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Section Addons -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Outils</h2>
                    <ul class="mt-2 sidebar-section">
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
                        <li class="mb-1">
                            <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <i class="fas fa-calendar-alt mr-3"></i>
                                Calendrier
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <i class="fas fa-envelope mr-3"></i>
                                Messages
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <i class="fas fa-tasks mr-3"></i>
                                Tâches
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Pied de page de la barre latérale -->
                <div class="absolute bottom-0 left-0 right-0 p-4 glass-effect rounded-t-lg">
                    <div class="text-sm text-white text-opacity-70">Connecté en tant que :</div>
                    <div class="font-semibold">Administrateur</div>
                    <div class="text-xs text-white text-opacity-60 mt-1">Dernière connexion : Aujourd'hui</div>
                </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div id="mainContent" class="main-content ml-64 p-6 w-full">
            <!-- En-tête et fil d'Ariane -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Catégories</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Catégories</span>
                    </div>
                </div>
                <button id="btnAjouter" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Ajouter une catégorie
                </button>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card text-white rounded-xl p-5 hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Total catégories</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($categories); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-tags text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Catégories actives</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $activeCategories = array_filter($categories, function($categorie) {
                                        return $categorie['statut'] == 0;
                                    });
                                    echo count($activeCategories);
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
                            <p class="text-sm font-medium text-white text-opacity-90">Catégories utilisées</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $categoriesUtilisees = array_filter($categories, function($categorie) use ($boutiquesParCategorie) {
                                        return isset($boutiquesParCategorie[$categorie['id']]) && $boutiquesParCategorie[$categorie['id']] > 0;
                                    });
                                    echo count($categoriesUtilisees);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-store text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Catégories inactives</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $inactiveCategories = array_filter($categories, function($categorie) {
                                        return $categorie['statut'] == 1;
                                    });
                                    echo count($inactiveCategories);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-ban text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Rechercher une catégorie..." 
                                   class="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <select id="statusFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Tous les statuts</option>
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                        <select id="sortFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="newest">Plus récentes</option>
                            <option value="oldest">Plus anciennes</option>
                            <option value="name_asc">Nom (A-Z)</option>
                            <option value="name_desc">Nom (Z-A)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tableau des catégories -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100 sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Désignation</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Boutiques associées</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date création</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="categorieTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($categories as $categorie): ?>
                            <tr class="hover:bg-purple-50 transition-colors duration-200 categorie-row" 
                                data-designation="<?php echo htmlspecialchars(strtolower($categorie['designation'])); ?>"
                                data-status="<?php echo $categorie['statut'] == 0 ? 'actif' : 'inactif'; ?>"
                                data-created="<?php echo $categorie['created_at']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-purple-500 rounded-full flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($categorie['designation'], 0, 2)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($categorie['designation']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php 
                                            $count = isset($boutiquesParCategorie[$categorie['id']]) ? $boutiquesParCategorie[$categorie['id']] : 0;
                                            echo $count . ' boutique' . ($count > 1 ? 's' : '');
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $categorie['statut'] == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $categorie['statut'] == 0 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($categorie['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-purple-600 hover:text-purple-800 mr-3 edit-categorie transition-colors duration-200" 
                                            data-id="<?php echo $categorie['id']; ?>"
                                            data-designation="<?php echo htmlspecialchars($categorie['designation']); ?>"
                                            data-statut="<?php echo $categorie['statut']; ?>"
                                            title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-categorie transition-colors duration-200" 
                                            data-id="<?php echo $categorie['id']; ?>"
                                            title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noResults" class="hidden p-8 text-center">
                    <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-700">Aucune catégorie trouvée</h3>
                    <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
                </div>
            </div>

            <!-- Modal pour ajouter/modifier une catégorie -->
            <div id="categorieModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-2xl rounded-2xl bg-white">
                    <div class="modal-scroll">
                        <div class="mt-3">
                            <!-- En-tête du modal -->
                            <div class="flex justify-between items-center pb-3 border-b">
                                <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Ajouter une catégorie</h3>
                                <button id="closeModal" class="text-gray-400 hover:text-gray-500 transition-colors duration-200">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <!-- Formulaire -->
                            <form id="categorieForm" action="../models/traitement/categorie-post.php" method="POST">
                                <input type="hidden" id="action" name="action" value="ajouter">
                                <input type="hidden" id="categorieId" name="id" value="">
                                
                                <div class="space-y-4 mt-4">
                                    <div>
                                        <label for="designation" class="block text-sm font-medium text-gray-700">Désignation *</label>
                                        <input type="text" id="designation" name="designation" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                            placeholder="Ex: Vêtements, Alimentation, etc.">
                                    </div>

                                    <div>
                                        <label for="description" class="block text-sm font-medium text-gray-700">Description (optionnel)</label>
                                        <textarea id="description" name="description" rows="3"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                            placeholder="Description de la catégorie..."></textarea>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" id="statut" name="statut" value="1" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                        <label for="statut" class="ml-2 block text-sm text-gray-700">Catégorie inactive</label>
                                    </div>

                                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-info-circle text-purple-500 mr-2"></i>
                                            <span class="text-sm text-purple-700">
                                                Une catégorie inactive ne sera pas disponible pour l'affectation aux boutiques.
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions du modal -->
                                <div class="flex justify-end space-x-3 pt-4 border-t mt-6">
                                    <button type="button" id="cancelBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                        Annuler
                                    </button>
                                    <button type="submit" id="saveBtn" class="btn-gradient text-white px-4 py-2 rounded-lg shadow hover-lift transition-all duration-300">
                                        Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
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
                                Êtes-vous sûr de vouloir désactiver cette catégorie ? Elle ne sera plus disponible pour l'affectation aux boutiques.
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="deleteForm" action="../models/traitement/categorie-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" id="deleteCategorieId" value="">
                                <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-200 shadow hover-lift">
                                    Désactiver
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ajouter Toastify JS -->
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

            // Supprimer le message de la session après l'affichage
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        // Éléments du DOM
        const categorieModal = document.getElementById('categorieModal');
        const deleteModal = document.getElementById('deleteModal');
        const categorieForm = document.getElementById('categorieForm');
        const modalTitle = document.getElementById('modalTitle');
        const btnAjouter = document.getElementById('btnAjouter');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelDelete = document.getElementById('cancelDelete');
        const actionInput = document.getElementById('action');
        const categorieIdInput = document.getElementById('categorieId');
        const deleteCategorieIdInput = document.getElementById('deleteCategorieId');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const sortFilter = document.getElementById('sortFilter');
        const categorieTableBody = document.getElementById('categorieTableBody');
        const noResults = document.getElementById('noResults');

        // Variables pour la gestion des actions
        let currentCategorieId = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setupSearchAndFilters();
            setupSorting();
        });

        // Configurer les écouteurs d'événements
        function setupEventListeners() {
            // Bouton pour ajouter une catégorie
            btnAjouter.addEventListener('click', function() {
                showModal();
            });
            
            // Fermer le modal
            closeModal.addEventListener('click', hideModal);
            cancelBtn.addEventListener('click', hideModal);
            
            // Fermer le modal de suppression
            cancelDelete.addEventListener('click', hideDeleteModal);
            
            // Boutons d'édition
            document.querySelectorAll('.edit-categorie').forEach(button => {
                button.addEventListener('click', function() {
                    const categorieId = this.getAttribute('data-id');
                    const designation = this.getAttribute('data-designation');
                    const statut = this.getAttribute('data-statut');
                    
                    editCategorie(categorieId, designation, statut);
                });
            });
            
            // Boutons de suppression
            document.querySelectorAll('.delete-categorie').forEach(button => {
                button.addEventListener('click', function() {
                    const categorieId = this.getAttribute('data-id');
                    showDeleteModal(categorieId);
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

            // Fermer les modales en cliquant en dehors
            document.addEventListener('click', function(event) {
                if (event.target === categorieModal) {
                    hideModal();
                }
                if (event.target === deleteModal) {
                    hideDeleteModal();
                }
            });

            // Empêcher la propagation des clics dans les modales
            document.querySelectorAll('#categorieModal > div, #deleteModal > div').forEach(modalContent => {
                modalContent.addEventListener('click', function(event) {
                    event.stopPropagation();
                });
            });
        }

        // Configurer la recherche et les filtres
        function setupSearchAndFilters() {
            searchInput.addEventListener('input', filterCategories);
            statusFilter.addEventListener('change', filterCategories);
        }

        // Configurer le tri
        function setupSorting() {
            sortFilter.addEventListener('change', function() {
                sortCategories(this.value);
            });
        }

        // Filtrer les catégories
        function filterCategories() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusFilterValue = statusFilter.value;
            
            const rows = document.querySelectorAll('.categorie-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const designation = row.getAttribute('data-designation');
                const status = row.getAttribute('data-status');
                
                const matchesSearch = designation.includes(searchTerm);
                const matchesStatus = !statusFilterValue || status === statusFilterValue;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Afficher/masquer le message "Aucun résultat"
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }

        // Trier les catégories
        function sortCategories(sortBy) {
            const rows = Array.from(document.querySelectorAll('.categorie-row'));
            const tbody = document.getElementById('categorieTableBody');
            
            rows.sort((a, b) => {
                switch(sortBy) {
                    case 'newest':
                        const dateA = new Date(a.getAttribute('data-created'));
                        const dateB = new Date(b.getAttribute('data-created'));
                        return dateB - dateA;
                        
                    case 'oldest':
                        const dateA2 = new Date(a.getAttribute('data-created'));
                        const dateB2 = new Date(b.getAttribute('data-created'));
                        return dateA2 - dateB2;
                        
                    case 'name_asc':
                        const nameA = a.getAttribute('data-designation').toLowerCase();
                        const nameB = b.getAttribute('data-designation').toLowerCase();
                        return nameA.localeCompare(nameB);
                        
                    case 'name_desc':
                        const nameA2 = a.getAttribute('data-designation').toLowerCase();
                        const nameB2 = b.getAttribute('data-designation').toLowerCase();
                        return nameB2.localeCompare(nameA2);
                        
                    default:
                        return 0;
                }
            });
            
            // Réorganiser les lignes dans le tbody
            rows.forEach(row => {
                tbody.appendChild(row);
            });
        }

        // Afficher le modal d'ajout/modification
        function showModal(categorie = null) {
            if (categorie) {
                // Mode édition
                modalTitle.textContent = 'Modifier la catégorie';
                actionInput.value = 'modifier';
                categorieIdInput.value = categorie.id;
                document.getElementById('designation').value = categorie.designation;
                document.getElementById('statut').checked = categorie.statut == 1;
                
                currentCategorieId = categorie.id;
            } else {
                // Mode ajout
                modalTitle.textContent = 'Ajouter une catégorie';
                actionInput.value = 'ajouter';
                categorieForm.reset();
                categorieIdInput.value = '';
                currentCategorieId = null;
            }
            
            categorieModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Empêcher le défilement du body
        }

        // Masquer le modal d'ajout/modification
        function hideModal() {
            categorieModal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Rétablir le défilement du body
        }

        // Afficher le modal de confirmation de suppression
        function showDeleteModal(categorieId) {
            deleteCategorieIdInput.value = categorieId;
            deleteModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Empêcher le défilement du body
        }

        // Masquer le modal de confirmation de suppression
        function hideDeleteModal() {
            deleteModal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // Rétablir le défilement du body
        }

        // Éditer une catégorie
        function editCategorie(categorieId, designation, statut) {
            const categorie = {
                id: categorieId,
                designation: designation,
                statut: statut
            };
            showModal(categorie);
        }

        // Gestion des touches pour fermer les modales avec ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (!categorieModal.classList.contains('hidden')) {
                    hideModal();
                }
                if (!deleteModal.classList.contains('hidden')) {
                    hideDeleteModal();
                }
            }
        });

        // Animation pour les barres de défilement au survol
        document.querySelectorAll('.sidebar-section, .user-menu-scroll, .table-scroll').forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.scrollbarColor = 'rgba(255, 255, 255, 0.5) rgba(255, 255, 255, 0.1)';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.scrollbarColor = 'rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1)';
            });
        });
    </script>
</body>
</html>