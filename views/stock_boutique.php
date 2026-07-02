<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    $_SESSION['flash_message'] = ['text' => 'Veuillez vous connecter', 'type' => 'error'];
    header('Location: ../login.php');
    exit;
}

$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    $_SESSION['flash_message'] = ['text' => 'Session invalide', 'type' => 'error'];
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';
$total_stocks = 0;
$stocks = [];
$boutique_info = null;

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Infos boutique
try {
    $queryBoutique = $pdo->prepare("SELECT id, nom, email, date_creation, actif FROM boutiques WHERE id = ? AND statut = 0");
    $queryBoutique->execute([$boutique_id]);
    $boutique_info = $queryBoutique->fetch(PDO::FETCH_ASSOC);
    if (!$boutique_info) {
        $_SESSION['flash_message'] = ['text' => "Boutique introuvable", 'type' => "error"];
        header('Location: ../login.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['text' => "Erreur : " . $e->getMessage(), 'type' => "error"];
    header('Location: ../login.php');
    exit;
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM stock s WHERE s.boutique_id = ? AND s.statut = 0");
    $countQuery->execute([$boutique_id]);
    $total_stocks = $countQuery->fetchColumn();
    $totalPages = ceil($total_stocks / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

    $query = $pdo->prepare("
        SELECT s.*, p.designation as produit_designation, p.umProduit as produit_unite, p.description as produit_description,
               nr.numero_rideau, nr.id as numero_rideau_id
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        LEFT JOIN numeros_rideaux nr ON s.numero_rideau_id = nr.id
        WHERE s.boutique_id = :bid AND s.statut = 0
        ORDER BY CASE WHEN s.quantite <= s.seuil_alerte_stock THEN 1 ELSE 2 END, s.date_creation DESC
        LIMIT :limit OFFSET :offset
    ");
    $query->bindValue(':bid', $boutique_id, PDO::PARAM_INT);
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $stocks = $query->fetchAll(PDO::FETCH_ASSOC);

    $queryStats = $pdo->prepare("
        SELECT COUNT(DISTINCT s.produit_matricule) as produits_diff, SUM(s.quantite) as qt_totale,
               SUM(s.quantite * s.prix) as val_totale, COUNT(CASE WHEN s.quantite <= s.seuil_alerte_stock THEN 1 END) as faible
        FROM stock s WHERE s.boutique_id = ? AND s.statut = 0
    ");
    $queryStats->execute([$boutique_id]);
    $stats = $queryStats->fetch(PDO::FETCH_ASSOC);
    $produits_differents = $stats['produits_diff'] ?? 0;
    $quantite_totale = $stats['qt_totale'] ?? 0;
    $valeur_totale = $stats['val_totale'] ?? 0;
    $produits_faible_stock = $stats['faible'] ?? 0;

    $queryStatsUnite = $pdo->prepare("
        SELECT p.umProduit, COUNT(DISTINCT s.produit_matricule) as nb, SUM(s.quantite) as qt, SUM(s.quantite * s.prix) as val
        FROM stock s JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE s.boutique_id = ? AND s.statut = 0 GROUP BY p.umProduit
    ");
    $queryStatsUnite->execute([$boutique_id]);
    $statsUnite = $queryStatsUnite->fetchAll(PDO::FETCH_ASSOC);
    $stats_metres = [];
    $stats_pieces = [];
    foreach ($statsUnite as $s) {
        if ($s['umProduit'] == 'metres') $stats_metres = $s;
        else $stats_pieces = $s;
    }
} catch (PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    $produits_differents = 0;
    $quantite_totale = 0;
    $valeur_totale = 0;
    $produits_faible_stock = 0;
    $stocks = [];
    $total_stocks = 0;
    $totalPages = 1;
    $stats_metres = [];
    $stats_pieces = [];
}

// Produits pour le modal (pour la recherche avec autocomplétion)
try {
    $queryProduits = $pdo->prepare("SELECT matricule, designation, umProduit FROM produits WHERE statut = 0 AND actif = 1 ORDER BY designation");
    $queryProduits->execute();
    $produits = $queryProduits->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $produits = [];
}

// Récupérer tous les numéros de rideaux disponibles pour cette boutique
$numeros_rideaux_disponibles = [];
try {
    $stmt = $pdo->prepare("SELECT id, numero_rideau FROM numeros_rideaux WHERE boutique_id = ? AND est_utilise = 0 AND actif = 1 ORDER BY numero_rideau");
    $stmt->execute([$boutique_id]);
    $numeros_rideaux_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $numeros_rideaux_disponibles = [];
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Stocks - <?= htmlspecialchars($boutique_info['nom']) ?> - NGS</title>

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

        .btn-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25);
        }

        .btn-green:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
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
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .dark .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .dark .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
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

        /* Style pour le champ de recherche de produit */
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

    <!-- SIDEBAR -->
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500 flex items-center justify-center shadow-lg"><span class="font-bold text-white">NGS</span></div>
                <div>
                    <h2 class="font-bold text-sm">NGS Pro</h2>
                    <p class="text-[10px] text-gray-400">Dashboard Boutique</p>
                </div>
            </div>
        </div>
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-500/20 border border-blue-400/30 flex items-center justify-center"><i class="fas fa-store text-blue-400"></i></div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($boutique_info['nom']) ?></p>
                    <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($boutique_info['email'] ?? '') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="stock_boutique.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Mes stocks<?php if ($total_stocks > 0): ?><span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_stocks ?></span><?php endif; ?></a>
            <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes</a>
            <a href="paiements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements</a>
            <a href="mouvements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements Caisse</a>
            <a href="transferts-boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-truck-loading w-4 text-center"></i>Transferts</a>
            <a href="rapports_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
            <a href="numeros_rideaux.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tags w-4 text-center"></i>N° Rideaux</a>
            <a href="realisations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations</a>
        </nav>
        <div class="p-3 border-t border-white/10 flex-shrink-0">
            <div class="flex items-center justify-between px-3 py-2 mb-2">
                <span class="text-xs text-gray-400"><i class="fas fa-moon mr-1"></i>Thème</span>
                <button id="theme-toggle" class="theme-toggle" aria-label="Changer le thème"></button>
            </div>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Mes stocks - <?= htmlspecialchars($boutique_info['nom']) ?></h1>
                        <p class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($boutique_info['email'] ?? '') ?> • Créée le <?= date('d/m/Y', strtotime($boutique_info['date_creation'])) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($produits_faible_stock > 0): ?>
                        <span class="px-3 py-1.5 rounded-full text-xs font-medium badge-warning flex items-center gap-1.5"><i class="fas fa-exclamation-triangle"></i><?= $produits_faible_stock ?> alerte(s)</span>
                    <?php endif; ?>
                    <button onclick="openStockModal()" class="btn-green px-4 py-2 rounded-xl text-sm flex items-center gap-2"><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Enregistrer un stock</span></button>
                    <button onclick="window.location.reload()" class="p-2 rounded-xl glass hover:bg-white/20 transition-all text-[var(--text-muted)]" title="Actualiser"><i class="fas fa-sync-alt text-sm"></i></button>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <?php if ($message): ?>
                <div class="animate-fade-in-up">
                    <div class="glass rounded-2xl p-4 border-l-4 <?= $message_type === 'success' ? 'border-emerald-500' : 'border-red-500' ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle text-emerald-500' : 'exclamation-circle text-red-500' ?> text-xl"></i>
                            <span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($message) ?></span>
                            <button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Total</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-boxes text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_stocks ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Stocks enregistrés</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Produits</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-box-open text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $produits_differents ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Types de produits</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-cyan-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-cyan-600 dark:text-cyan-400">Quantité</span>
                        <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center"><i class="fas fa-weight-hanging text-cyan-600 dark:text-cyan-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($quantite_totale, 3) ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Unités en stock</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-purple-600 dark:text-purple-400">Valeur</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-chart-line text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($valeur_totale, 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Valeur totale</p>
                </div>
            </div>

            <!-- Stats par unité -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in-up" style="animation-delay:0.15s">
                <?php if (!empty($stats_metres)): ?>
                    <div class="premium-card p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2"><i class="fas fa-ruler-combined text-blue-500"></i><span class="font-bold text-sm text-[var(--text-primary)]">Rideaux (mètres)</span></div>
                            <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">Mètres</span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Produits :</span><span class="font-medium text-[var(--text-primary)]"><?= $stats_metres['nb'] ?? 0 ?></span></div>
                            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Quantité :</span><span class="font-medium text-[var(--text-primary)]"><?= number_format($stats_metres['qt'] ?? 0, 3) ?> m</span></div>
                            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Valeur :</span><span class="font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($stats_metres['val'] ?? 0, 2) ?> $</span></div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($stats_pieces)): ?>
                    <div class="premium-card p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2"><i class="fas fa-cube text-emerald-500"></i><span class="font-bold text-sm text-[var(--text-primary)]">Produits (pièces)</span></div>
                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">Pièces</span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Produits :</span><span class="font-medium text-[var(--text-primary)]"><?= $stats_pieces['nb'] ?? 0 ?></span></div>
                            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Quantité :</span><span class="font-medium text-[var(--text-primary)]"><?= number_format($stats_pieces['qt'] ?? 0, 3) ?> pce</span></div>
                            <div class="flex justify-between"><span class="text-[var(--text-muted)]">Valeur :</span><span class="font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($stats_pieces['val'] ?? 0, 2) ?> $</span></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Alerte stock faible -->
            <?php if ($produits_faible_stock > 0): ?>
                <div class="animate-fade-in-up" style="animation-delay:0.2s">
                    <div class="glass rounded-2xl p-4 border-l-4 border-amber-500">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-amber-500 text-xl"></i>
                            <div>
                                <p class="font-bold text-sm text-[var(--text-primary)]"><?= $produits_faible_stock ?> produit(s) en stock faible</p>
                                <p class="text-xs text-[var(--text-muted)]">Pensez à réapprovisionner ces produits.</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recherche -->
            <div class="premium-card p-4 animate-fade-in-up" style="animation-delay:0.25s">
                <div class="flex items-center gap-3">
                    <div class="relative flex-1 max-w-md">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)]"></i>
                        <input type="text" id="searchInput" placeholder="Rechercher par produit, matricule..." class="w-full input-glass pl-10 pr-4 py-2.5 text-sm">
                    </div>
                    <span class="text-xs text-[var(--text-muted)] hidden sm:block">Page <?= $page ?>/<?= $totalPages ?></span>
                </div>
            </div>

            <!-- Tableau -->
            <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.3s">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px]" id="stocksTable">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">ID</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produit</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">N° Rideau</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Type</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Quantité</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Prix</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Valeur</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Seuil</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]" id="tableBody">
                            <?php if (!empty($stocks)): ?>
                                <?php foreach ($stocks as $s):
                                    $isLow = $s['quantite'] <= $s['seuil_alerte_stock'];
                                    $ut = $s['produit_unite'] == 'metres' ? 'mètres' : 'pièces';
                                    $valeur = $s['quantite'] * $s['prix'];
                                ?>
                                    <tr class="hover:bg-white/5 transition-colors stock-row <?= $isLow ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' ?>"
                                        data-designation="<?= strtolower($s['produit_designation']) ?>"
                                        data-matricule="<?= strtolower($s['produit_matricule']) ?>"
                                        data-mouvement="<?= strtolower($s['type_mouvement']) ?>">
                                        <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $s['id'] ?></td>
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
                                        <td class="px-5 py-3.5">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $s['type_mouvement'] == 'transfert' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' ?>">
                                                <?= $s['type_mouvement'] == 'transfert' ? 'Transfert' : 'Approvisionnement' ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm font-bold <?= $isLow ? 'text-amber-600 dark:text-amber-400' : 'text-[var(--text-primary)]' ?>">
                                            <?= number_format($s['quantite'], 3) ?> <span class="text-xs font-normal text-[var(--text-muted)]"><?= $ut ?></span>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]"><?= number_format($s['prix'], 2) ?> $</td>
                                        <td class="px-5 py-3.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400"><?= number_format($valeur, 2) ?> $</td>
                                        <td class="px-5 py-3.5">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $isLow ? 'badge-warning' : 'badge-success' ?>"><?= $isLow ? 'Faible' : 'OK' ?> (<?= $s['seuil_alerte_stock'] ?>)</span>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-muted)]"><?= date('d/m/Y H:i', strtotime($s['date_creation'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-5 py-12 text-center"><i class="fas fa-inbox text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                                        <p class="text-[var(--text-secondary)] font-medium">Aucun stock enregistré</p>
                                        <p class="text-xs text-[var(--text-muted)] mt-1">Utilisez le bouton "Enregistrer un stock"</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResults" class="hidden text-center py-12">
                    <i class="fas fa-search text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                    <p class="text-[var(--text-secondary)] font-medium">Aucun résultat trouvé</p>
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

    <!-- MODAL ENREGISTRER STOCK -->
    <div id="stockModalOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="stockModalContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-box mr-2 text-blue-500"></i>Enregistrer un stock</h3>
            <button onclick="closeStockModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form id="stockForm" action="../models/traitement/enregistrer_stock.php" method="POST" class="space-y-4">
            <input type="hidden" name="boutique_id" value="<?= $boutique_id ?>">
            <input type="hidden" name="produit_matricule" id="produitMatriculeHidden">
            <!-- Le champ numero_rideau_id est dans la div numeroRideauField ci-dessous -->

            <!-- Champ de recherche de produit avec autocomplétion -->
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Produit *</label>
                <div class="produit-search-wrapper">
                    <input type="text" id="produitSearch"
                        class="w-full input-glass px-3 py-2.5 text-sm"
                        placeholder="Tapez le nom du produit..."
                        autocomplete="off"
                        oninput="filterProduits()"
                        onfocus="filterProduits()">
                    <div id="produitSuggestions" class="produit-suggestions"></div>
                </div>
                <div id="produitInfo" class="hidden mt-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 text-xs">
                    <p><strong>Produit sélectionné :</strong> <span id="infoDesignation">-</span></p>
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
                <!-- ✅ UN SEUL champ caché pour l'ID du numéro de rideau -->
                <input type="hidden" name="numero_rideau_id" id="numeroRideauHidden" value="">
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

            <div class="p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/30 text-xs text-blue-700 dark:text-blue-300">
                <i class="fas fa-info-circle mr-1"></i>Type de mouvement : <strong>Approvisionnement</strong>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Quantité *</label>
                    <div class="relative">
                        <input type="number" name="quantite" step="0.001" min="0.001" required placeholder="0.000" class="w-full input-glass pl-4 pr-14 py-2.5 text-sm">
                        <span id="uniteLabel" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)]">pièces</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Prix unitaire ($) *</label>
                    <input type="number" name="prix" step="0.01" min="0.01" required placeholder="0.00" class="w-full input-glass px-4 py-2.5 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Seuil d'alerte *</label>
                <div class="flex items-center gap-2">
                    <input type="number" name="seuil_alerte_stock" value="5" min="1" required class="w-full input-glass px-4 py-2.5 text-sm">
                    <span id="seuilLabel" class="text-xs text-[var(--text-muted)] whitespace-nowrap">unités</span>
                </div>
                <p class="text-xs text-[var(--text-muted)] mt-1">Alerte quand le stock atteint cette quantité</p>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeStockModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" id="submitStockBtn" class="btn-green px-5 py-2.5 rounded-xl text-sm"><i class="fas fa-save mr-1.5"></i>Enregistrer</button>
            </div>
        </form>
    </div>

    <script>
        // Données des produits (passées depuis PHP)
        const produitsData = <?= json_encode($produits) ?>;

        // Données des numéros de rideaux disponibles
        const numerosData = <?= json_encode($numeros_rideaux_disponibles) ?>;

        // Theme
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) html.classList.add('dark');
        themeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
        });

        // Sidebar
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('hidden');
        }
        document.getElementById('mobileMenuBtn').addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Modal
        function openStockModal() {
            resetStockForm();
            document.getElementById('stockModalOverlay').classList.remove('hidden');
            document.getElementById('stockModalContent').classList.remove('hidden');
            setTimeout(() => document.getElementById('produitSearch').focus(), 200);
        }

        function closeStockModal() {
            document.getElementById('stockModalOverlay').classList.add('hidden');
            document.getElementById('stockModalContent').classList.add('hidden');
        }

        function resetStockForm() {
            document.getElementById('produitSearch').value = '';
            document.getElementById('produitMatriculeHidden').value = '';
            document.getElementById('numeroSearch').value = '';
            document.getElementById('numeroRideauHidden').value = '';
            document.getElementById('produitInfo').classList.add('hidden');
            document.getElementById('numeroInfo').classList.add('hidden');
            document.getElementById('numeroRideauField').classList.add('hidden');
            document.getElementById('uniteLabel').textContent = 'pièces';
            document.getElementById('seuilLabel').textContent = 'unités';
            document.getElementById('produitSuggestions').classList.remove('show');
            document.getElementById('numeroSuggestions').classList.remove('show');
            document.getElementById('submitStockBtn').disabled = false;
            // Réinitialiser le formulaire
            const form = document.getElementById('stockForm');
            if (form) {
                form.querySelectorAll('input[type="number"]').forEach(input => {
                    if (input.name !== 'seuil_alerte_stock') {
                        input.value = '';
                    }
                });
            }
        }

        document.getElementById('stockModalOverlay')?.addEventListener('click', function(e) {
            if (e.target === this) closeStockModal();
        });

        // Filtrer les produits dans la recherche
        function filterProduits() {
            const searchTerm = document.getElementById('produitSearch').value.toLowerCase();
            const suggestions = document.getElementById('produitSuggestions');

            if (searchTerm.length === 0) {
                suggestions.classList.remove('show');
                return;
            }

            const filtered = produitsData.filter(p =>
                p.designation.toLowerCase().includes(searchTerm)
            );

            if (filtered.length === 0) {
                suggestions.innerHTML = '<div class="produit-suggestion-item text-[var(--text-muted)]">Aucun produit trouvé</div>';
            } else {
                suggestions.innerHTML = filtered.map(p =>
                    `<div class="produit-suggestion-item" onclick="selectProduit('${p.matricule}', '${p.designation.replace(/'/g, "\\'")}', '${p.umProduit}')">
                    ${p.designation} <span class="text-xs text-[var(--text-muted)]">(${p.umProduit === 'metres' ? 'mètres' : 'pièces'})</span>
                </div>`
                ).join('');
            }
            suggestions.classList.add('show');
        }

        // Sélectionner un produit
        function selectProduit(matricule, designation, unite) {
            document.getElementById('produitSearch').value = designation;
            document.getElementById('produitMatriculeHidden').value = matricule;
            document.getElementById('produitSuggestions').classList.remove('show');

            const u = unite === 'metres' ? 'mètres' : 'pièces';
            document.getElementById('infoDesignation').textContent = designation;
            document.getElementById('infoUnite').textContent = u;
            document.getElementById('infoMatricule').textContent = matricule;
            document.getElementById('produitInfo').classList.remove('hidden');

            document.getElementById('uniteLabel').textContent = u;
            document.getElementById('seuilLabel').textContent = u;

            // Afficher/masquer le champ numéro de rideau (seulement pour les mètres)
            const numeroField = document.getElementById('numeroRideauField');
            if (unite === 'metres') {
                numeroField.classList.remove('hidden');
                // Vérifier si des numéros sont disponibles
                if (numerosData.length === 0) {
                    document.getElementById('submitStockBtn').disabled = true;
                    document.getElementById('numeroSearch').placeholder = 'Aucun numéro disponible';
                } else {
                    document.getElementById('submitStockBtn').disabled = false;
                    document.getElementById('numeroSearch').placeholder = 'Tapez ou sélectionnez un numéro de rideau...';
                    // Si un seul numéro disponible, le sélectionner automatiquement
                    if (numerosData.length === 1) {
                        selectNumero(numerosData[0].id, numerosData[0].numero_rideau);
                    }
                }
                // Filtrer les suggestions de numéros
                filterNumeros();
            } else {
                numeroField.classList.add('hidden');
                document.getElementById('submitStockBtn').disabled = false;
                document.getElementById('numeroRideauHidden').value = '';
            }
        }

        // Filtrer les numéros de rideaux
        function filterNumeros() {
            const searchTerm = document.getElementById('numeroSearch').value.toLowerCase();
            const suggestions = document.getElementById('numeroSuggestions');
            const field = document.getElementById('numeroRideauField');

            if (!field.classList.contains('hidden') && numerosData.length > 0) {
                // Filtrer les numéros disponibles par recherche
                const filtered = numerosData.filter(n =>
                    n.numero_rideau.toLowerCase().includes(searchTerm)
                );

                if (filtered.length === 0) {
                    suggestions.innerHTML = '<div class="numero-suggestion-item text-[var(--text-muted)]">Aucun numéro disponible</div>';
                } else {
                    suggestions.innerHTML = filtered.map(n =>
                        `<div class="numero-suggestion-item" onclick="selectNumero('${n.id}', '${n.numero_rideau}')">
                        <i class="fas fa-tag text-amber-500 mr-2"></i>${n.numero_rideau}
                        <span class="text-xs text-[var(--text-muted)] ml-2">(ID: ${n.id})</span>
                    </div>`
                    ).join('');
                }
                suggestions.classList.add('show');
            } else {
                suggestions.classList.remove('show');
            }
        }

        // Sélectionner un numéro de rideau - On envoie l'ID du numéro sélectionné
        function selectNumero(id, numero) {
            // Mettre à jour l'affichage
            document.getElementById('numeroSearch').value = numero;

            // ✅ ICI on stocke l'ID dans le champ caché, pas le numéro
            document.getElementById('numeroRideauHidden').value = id;
            document.getElementById('numeroSuggestions').classList.remove('show');

            // Afficher les informations
            document.getElementById('infoNumero').textContent = numero;
            document.getElementById('numeroInfo').classList.remove('hidden');
            document.getElementById('submitStockBtn').disabled = false;

            console.log('✅ Numéro sélectionné:', numero, 'ID:', id);
            console.log('✅ Valeur du champ caché numeroRideauHidden:', document.getElementById('numeroRideauHidden').value);
        }

        // Fermer les suggestions au clic ailleurs
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.produit-search-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('produitSuggestions').classList.remove('show');
            }
            const numeroWrapper = document.querySelector('.numero-search-wrapper');
            if (numeroWrapper && !numeroWrapper.contains(e.target)) {
                document.getElementById('numeroSuggestions').classList.remove('show');
            }
        });

        // Formulaire AJAX - Version corrigée avec gestion d'erreur améliorée
        document.getElementById('stockForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Vérifier qu'un produit est sélectionné
            if (!document.getElementById('produitMatriculeHidden').value) {
                console.warn('Veuillez sélectionner un produit dans la liste');
                document.getElementById('produitSearch').focus();
                return;
            }

            // Vérifier qu'un numéro de rideau est sélectionné si le champ est visible
            const numeroField = document.getElementById('numeroRideauField');
            if (!numeroField.classList.contains('hidden')) {
                const numeroId = document.getElementById('numeroRideauHidden').value;
                if (!numeroId || numeroId === '') {
                    console.warn('Veuillez sélectionner un numéro de rideau');
                    document.getElementById('numeroSearch').focus();
                    return;
                }
            }

            // Désactiver le bouton pendant l'envoi
            const btn = this.querySelector('button[type="submit"]');
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Enregistrement...';

            // Récupérer les données du formulaire
            const formData = new FormData(this);

            // Afficher les données envoyées dans la console pour débogage
            console.log('📦 Données envoyées au serveur:');
            for (let [key, value] of formData.entries()) {
                console.log('  ' + key + ': ' + value);
            }

            // Récupérer l'URL d'action
            const actionUrl = this.action;
            console.log('🔗 URL de la requête:', actionUrl);

            // Envoyer la requête
            fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('📡 Statut HTTP:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ' - ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('📥 Réponse du serveur:', data);
                    if (data.success) {
                        // Fermer le modal
                        closeStockModal();
                    } else {
                        console.warn('⚠️ Enregistrement non réussi:', data.message);
                    }

                    btn.disabled = false;
                    btn.innerHTML = orig;

                    window.location.href = data.redirect || 'stock_boutique.php';
                })
                .catch((error) => {
                    console.error('❌ Erreur réseau détaillée:', error);

                    // Afficher une erreur plus détaillée
                    let errorMsg = 'Erreur réseau: ' + error.message;
                    errorMsg += '\n\nVérifiez que le fichier ' + actionUrl + ' existe et est accessible.';
                    errorMsg += '\n\nChemin relatif depuis cette page: ' + window.location.pathname;

                    btn.disabled = false;
                    btn.innerHTML = orig;

                    window.location.href = 'stock_boutique.php';
                });
        });

        // Search dans le tableau
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const s = this.value.toLowerCase();
            let found = false;
            document.querySelectorAll('.stock-row').forEach(r => {
                const m = r.dataset.designation.includes(s) || r.dataset.matricule.includes(s) || r.dataset.mouvement.includes(s);
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
                if (sidebar.classList.contains('open')) toggleSidebar();
            }
        });

        // Fonction pour définir la quantité maximale
        function setMaxQte() {
            const stockId = document.getElementById('stockHidden')?.value;
            if (!stockId) {
                console.warn('Veuillez d\'abord sélectionner un produit');
                return;
            }
            // Cette fonction peut être utilisée pour définir la quantité maximale disponible
            console.log('Stock ID:', stockId);
        }
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>