<?php
# DB connection
include '../connexion/connexion.php';
# Selection Querries
require_once("../models/select/select-membre.php");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Membres - GestionLoyer</title>
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
        
        /* Style pour les formulaires */
        .form-input {
            transition: all 0.3s;
        }
        
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Animation pour les messages */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-out;
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
                            <a href="membres.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
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
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Locataires</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Locataires</span>
                    </div>
                </div>
                <a href="membres.php?NewMember" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Nouveau locataire
                </a>
            </div>

            <!-- Messages de session -->
            <?php if (isset($_SESSION['msg']) && !empty($_SESSION['msg'])): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 fade-in">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                        <p class="text-blue-700"><?= htmlspecialchars($_SESSION['msg']) ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <!-- Formulaire d'ajout/modification -->
            <?php if (isset($_GET['NewMember'])): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-gray-800 text-center"><?= isset($_GET['idMembre']) ? 'Modifier le locataire' : 'Nouveau locataire' ?></h2>
                        <div class="w-20 h-1 bg-gradient-to-r from-purple-500 to-pink-500 mx-auto mt-2 rounded-full"></div>
                    </div>

                    <form action="<?= $Action ?>" method="POST" class="space-y-6" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <!-- Nom -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nom <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="nom" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                       placeholder="Entrez le nom"
                                       value="<?= isset($_GET['idMembre']) ? htmlspecialchars($element['nom']) : '' ?>"
                                       required>
                            </div>

                            <!-- Postnom -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Postnom <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="postnom" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                       placeholder="Entrez le postnom"
                                       value="<?= isset($_GET['idMembre']) ? htmlspecialchars($element['postnom']) : '' ?>"
                                       required>
                            </div>

                            <!-- Prénom -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Prénom <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="prenom" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                       placeholder="Entrez le prénom"
                                       value="<?= isset($_GET['idMembre']) ? htmlspecialchars($element['prenom']) : '' ?>"
                                       required>
                            </div>

                            <!-- Genre -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Genre <span class="text-red-500">*</span>
                                </label>
                                <select name="genre" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input" required>
                                    <?php if (isset($_GET['idMembre'])): ?>
                                        <option value="Masculin" <?= $tab['genre'] == 'Masculin' ? 'selected' : '' ?>>Masculin</option>
                                        <option value="Feminin" <?= $tab['genre'] == 'Feminin' ? 'selected' : '' ?>>Feminin</option>
                                    <?php else: ?>
                                        <option value="" disabled selected>Choisir un genre</option>
                                        <option value="Masculin">Masculin</option>
                                        <option value="Feminin">Feminin</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Date de naissance -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Date de naissance <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="dateNaiss" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                       value="<?= isset($_GET['idMembre']) ? $element['dateNaissance'] : '' ?>"
                                       required>
                            </div>

                            <!-- État civil -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    État civil <span class="text-red-500">*</span>
                                </label>
                                <select name="EtatCivil" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input" required>
                                    <?php if (isset($_GET['idMembre'])): ?>
                                        <?php $EtatCvl = $element['etatCivil']; ?>
                                        <option value="Celibataire" <?= $EtatCvl == "Celibataire" ? 'selected' : '' ?>>Célibataire</option>
                                        <option value="Fiance" <?= $EtatCvl == "Fiance" ? 'selected' : '' ?>>Fiancé(e)</option>
                                        <option value="Marie" <?= $EtatCvl == "Marie" ? 'selected' : '' ?>>Marié(e)</option>
                                    <?php else: ?>
                                        <option value="Celibataire">Célibataire</option>
                                        <option value="Fiance">Fiancé(e)</option>
                                        <option value="Marie">Marié(e)</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Adresse -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Adresse <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="adress" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                       placeholder="Entrez l'adresse"
                                       value="<?= isset($_GET['idMembre']) ? htmlspecialchars($element['adress']) : '' ?>"
                                       required>
                            </div>

                            <!-- Ville -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Ville <span class="text-red-500">*</span>
                                </label>
                                <select name="ville" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input" required>
                                    <?php while ($Ville = $getVille->fetch()): ?>
                                        <?php if (isset($_GET['idMembre'])): ?>
                                            <?php $VilleModif = $element['ville']; ?>
                                            <option value="<?= $Ville['id'] ?>" <?= $VilleModif == $Ville['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($Ville['nom']) ?>
                                            </option>
                                        <?php else: ?>
                                            <option value="<?= $Ville['id'] ?>"><?= htmlspecialchars($Ville['nom']) ?></option>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Téléphone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Téléphone <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="telephone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                       placeholder="Entrez le numéro de téléphone"
                                       value="<?= isset($_GET['idMembre']) ? htmlspecialchars($element['telephone']) : '' ?>"
                                       required>
                            </div>

                            <!-- Profession -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Profession <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="profession" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                       placeholder="Entrez la profession"
                                       value="<?= isset($_GET['idMembre']) ? htmlspecialchars($element['profession']) : '' ?>"
                                       required>
                            </div>

                            <!-- Photo de profil (uniquement pour nouvel ajout) -->
                            <?php if (!isset($_GET['idMembre'])): ?>
                                <div class="md:col-span-2 lg:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Photo de profil <span class="text-red-500">*</span>
                                    </label>
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1">
                                            <input type="file" name="picture" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 form-input"
                                                   accept="image/*"
                                                   required>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Formats acceptés: JPG, PNG, GIF
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="flex justify-end space-x-4 pt-6 border-t">
                            <?php if (isset($_GET['idMembre'])): ?>
                                <a href="membres.php" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium">
                                    <i class="fas fa-times mr-2"></i> Annuler
                                </a>
                                <button type="submit" name="valider" class="btn-gradient text-white px-6 py-3 rounded-lg shadow hover-lift transition-all duration-300 font-medium">
                                    <i class="fas fa-save mr-2"></i> Modifier
                                </button>
                            <?php else: ?>
                                <a href="membres.php" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium">
                                    <i class="fas fa-times mr-2"></i> Annuler
                                </a>
                                <button type="submit" name="valider" class="btn-gradient text-white px-6 py-3 rounded-lg shadow hover-lift transition-all duration-300 font-medium">
                                    <i class="fas fa-save mr-2"></i> Enregistrer
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Tableau des membres -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="p-6 border-b">
                    <h2 class="text-xl font-semibold text-gray-800">Liste des Locataires</h2>
                    <p class="text-gray-600 mt-1"><?php 
                        $getMember->execute();
                        $count = $getMember->rowCount();
                        echo $count . " locataire" . ($count > 1 ? 's' : '') . " trouvé" . ($count > 1 ? 's' : '');
                        $getMember->execute(); // Ré-exécuter pour l'affichage des données
                    ?></p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Nom complet</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Genre</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Âge</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Adresse</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">État civil</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Profession</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Téléphone</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Ville</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Profil</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $numero = 0;
                            while ($affiche = $getMember->fetch()):
                                $numero++;
                                $age = $affiche['age'];
                            ?>
                                <tr class="hover:bg-purple-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= $numero ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($affiche['nom'] . " " . $affiche['postnom'] . " " . $affiche['prenom']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $affiche['genre'] == 'Masculin' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800' ?>">
                                            <?= $affiche['genre'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium <?= $age > 12 ? 'text-gray-900' : 'text-red-600' ?>">
                                            <?= $age ?> Ans
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 max-w-xs truncate"><?= htmlspecialchars($affiche['adress']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $affiche['etatCivil'] == 'Celibataire' ? 'bg-gray-100 text-gray-800' : 
                                               ($affiche['etatCivil'] == 'Fiance' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                                            <?= $affiche['etatCivil'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($affiche['profession']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($affiche['telephone']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= htmlspecialchars($affiche['NomVille']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img src="../assets/img/profiles/<?= htmlspecialchars($affiche["photo"]) ?>" 
                                                 alt="Photo de profil" 
                                                 class="rounded-full w-12 h-12 object-cover border-2 border-gray-200 shadow-sm">
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="membres.php?NewMember&idMembre=<?= $affiche['id'] ?>" 
                                               class="text-purple-600 hover:text-purple-800 transition-colors duration-200"
                                               title="Modifier">
                                                <i class="fas fa-edit text-lg"></i>
                                            </a>
                                            <a onclick="return confirm('Voulez-vous vraiment supprimer ce locataire ?')" 
                                               href="../models/delete/deletjeunes.php?idMembre=<?= $affiche['id'] ?>" 
                                               class="text-red-600 hover:text-red-800 transition-colors duration-200"
                                               title="Supprimer">
                                                <i class="fas fa-trash text-lg"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($numero == 0): ?>
                    <div class="p-12 text-center">
                        <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-700">Aucun locataire trouvé</h3>
                        <p class="text-gray-500 mt-2">Commencez par ajouter votre premier locataire</p>
                        <a href="membres.php?NewMember" class="btn-gradient text-white px-6 py-3 rounded-lg inline-flex items-center mt-4 hover-lift">
                            <i class="fas fa-plus mr-2"></i> Ajouter un locataire
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ajouter Toastify JS -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <script>
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
        });

        // Configurer les écouteurs d'événements
        function setupEventListeners() {
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
                
                if (userMenuButton && userMenu && !userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                    userMenu.classList.add('hidden');
                }
            });

            // Validation du formulaire
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Validation basique pour s'assurer que les champs requis sont remplis
                    const requiredInputs = this.querySelectorAll('[required]');
                    let isValid = true;
                    const errors = [];
                    
                    requiredInputs.forEach(input => {
                        if (!input.value.trim()) {
                            isValid = false;
                            input.classList.add('border-red-500');
                            errors.push(`Le champ "${input.name}" est requis`);
                        } else {
                            input.classList.remove('border-red-500');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        Toastify({
                            text: "Veuillez remplir tous les champs obligatoires",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            style: {
                                background: "linear-gradient(to right, #ef4444, #dc2626)",
                            },
                        }).showToast();
                    }
                });
            });

            // Afficher une confirmation pour les suppressions
            const deleteLinks = document.querySelectorAll('a[onclick*="confirm"]');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm(this.getAttribute('onclick').match(/confirm\('([^']+)'/)[1])) {
                        e.preventDefault();
                    }
                });
            });
        }

        // Navigation au clavier
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const userMenu = document.getElementById('userMenu');
                if (userMenu && !userMenu.classList.contains('hidden')) {
                    userMenu.classList.add('hidden');
                }
            }
        });
    </script>
</body>
</html>