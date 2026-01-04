<?php
# Connexion à la base de données
include '../connexion/connexion.php';

// Vérification de l'authentification BOUTIQUE
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de la boutique connectée
$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

// Initialisation des variables
$message = '';
$message_type = '';
$boutique_info = null;

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer les informations de la boutique
try {
    $queryBoutique = $pdo->prepare("SELECT id, nom, email, date_creation, actif FROM boutiques WHERE id = ? AND statut = 0");
    $queryBoutique->execute([$boutique_id]);
    $boutique_info = $queryBoutique->fetch(PDO::FETCH_ASSOC);

    if (!$boutique_info) {
        $_SESSION['flash_message'] = [
            'text' => "Boutique introuvable ou supprimée",
            'type' => "error"
        ];
        header('Location: ../login.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des informations de la boutique : " . $e->getMessage(),
        'type' => "error"
    ];
    header('Location: ../login.php');
    exit;
}

// Récupérer les dates de filtrage
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Début du mois par défaut
$date_fin = $_GET['date_fin'] ?? date('Y-m-t'); // Fin du mois par défaut
$periode = $_GET['periode'] ?? 'mois'; // mois, semaine, jour, personnalise

// Ajuster les dates selon la période
if ($periode === 'semaine') {
    $date_debut = date('Y-m-d', strtotime('monday this week'));
    $date_fin = date('Y-m-d', strtotime('sunday this week'));
} elseif ($periode === 'jour') {
    $date_debut = date('Y-m-d');
    $date_fin = date('Y-m-d');
}

// Initialiser les tableaux de données
$rapport_stocks = [];
$alertes_stock = [];
$rapport_ventes = [];
$top_produits = [];
$rapport_mouvements = [];
$balance_caisse = [];

// Variables de statistiques
$valeur_stock = 0;
$chiffre_affaires = 0;
$total_entrees = 0;
$total_sorties = 0;
$solde_caisse = 0;
$nb_produits = 0;
$quantite_totale = 0;
$stats_unite = [];

try {
    // --- RAPPORTS STOCKS ---
    // 1. Stocks par produit
    $queryStocks = $pdo->prepare("
        SELECT 
            p.matricule,
            p.designation,
            p.umProduit,
            SUM(s.quantite) as quantite_totale,
            AVG(s.prix) as prix_moyen,
            MIN(s.prix) as prix_min,
            MAX(s.prix) as prix_max,
            SUM(s.quantite * s.prix) as valeur_totale,
            COUNT(s.id) as nombre_mouvements
        FROM stock s
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ?
          AND s.statut = 0
          AND s.quantite > 0
        GROUP BY p.matricule, p.designation, p.umProduit
        ORDER BY valeur_totale DESC
    ");
    $queryStocks->execute([$boutique_id]);
    $rapport_stocks = $queryStocks->fetchAll(PDO::FETCH_ASSOC);

    // 2. Alertes de stock faible
    $queryAlertes = $pdo->prepare("
        SELECT 
            p.designation,
            p.umProduit,
            s.quantite,
            s.seuil_alerte_stock,
            s.date_creation
        FROM stock s
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ?
          AND s.statut = 0
          AND s.quantite <= s.seuil_alerte_stock
          AND s.quantite > 0
        ORDER BY s.quantite ASC
    ");
    $queryAlertes->execute([$boutique_id]);
    $alertes_stock = $queryAlertes->fetchAll(PDO::FETCH_ASSOC);

    // --- RAPPORTS VENTES ---
    // Ventes par période (en utilisant commandes et commande_produits)
    $queryVentes = $pdo->prepare("
        SELECT 
            DATE(c.date_commande) as date_vente,
            COUNT(DISTINCT c.id) as nombre_commandes,
            SUM(cp.quantite) as quantite_vendue,
            SUM(cp.quantite * cp.prix_unitaire) as chiffre_affaires,
            AVG(cp.quantite * cp.prix_unitaire) as panier_moyen
        FROM commandes c
        JOIN commande_produits cp ON c.id = cp.commande_id
        WHERE c.boutique_id = ?
          AND c.statut = 0
          AND cp.statut = 0
          AND DATE(c.date_commande) BETWEEN ? AND ?
          AND c.etat = 'payee'
        GROUP BY DATE(c.date_commande)
        ORDER BY date_vente DESC
        LIMIT 30
    ");
    $queryVentes->execute([$boutique_id, $date_debut, $date_fin]);
    $rapport_ventes = $queryVentes->fetchAll(PDO::FETCH_ASSOC);

    // 2. Produits les plus vendus
    $queryTopProduits = $pdo->prepare("
        SELECT 
            p.designation,
            p.umProduit,
            SUM(cp.quantite) as quantite_vendue,
            SUM(cp.quantite * cp.prix_unitaire) as chiffre_affaires_produit,
            COUNT(DISTINCT c.id) as nombre_commandes
        FROM commande_produits cp
        JOIN commandes c ON cp.commande_id = c.id
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE c.boutique_id = ?
          AND cp.statut = 0
          AND c.statut = 0
          AND c.etat = 'payee'
          AND DATE(c.date_commande) BETWEEN ? AND ?
        GROUP BY p.designation, p.umProduit
        ORDER BY quantite_vendue DESC
        LIMIT 10
    ");
    $queryTopProduits->execute([$boutique_id, $date_debut, $date_fin]);
    $top_produits = $queryTopProduits->fetchAll(PDO::FETCH_ASSOC);

    // --- RAPPORTS MOUVEMENTS DE CAISSE ---
    // 1. Mouvements par type
    $queryMouvements = $pdo->prepare("
        SELECT 
            DATE(mc.date_mouvement) as date_mouvement,
            mc.type_mouvement,
            SUM(mc.montant) as montant_total,
            COUNT(mc.id) as nombre_operations
        FROM mouvement_caisse mc
        WHERE mc.id_boutique = ?
          AND DATE(mc.date_mouvement) BETWEEN ? AND ?
          AND mc.statut = 0
        GROUP BY DATE(mc.date_mouvement), mc.type_mouvement
        ORDER BY date_mouvement DESC
        LIMIT 30
    ");
    $queryMouvements->execute([$boutique_id, $date_debut, $date_fin]);
    $rapport_mouvements = $queryMouvements->fetchAll(PDO::FETCH_ASSOC);

    // 2. Balance de caisse
    $queryBalance = $pdo->prepare("
        SELECT 
            type_mouvement,
            SUM(montant) as montant_total,
            COUNT(id) as nombre_operations
        FROM mouvement_caisse
        WHERE id_boutique = ?
          AND DATE(date_mouvement) BETWEEN ? AND ?
          AND statut = 0
        GROUP BY type_mouvement
    ");
    $queryBalance->execute([$boutique_id, $date_debut, $date_fin]);
    $balance_caisse = $queryBalance->fetchAll(PDO::FETCH_ASSOC);

    // --- STATISTIQUES GÉNÉRALES ---
    // 1. Valeur totale du stock
    $queryValeurStock = $pdo->prepare("
        SELECT SUM(quantite * prix) as valeur_stock
        FROM stock
        WHERE boutique_id = ?
          AND statut = 0
          AND quantite > 0
    ");
    $queryValeurStock->execute([$boutique_id]);
    $valeur_stock = $queryValeurStock->fetchColumn() ?? 0;

    // 2. Chiffre d'affaires période
    $queryCA = $pdo->prepare("
        SELECT SUM(cp.quantite * cp.prix_unitaire) as chiffre_affaires
        FROM commande_produits cp
        JOIN commandes c ON cp.commande_id = c.id
        WHERE c.boutique_id = ?
          AND cp.statut = 0
          AND c.statut = 0
          AND c.etat = 'payee'
          AND DATE(c.date_commande) BETWEEN ? AND ?
    ");
    $queryCA->execute([$boutique_id, $date_debut, $date_fin]);
    $chiffre_affaires = $queryCA->fetchColumn() ?? 0;

    // 3. Total entrées/sorties caisse
    $queryCaisse = $pdo->prepare("
        SELECT 
            type_mouvement,
            SUM(montant) as total
        FROM mouvement_caisse
        WHERE id_boutique = ?
          AND DATE(date_mouvement) BETWEEN ? AND ?
          AND statut = 0
        GROUP BY type_mouvement
    ");
    $queryCaisse->execute([$boutique_id, $date_debut, $date_fin]);
    $totaux_caisse = [];
    while ($row = $queryCaisse->fetch(PDO::FETCH_ASSOC)) {
        $totaux_caisse[$row['type_mouvement']] = $row['total'];
    }

    $total_entrees = $totaux_caisse['entrée'] ?? 0;
    $total_sorties = $totaux_caisse['sortie'] ?? 0;
    $solde_caisse = $total_entrees - $total_sorties;

    // 4. Nombre de produits différents
    $queryNbProduits = $pdo->prepare("
        SELECT COUNT(DISTINCT produit_matricule) as nb_produits
        FROM stock
        WHERE boutique_id = ?
          AND statut = 0
          AND quantite > 0
    ");
    $queryNbProduits->execute([$boutique_id]);
    $nb_produits = $queryNbProduits->fetchColumn() ?? 0;

    // 5. Quantité totale en stock
    $queryQteStock = $pdo->prepare("
        SELECT SUM(quantite) as quantite_totale
        FROM stock
        WHERE boutique_id = ?
          AND statut = 0
          AND quantite > 0
    ");
    $queryQteStock->execute([$boutique_id]);
    $quantite_totale = $queryQteStock->fetchColumn() ?? 0;

    // Statistiques par unité de mesure
    $queryStatsUnite = $pdo->prepare("
        SELECT 
            p.umProduit,
            COUNT(DISTINCT s.produit_matricule) as nombre_produits,
            SUM(s.quantite) as quantite_totale,
            SUM(s.quantite * s.prix) as valeur_totale
        FROM stock s
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE s.boutique_id = ?
          AND s.statut = 0
          AND s.quantite > 0
        GROUP BY p.umProduit
    ");
    $queryStatsUnite->execute([$boutique_id]);
    $stats_unite = $queryStatsUnite->fetchAll(PDO::FETCH_ASSOC);

    // --- RAPPORTS TRANSFERTS ---
    $queryTransferts = $pdo->prepare("
        SELECT 
            t.id,
            t.date,
            t.Expedition as boutique_expedition_id,
            t.Destination as boutique_destination_id,
            be.nom as boutique_expedition,
            bd.nom as boutique_destination,
            s.produit_matricule,
            p.designation,
            p.umProduit,
            t.statut
        FROM transferts t
        JOIN stock s ON t.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        LEFT JOIN boutiques be ON t.Expedition = be.id
        LEFT JOIN boutiques bd ON t.Destination = bd.id
        WHERE (t.Expedition = ? OR t.Destination = ?)
          AND t.statut = 0
          AND DATE(t.date) BETWEEN ? AND ?
        ORDER BY t.date DESC
    ");
    $queryTransferts->execute([$boutique_id, $boutique_id, $date_debut, $date_fin]);
    $rapport_transferts = $queryTransferts->fetchAll(PDO::FETCH_ASSOC);

    // --- RAPPORTS PAIEMENTS ---
    $queryPaiements = $pdo->prepare("
        SELECT 
            p.id,
            p.date,
            p.montant,
            c.numero_facture,
            c.client_nom,
            p.statut
        FROM paiements p
        JOIN commandes c ON p.commandes_id = c.id
        WHERE c.boutique_id = ?
          AND p.statut = 0
          AND DATE(p.date) BETWEEN ? AND ?
        ORDER BY p.date DESC
    ");
    $queryPaiements->execute([$boutique_id, $date_debut, $date_fin]);
    $rapport_paiements = $queryPaiements->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques paiements
    $total_paiements = array_sum(array_column($rapport_paiements, 'montant'));

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des rapports: " . $e->getMessage(),
        'type' => "error"
    ];
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Rapports - <?= htmlspecialchars($boutique_info['nom']) ?> - NGS (New Grace Service)</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .gradient-blue-btn {
            background: linear-gradient(90deg, #4F86F7 0%, #1A5A9C 100%);
            color: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-blue-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
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
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-inactive {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar-header,
        .sidebar-profile,
        .sidebar-footer {
            flex-shrink: 0;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }

        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            padding-left: 1.25rem;
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: var(--accent);
            border-radius: 0 4px 4px 0;
        }

        .main-content {
            height: 100vh;
            overflow-y: auto;
        }

        .mobile-menu-btn {
            transition: transform 0.3s ease;
        }

        .mobile-menu-btn.active {
            transform: rotate(90deg);
        }

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .badge-unite {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-metres {
            background-color: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
        }

        .badge-pieces {
            background-color: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }

        .badge-vente {
            background-color: #DCFCE7;
            color: #166534;
        }

        .badge-entree {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .badge-sortie {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .tab-button {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .tab-button.active {
            background: linear-gradient(90deg, #4F86F7 0%, #1A5A9C 100%);
            color: white;
        }

        .tab-button:not(.active) {
            background-color: #F3F4F6;
            color: #6B7280;
        }

        .tab-button:not(.active):hover {
            background-color: #E5E7EB;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
        }

        .badge-transfert-envoi {
            background-color: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }

        .badge-transfert-reception {
            background-color: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .badge-paiement {
            background-color: #DBEAFE;
            color: #1E40AF;
            border: 1px solid #BFDBFE;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-btn {
                width: 100%;
                justify-content: center;
            }

            .nav-link {
                padding: 0.75rem 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tab-button {
                padding: 8px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body class="font-inter min-h-screen bg-gray-50">
    <button id="mobileMenuButton" class="mobile-menu-btn md:hidden fixed top-4 left-4 z-50 p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-full shadow-lg hover:shadow-xl transition-shadow">
        <i class="fas fa-bars"></i>
    </button>

    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <div class="flex h-screen">
        <aside id="sidebar" class="sidebar w-64 gradient-bg text-white flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:sticky md:top-0 md:h-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:z-auto">
            <div class="sidebar-header p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center shadow-lg">
                        <span class="font-bold text-white text-lg font-display">NGS</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold">NGS</h1>
                        <p class="text-xs text-gray-300">New Grace Service - Dashboard Boutique</p>
                    </div>
                </div>
            </div>

            <div class="sidebar-profile p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-blue-500/20 border-2 border-blue-500/30 flex items-center justify-center relative">
                        <i class="fas fa-store text-blue-500 text-lg"></i>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-900"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold truncate"><?= htmlspecialchars($boutique_info['nom']) ?></h3>
                        <p class="text-sm text-gray-300 truncate"><?= htmlspecialchars($boutique_info['email'] ?? '') ?></p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav p-4 space-y-1">
                <a href="dashboard_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="stock_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-warehouse w-5 text-gray-300"></i>
                    <span>Mes stocks</span>
                </a>
                <a href="ventes_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-shopping-cart w-5 text-gray-300"></i>
                    <span>Ventes</span>
                </a>
                <a href="paiements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-money-bill-wave w-5 text-gray-300"></i>
                    <span>Paiements</span>
                </a>
                <a href="transferts-boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-truck-loading w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="mouvements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Mouvements Caisse</span>
                </a>
                <a href="rapports_boutique.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-chart-bar w-5 text-white"></i>
                    <span>Rapports</span>
                </a>
            </nav>

            <div class="sidebar-footer p-4 border-t border-white/10">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200 transition-colors">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <div class="main-content flex-1 overflow-y-auto">
            <header class="boutique-header gradient-bg p-4 md:p-6 sticky top-0 z-30 shadow-lg">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center space-x-3">
                            <div>
                                <h1 class="text-xl md:text-2xl font-bold text-white">Rapports - <?= htmlspecialchars($boutique_info['nom']) ?></h1>
                                <p class="text-gray-200 text-sm md:text-base">New Grace Service - Rapports analytiques de votre boutique</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <div class="flex items-center space-x-2 text-sm text-gray-200">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($boutique_info['email'] ?? 'Non spécifié') ?></span>
                            </div>
                            <div class="flex items-center space-x-2 text-sm text-gray-200">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Créée le <?= date('d/m/Y', strtotime($boutique_info['date_creation'])) ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="status-badge <?= $boutique_info['actif'] ? 'status-active' : 'status-inactive' ?>">
                                    <i class="fas fa-<?= $boutique_info['actif'] ? 'check-circle' : 'times-circle' ?> mr-1"></i>
                                    <?= $boutique_info['actif'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button onclick="refreshPage()"
                            class="px-4 py-2 bg-white/20 text-white rounded-lg hover:bg-white/30 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-sync-alt"></i>
                            <span>Actualiser</span>
                        </button>
                        <button onclick="exportRapports()"
                            class="px-4 py-2 gradient-blue-btn text-white rounded-lg flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-download"></i>
                            <span>Exporter</span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="p-4 md:p-6">
                <?php if ($message): ?>
                    <div class="mb-6 fade-in relative z-10 animate-fade-in">
                        <div class="
                            <?php if ($message_type === 'success'): ?>bg-green-50 text-green-700 border border-green-200
                            <?php elseif ($message_type === 'error'): ?>bg-red-50 text-red-700 border border-red-200
                            <?php elseif ($message_type === 'warning'): ?>bg-yellow-50 text-yellow-700 border border-yellow-200
                            <?php else: ?>bg-blue-50 text-blue-700 border border-blue-200<?php endif; ?>
                            rounded-xl p-4 flex items-center justify-between shadow-soft">
                            <div class="flex items-center space-x-3">
                                <?php if ($message_type === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                                <?php elseif ($message_type === 'error'): ?>
                                    <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
                                <?php elseif ($message_type === 'warning'): ?>
                                    <i class="fas fa-exclamation-triangle text-yellow-600 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-blue-600 text-lg"></i>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($message) ?></span>
                            </div>
                            <button onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filtres de période -->
                <div class="filter-card p-6 mb-6 rounded-2xl shadow-soft animate-fade-in">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-filter mr-2"></i>Filtres de période
                    </h3>
                    <form method="GET" class="space-y-4 md:space-y-0 md:flex md:items-end md:space-x-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 flex-1">
                            <div>
                                <label class="block text-sm font-medium text-white/90 mb-2">Période</label>
                                <select name="periode" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-white/50 bg-white/20 text-white placeholder-white/70" onchange="this.form.submit()">
                                    <option value="mois" <?= $periode === 'mois' ? 'selected' : '' ?>>Ce mois</option>
                                    <option value="semaine" <?= $periode === 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                                    <option value="jour" <?= $periode === 'jour' ? 'selected' : '' ?>>Aujourd'hui</option>
                                    <option value="personnalise" <?= $periode === 'personnalise' ? 'selected' : '' ?>>Personnalisé</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-white/90 mb-2">Date début</label>
                                <input type="date" name="date_debut" value="<?= $date_debut ?>"
                                    class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-white/50 bg-white/20 text-white"
                                    <?= $periode !== 'personnalise' ? 'disabled' : '' ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-white/90 mb-2">Date fin</label>
                                <input type="date" name="date_fin" value="<?= $date_fin ?>"
                                    class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-white/50 bg-white/20 text-white"
                                    <?= $periode !== 'personnalise' ? 'disabled' : '' ?>>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" class="px-6 py-3 bg-white text-purple-600 rounded-lg font-semibold hover:bg-white/90 transition-colors">
                                <i class="fas fa-search mr-2"></i>Appliquer
                            </button>
                            <a href="rapports_boutique.php" class="px-6 py-3 bg-white/20 text-white rounded-lg font-semibold hover:bg-white/30 transition-colors">
                                <i class="fas fa-redo mr-2"></i>Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Onglets -->
                <div class="mb-6 bg-white rounded-2xl shadow-soft p-4 animate-fade-in" style="animation-delay: 0.1s">
                    <div class="flex flex-wrap gap-2">
                        <button class="tab-button active" data-tab="stats">
                            <i class="fas fa-chart-pie mr-2"></i>Statistiques
                        </button>
                        <button class="tab-button" data-tab="stocks">
                            <i class="fas fa-warehouse mr-2"></i>Rapport Stocks
                        </button>
                        <button class="tab-button" data-tab="ventes">
                            <i class="fas fa-shopping-cart mr-2"></i>Rapport Ventes
                        </button>
                        <button class="tab-button" data-tab="caisse">
                            <i class="fas fa-exchange-alt mr-2"></i>Rapport Caisse
                        </button>
                        <button class="tab-button" data-tab="transferts">
                            <i class="fas fa-truck mr-2"></i>Transferts
                        </button>
                        <button class="tab-button" data-tab="paiements">
                            <i class="fas fa-money-bill-wave mr-2"></i>Paiements
                        </button>
                        <button class="tab-button" data-tab="alertes">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Alertes
                        </button>
                    </div>
                </div>

                <!-- Contenu des onglets -->

                <!-- Onglet Statistiques -->
                <div id="tab-stats" class="tab-content active">
                    <!-- Statistiques générales -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 stats-grid">
                        <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-boxes text-blue-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-blue-600">Valeur stock</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($valeur_stock, 2) ?> $</h3>
                            <p class="text-gray-600"><?= $nb_produits ?> produits en stock</p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-emerald-500 animate-fade-in" style="animation-delay: 0.1s">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                    <i class="fas fa-chart-line text-emerald-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-emerald-600">Chiffre d'affaires</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($chiffre_affaires, 2) ?> $</h3>
                            <p class="text-gray-600">Période: <?= date('d/m/Y', strtotime($date_debut)) ?> - <?= date('d/m/Y', strtotime($date_fin)) ?></p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-cyan-500 animate-fade-in" style="animation-delay: 0.2s">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-cyan-100 flex items-center justify-center">
                                    <i class="fas fa-weight-hanging text-cyan-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-cyan-600">Quantité totale</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($quantite_totale, 3) ?></h3>
                            <p class="text-gray-600">Unités en stock</p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.3s">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                    <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-purple-600">Solde caisse</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($solde_caisse, 2) ?> $</h3>
                            <p class="text-gray-600">Entrées: <?= number_format($total_entrees, 2) ?> $ | Sorties: <?= number_format($total_sorties, 2) ?> $</p>
                        </div>
                    </div>

                    <!-- Graphiques -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 animate-fade-in" style="animation-delay: 0.4s">
                        <!-- Graphique ventes -->
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                                Évolution des ventes
                            </h3>
                            <div class="chart-container">
                                <canvas id="ventesChart"></canvas>
                            </div>
                        </div>

                        <!-- Graphique mouvements caisse -->
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-exchange-alt text-purple-500 mr-2"></i>
                                Mouvements de caisse
                            </h3>
                            <div class="chart-container">
                                <canvas id="caisseChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques par unité -->
                    <?php if (!empty($stats_unite)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 animate-fade-in" style="animation-delay: 0.5s">
                            <?php foreach ($stats_unite as $stat): ?>
                                <div class="bg-white rounded-2xl shadow-soft p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-xl <?= $stat['umProduit'] == 'metres' ? 'bg-blue-100' : 'bg-emerald-100' ?> flex items-center justify-center">
                                                <i class="<?= $stat['umProduit'] == 'metres' ? 'fas fa-ruler-combined text-blue-600' : 'fas fa-cube text-emerald-600' ?>"></i>
                                            </div>
                                            <div>
                                                <h3 class="font-bold text-gray-900">
                                                    <?= $stat['umProduit'] == 'metres' ? 'Rideaux (mètres)' : 'Produits (pièces)' ?>
                                                </h3>
                                                <p class="text-sm text-gray-600">Statistiques détaillées</p>
                                            </div>
                                        </div>
                                        <span class="badge-unite <?= $stat['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                            <i class="<?= $stat['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1"></i>
                                            <?= $stat['umProduit'] == 'metres' ? 'Mètres' : 'Pièces' ?>
                                        </span>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">Nombre de produits:</span>
                                            <span class="font-bold"><?= $stat['nombre_produits'] ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">Quantité totale:</span>
                                            <span class="font-bold"><?= number_format($stat['quantite_totale'], 3) ?></span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">Valeur totale:</span>
                                            <span class="font-bold text-green-600"><?= number_format($stat['valeur_totale'], 2) ?> $</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Onglet Rapport Stocks -->
                <div id="tab-stocks" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden mb-6 animate-fade-in">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 mb-2 md:mb-0">
                                    <i class="fas fa-warehouse text-blue-500 mr-2"></i>
                                    Rapport détaillé des stocks
                                </h2>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600">
                                        <?= count($rapport_stocks) ?> produits en stock
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1000px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unité</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix moyen</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mouvements</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_stocks)): ?>
                                        <?php foreach ($rapport_stocks as $stock): ?>
                                            <?php
                                            $uniteClass = $stock['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces';
                                            $uniteText = $stock['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                            ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($stock['designation']) ?></div>
                                                        <div class="text-xs text-gray-500 font-mono mt-1">
                                                            <?= htmlspecialchars($stock['matricule']) ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="badge-unite <?= $uniteClass ?>">
                                                        <i class="<?= $stock['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1"></i>
                                                        <?= $uniteText ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-bold">
                                                        <?= number_format($stock['quantite_totale'], 3) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <div class="flex items-center">
                                                        <span class="text-sm font-medium">
                                                            <?= number_format($stock['prix_moyen'], 2) ?> $
                                                        </span>
                                                        <span class="text-xs text-gray-500 ml-2">
                                                            (<?= number_format($stock['prix_min'], 2) ?>-<?= number_format($stock['prix_max'], 2) ?>)
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-bold text-green-600">
                                                        <?= number_format($stock['valeur_totale'], 2) ?> $
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $stock['nombre_mouvements'] ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun stock enregistré</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Onglet Rapport Ventes -->
                <div id="tab-ventes" class="tab-content hidden">
                    <!-- Produits les plus vendus -->
                    <div class="mb-6 animate-fade-in">
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                                Top 10 des produits les plus vendus
                            </h3>
                            <div class="space-y-4">
                                <?php if (!empty($top_produits)): ?>
                                    <?php foreach ($top_produits as $index => $produit): ?>
                                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                            <div class="flex items-center space-x-3">
                                                <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                                                    <?= $index + 1 ?>
                                                </span>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($produit['designation']) ?></div>
                                                    <div class="flex items-center space-x-2 text-sm text-gray-500 mt-1">
                                                        <span class="badge-unite <?= $produit['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                                            <i class="<?= $produit['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1"></i>
                                                            <?= $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces' ?>
                                                        </span>
                                                        <span>•</span>
                                                        <span><?= $produit['nombre_commandes'] ?> commandes</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="font-bold text-green-600"><?= number_format($produit['chiffre_affaires_produit'], 2) ?> $</div>
                                                <div class="text-sm text-gray-500"><?= number_format($produit['quantite_vendue'], 3) ?> vendus</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-chart-line text-4xl mb-4"></i>
                                        <p>Aucune vente enregistrée sur cette période</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Détail des ventes par jour -->
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.1s">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                                Ventes par jour (30 derniers jours)
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[800px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commandes</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité vendue</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chiffre d'affaires</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Panier moyen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_ventes)): ?>
                                        <?php foreach ($rapport_ventes as $vente): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($vente['date_vente'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-medium"><?= $vente['nombre_commandes'] ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($vente['quantite_vendue'], 3) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-bold text-green-600">
                                                        <?= number_format($vente['chiffre_affaires'], 2) ?> $
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($vente['panier_moyen'], 2) ?> $
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                                <i class="fas fa-chart-line text-4xl mb-4"></i>
                                                <p>Aucune vente enregistrée sur cette période</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Onglet Rapport Caisse -->
                <div id="tab-caisse" class="tab-content hidden">
                    <!-- Balance de caisse -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 animate-fade-in">
                        <div class="bg-white rounded-2xl shadow-soft p-6 border-l-4 border-green-500">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-arrow-down text-green-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-green-600">Entrées</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($total_entrees, 2) ?> $</h3>
                            <p class="text-gray-600">Total des entrées de caisse</p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 border-l-4 border-red-500">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-arrow-up text-red-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-red-600">Sorties</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($total_sorties, 2) ?> $</h3>
                            <p class="text-gray-600">Total des sorties de caisse</p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 border-l-4 border-blue-500">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-balance-scale text-blue-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-blue-600">Solde</span>
                            </div>
                            <h3 class="text-3xl font-bold <?= $solde_caisse >= 0 ? 'text-green-600' : 'text-red-600' ?> mb-2">
                                <?= number_format($solde_caisse, 2) ?> $
                            </h3>
                            <p class="text-gray-600">Balance finale de caisse</p>
                        </div>
                    </div>

                    <!-- Détail des mouvements -->
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.1s">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-list-alt text-purple-500 mr-2"></i>
                                Détail des mouvements de caisse
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[800px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opérations</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_mouvements)): ?>
                                        <?php foreach ($rapport_mouvements as $mouvement): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($mouvement['date_mouvement'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="badge-<?= $mouvement['type_mouvement'] == 'entrée' ? 'entree' : 'sortie' ?>">
                                                        <i class="fas fa-<?= $mouvement['type_mouvement'] == 'entrée' ? 'arrow-down' : 'arrow-up' ?> mr-1"></i>
                                                        <?= ucfirst($mouvement['type_mouvement']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-bold <?= $mouvement['type_mouvement'] == 'entrée' ? 'text-green-600' : 'text-red-600' ?>">
                                                        <?= number_format($mouvement['montant_total'], 2) ?> $
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= $mouvement['nombre_operations'] ?> opérations
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                                <i class="fas fa-exchange-alt text-4xl mb-4"></i>
                                                <p>Aucun mouvement de caisse enregistré sur cette période</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Onglet Transferts -->
                <div id="tab-transferts" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden mb-6 animate-fade-in">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 mb-2 md:mb-0">
                                    <i class="fas fa-truck text-orange-500 mr-2"></i>
                                    Historique des transferts
                                </h2>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600">
                                        <?= count($rapport_transferts) ?> transferts
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1000px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutique source</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutique destination</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_transferts)): ?>
                                        <?php foreach ($rapport_transferts as $transfert): ?>
                                            <?php
                                            $estExpediteur = $transfert['boutique_expedition_id'] == $boutique_id;
                                            $typeTransfert = $estExpediteur ? 'Envoi' : 'Réception';
                                            $typeBadgeClass = $estExpediteur ? 'badge-transfert-envoi' : 'badge-transfert-reception';
                                            ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($transfert['date'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="badge-unite <?= $typeBadgeClass ?>">
                                                        <i class="<?= $estExpediteur ? 'fas fa-share-square' : 'fas fa-inbox' ?> mr-1"></i>
                                                        <?= $typeTransfert ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($transfert['designation']) ?></div>
                                                        <div class="text-xs text-gray-500">
                                                            <span class="badge-unite <?= $transfert['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                                                <i class="<?= $transfert['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1"></i>
                                                                <?= $transfert['umProduit'] == 'metres' ? 'mètres' : 'pièces' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($transfert['boutique_expedition']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($transfert['boutique_destination']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="status-badge <?= $transfert['statut'] == 0 ? 'status-active' : 'status-inactive' ?>">
                                                        <i class="fas fa-<?= $transfert['statut'] == 0 ? 'check-circle' : 'times-circle' ?> mr-1"></i>
                                                        <?= $transfert['statut'] == 0 ? 'Actif' : 'Supprimé' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                                <i class="fas fa-truck text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun transfert enregistré</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Onglet Paiements -->
                <div id="tab-paiements" class="tab-content hidden">
                    <!-- Statistiques paiements -->
                    <div class="mb-6 animate-fade-in">
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>
                                Statistiques des paiements
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-blue-600 font-bold text-2xl"><?= count($rapport_paiements) ?></div>
                                    <div class="text-blue-800">Nombre de paiements</div>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="text-green-600 font-bold text-2xl"><?= number_format($total_paiements, 2) ?> $</div>
                                    <div class="text-green-800">Total des paiements</div>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <div class="text-purple-600 font-bold text-2xl">
                                        <?= count($rapport_paiements) > 0 ? number_format($total_paiements / count($rapport_paiements), 2) : '0.00' ?> $
                                    </div>
                                    <div class="text-purple-800">Moyenne par paiement</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Détail des paiements -->
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.1s">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-list-alt text-green-500 mr-2"></i>
                                Détail des paiements
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[800px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facture</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_paiements)): ?>
                                        <?php foreach ($rapport_paiements as $paiement): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($paiement['date'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-medium"><?= htmlspecialchars($paiement['numero_facture']) ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($paiement['client_nom']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-bold text-green-600">
                                                        <?= number_format($paiement['montant'], 2) ?> $
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="status-badge <?= $paiement['statut'] == 0 ? 'status-active' : 'status-inactive' ?>">
                                                        <i class="fas fa-<?= $paiement['statut'] == 0 ? 'check-circle' : 'times-circle' ?> mr-1"></i>
                                                        <?= $paiement['statut'] == 0 ? 'Actif' : 'Supprimé' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                                <i class="fas fa-money-bill-wave text-4xl mb-4"></i>
                                                <p>Aucun paiement enregistré sur cette période</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Onglet Alertes -->
                <div id="tab-alertes" class="tab-content hidden">
                    <!-- Alertes stock faible -->
                    <div class="mb-6 animate-fade-in">
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                Alertes stock faible
                            </h3>
                            <?php if (!empty($alertes_stock)): ?>
                                <div class="space-y-3">
                                    <?php foreach ($alertes_stock as $alerte): ?>
                                        <div class="flex items-center justify-between p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                                    <i class="fas fa-exclamation text-yellow-600"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($alerte['designation']) ?></div>
                                                    <div class="text-sm text-yellow-700">
                                                        Stock actuel: <span class="font-bold"><?= number_format($alerte['quantite'], 3) ?></span> |
                                                        Seuil: <span class="font-bold"><?= $alerte['seuil_alerte_stock'] ?></span> |
                                                        Unité: <span class="badge-unite <?= $alerte['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                                            <?= $alerte['umProduit'] == 'metres' ? 'mètres' : 'pièces' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="text-sm text-yellow-600">
                                                Dernier approvisionnement: <?= date('d/m/Y', strtotime($alerte['date_creation'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-check-circle text-4xl mb-4 text-green-500"></i>
                                    <p>Aucune alerte de stock faible</p>
                                    <p class="text-sm mt-2">Tous vos produits sont au-dessus du seuil d'alerte.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // --- GESTION DE LA SIDEBAR MOBILE ---
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
            mobileMenuButton.classList.toggle('active');
        }

        mobileMenuButton.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // --- GESTION DES LIENS ACTIFS ---
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // --- GESTION DES ONGLETS ---
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Désactiver tous les boutons
                tabButtons.forEach(btn => btn.classList.remove('active'));
                // Activer le bouton cliqué
                button.classList.add('active');

                // Cacher tous les contenus
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    content.classList.add('hidden');
                });

                // Afficher le contenu correspondant
                const tabId = button.getAttribute('data-tab');
                const tabContent = document.getElementById(`tab-${tabId}`);
                if (tabContent) {
                    tabContent.classList.remove('hidden');
                    tabContent.classList.add('active');
                }
            });
        });

        // --- FONCTION DE RAFRAÎCHISSEMENT ---
        function refreshPage() {
            const button = event.target.closest('button');
            if (button) {
                button.classList.add('animate-spin');
                setTimeout(() => {
                    button.classList.remove('animate-spin');
                    window.location.reload();
                }, 500);
            }
        }

        // --- FONCTION D'EXPORT ---
        function exportRapports() {
            const tabActive = document.querySelector('.tab-button.active');
            const tabId = tabActive ? tabActive.getAttribute('data-tab') : 'stats';

            let exportUrl = `export_rapports.php?tab=${tabId}&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&boutique_id=<?= $boutique_id ?>`;

            // Ouvrir dans un nouvel onglet pour téléchargement
            window.open(exportUrl, '_blank');
        }

        // --- GRAPHIQUES ---
        document.addEventListener('DOMContentLoaded', function() {
            // Données pour le graphique des ventes
            const ventesData = {
                labels: [
                    <?php foreach ($rapport_ventes as $vente): ?> '<?= date('d/m', strtotime($vente['date_vente'])) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Chiffre d\'affaires ($)',
                    data: [
                        <?php foreach ($rapport_ventes as $vente): ?>
                            <?= $vente['chiffre_affaires'] ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            };

            // Créer le graphique des ventes
            const ventesCtx = document.getElementById('ventesChart');
            if (ventesCtx) {
                new Chart(ventesCtx, {
                    type: 'line',
                    data: ventesData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return value.toLocaleString('fr-FR', {
                                            minimumFractionDigits: 2
                                        }) + ' $';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Données pour le graphique de caisse
            <?php
            // Grouper les mouvements par date
            $mouvements_par_date = [];
            foreach ($rapport_mouvements as $mouvement) {
                $date = $mouvement['date_mouvement'];
                if (!isset($mouvements_par_date[$date])) {
                    $mouvements_par_date[$date] = ['entrée' => 0, 'sortie' => 0];
                }
                $mouvements_par_date[$date][$mouvement['type_mouvement']] = $mouvement['montant_total'];
            }

            // Trier par date et limiter
            krsort($mouvements_par_date);
            $mouvements_par_date = array_slice($mouvements_par_date, 0, 15, true);
            ?>

            const caisseData = {
                labels: [
                    <?php foreach ($mouvements_par_date as $date => $mouvements): ?> '<?= date('d/m', strtotime($date)) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Entrées',
                    data: [
                        <?php foreach ($mouvements_par_date as $mouvements): ?>
                            <?= $mouvements['entrée'] ?? 0 ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#10b981',
                    borderColor: '#10b981',
                    borderWidth: 1
                }, {
                    label: 'Sorties',
                    data: [
                        <?php foreach ($mouvements_par_date as $mouvements): ?>
                            <?= $mouvements['sortie'] ?? 0 ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#ef4444',
                    borderColor: '#ef4444',
                    borderWidth: 1
                }]
            };

            // Créer le graphique de caisse
            const caisseCtx = document.getElementById('caisseChart');
            if (caisseCtx) {
                new Chart(caisseCtx, {
                    type: 'bar',
                    data: caisseData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                stacked: false,
                                ticks: {
                                    callback: function(value) {
                                        return value.toLocaleString('fr-FR', {
                                            minimumFractionDigits: 2
                                        }) + ' $';
                                    }
                                }
                            },
                            x: {
                                stacked: false
                            }
                        }
                    }
                });
            }
        });

        // --- GESTION DES FILTRES ---
        document.querySelector('select[name="periode"]').addEventListener('change', function() {
            const dateDebut = document.querySelector('input[name="date_debut"]');
            const dateFin = document.querySelector('input[name="date_fin"]');

            if (this.value === 'personnalise') {
                dateDebut.disabled = false;
                dateFin.disabled = false;
            } else {
                dateDebut.disabled = true;
                dateFin.disabled = true;
            }
        });

        // --- NAVIGATION CLAVIER ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });
    </script>
</body>

</html>