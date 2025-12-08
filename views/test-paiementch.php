<?php
include '../connexion/connexion.php';

# Vérification d'authentification
// if (!isset($_SESSION['user_id'])) {
//     header('Location: login.php');
//     exit;
// }

# Récupérer les paiements avec les informations liées
$sql_paiements = "
    SELECT 
        pc.id, 
        pc.date, 
        pc.montant, 
        pc.statut,
        pc.created_at,
        pc.aligement_id,
        a.affectation_id,
        a.charge_id,
        c.designation as charge_designation,
        m.nom as locataire_nom,
        m.prenom as locataire_prenom,
        m.postnom as locataire_postnom,
        b.numero as boutique_numero
    FROM paiments_Charge pc
    LEFT JOIN aligements a ON pc.aligement_id = a.id
    LEFT JOIN charges c ON a.charge_id = c.id
    LEFT JOIN affectation aff ON a.affectation_id = aff.id
    LEFT JOIN membres m ON aff.membre = m.id
    LEFT JOIN boutiques b ON aff.boutique = b.id
    ORDER BY pc.created_at DESC
";

try {
    $stmt_paiements = $pdo->query($sql_paiements);
    $paiements = $stmt_paiements->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des paiements: " . $e->getMessage());
    $paiements = [];
}

# Récupérer les alignements pour les formulaires (uniquement ceux avec affectation pour les paiements spécifiques)
$sql_alignements = "
    SELECT 
        a.id,
        a.montant as montant_alignement,
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
    WHERE a.statut = 0 
    AND a.affectation_id IS NOT NULL
    ORDER BY m.nom, b.numero, c.designation
";

try {
    $stmt_alignements = $pdo->query($sql_alignements);
    $alignements = $stmt_alignements->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des alignements: " . $e->getMessage());
    $alignements = [];
}

