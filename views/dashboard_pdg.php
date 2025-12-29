<?php
# Connexion à la Db
include '../connexion/connexion.php';

# Vérification de l'authentification PDG
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'PDG') {
    header('Location: ../login.php');
    exit;
}

// Récupérer les statistiques globales
$total_boutiques = 0;
$ca_total = 0;
$produits_total = 0;
$alertes_stock = 0;
$dernieres_ventes = [];
$boutiques_ca = [];
$produits_populaires = [];

try {
    // Nombre total de boutiques actives
    $stmt = $pdo->query("SELECT COUNT(*) as total_boutiques FROM boutiques WHERE statut = 0 AND actif = 1");
    $result = $stmt->fetch();
    $total_boutiques = $result['total_boutiques'] ?? 0;

    // Chiffre d'affaires total (somme des paiements)
    $stmt = $pdo->query("SELECT SUM(montant) as ca_total FROM paiements WHERE statut = 0");
    $result = $stmt->fetch();
    $ca_total = $result['ca_total'] ?? 0;

    // Nombre de produits distincts en stock (quantité > 0)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT produit_matricule) as produits_total 
        FROM stock 
        WHERE quantite > 0 AND statut = 0
    ");
    $result = $stmt->fetch();
    $produits_total = $result['produits_total'] ?? 0;

    // Alertes stock bas (quantité <= seuil d'alerte)
    $stmt = $pdo->query("
        SELECT COUNT(*) as alertes_stock 
        FROM stock 
        WHERE quantite <= seuil_alerte_stock 
        AND quantite > 0 
        AND statut = 0
    ");
    $result = $stmt->fetch();
    $alertes_stock = $result['alertes_stock'] ?? 0;

    // Derniers paiements
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.date,
            p.montant,
            p.commandes_id,
            (SELECT nom FROM boutiques WHERE id = (
                SELECT boutique_id FROM stock WHERE id = (
                    SELECT stock_id FROM commande_produits WHERE commande_id = p.commandes_id LIMIT 1
                ) LIMIT 1
            )) as boutique_nom
        FROM paiements p
        WHERE p.statut = 0
        ORDER BY p.date DESC 
        LIMIT 5
    ");
    $dernieres_ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Boutiques avec statistiques
    $stmt = $pdo->query("
        SELECT 
            b.*,
            (SELECT COUNT(*) FROM stock WHERE boutique_id = b.id AND quantite > 0 AND statut = 0) as produits_en_stock,
            (SELECT COUNT(*) FROM stock WHERE boutique_id = b.id AND quantite <= seuil_alerte_stock AND quantite > 0 AND statut = 0) as alertes_boutique
        FROM boutiques b 
        WHERE b.statut = 0 AND b.actif = 1
        ORDER BY b.nom
    ");
    $boutiques_ca = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Produits les plus vendus
    $stmt = $pdo->query("
        SELECT 
            p.matricule,
            p.designation,
            p.umProduit,
            COALESCE(SUM(cp.quantite), 0) as total_vendu,
            COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) as chiffre_affaires
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE cp.statut = 0
        GROUP BY p.matricule, p.designation, p.umProduit
        ORDER BY total_vendu DESC 
        LIMIT 5
    ");
    $produits_populaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur dashboard PDG: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des données: " . $e->getMessage();
}

// Calculer les tendances mensuelles
$ca_mois_courant = 0;
$ca_mois_precedent = 0;
$variation_ca = 0;

try {
    // CA du mois courant
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(montant), 0) as ca_mois 
        FROM paiements 
        WHERE statut = 0 
        AND MONTH(date) = MONTH(CURRENT_DATE()) 
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $result = $stmt->fetch();
    $ca_mois_courant = $result['ca_mois'] ?? 0;

    // CA du mois précédent
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(montant), 0) as ca_mois 
        FROM paiements 
        WHERE statut = 0 
        AND MONTH(date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
        AND YEAR(date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    ");
    $result = $stmt->fetch();
    $ca_mois_precedent = $result['ca_mois'] ?? 0;

    // Calculer la variation
    if ($ca_mois_precedent > 0) {
        $variation_ca = (($ca_mois_courant - $ca_mois_precedent) / $ca_mois_precedent) * 100;
    } elseif ($ca_mois_courant > 0) {
        $variation_ca = 100;
    }

} catch (PDOException $e) {
    error_log("Erreur calcul tendances: " . $e->getMessage());
}

// Calculer les produits actifs
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE statut = 0 AND actif = 1");
    $total_produits = $stmt->fetch()['total'] ?? 0;
    $pourcentage_produits = $total_produits > 0 ? ($produits_total / $total_produits) * 100 : 0;
} catch (Exception $e) {
    $total_produits = 0;
    $pourcentage_produits = 0;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Tableau de bord - PDG <?= htmlspecialchars($_SESSION['user_name'] ?? 'NGS') ?></title>
    
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
        <!-- Sidebar -->
        <aside class="w-64 gradient-bg text-white min-h-screen">
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center">
                        <span class="font-bold">NGS</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold truncate">NGS Rideaux</h1>
                        <p class="text-xs text-gray-300">Dashboard PDG</p>
                    </div>
                </div>
            </div>

            <nav class="p-4 space-y-1">
                <a href="dashboard_pdg.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-chart-line w-5"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-store w-5"></i>
                    <span>Boutiques</span>
                    <?php if ($total_boutiques > 0): ?>
                    <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $total_boutiques ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="produits.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-box w-5"></i>
                    <span>Produits</span>
                </a>
                <a href="stocks.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-warehouse w-5"></i>
                    <span>Stocks</span>
                    <?php if ($alertes_stock > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= $alertes_stock ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="transferts.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="utilisateurs.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-users w-5"></i>
                    <span>Utilisateurs</span>
                </a>
                <a href="parametres.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-cog w-5"></i>
                    <span>Paramètres</span>
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
                        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord PDG</h1>
                        <p class="text-gray-600">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <?= date('d/m/Y') ?> | 
                            <i class="fas fa-clock ml-4 mr-2"></i>
                            <span id="currentTime"><?= date('H:i') ?></span>
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <?php if ($alertes_stock > 0): ?>
                        <a href="stocks.php" 
                           class="px-4 py-2 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-lg hover:opacity-90 shadow-md flex items-center">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Alertes Stock
                            <span class="ml-2 bg-white text-red-600 text-xs font-bold rounded-full w-6 h-6 flex items-center justify-center">
                                <?= $alertes_stock ?>
                            </span>
                        </a>
                        <?php endif; ?>
                        
                        <a href="nouvelle_boutique.php"
                           class="px-4 py-2 gradient-success text-white rounded-lg hover:opacity-90 shadow-md">
                            <i class="fas fa-plus mr-2"></i>Nouvelle boutique
                        </a>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Carte 1 - Boutiques -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-store text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Actives</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= $total_boutiques ?></h3>
                        <p class="text-gray-600 text-sm">Boutiques</p>
                    </div>
                    
                    <!-- Carte 2 - CA Total -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-green-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                                <i class="fas fa-euro-sign text-green-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-green-600">Total</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= number_format($ca_total, 2, ',', ' ') ?> €</h3>
                        <p class="text-gray-600 text-sm">Chiffre d'affaires</p>
                    </div>
                    
                    <!-- Carte 3 - Produits -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">En stock</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= $produits_total ?></h3>
                        <p class="text-gray-600 text-sm">Produits disponibles</p>
                    </div>
                    
                    <!-- Carte 4 - Alertes -->
                    <div class="bg-white rounded-xl shadow-soft p-6 stats-card border-l-4 border-red-500 animate-fade-in hover-lift">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-red-600">À traiter</span>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2"><?= $alertes_stock ?></h3>
                        <p class="text-gray-600 text-sm">Alertes stock</p>
                    </div>
                </div>

                <!-- Tableaux -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Dernières transactions -->
                    <div class="bg-white rounded-xl shadow-soft p-6 hover-lift">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Dernières transactions</h2>
                            <a href="paiements.php" class="text-sm text-purple-600 hover:text-purple-800">
                                Voir tout
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">ID</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Boutique</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Montant</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($dernieres_ventes)): ?>
                                    <?php foreach ($dernieres_ventes as $vente): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 text-sm">#<?= htmlspecialchars($vente['id'] ?? 'N/A') ?></td>
                                        <td class="py-3 text-sm"><?= htmlspecialchars($vente['boutique_nom'] ?? 'N/A') ?></td>
                                        <td class="py-3 text-sm font-medium text-green-600">
                                            <?= number_format($vente['montant'] ?? 0, 2, ',', ' ') ?> €
                                        </td>
                                        <td class="py-3 text-sm text-gray-500">
                                            <?= date('d/m/Y', strtotime($vente['date'] ?? '')) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-gray-500">
                                            <i class="fas fa-shopping-cart text-3xl text-gray-300 mb-3"></i>
                                            <p>Aucune transaction récente</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Produits populaires -->
                    <div class="bg-white rounded-xl shadow-soft p-6 hover-lift">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Top produits</h2>
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
                                        <?= number_format($produit['chiffre_affaires'], 2, ',', ' ') ?> €
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

                <!-- Boutiques -->
                <div class="bg-white rounded-xl shadow-soft p-6 hover-lift mb-8">
                    <h2 class="text-lg font-bold text-gray-900 mb-6">Boutiques</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Boutique</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Produits en stock</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Alertes</th>
                                    <th class="text-left py-3 text-sm font-medium text-gray-600">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($boutiques_ca)): ?>
                                <?php foreach ($boutiques_ca as $boutique): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-store text-gray-600"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900"><?= htmlspecialchars($boutique['nom']) ?></h4>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($boutique['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 text-sm font-medium text-gray-900">
                                        <?= $boutique['produits_en_stock'] ?? 0 ?>
                                    </td>
                                    <td class="py-3">
                                        <?php if (($boutique['alertes_boutique'] ?? 0) > 0): ?>
                                        <span class="badge badge-danger">
                                            <?= $boutique['alertes_boutique'] ?? 0 ?> alerte(s)
                                        </span>
                                        <?php else: ?>
                                        <span class="badge badge-success">
                                            OK
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge <?= ($boutique['actif'] ?? 0) ? 'badge-success' : 'badge-danger' ?>">
                                            <?= ($boutique['actif'] ?? 0) ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-500">
                                        <i class="fas fa-store text-3xl text-gray-300 mb-3"></i>
                                        <p>Aucune boutique disponible</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="nouvelle_boutique.php" 
                       class="p-4 rounded-xl border-2 border-green-200 hover:border-green-500 hover:bg-green-50 text-center">
                        <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-plus text-green-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Nouvelle boutique</span>
                    </a>
                    
                    <a href="nouveau_produit.php" 
                       class="p-4 rounded-xl border-2 border-blue-200 hover:border-blue-500 hover:bg-blue-50 text-center">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-box text-blue-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Nouveau produit</span>
                    </a>
                    
                    <a href="stocks.php" 
                       class="p-4 rounded-xl border-2 border-purple-200 hover:border-purple-500 hover:bg-purple-50 text-center">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center mx-auto mb-2">
                            <i class="fas fa-warehouse text-purple-600"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-900">Gestion stocks</span>
                    </a>
                    
                    <a href="rapports.php" 
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