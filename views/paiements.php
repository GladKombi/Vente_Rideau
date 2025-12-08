<?php
# Se connecter à la BD
include '../connexion/connexion.php';

# Récupérer les contrats actifs (non expirés)
$sql_contrats = "SELECT c.*, cat.designation as categorie_nom 
                 FROM contrats c 
                 LEFT JOIN categorie cat ON c.categorie = cat.id 
                 WHERE c.statut = 0 
                 AND (c.date_fin IS NULL OR c.date_fin >= CURDATE())
                 ORDER BY c.created_at DESC";
$stmt_contrats = $pdo->query($sql_contrats);
$contrats = $stmt_contrats->fetchAll(PDO::FETCH_ASSOC);

# Récupérer les membres/locataires
$sql_membres = "SELECT id, nom, prenom FROM membres WHERE statut = 0 ORDER BY nom, prenom";
$stmt_membres = $pdo->query($sql_membres);
$membres = $stmt_membres->fetchAll(PDO::FETCH_ASSOC);

# Récupérer les affectations actives
$sql_affectations = "SELECT a.*, 
                     m.nom as nom_membre, m.postnom as postnom_membre, m.prenom as prenom_membre,
                     b.numero as boutique_numero
                     FROM affectation a 
                     LEFT JOIN membres m ON a.membre = m.id 
                     LEFT JOIN boutiques b ON a.boutique = b.id 
                     WHERE a.statut = 0 
                     ORDER BY a.created_at DESC";
$stmt_affectations = $pdo->query($sql_affectations);
$affectations = $stmt_affectations->fetchAll(PDO::FETCH_ASSOC);

# Récupérer les paiements avec les informations des contrats et membres
$sql_paiements = "SELECT p.*, c.date_debut, c.date_fin, c.loyer_mensuel, m.nom, m.prenom 
                  FROM paiements p 
                  LEFT JOIN contrats c ON p.contrat_id = c.id 
                  LEFT JOIN affectation a ON p.affectation = a.id 
                  LEFT JOIN membres m ON a.membre = m.id 
                  WHERE p.statut = 0 
                  ORDER BY p.created_at DESC";
$stmt_paiements = $pdo->query($sql_paiements);
$paiements = $stmt_paiements->fetchAll(PDO::FETCH_ASSOC);

# Calculer les statistiques
$totalPerçu = array_sum(array_column($paiements, 'montant'));
$totalAttendu = array_sum(array_column($paiements, 'loyer_mensuel'));
$soldeRestant = $totalAttendu - $totalPerçu;

