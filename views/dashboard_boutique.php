<?php
// dashboard_boutique.php - VERSION SIMPLIFIÉE
include '../connexion/connexion.php';

# Vérifier si une boutique est connectée 
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

# Récupérer l'ID de la boutique 
$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

// Initialiser toutes les variables
$boutique = [];
$stats = [
    'ca_total' => 0,
    'ca_mois' => 0,
    'ca_semaine' => 0,
    'ca_jour' => 0,
    'commandes_total' => 0,
    'commandes_jour' => 0,
    'produits_stock' => 0,
    'alertes_stock' => 0,
    'valeur_stock' => 0,
    'total_impaye' => 0
];

$produits_stock_bas = [];
$produits_populaires = [];
$mouvements_recent = [];

// Récupérer les informations de la boutique
try {
    $stmt = $pdo->prepare("SELECT * FROM boutiques WHERE id = ? AND statut = 0");
    $stmt->execute([$boutique_id]);
    $boutique = $stmt->fetch();
    
    if (!$boutique) {
        session_destroy();
        header('Location: ../login.php');
        exit;
    }

    // ============================================
    // STATISTIQUES DE BASE (UTILISANT LES TABLES EXISTANTES)
    // ============================================

    // 1. Chiffre d'affaires total (basé sur commande_produits)
    // Note: Sans table commandes, on calcule à partir de commande_produits
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) as ca_total 
        FROM commande_produits cp
        WHERE cp.statut = 0
        AND EXISTS (
            SELECT 1 FROM paiements p 
            WHERE p.commandes_id = cp.commande_id 
            AND p.statut = 0
        )
    ");
    $stmt->execute();
    $stats['ca_total'] = $stmt->fetch()['ca_total'] ?? 0;

    // 2. CA du mois (approximation basée sur paiements)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.montant), 0) as ca_mois 
        FROM paiements p
        WHERE p.statut = 0
        AND MONTH(p.date) = MONTH(CURRENT_DATE())
        AND YEAR(p.date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $stats['ca_mois'] = $stmt->fetch()['ca_mois'] ?? 0;

    // 3. CA de la semaine
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.montant), 0) as ca_semaine 
        FROM paiements p
        WHERE p.statut = 0
        AND YEARWEEK(p.date, 1) = YEARWEEK(CURDATE(), 1)
    ");
    $stmt->execute();
    $stats['ca_semaine'] = $stmt->fetch()['ca_semaine'] ?? 0;

    // 4. CA du jour
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.montant), 0) as ca_jour 
        FROM paiements p
        WHERE p.statut = 0
        AND DATE(p.date) = CURDATE()
    ");
    $stmt->execute();
    $stats['ca_jour'] = $stmt->fetch()['ca_jour'] ?? 0;

    // 5. Nombre de commandes (approximation basée sur paiements)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT p.commandes_id) as total,
            SUM(CASE WHEN DATE(p.date) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui
        FROM paiements p
        WHERE p.statut = 0
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['commandes_total'] = $result['total'] ?? 0;
    $stats['commandes_jour'] = $result['aujourdhui'] ?? 0;

    // 6. Produits en stock
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT produit_matricule) as produits_stock 
        FROM stock 
        WHERE boutique_id = ? 
        AND statut = 0
        AND quantite > 0
    ");
    $stmt->execute([$boutique_id]);
    $stats['produits_stock'] = $stmt->fetch()['produits_stock'] ?? 0;

    // 7. Alertes stock
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as alertes_stock 
        FROM stock 
        WHERE boutique_id = ? 
        AND statut = 0
        AND quantite <= seuil_alerte_stock
        AND quantite > 0
    ");
    $stmt->execute([$boutique_id]);
    $stats['alertes_stock'] = $stmt->fetch()['alertes_stock'] ?? 0;

    // 8. Valeur du stock
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantite * prix), 0) as valeur_stock
        FROM stock 
        WHERE boutique_id = ? 
        AND statut = 0
        AND quantite > 0
    ");
    $stmt->execute([$boutique_id]);
    $stats['valeur_stock'] = $stmt->fetch()['valeur_stock'] ?? 0;

    // 9. Produits avec stock bas
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            p.designation,
            p.matricule,
            p.umProduit
        FROM stock s
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ? 
        AND s.statut = 0
        AND s.quantite <= s.seuil_alerte_stock
        ORDER BY s.quantite ASC
        LIMIT 10
    ");
    $stmt->execute([$boutique_id]);
    $produits_stock_bas = $stmt->fetchAll();

    // 10. Produits les plus vendus (basé sur commande_produits)
    $stmt = $pdo->prepare("
        SELECT 
            p.matricule,
            p.designation,
            p.umProduit,
            COALESCE(SUM(cp.quantite), 0) as total_vendu,
            COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) as chiffre_affaires,
            (SELECT quantite 
             FROM stock 
             WHERE produit_matricule = p.matricule 
             AND boutique_id = ? 
             AND statut = 0 LIMIT 1) as stock_actuel
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ? 
        AND cp.statut = 0
        GROUP BY p.matricule, p.designation, p.umProduit
        ORDER BY total_vendu DESC 
        LIMIT 8
    ");
    $stmt->execute([$boutique_id, $boutique_id]);
    $produits_populaires = $stmt->fetchAll();

    // 11. Mouvements récents (ventes et approvisionnements)
    $stmt = $pdo->prepare("
        (SELECT 
            'vente' as type,
            cp.quantite as quantite,
            p.designation,
            'Sortie' as sens,
            DATE_FORMAT((SELECT MAX(date) FROM paiements WHERE commandes_id = cp.commande_id), '%d/%m %H:%i') as date_str
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ? 
        AND cp.statut = 0
        ORDER BY (SELECT MAX(date) FROM paiements WHERE commandes_id = cp.commande_id) DESC
        LIMIT 5)
        UNION ALL
        (SELECT 
            'approvisionnement' as type,
            s.quantite as quantite,
            p.designation,
            'Entrée' as sens,
            DATE_FORMAT(s.date_creation, '%d/%m %H:%i') as date_str
        FROM stock s
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ? 
        AND s.statut = 0
        AND s.type_mouvement = 'approvisionnement'
        ORDER BY s.date_creation DESC
        LIMIT 5)
        ORDER BY date_str DESC
        LIMIT 10
    ");
    $stmt->execute([$boutique_id, $boutique_id]);
    $mouvements_recent = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Erreur dashboard boutique: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des données.";
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Tableau de bord - Boutique <?= htmlspecialchars($boutique['nom'] ?? 'NGS') ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0A2540;
            --secondary: #7B61FF;
            --accent: #00D4AA;
        }
        
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #F8FAFC;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #0A2540 0%, #1E3A5F 100%);
        }
        
        .gradient-success {
            background: linear-gradient(90deg, #10B981 0%, #059669 100%); 
        }
        
        .gradient-warning {
            background: linear-gradient(90deg, #F59E0B 0%, #D97706 100%); 
        }
        
        .gradient-danger {
            background: linear-gradient(90deg, #EF4444 0%, #DC2626 100%); 
        }
        
        .gradient-info {
            background: linear-gradient(90deg, #3B82F6 0%, #1D4ED8 100%); 
        }
        
        .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-success { background-color: #D1FAE5; color: #065F46; }
        .badge-warning { background-color: #FEF3C7; color: #92400E; }
        .badge-danger { background-color: #FEE2E2; color: #991B1B; }
        
        .stats-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="flex">
        <!-- Sidebar simplifiée -->
        <aside class="w-64 gradient-bg text-white min-h-screen">
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center">
                        <span class="font-bold">NGS</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold truncate"><?= htmlspecialchars($boutique['nom'] ?? 'Boutique') ?></h1>
                        <p class="text-xs text-gray-300">Tableau de bord</p>
                    </div>
                </div>
            </div>

            <nav class="p-4 space-y-1">
                <a href="dashboard_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-chart-line w-5"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="ventes_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-shopping-cart w-5"></i>
                    <span>Ventes</span>
                </a>
                <a href="paiements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users w-5 text-gray-300"></i>
                    <span>paiements</span>
                </a>
                <a href="mouvements.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-exchange-alt w-5 text-white"></i>
                    <span>Mouvements Caisse</span>
                </a>              
                <a href="stock_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-box w-5"></i>
                    <span>Stock</span>
                    <?php if ($stats['alertes_stock'] > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $stats['alertes_stock'] ?>
                    </span>
                    <?php endif; ?>
                </a>
                 <a href="rapports_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-bar w-5 text-gray-300"></i>
                    <span>Rapports</span>
                </a>
            </nav>

            <div class="p-4 border-t border-white/10 mt-auto">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <div class="flex-1">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord</h1>
                        <p class="text-gray-600">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <?= date('d/m/Y') ?> | 
                            <i class="fas fa-clock ml-4 mr-2"></i>
                            <span id="currentTime"><?= date('H:i') ?></span>
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <?php if ($stats['alertes_stock'] > 0): ?>
                        <a href="stock_boutique.php" 
                           class="px-4 py-2 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-lg hover:opacity-90 shadow-md flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Alertes Stock
                            <span class="ml-2 bg-white text-red-600 text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                <?= $stats['alertes_stock'] ?>
                            </span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="ventes_boutique.php"
                           class="px-4 py-2 gradient-success text-white rounded-lg hover:opacity-90 shadow-md">
                            <i class="fas fa-plus mr-2"></i>Nouvelle vente
                        </a>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Carte 1 - CA Mensuel -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-euro-sign text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Ce mois</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= number_format($stats['ca_mois'], 2, ',', ' ') ?> $</h3>
                        <p class="text-gray-600 text-sm">Chiffre d'affaires</p>
                    </div>
                    
                    <!-- Carte 2 - Commandes -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-green-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-green-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-green-600">Aujourd'hui</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= $stats['commandes_jour'] ?></h3>
                        <p class="text-gray-600 text-sm">Commandes</p>
                    </div>
                    
                    <!-- Carte 3 - Stock -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Valeur</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= number_format($stats['valeur_stock'], 2, ',', ' ') ?> $</h3>
                        <p class="text-gray-600 text-sm">Stock boutique</p>
                    </div>
                    
                    <!-- Carte 4 - Produits -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-orange-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-orange-100 flex items-center justify-center">
                                <i class="fas fa-box text-orange-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-orange-600">En stock</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= $stats['produits_stock'] ?></h3>
                        <p class="text-gray-600 text-sm">Produits différents</p>
                    </div>
                </div>

                <!-- Tableaux -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Produits avec stock bas -->
                    <div class="bg-white rounded-xl shadow-soft p-6 hover-lift">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Alertes stock</h2>
                            <a href="stock_boutique.php" class="text-sm text-purple-600 hover:text-purple-800">
                                Gérer
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php if (!empty($produits_stock_bas)): ?>
                            <?php foreach ($produits_stock_bas as $produit): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg <?= $produit['quantite'] == 0 ? 'bg-red-50' : 'bg-yellow-50' ?>">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($produit['designation']) ?></h4>
                                    <div class="flex items-center gap-4 text-sm text-gray-500">
                                        <span>Réf: <?= htmlspecialchars($produit['matricule']) ?></span>
                                        <span>Unité: <?= $produit['umProduit'] ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-lg font-bold <?= $produit['quantite'] == 0 ? 'text-red-600' : 'text-yellow-600' ?>">
                                        <?= $produit['quantite'] ?>
                                    </span>
                                    <div class="text-xs text-gray-500">
                                        Seuil: <?= $produit['seuil_alerte_stock'] ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-check-circle text-3xl text-green-500 mb-3"></i>
                                <p>Aucune alerte stock</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Produits populaires -->
                    <div class="bg-white rounded-xl shadow-soft p-6 hover-lift">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Produits populaires</h2>
                            <span class="text-sm text-gray-500">Toutes ventes</span>
                        </div>
                        <div class="space-y-4">
                            <?php if (!empty($produits_populaires)): ?>
                            <?php foreach ($produits_populaires as $produit): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($produit['designation']) ?></h4>
                                    <div class="flex items-center gap-4 text-sm text-gray-500">
                                        <span>Réf: <?= htmlspecialchars($produit['matricule']) ?></span>
                                        <span>Unité: <?= $produit['umProduit'] ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-lg font-bold text-green-600">
                                        <?= $produit['total_vendu'] ?> vendus
                                    </span>
                                    <div class="text-sm text-gray-600">
                                        <?= number_format($produit['chiffre_affaires'], 2, ',', ' ') ?> $
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-chart-line text-3xl text-gray-300 mb-3"></i>
                                <p>Aucune donnée de vente</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Mouvements récents -->
                <div class="bg-white rounded-xl shadow-soft p-6 hover-lift mb-8">
                    <h2 class="text-lg font-bold text-gray-900 mb-6">Mouvements récents</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Type</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Produit</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Quantité</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Sens</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($mouvements_recent)): ?>
                                <?php foreach ($mouvements_recent as $mouvement): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3">
                                        <span class="badge <?= $mouvement['type'] == 'vente' ? 'badge-success' : 'badge-info' ?>">
                                            <?= ucfirst($mouvement['type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm text-gray-700">
                                        <?= htmlspecialchars($mouvement['designation']) ?>
                                    </td>
                                    <td class="py-3 text-sm font-medium text-gray-900">
                                        <?= $mouvement['quantite'] ?>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge <?= $mouvement['sens'] == 'Entrée' ? 'badge-success' : 'badge-danger' ?>">
                                            <?= $mouvement['sens'] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-sm text-gray-500">
                                        <?= $mouvement['date_str'] ?? 'N/A' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-gray-500">
                                        Aucun mouvement récent
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="ventes_boutique.php?action=nouvelle" 
                       class="p-4 rounded-xl border-2 border-green-200 hover:border-green-500 hover:bg-green-50 text-center">
                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-cash-register text-green-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Nouvelle vente</span>
                    </a>
                    
                    <a href="stock_boutique.php" 
                       class="p-4 rounded-xl border-2 border-blue-200 hover:border-blue-500 hover:bg-blue-50 text-center">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-boxes text-blue-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Gestion stock</span>
                    </a>
                    
                    <a href="paiements.php" 
                       class="p-4 rounded-xl border-2 border-purple-200 hover:border-purple-500 hover:bg-purple-50 text-center">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-money-bill-wave text-purple-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Paiements</span>
                    </a>
                    
                    <a href="rapports_boutique.php" 
                       class="p-4 rounded-xl border-2 border-yellow-200 hover:border-yellow-500 hover:bg-yellow-50 text-center">
                        <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-chart-bar text-yellow-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Rapports</span>
                    </a>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mise à jour du temps
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', { 
                hour: '2-digit', 
                minute: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        setInterval(updateClock, 60000);
        updateClock();
        
        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>