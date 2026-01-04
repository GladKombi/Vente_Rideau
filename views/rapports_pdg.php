<?php
# Connexion à la base de données
include '../connexion/connexion.php';

// Vérification de l'authentification PDG
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'PDG') {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de l'utilisateur PDG connecté
$pdg_id = $_SESSION['user_id'] ?? null;
if (!$pdg_id) {
    header('Location: ../login.php');
    exit;
}

// Initialisation des variables
$message = '';
$message_type = '';

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer les dates de filtrage
$date_debut = $_GET['date_debut'] ?? date('Y-m-01'); // Début du mois par défaut
$date_fin = $_GET['date_fin'] ?? date('Y-m-t'); // Fin du mois par défaut
$periode = $_GET['periode'] ?? 'mois'; // mois, semaine, jour, personnalise
$boutique_id = $_GET['boutique_id'] ?? 'all'; // ID de boutique spécifique ou 'all' pour toutes

// Ajuster les dates selon la période
if ($periode === 'semaine') {
    $date_debut = date('Y-m-d', strtotime('monday this week'));
    $date_fin = date('Y-m-d', strtotime('sunday this week'));
} elseif ($periode === 'jour') {
    $date_debut = date('Y-m-d');
    $date_fin = date('Y-m-d');
}

// Initialiser les tableaux de données
$rapport_boutiques = [];
$rapport_ventes_globales = [];
$rapport_mouvements_globaux = [];
$top_produits_global = [];
$rapport_transferts_global = [];
$rapport_paiements_global = [];

// Variables de statistiques globales
$valeur_stock_global = 0;
$chiffre_affaires_global = 0;
$total_entrees_global = 0;
$total_sorties_global = 0;
$solde_caisse_global = 0;
$nb_produits_global = 0;
$quantite_totale_global = 0;
$total_paiements_global = 0;
$total_commandes_global = 0;

try {
    // --- RÉCUPÉRER TOUTES LES BOUTIQUES ---
    $queryBoutiques = $pdo->prepare("
        SELECT id, nom, email, date_creation, actif 
        FROM boutiques 
        WHERE statut = 0
        ORDER BY nom
    ");
    $queryBoutiques->execute();
    $boutiques = $queryBoutiques->fetchAll(PDO::FETCH_ASSOC);

    // --- STATISTIQUES GLOBALES POUR TOUTES LES BOUTIQUES ---
    // Préparer la clause WHERE pour les requêtes simples
    $where_boutique_simple = $boutique_id !== 'all' ? "WHERE s.boutique_id = ? AND s.statut = 0 AND s.quantite > 0" : "WHERE s.statut = 0 AND s.quantite > 0";
    $params_boutique = $boutique_id !== 'all' ? [$boutique_id] : [];

    // 1. Valeur totale du stock (toutes les boutiques ou une spécifique)
    $queryValeurStockGlobal = $pdo->prepare("
        SELECT SUM(s.quantite * s.prix) as valeur_stock_global
        FROM stock s
        $where_boutique_simple
    ");
    $queryValeurStockGlobal->execute($params_boutique);
    $valeur_stock_global = $queryValeurStockGlobal->fetchColumn() ?? 0;

    // 2. Chiffre d'affaires global
    $queryCAGlobal = $pdo->prepare("
        SELECT SUM(cp.quantite * cp.prix_unitaire) as ca_global
        FROM commande_produits cp
        JOIN commandes c ON cp.commande_id = c.id
        JOIN stock s ON cp.stock_id = s.id
        WHERE " . ($boutique_id !== 'all' ? "s.boutique_id = ? AND " : "") . "
        cp.statut = 0
        AND c.statut = 0
        AND c.etat = 'payee'
        AND DATE(c.date_commande) BETWEEN ? AND ?
    ");
    $params_ca = $boutique_id !== 'all' ? [$boutique_id, $date_debut, $date_fin] : [$date_debut, $date_fin];
    $queryCAGlobal->execute($params_ca);
    $chiffre_affaires_global = $queryCAGlobal->fetchColumn() ?? 0;

    // 3. Total entrées/sorties caisse global
    $queryCaisseGlobal = $pdo->prepare("
        SELECT 
            mc.type_mouvement,
            SUM(mc.montant) as total
        FROM mouvement_caisse mc
        WHERE " . ($boutique_id !== 'all' ? "mc.id_boutique = ? AND " : "") . "
        DATE(mc.date_mouvement) BETWEEN ? AND ?
        AND mc.statut = 0
        GROUP BY mc.type_mouvement
    ");
    $params_caisse = $boutique_id !== 'all' ? [$boutique_id, $date_debut, $date_fin] : [$date_debut, $date_fin];
    $queryCaisseGlobal->execute($params_caisse);
    $totaux_caisse_global = [];
    while ($row = $queryCaisseGlobal->fetch(PDO::FETCH_ASSOC)) {
        $totaux_caisse_global[$row['type_mouvement']] = $row['total'];
    }

    $total_entrees_global = $totaux_caisse_global['entrée'] ?? 0;
    $total_sorties_global = $totaux_caisse_global['sortie'] ?? 0;
    $solde_caisse_global = $total_entrees_global - $total_sorties_global;

    // 4. Nombre total de produits différents
    $queryNbProduitsGlobal = $pdo->prepare("
        SELECT COUNT(DISTINCT s.produit_matricule) as nb_produits_global
        FROM stock s
        $where_boutique_simple
    ");
    $queryNbProduitsGlobal->execute($params_boutique);
    $nb_produits_global = $queryNbProduitsGlobal->fetchColumn() ?? 0;

    // 5. Quantité totale en stock
    $queryQteStockGlobal = $pdo->prepare("
        SELECT SUM(s.quantite) as quantite_totale_global
        FROM stock s
        $where_boutique_simple
    ");
    $queryQteStockGlobal->execute($params_boutique);
    $quantite_totale_global = $queryQteStockGlobal->fetchColumn() ?? 0;

    // 6. Total des commandes
    $queryCommandesGlobal = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as total_commandes
        FROM commandes c
        WHERE " . ($boutique_id !== 'all' ? "c.boutique_id = ? AND " : "") . "
        c.statut = 0
        AND DATE(c.date_commande) BETWEEN ? AND ?
    ");
    $params_commandes = $boutique_id !== 'all' ? [$boutique_id, $date_debut, $date_fin] : [$date_debut, $date_fin];
    $queryCommandesGlobal->execute($params_commandes);
    $total_commandes_global = $queryCommandesGlobal->fetchColumn() ?? 0;

    // --- RAPPORTS PAR BOUTIQUE ---
    $rapport_boutiques = [];
    foreach ($boutiques as $boutique) {
        $boutique_id_current = $boutique['id'];
        
        // Valeur du stock par boutique
        $queryStockBoutique = $pdo->prepare("
            SELECT SUM(quantite * prix) as valeur_stock
            FROM stock
            WHERE boutique_id = ?
            AND statut = 0
            AND quantite > 0
        ");
        $queryStockBoutique->execute([$boutique_id_current]);
        $valeur_stock_boutique = $queryStockBoutique->fetchColumn() ?? 0;
        
        // Chiffre d'affaires par boutique
        $queryCABoutique = $pdo->prepare("
            SELECT SUM(cp.quantite * cp.prix_unitaire) as chiffre_affaires
            FROM commande_produits cp
            JOIN commandes c ON cp.commande_id = c.id
            WHERE c.boutique_id = ?
            AND cp.statut = 0
            AND c.statut = 0
            AND c.etat = 'payee'
            AND DATE(c.date_commande) BETWEEN ? AND ?
        ");
        $queryCABoutique->execute([$boutique_id_current, $date_debut, $date_fin]);
        $chiffre_affaires_boutique = $queryCABoutique->fetchColumn() ?? 0;
        
        // Mouvements de caisse par boutique
        $queryCaisseBoutique = $pdo->prepare("
            SELECT 
                type_mouvement,
                SUM(montant) as total
            FROM mouvement_caisse
            WHERE id_boutique = ?
            AND DATE(date_mouvement) BETWEEN ? AND ?
            AND statut = 0
            GROUP BY type_mouvement
        ");
        $queryCaisseBoutique->execute([$boutique_id_current, $date_debut, $date_fin]);
        $totaux_caisse_boutique = [];
        while ($row = $queryCaisseBoutique->fetch(PDO::FETCH_ASSOC)) {
            $totaux_caisse_boutique[$row['type_mouvement']] = $row['total'];
        }
        
        $entrees_boutique = $totaux_caisse_boutique['entrée'] ?? 0;
        $sorties_boutique = $totaux_caisse_boutique['sortie'] ?? 0;
        $solde_boutique = $entrees_boutique - $sorties_boutique;
        
        // Nombre de commandes par boutique
        $queryCommandesBoutique = $pdo->prepare("
            SELECT COUNT(id) as nombre_commandes
            FROM commandes
            WHERE boutique_id = ?
            AND statut = 0
            AND DATE(date_commande) BETWEEN ? AND ?
        ");
        $queryCommandesBoutique->execute([$boutique_id_current, $date_debut, $date_fin]);
        $nombre_commandes_boutique = $queryCommandesBoutique->fetchColumn() ?? 0;
        
        $rapport_boutiques[] = [
            'id' => $boutique_id_current,
            'nom' => $boutique['nom'],
            'email' => $boutique['email'],
            'actif' => $boutique['actif'],
            'date_creation' => $boutique['date_creation'],
            'valeur_stock' => $valeur_stock_boutique,
            'chiffre_affaires' => $chiffre_affaires_boutique,
            'entrees_caisse' => $entrees_boutique,
            'sorties_caisse' => $sorties_boutique,
            'solde_caisse' => $solde_boutique,
            'nombre_commandes' => $nombre_commandes_boutique
        ];
    }

    // --- TOP 10 DES PRODUITS LES PLUS VENDUS GLOBALEMENT ---
    $queryTopProduitsGlobal = $pdo->prepare("
        SELECT 
            p.designation,
            p.umProduit,
            SUM(cp.quantite) as quantite_vendue,
            SUM(cp.quantite * cp.prix_unitaire) as chiffre_affaires_produit,
            COUNT(DISTINCT c.id) as nombre_commandes,
            COUNT(DISTINCT c.boutique_id) as nombre_boutiques
        FROM commande_produits cp
        JOIN commandes c ON cp.commande_id = c.id
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE cp.statut = 0
        AND c.statut = 0
        AND c.etat = 'payee'
        AND DATE(c.date_commande) BETWEEN ? AND ?
        GROUP BY p.designation, p.umProduit
        ORDER BY quantite_vendue DESC
        LIMIT 10
    ");
    $queryTopProduitsGlobal->execute([$date_debut, $date_fin]);
    $top_produits_global = $queryTopProduitsGlobal->fetchAll(PDO::FETCH_ASSOC);

    // --- VENTES PAR JOUR (GLOBAL) ---
    $queryVentesGlobal = $pdo->prepare("
        SELECT 
            DATE(c.date_commande) as date_vente,
            COUNT(DISTINCT c.id) as nombre_commandes,
            SUM(cp.quantite) as quantite_vendue,
            SUM(cp.quantite * cp.prix_unitaire) as chiffre_affaires,
            COUNT(DISTINCT c.boutique_id) as nombre_boutiques
        FROM commande_produits cp
        JOIN commandes c ON cp.commande_id = c.id
        WHERE cp.statut = 0
        AND c.statut = 0
        AND c.etat = 'payee'
        AND DATE(c.date_commande) BETWEEN ? AND ?
        GROUP BY DATE(c.date_commande)
        ORDER BY date_vente DESC
        LIMIT 30
    ");
    $queryVentesGlobal->execute([$date_debut, $date_fin]);
    $rapport_ventes_globales = $queryVentesGlobal->fetchAll(PDO::FETCH_ASSOC);

    // --- MOUVEMENTS DE CAISSE PAR JOUR (GLOBAL) ---
    $queryMouvementsGlobal = $pdo->prepare("
        SELECT 
            DATE(mc.date_mouvement) as date_mouvement,
            mc.type_mouvement,
            SUM(mc.montant) as montant_total,
            COUNT(mc.id) as nombre_operations,
            COUNT(DISTINCT mc.id_boutique) as nombre_boutiques
        FROM mouvement_caisse mc
        WHERE DATE(mc.date_mouvement) BETWEEN ? AND ?
        AND mc.statut = 0
        GROUP BY DATE(mc.date_mouvement), mc.type_mouvement
        ORDER BY date_mouvement DESC
        LIMIT 30
    ");
    $queryMouvementsGlobal->execute([$date_debut, $date_fin]);
    $rapport_mouvements_globaux = $queryMouvementsGlobal->fetchAll(PDO::FETCH_ASSOC);

    // --- TRANSFERTS GLOBAUX ---
    $queryTransfertsGlobal = $pdo->prepare("
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
        WHERE t.statut = 0
        AND DATE(t.date) BETWEEN ? AND ?
        ORDER BY t.date DESC
        LIMIT 50
    ");
    $queryTransfertsGlobal->execute([$date_debut, $date_fin]);
    $rapport_transferts_global = $queryTransfertsGlobal->fetchAll(PDO::FETCH_ASSOC);

    // --- PAIEMENTS GLOBAUX ---
    $queryPaiementsGlobal = $pdo->prepare("
        SELECT 
            p.id,
            p.date,
            p.montant,
            c.numero_facture,
            c.client_nom,
            b.nom as boutique_nom,
            p.statut
        FROM paiements p
        JOIN commandes c ON p.commandes_id = c.id
        JOIN boutiques b ON c.boutique_id = b.id
        WHERE p.statut = 0
        AND DATE(p.date) BETWEEN ? AND ?
        ORDER BY p.date DESC
        LIMIT 50
    ");
    $queryPaiementsGlobal->execute([$date_debut, $date_fin]);
    $rapport_paiements_global = $queryPaiementsGlobal->fetchAll(PDO::FETCH_ASSOC);

    // Total des paiements
    $total_paiements_global = array_sum(array_column($rapport_paiements_global, 'montant'));

    // --- STATISTIQUES PAR UNITÉ DE MESURE ---
    $queryStatsUniteGlobal = $pdo->prepare("
        SELECT 
            p.umProduit,
            COUNT(DISTINCT s.produit_matricule) as nombre_produits,
            SUM(s.quantite) as quantite_totale,
            SUM(s.quantite * s.prix) as valeur_totale
        FROM stock s
        JOIN produits p ON s.produit_matricule = p.matricule
        $where_boutique_simple
        GROUP BY p.umProduit
    ");
    $queryStatsUniteGlobal->execute($params_boutique);
    $stats_unite_global = $queryStatsUniteGlobal->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Rapports PDG - NGS (New Grace Service)</title>

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
                        <p class="text-xs text-gray-300">New Grace Service - Dashboard PDG</p>
                    </div>
                </div>
            </div>

            <div class="sidebar-profile p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-blue-500/20 border-2 border-blue-500/30 flex items-center justify-center relative">
                        <i class="fas fa-crown text-yellow-500 text-lg"></i>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-900"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold truncate">Directeur Général</h3>
                        <p class="text-sm text-gray-300 truncate">Tableau de bord PDG</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav p-4 space-y-1">
                <a href="dashboard_pdg.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="gestion_boutiques.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-store w-5 text-gray-300"></i>
                    <span>Gestion Boutiques</span>
                </a>
                <a href="gestion_utilisateurs.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users w-5 text-gray-300"></i>
                    <span>Gestion Utilisateurs</span>
                </a>
                <a href="gestion_produits.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-boxes w-5 text-gray-300"></i>
                    <span>Gestion Produits</span>
                </a>
                <a href="transferts.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-truck-loading w-5 text-gray-300"></i>
                    <span>Transferts</span>
                </a>
                <a href="rapports_pdg.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-chart-bar w-5 text-white"></i>
                    <span>Rapports PDG</span>
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
            <header class="gradient-bg p-4 md:p-6 sticky top-0 z-30 shadow-lg">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center space-x-3">
                            <div>
                                <h1 class="text-xl md:text-2xl font-bold text-white">Rapports PDG - Vue Globale</h1>
                                <p class="text-gray-200 text-sm md:text-base">New Grace Service - Rapports analytiques de toutes les boutiques</p>
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
                        <i class="fas fa-filter mr-2"></i>Filtres de période et boutique
                    </h3>
                    <form method="GET" class="space-y-4 md:space-y-0 md:flex md:items-end md:space-x-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 flex-1">
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
                                <label class="block text-sm font-medium text-white/90 mb-2">Boutique</label>
                                <select name="boutique_id" class="w-full px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-white/50 bg-white/20 text-white placeholder-white/70">
                                    <option value="all" <?= $boutique_id === 'all' ? 'selected' : '' ?>>Toutes les boutiques</option>
                                    <?php foreach ($boutiques as $boutique): ?>
                                        <option value="<?= $boutique['id'] ?>" <?= $boutique_id == $boutique['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($boutique['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
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
                            <a href="rapports_pdg.php" class="px-6 py-3 bg-white/20 text-white rounded-lg font-semibold hover:bg-white/30 transition-colors">
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
                        <button class="tab-button" data-tab="boutiques">
                            <i class="fas fa-store mr-2"></i>Performance Boutiques
                        </button>
                        <button class="tab-button" data-tab="ventes">
                            <i class="fas fa-shopping-cart mr-2"></i>Ventes Globales
                        </button>
                        <button class="tab-button" data-tab="transferts">
                            <i class="fas fa-truck mr-2"></i>Transferts
                        </button>
                        <button class="tab-button" data-tab="paiements">
                            <i class="fas fa-money-bill-wave mr-2"></i>Paiements
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
                                <span class="text-sm font-medium text-blue-600">Valeur stock global</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($valeur_stock_global, 2) ?> $</h3>
                            <p class="text-gray-600"><?= $nb_produits_global ?> produits en stock</p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-emerald-500 animate-fade-in" style="animation-delay: 0.1s">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                    <i class="fas fa-chart-line text-emerald-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-emerald-600">Chiffre d'affaires global</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($chiffre_affaires_global, 2) ?> $</h3>
                            <p class="text-gray-600"><?= $total_commandes_global ?> commandes</p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-cyan-500 animate-fade-in" style="animation-delay: 0.2s">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-cyan-100 flex items-center justify-center">
                                    <i class="fas fa-weight-hanging text-cyan-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-cyan-600">Quantité totale</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($quantite_totale_global, 3) ?></h3>
                            <p class="text-gray-600">Unités en stock global</p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.3s">
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                    <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                                </div>
                                <span class="text-sm font-medium text-purple-600">Solde caisse global</span>
                            </div>
                            <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($solde_caisse_global, 2) ?> $</h3>
                            <p class="text-gray-600">Entrées: <?= number_format($total_entrees_global, 2) ?> $ | Sorties: <?= number_format($total_sorties_global, 2) ?> $</p>
                        </div>
                    </div>

                    <!-- Graphiques -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 animate-fade-in" style="animation-delay: 0.4s">
                        <!-- Graphique performance boutiques -->
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-store text-blue-500 mr-2"></i>
                                Performance par boutique (CA)
                            </h3>
                            <div class="chart-container">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>

                        <!-- Graphique ventes globales -->
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-chart-line text-green-500 mr-2"></i>
                                Évolution des ventes globales
                            </h3>
                            <div class="chart-container">
                                <canvas id="ventesGlobalesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Statistiques par unité de mesure -->
                    <?php if (!empty($stats_unite_global)): ?>
                        <div class="mb-6 animate-fade-in" style="animation-delay: 0.5s">
                            <div class="bg-white rounded-2xl shadow-soft p-6">
                                <h3 class="text-lg font-bold text-gray-900 mb-4">
                                    <i class="fas fa-balance-scale text-gray-500 mr-2"></i>
                                    Répartition par unité de mesure
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <?php foreach ($stats_unite_global as $stat): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-10 h-10 rounded-xl <?= $stat['umProduit'] == 'metres' ? 'bg-blue-100' : 'bg-emerald-100' ?> flex items-center justify-center">
                                                        <i class="<?= $stat['umProduit'] == 'metres' ? 'fas fa-ruler-combined text-blue-600' : 'fas fa-cube text-emerald-600' ?>"></i>
                                                    </div>
                                                    <div>
                                                        <h3 class="font-bold text-gray-900">
                                                            <?= $stat['umProduit'] == 'metres' ? 'Rideaux (mètres)' : 'Produits (pièces)' ?>
                                                        </h3>
                                                    </div>
                                                </div>
                                                <span class="badge-unite <?= $stat['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                                    <?= $stat['umProduit'] == 'metres' ? 'Mètres' : 'Pièces' ?>
                                                </span>
                                            </div>
                                            <div class="space-y-2">
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Produits:</span>
                                                    <span class="font-bold"><?= $stat['nombre_produits'] ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Quantité:</span>
                                                    <span class="font-bold"><?= number_format($stat['quantite_totale'], 3) ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span class="text-gray-600">Valeur:</span>
                                                    <span class="font-bold text-green-600"><?= number_format($stat['valeur_totale'], 2) ?> $</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Onglet Performance Boutiques -->
                <div id="tab-boutiques" class="tab-content hidden">
                    <!-- Tableau des performances par boutique -->
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden mb-6 animate-fade-in">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 mb-2 md:mb-0">
                                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                                    Performance détaillée par boutique
                                </h2>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600">
                                        <?= count($rapport_boutiques) ?> boutiques
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1200px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutique</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commandes</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur Stock</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chiffre d'Affaires</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entrées Caisse</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sorties Caisse</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solde Caisse</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_boutiques)): ?>
                                        <?php foreach ($rapport_boutiques as $boutique): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                                            <i class="fas fa-store text-blue-600 text-sm"></i>
                                                        </div>
                                                        <div>
                                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($boutique['nom']) ?></div>
                                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($boutique['email']) ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="status-badge <?= $boutique['actif'] ? 'status-active' : 'status-inactive' ?>">
                                                        <i class="fas fa-<?= $boutique['actif'] ? 'check-circle' : 'times-circle' ?> mr-1"></i>
                                                        <?= $boutique['actif'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-medium"><?= $boutique['nombre_commandes'] ?></span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-medium"><?= number_format($boutique['valeur_stock'], 2) ?> $</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-bold text-green-600"><?= number_format($boutique['chiffre_affaires'], 2) ?> $</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="text-green-600"><?= number_format($boutique['entrees_caisse'], 2) ?> $</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="text-red-600"><?= number_format($boutique['sorties_caisse'], 2) ?> $</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-bold <?= $boutique['solde_caisse'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                                        <?= number_format($boutique['solde_caisse'], 2) ?> $
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                                <i class="fas fa-store-slash text-4xl mb-4"></i>
                                                <p class="text-lg">Aucune boutique trouvée</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top 5 des boutiques par CA -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in" style="animation-delay: 0.1s">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">
                            <i class="fas fa-medal text-yellow-500 mr-2"></i>
                            Top 5 des boutiques par chiffre d'affaires
                        </h3>
                        <div class="space-y-4">
                            <?php 
                            // Trier les boutiques par CA décroissant
                            usort($rapport_boutiques, function($a, $b) {
                                return $b['chiffre_affaires'] <=> $a['chiffre_affaires'];
                            });
                            $top_5_boutiques = array_slice($rapport_boutiques, 0, 5);
                            ?>
                            <?php if (!empty($top_5_boutiques)): ?>
                                <?php foreach ($top_5_boutiques as $index => $boutique): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                        <div class="flex items-center space-x-3">
                                            <span class="w-8 h-8 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center font-bold">
                                                <?= $index + 1 ?>
                                            </span>
                                            <div>
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($boutique['nom']) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($boutique['email']) ?></div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-green-600"><?= number_format($boutique['chiffre_affaires'], 2) ?> $</div>
                                            <div class="text-sm text-gray-500"><?= $boutique['nombre_commandes'] ?> commandes</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-chart-line text-4xl mb-4"></i>
                                    <p>Aucune donnée de vente disponible</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Onglet Ventes Globales -->
                <div id="tab-ventes" class="tab-content hidden">
                    <!-- Top produits global -->
                    <div class="mb-6 animate-fade-in">
                        <div class="bg-white rounded-2xl shadow-soft p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">
                                <i class="fas fa-star text-yellow-500 mr-2"></i>
                                Top 10 des produits les plus vendus (global)
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[800px]">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unité</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité vendue</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chiffre d'affaires</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commandes</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutiques</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (!empty($top_produits_global)): ?>
                                            <?php foreach ($top_produits_global as $index => $produit): ?>
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold mr-3">
                                                                <?= $index + 1 ?>
                                                            </span>
                                                            <div class="font-medium text-gray-900"><?= htmlspecialchars($produit['designation']) ?></div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="badge-unite <?= $produit['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                                            <i class="<?= $produit['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1"></i>
                                                            <?= $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces' ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <span class="font-bold"><?= number_format($produit['quantite_vendue'], 3) ?></span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <span class="font-bold text-green-600"><?= number_format($produit['chiffre_affaires_produit'], 2) ?> $</span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= $produit['nombre_commandes'] ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= $produit['nombre_boutiques'] ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
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

                    <!-- Détail des ventes par jour -->
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.1s">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-calendar-alt text-blue-500 mr-2"></i>
                                Ventes globales par jour (30 derniers jours)
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[800px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutiques</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commandes</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité vendue</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chiffre d'affaires</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_ventes_globales)): ?>
                                        <?php foreach ($rapport_ventes_globales as $vente): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($vente['date_vente'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="font-medium"><?= $vente['nombre_boutiques'] ?></span>
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

                <!-- Onglet Transferts -->
                <div id="tab-transferts" class="tab-content hidden">
                    <div class="bg-white rounded-2xl shadow-soft overflow-hidden mb-6 animate-fade-in">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                <h2 class="text-lg font-semibold text-gray-900 mb-2 md:mb-0">
                                    <i class="fas fa-truck text-orange-500 mr-2"></i>
                                    Historique des transferts globaux
                                </h2>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600">
                                        <?= count($rapport_transferts_global) ?> transferts
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1000px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unité</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expéditeur</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinataire</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_transferts_global)): ?>
                                        <?php foreach ($rapport_transferts_global as $transfert): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('d/m/Y', strtotime($transfert['date'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($transfert['designation']) ?></div>
                                                        <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($transfert['produit_matricule']) ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="badge-unite <?= $transfert['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                                        <i class="<?= $transfert['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1"></i>
                                                        <?= $transfert['umProduit'] == 'metres' ? 'mètres' : 'pièces' ?>
                                                    </span>
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
                                Statistiques des paiements globaux
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-blue-600 font-bold text-2xl"><?= count($rapport_paiements_global) ?></div>
                                    <div class="text-blue-800">Nombre de paiements</div>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="text-green-600 font-bold text-2xl"><?= number_format($total_paiements_global, 2) ?> $</div>
                                    <div class="text-green-800">Total des paiements</div>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <div class="text-purple-600 font-bold text-2xl">
                                        <?= count($rapport_paiements_global) > 0 ? number_format($total_paiements_global / count($rapport_paiements_global), 2) : '0.00' ?> $
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
                                Détail des paiements globaux
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[800px]">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facture</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Boutique</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($rapport_paiements_global)): ?>
                                        <?php foreach ($rapport_paiements_global as $paiement): ?>
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
                                                    <span class="font-medium"><?= htmlspecialchars($paiement['boutique_nom']) ?></span>
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
                                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
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

            let exportUrl = `export_rapports_pdg.php?tab=${tabId}&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&boutique_id=<?= $boutique_id ?>`;
            window.open(exportUrl, '_blank');
        }

        // --- GRAPHIQUES ---
        document.addEventListener('DOMContentLoaded', function() {
            // Données pour le graphique de performance par boutique
            const boutiquesLabels = [
                <?php foreach ($rapport_boutiques as $boutique): ?>
                    '<?= htmlspecialchars($boutique['nom']) ?>',
                <?php endforeach; ?>
            ];
            
            const boutiquesCA = [
                <?php foreach ($rapport_boutiques as $boutique): ?>
                    <?= $boutique['chiffre_affaires'] ?>,
                <?php endforeach; ?>
            ];

            // Graphique performance par boutique
            const performanceCtx = document.getElementById('performanceChart');
            if (performanceCtx) {
                new Chart(performanceCtx, {
                    type: 'bar',
                    data: {
                        labels: boutiquesLabels,
                        datasets: [{
                            label: 'Chiffre d\'affaires ($)',
                            data: boutiquesCA,
                            backgroundColor: '#3b82f6',
                            borderColor: '#1d4ed8',
                            borderWidth: 1
                        }]
                    },
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

            // Données pour le graphique des ventes globales
            const ventesGlobalesData = {
                labels: [
                    <?php foreach ($rapport_ventes_globales as $vente): ?>
                        '<?= date('d/m', strtotime($vente['date_vente'])) ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Chiffre d\'affaires global ($)',
                    data: [
                        <?php foreach ($rapport_ventes_globales as $vente): ?>
                            <?= $vente['chiffre_affaires'] ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            };

            // Créer le graphique des ventes globales
            const ventesGlobalesCtx = document.getElementById('ventesGlobalesChart');
            if (ventesGlobalesCtx) {
                new Chart(ventesGlobalesCtx, {
                    type: 'line',
                    data: ventesGlobalesData,
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