<?php

// Vérification de l'authentification PDG
// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
//     header('Location: ../login.php');
//     exit;
// }

include '../connexion/connexion.php';

// Récupérer les statistiques globales
$total_boutiques = 0;
$ca_total = 0;
$produits_total = 0;
$alertes_stock = 0;
$dernieres_ventes = [];
$boutiques_ca = [];
$produits_populaires = [];

try {
    // Nombre total de boutiques
    $stmt = $pdo->query("SELECT COUNT(*) as total_boutiques FROM boutiques WHERE statut = 0 AND actif = 1");
    $result = $stmt->fetch();
    $total_boutiques = $result['total_boutiques'] ?? 0;

    // Chiffre d'affaires total
    $stmt = $pdo->query("SELECT SUM(total_ttc) as ca_total FROM ventes WHERE statut IN ('validee', 'payee')");
    $result = $stmt->fetch();
    $ca_total = $result['ca_total'] ?? 0;

    // Produits en stock total
    $stmt = $pdo->query("SELECT COUNT(DISTINCT produit_id) as produits_total FROM stock_boutique WHERE quantite > 0");
    $result = $stmt->fetch();
    $produits_total = $result['produits_total'] ?? 0;

    // Alertes stock bas
    $stmt = $pdo->query("SELECT COUNT(*) as alertes_stock FROM stock_boutique WHERE quantite <= seuil_alerte_min");
    $result = $stmt->fetch();
    $alertes_stock = $result['alertes_stock'] ?? 0;

    // Dernières ventes
    $stmt = $pdo->query("SELECT v.*, b.nom as boutique_nom FROM ventes v 
                         JOIN boutiques b ON v.boutique_id = b.id 
                         WHERE v.statut IN ('validee', 'payee')
                         ORDER BY v.date_vente DESC LIMIT 5");
    $dernieres_ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Boutiques avec chiffre d'affaires
    $stmt = $pdo->query("SELECT b.*, COALESCE(SUM(v.total_ttc), 0) as ca_boutique 
                         FROM boutiques b 
                         LEFT JOIN ventes v ON b.id = v.boutique_id 
                         AND v.statut IN ('validee', 'payee')
                         WHERE b.statut = 0 AND b.actif = 1
                         GROUP BY b.id 
                         ORDER BY ca_boutique DESC");
    $boutiques_ca = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Produits les plus vendus
    $stmt = $pdo->query("SELECT p.reference, p.designation, SUM(lv.quantite) as total_vendu 
                         FROM lignes_vente lv 
                         JOIN produits p ON lv.produit_id = p.id 
                         GROUP BY p.id 
                         ORDER BY total_vendu DESC LIMIT 5");
    $produits_populaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur dashboard PDG: " . $e->getMessage());
    $error_message = "Erreur lors du chargement des données";
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dashboard PDG - Julien_Rideau</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
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
        
        .gradient-text {
            background: linear-gradient(90deg, #7B61FF 0%, #00D4AA 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .card-glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        }
        
        .shadow-hover {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
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
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stats-card:hover {
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 50;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            
            .overlay.open {
                display: block;
            }
        }
    </style>
</head>

<body class="font-inter min-h-screen bg-gray-50">
    <!-- Mobile menu button -->
    <button id="mobileMenuButton" class="md:hidden fixed top-4 left-4 z-50 p-2 bg-primary text-white rounded-lg">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Overlay for mobile -->
    <div id="overlay" class="overlay"></div>

    <!-- Navigation Sidebar -->
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 gradient-bg text-white flex flex-col">
            <!-- Logo -->
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center">
                        <span class="font-bold text-white text-lg">JR</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold">Julien_Rideau</h1>
                        <p class="text-xs text-gray-300">Dashboard PDG</p>
                    </div>
                </div>
            </div>
            
            <!-- User Info -->
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-yellow-500/20 border border-yellow-500/30 flex items-center justify-center">
                        <i class="fas fa-crown text-yellow-500"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?= htmlspecialchars($_SESSION['user_name'] ?? 'PDG') ?></h3>
                        <p class="text-sm text-gray-300"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Menu -->
            <nav class="flex-1 p-4 space-y-2">
                <a href="dashboard_pdg.php" class="flex items-center space-x-3 p-3 rounded-lg nav-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-store"></i>
                    <span>Boutiques</span>
                </a>
                <a href="rapports.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapports</span>
                </a>
                <a href="produits.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box"></i>
                    <span>Produits</span>
                </a>
                <a href="utilisateurs.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
                <a href="parametres.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </nav>
            
            <!-- Logout -->
            <div class="p-4 border-t border-white/10">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200 transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 p-4 md:p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Tableau de bord PDG</h1>
                        <p class="text-gray-600 text-sm md:text-base">Vue d'ensemble de l'entreprise</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative cursor-pointer" id="notifications">
                            <i class="fas fa-bell text-gray-400 text-xl"></i>
                            <?php if ($alertes_stock > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center animate-pulse">
                                <?= $alertes_stock ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-600 hidden md:block">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <span id="currentDate"><?= date('d/m/Y') ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 md:p-6">
                <!-- Messages d'erreur -->
                <?php if (isset($error_message)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <p class="text-red-700"><?= htmlspecialchars($error_message) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistiques globales -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
                    <!-- Carte 1 -->
                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-blue-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-store text-blue-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-blue-600">+2.5%</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $total_boutiques ?></h3>
                        <p class="text-gray-600 text-sm md:text-base">Boutiques actives</p>
                    </div>
                    
                    <!-- Carte 2 -->
                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-green-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-green-100 flex items-center justify-center">
                                <i class="fas fa-euro-sign text-green-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-green-600">+12.8%</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= number_format($ca_total, 0, ',', ' ') ?>€</h3>
                        <p class="text-gray-600 text-sm md:text-base">Chiffre d'affaires total</p>
                    </div>
                    
                    <!-- Carte 3 -->
                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-purple-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-purple-600"><?= $produits_total ?></span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $produits_total ?></h3>
                        <p class="text-gray-600 text-sm md:text-base">Produits en stock</p>
                    </div>
                    
                    <!-- Carte 4 -->
                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 stats-card border-l-4 border-red-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-3 md:mb-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-red-100 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-lg md:text-xl"></i>
                            </div>
                            <span class="text-xs md:text-sm font-medium text-red-600">Attention</span>
                        </div>
                        <h3 class="text-2xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2"><?= $alertes_stock ?></h3>
                        <p class="text-gray-600 text-sm md:text-base">Alertes stock</p>
                    </div>
                </div>

                <!-- Graphiques et tableaux -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
                    <!-- Dernières ventes -->
                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6">
                        <div class="flex justify-between items-center mb-4 md:mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Dernières ventes</h2>
                            <a href="ventes.php" class="text-sm text-secondary hover:text-accent transition-colors">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full min-w-max">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">N° Facture</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Boutique</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Montant</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dernieres_ventes)): ?>
                                        <?php foreach ($dernieres_ventes as $vente): ?>
                                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                            <td class="py-3 text-sm"><?= htmlspecialchars($vente['numero_facture'] ?? 'N/A') ?></td>
                                            <td class="py-3 text-sm"><?= htmlspecialchars($vente['boutique_nom'] ?? 'N/A') ?></td>
                                            <td class="py-3 text-sm font-medium"><?= number_format($vente['total_ttc'] ?? 0, 2, ',', ' ') ?>€</td>
                                            <td class="py-3 text-sm text-gray-500"><?= date('d/m/Y', strtotime($vente['date_vente'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="py-4 text-center text-gray-500">
                                                Aucune vente récente
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Produits populaires -->
                    <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6">
                        <div class="flex justify-between items-center mb-4 md:mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Produits populaires</h2>
                            <a href="produits.php" class="text-sm text-secondary hover:text-accent transition-colors">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="space-y-3 md:space-y-4">
                            <?php if (!empty($produits_populaires)): ?>
                                <?php foreach ($produits_populaires as $produit): ?>
                                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div class="truncate mr-4">
                                        <h4 class="font-medium text-gray-900 truncate"><?= htmlspecialchars($produit['designation'] ?? 'N/A') ?></h4>
                                        <p class="text-sm text-gray-500 truncate"><?= htmlspecialchars($produit['reference'] ?? 'N/A') ?></p>
                                    </div>
                                    <span class="px-3 py-1 bg-secondary text-white text-sm rounded-full whitespace-nowrap">
                                        <?= $produit['total_vendu'] ?? 0 ?> vendus
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-gray-500">
                                    Aucun produit populaire
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Performance des boutiques -->
                <div class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 mb-6 md:mb-8">
                    <div class="flex justify-between items-center mb-4 md:mb-6">
                        <h2 class="text-lg font-bold text-gray-900">Performance des boutiques</h2>
                        <a href="rapports.php" class="text-sm text-secondary hover:text-accent transition-colors">
                            Rapport complet <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-max">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Boutique</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">CA</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($boutiques_ca)): ?>
                                    <?php foreach ($boutiques_ca as $boutique): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                        <td class="py-3">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 rounded-full gradient-accent flex items-center justify-center flex-shrink-0">
                                                    <i class="fas fa-store text-white text-xs"></i>
                                                </div>
                                                <span class="font-medium truncate"><?= htmlspecialchars($boutique['nom'] ?? 'N/A') ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3 text-sm font-medium"><?= number_format($boutique['ca_boutique'] ?? 0, 0, ',', ' ') ?>€</td>
                                        <td class="py-3">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap
                                                <?= ($boutique['actif'] ?? 0) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= ($boutique['actif'] ?? 0) ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="py-4 text-center text-gray-500">
                                            Aucune boutique disponible
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                    <a href="nouvelle_boutique.php" class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 hover:shadow-hover transition-all hover-lift">
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl gradient-accent flex items-center justify-center mb-3 md:mb-4">
                            <i class="fas fa-plus text-white text-lg md:text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900 mb-1 md:mb-2">Ajouter une boutique</h3>
                        <p class="text-gray-600 text-sm">Créez une nouvelle boutique</p>
                    </a>
                    
                    <a href="rapport_ventes.php" class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 hover:shadow-hover transition-all hover-lift">
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-blue-100 flex items-center justify-center mb-3 md:mb-4">
                            <i class="fas fa-chart-pie text-blue-600 text-lg md:text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900 mb-1 md:mb-2">Rapport de ventes</h3>
                        <p class="text-gray-600 text-sm">Générez un rapport détaillé</p>
                    </a>
                    
                    <a href="alertes.php" class="bg-white rounded-xl md:rounded-2xl shadow-soft p-4 md:p-6 hover:shadow-hover transition-all hover-lift">
                        <div class="w-10 h-10 md:w-12 md:h-12 rounded-lg md:rounded-xl bg-red-100 flex items-center justify-center mb-3 md:mb-4">
                            <i class="fas fa-bell text-red-600 text-lg md:text-xl"></i>
                        </div>
                        <h3 class="font-bold text-gray-900 mb-1 md:mb-2">Alertes</h3>
                        <p class="text-gray-600 text-sm">Consultez les alertes système</p>
                    </a>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-white border-t border-gray-200 p-4 md:p-6 text-center text-gray-600 text-sm">
                <p>Julien_Rideau © <?= date('Y') ?> - Dashboard PDG v1.0</p>
            </footer>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }

        mobileMenuButton.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Close sidebar on window resize if it's desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
                document.body.style.overflow = '';
            }
        });

        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // Mise à jour de la date
            function updateDate() {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                };
                document.getElementById('currentDate').textContent = 
                    now.toLocaleDateString('fr-FR', options);
            }
            updateDate();

            // Gestion des notifications
            const notificationBell = document.getElementById('notifications');
            if (notificationBell) {
                notificationBell.addEventListener('click', function() {
                    window.location.href = 'alertes.php';
                });
            }
        });

        // Auto-refresh des données toutes les 5 minutes
        setTimeout(() => {
            window.location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html>