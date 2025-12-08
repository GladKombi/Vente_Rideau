<?php
# Se connecter à la BD
include '../connexion/connexion.php';
# Selection Querries
require_once('../models/select/select-contrats.php');

// Vérification d'authentification
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Contrats - GestionLoyer</title>
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
                            <a href="contrats.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-contract mr-3"></i>
                                    Contrats
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                                    Paiements
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
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Contrats</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Contrats</span>
                    </div>
                </div>
                <button id="btnAjouter" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Nouveau contrat
                </button>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card text-white rounded-xl p-5 hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Total contrats</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($contrats); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-file-contract text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Contrats actifs</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $actifs = array_filter($contrats, function($contrat) {
                                        return $contrat['statut'] == 0;
                                    });
                                    echo count($actifs);
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
                            <p class="text-sm font-medium text-white text-opacity-90">Contrats expirés</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $expires = array_filter($contrats, function($contrat) {
                                        return $contrat['date_fin'] && strtotime($contrat['date_fin']) < time();
                                    });
                                    echo count($expires);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-exclamation-triangle text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Paiements en retard</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $en_retard = array_filter($contrats, function($contrat) {
                                        return $contrat['date_paiement'] && strtotime($contrat['date_paiement']) < time() && $contrat['statut'] == 0;
                                    });
                                    echo count($en_retard);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-clock text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Rechercher un contrat..." 
                                   class="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <select id="categorieFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $categorie): ?>
                                <option value="<?php echo $categorie['id']; ?>">
                                    <?php echo htmlspecialchars($categorie['designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statutFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Tous les statuts</option>
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tableau des contrats -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date début</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date fin</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Catégorie</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Loyer mensuel</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date limite paiement</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="contratTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($contrats as $contrat): ?>
                            <tr class="hover:bg-purple-50 transition-colors duration-200 contrat-row" 
                                data-statut="<?php echo $contrat['statut'] == 0 ? 'actif' : 'inactif'; ?>"
                                data-categorie="<?php echo $contrat['categorie']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($contrat['date_debut'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $contrat['date_fin'] ? date('d/m/Y', strtotime($contrat['date_fin'])) : 'Indéterminée'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $contrat['categorie_nom'] ? htmlspecialchars($contrat['categorie_nom']) : 'Non définie'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo number_format($contrat['loyer_mensuel'], 2, ',', ' '); ?> $
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium <?php echo (strtotime($contrat['date_paiement']) < time() && $contrat['statut'] == 0) ? 'text-red-600' : 'text-gray-900'; ?>">
                                        <?php echo date('d/m/Y', strtotime($contrat['date_paiement'])); ?>
                                        <?php if (strtotime($contrat['date_paiement']) < time() && $contrat['statut'] == 0): ?>
                                        <span class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">En retard</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $contrat['statut'] == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $contrat['statut'] == 0 ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-purple-600 hover:text-purple-800 mr-3 edit-contrat transition-colors duration-200" 
                                            data-id="<?php echo $contrat['id']; ?>"
                                            data-date-debut="<?php echo $contrat['date_debut']; ?>"
                                            data-date-fin="<?php echo $contrat['date_fin']; ?>"
                                            data-categorie="<?php echo $contrat['categorie']; ?>"
                                            data-loyer="<?php echo $contrat['loyer_mensuel']; ?>"
                                            data-date-paiement="<?php echo $contrat['date_paiement']; ?>"
                                            data-conditions="<?php echo htmlspecialchars($contrat['conditions_speciales']); ?>"
                                            data-statut="<?php echo $contrat['statut']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($contrat['statut'] == 0): ?>
                                    <button class="text-red-600 hover:text-red-800 delete-contrat transition-colors duration-200" 
                                            data-id="<?php echo $contrat['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="text-green-600 hover:text-green-800 reactiver-contrat transition-colors duration-200" 
                                            data-id="<?php echo $contrat['id']; ?>">
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
                    <h3 class="text-lg font-medium text-gray-700">Aucun contrat trouvé</h3>
                    <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
                </div>
            </div>

            <!-- Modal pour ajouter/modifier un contrat -->
            <div id="contratModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
                    <div class="mt-3">
                        <!-- En-tête du modal -->
                        <div class="flex justify-between items-center pb-3 border-b">
                            <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Nouveau contrat</h3>
                            <button id="closeModal" class="text-gray-400 hover:text-gray-500 transition-colors duration-200">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Formulaire -->
                        <form id="contratForm" action="../models/traitement/contrat-post.php" method="POST">
                            <input type="hidden" id="action" name="action" value="ajouter">
                            <input type="hidden" id="contratId" name="id" value="">
                            
                            <div class="grid grid-cols-1 gap-6 mt-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="date_debut" class="block text-sm font-medium text-gray-700">Date de début *</label>
                                        <input type="date" id="date_debut" name="date_debut" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                            value="<?php echo $date_automatique; ?>">
                                        <p id="date_debut_message" class="text-red-500 text-xs mt-1 hidden"></p>
                                    </div>

                                    <div>
                                        <label for="date_fin" class="block text-sm font-medium text-gray-700">Date de fin</label>
                                        <input type="date" id="date_fin" name="date_fin"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                        <p id="date_fin_message" class="text-red-500 text-xs mt-1 hidden"></p>
                                    </div>
                                </div>

                                <div>
                                    <label for="categorie" class="block text-sm font-medium text-gray-700">Catégorie de boutique</label>
                                    <select id="categorie" name="categorie" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                        <option value="">Sélectionnez une catégorie</option>
                                        <?php foreach ($categories as $categorie): ?>
                                            <option value="<?php echo $categorie['id']; ?>">
                                                <?php echo htmlspecialchars($categorie['designation']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="loyer_mensuel" class="block text-sm font-medium text-gray-700">Loyer mensuel ($) *</label>
                                        <input type="number" id="loyer_mensuel" name="loyer_mensuel" step="0.01" min="0" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                            placeholder="0.00">
                                        <p id="loyer_mensuel_message" class="text-red-500 text-xs mt-1 hidden"></p>
                                    </div>

                                    <div>
                                        <label for="date_paiement" class="block text-sm font-medium text-gray-700">Date limite paiement *</label>
                                        <input type="date" id="date_paiement" name="date_paiement" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                            value="<?php echo $date_paiement_par_defaut; ?>">
                                        <p id="date_paiement_message" class="text-red-500 text-xs mt-1 hidden"></p>
                                    </div>
                                </div>

                                <div>
                                    <label for="conditions_speciales" class="block text-sm font-medium text-gray-700">Conditions spéciales</label>
                                    <textarea id="conditions_speciales" name="conditions_speciales" rows="3"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                        placeholder="Conditions particulières du contrat..."></textarea>
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
                                Êtes-vous sûr de vouloir désactiver ce contrat ?
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="deleteForm" action="../models/traitement/contrat-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" id="deleteContratId" value="">
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
                                Êtes-vous sûr de vouloir réactiver ce contrat ?
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelReactivate" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="reactivateForm" action="../models/traitement/contrat-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="reactiver">
                                <input type="hidden" name="id" id="reactivateContratId" value="">
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
                gravity: "top", // `top` ou `bottom`
                position: "right", // `left`, `center` ou `right`
                stopOnFocus: true, // Arrête la minuterie si l'utilisateur interagit avec la fenêtre
                style: {
                    background: "linear-gradient(to right, <?= ($_SESSION['message']['type'] == 'success') ? '#22c55e, #16a34a' : '#ef4444, #dc2626' ?>)",
                },
                onClick: function() {} // Callback après le clic
            }).showToast();

            // Supprimer le message de la session après l'affichage
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        // Éléments du DOM
        const contratModal = document.getElementById('contratModal');
        const deleteModal = document.getElementById('deleteModal');
        const reactivateModal = document.getElementById('reactivateModal');
        const contratForm = document.getElementById('contratForm');
        const modalTitle = document.getElementById('modalTitle');
        const btnAjouter = document.getElementById('btnAjouter');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelDelete = document.getElementById('cancelDelete');
        const cancelReactivate = document.getElementById('cancelReactivate');
        const actionInput = document.getElementById('action');
        const contratIdInput = document.getElementById('contratId');
        const deleteContratIdInput = document.getElementById('deleteContratId');
        const reactivateContratIdInput = document.getElementById('reactivateContratId');
        const searchInput = document.getElementById('searchInput');
        const categorieFilter = document.getElementById('categorieFilter');
        const statutFilter = document.getElementById('statutFilter');
        const contratTableBody = document.getElementById('contratTableBody');
        const noResults = document.getElementById('noResults');

        // Variables pour la gestion des actions
        let currentContratId = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setupSearchAndFilters();
        });

        // Configurer les écouteurs d'événements
        function setupEventListeners() {
            // Bouton pour ajouter un contrat
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
            document.querySelectorAll('.edit-contrat').forEach(button => {
                button.addEventListener('click', function() {
                    const contratId = this.getAttribute('data-id');
                    const dateDebut = this.getAttribute('data-date-debut');
                    const dateFin = this.getAttribute('data-date-fin');
                    const categorie = this.getAttribute('data-categorie');
                    const loyer = this.getAttribute('data-loyer');
                    const datePaiement = this.getAttribute('data-date-paiement');
                    const conditions = this.getAttribute('data-conditions');
                    const statut = this.getAttribute('data-statut');
                    
                    editContrat(contratId, dateDebut, dateFin, categorie, loyer, datePaiement, conditions, statut);
                });
            });
            
            // Boutons de suppression
            document.querySelectorAll('.delete-contrat').forEach(button => {
                button.addEventListener('click', function() {
                    const contratId = this.getAttribute('data-id');
                    showDeleteModal(contratId);
                });
            });

            // Boutons de réactivation
            document.querySelectorAll('.reactiver-contrat').forEach(button => {
                button.addEventListener('click', function() {
                    const contratId = this.getAttribute('data-id');
                    showReactivateModal(contratId);
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
            contratForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const dateDebut = document.getElementById('date_debut').value;
                const dateFin = document.getElementById('date_fin').value;
                const loyer = document.getElementById('loyer_mensuel').value;
                const datePaiement = document.getElementById('date_paiement').value;
                
                let errors = [];
                
                // Validation des champs obligatoires
                if (!dateDebut) {
                    errors.push('La date de début est obligatoire');
                }
                
                if (!loyer || loyer <= 0) {
                    errors.push('Le loyer mensuel doit être un nombre positif');
                }
                
                if (!datePaiement) {
                    errors.push('La date limite de paiement est obligatoire');
                }
                
                // Validation des dates
                if (dateDebut && datePaiement) {
                    const debut = new Date(dateDebut);
                    const paiement = new Date(datePaiement);
                    
                    if (paiement < debut) {
                        errors.push('La date limite de paiement doit être après la date de début');
                    }
                }
                
                if (dateFin) {
                    const debut = new Date(dateDebut);
                    const fin = new Date(dateFin);
                    const paiement = new Date(datePaiement);
                    
                    if (fin <= debut) {
                        errors.push('La date de fin doit être après la date de début');
                    }
                    
                    if (paiement > fin) {
                        errors.push('La date limite de paiement ne peut pas être après la date de fin du contrat');
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

            // Validation en temps réel des dates
            document.getElementById('date_debut').addEventListener('change', function() {
                validerDatesEnTempsReel();
            });

            document.getElementById('date_fin').addEventListener('change', function() {
                validerDatesEnTempsReel();
            });

            document.getElementById('date_paiement').addEventListener('change', function() {
                validerDatesEnTempsReel();
            });

            document.getElementById('loyer_mensuel').addEventListener('input', function() {
                validerLoyerEnTempsReel();
            });
        }

        // Configurer la recherche et les filtres
        function setupSearchAndFilters() {
            searchInput.addEventListener('input', filterContrats);
            categorieFilter.addEventListener('change', filterContrats);
            statutFilter.addEventListener('change', filterContrats);
        }

        // Filtrer les contrats
        function filterContrats() {
            const searchTerm = searchInput.value.toLowerCase();
            const categorieFilterValue = categorieFilter.value;
            const statutFilterValue = statutFilter.value;
            
            const rows = document.querySelectorAll('.contrat-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const statut = row.getAttribute('data-statut');
                const categorie = row.getAttribute('data-categorie');
                
                // Pour la recherche, on peut chercher dans le texte des cellules
                const rowText = row.textContent.toLowerCase();
                const matchesSearch = rowText.includes(searchTerm);
                const matchesCategorie = !categorieFilterValue || categorie === categorieFilterValue;
                const matchesStatut = !statutFilterValue || statut === statutFilterValue;
                
                if (matchesSearch && matchesCategorie && matchesStatut) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Afficher/masquer le message "Aucun résultat"
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
                contratTableBody.style.display = 'none';
            } else {
                noResults.classList.add('hidden');
                contratTableBody.style.display = '';
            }
        }

        // Validation en temps réel des dates
        function validerDatesEnTempsReel() {
            const dateDebut = document.getElementById('date_debut').value;
            const dateFin = document.getElementById('date_fin').value;
            const datePaiement = document.getElementById('date_paiement').value;
            const saveBtn = document.getElementById('saveBtn');
            
            let erreur = false;
            
            // Réinitialiser tous les messages d'erreur
            cacherMessageChamp('date_debut');
            cacherMessageChamp('date_fin');
            cacherMessageChamp('date_paiement');
            
            if (dateDebut && datePaiement) {
                const debut = new Date(dateDebut);
                const paiement = new Date(datePaiement);
                
                if (paiement < debut) {
                    afficherMessageChamp('date_paiement', 'La date limite de paiement doit être après la date de début');
                    erreur = true;
                }
            }
            
            if (dateFin) {
                const debut = new Date(dateDebut);
                const fin = new Date(dateFin);
                const paiement = new Date(datePaiement);
                
                if (fin <= debut) {
                    afficherMessageChamp('date_fin', 'La date de fin doit être après la date de début');
                    erreur = true;
                }
                
                if (paiement > fin) {
                    afficherMessageChamp('date_paiement', 'La date limite de paiement ne peut pas être après la date de fin');
                    erreur = true;
                }
            }
            
            // Désactiver le bouton si erreur
            saveBtn.disabled = erreur;
        }

        // Validation en temps réel du loyer
        function validerLoyerEnTempsReel() {
            const loyer = document.getElementById('loyer_mensuel').value;
            const saveBtn = document.getElementById('saveBtn');
            
            cacherMessageChamp('loyer_mensuel');
            
            if (loyer && loyer <= 0) {
                afficherMessageChamp('loyer_mensuel', 'Le loyer mensuel doit être supérieur à 0');
                saveBtn.disabled = true;
            }
        }

        function afficherMessageChamp(champId, message) {
            let champ = document.getElementById(champId);
            let messageElement = document.getElementById(champId + '_message');
            
            if (!messageElement) {
                messageElement = document.createElement('p');
                messageElement.id = champId + '_message';
                messageElement.className = 'text-red-500 text-xs mt-1';
                champ.parentNode.appendChild(messageElement);
            }
            
            messageElement.textContent = message;
            messageElement.classList.remove('hidden');
            champ.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            champ.classList.remove('focus:ring-purple-500', 'focus:border-purple-500');
        }

        function cacherMessageChamp(champId) {
            let champ = document.getElementById(champId);
            let messageElement = document.getElementById(champId + '_message');
            
            if (messageElement) {
                messageElement.classList.add('hidden');
            }
            
            champ.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            champ.classList.add('focus:ring-purple-500', 'focus:border-purple-500');
        }

        // Afficher le modal d'ajout
        function showModal() {
            modalTitle.textContent = 'Nouveau contrat';
            actionInput.value = 'ajouter';
            contratForm.reset();
            contratIdInput.value = '';
            currentContratId = null;
            
            // Définir les dates par défaut
            document.getElementById('date_debut').value = '<?php echo $date_automatique; ?>';
            document.getElementById('date_paiement').value = '<?php echo $date_paiement_par_defaut; ?>';
            
            // Réinitialiser les messages d'erreur
            cacherMessageChamp('date_debut');
            cacherMessageChamp('date_fin');
            cacherMessageChamp('date_paiement');
            cacherMessageChamp('loyer_mensuel');
            
            // Réactiver le bouton
            document.getElementById('saveBtn').disabled = false;
            
            contratModal.classList.remove('hidden');
        }

        // Masquer le modal d'ajout
        function hideModal() {
            contratModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de suppression
        function showDeleteModal(contratId) {
            deleteContratIdInput.value = contratId;
            deleteModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de suppression
        function hideDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de réactivation
        function showReactivateModal(contratId) {
            reactivateContratIdInput.value = contratId;
            reactivateModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de réactivation
        function hideReactivateModal() {
            reactivateModal.classList.add('hidden');
        }

        // Éditer un contrat
        function editContrat(contratId, dateDebut, dateFin, categorie, loyer, datePaiement, conditions, statut) {
            const contrat = {
                id: contratId,
                dateDebut: dateDebut,
                dateFin: dateFin,
                categorie: categorie,
                loyer: loyer,
                datePaiement: datePaiement,
                conditions: conditions,
                statut: statut
            };
            showEditModal(contrat);
        }

        // Afficher le modal d'édition
        function showEditModal(contrat) {
            modalTitle.textContent = 'Modifier le contrat';
            actionInput.value = 'modifier';
            contratIdInput.value = contrat.id;
            document.getElementById('date_debut').value = contrat.dateDebut;
            document.getElementById('date_fin').value = contrat.dateFin;
            document.getElementById('categorie').value = contrat.categorie;
            document.getElementById('loyer_mensuel').value = contrat.loyer;
            document.getElementById('date_paiement').value = contrat.datePaiement;
            document.getElementById('conditions_speciales').value = contrat.conditions;
            
            // Réinitialiser les messages d'erreur
            cacherMessageChamp('date_debut');
            cacherMessageChamp('date_fin');
            cacherMessageChamp('date_paiement');
            cacherMessageChamp('loyer_mensuel');
            
            // Réactiver le bouton
            document.getElementById('saveBtn').disabled = false;
            
            currentContratId = contrat.id;
            
            contratModal.classList.remove('hidden');
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