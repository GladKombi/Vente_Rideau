<?php
# Connexion à la base de données
include '../connexion/connexion.php';

// Vérification de l'authentification PDG
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    header('Location: ../login.php');
    exit;
}

// Initialisation des variables
$message = '';
$message_type = '';
$total_transferts = 0;
$transferts = [];

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer la liste des boutiques
try {
    $queryBoutiques = $pdo->prepare("SELECT id, nom FROM boutiques WHERE statut = 0 AND actif = 1 ORDER BY nom");
    $queryBoutiques->execute();
    $boutiques = $queryBoutiques->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer la liste des produits pour le formulaire
    $queryProduits = $pdo->prepare("
        SELECT p.matricule, p.designation, p.umProduit,
               COALESCE(SUM(s.quantite), 0) as quantite_totale
        FROM produits p
        LEFT JOIN stock s ON p.matricule = s.produit_matricule AND s.statut = 0
        WHERE p.statut = 0 AND p.actif = 1
        GROUP BY p.matricule, p.designation, p.umProduit
        ORDER BY p.designation
    ");
    $queryProduits->execute();
    $produits = $queryProduits->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des données : " . $e->getMessage(),
        'type' => "error"
    ];
    $boutiques = [];
    $produits = [];
}

// Gestion du formulaire de transfert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['effectuer_transfert'])) {
    try {
        // Validation des données
        $produit_matricule = $_POST['produit_matricule'];
        $boutique_expedition = (int)$_POST['boutique_expedition'];
        $boutique_destination = (int)$_POST['boutique_destination'];
        $quantite_transferee = (float)$_POST['quantite_transferee'];
        
        // Validation basique
        if ($boutique_expedition == $boutique_destination) {
            throw new Exception("Impossible de transférer vers la même boutique");
        }
        
        if ($quantite_transferee <= 0) {
            throw new Exception("La quantité transférée doit être supérieure à 0");
        }
        
        // Vérifier si le produit existe dans le stock de la boutique expédition
        $queryStockSource = $pdo->prepare("
            SELECT s.*, p.designation, p.umProduit
            FROM stock s 
            JOIN produits p ON s.produit_matricule = p.matricule 
            WHERE s.boutique_id = ? 
              AND s.produit_matricule = ? 
              AND s.statut = 0
              AND s.quantite >= ?
            ORDER BY s.date_creation ASC
            LIMIT 1
        ");
        $queryStockSource->execute([$boutique_expedition, $produit_matricule, $quantite_transferee]);
        $stock_source = $queryStockSource->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock_source) {
            // Vérifier la quantité totale disponible
            $queryQuantiteTotale = $pdo->prepare("
                SELECT SUM(quantite) as quantite_totale
                FROM stock 
                WHERE boutique_id = ? 
                  AND produit_matricule = ? 
                  AND statut = 0
            ");
            $queryQuantiteTotale->execute([$boutique_expedition, $produit_matricule]);
            $quantite_disponible = $queryQuantiteTotale->fetch(PDO::FETCH_ASSOC);
            
            $quantite_totale = $quantite_disponible['quantite_totale'] ?? 0;
            
            if ($quantite_totale == 0) {
                throw new Exception("Ce produit n'existe pas dans le stock de la boutique expédition");
            } else {
                throw new Exception("Quantité insuffisante. Quantité disponible : " . number_format($quantite_totale, 3));
            }
        }
        
        // Vérifier si un stock existe déjà pour ce produit dans la boutique destination
        $queryStockDest = $pdo->prepare("
            SELECT id, quantite, prix 
            FROM stock 
            WHERE boutique_id = ? 
              AND produit_matricule = ? 
              AND statut = 0
            LIMIT 1
        ");
        $queryStockDest->execute([$boutique_destination, $produit_matricule]);
        $stock_destination = $queryStockDest->fetch(PDO::FETCH_ASSOC);
        
        // Démarrer la transaction
        $pdo->beginTransaction();
        
        // 1. Mettre à jour le stock source (diminuer la quantité)
        $queryUpdateSource = $pdo->prepare("
            UPDATE stock 
            SET quantite = quantite - ? 
            WHERE id = ? AND statut = 0
        ");
        $queryUpdateSource->execute([$quantite_transferee, $stock_source['id']]);
        
        // 2. Si le produit existe déjà dans la boutique destination
        if ($stock_destination) {
            // Augmenter la quantité du stock existant
            $queryUpdateDest = $pdo->prepare("
                UPDATE stock 
                SET quantite = quantite + ?, 
                    type_mouvement = 'transfert'
                WHERE id = ? AND statut = 0
            ");
            $queryUpdateDest->execute([$quantite_transferee, $stock_destination['id']]);
            $nouveau_stock_id = $stock_destination['id'];
        } else {
            // Créer un nouveau stock pour la boutique destination
            $queryInsertDest = $pdo->prepare("
                INSERT INTO stock (type_mouvement, boutique_id, produit_matricule, quantite, prix, seuil_alerte_stock)
                VALUES ('transfert', ?, ?, ?, ?, ?)
            ");
            $queryInsertDest->execute([
                $boutique_destination,
                $produit_matricule,
                $quantite_transferee,
                $stock_source['prix'],
                $stock_source['seuil_alerte_stock']
            ]);
            $nouveau_stock_id = $pdo->lastInsertId();
        }
        
        // 3. Enregistrer le transfert dans la table transferts
        $queryInsertTransfert = $pdo->prepare("
            INSERT INTO transferts (date, stock_id, Expedition, Destination)
            VALUES (CURDATE(), ?, ?, ?)
        ");
        $queryInsertTransfert->execute([
            $stock_source['id'],
            $boutique_expedition,
            $boutique_destination
        ]);
        
        // Valider la transaction
        $pdo->commit();
        
        // Récupérer les noms des boutiques pour le message
        $boutique_exp_nom = '';
        $boutique_dest_nom = '';
        
        foreach ($boutiques as $b) {
            if ($b['id'] == $boutique_expedition) $boutique_exp_nom = $b['nom'];
            if ($b['id'] == $boutique_destination) $boutique_dest_nom = $b['nom'];
        }
        
        $_SESSION['flash_message'] = [
            'text' => "Transfert effectué avec succès ! " . number_format($quantite_transferee, 3) . " " . 
                     ($stock_source['umProduit'] == 'metres' ? 'mètres' : 'pièces') . 
                     " de " . $stock_source['designation'] . " transférés de " . 
                     $boutique_exp_nom . " vers " . $boutique_dest_nom . ".",
            'type' => "success"
        ];
        
        header('Location: transferts.php');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors du transfert : " . $e->getMessage(),
            'type' => "error"
        ];
    }
}

// Pagination pour les historiques de transfert
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Compter le nombre total de transferts
try {
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM transferts WHERE statut = 0");
    $countQuery->execute();
    $total_transferts = $countQuery->fetchColumn();
    $totalPages = ceil($total_transferts / $limit);

    // Requête paginée avec jointures
    $query = $pdo->prepare("
        SELECT t.*, 
               s.produit_matricule,
               p.designation as produit_designation,
               p.umProduit,
               b1.nom as boutique_expedition,
               b2.nom as boutique_destination,
               st.quantite as quantite_transferee,
               st.prix as prix_unitaire
        FROM transferts t 
        JOIN stock s ON t.stock_id = s.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        JOIN boutiques b1 ON t.Expedition = b1.id 
        JOIN boutiques b2 ON t.Destination = b2.id
        JOIN stock st ON t.stock_id = st.id
        WHERE t.statut = 0 
        ORDER BY t.date DESC, t.id DESC 
        LIMIT :limit OFFSET :offset
    ");
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $transferts = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques
    $queryStats = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT t.Expedition) as boutiques_expeditrices,
            COUNT(DISTINCT t.Destination) as boutiques_destinataires,
            COUNT(DISTINCT p.matricule) as produits_transferes,
            SUM(s.quantite) as quantite_totale_transferee,
            SUM(s.quantite * s.prix) as valeur_totale_transferee
        FROM transferts t 
        JOIN stock s ON t.stock_id = s.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE t.statut = 0
    ");
    $queryStats->execute();
    $stats = $queryStats->fetch(PDO::FETCH_ASSOC);
    
    $boutiques_expeditrices = $stats['boutiques_expeditrices'] ?? 0;
    $boutiques_destinataires = $stats['boutiques_destinataires'] ?? 0;
    $produits_transferes = $stats['produits_transferes'] ?? 0;
    $quantite_totale_transferee = $stats['quantite_totale_transferee'] ?? 0;
    $valeur_totale_transferee = $stats['valeur_totale_transferee'] ?? 0;

} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des transferts: " . $e->getMessage(),
        'type' => "error"
    ];
    $boutiques_expeditrices = 0;
    $boutiques_destinataires = 0;
    $produits_transferes = 0;
    $quantite_totale_transferee = 0;
    $valeur_totale_transferee = 0;
    $transferts = [];
    $total_transferts = 0;
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Gestion des transferts - NGS (New Grace Service)</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .gradient-blue-btn {
            background: linear-gradient(90deg, #4F86F7 0%, #1A5A9C 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-blue-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .gradient-purple-btn {
            background: linear-gradient(90deg, #8B5CF6 0%, #7C3AED 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-purple-btn:hover {
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .slide-down {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar-header, .sidebar-profile, .sidebar-footer {
            flex-shrink: 0;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            transition: background 0.3s ease;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) rgba(255, 255, 255, 0.05);
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

        html {
            scroll-behavior: smooth;
        }

        .mobile-menu-btn {
            transition: transform 0.3s ease;
        }

        .mobile-menu-btn.active {
            transform: rotate(90deg);
        }

        .fade-in-row {
            animation: fadeInRow 0.5s ease-out forwards;
            opacity: 0;
        }

        @keyframes fadeInRow {
            to {
                opacity: 1;
            }
        }

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(50%, -50%);
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .table-container {
            overflow-x: auto;
        }

        .transfert-row:hover {
            background-color: #f9fafb;
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
        
        .badge-transfert {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(90deg, #8B5CF6 0%, #7C3AED 100%);
            color: white;
        }
        
        .badge-expedition {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background-color: #FEE2E2;
            color: #991B1B;
        }
        
        .badge-destination {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background-color: #DCFCE7;
            color: #065F46;
        }
        
        .arrow-transfert {
            color: #8B5CF6;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        
        .info-box p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }

        .input-with-unite {
            position: relative;
        }
        
        .input-with-unite input {
            padding-right: 60px;
        }
        
        .unite-label {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            color: #6b7280;
            pointer-events: none;
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
            
            .transfert-flow {
                flex-direction: column;
                align-items: center;
            }
            
            .transfert-arrow {
                transform: rotate(90deg);
                margin: 10px 0;
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
                    <div class="w-12 h-12 rounded-full bg-yellow-500/20 border-2 border-yellow-500/30 flex items-center justify-center relative">
                        <i class="fas fa-crown text-yellow-500 text-lg"></i>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-gray-900"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold truncate"><?= htmlspecialchars($_SESSION['user_name'] ?? 'PDG') ?></h3>
                        <p class="text-sm text-gray-300 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav p-4 space-y-1">
                <a href="dashboard_pdg.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors relative">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="boutiques.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-store w-5 text-gray-300"></i>
                    <span>Boutiques</span>
                </a>                
                <a href="produits.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Produits</span>
                </a>
                <a href="stocks.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-warehouse w-5 text-gray-300"></i>
                    <span>Stocks</span>
                </a>
                <a href="transferts.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-exchange-alt w-5 text-white"></i>
                    <span>Transferts</span>
                    <span class="notification-badge"><?= $total_transferts ?></span>
                </a>
                <a href="utilisateurs.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users w-5 text-gray-300"></i>
                    <span>Utilisateurs</span>
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
            <header class="bg-white border-b border-gray-200 p-4 md:p-6 sticky top-0 z-30 shadow-sm">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Gestion des transferts - NGS</h1>
                        <p class="text-gray-600 text-sm md:text-base">New Grace Service - Transferts de produits entre boutiques</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button onclick="openTransfertModal()"
                            class="px-4 py-3 gradient-purple-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="hidden md:inline">Nouveau transfert</span>
                            <span class="md:hidden">Transfert</span>
                        </button>
                        <a href="stocks.php"
                            class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-warehouse"></i>
                            <span class="hidden md:inline">Voir stocks</span>
                            <span class="md:hidden">Stocks</span>
                        </a>
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

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8 stats-grid">
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_transferts ?></h3>
                        <p class="text-gray-600">Transferts effectués</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-indigo-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center">
                                <i class="fas fa-store text-indigo-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-indigo-600">Boutiques</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $boutiques_expeditrices ?> → <?= $boutiques_destinataires ?></h3>
                        <p class="text-gray-600">Expéditrices → Destinataires</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-emerald-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-boxes text-emerald-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-emerald-600">Produits</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $produits_transferes ?></h3>
                        <p class="text-gray-600">Types de produits</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-cyan-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-cyan-100 flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-cyan-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-cyan-600">Valeur totale</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($valeur_totale_transferee, 2) ?> $</h3>
                        <p class="text-gray-600">Valeur transférée</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in" style="animation-delay: 0.4s">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="relative flex-1 max-w-lg">
                            <input type="text"
                                id="searchInput"
                                placeholder="Rechercher par boutique, produit ou ID..."
                                class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-secondary focus:border-secondary transition-all shadow-sm">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-sm text-gray-600 hidden md:flex items-center space-x-2">
                                <i class="fas fa-info-circle text-purple-500"></i>
                                <span>Page <?= $page ?> sur <?= $totalPages ?></span>
                            </div>
                            <button onclick="refreshPage()" class="p-2 text-gray-600 hover:text-purple-600 transition-colors" title="Actualiser">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.5s">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Historique des transferts - NGS</h2>
                    </div>

                    <div class="table-container">
                        <table class="w-full min-w-[1000px]" id="transfertsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfert</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php if (!empty($transferts)): ?>
                                    <?php foreach ($transferts as $index => $transfert): ?>
                                        <?php 
                                        $uniteClass = $transfert['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces';
                                        $uniteText = $transfert['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                        $uniteIcon = $transfert['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube';
                                        $valeur_transfert = $transfert['quantite_transferee'] * $transfert['prix_unitaire'];
                                        ?>
                                        <tr class="transfert-row hover:bg-gray-50 transition-colors fade-in-row"
                                            data-transfert-id="<?= htmlspecialchars($transfert['id']) ?>"
                                            data-expedition="<?= htmlspecialchars(strtolower($transfert['boutique_expedition'])) ?>"
                                            data-destination="<?= htmlspecialchars(strtolower($transfert['boutique_destination'])) ?>"
                                            data-produit="<?= htmlspecialchars(strtolower($transfert['produit_designation'])) ?>"
                                            style="animation-delay: <?= $index * 0.05 ?>s">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-mono font-bold">#<?= htmlspecialchars($transfert['id']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d/m/Y', strtotime($transfert['date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div>
                                                    <div class="flex items-center">
                                                        <span class="font-medium"><?= htmlspecialchars($transfert['produit_designation']) ?></span>
                                                        <span class="badge-unite ml-2 <?= $uniteClass ?>">
                                                            <i class="<?= $uniteIcon ?> mr-1 text-xs"></i>
                                                            <?= $uniteText ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 font-mono mt-1">
                                                        <?= htmlspecialchars($transfert['produit_matricule']) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center space-x-2 transfert-flow">
                                                    <span class="badge-expedition">
                                                        <i class="fas fa-paper-plane mr-1"></i>
                                                        <?= htmlspecialchars($transfert['boutique_expedition']) ?>
                                                    </span>
                                                    <span class="transfert-arrow arrow-transfert">
                                                        <i class="fas fa-long-arrow-alt-right"></i>
                                                    </span>
                                                    <span class="badge-destination">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                                        <?= htmlspecialchars($transfert['boutique_destination']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-bold"><?= number_format($transfert['quantite_transferee'], 3) ?></span>
                                                    <span class="text-xs text-gray-500 ml-1">
                                                        <?= $uniteText ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-lg text-sm font-medium">
                                                        <?= number_format($valeur_transfert, 2) ?> $
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2 action-buttons">
                                                    <button onclick="openDeleteTransfertModal(<?= $transfert['id'] ?>, '<?= htmlspecialchars(addslashes($transfert['produit_designation'])) ?>'); return false;"
                                                            class="action-btn inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors">
                                                        <i class="fas fa-trash-alt mr-1"></i>
                                                        <span class="hidden md:inline">Supprimer</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-exchange-alt text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun transfert enregistré</p>
                                                <p class="text-sm mt-2">Effectuez votre premier transfert en utilisant le bouton "Nouveau transfert"</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="noResults" class="hidden text-center py-12">
                        <div class="bg-gray-50 rounded-2xl p-8 max-w-md mx-auto shadow-soft">
                            <i class="fas fa-search text-6xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun résultat trouvé</h3>
                            <p class="text-gray-600">Aucun transfert ne correspond à votre recherche</p>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 hidden sm:block">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_transferts) ?></span> sur
                                    <span class="font-medium"><?= $total_transferts ?></span> transferts
                                </div>

                                <div class="flex items-center space-x-2 mx-auto sm:mx-0">
                                    <a href="?page=<?= max(1, $page - 1) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>

                                    <?php
                                    $startPage = max(1, $page - 1);
                                    $endPage = min($totalPages, $page + 1);

                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isActive = $i == $page;
                                    ?>
                                        <a href="?page=<?= $i ?>"
                                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $isActive ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php } ?>

                                    <a href="?page=<?= min($totalPages, $page + 1) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal pour nouveau transfert -->
    <div id="transfertModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Nouveau transfert - NGS</h3>
                <button onclick="closeTransfertModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="transfertForm" method="POST" action="transferts.php">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="boutique_expedition" class="block text-sm font-medium text-gray-700 mb-1">Boutique expédition *</label>
                            <select name="boutique_expedition" id="boutique_expedition" required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3">
                                <option value="">Sélectionnez la boutique qui envoie</option>
                                <?php foreach ($boutiques as $boutique): ?>
                                    <option value="<?= $boutique['id'] ?>"><?= htmlspecialchars($boutique['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="boutique_destination" class="block text-sm font-medium text-gray-700 mb-1">Boutique destination *</label>
                            <select name="boutique_destination" id="boutique_destination" required
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3">
                                <option value="">Sélectionnez la boutique qui reçoit</option>
                                <?php foreach ($boutiques as $boutique): ?>
                                    <option value="<?= $boutique['id'] ?>"><?= htmlspecialchars($boutique['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label for="produit_matricule" class="block text-sm font-medium text-gray-700 mb-1">Produit à transférer *</label>
                        <select name="produit_matricule" id="produit_matricule" required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3">
                            <option value="">Sélectionnez un produit</option>
                            <?php foreach ($produits as $produit): 
                                $uniteText = $produit['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                            ?>
                                <option value="<?= htmlspecialchars($produit['matricule']) ?>" 
                                        data-unite="<?= htmlspecialchars($produit['umProduit']) ?>"
                                        data-quantite-totale="<?= $produit['quantite_totale'] ?? 0 ?>">
                                    <?= htmlspecialchars($produit['designation']) ?> 
                                    (<?= htmlspecialchars($produit['matricule']) ?> - 
                                    <?= number_format($produit['quantite_totale'] ?? 0, 3) ?> <?= $uniteText ?> disponibles)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="quantite_transferee" class="block text-sm font-medium text-gray-700 mb-1">Quantité à transférer *</label>
                        <div class="input-with-unite">
                            <input type="number" name="quantite_transferee" id="quantite_transferee" required step="0.001" min="0.001"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                                   placeholder="Ex: 5.000">
                            <span id="quantiteUniteLabel" class="unite-label">unités</span>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-blue-700 font-medium">Processus de transfert</p>
                                <ul class="text-xs text-blue-600 mt-1 list-disc pl-4 space-y-1">
                                    <li>La quantité sera déduite du stock de la boutique expédition</li>
                                    <li>La quantité sera ajoutée au stock de la boutique destination</li>
                                    <li>Un nouveau stock sera créé si le produit n'existe pas dans la boutique destination</li>
                                    <li>Le mouvement sera enregistré comme "transfert" dans l'historique</li>
                                    <li>Le prix unitaire est conservé lors du transfert</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeTransfertModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="effectuer_transfert"
                            class="px-4 py-2 gradient-purple-btn text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                        Effectuer le transfert
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour suppression de transfert -->
    <div id="deleteTransfertModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Suppression de transfert - NGS</h3>
                <button onclick="closeDeleteTransfertModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="text-center py-4">
                <i class="fas fa-trash-alt text-5xl mb-4 text-red-500"></i>
                <p class="text-lg font-bold text-red-700 mb-2">ATTENTION ! Suppression définitive</p>
                <p class="text-gray-600 mb-4" id="deleteTransfertModalText">Vous êtes sur le point de supprimer définitivement ce transfert. Cette action est irréversible et peut affecter l'équilibre des stocks. Confirmez-vous ?</p>
            </div>

            <form id="deleteTransfertForm" method="POST" action="../models/traitement/transfert-post.php" class="mt-6 flex justify-center space-x-3">
                <input type="hidden" name="transfert_id" id="deleteTransfertId">
                <button type="button" onclick="closeDeleteTransfertModal()"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                    Annuler
                </button>
                <button type="submit" name="supprimer_transfert"
                        class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded-lg hover:opacity-90 transition-opacity shadow-md">
                    Oui, Supprimer définitivement
                </button>
            </form>
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

        // --- GESTION DE LA MODALE DE TRANSFERT ---
        const transfertModal = document.getElementById('transfertModal');
        const produitSelect = document.getElementById('produit_matricule');
        const boutiqueExpeditionSelect = document.getElementById('boutique_expedition');
        const boutiqueDestinationSelect = document.getElementById('boutique_destination');
        const quantiteTransfereeInput = document.getElementById('quantite_transferee');
        const quantiteUniteLabel = document.getElementById('quantiteUniteLabel');

        // Fonction pour mettre à jour l'unité de mesure
        function updateUnite() {
            const selectedOption = produitSelect.options[produitSelect.selectedIndex];
            if (selectedOption && selectedOption.value) {
                const unite = selectedOption.getAttribute('data-unite');
                const uniteText = unite === 'metres' ? 'mètres' : 'pièces';
                quantiteUniteLabel.textContent = uniteText;
            } else {
                quantiteUniteLabel.textContent = 'unités';
            }
        }

        // Fonction pour vérifier que la destination est différente de l'expédition
        function verifierDestination() {
            const expeditionId = boutiqueExpeditionSelect.value;
            const destinationId = boutiqueDestinationSelect.value;
            
            if (expeditionId && destinationId && expeditionId === destinationId) {
                alert('La boutique destination doit être différente de la boutique expédition');
                boutiqueDestinationSelect.value = '';
                return false;
            }
            return true;
        }

        // Fonction pour ouvrir le modal
        function openTransfertModal() {
            console.log('Opening transfert modal...');
            // Réinitialiser le formulaire
            document.getElementById('transfertForm').reset();
            quantiteUniteLabel.textContent = 'unités';
            
            // Afficher le modal
            transfertModal.classList.add('show');
            console.log('Modal should be visible now');
        }

        // Fonction pour fermer le modal
        function closeTransfertModal() {
            transfertModal.classList.remove('show');
        }

        // Fonction pour ouvrir le modal de suppression
        function openDeleteTransfertModal(transfertId, produitDesignation) {
            const deleteModal = document.getElementById('deleteTransfertModal');
            const deleteTransfertModalText = document.getElementById('deleteTransfertModalText');
            const deleteTransfertId = document.getElementById('deleteTransfertId');
            
            if (deleteTransfertModalText && deleteTransfertId) {
                deleteTransfertModalText.innerHTML = `Vous êtes sur le point de supprimer définitivement le transfert #${transfertId} (Produit: <strong>${produitDesignation}</strong>). Cette action est irréversible et peut affecter l'équilibre des stocks. Confirmez-vous ?`;
                deleteTransfertId.value = transfertId;
                deleteModal.classList.add('show');
            }
        }

        // Fonction pour fermer le modal de suppression
        function closeDeleteTransfertModal() {
            const deleteModal = document.getElementById('deleteTransfertModal');
            if (deleteModal) {
                deleteModal.classList.remove('show');
            }
        }

        // Écouter les changements sur les selects
        produitSelect.addEventListener('change', updateUnite);
        boutiqueDestinationSelect.addEventListener('change', verifierDestination);

        // --- GESTION DE LA RECHERCHE ---
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.transfert-row');
                let found = false;

                rows.forEach(row => {
                    const transfertId = row.dataset.transfertId;
                    const expedition = row.dataset.expedition;
                    const destination = row.dataset.destination;
                    const produit = row.dataset.produit;

                    if (transfertId.includes(searchTerm) || 
                        expedition.includes(searchTerm) || 
                        destination.includes(searchTerm) || 
                        produit.includes(searchTerm)) {
                        row.style.display = '';
                        found = true;
                    } else {
                        row.style.display = 'none';
                    }
                });

                const noResults = document.getElementById('noResults');
                const tableBody = document.getElementById('tableBody');
                
                if (noResults) noResults.classList.toggle('hidden', found);
                if (tableBody) tableBody.classList.toggle('hidden', !found && searchTerm !== '');
            });
        }

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

        // --- VALIDATION DU FORMULAIRE DE TRANSFERT ---
        const transfertForm = document.getElementById('transfertForm');
        
        if (transfertForm) {
            transfertForm.addEventListener('submit', function(e) {
                const boutiqueExpedition = boutiqueExpeditionSelect.value;
                const boutiqueDestination = boutiqueDestinationSelect.value;
                const produitMatricule = produitSelect.value;
                const quantiteTransferee = parseFloat(quantiteTransfereeInput.value);
                
                // Validation basique
                if (!boutiqueExpedition) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une boutique expédition.');
                    return false;
                }
                
                if (!boutiqueDestination) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une boutique destination.');
                    return false;
                }
                
                if (boutiqueExpedition === boutiqueDestination) {
                    e.preventDefault();
                    alert('La boutique destination doit être différente de la boutique expédition.');
                    return false;
                }
                
                if (!produitMatricule) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un produit à transférer.');
                    return false;
                }
                
                if (!quantiteTransferee || quantiteTransferee <= 0) {
                    e.preventDefault();
                    alert('La quantité transférée doit être supérieure à 0.');
                    return false;
                }
                
                // Confirmation supplémentaire pour les grandes quantités
                if (quantiteTransferee > 100) {
                    const confirmation = confirm(`Vous êtes sur le point de transférer ${quantiteTransferee} unités. Confirmez-vous cette opération ?`);
                    if (!confirmation) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                return true;
            });
        }

        // --- NAVIGATION CLAVIER ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (transfertModal.classList.contains('show')) closeTransfertModal();
                
                const deleteModal = document.getElementById('deleteTransfertModal');
                if (deleteModal && deleteModal.classList.contains('show')) {
                    closeDeleteTransfertModal();
                }
                
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });

        // --- ANIMATION DES LIGNES AU CHARGEMENT ---
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.fade-in-row');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
            });
        });

        // --- GESTION DES EFFETS VISUELS ---
        document.querySelectorAll('.stats-card, .nav-link').forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Initialisation
        console.log('Transferts page loaded successfully');
        console.log('openTransfertModal function available:', typeof openTransfertModal === 'function');
    </script>
</body>
</html>