# Préparer les données pour JavaScript
$alignements_js = [];
foreach ($alignements as $alignement) {
    $alignements_js[] = [
        'id' => $alignement['id'],
        'montant_alignement' => $alignement['montant_alignement'],
        'charge_designation' => $alignement['charge_designation'],
        'locataire_nom' => $alignement['locataire_nom'],
        'locataire_prenom' => $alignement['locataire_prenom'],
        'locataire_postnom' => $alignement['locataire_postnom'],
        'boutique_numero' => $alignement['boutique_numero'],
        'affectation_id' => $alignement['affectation_id']
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiements des Charges - GestionLoyer</title>
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
        .progress-bar {
            height: 8px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                font-size: 12px !important;
            }
            .receipt {
                width: 80mm !important;
                padding: 5mm !important;
                font-family: 'Courier New', monospace !important;
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
                                    Paiements Loyer
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
                            <a href="alignements.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-euro-sign mr-3"></i>
                                    Alignements
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="paiements_charges.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
                                <div class="flex items-center">
                                    <i class="fas fa-credit-card mr-3"></i>
                                    Paiements Charges
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
                    <h1 class="text-2xl font-bold text-gray-800">Paiements des Charges</h1>
                    <div class="flex items-center text-sm text-gray-600 mt-1">
                        <span class="text-purple-600">Tableau de bord</span>
                        <i class="fas fa-chevron-right mx-2 text-xs text-purple-400"></i>
                        <span class="font-medium text-gray-700">Paiements Charges</span>
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
                            <i class="fas fa-credit-card text-lg"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl p-5 shadow-lg hover-lift">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm font-medium text-white text-opacity-90">Paiements actifs</p>
                            <p class="text-2xl font-bold mt-1">
                                <?php 
                                    $actives = array_filter($paiements, function($paiement) {
                                        return $paiement['statut'] == 0;
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
                            <p class="text-sm font-medium text-white text-opacity-90">Montant total</p>
                            <p class="text-lg font-bold mt-1">
                                <?php 
                                    if (!empty($paiements)) {
                                        $total = array_sum(array_column($paiements, 'montant'));
                                        echo number_format($total, 2, ',', ' ') . ' $';
                                    } else {
                                        echo '0,00 $';
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
                            <p class="text-sm font-medium text-white text-opacity-90">Dernier paiement</p>
                            <p class="text-lg font-bold mt-1">
                                <?php 
                                    if (!empty($paiements)) {
                                        $dernier = $paiements[0];
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
                            <input type="text" id="searchInput" placeholder="Rechercher un paiement..." 
                                   class="w-full px-4 py-3 pl-12 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <label for="filterStatut" class="text-sm font-medium text-gray-700">Filtrer par statut:</label>
                        <select id="filterStatut" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200">
                            <option value="all">Tous</option>
                            <option value="actif">Actifs</option>
                            <option value="inactif">Inactifs</option>
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
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">#ID</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Charge / Montant Aligné</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Locataire / Boutique</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Montant Payé</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Solde</th>
                                <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="paiementTableBody" class="bg-white divide-y divide-gray-200">
                            <?php if (empty($paiements)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-500">
                                            <i class="fas fa-credit-card text-4xl mb-4 text-gray-300"></i>
                                            <h3 class="text-lg font-medium text-gray-700">Aucun paiement enregistré</h3>
                                            <p class="text-gray-500 mt-2">Commencez par enregistrer votre premier paiement de charge</p>
                                            <button id="btnAjouterEmpty" class="btn-gradient text-white px-4 py-2 rounded-lg flex items-center mt-4 shadow-lg hover-lift transition-all duration-300">
                                                <i class="fas fa-plus mr-2"></i> Nouveau paiement
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paiements as $paiement): 
                                    $montant_alignement = $paiement['montant_alignement'] ?? 0;
                                    $montant_paye = $paiement['montant'] ?? 0;
                                    $solde = $montant_alignement - $montant_paye;
                                    $pourcentage = $montant_alignement > 0 ? ($montant_paye / $montant_alignement) * 100 : 0;
                                    $nom_complet = trim(($paiement['locataire_nom'] ?? '') . ' ' . ($paiement['locataire_postnom'] ?? '') . ' ' . ($paiement['locataire_prenom'] ?? ''));
                                ?>
                                <tr class="hover:bg-purple-50 transition-colors duration-200 paiement-row" data-statut="<?php echo $paiement['statut']; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            #<?php echo $paiement['id']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('d/m/Y', strtotime($paiement['date'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="space-y-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <i class="fas fa-money-bill-wave text-purple-500 mr-1"></i>
                                                <?php echo htmlspecialchars($paiement['charge_designation'] ?? 'N/A'); ?>
                                            </div>
                                            <div class="text-xs text-blue-600 font-semibold">
                                                <i class="fas fa-euro-sign text-xs mr-1"></i>
                                                Montant aligné: <?php echo number_format($montant_alignement, 2, ',', ' '); ?> $
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="space-y-1">
                                            <div class="text-sm font-medium text-gray-900">
                                                <i class="fas fa-user text-green-500 mr-1"></i>
                                                <?php echo htmlspecialchars($nom_complet ?: 'N/A'); ?>
                                            </div>
                                            <div class="text-xs text-blue-600">
                                                <i class="fas fa-store text-xs mr-1"></i>
                                                Boutique <?php echo htmlspecialchars($paiement['boutique_numero'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold <?php echo $montant_paye >= $montant_alignement ? 'text-green-600' : 'text-orange-600'; ?>">
                                            <?php echo number_format($montant_paye, 2, ',', ' '); ?> $
                                        </div>
                                        <?php if ($montant_alignement > 0): ?>
                                        <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                            <div class="progress-bar rounded-full h-2" style="width: <?php echo min($pourcentage, 100); ?>%"></div>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php echo number_format($pourcentage, 1); ?>%
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-bold <?php echo $solde <= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo number_format(abs($solde), 2, ',', ' '); ?> $
                                            <?php if ($solde > 0): ?>
                                                <span class="text-xs text-red-500 block">À payer</span>
                                            <?php elseif ($solde < 0): ?>
                                                <span class="text-xs text-green-500 block">Excédent</span>
                                            <?php else: ?>
                                                <span class="text-xs text-green-500 block">Soldé</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button class="text-blue-600 hover:text-blue-800 mr-3 print-receipt transition-colors duration-200" 
                                                data-id="<?php echo $paiement['id']; ?>"
                                                data-date="<?php echo date('d/m/Y', strtotime($paiement['date'])); ?>"
                                                data-charge="<?php echo htmlspecialchars($paiement['charge_designation'] ?? ''); ?>"
                                                data-locataire="<?php echo htmlspecialchars($nom_complet); ?>"
                                                data-boutique="<?php echo htmlspecialchars($paiement['boutique_numero'] ?? ''); ?>"
                                                data-montant="<?php echo number_format($montant_paye, 2, ',', ' '); ?>">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="text-purple-600 hover:text-purple-800 mr-3 edit-paiement transition-colors duration-200" 
                                                data-id="<?php echo $paiement['id']; ?>"
                                                data-date="<?php echo $paiement['date']; ?>"
                                                data-montant="<?php echo $paiement['montant']; ?>"
                                                data-alignement-id="<?php echo $paiement['aligement_id'] ?? ''; ?>">
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
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noResults" class="hidden p-8 text-center">
                    <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-700">Aucun paiement trouvé</h3>
                    <p class="text-gray-500 mt-2">Essayez de modifier vos critères de recherche</p>
                </div>
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
                    <form id="paiementForm" action="../models/traitement/paiement-charge-post.php" method="POST">
                        <input type="hidden" id="action" name="action" value="ajouter">
                        <input type="hidden" id="paiementId" name="id" value="">
                        
                        <div class="grid grid-cols-1 gap-6 mt-4">
                            <!-- Recherche d'alignement -->
                            <div>
                                <label for="searchAlignement" class="block text-sm font-medium text-gray-700">Rechercher un alignement *</label>
                                <div class="relative">
                                    <input type="text" id="searchAlignement" 
                                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                           placeholder="Tapez le nom du locataire, la boutique ou la charge...">
                                    <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                                    <div id="alignementResults" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                        <!-- Les résultats apparaîtront ici -->
                                    </div>
                                </div>
                                <input type="hidden" id="alignement_id" name="alignement_id">
                                <div id="selectedAlignement" class="hidden mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <!-- L'alignement sélectionné apparaîtra ici -->
                                </div>
                                <div id="alignementInfo" class="hidden mt-3 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                                    <!-- Informations sur l'alignement sélectionné -->
                                </div>
                            </div>

                            <!-- Date du paiement -->
                            <div>
                                <label for="date" class="block text-sm font-medium text-gray-700">Date du paiement *</label>
                                <input type="date" id="date" name="date" required
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <!-- Montant du paiement -->
                            <div>
                                <label for="montant" class="block text-sm font-medium text-gray-700">Montant ($) *</label>
                                <input type="number" id="montant" name="montant" required step="0.01" min="0.01"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                                    placeholder="0.00">
                                <div id="montantInfo" class="mt-2 text-sm text-gray-500 hidden">
                                    <span id="montantAlignement">0.00 $</span> dû - <span id="montantReste" class="font-semibold">0.00 $</span> restant
                                </div>
                            </div>

                            <!-- Information -->
                            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-green-500 mr-2 mt-0.5"></i>
                                    <p class="text-sm text-green-700">
                                        <strong>Important :</strong> Un paiement est lié à un alignement spécifique. Vous ne pouvez pas créer de paiement sans alignement.
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
                            Êtes-vous sûr de vouloir désactiver ce paiement ?
                        </p>
                    </div>
                    <div class="flex justify-center space-x-3 mt-4">
                        <button id="cancelDelete" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                            Annuler
                        </button>
                        <form id="deleteForm" action="../models/traitement/paiement-charge-post.php" method="POST" class="inline">
                            <input type="hidden" name="action" value="supprimer">
                            <input type="hidden" name="id" id="deletePaiementId" value="">
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
                            Êtes-vous sûr de vouloir réactiver ce paiement ?
                        </p>
                    </div>
                    <div class="flex justify-center space-x-3 mt-4">
                        <button id="cancelReactivate" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors duration-200">
                            Annuler
                        </button>
                        <form id="reactivateForm" action="../models/traitement/paiement-charge-post.php" method="POST" class="inline">
                            <input type="hidden" name="action" value="reactiver">
                            <input type="hidden" name="id" id="reactivatePaiementId" value="">
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

    // Données des alignements pour l'autocomplétion
    const alignementsData = <?php echo json_encode($alignements_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    // Éléments du DOM
    const paiementModal = document.getElementById('paiementModal');
    const deleteModal = document.getElementById('deleteModal');
    const reactivateModal = document.getElementById('reactivateModal');
    const paiementForm = document.getElementById('paiementForm');
    const modalTitle = document.getElementById('modalTitle');
    const btnAjouter = document.getElementById('btnAjouter');
    const btnAjouterEmpty = document.getElementById('btnAjouterEmpty');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const cancelDelete = document.getElementById('cancelDelete');
    const cancelReactivate = document.getElementById('cancelReactivate');
    const searchAlignement = document.getElementById('searchAlignement');
    const alignementResults = document.getElementById('alignementResults');
    const alignementIdInput = document.getElementById('alignement_id');
    const selectedAlignement = document.getElementById('selectedAlignement');
    const alignementInfo = document.getElementById('alignementInfo');
    const montantInput = document.getElementById('montant');
    const montantInfo = document.getElementById('montantInfo');
    const montantAlignementSpan = document.getElementById('montantAlignement');
    const montantResteSpan = document.getElementById('montantReste');
    const deletePaiementIdInput = document.getElementById('deletePaiementId');
    const reactivatePaiementIdInput = document.getElementById('reactivatePaiementId');
    const filterStatut = document.getElementById('filterStatut');

    // Variables globales
    let selectedAlignementData = null;
    let totalPaiementsPourAlignement = 0;

    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        setupEventListeners();
        setupSearch();
        setupAlignementSearch();
        setupFilter();
        setupPrintButtons();
    });

    // Configurer les écouteurs d'événements
    function setupEventListeners() {
        // Boutons pour ajouter un paiement
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
        
        // Boutons d'édition
        document.querySelectorAll('.edit-paiement').forEach(button => {
            button.addEventListener('click', function() {
                const paiementId = this.getAttribute('data-id');
                const date = this.getAttribute('data-date');
                const montant = this.getAttribute('data-montant');
                const alignementId = this.getAttribute('data-alignement-id');
                
                editPaiement(paiementId, date, montant, alignementId);
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
        
        // Validation du formulaire
        paiementForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const alignementId = alignementIdInput.value;
            const montant = montantInput.value;
            const date = document.getElementById('date').value;
            
            let errors = [];
            
            // Validation des champs obligatoires
            if (!alignementId) {
                errors.push('Veuillez sélectionner un alignement');
            }
            
            if (!date) {
                errors.push('La date est obligatoire');
            }
            
            if (!montant || montant <= 0) {
                errors.push('Le montant doit être supérieur à 0');
            }
            
            // Vérifier si le montant dépasse le reste dû
            if (selectedAlignementData) {
                const montantAlignement = parseFloat(selectedAlignementData.montant_alignement);
                const montantPaye = parseFloat(montant);
                const reste = montantAlignement - totalPaiementsPourAlignement;
                
                if (montantPaye > reste) {
                    errors.push(`Le montant dépasse le reste dû (${reste.toFixed(2)} $)`);
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

        // Mettre à jour les infos de montant quand on change le montant
        montantInput.addEventListener('input', updateMontantInfo);
    }

    // Configurer les boutons d'impression
    function setupPrintButtons() {
        document.querySelectorAll('.print-receipt').forEach(button => {
            button.addEventListener('click', function() {
                const paiementId = this.getAttribute('data-id');
                const date = this.getAttribute('data-date');
                const charge = this.getAttribute('data-charge');
                const locataire = this.getAttribute('data-locataire');
                const boutique = this.getAttribute('data-boutique');
                const montant = this.getAttribute('data-montant');
                
                printCompactReceipt(paiementId, date, charge, locataire, boutique, montant);
            });
        });
    }

    // Fonction pour imprimer un reçu compact (pour petite imprimante)
    function printCompactReceipt(paiementId, date, charge, locataire, boutique, montant) {
        // Générer un contenu HTML compact pour le reçu
        const receiptContent = `
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Reçu de paiement #${paiementId}</title>
                <style>
                    body {
                        font-family: 'Courier New', monospace;
                        font-size: 12px;
                        width: 80mm;
                        margin: 0;
                        padding: 2mm;
                        line-height: 1.2;
                        color: #000;
                        background: white;
                    }
                    .receipt {
                        width: 100%;
                    }
                    .center {
                        text-align: center;
                    }
                    .right {
                        text-align: right;
                    }
                    .bold {
                        font-weight: bold;
                    }
                    .underline {
                        border-bottom: 1px dashed #000;
                        padding-bottom: 1px;
                    }
                    .separator {
                        border-top: 1px dashed #000;
                        margin: 4px 0;
                    }
                    .double-separator {
                        border-top: 2px solid #000;
                        margin: 6px 0;
                    }
                    .mt-1 { margin-top: 2mm; }
                    .mt-2 { margin-top: 4mm; }
                    .mt-3 { margin-top: 6mm; }
                    .mb-1 { margin-bottom: 2mm; }
                    .mb-2 { margin-bottom: 4mm; }
                    .mb-3 { margin-bottom: 6mm; }
                    .pt-1 { padding-top: 2mm; }
                    .pb-1 { padding-bottom: 2mm; }
                    .no-print {
                        display: none;
                    }
                    @media print {
                        body {
                            width: 80mm !important;
                            padding: 2mm !important;
                            font-size: 11px !important;
                        }
                        .no-print {
                            display: none !important;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="receipt">
                    <!-- En-tête -->
                    <div class="center bold mb-1">
                        <div>========================</div>
                        <div>GESTIONLOYER</div>
                        <div>Reçu de paiement</div>
                        <div>========================</div>
                    </div>
                    
                    <!-- Informations de base -->
                    <div class="mb-1">
                        <div><span class="bold">N° Reçu:</span> #${paiementId}</div>
                        <div><span class="bold">Date:</span> ${date}</div>
                        <div><span class="bold">Heure:</span> ${new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'})}</div>
                    </div>
                    
                    <div class="separator"></div>
                    
                    <!-- Détails du paiement -->
                    <div class="mb-1">
                        <div><span class="bold">Charge:</span></div>
                        <div>${charge}</div>
                    </div>
                    
                    <div class="mb-1">
                        <div><span class="bold">Locataire:</span></div>
                        <div>${locataire}</div>
                    </div>
                    
                    <div class="mb-1">
                        <div><span class="bold">Boutique:</span></div>
                        <div>${boutique}</div>
                    </div>
                    
                    <div class="double-separator"></div>
                    
                    <!-- Montant -->
                    <div class="center bold mb-1">
                        <div>MONTANT PAYÉ</div>
                        <div style="font-size: 16px;">${montant} $</div>
                    </div>
                    
                    <div class="double-separator"></div>
                    
                    <!-- Message de remerciement -->
                    <div class="center mt-2 mb-1">
                        <div>Merci pour votre confiance!</div>
                        <div>Ce reçu certifie le paiement</div>
                    </div>
                    
                    <!-- Espace pour signature -->
                    <div class="mt-3">
                        <div class="separator"></div>
                        <div class="center">Signature du locataire</div>
                    </div>
                    
                    <!-- Pied de page -->
                    <div class="center mt-2" style="font-size: 10px;">
                        <div>------------------------</div>
                        <div>Reçu électronique</div>
                        <div>Date d'émission: ${new Date().toLocaleDateString('fr-FR')}</div>
                        <div>------------------------</div>
                    </div>
                    
                    <!-- Boutons pour l'interface utilisateur -->
                    <div class="center no-print mt-3">
                        <button onclick="window.print()" style="padding: 5px 10px; background: #000; color: white; border: none; cursor: pointer; margin-right: 5px;">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                        <button onclick="window.close()" style="padding: 5px 10px; background: #666; color: white; border: none; cursor: pointer;">
                            Fermer
                        </button>
                    </div>
                </div>
                
                <script>
                    // Impression automatique après 500ms
                    setTimeout(function() {
                        window.print();
                    }, 500);
                <\/script>
            </body>
            </html>
        `;
        
        // Ouvrir une nouvelle fenêtre avec le reçu compact
        const printWindow = window.open('', '_blank', 'width=300,height=400');
        printWindow.document.write(receiptContent);
        printWindow.document.close();
    }

    // Configurer la recherche dans le tableau
    function setupSearch() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', filterPaiements);
        }
    }

    // Configurer le filtre par statut
    function setupFilter() {
        if (filterStatut) {
            filterStatut.addEventListener('change', filterPaiements);
        }
    }

    // Configurer la recherche d'alignements
    function setupAlignementSearch() {
        if (!searchAlignement) return;

        searchAlignement.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm.length < 2) {
                alignementResults.classList.add('hidden');
                return;
            }
            
            const results = alignementsData.filter(alignement => {
                const nomComplet = (
                    alignement.locataire_nom + ' ' + 
                    alignement.locataire_postnom + ' ' + 
                    alignement.locataire_prenom
                ).toLowerCase();
                
                const boutique = alignement.boutique_numero.toLowerCase();
                const charge = alignement.charge_designation.toLowerCase();
                
                return nomComplet.includes(searchTerm) || 
                       boutique.includes(searchTerm) ||
                       charge.includes(searchTerm) ||
                       alignement.locataire_nom.toLowerCase().includes(searchTerm) ||
                       alignement.locataire_prenom.toLowerCase().includes(searchTerm) ||
                       alignement.locataire_postnom.toLowerCase().includes(searchTerm);
            });
            
            displayAlignementResults(results);
        });
        
        // Fermer les résultats quand on clique ailleurs
        document.addEventListener('click', function(event) {
            if (!searchAlignement.contains(event.target) && !alignementResults.contains(event.target)) {
                alignementResults.classList.add('hidden');
            }
        });
    }

    // Afficher les résultats de recherche d'alignements
    function displayAlignementResults(results) {
        alignementResults.innerHTML = '';
        
        if (results.length === 0) {
            const noResult = document.createElement('div');
            noResult.className = 'p-3 text-gray-500';
            noResult.textContent = 'Aucun alignement trouvé';
            alignementResults.appendChild(noResult);
            alignementResults.classList.remove('hidden');
            return;
        }
        
        results.forEach(alignement => {
            const nomComplet = `${alignement.locataire_nom} ${alignement.locataire_postnom} ${alignement.locataire_prenom}`;
            const item = document.createElement('div');
            item.className = 'p-3 border-b border-gray-100 search-result-item';
            item.innerHTML = `
                <div class="font-medium text-gray-800">${nomComplet}</div>
                <div class="text-sm text-gray-600">
                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-2">
                        <i class="fas fa-store mr-1"></i>${alignement.boutique_numero}
                    </span>
                    <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded mr-2">
                        <i class="fas fa-money-bill-wave mr-1"></i>${alignement.charge_designation}
                    </span>
                    <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded">
                        <i class="fas fa-euro-sign mr-1"></i>${alignement.montant_alignement} $
                    </span>
                </div>
            `;
            
            item.addEventListener('click', function() {
                selectAlignement(alignement);
            });
            
            alignementResults.appendChild(item);
        });
        
        alignementResults.classList.remove('hidden');
    }

    // Sélectionner un alignement
    async function selectAlignement(alignement) {
        const nomComplet = `${alignement.locataire_nom} ${alignement.locataire_postnom} ${alignement.locataire_prenom}`;
        
        searchAlignement.value = nomComplet;
        alignementIdInput.value = alignement.id;
        selectedAlignementData = alignement;
        
        // Récupérer le total des paiements déjà effectués pour cet alignement
        try {
            const response = await fetch(`../models/traitement/get-total-paiements.php?alignement_id=${alignement.id}`);
            const data = await response.json();
            totalPaiementsPourAlignement = data.total || 0;
        } catch (error) {
            console.error('Erreur lors de la récupération des paiements:', error);
            totalPaiementsPourAlignement = 0;
        }
        
        selectedAlignement.innerHTML = `
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-blue-500 mr-2"></i>
                        <div>
                            <div class="font-medium text-blue-700">${nomComplet}</div>
                            <div class="text-sm text-blue-600 mt-1">
                                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded mr-2">
                                    <i class="fas fa-store mr-1"></i>Boutique ${alignement.boutique_numero}
                                </span>
                                <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded">
                                    <i class="fas fa-money-bill-wave mr-1"></i>${alignement.charge_designation}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="clearAlignementSelection()" class="text-red-500 hover:text-red-700 ml-2">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        alignementInfo.innerHTML = `
            <div class="space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Montant aligné:</span>
                    <span class="text-sm font-bold text-blue-600">${alignement.montant_alignement} $</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Déjà payé:</span>
                    <span class="text-sm font-bold ${totalPaiementsPourAlignement >= alignement.montant_alignement ? 'text-green-600' : 'text-orange-600'}">
                        ${totalPaiementsPourAlignement} $
                    </span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-medium text-gray-700">Reste à payer:</span>
                    <span class="text-sm font-bold ${(alignement.montant_alignement - totalPaiementsPourAlignement) <= 0 ? 'text-green-600' : 'text-red-600'}">
                        ${(alignement.montant_alignement - totalPaiementsPourAlignement).toFixed(2)} $
                    </span>
                </div>
            </div>
        `;
        
        selectedAlignement.classList.remove('hidden');
        alignementInfo.classList.remove('hidden');
        montantInfo.classList.remove('hidden');
        
        montantAlignementSpan.textContent = `${alignement.montant_alignement} $`;
        const reste = alignement.montant_alignement - totalPaiementsPourAlignement;
        montantResteSpan.textContent = `${reste.toFixed(2)} $`;
        
        // Mettre le montant maximum comme placeholder
        montantInput.max = reste > 0 ? reste : alignement.montant_alignement;
        montantInput.placeholder = `Max: ${reste > 0 ? reste.toFixed(2) : alignement.montant_alignement} $`;
        
        alignementResults.classList.add('hidden');
    }

    // Mettre à jour les informations de montant
    function updateMontantInfo() {
        if (!selectedAlignementData) return;
        
        const montantSaisi = parseFloat(montantInput.value) || 0;
        const montantAlignement = parseFloat(selectedAlignementData.montant_alignement);
        const reste = montantAlignement - totalPaiementsPourAlignement;
        
        if (montantSaisi > reste) {
            montantInput.classList.add('border-red-500');
            montantInput.classList.remove('border-gray-300');
            montantResteSpan.className = 'font-semibold text-red-600';
        } else {
            montantInput.classList.remove('border-red-500');
            montantInput.classList.add('border-gray-300');
            montantResteSpan.className = 'font-semibold text-green-600';
        }
    }

    // Effacer la sélection d'alignement (fonction globale)
    window.clearAlignementSelection = function() {
        searchAlignement.value = '';
        alignementIdInput.value = '';
        selectedAlignementData = null;
        selectedAlignement.classList.add('hidden');
        selectedAlignement.innerHTML = '';
        alignementInfo.classList.add('hidden');
        alignementInfo.innerHTML = '';
        montantInfo.classList.add('hidden');
        alignementResults.classList.add('hidden');
        montantInput.max = '';
        montantInput.placeholder = '0.00';
    }

    // Filtrer les paiements dans le tableau
    function filterPaiements() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const filterValue = filterStatut ? filterStatut.value : 'all';
        const rows = document.querySelectorAll('.paiement-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const rowStatut = row.getAttribute('data-statut');
            const matchesSearch = rowText.includes(searchTerm);
            const matchesFilter = filterValue === 'all' || 
                                  (filterValue === 'actif' && rowStatut == 0) || 
                                  (filterValue === 'inactif' && rowStatut == 1);
            
            if (matchesSearch && matchesFilter) {
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
            document.getElementById('paiementTableBody').style.display = 'none';
        } else {
            noResults.classList.add('hidden');
            document.getElementById('paiementTableBody').style.display = '';
        }
    }

    // Afficher le modal d'ajout
    function showModal() {
        modalTitle.textContent = 'Nouveau paiement';
        document.getElementById('action').value = 'ajouter';
        paiementForm.reset();
        document.getElementById('paiementId').value = '';
        document.getElementById('date').value = new Date().toISOString().split('T')[0];
        
        // Réinitialiser les champs
        clearAlignementSelection();
        
        paiementModal.classList.remove('hidden');
    }

    // Masquer le modal
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
    async function editPaiement(paiementId, date, montant, alignementId) {
        modalTitle.textContent = 'Modifier le paiement';
        document.getElementById('action').value = 'modifier';
        document.getElementById('paiementId').value = paiementId;
        document.getElementById('date').value = date;
        document.getElementById('montant').value = montant;
        
        // Trouver l'alignement correspondant
        const alignement = alignementsData.find(a => a.id == alignementId);
        if (alignement) {
            selectAlignement(alignement);
        }
        
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