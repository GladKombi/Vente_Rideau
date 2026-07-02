<?php
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';
$total_stocks = 0;
$stocks = [];

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

try {
    $queryBoutiques = $pdo->prepare("SELECT id, nom FROM boutiques WHERE statut = 0 AND actif = 1 ORDER BY nom");
    $queryBoutiques->execute();
    $boutiques = $queryBoutiques->fetchAll();

    $queryProduits = $pdo->prepare("SELECT matricule, designation, umProduit FROM produits WHERE statut = 0 AND actif = 1 ORDER BY designation");
    $queryProduits->execute();
    $produits = $queryProduits->fetchAll();
} catch (PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    $boutiques = [];
    $produits = [];
}

// AJAX get stock
if (isset($_GET['action']) && $_GET['action'] == 'get_stock' && isset($_GET['id'])) {
    $query = $pdo->prepare("SELECT s.*, b.nom as boutique_nom, p.designation as produit_designation, p.umProduit, nr.numero_rideau FROM stock s JOIN boutiques b ON s.boutique_id=b.id JOIN produits p ON s.produit_matricule=p.matricule LEFT JOIN numeros_rideaux nr ON s.numero_rideau_id = nr.id WHERE s.id=? AND s.statut=0");
    $query->execute([(int)$_GET['id']]);
    $stock = $query->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($stock ? ['success' => true, 'stock' => $stock] : ['success' => false, 'message' => 'Non trouvé']);
    exit;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE statut = 0 AND type_mouvement = 'approvisionnement'");
    $countQuery->execute();
    $total_stocks = $countQuery->fetchColumn();
    $totalPages = ceil($total_stocks / $limit);
    if ($totalPages < 1) $totalPages = 1;

    $query = $pdo->prepare("
        SELECT s.*, b.nom as boutique_nom, p.designation as produit_designation, p.umProduit, nr.numero_rideau
        FROM stock s 
        JOIN boutiques b ON s.boutique_id = b.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        LEFT JOIN numeros_rideaux nr ON s.numero_rideau_id = nr.id
        WHERE s.statut = 0 AND s.type_mouvement = 'approvisionnement' 
        ORDER BY s.date_creation DESC 
        LIMIT :limit OFFSET :offset
    ");
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $stocks = $query->fetchAll();

    $total_quantite = $pdo->query("SELECT COALESCE(SUM(quantite),0) FROM stock WHERE statut=0 AND type_mouvement='approvisionnement'")->fetchColumn();
    $total_valeur = $pdo->query("SELECT COALESCE(SUM(quantite*prix),0) FROM stock WHERE statut=0 AND type_mouvement='approvisionnement'")->fetchColumn();

    $su = $pdo->query("SELECT COUNT(DISTINCT CASE WHEN p.umProduit='metres' THEN s.produit_matricule END) as pm, COUNT(DISTINCT CASE WHEN p.umProduit='pieces' THEN s.produit_matricule END) as pp, SUM(CASE WHEN p.umProduit='metres' THEN s.quantite ELSE 0 END) as qm, SUM(CASE WHEN p.umProduit='pieces' THEN s.quantite ELSE 0 END) as qp FROM stock s JOIN produits p ON s.produit_matricule=p.matricule WHERE s.statut=0 AND s.type_mouvement='approvisionnement'")->fetch();
    $produits_metres = $su['pm'] ?? 0;
    $produits_pieces = $su['pp'] ?? 0;
    $quantite_metres = $su['qm'] ?? 0;
    $quantite_pieces = $su['qp'] ?? 0;

    // Récupérer tous les numéros de rideaux disponibles pour le PDG (toutes boutiques confondues)
    $numeros_rideaux_disponibles = $pdo->query("SELECT id, numero_rideau, boutique_id FROM numeros_rideaux WHERE est_utilise = 0 AND actif = 1 ORDER BY boutique_id, numero_rideau")->fetchAll();

} catch (PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    $total_quantite = 0;
    $total_valeur = 0;
    $produits_metres = 0;
    $produits_pieces = 0;
    $quantite_metres = 0;
    $quantite_pieces = 0;
    $stocks = [];
    $total_stocks = 0;
    $totalPages = 1;
    $numeros_rideaux_disponibles = [];
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Stocks - NGS (PDG)</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.3);
            --card-bg: rgba(255, 255, 255, 0.8);
            --text-primary: #1a1a2e;
            --text-secondary: #4a4a6a;
            --text-muted: #6b7280;
            --accent-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            --input-bg: rgba(255, 255, 255, 0.9);
            --input-border: rgba(0, 0, 0, 0.1);
            --divider: rgba(0, 0, 0, 0.06);
        }

        .dark {
            --sidebar-bg: linear-gradient(180deg, #020617 0%, #0f172a 100%);
            --glass-bg: rgba(15, 23, 42, 0.75);
            --glass-border: rgba(255, 255, 255, 0.08);
            --card-bg: rgba(30, 41, 59, 0.7);
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --accent-gradient: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
            --input-bg: rgba(30, 41, 59, 0.8);
            --input-border: rgba(255, 255, 255, 0.1);
            --divider: rgba(255, 255, 255, 0.06);
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 50%, #f5f3ff 100%);
            color: var(--text-primary);
            transition: background 0.4s ease, color 0.4s ease;
        }

        .dark body {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
        }

        .sidebar {
            background: var(--sidebar-bg);
        }

        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }

        .premium-card {
            background: var(--card-bg);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--glass-border);
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .premium-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
        }

        .dark .premium-card:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .input-glass {
            background: var(--input-bg);
            border: 2px solid var(--input-border);
            color: var(--text-primary);
            border-radius: 0.75rem;
            transition: all 0.3s ease;
        }

        .input-glass:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .btn-glass {
            background: var(--accent-gradient);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.2);
        }

        .btn-glass:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.35);
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
            border-radius: 0.75rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding-left: 1.25rem;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            border-left: 3px solid #60a5fa;
        }

        .stat-card {
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        }

        .theme-toggle {
            width: 44px;
            height: 24px;
            background: #cbd5e1;
            border-radius: 12px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .dark .theme-toggle {
            background: #334155;
        }

        .theme-toggle::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }

        .dark .theme-toggle::after {
            transform: translateX(20px);
            background: #fbbf24;
        }

        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .modal-container {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 1.5rem;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
            max-height: 85vh;
            overflow-y: auto;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .dark .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .dark .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
        }

        .dark .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        /* Autocomplétion */
        .produit-search-wrapper {
            position: relative;
        }

        .produit-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 0.75rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 50;
            display: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .produit-suggestions.show {
            display: block;
        }

        .produit-suggestion-item {
            padding: 0.625rem 1rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background 0.2s;
        }

        .produit-suggestion-item:hover {
            background: rgba(59, 130, 246, 0.1);
        }

        /* Style pour la recherche de numéro de rideau */
        .numero-search-wrapper {
            position: relative;
        }

        .numero-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 0.75rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 50;
            display: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .numero-suggestions.show {
            display: block;
        }

        .numero-suggestion-item {
            padding: 0.625rem 1rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background 0.2s;
        }

        .numero-suggestion-item:hover {
            background: rgba(251, 191, 36, 0.1);
        }

        *:focus-visible {
            outline: 2px solid #60a5fa;
            outline-offset: 2px;
            border-radius: 6px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.4s ease-out forwards;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body class="h-screen flex overflow-hidden">

    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR PDG -->
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg"><span class="font-bold text-white">NGS</span></div>
                <div>
                    <h2 class="font-bold text-sm">NGS Pro</h2>
                    <p class="text-[10px] text-gray-400">Dashboard PDG</p>
                </div>
            </div>
        </div>
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-500/20 border border-amber-400/30 flex items-center justify-center"><i class="fas fa-crown text-amber-400"></i></div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($_SESSION['user_name'] ?? 'PDG') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="boutiques.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-store w-4 text-center"></i>Boutiques</a>
            <a href="produits.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-box w-4 text-center"></i>Produits</a>
            <a href="stocks.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Stocks<?php if ($total_stocks > 0): ?><span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_stocks ?></span><?php endif; ?></a>
            <a href="transferts.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Transferts</a>
            <a href="utilisateurs.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-users w-4 text-center"></i>Utilisateurs</a>
            <a href="rapports_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
            <a href="realisations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations</a>
            <a href="numeros_rideaux.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tags w-4 text-center"></i>N° Rideaux</a>
        </nav>
        <div class="p-3 border-t border-white/10 flex-shrink-0">
            <div class="flex items-center justify-between px-3 py-2 mb-2"><span class="text-xs text-gray-400"><i class="fas fa-moon mr-1"></i>Thème</span><button id="theme-toggle" class="theme-toggle" aria-label="Changer le thème"></button></div>
            <a href="../models/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-red-400 hover:bg-red-500/10 transition-colors text-sm"><i class="fas fa-sign-out-alt w-4 text-center"></i>Déconnexion</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-y-auto">
        <header class="sticky top-0 z-30 glass border-b border-white/10">
            <div class="flex items-center justify-between px-4 md:px-6 py-4">
                <div class="flex items-center gap-3">
                    <button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors text-[var(--text-primary)]"><i class="fas fa-bars text-lg"></i></button>
                    <div>
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Gestion des Stocks</h1>
                        <p class="text-xs text-[var(--text-muted)]">Approvisionnements • Vue PDG</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openStockModal()" class="btn-glass px-4 py-2 rounded-xl text-sm flex items-center gap-2"><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouveau stock</span></button>
                    <a href="transferts.php" class="px-4 py-2 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 transition-colors hidden sm:flex items-center gap-2"><i class="fas fa-exchange-alt"></i>Transferts</a>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <?php if ($message): ?>
                <div class="animate-fade-in-up">
                    <div class="glass rounded-2xl p-4 border-l-4 <?= $message_type === 'success' ? 'border-emerald-500' : 'border-red-500' ?>">
                        <div class="flex items-center gap-3"><i class="fas fa-<?= $message_type === 'success' ? 'check-circle text-emerald-500' : 'exclamation-circle text-red-500' ?> text-xl"></i><span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($message) ?></span><button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button></div>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0s">
                        <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Total</span>
                            <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-boxes text-blue-600 dark:text-blue-400 text-sm"></i></div>
                        </div>
                        <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_stocks ?></p>
                        <p class="text-xs text-[var(--text-muted)] mt-1">Approvisionnements</p>
                    </div>
                    <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-cyan-500" style="animation-delay:0.1s">
                        <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-cyan-600 dark:text-cyan-400">Rideaux (m)</span>
                            <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center"><i class="fas fa-ruler-combined text-cyan-600 dark:text-cyan-400 text-sm"></i></div>
                        </div>
                        <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($quantite_metres, 3) ?></p>
                        <p class="text-xs text-[var(--text-muted)] mt-1"><?= $produits_metres ?> produits</p>
                    </div>
                    <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.2s">
                        <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Produits (pce)</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-cube text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                        </div>
                        <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($quantite_pieces, 3) ?></p>
                        <p class="text-xs text-[var(--text-muted)] mt-1"><?= $produits_pieces ?> produits</p>
                    </div>
                    <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.3s">
                        <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-purple-600 dark:text-purple-400">Valeur stock</span>
                            <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-chart-line text-purple-600 dark:text-purple-400 text-sm"></i></div>
                        </div>
                        <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_valeur, 2) ?> $</p>
                        <p class="text-xs text-[var(--text-muted)] mt-1">Valeur totale</p>
                    </div>
                </div>

                <!-- Recherche -->
                <div class="premium-card p-4 animate-fade-in-up" style="animation-delay:0.15s">
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-md">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)]"></i>
                            <input type="text" id="searchInput" placeholder="Rechercher par boutique, produit ou ID..." class="w-full input-glass pl-10 pr-4 py-2.5 text-sm">
                        </div>
                        <span class="text-xs text-[var(--text-muted)] hidden sm:block">Page <?= $page ?>/<?= $totalPages ?></span>
                        <button onclick="window.location.reload()" class="p-2.5 rounded-xl glass hover:bg-white/20 transition-all text-[var(--text-muted)]" title="Actualiser"><i class="fas fa-sync-alt text-sm"></i></button>
                    </div>
                </div>

                <!-- Tableau -->
                <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.2s">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1000px]" id="stocksTable">
                            <thead>
                                <tr class="border-b border-[var(--divider)] text-left">
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">ID</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Boutique</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produit</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">N° Rideau</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Qté</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Prix</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Seuil</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                    <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--divider)]" id="tableBody">
                                <?php if (!empty($stocks)): ?>
                                    <?php foreach ($stocks as $s):
                                        $isLow = $s['quantite'] <= $s['seuil_alerte_stock'];
                                        $ut = $s['umProduit'] == 'metres' ? 'm' : 'pce';
                                    ?>
                                        <tr class="hover:bg-white/5 transition-colors stock-row <?= $isLow ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' ?>"
                                            data-id="<?= $s['id'] ?>" data-boutique="<?= strtolower($s['boutique_nom']) ?>" data-produit="<?= strtolower($s['produit_designation']) ?>">
                                            <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $s['id'] ?></td>
                                            <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($s['boutique_nom']) ?></td>
                                            <td class="px-5 py-3.5">
                                                <span class="text-sm font-medium text-[var(--text-primary)]"><?= htmlspecialchars($s['produit_designation']) ?></span>
                                                <span class="text-xs text-[var(--text-muted)] block font-mono"><?= $s['produit_matricule'] ?></span>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <?php if (!empty($s['numero_rideau'])): ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                                        <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($s['numero_rideau']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-[var(--text-muted)]">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-3.5 text-sm font-bold <?= $isLow ? 'text-amber-600 dark:text-amber-400' : 'text-[var(--text-primary)]' ?>"><?= number_format($s['quantite'], 3) ?> <span class="text-xs font-normal text-[var(--text-muted)]"><?= $ut ?></span></td>
                                            <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]"><?= number_format($s['prix'], 2) ?> $</td>
                                            <td class="px-5 py-3.5"><span class="px-2 py-1 rounded-full text-xs font-medium <?= $isLow ? 'badge-warning' : 'badge-success' ?>"><?= $isLow ? 'Faible' : 'OK' ?> (<?= $s['seuil_alerte_stock'] ?>)</span></td>
                                            <td class="px-5 py-3.5 text-sm text-[var(--text-muted)]"><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></td>
                                            <td class="px-5 py-3.5">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <button onclick="openStockModal(<?= $s['id'] ?>)" class="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors" title="Modifier"><i class="fas fa-edit text-xs"></i></button>
                                                    <button onclick="openDeleteModal(<?= $s['id'] ?>,'<?= htmlspecialchars(addslashes($s['boutique_nom'])) ?>','<?= htmlspecialchars(addslashes($s['produit_designation'])) ?>')" class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Archiver"><i class="fas fa-archive text-xs"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-5 py-12 text-center"><i class="fas fa-inbox text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                                            <p class="text-[var(--text-secondary)] font-medium">Aucun approvisionnement</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="noResults" class="hidden text-center py-12"><i class="fas fa-search text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                        <p class="text-[var(--text-secondary)]">Aucun résultat</p>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-5 py-4 border-t border-[var(--divider)] flex items-center justify-between">
                            <span class="text-xs text-[var(--text-muted)] hidden sm:block"><?= ($page - 1) * $limit + 1 ?>-<?= min($page * $limit, $total_stocks) ?> sur <?= $total_stocks ?></span>
                            <div class="flex items-center gap-1.5 mx-auto sm:mx-0">
                                <a href="?page=<?= max(1, $page - 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-left text-xs"></i></a>
                                <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                    <a href="?page=<?= $i ?>" class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i == $page ? 'btn-glass shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                                <a href="?page=<?= min($totalPages, $page + 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-right text-xs"></i></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                </div>
    </main>

    <!-- MODAL AJOUT/MODIF -->
    <div id="stockOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="stockContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-[var(--text-primary)]" id="modalTitle"><i class="fas fa-box mr-2 text-blue-500"></i>Nouveau stock</h3><button onclick="closeStockModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="../models/traitement/stock-post.php" class="space-y-4">
            <input type="hidden" name="stock_id" id="stockId">
            <input type="hidden" name="type_mouvement" value="approvisionnement">
            <input type="hidden" name="unite_produit" id="uniteHidden">
            <input type="hidden" name="produit_matricule" id="produitMatriculeHidden">
            <input type="hidden" name="numero_rideau_id" id="numeroRideauHidden" value="">
            
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Boutique *</label>
                <select name="boutique_id" id="boutiqueSelect" required class="w-full input-glass px-3 py-2.5 text-sm" onchange="filterNumerosByBoutique()">
                    <?php foreach ($boutiques as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option><?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Produit *</label>
                <div class="produit-search-wrapper">
                    <input type="text" id="produitSearch" class="w-full input-glass px-3 py-2.5 text-sm" placeholder="Tapez le nom du produit..." autocomplete="off" oninput="filterProduits()" onfocus="filterProduits()">
                    <div id="produitSuggestions" class="produit-suggestions"></div>
                </div>
                <div id="produitInfo" class="hidden mt-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 text-xs">
                    <p><strong>Produit :</strong> <span id="infoDesignation">-</span></p>
                    <p><strong>Unité :</strong> <span id="infoUnite">-</span></p>
                    <p><strong>Matricule :</strong> <span id="infoMatricule">-</span></p>
                </div>
            </div>

            <!-- Numéro de rideau (visible seulement pour les produits en mètres) avec recherche -->
            <div id="numeroRideauField" class="hidden">
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Numéro de rideau *</label>
                <div class="numero-search-wrapper">
                    <input type="text" id="numeroSearch" 
                           class="w-full input-glass px-3 py-2.5 text-sm" 
                           placeholder="Tapez ou sélectionnez un numéro de rideau..."
                           autocomplete="off"
                           oninput="filterNumeros()"
                           onfocus="filterNumeros()">
                    <div id="numeroSuggestions" class="numero-suggestions"></div>
                </div>
                <div id="numeroInfo" class="hidden mt-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/30 text-xs">
                    <p><strong>Numéro sélectionné :</strong> <span id="infoNumero" class="font-medium text-amber-700 dark:text-amber-300">-</span></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Ce numéro sera attribué au rideau</p>
                </div>
                <?php if (empty($numeros_rideaux_disponibles)): ?>
                    <div class="mt-2 p-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/30 text-xs text-red-600 dark:text-red-400">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        Aucun numéro de rideau disponible. Veuillez d'abord <a href="numeros_rideaux.php" class="text-blue-500 hover:underline">ajouter des numéros</a>.
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Prix ($) *</label><input type="number" name="prix" step="0.001" min="0" required class="w-full input-glass px-3 py-2.5 text-sm" placeholder="0.000"></div>
                <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Quantité *</label>
                    <div class="relative"><input type="number" name="quantite" step="0.001" min="0" required class="w-full input-glass pl-4 pr-12 py-2.5 text-sm" placeholder="0.000"><span id="qteLabel" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)]">unités</span></div>
                </div>
            </div>
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Seuil alerte *</label>
                <div class="relative"><input type="number" name="seuil_alerte_stock" value="5" min="1" required class="w-full input-glass pl-4 pr-12 py-2.5 text-sm"><span id="seuilLabel" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)]">unités</span></div>
            </div>
            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/30 text-xs text-blue-700 dark:text-blue-300"><i class="fas fa-info-circle mr-1"></i>Unité définie par le produit (mètres ou pièces).</div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeStockModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" name="ajouter_stock" id="submitBtn" class="btn-glass px-5 py-2.5 rounded-xl text-sm">Enregistrer</button>
            </div>
        </form>
    </div>

    <!-- MODAL DELETE -->
    <div id="deleteOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="deleteContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-archive text-5xl text-red-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Archiver ce stock ?</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6" id="deleteText"></p>
        <form method="POST" action="../models/traitement/stock-post.php" class="flex justify-center gap-3">
            <input type="hidden" name="stock_id" id="deleteId">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
            <button type="submit" name="archiver_stock" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-semibold hover:opacity-90 transition-all">Archiver</button>
        </form>
    </div>

    <script>
        // Données des produits
        const produitsData = <?= json_encode($produits) ?>;
        
        // Données des numéros de rideaux disponibles
        const numerosData = <?= json_encode($numeros_rideaux_disponibles) ?>;

        // Theme
        const themeToggle = document.getElementById('theme-toggle'),
            html = document.documentElement;
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme:dark)').matches)) html.classList.add('dark');
        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light')
        });

        // Sidebar
        const sidebar = document.getElementById('sidebar'),
            overlay = document.getElementById('overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('hidden')
        }
        document.getElementById('mobileMenuBtn').addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Modals
        function openModal(o, c) {
            document.getElementById(o).classList.remove('hidden');
            document.getElementById(c).classList.remove('hidden')
        }

        function closeModal(o, c) {
            document.getElementById(o).classList.add('hidden');
            document.getElementById(c).classList.add('hidden')
        }

        // Autocomplétion Produits
        function filterProduits() {
            const s = document.getElementById('produitSearch').value.toLowerCase();
            const box = document.getElementById('produitSuggestions');
            if (!s) {
                box.classList.remove('show');
                return;
            }
            const filtered = produitsData.filter(p => p.designation.toLowerCase().includes(s));
            box.innerHTML = filtered.length === 0 ? '<div class="produit-suggestion-item text-[var(--text-muted)]">Aucun produit</div>' : filtered.map(p => `<div class="produit-suggestion-item" onclick="selectProduit('${p.matricule}','${p.designation.replace(/'/g,"\\'")}','${p.umProduit}')">${p.designation} <span class="text-xs text-[var(--text-muted)]">(${p.umProduit==='metres'?'mètres':'pièces'})</span></div>`).join('');
            box.classList.add('show');
        }

        function selectProduit(matricule, designation, unite) {
            document.getElementById('produitSearch').value = designation;
            document.getElementById('produitMatriculeHidden').value = matricule;
            document.getElementById('produitSuggestions').classList.remove('show');
            const u = unite === 'metres' ? 'mètres' : 'pièces';
            document.getElementById('infoDesignation').textContent = designation;
            document.getElementById('infoUnite').textContent = u;
            document.getElementById('infoMatricule').textContent = matricule;
            document.getElementById('produitInfo').classList.remove('hidden');
            document.getElementById('uniteHidden').value = unite;
            document.getElementById('qteLabel').textContent = u;
            document.getElementById('seuilLabel').textContent = u;
            
            // Afficher/masquer le champ numéro de rideau (seulement pour les mètres)
            const numeroField = document.getElementById('numeroRideauField');
            if (unite === 'metres') {
                numeroField.classList.remove('hidden');
                // Filtrer les numéros par boutique sélectionnée
                filterNumerosByBoutique();
                // Vérifier si des numéros sont disponibles
                const boutiquesFiltered = getNumerosForBoutique();
                if (boutiquesFiltered.length === 0) {
                    document.getElementById('submitBtn').disabled = true;
                } else {
                    document.getElementById('submitBtn').disabled = false;
                    // Si un seul numéro disponible, le sélectionner automatiquement
                    if (boutiquesFiltered.length === 1) {
                        selectNumero(boutiquesFiltered[0].id, boutiquesFiltered[0].numero_rideau);
                    }
                }
            } else {
                numeroField.classList.add('hidden');
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('numeroRideauHidden').value = '';
            }
        }

        // Filtrer les numéros par boutique
        function getNumerosForBoutique() {
            const boutiqueId = document.getElementById('boutiqueSelect').value;
            if (!boutiqueId) return numerosData;
            return numerosData.filter(n => n.boutique_id == boutiqueId);
        }

        function filterNumerosByBoutique() {
            const field = document.getElementById('numeroRideauField');
            if (field.classList.contains('hidden')) return;
            
            const filtered = getNumerosForBoutique();
            const searchInput = document.getElementById('numeroSearch');
            const suggestions = document.getElementById('numeroSuggestions');
            
            if (filtered.length === 0) {
                searchInput.value = '';
                document.getElementById('numeroRideauHidden').value = '';
                document.getElementById('numeroInfo').classList.add('hidden');
                document.getElementById('submitBtn').disabled = true;
                suggestions.innerHTML = '<div class="numero-suggestion-item text-[var(--text-muted)]">Aucun numéro disponible pour cette boutique</div>';
                suggestions.classList.add('show');
                return;
            }
            
            document.getElementById('submitBtn').disabled = false;
            // Si un seul numéro disponible, le sélectionner automatiquement
            if (filtered.length === 1) {
                selectNumero(filtered[0].id, filtered[0].numero_rideau);
            }
            
            // Mettre à jour les suggestions
            const searchTerm = searchInput.value.toLowerCase();
            const filteredSearch = filtered.filter(n => n.numero_rideau.toLowerCase().includes(searchTerm));
            if (filteredSearch.length === 0) {
                suggestions.innerHTML = '<div class="numero-suggestion-item text-[var(--text-muted)]">Aucun numéro correspondant</div>';
            } else {
                suggestions.innerHTML = filteredSearch.map(n => 
                    `<div class="numero-suggestion-item" onclick="selectNumero('${n.id}', '${n.numero_rideau}')">
                        <i class="fas fa-tag text-amber-500 mr-2"></i>${n.numero_rideau}
                    </div>`
                ).join('');
            }
            suggestions.classList.add('show');
        }

        // Filtrer les numéros de rideaux (recherche)
        function filterNumeros() {
            const searchTerm = document.getElementById('numeroSearch').value.toLowerCase();
            const suggestions = document.getElementById('numeroSuggestions');
            const field = document.getElementById('numeroRideauField');
            
            if (!field.classList.contains('hidden')) {
                const filtered = getNumerosForBoutique().filter(n => 
                    n.numero_rideau.toLowerCase().includes(searchTerm)
                );

                if (filtered.length === 0) {
                    suggestions.innerHTML = '<div class="numero-suggestion-item text-[var(--text-muted)]">Aucun numéro disponible</div>';
                } else {
                    suggestions.innerHTML = filtered.map(n => 
                        `<div class="numero-suggestion-item" onclick="selectNumero('${n.id}', '${n.numero_rideau}')">
                            <i class="fas fa-tag text-amber-500 mr-2"></i>${n.numero_rideau}
                        </div>`
                    ).join('');
                }
                suggestions.classList.add('show');
            } else {
                suggestions.classList.remove('show');
            }
        }

        // Sélectionner un numéro de rideau
        function selectNumero(id, numero) {
            document.getElementById('numeroSearch').value = numero;
            document.getElementById('numeroRideauHidden').value = id;
            document.getElementById('numeroSuggestions').classList.remove('show');
            
            document.getElementById('infoNumero').textContent = numero;
            document.getElementById('numeroInfo').classList.remove('hidden');
            document.getElementById('submitBtn').disabled = false;
        }

        document.addEventListener('click', function(e) {
            const w = document.querySelector('.produit-search-wrapper');
            if (w && !w.contains(e.target)) document.getElementById('produitSuggestions').classList.remove('show');
            const nw = document.querySelector('.numero-search-wrapper');
            if (nw && !nw.contains(e.target)) document.getElementById('numeroSuggestions').classList.remove('show');
        });

        // Ouvrir modal
        function openStockModal(id = null) {
            document.getElementById('stockForm')?.reset();
            document.getElementById('produitInfo').classList.add('hidden');
            document.getElementById('numeroInfo').classList.add('hidden');
            document.getElementById('numeroRideauField').classList.add('hidden');
            document.getElementById('produitSearch').value = '';
            document.getElementById('produitMatriculeHidden').value = '';
            document.getElementById('numeroSearch').value = '';
            document.getElementById('numeroRideauHidden').value = '';
            document.getElementById('submitBtn').disabled = false;
            
            if (id) {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit mr-2 text-blue-500"></i>Modifier le stock';
                document.getElementById('submitBtn').textContent = 'Modifier';
                document.getElementById('submitBtn').name = 'modifier_stock';
                document.getElementById('stockId').value = id;
                fetch('stocks.php?action=get_stock&id=' + id).then(r => r.json()).then(d => {
                    if (d.success) {
                        document.getElementById('boutiqueSelect').value = d.stock.boutique_id;
                        document.getElementById('produitSearch').value = d.stock.produit_designation;
                        document.getElementById('produitMatriculeHidden').value = d.stock.produit_matricule;
                        document.querySelector('input[name="prix"]').value = d.stock.prix;
                        document.querySelector('input[name="quantite"]').value = d.stock.quantite;
                        document.querySelector('input[name="seuil_alerte_stock"]').value = d.stock.seuil_alerte_stock;
                        document.getElementById('uniteHidden').value = d.stock.umProduit;
                        const u = d.stock.umProduit === 'metres' ? 'mètres' : 'pièces';
                        document.getElementById('infoDesignation').textContent = d.stock.produit_designation;
                        document.getElementById('infoUnite').textContent = u;
                        document.getElementById('infoMatricule').textContent = d.stock.produit_matricule;
                        document.getElementById('produitInfo').classList.remove('hidden');
                        document.getElementById('qteLabel').textContent = u;
                        document.getElementById('seuilLabel').textContent = u;
                        
                        // Si le produit est en mètres, afficher le champ numéro
                        if (d.stock.umProduit === 'metres') {
                            document.getElementById('numeroRideauField').classList.remove('hidden');
                            filterNumerosByBoutique();
                            if (d.stock.numero_rideau) {
                                document.getElementById('numeroSearch').value = d.stock.numero_rideau;
                                // Trouver l'ID du numéro
                                const num = getNumerosForBoutique().find(n => n.numero_rideau === d.stock.numero_rideau);
                                if (num) {
                                    document.getElementById('numeroRideauHidden').value = num.id;
                                    document.getElementById('infoNumero').textContent = d.stock.numero_rideau;
                                    document.getElementById('numeroInfo').classList.remove('hidden');
                                }
                            }
                        }
                    }
                });
            } else {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle mr-2 text-blue-500"></i>Nouveau stock';
                document.getElementById('submitBtn').textContent = 'Enregistrer';
                document.getElementById('submitBtn').name = 'ajouter_stock';
                document.getElementById('stockId').value = '';
                document.getElementById('boutiqueSelect').selectedIndex = 0;
            }
            openModal('stockOverlay', 'stockContent');
        }

        function closeStockModal() {
            closeModal('stockOverlay', 'stockContent')
        }

        function openDeleteModal(id, boutique, produit) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteText').textContent = `Stock #${id} (${boutique} - ${produit}). Cette action est réversible uniquement par un administrateur.`;
            openModal('deleteOverlay', 'deleteContent');
        }

        function closeDeleteModal() {
            closeModal('deleteOverlay', 'deleteContent')
        }

        ['stockOverlay', 'deleteOverlay'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function(e) {
                if (e.target === this) closeModal(id, id.replace('Overlay', 'Content'))
            })
        });

        // Search
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const s = this.value.toLowerCase();
            let found = false;
            document.querySelectorAll('.stock-row').forEach(r => {
                const m = r.dataset.id.includes(s) || r.dataset.boutique.includes(s) || r.dataset.produit.includes(s);
                r.style.display = m ? '' : 'none';
                if (m) found = true;
            });
            document.getElementById('noResults')?.classList.toggle('hidden', found || s === '');
            document.getElementById('tableBody')?.classList.toggle('hidden', !found && s !== '');
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeStockModal();
                closeDeleteModal();
                if (sidebar.classList.contains('open')) toggleSidebar()
            }
        });
    </script>
    <?php unset($_SESSION['msg']); ?>
</body>

</html>