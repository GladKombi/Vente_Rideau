<?php
# Se connecter à la BD
include '../connexion/connexion.php';
# Selection Querries
require_once('../models/select/select-boutique.php');

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Boutiques - GestionLoyer</title>
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
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-user mr-2 text-purple-500"></i>Profil
                    </a>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-cog mr-2 text-purple-500"></i>Paramètres
                    </a>
                    <a href="#" class="block px-4 py-2 hover:bg-purple-50 transition-colors duration-200">
                        <i class="fas fa-history mr-2 text-purple-500"></i>Journal d'activité
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
                            <a href="boutiques.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-store mr-3"></i>
                                    Boutiques
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo count($boutiques); ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="categories.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
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
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo isset($membres_count) ? $membres_count : '0'; ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="affectations.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-link mr-3"></i>
                                    Affectations
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo isset($affectations_count) ? $affectations_count : '0'; ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="contrats.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-contract mr-3"></i>
                                    Contrats
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo isset($contrats_count) ? $contrats_count : '0'; ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="paiements.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                                    Paiements Loyer
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo isset($paiements_count) ? $paiements_count : '0'; ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="charges.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-money-bill-wave mr-3"></i>
                                    Charges
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo isset($charges_count) ? $charges_count : '0'; ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="paiements_charges.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-credit-card mr-3"></i>
                                    Paiements Charges
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo isset($paiements_charges_count) ? $paiements_charges_count : '0'; ?></span>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="utilisateurs.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-user-cog mr-3"></i>
                                    Utilisateurs
                                </div>
                                <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo isset($utilisateurs_count) ? $utilisateurs_count : '0'; ?></span>
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
                <div class="text-xs text-white text-opacity-60 mt-1">Dernière connexion : Aujourd'hui</div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div id="mainContent" class="main-content ml-64 p-6 w-full">
            <!-- En-tête et fil d'Ariane -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Boutiques</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Boutiques</span>
                    </div>
                </div>
                <button id="btnAjouter" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Ajouter une boutique
                </button>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card text-white rounded-xl p-5 hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Total boutiques</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($boutiques); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-store text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Boutiques libres</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php
                                $libres = array_filter($boutiques, function ($boutique) {
                                    return $boutique['etat'] == 'libre' && $boutique['statut'] == 0;
                                });
                                echo count($libres);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-door-open text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Boutiques occupées</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php
                                $occupees = array_filter($boutiques, function ($boutique) {
                                    return $boutique['etat'] == 'occupée' && $boutique['statut'] == 0;
                                });
                                echo count($occupees);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-door-closed text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Boutiques inactives</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php
                                $inactives = array_filter($boutiques, function ($boutique) {
                                    return $boutique['statut'] == 1;
                                });
                                echo count($inactives);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-store-slash text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Rechercher une boutique..."
                                class="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <select id="etatFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Tous les états</option>
                            <option value="libre">Libre</option>
                            <option value="occupée">Occupée</option>
                        </select>
                        <select id="categorieFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $categorie): ?>
                                <option value="<?php echo $categorie['id']; ?>">
                                    <?php echo htmlspecialchars($categorie['designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Tous les statuts</option>
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tableau des boutiques -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Numéro</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Surface</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">État</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Catégorie</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date création</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="boutiqueTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($boutiques as $boutique):
                                // Trouver le nom de la catégorie
                                $nomCategorie = 'Non définie';
                                foreach ($categories as $categorie) {
                                    if ($categorie['id'] == $boutique['categorie']) {
                                        $nomCategorie = $categorie['designation'];
                                        break;
                                    }
                                }
                            ?>
                                <tr class="hover:bg-purple-50 transition-colors duration-200 boutique-row"
                                    data-numero="<?php echo htmlspecialchars(strtolower($boutique['numero'])); ?>"
                                    data-surface="<?php echo htmlspecialchars($boutique['surface']); ?>"
                                    data-etat="<?php echo htmlspecialchars($boutique['etat']); ?>"
                                    data-categorie="<?php echo htmlspecialchars($boutique['categorie']); ?>"
                                    data-status="<?php echo $boutique['statut'] == 0 ? 'actif' : 'inactif'; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0 bg-purple-500 rounded-full flex items-center justify-center">
                                                <span class="text-white font-semibold text-sm">
                                                    <?php echo strtoupper(substr($boutique['numero'], 0, 2)); ?>
                                                </span>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($boutique['numero']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($boutique['surface']); ?> m²</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $boutique['etat'] == 'libre' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo $boutique['etat'] == 'libre' ? 'Libre' : 'Occupée'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                            <?php echo htmlspecialchars($nomCategorie); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $boutique['statut'] == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $boutique['statut'] == 0 ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('d/m/Y', strtotime($boutique['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="text-purple-600 hover:text-purple-800 mr-3 edit-boutique transition-colors duration-200"
                                            data-id="<?php echo $boutique['id']; ?>"
                                            data-surface="<?php echo htmlspecialchars($boutique['surface']); ?>"
                                            data-etat="<?php echo htmlspecialchars($boutique['etat']); ?>"
                                            data-categorie="<?php echo htmlspecialchars($boutique['categorie']); ?>"
                                            data-statut="<?php echo $boutique['statut']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($boutique['statut'] == 0): ?>
                                            <button class="text-red-600 hover:text-red-800 delete-boutique transition-colors duration-200"
                                                data-id="<?php echo $boutique['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="text-green-600 hover:text-green-800 reactiver-boutique transition-colors duration-200"
                                                data-id="<?php echo $boutique['id']; ?>">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noResults" class="hidden p-8 text-center">
                    <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-700">Aucune boutique trouvée</h3>
                    <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
                </div>
            </div>

            <!-- Modal pour ajouter/modifier une boutique -->
            <div id="boutiqueModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
                    <div class="mt-3">
                        <!-- En-tête du modal -->
                        <div class="flex justify-between items-center pb-3 border-b">
                            <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Ajouter une boutique</h3>
                            <button id="closeModal" class="text-gray-400 hover:text-gray-500 transition-colors duration-200">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Formulaire -->
                        <form id="boutiqueForm" action="../models/traitement/boutique-post.php" method="POST">
                            <input type="hidden" id="action" name="action" value="ajouter">
                            <input type="hidden" id="boutiqueId" name="id" value="">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <div class="space-y-4">
                                    <div>
                                        <label for="surface" class="block text-sm font-medium text-gray-700">Surface (m²) *</label>
                                        <input type="number" id="surface" name="surface" step="0.01" min="0" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                            placeholder="Ex: 25.50">
                                    </div>

                                    <div>
                                        <label for="etat" class="block text-sm font-medium text-gray-700">État *</label>
                                        <select id="etat" name="etat" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                            <option value="">Sélectionner un état</option>
                                            <option value="libre">Libre</option>
                                            <option value="occupée">Occupée</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label for="categorie" class="block text-sm font-medium text-gray-700">Catégorie *</label>
                                        <select id="categorie" name="categorie" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                            <option value="">Sélectionner une catégorie</option>
                                            <?php foreach ($categories as $categorie): ?>
                                                <option value="<?php echo $categorie['id']; ?>">
                                                    <?php echo htmlspecialchars($categorie['designation']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" id="statut" name="statut" value="1" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                        <label for="statut" class="ml-2 block text-sm text-gray-700">Boutique inactive</label>
                                    </div>

                                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mt-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-info-circle text-purple-500 mr-2"></i>
                                            <span class="text-sm text-purple-700">
                                                Le numéro sera généré automatiquement (ex: B001, Q001) selon la catégorie sélectionnée.
                                            </span>
                                        </div>
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
                                Êtes-vous sûr de vouloir désactiver cette boutique ? Elle ne sera plus disponible pour la location.
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="deleteForm" action="../models/traitement/boutique-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" id="deleteBoutiqueId" value="">
                                <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-200 shadow hover-lift">
                                    Désactiver
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
                                Êtes-vous sûr de vouloir réactiver cette boutique ? Elle sera à nouveau disponible pour la location.
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelReactivate" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="reactivateForm" action="../models/traitement/boutique-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="reactiver">
                                <input type="hidden" name="id" id="reactivateBoutiqueId" value="">
                                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors duration-200 shadow hover-lift">
                                    Réactiver
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

            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        // Éléments du DOM
        const boutiqueModal = document.getElementById('boutiqueModal');
        const deleteModal = document.getElementById('deleteModal');
        const reactivateModal = document.getElementById('reactivateModal');
        const boutiqueForm = document.getElementById('boutiqueForm');
        const modalTitle = document.getElementById('modalTitle');
        const btnAjouter = document.getElementById('btnAjouter');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelDelete = document.getElementById('cancelDelete');
        const cancelReactivate = document.getElementById('cancelReactivate');
        const actionInput = document.getElementById('action');
        const boutiqueIdInput = document.getElementById('boutiqueId');
        const deleteBoutiqueIdInput = document.getElementById('deleteBoutiqueId');
        const reactivateBoutiqueIdInput = document.getElementById('reactivateBoutiqueId');
        const searchInput = document.getElementById('searchInput');
        const etatFilter = document.getElementById('etatFilter');
        const categorieFilter = document.getElementById('categorieFilter');
        const statusFilter = document.getElementById('statusFilter');
        const boutiqueTableBody = document.getElementById('boutiqueTableBody');
        const noResults = document.getElementById('noResults');

        // Variables pour la gestion des actions
        let currentBoutiqueId = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setupSearchAndFilters();
            setupSidebarToggle();
        });

        // Configurer le toggle de la sidebar
        function setupSidebarToggle() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('ml-64');
                    mainContent.classList.toggle('ml-0');
                });
            }

            // Toggle du menu utilisateur
            const userMenuButton = document.getElementById('userMenuButton');
            const userMenu = document.getElementById('userMenu');
            
            if (userMenuButton && userMenu) {
                userMenuButton.addEventListener('click', function() {
                    userMenu.classList.toggle('hidden');
                });

                // Fermer le menu utilisateur en cliquant ailleurs
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.add('hidden');
                    }
                });
            }
        }

        // Configurer les écouteurs d'événements
        function setupEventListeners() {
            // Bouton pour ajouter une boutique
            btnAjouter.addEventListener('click', function() {
                showModal();
            });

            // Fermer le modal
            closeModal.addEventListener('click', hideModal);
            cancelBtn.addEventListener('click', hideModal);

            // Fermer le modal de suppression
            cancelDelete.addEventListener('click', hideDeleteModal);

            // Fermer le modal de réactivation
            cancelReactivate.addEventListener('click', hideReactivateModal);

            // Boutons d'édition
            document.querySelectorAll('.edit-boutique').forEach(button => {
                button.addEventListener('click', function() {
                    const boutiqueId = this.getAttribute('data-id');
                    const surface = this.getAttribute('data-surface');
                    const etat = this.getAttribute('data-etat');
                    const categorie = this.getAttribute('data-categorie');
                    const statut = this.getAttribute('data-statut');

                    editBoutique(boutiqueId, surface, etat, categorie, statut);
                });
            });

            // Boutons de suppression
            document.querySelectorAll('.delete-boutique').forEach(button => {
                button.addEventListener('click', function() {
                    const boutiqueId = this.getAttribute('data-id');
                    showDeleteModal(boutiqueId);
                });
            });

            // Boutons de réactivation
            document.querySelectorAll('.reactiver-boutique').forEach(button => {
                button.addEventListener('click', function() {
                    const boutiqueId = this.getAttribute('data-id');
                    showReactivateModal(boutiqueId);
                });
            });

            // Validation du formulaire
            boutiqueForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const surface = document.getElementById('surface').value;
                const etat = document.getElementById('etat').value;
                const categorie = document.getElementById('categorie').value;

                let errors = [];

                if (!surface || surface <= 0) {
                    errors.push('La surface doit être un nombre positif');
                }

                if (!etat) {
                    errors.push('L\'état est obligatoire');
                }

                if (!categorie) {
                    errors.push('La catégorie est obligatoire');
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

            // Fermer les modales en cliquant en dehors
            document.addEventListener('click', function(event) {
                if (event.target === boutiqueModal) {
                    hideModal();
                }
                if (event.target === deleteModal) {
                    hideDeleteModal();
                }
                if (event.target === reactivateModal) {
                    hideReactivateModal();
                }
            });

            // Gestion des touches pour fermer les modales avec ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (!boutiqueModal.classList.contains('hidden')) {
                        hideModal();
                    }
                    if (!deleteModal.classList.contains('hidden')) {
                        hideDeleteModal();
                    }
                    if (!reactivateModal.classList.contains('hidden')) {
                        hideReactivateModal();
                    }
                }
            });
        }

        // Configurer la recherche et les filtres
        function setupSearchAndFilters() {
            searchInput.addEventListener('input', filterBoutiques);
            etatFilter.addEventListener('change', filterBoutiques);
            categorieFilter.addEventListener('change', filterBoutiques);
            statusFilter.addEventListener('change', filterBoutiques);
        }

        // Filtrer les boutiques
        function filterBoutiques() {
            const searchTerm = searchInput.value.toLowerCase();
            const etatFilterValue = etatFilter.value;
            const categorieFilterValue = categorieFilter.value;
            const statusFilterValue = statusFilter.value;

            const rows = document.querySelectorAll('.boutique-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const numero = row.getAttribute('data-numero');
                const etat = row.getAttribute('data-etat');
                const categorie = row.getAttribute('data-categorie');
                const status = row.getAttribute('data-status');

                const matchesSearch = numero.includes(searchTerm);
                const matchesEtat = !etatFilterValue || etat === etatFilterValue;
                const matchesCategorie = !categorieFilterValue || categorie === categorieFilterValue;
                const matchesStatus = !statusFilterValue || status === statusFilterValue;

                if (matchesSearch && matchesEtat && matchesCategorie && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Afficher/masquer le message "Aucun résultat"
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
                boutiqueTableBody.style.display = 'none';
            } else {
                noResults.classList.add('hidden');
                boutiqueTableBody.style.display = '';
            }
        }

        // Afficher le modal d'ajout/modification
        function showModal(boutique = null) {
            if (boutique) {
                // Mode édition
                modalTitle.textContent = 'Modifier la boutique';
                actionInput.value = 'modifier';
                boutiqueIdInput.value = boutique.id;
                document.getElementById('surface').value = boutique.surface;
                document.getElementById('etat').value = boutique.etat;
                document.getElementById('categorie').value = boutique.categorie;
                document.getElementById('statut').checked = boutique.statut == 1;

                currentBoutiqueId = boutique.id;
            } else {
                // Mode ajout
                modalTitle.textContent = 'Ajouter une boutique';
                actionInput.value = 'ajouter';
                boutiqueForm.reset();
                boutiqueIdInput.value = '';
                currentBoutiqueId = null;
            }

            boutiqueModal.classList.remove('hidden');
        }

        // Masquer le modal d'ajout/modification
        function hideModal() {
            boutiqueModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de suppression
        function showDeleteModal(boutiqueId) {
            deleteBoutiqueIdInput.value = boutiqueId;
            deleteModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de suppression
        function hideDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de réactivation
        function showReactivateModal(boutiqueId) {
            reactivateBoutiqueIdInput.value = boutiqueId;
            reactivateModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de réactivation
        function hideReactivateModal() {
            reactivateModal.classList.add('hidden');
        }

        // Éditer une boutique
        function editBoutique(boutiqueId, surface, etat, categorie, statut) {
            const boutique = {
                id: boutiqueId,
                surface: surface,
                etat: etat,
                categorie: categorie,
                statut: statut
            };
            showModal(boutique);
        }
    </script>
</body>

</html>