# Dates par défaut
$date_automatique = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - GestionLoyer</title>
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
        .badge-paye {
            background-color: #10B981;
            color: white;
        }
        .badge-retard {
            background-color: #EF4444;
            color: white;
        }
        .badge-attente {
            background-color: #F59E0B;
            color: white;
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
                            <a href="paiements.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
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
                    <h1 class="text-2xl font-bold text-gray-800">Gestion des Paiements</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Paiements</span>
                    </div>
                </div>
                <button id="btnAjouter" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center shadow-lg hover-lift transition-all duration-300">
                    <i class="fas fa-plus mr-2"></i> Nouveau paiement
                </button>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-card text-white rounded-xl p-5 hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Total paiements</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo count($paiements); ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-file-invoice-dollar text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Montant total perçu</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo number_format($totalPerçu, 2, ',', ' '); ?> $
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-money-bill-wave text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Montant total attendu</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo number_format($totalAttendu, 2, ',', ' '); ?> $
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-chart-line text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Solde restant</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php echo number_format($soldeRestant, 2, ',', ' '); ?> $
                            </p>
                            <p class="text-xs mt-1 <?php echo $soldeRestant > 0 ? 'text-red-200' : 'text-green-200'; ?>">
                                <?php echo $soldeRestant > 0 ? 'En attente' : 'Excédent'; ?>
                            </p>
                        </div>
                        <div class="bg-white bg-opacity-20 p-3 rounded-xl">
                            <i class="fas fa-balance-scale text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Barre de recherche et filtres -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6 hover-lift">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Rechercher un paiement..." 
                                   class="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <select id="etatFilter" class="px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="">Tous les états</option>
                            <option value="validé">Validé</option>
                            <option value="en_attente">En attente</option>
                            <option value="rejeté">Rejeté</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tableau des paiements -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden hover-lift">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date paiement</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Locataire</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Contrat</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Montant payé</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Montant restant</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">État</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Notes</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="paiementTableBody" class="bg-white divide-y divide-gray-200">
                            <?php foreach ($paiements as $paiement): 
                                $montantRestant = $paiement['loyer_mensuel'] - $paiement['montant'];
                                $pourcentagePaye = ($paiement['montant'] / $paiement['loyer_mensuel']) * 100;
                            ?>
                            <tr class="hover:bg-purple-50 transition-colors duration-200 paiement-row" 
                                data-etat="<?php echo $paiement['etat']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $paiement['nom'] ? htmlspecialchars($paiement['prenom'] . ' ' . $paiement['nom']) : 'Non assigné'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        Contrat du <?php echo date('d/m/Y', strtotime($paiement['date_debut'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Loyer: <?php echo number_format($paiement['loyer_mensuel'], 2, ',', ' '); ?> $
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo number_format($paiement['montant'], 2, ',', ' '); ?> $
                                    </div>
                                    <!-- Barre de progression -->
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                        <div class="bg-green-600 h-2 rounded-full" 
                                             style="width: <?php echo min($pourcentagePaye, 100); ?>%">
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php echo number_format($pourcentagePaye, 1); ?>% payé
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($montantRestant > 0): ?>
                                        <div class="text-sm font-medium text-red-600">
                                            <?php echo number_format($montantRestant, 2, ',', ' '); ?> $
                                        </div>
                                        <div class="text-xs text-red-500">
                                            Reste à payer
                                        </div>
                                    <?php elseif ($montantRestant < 0): ?>
                                        <div class="text-sm font-medium text-green-600">
                                            <?php echo number_format(abs($montantRestant), 2, ',', ' '); ?> $
                                        </div>
                                        <div class="text-xs text-green-500">
                                            Excédent
                                        </div>
                                    <?php else: ?>
                                        <div class="text-sm font-medium text-green-600">
                                            0,00 $
                                        </div>
                                        <div class="text-xs text-green-500">
                                            Payé intégralement
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                            switch($paiement['etat']) {
                                                case 'validé': echo 'badge-paye'; break;
                                                case 'en_attente': echo 'badge-attente'; break;
                                                case 'rejeté': echo 'badge-retard'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?>">
                                        <?php 
                                            switch($paiement['etat']) {
                                                case 'validé': echo 'Validé'; break;
                                                case 'en_attente': echo 'En attente'; break;
                                                case 'rejeté': echo 'Rejeté'; break;
                                                default: echo $paiement['etat'];
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate">
                                        <?php echo $paiement['notes'] ? htmlspecialchars($paiement['notes']) : 'Aucune note'; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-purple-600 hover:text-purple-800 mr-3 edit-paiement transition-colors duration-200" 
                                            data-id="<?php echo $paiement['id']; ?>"
                                            data-date-paiement="<?php echo $paiement['date_paiement']; ?>"
                                            data-contrat-id="<?php echo $paiement['contrat_id']; ?>"
                                            data-affectation="<?php echo $paiement['affectation']; ?>"
                                            data-montant="<?php echo $paiement['montant']; ?>"
                                            data-etat="<?php echo $paiement['etat']; ?>"
                                            data-notes="<?php echo htmlspecialchars($paiement['notes']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($paiement['statut'] == 0): ?>
                                    <button class="text-red-600 hover:text-red-800 delete-paiement transition-colors duration-200" 
                                            data-id="<?php echo $paiement['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="text-green-600 hover:text-green-800 reactiver-paiement transition-colors duration-200" 
                                            data-id="<?php echo $paiement['id']; ?>">
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
                    <h3 class="text-lg font-medium text-gray-700">Aucun paiement trouvé</h3>
                    <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
                </div>
            </div>

            <!-- Modal pour ajouter/modifier un paiement -->
            <div id="paiementModal" class="fixed inset-0 modal-backdrop overflow-y-auto h-full w-full hidden z-50">
                <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-2xl rounded-2xl bg-white">
                    <div class="mt-3">
                        <!-- En-tête du modal -->
                        <div class="flex justify-between items-center pb-3 border-b">
                            <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Nouveau paiement</h3>
                            <button id="closeModal" class="text-gray-400 hover:text-gray-500 transition-colors duration-200">
                                <i class="fas fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Formulaire -->
                        <form id="paiementForm" action="../models/traitement/paiement-post.php" method="POST">
                            <input type="hidden" id="action" name="action" value="ajouter">
                            <input type="hidden" id="paiementId" name="id" value="">
                            <input type="hidden" id="date_paiement" name="date_paiement" value="<?php echo $date_automatique; ?>">
                            
                            <div class="grid grid-cols-1 gap-6 mt-4">
                                <!-- Informations sur la date de paiement (automatique) -->
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                        <span class="text-sm font-medium text-blue-800">
                                            Date de paiement automatique : <?php echo date('d/m/Y'); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="contrat_id" class="block text-sm font-medium text-gray-700">Contrat *</label>
                                        <select id="contrat_id" name="contrat_id" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                            <option value="">Sélectionner un contrat</option>
                                            <?php foreach ($contrats as $contrat): ?>
                                                <option value="<?php echo $contrat['id']; ?>" 
                                                        data-loyer="<?php echo $contrat['loyer_mensuel']; ?>"
                                                        data-date-debut="<?php echo $contrat['date_debut']; ?>"
                                                        data-date-fin="<?php echo $contrat['date_fin']; ?>">
                                                    Contrat #<?php echo $contrat['id']; ?> - <?php echo number_format($contrat['loyer_mensuel'], 2, ',', ' '); ?> $
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p id="contrat_id_message" class="text-red-500 text-xs mt-1 hidden"></p>
                                    </div>

                                    <div>
                                        <label for="affectation" class="block text-sm font-medium text-gray-700">Affectation *</label>
                                        <select id="affectation" name="affectation" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                            <option value="">Sélectionner une affectation</option>
                                            <?php foreach ($affectations as $affectation): ?>
                                                <option value="<?php echo $affectation['id']; ?>">
                                                    Affectation #<?php echo $affectation['id']; ?> - 
                                                    <?php echo htmlspecialchars($affectation['nom_membre'] . ' ' . $affectation['postnom_membre'] . ' ' . $affectation['prenom_membre']); ?> -
                                                    Boutique #<?php echo $affectation['boutique_numero']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <p id="affectation_message" class="text-red-500 text-xs mt-1 hidden"></p>
                                    </div>
                                </div>

                                <!-- Dates du contrat -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Date de début</label>
                                        <div class="mt-1 px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700">
                                            <span id="contrat_date_debut">-</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Date de fin</label>
                                        <div class="mt-1 px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700">
                                            <span id="contrat_date_fin">-</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="montant" class="block text-sm font-medium text-gray-700">Montant ($) *</label>
                                        <input type="number" id="montant" name="montant" step="0.01" min="0" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                            placeholder="0.00">
                                        <div id="loyer_reference" class="text-xs text-gray-500 mt-1 hidden">
                                            Loyer mensuel: <span id="loyer_mensuel_value"></span> $
                                        </div>
                                        <p id="montant_message" class="text-red-500 text-xs mt-1 hidden"></p>
                                    </div>

                                    <div>
                                        <label for="etat" class="block text-sm font-medium text-gray-700">État *</label>
                                        <select id="etat" name="etat" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                                            <option value="en_attente">En attente</option>
                                            <option value="validé">Validé</option>
                                            <option value="rejeté">Rejeté</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea id="notes" name="notes" rows="3"
                                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                        placeholder="Notes supplémentaires sur le paiement..."></textarea>
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
                                Êtes-vous sûr de vouloir désactiver ce paiement ?
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="deleteForm" action="../models/traitement/paiement-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id" id="deletePaiementId" value="">
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
                                Êtes-vous sûr de vouloir réactiver ce paiement ?
                            </p>
                        </div>
                        <div class="flex justify-center space-x-3 mt-4">
                            <button id="cancelReactivate" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                                Annuler
                            </button>
                            <form id="reactivateForm" action="../models/traitement/paiement-post.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="reactiver">
                                <input type="hidden" name="id" id="reactivatePaiementId" value="">
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
        const paiementModal = document.getElementById('paiementModal');
        const deleteModal = document.getElementById('deleteModal');
        const reactivateModal = document.getElementById('reactivateModal');
        const paiementForm = document.getElementById('paiementForm');
        const modalTitle = document.getElementById('modalTitle');
        const btnAjouter = document.getElementById('btnAjouter');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelDelete = document.getElementById('cancelDelete');
        const cancelReactivate = document.getElementById('cancelReactivate');
        const actionInput = document.getElementById('action');
        const paiementIdInput = document.getElementById('paiementId');
        const deletePaiementIdInput = document.getElementById('deletePaiementId');
        const reactivatePaiementIdInput = document.getElementById('reactivatePaiementId');
        const searchInput = document.getElementById('searchInput');
        const etatFilter = document.getElementById('etatFilter');
        const paiementTableBody = document.getElementById('paiementTableBody');
        const noResults = document.getElementById('noResults');
        const contratSelect = document.getElementById('contrat_id');
        const montantInput = document.getElementById('montant');
        const loyerReference = document.getElementById('loyer_reference');
        const loyerMensuelValue = document.getElementById('loyer_mensuel_value');
        const contratDateDebut = document.getElementById('contrat_date_debut');
        const contratDateFin = document.getElementById('contrat_date_fin');

        // Variables pour la gestion des actions
        let currentPaiementId = null;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            setupSearchAndFilters();
            setupContratLoyer();
        });

        // Configurer les écouteurs d'événements
        function setupEventListeners() {
            // Bouton pour ajouter un paiement
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
            document.querySelectorAll('.edit-paiement').forEach(button => {
                button.addEventListener('click', function() {
                    const paiementId = this.getAttribute('data-id');
                    const datePaiement = this.getAttribute('data-date-paiement');
                    const contratId = this.getAttribute('data-contrat-id');
                    const affectation = this.getAttribute('data-affectation');
                    const montant = this.getAttribute('data-montant');
                    const etat = this.getAttribute('data-etat');
                    const notes = this.getAttribute('data-notes');
                    
                    editPaiement(paiementId, datePaiement, contratId, affectation, montant, etat, notes);
                });
            });
            
            // Boutons de suppression
            document.querySelectorAll('.delete-paiement').forEach(button => {
                button.addEventListener('click', function() {
                    const paiementId = this.getAttribute('data-id');
                    showDeleteModal(paiementId);
                });
            });

            // Boutons de réactivation
            document.querySelectorAll('.reactiver-paiement').forEach(button => {
                button.addEventListener('click', function() {
                    const paiementId = this.getAttribute('data-id');
                    showReactivateModal(paiementId);
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
            paiementForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const contratId = document.getElementById('contrat_id').value;
                const affectation = document.getElementById('affectation').value;
                const montant = document.getElementById('montant').value;
                const etat = document.getElementById('etat').value;
                
                let errors = [];
                
                // Validation des champs obligatoires
                if (!contratId) {
                    errors.push('Le contrat est obligatoire');
                }
                
                if (!affectation) {
                    errors.push('L\'affectation est obligatoire');
                }
                
                if (!montant || montant <= 0) {
                    errors.push('Le montant doit être un nombre positif');
                }
                
                if (!etat) {
                    errors.push('L\'état est obligatoire');
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

            // Validation en temps réel
            montantInput.addEventListener('input', function() {
                validerMontantEnTempsReel();
            });
        }

        // Configurer la gestion du loyer par contrat
        function setupContratLoyer() {
            contratSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const loyerMensuel = selectedOption.getAttribute('data-loyer');
                const dateDebut = selectedOption.getAttribute('data-date-debut');
                const dateFin = selectedOption.getAttribute('data-date-fin');
                
                // Mettre à jour les dates du contrat
                if (dateDebut) {
                    contratDateDebut.textContent = formatDate(dateDebut);
                } else {
                    contratDateDebut.textContent = '-';
                }
                
                if (dateFin) {
                    contratDateFin.textContent = formatDate(dateFin);
                } else {
                    contratDateFin.textContent = 'Indéterminée';
                }
                
                // Mettre à jour le loyer
                if (loyerMensuel) {
                    loyerMensuelValue.textContent = parseFloat(loyerMensuel).toFixed(2);
                    loyerReference.classList.remove('hidden');
                    
                    // Pré-remplir le montant avec le loyer mensuel
                    if (!montantInput.value) {
                        montantInput.value = parseFloat(loyerMensuel).toFixed(2);
                    }
                } else {
                    loyerReference.classList.add('hidden');
                }
            });
        }

        // Fonction pour formater la date
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        // Configurer la recherche et les filtres
        function setupSearchAndFilters() {
            searchInput.addEventListener('input', filterPaiements);
            etatFilter.addEventListener('change', filterPaiements);
        }

        // Filtrer les paiements
        function filterPaiements() {
            const searchTerm = searchInput.value.toLowerCase();
            const etatFilterValue = etatFilter.value;
            
            const rows = document.querySelectorAll('.paiement-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const etat = row.getAttribute('data-etat');
                const rowText = row.textContent.toLowerCase();
                const matchesSearch = rowText.includes(searchTerm);
                const matchesEtat = !etatFilterValue || etat === etatFilterValue;
                
                if (matchesSearch && matchesEtat) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Afficher/masquer le message "Aucun résultat"
            if (visibleCount === 0) {
                noResults.classList.remove('hidden');
                paiementTableBody.style.display = 'none';
            } else {
                noResults.classList.add('hidden');
                paiementTableBody.style.display = '';
            }
        }

        // Validation en temps réel du montant
        function validerMontantEnTempsReel() {
            const montant = parseFloat(montantInput.value);
            const saveBtn = document.getElementById('saveBtn');
            
            cacherMessageChamp('montant');
            
            if (montant && montant <= 0) {
                afficherMessageChamp('montant', 'Le montant doit être supérieur à 0');
                saveBtn.disabled = true;
            } else {
                saveBtn.disabled = false;
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
            modalTitle.textContent = 'Nouveau paiement';
            actionInput.value = 'ajouter';
            paiementForm.reset();
            paiementIdInput.value = '';
            currentPaiementId = null;
            
            // Réinitialiser les messages d'erreur
            cacherMessageChamp('contrat_id');
            cacherMessageChamp('affectation');
            cacherMessageChamp('montant');
            
            // Réinitialiser les dates du contrat
            contratDateDebut.textContent = '-';
            contratDateFin.textContent = '-';
            
            // Réactiver le bouton
            document.getElementById('saveBtn').disabled = false;
            
            paiementModal.classList.remove('hidden');
        }

        // Masquer le modal d'ajout
        function hideModal() {
            paiementModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de suppression
        function showDeleteModal(paiementId) {
            deletePaiementIdInput.value = paiementId;
            deleteModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de suppression
        function hideDeleteModal() {
            deleteModal.classList.add('hidden');
        }

        // Afficher le modal de confirmation de réactivation
        function showReactivateModal(paiementId) {
            reactivatePaiementIdInput.value = paiementId;
            reactivateModal.classList.remove('hidden');
        }

        // Masquer le modal de confirmation de réactivation
        function hideReactivateModal() {
            reactivateModal.classList.add('hidden');
        }

        // Éditer un paiement
        function editPaiement(paiementId, datePaiement, contratId, affectation, montant, etat, notes) {
            const paiement = {
                id: paiementId,
                datePaiement: datePaiement,
                contratId: contratId,
                affectation: affectation,
                montant: montant,
                etat: etat,
                notes: notes
            };
            showEditModal(paiement);
        }

        // Afficher le modal d'édition
        function showEditModal(paiement) {
            modalTitle.textContent = 'Modifier le paiement';
            actionInput.value = 'modifier';
            paiementIdInput.value = paiement.id;
            document.getElementById('date_paiement').value = paiement.datePaiement;
            document.getElementById('contrat_id').value = paiement.contratId;
            document.getElementById('affectation').value = paiement.affectation;
            document.getElementById('montant').value = paiement.montant;
            document.getElementById('etat').value = paiement.etat;
            document.getElementById('notes').value = paiement.notes;
            
            // Mettre à jour la référence du loyer et les dates
            const selectedOption = contratSelect.options[contratSelect.selectedIndex];
            if (selectedOption) {
                const loyerMensuel = selectedOption.getAttribute('data-loyer');
                const dateDebut = selectedOption.getAttribute('data-date-debut');
                const dateFin = selectedOption.getAttribute('data-date-fin');
                
                if (loyerMensuel) {
                    loyerMensuelValue.textContent = parseFloat(loyerMensuel).toFixed(2);
                    loyerReference.classList.remove('hidden');
                }
                
                if (dateDebut) {
                    contratDateDebut.textContent = formatDate(dateDebut);
                }
                
                if (dateFin) {
                    contratDateFin.textContent = formatDate(dateFin);
                }
            }
            
            // Réinitialiser les messages d'erreur
            cacherMessageChamp('contrat_id');
            cacherMessageChamp('affectation');
            cacherMessageChamp('montant');
            
            // Réactiver le bouton
            document.getElementById('saveBtn').disabled = false;
            
            currentPaiementId = paiement.id;
            
            paiementModal.classList.remove('hidden');
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