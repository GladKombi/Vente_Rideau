<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

include '../config/database.php';

// Récupérer les informations de la boutique
try {
    $stmt = $pdo->prepare("SELECT * FROM boutiques WHERE id = ? AND statut = 0 AND actif = 1");
    $stmt->execute([$boutique_id]);
    $boutique = $stmt->fetch();
    
    if (!$boutique) {
        session_destroy();
        header('Location: ../login.php');
        exit;
    }

    // Statistiques de la boutique
    // Chiffre d'affaires du mois
    $stmt = $pdo->prepare("SELECT SUM(total_ttc) as ca_mois 
                          FROM ventes 
                          WHERE boutique_id = ? 
                          AND statut IN ('validee', 'payee')
                          AND MONTH(date_vente) = MONTH(CURRENT_DATE())
                          AND YEAR(date_vente) = YEAR(CURRENT_DATE())");
    $stmt->execute([$boutique_id]);
    $ca_mois = $stmt->fetch()['ca_mois'] ?? 0;

    // Ventes du jour
    $stmt = $pdo->prepare("SELECT COUNT(*) as ventes_jour 
                          FROM ventes 
                          WHERE boutique_id = ? 
                          AND statut IN ('validee', 'payee')
                          AND DATE(date_vente) = CURDATE()");
    $stmt->execute([$boutique_id]);
    $ventes_jour = $stmt->fetch()['ventes_jour'] ?? 0;

    // Produits en stock
    $stmt = $pdo->prepare("SELECT COUNT(*) as produits_stock 
                          FROM stock_boutique 
                          WHERE boutique_id = ? 
                          AND quantite > 0");
    $stmt->execute([$boutique_id]);
    $produits_stock = $stmt->fetch()['produits_stock'] ?? 0;

    // Alertes stock bas
    $stmt = $pdo->prepare("SELECT COUNT(*) as alertes_stock 
                          FROM stock_boutique 
                          WHERE boutique_id = ? 
                          AND quantite <= seuil_alerte_min");
    $stmt->execute([$boutique_id]);
    $alertes_stock = $stmt->fetch()['alertes_stock'] ?? 0;

    // Dernières ventes
    $stmt = $pdo->prepare("SELECT * FROM ventes 
                          WHERE boutique_id = ? 
                          AND statut IN ('validee', 'payee')
                          ORDER BY date_vente DESC 
                          LIMIT 5");
    $stmt->execute([$boutique_id]);
    $dernieres_ventes = $stmt->fetchAll();

    // Produits avec stock bas
    $stmt = $pdo->prepare("SELECT sb.*, p.reference, p.designation 
                          FROM stock_boutique sb 
                          JOIN produits p ON sb.produit_id = p.id 
                          WHERE sb.boutique_id = ? 
                          AND sb.quantite <= sb.seuil_alerte_min 
                          ORDER BY sb.quantite ASC 
                          LIMIT 5");
    $stmt->execute([$boutique_id]);
    $produits_stock_bas = $stmt->fetchAll();

    // Produits populaires boutique
    $stmt = $pdo->prepare("SELECT p.reference, p.designation, SUM(lv.quantite) as total_vendu 
                          FROM lignes_vente lv 
                          JOIN ventes v ON lv.vente_id = v.id 
                          JOIN produits p ON lv.produit_id = p.id 
                          WHERE v.boutique_id = ? 
                          GROUP BY p.id 
                          ORDER BY total_vendu DESC 
                          LIMIT 5");
    $stmt->execute([$boutique_id]);
    $produits_populaires = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Erreur dashboard boutique: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dashboard Boutique - Julien_Rideau</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #7B61FF;
            --secondary: #00D4AA;
            --accent: #0A2540;
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
            background: linear-gradient(135deg, #7B61FF 0%, #00D4AA 100%);
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
        
        .hover-lift {
            transition: transform 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stats-card:hover {
            transform: translateX(5px);
        }
        
        .badge-stock {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stock-normal { background-color: #DCFCE7; color: #166534; }
        .stock-bas { background-color: #FEF3C7; color: #92400E; }
        .stock-critique { background-color: #FEE2E2; color: #991B1B; }
    </style>
</head>

<body class="font-inter min-h-screen bg-gray-50">
    <!-- Navigation Sidebar -->
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 gradient-bg text-white flex flex-col">
            <!-- Logo -->
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fas fa-store text-white"></i>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold"><?= htmlspecialchars($boutique['nom']) ?></h1>
                        <p class="text-xs text-gray-300">Dashboard boutique</p>
                    </div>
                </div>
            </div>
            
            <!-- User Info -->
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-white/20 border border-white/30 flex items-center justify-center">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?= htmlspecialchars($boutique['nom']) ?></h3>
                        <p class="text-sm text-gray-300">Boutique</p>
                    </div>
                </div>
            </div>
            
            <!-- Menu -->
            <nav class="flex-1 p-4 space-y-2">
                <a href="dashboard_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="ventes.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Ventes</span>
                </a>
                <a href="stock.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-boxes"></i>
                    <span>Stock</span>
                </a>
                <a href="produits_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-box"></i>
                    <span>Produits</span>
                </a>
                <a href="clients.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-users"></i>
                    <span>Clients</span>
                </a>
                <a href="rapports_boutique.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-chart-bar"></i>
                    <span>Rapports</span>
                </a>
            </nav>
            
            <!-- Logout -->
            <div class="p-4 border-t border-white/10">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord - <?= htmlspecialchars($boutique['nom']) ?></h1>
                        <p class="text-gray-600">Gestion de la boutique</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                            <?php if ($alertes_stock > 0): ?>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                <?= $alertes_stock ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            ID: <?= $boutique_id ?>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Statistiques boutique -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Carte 1 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-euro-sign text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Ce mois</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($ca_mois, 0, ',', ' ') ?>€</h3>
                        <p class="text-gray-600">Chiffre d'affaires</p>
                    </div>
                    
                    <!-- Carte 2 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-green-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-green-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-green-600">Aujourd'hui</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $ventes_jour ?></h3>
                        <p class="text-gray-600">Ventes</p>
                    </div>
                    
                    <!-- Carte 3 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">En stock</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $produits_stock ?></h3>
                        <p class="text-gray-600">Produits</p>
                    </div>
                    
                    <!-- Carte 4 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-red-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-red-600">Attention</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $alertes_stock ?></h3>
                        <p class="text-gray-600">Alertes stock</p>
                    </div>
                </div>

                <!-- Tableaux -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Dernières ventes -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Dernières ventes</h2>
                            <a href="ventes.php" class="text-sm text-secondary hover:text-green-700">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">N° Facture</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Client</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Montant</th>
                                        <th class="text-left py-3 text-sm font-medium text-gray-600">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dernieres_ventes as $vente): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 text-sm"><?= htmlspecialchars($vente['numero_facture']) ?></td>
                                        <td class="py-3 text-sm">
                                            <?php 
                                                if ($vente['client_id']) {
                                                    // Récupérer le nom du client
                                                    $stmt = $pdo->prepare("SELECT nom_complet FROM clients WHERE id = ?");
                                                    $stmt->execute([$vente['client_id']]);
                                                    $client = $stmt->fetch();
                                                    echo htmlspecialchars($client['nom_complet'] ?? 'Client');
                                                } else {
                                                    echo 'Client';
                                                }
                                            ?>
                                        </td>
                                        <td class="py-3 text-sm font-medium"><?= number_format($vente['total_ttc'], 2, ',', ' ') ?>€</td>
                                        <td class="py-3">
                                            <span class="px-2 py-1 rounded-full text-xs 
                                                <?= $vente['statut'] == 'payee' ? 'bg-green-100 text-green-800' : 
                                                   ($vente['statut'] == 'validee' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                                <?= $vente['statut'] == 'payee' ? 'Payée' : 
                                                   ($vente['statut'] == 'validee' ? 'Validée' : 'En attente') ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Produits stock bas -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Alertes stock</h2>
                            <a href="stock.php" class="text-sm text-secondary hover:text-green-700">
                                Gérer <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($produits_stock_bas as $produit): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg 
                                <?= $produit['quantite'] == 0 ? 'bg-red-50' : 
                                   ($produit['quantite'] <= $produit['seuil_alerte_min'] ? 'bg-yellow-50' : 'bg-gray-50') ?>">
                                <div>
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($produit['designation']) ?></h4>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($produit['reference']) ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="block text-lg font-bold 
                                        <?= $produit['quantite'] == 0 ? 'text-red-600' : 
                                           ($produit['quantite'] <= $produit['seuil_alerte_min'] ? 'text-yellow-600' : 'text-gray-600') ?>">
                                        <?= $produit['quantite'] ?>
                                    </span>
                                    <span class="text-xs <?= $produit['quantite'] == 0 ? 'text-red-700' : 'text-yellow-700' ?>">
                                        Seuil: <?= $produit['seuil_alerte_min'] ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($produits_stock_bas)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-check-circle text-4xl text-green-500 mb-3"></i>
                                <p class="text-gray-600">Aucune alerte stock</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Produits populaires et actions rapides -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Produits populaires -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Produits populaires</h2>
                            <a href="produits_boutique.php" class="text-sm text-secondary hover:text-green-700">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($produits_populaires as $produit): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 hover:bg-gray-100">
                                <div>
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($produit['designation']) ?></h4>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($produit['reference']) ?></p>
                                </div>
                                <span class="px-3 py-1 bg-secondary text-white text-sm rounded-full">
                                    <?= $produit['total_vendu'] ?> vendus
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Actions rapides -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-6">Actions rapides</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <a href="nouvelle_vente.php" class="p-4 rounded-xl border border-gray-200 hover:border-green-500 hover:bg-green-50 transition-all text-center hover-lift">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-cash-register text-green-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Nouvelle vente</span>
                            </a>
                            
                            <a href="inventaire.php" class="p-4 rounded-xl border border-gray-200 hover:border-blue-500 hover:bg-blue-50 transition-all text-center hover-lift">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-clipboard-check text-blue-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Inventaire</span>
                            </a>
                            
                            <a href="transfert_stock.php" class="p-4 rounded-xl border border-gray-200 hover:border-purple-500 hover:bg-purple-50 transition-all text-center hover-lift">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-exchange-alt text-purple-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Transfert stock</span>
                            </a>
                            
                            <a href="rapport_quotidien.php" class="p-4 rounded-xl border border-gray-200 hover:border-yellow-500 hover:bg-yellow-50 transition-all text-center hover-lift">
                                <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-file-alt text-yellow-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Rapport quotidien</span>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Mise à jour du temps en direct
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        setInterval(updateClock, 1000);
        updateClock();

        // Gestion des notifications
        function checkStockAlerts() {
            const alertCount = <?= $alertes_stock ?>;
            if (alertCount > 0 && !sessionStorage.getItem('alertsShown')) {
                showNotification('Stock', 'Vous avez ' + alertCount + ' produit(s) avec stock bas');
                sessionStorage.setItem('alertsShown', 'true');
            }
        }

        function showNotification(title, message) {
            if ("Notification" in window && Notification.permission === "granted") {
                new Notification(title, { body: message, icon: '/favicon.ico' });
            }
        }

        // Demander la permission pour les notifications
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }

        // Vérifier les alertes au chargement
        checkStockAlerts();
    </script>
</body>
</html>