<?php
# Se connecter à la BD
include '../connexion/connexion.php';
# Selection Querries
require_once('../models/select/select-membre.php');


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Locataires - GestionLoyer</title>
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
                <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 text-gray-700 z-20">
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
            <div class="p-4">
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
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-home mr-3"></i>
                                    Biens immobiliers
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="membres.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-users mr-3"></i>
                                    Locataires
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

                <!-- Pied de page de la barre latérale -->
                <div class="absolute bottom-0 left-0 right-0 p-4 glass-effect rounded-t-lg">
                    <div class="text-sm text-white text-opacity-70">Connecté en tant que :</div>
                    <div class="font-semibold">Administrateur</div>
                </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div id="mainContent" class="main-content ml-64 p-6 w-full">
            <!-- En-tête et fil d'Ariane -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Locataires</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Locataires</span>
                    </div>
                </div>
                <button id="btnAjouter" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Ajouter un locataire
                </button>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card text-white rounded-xl p-5 hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Total locataires</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($membres); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-users text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Locataires actifs</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $activeMembres = array_filter($membres, function($membre) {
                                        return $membre['statut'] == 0;
                                    });
                                    echo count($activeMembres);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-user-check text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Nouveaux ce mois</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $currentMonth = date('Y-m');
                                    $newThisMonth = array_filter($membres, function($membre) use ($currentMonth) {
                                        return date('Y-m', strtotime($membre['created_at'])) === $currentMonth;
                                    });
                                    echo count($newThisMonth);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-user-plus text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-red-500 to-red-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Locataires inactifs</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $inactiveMembres = array_filter($membres, function($membre) {
                                        return $membre['statut'] == 1;
                                    });
                                    echo count($inactiveMembres);
                                ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-user-slash text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Rechercher un locataire..." 
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
                    </div>
                </div>
            </div>

            <!-- Tableau des membres -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Nom complet</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Adresse</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Téléphone</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date création</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="membreTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($membres as $membre): ?>
                            <tr class="hover:bg-purple-50 transition-colors duration-200 membre-row" 
                                data-nom="<?php echo htmlspecialchars(strtolower($membre['nom'] . ' ' . $membre['postnom'] . ' ' . $membre['prenom'])); ?>"
                                data-adresse="<?php echo htmlspecialchars(strtolower($membre['adresse'])); ?>"
                                data-telephone="<?php echo htmlspecialchars($membre['telephone']); ?>"
                                data-email="<?php echo htmlspecialchars(strtolower($membre['email'])); ?>"
                                data-status="<?php echo $membre['statut'] == 0 ? 'actif' : 'inactif'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-purple-500 rounded-full flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($membre['prenom'], 0, 1) . substr($membre['nom'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($membre['prenom'] . ' ' . $membre['nom'] . ' ' . $membre['postnom']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($membre['adresse'] ?: 'Non renseignée'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($membre['telephone'] ?: 'Non renseigné'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($membre['email'] ?: 'Non renseigné'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $membre['statut'] == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $membre['statut'] == 0 ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y', strtotime($membre['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-purple-600 hover:text-purple-800 mr-3 edit-membre transition-colors duration-200" 
                                            data-id="<?php echo $membre['id']; ?>"
                                            data-nom="<?php echo htmlspecialchars($membre['nom']); ?>"
                                            data-postnom="<?php echo htmlspecialchars($membre['postnom']); ?>"
                                            data-prenom="<?php echo htmlspecialchars($membre['prenom']); ?>"
                                            data-adresse="<?php echo htmlspecialchars($membre['adresse']); ?>"
                                            data-telephone="<?php echo htmlspecialchars($membre['telephone']); ?>"
                                            data-email="<?php echo htmlspecialchars($membre['email']); ?>"
                                            data-statut="<?php echo $membre['statut']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="text-red-600 hover:text-red-800 delete-membre transition-colors duration-200" 
                                            data-id="<?php echo $membre['id']; ?>">
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
                    <h3 class="text-lg font-medium text-gray-700">Aucun locataire trouvé</h3>
                    <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
                </div>
            </div>

            <!-- Modal pour ajouter/modifier un membre -->
            <div id="membreModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
                    <div class="mt-3">
                        <!-- En-tête du modal -->
                        <div class="flex justify-between items-center pb-3 border-b">
                            <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Ajouter un locataire</h3>
                            <button id="closeModal" class="text-gray-400 hover:text-gray-500 transition-colors duration-200">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Formulaire -->
                        <form id="membreForm" action="../models/traitement/membre-post.php" method="POST">
                            <input type="hidden" id="action" name="action" value="ajouter">
                            <input type="hidden" id="membreId" name="id" value="">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <div class="space-y-4">
                                    <div>
                                        <label for="nom" class="block text-sm font-medium text-gray-700">Nom *</label>
                                        <input type="text" id="nom" name="nom" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                    </div>

                                    <div>
                                        <label for="postnom" class="block text-sm font-medium text-gray-700">Postnom *</label>
                                        <input type="text" id="postnom" name="postnom" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                    </div>

                                    <div>
                                        <label for="prenom" class="block text-sm font-medium text-gray-700">Prénom *</label>
                                        <input type="text" id="prenom" name="prenom" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                    </div>
                                </div>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="adresse" class="block text-sm font-medium text-gray-700">Adresse</label>
                                        <textarea id="adresse" name="adresse" rows="3"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"></textarea>
                                    </div>

                                    <div>
                                        <label for="telephone" class="block text-sm font-medium text-gray-700">Téléphone</label>
                                        <input type="tel" id="telephone" name="telephone"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                    </div>

                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                        <input type="email" id="email" name="email"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" id="statut" name="statut" value="1" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                        <label for="statut" class="ml-2 block text-sm text-gray-700">Locataire inactif</label>
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
                                Êtes-vous sûr de vouloir désactiver ce locataire ? Il ne pourra plus être associé à de nouveaux contrats.
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="deleteForm" action="../models/traitement/membre-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" id="deleteMembreId" value="">
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
        const membreModal = document.getElementById('membreModal');
        const deleteModal = document.getElementById('deleteModal');
        const membreForm = document.getElementById('membreForm');
        const modalTitle = document.getElementById('modalTitle');
        const btnAjouter = document.getElementById('btnAjouter');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelDelete = document.getElementById('cancelDelete');
        const actionInput = document.getElementById('action');
        const membreIdInput = document.getElementById('membreId');
        const deleteMembreIdInput = document.getElementById('deleteMembreId');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const membreTableBody = document.getElementById('membreTableBody');
        const noResults = document.getElementById('noResults');

        // Variables pour la gestion des actions
        let currentMembreId = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setupSearchAndFilters();
        });

        // Configurer les écouteurs d'événements
        function setupEventListeners() {
            // Bouton pour ajouter un membre
            btnAjouter.addEventListener('click', function() {
                showModal();
            });
            
            // Fermer le modal
            closeModal.addEventListener('click', hideModal);
            cancelBtn.addEventListener('click', hideModal);
            
            // Fermer le modal de suppression
            cancelDelete.addEventListener('click', hideDeleteModal);
            
            // Boutons d'édition
            document.querySelectorAll('.edit-membre').forEach(button => {
                button.addEventListener('click', function() {
                    const membreId = this.getAttribute('data-id');
                    const nom = this.getAttribute('data-nom');
                    const postnom = this.getAttribute('data-postnom');
                    const prenom = this.getAttribute('data-prenom');
                    const adresse = this.getAttribute('data-adresse');
                    const telephone = this.getAttribute('data-telephone');
                    const email = this.getAttribute('data-email');
                    const statut = this.getAttribute('data-statut');
                    
                    editMembre(membreId, nom, postnom, prenom, adresse, telephone, email, statut);
                });
            });
            
            // Boutons de suppression
            document.querySelectorAll('.delete-membre').forEach(button => {
                button.addEventListener('click', function() {
                    const membreId = this.getAttribute('data-id');
                    showDeleteModal(membreId);
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
        }

        // Configurer la recherche et les filtres
        function setupSearchAndFilters() {
            searchInput.addEventListener('input', filterMembres);
            statusFilter.addEventListener('change', filterMembres);
        }

        // Filtrer les membres
        function filterMembres() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusFilterValue = statusFilter.value;
            
            const rows = document.querySelectorAll('.membre-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const nom = row.getAttribute('data-nom');
                const adresse = row.getAttribute('data-adresse');
                const telephone = row.getAttribute('data-telephone');
                const email = row.getAttribute('data-email');
                const status = row.getAttribute('data-status');
                
                const matchesSearch = nom.includes(searchTerm) || 
                                   adresse.includes(searchTerm) || 
                                   telephone.includes(searchTerm) || 
                                   email.includes(searchTerm);
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
                membreTableBody.style.display = 'none';
            } else {
                noResults.classList.add('hidden');
                membreTableBody.style.display = '';
            }
        }

        // Afficher le modal d'ajout/modification
        function showModal(membre = null) {
            if (membre) {
                // Mode édition
                modalTitle.textContent = 'Modifier le locataire';
                actionInput.value = 'modifier';
                membreIdInput.value = membre.id;
                document.getElementById('nom').value = membre.nom;
                document.getElementById('postnom').value = membre.postnom;
                document.getElementById('prenom').value = membre.prenom;
                document.getElementById('adresse').value = membre.adresse;
                document.getElementById('telephone').value = membre.telephone;
                document.getElementById('email').value = membre.email;
                document.getElementById('statut').checked = membre.statut == 1;
                
                currentMembreId = membre.id;
            } else {
                // Mode ajout
                modalTitle.textContent = 'Ajouter un locataire';
                actionInput.value = 'ajouter';
                membreForm.reset();
                membreIdInput.value = '';
                currentMembreId = null;
            }
            
            membreModal.classList.remove('hidden');
        }

        // Masquer le modal d'ajout/modification
        function hideModal() {
            membreModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de suppression
        function showDeleteModal(membreId) {
            deleteMembreIdInput.value = membreId;
            deleteModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de suppression
        function hideDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        // Éditer un membre
        function editMembre(membreId, nom, postnom, prenom, adresse, telephone, email, statut) {
            const membre = {
                id: membreId,
                nom: nom,
                postnom: postnom,
                prenom: prenom,
                adresse: adresse,
                telephone: telephone,
                email: email,
                statut: statut
            };
            showModal(membre);
        }
    </script>
</body>
</html>