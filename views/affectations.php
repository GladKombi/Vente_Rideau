<?php
# DB connection
include '../connexion/connexion.php';
# Selection Querries
require_once("../models/select/select-affectation.php");

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Affectations - GestionLoyer</title>
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
        <?php require_once 'aside1.php'?>

        <!-- Contenu principal -->
        <div id="mainContent" class="main-content ml-64 p-6 w-full">
            <!-- En-tête et fil d'Ariane -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Affectations</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Affectations</span>
                    </div>
                </div>
                <button id="btnAjouter" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Nouvelle affectation
                </button>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card text-white rounded-xl p-5 hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Total affectations</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($affectations); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-link text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Affectations actives</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $actives = array_filter($affectations, function($affectation) {
                                        return $affectation['statut'] == 0;
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
                            <p class="text-sm font-medium text-white text-opacity-90">Membres disponibles</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($membres); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-users text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Boutiques libres</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($boutiques); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-store text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Rechercher une affectation..." 
                                   class="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <select id="statutFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Tous les statuts</option>
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tableau des affectations -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date Affectation</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Membre</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Téléphone</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Boutique</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Catégorie</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="affectationTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($affectations as $affectation): ?>
                            <tr class="hover:bg-purple-50 transition-colors duration-200 affectation-row" 
                                data-membre="<?php echo htmlspecialchars(strtolower($affectation['membre_nom'] . ' ' . $affectation['membre_postnom'])); ?>"
                                data-boutique="<?php echo htmlspecialchars(strtolower($affectation['boutique_numero'])); ?>"
                                data-statut="<?php echo $affectation['statut'] == 0 ? 'actif' : 'inactif'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($affectation['date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-blue-500 rounded-full flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($affectation['membre_nom'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($affectation['membre_nom'] . ' ' . $affectation['membre_postnom'] . ' ' . $affectation['membre_prenom']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($affectation['membre_telephone']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0 bg-purple-500 rounded-full flex items-center justify-center">
                                            <span class="text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($affectation['boutique_numero'], 0, 2)); ?>
                                            </span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($affectation['boutique_numero']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                        <?php echo htmlspecialchars($affectation['categorie_nom']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $affectation['statut'] == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $affectation['statut'] == 0 ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($affectation['statut'] == 0): ?>
                                    <button class="text-red-600 hover:text-red-800 delete-affectation transition-colors duration-200" 
                                            data-id="<?php echo $affectation['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="text-green-600 hover:text-green-800 reactiver-affectation transition-colors duration-200" 
                                            data-id="<?php echo $affectation['id']; ?>">
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
                    <h3 class="text-lg font-medium text-gray-700">Aucune affectation trouvée</h3>
                    <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
                </div>
            </div>

            <!-- Modal pour ajouter une affectation -->
            <div id="affectationModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
                    <div class="mt-3">
                        <!-- En-tête du modal -->
                        <div class="flex justify-between items-center pb-3 border-b">
                            <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Nouvelle affectation</h3>
                            <button id="closeModal" class="text-gray-400 hover:text-gray-500 transition-colors duration-200">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Formulaire -->
                        <form id="affectationForm" action="../models/traitement/affectation-post.php" method="POST">
                            <input type="hidden" id="action" name="action" value="ajouter">
                            <input type="hidden" id="affectationId" name="id" value="">
                            
                            <div class="grid grid-cols-1 gap-6 mt-4">
                                <div>
                                    <label for="date" class="block text-sm font-medium text-gray-700">Date d'affectation</label>
                                    <input type="date" id="date" name="date" readonly
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm bg-gray-100 cursor-not-allowed"
                                        value="<?php echo $date_automatique; ?>">
                                    <p class="text-sm text-gray-500 mt-1">La date est automatiquement définie à aujourd'hui</p>
                                </div>

                                <div>
                                    <label for="membre" class="block text-sm font-medium text-gray-700">Membre *</label>
                                    <select id="membre" name="membre" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                        <option value="">Sélectionner un membre</option>
                                        <?php foreach($membres as $membre): ?>
                                            <option value="<?php echo $membre['id']; ?>">
                                                <?php echo htmlspecialchars($membre['nom'] . ' ' . $membre['postnom'] . ' ' . $membre['prenom'] . ' - ' . $membre['telephone']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="boutique" class="block text-sm font-medium text-gray-700">Boutique *</label>
                                    <select id="boutique" name="boutique" required
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                        <option value="">Sélectionner une boutique</option>
                                        <?php foreach($boutiques as $boutique): ?>
                                            <option value="<?php echo $boutique['id']; ?>">
                                                <?php echo htmlspecialchars($boutique['numero'] . ' - ' . $boutique['categorie_nom']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                        <span class="text-sm text-blue-700">
                                            L'affectation d'un membre à une boutique marquera automatiquement la boutique comme "occupée".
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
                                Êtes-vous sûr de vouloir désactiver cette affectation ? La boutique concernée redeviendra libre.
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="deleteForm" action="../models/traitement/affectation-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" id="deleteAffectationId" value="">
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
                                Êtes-vous sûr de vouloir réactiver cette affectation ? La boutique concernée sera à nouveau marquée comme occupée.
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelReactivate" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="reactivateForm" action="../models/traitement/affectation-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="reactiver">
                                <input type="hidden" name="id" id="reactivateAffectationId" value="">
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
        const affectationModal = document.getElementById('affectationModal');
        const deleteModal = document.getElementById('deleteModal');
        const reactivateModal = document.getElementById('reactivateModal');
        const affectationForm = document.getElementById('affectationForm');
        const modalTitle = document.getElementById('modalTitle');
        const btnAjouter = document.getElementById('btnAjouter');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelDelete = document.getElementById('cancelDelete');
        const cancelReactivate = document.getElementById('cancelReactivate');
        const actionInput = document.getElementById('action');
        const affectationIdInput = document.getElementById('affectationId');
        const deleteAffectationIdInput = document.getElementById('deleteAffectationId');
        const reactivateAffectationIdInput = document.getElementById('reactivateAffectationId');
        const searchInput = document.getElementById('searchInput');
        const statutFilter = document.getElementById('statutFilter');
        const affectationTableBody = document.getElementById('affectationTableBody');
        const noResults = document.getElementById('noResults');

        // Variables pour la gestion des actions
        let currentAffectationId = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setupSearchAndFilters();
        });

        // Configurer les écouteurs d'événements
        function setupEventListeners() {
            // Bouton pour ajouter une affectation
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
            
            // Boutons de suppression
            document.querySelectorAll('.delete-affectation').forEach(button => {
                button.addEventListener('click', function() {
                    const affectationId = this.getAttribute('data-id');
                    showDeleteModal(affectationId);
                });
            });

            // Boutons de réactivation
            document.querySelectorAll('.reactiver-affectation').forEach(button => {
                button.addEventListener('click', function() {
                    const affectationId = this.getAttribute('data-id');
                    showReactivateModal(affectationId);
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
            affectationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const membre = document.getElementById('membre').value;
                const boutique = document.getElementById('boutique').value;
                
                let errors = [];
                
                if (!membre) {
                    errors.push('Le membre est obligatoire');
                }
                
                if (!boutique) {
                    errors.push('La boutique est obligatoire');
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

        // Configurer la recherche et les filtres
        function setupSearchAndFilters() {
            searchInput.addEventListener('input', filterAffectations);
            statutFilter.addEventListener('change', filterAffectations);
        }

        // Filtrer les affectations
        function filterAffectations() {
            const searchTerm = searchInput.value.toLowerCase();
            const statutFilterValue = statutFilter.value;
            
            const rows = document.querySelectorAll('.affectation-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const membre = row.getAttribute('data-membre');
                const boutique = row.getAttribute('data-boutique');
                const statut = row.getAttribute('data-statut');
                
                const matchesSearch = membre.includes(searchTerm) || boutique.includes(searchTerm);
                const matchesStatut = !statutFilterValue || statut === statutFilterValue;
                
                if (matchesSearch && matchesStatut) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Afficher/masquer le message "Aucun résultat"
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
                affectationTableBody.style.display = 'none';
            } else {
                noResults.classList.add('hidden');
                affectationTableBody.style.display = '';
            }
        }

        // Afficher le modal d'ajout
        function showModal() {
            modalTitle.textContent = 'Nouvelle affectation';
            actionInput.value = 'ajouter';
            affectationForm.reset();
            affectationIdInput.value = '';
            currentAffectationId = null;
            
            affectationModal.classList.remove('hidden');
        }

        // Masquer le modal d'ajout
        function hideModal() {
            affectationModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de suppression
        function showDeleteModal(affectationId) {
            deleteAffectationIdInput.value = affectationId;
            deleteModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de suppression
        function hideDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de réactivation
        function showReactivateModal(affectationId) {
            reactivateAffectationIdInput.value = affectationId;
            reactivateModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de réactivation
        function hideReactivateModal() {
            reactivateModal.classList.add('hidden');
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