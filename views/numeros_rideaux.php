<?php
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Accessible par PDG et Boutique
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['pdg', 'boutique'])) {
    header('Location: ../login.php');
    exit;
}

$user_type = $_SESSION['user_type'];
$boutique_id = $_SESSION['boutique_id'] ?? null;
$is_pdg = $user_type === 'pdg';

$message = '';
$message_type = '';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// --- CRÉER UN LOT DE 10 NUMÉROS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_lot'])) {
    try {
        $boutique_id_form = $is_pdg ? (int)$_POST['boutique_id'] : $boutique_id;

        if ($boutique_id_form <= 0) throw new Exception("Boutique invalide");

        // Trouver le dernier numéro pour cette boutique
        $stmt = $pdo->prepare("SELECT numero_rideau FROM numeros_rideaux WHERE boutique_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$boutique_id_form]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last) {
            preg_match('/\d+/', $last['numero_rideau'], $matches);
            $lastNumber = !empty($matches) ? (int)$matches[0] : 0;
        } else {
            $lastNumber = 0;
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO numeros_rideaux (boutique_id, numero_rideau) VALUES (?, ?)");

        for ($i = 1; $i <= 10; $i++) {
            $nextNumber = $lastNumber + $i;
            $numero = 'R' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $stmt->execute([$boutique_id_form, $numero]);
        }

        $pdo->commit();
        $_SESSION['flash_message'] = ['text' => "Lot de 10 numéros créé avec succès (R" . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT) . " à R" . str_pad($lastNumber + 10, 3, '0', STR_PAD_LEFT) . ") !", 'type' => "success"];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_message'] = ['text' => "Erreur : " . $e->getMessage(), 'type' => "error"];
    }
    header("Location: numeros_rideaux.php");
    exit;
}

// --- SUPPRIMER UN NUMÉRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_numero'])) {
    $numero_id = (int)$_POST['numero_id'];
    $check = $pdo->prepare("SELECT nr.id, s.id as stock_id FROM numeros_rideaux nr LEFT JOIN stock s ON s.numero_rideau_id = nr.id AND s.statut = 0 WHERE nr.id = ?");
    $check->execute([$numero_id]);
    $num = $check->fetch(PDO::FETCH_ASSOC);

    if ($num && $num['stock_id']) {
        $_SESSION['flash_message'] = ['text' => "Impossible de supprimer : ce numéro est attribué à un stock.", 'type' => "error"];
    } else {
        $pdo->prepare("UPDATE numeros_rideaux SET actif = 0 WHERE id = ?")->execute([$numero_id]);
        $_SESSION['flash_message'] = ['text' => "Numéro supprimé avec succès.", 'type' => "success"];
    }
    header("Location: numeros_rideaux.php");
    exit;
}

// --- RÉCUPÉRATION DES DONNÉES ---
// CORRECTION : utiliser directement boutique_id dans les requêtes au lieu d'alias ambigus
$boutique_filter_sql = $is_pdg ? "" : "AND nr.boutique_id = " . (int)$boutique_id;

// Stats
$total_numeros = $pdo->query("SELECT COUNT(*) FROM numeros_rideaux nr WHERE nr.actif = 1 $boutique_filter_sql")->fetchColumn();
$numeros_disponibles = $pdo->query("SELECT COUNT(*) FROM numeros_rideaux nr WHERE nr.actif = 1 AND nr.est_utilise = 0 $boutique_filter_sql")->fetchColumn();
$numeros_utilises = $pdo->query("SELECT COUNT(*) FROM numeros_rideaux nr WHERE nr.actif = 1 AND nr.est_utilise = 1 $boutique_filter_sql")->fetchColumn();

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$totalPages = ceil($total_numeros / $limit);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$numeros = $pdo->query("
    SELECT nr.*, b.nom as boutique_nom, 
           CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as est_attribue,
           p.designation as produit_designation
    FROM numeros_rideaux nr 
    JOIN boutiques b ON nr.boutique_id = b.id
    LEFT JOIN stock s ON s.numero_rideau_id = nr.id AND s.statut = 0
    LEFT JOIN produits p ON s.produit_matricule = p.matricule
    WHERE nr.actif = 1 $boutique_filter_sql
    ORDER BY nr.numero_rideau ASC
    LIMIT $limit OFFSET $offset
")->fetchAll();

// Liste des boutiques pour le PDG
$boutiques = $is_pdg ? $pdo->query("SELECT id, nom FROM boutiques WHERE statut = 0 AND actif = 1 ORDER BY nom")->fetchAll() : [];
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Numéros de Rideaux - NGS</title>

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
            border-color: #f59e0b;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
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

        .btn-amber {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.25);
        }

        .btn-amber:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
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
            border-left: 3px solid #fbbf24;
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

        *:focus-visible {
            outline: 2px solid #f59e0b;
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

    <!-- SIDEBAR -->
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center shadow-lg"><span class="font-bold text-white">NGS</span></div>
                <div>
                    <h2 class="font-bold text-sm">NGS Pro</h2>
                    <p class="text-[10px] text-gray-400">Dashboard <?= $is_pdg ? 'PDG' : 'Boutique' ?></p>
                </div>
            </div>
        </div>
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-amber-500/20 border border-amber-400/30 flex items-center justify-center"><i class="fas fa-<?= $is_pdg ? 'crown text-amber-400' : 'store text-blue-400' ?>"></i></div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= $is_pdg ? 'Directeur Général' : htmlspecialchars($_SESSION['boutique_nom'] ?? 'Boutique') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <?php if ($is_pdg): ?>
                <a href="dashboard_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
                <a href="boutiques.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-store w-4 text-center"></i>Boutiques</a>
                <a href="produits.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-box w-4 text-center"></i>Produits</a>
                <a href="stocks.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Stocks</a>
                <a href="transferts.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Transferts</a>
                <a href="utilisateurs.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-users w-4 text-center"></i>Utilisateurs</a>
                <a href="rapports_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
                <a href="realisations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations</a>
            <?php else: ?>
                <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
                <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Mes stocks</a>
                <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes</a>
                <a href="paiements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements</a>
                <a href="mouvements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements Caisse</a>
                <a href="transferts-boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-truck-loading w-4 text-center"></i>Transferts</a>
                <a href="rapports_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
                <a href="realisations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations</a>
            <?php endif; ?>
            <a href="numeros_rideaux.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tags w-4 text-center"></i>N° Rideaux<?php if ($total_numeros > 0): ?><span class="ml-auto bg-amber-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_numeros ?></span><?php endif; ?></a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Numéros de Rideaux</h1>
                        <p class="text-xs text-[var(--text-muted)]">Gestion des numéros par lot de 10</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openCreerLotModal()" class="btn-amber px-4 py-2 rounded-xl text-sm flex items-center gap-2"><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Créer un lot de 10</span></button>
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
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-amber-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-amber-600 dark:text-amber-400">Total</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center"><i class="fas fa-tags text-amber-600 dark:text-amber-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_numeros ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Numéros créés</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Disponibles</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-check-circle text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $numeros_disponibles ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Libres</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Utilisés</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-tag text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $numeros_utilises ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Attribués</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-purple-600 dark:text-purple-400">Page</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-file-alt text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $page ?>/<?= $totalPages ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Pagination</p>
                </div>
            </div>

            <!-- Tableau -->
            <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.15s">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px]">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Numéro</th>
                                <?php if ($is_pdg): ?><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Boutique</th><?php endif; ?>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Statut</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Attribué à</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date création</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]">
                            <?php if (!empty($numeros)): ?>
                                <?php foreach ($numeros as $n): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-5 py-3.5">
                                            <span class="text-sm font-mono font-bold text-[var(--text-primary)]"><?= htmlspecialchars($n['numero_rideau']) ?></span>
                                        </td>
                                        <?php if ($is_pdg): ?>
                                            <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($n['boutique_nom']) ?></td>
                                        <?php endif; ?>
                                        <td class="px-5 py-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $n['est_utilise'] ? 'badge-warning' : 'badge-success' ?>">
                                                <?= $n['est_utilise'] ? 'Utilisé' : 'Disponible' ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]">
                                            <?= $n['est_attribue'] ? htmlspecialchars($n['produit_designation'] ?? 'Attribué') : '-' ?>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-muted)]"><?= date('d/m/Y H:i', strtotime($n['date_creation'])) ?></td>
                                        <td class="px-5 py-3.5 text-center">
                                            <?php if (!$n['est_utilise'] && !$n['est_attribue']): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Supprimer ce numéro ?')" class="inline">
                                                    <input type="hidden" name="numero_id" value="<?= $n['id'] ?>">
                                                    <button type="submit" name="supprimer_numero" class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Supprimer"><i class="fas fa-trash text-xs"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-xs text-[var(--text-muted)]">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= $is_pdg ? 6 : 5 ?>" class="px-5 py-12 text-center"><i class="fas fa-tags text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                                        <p class="text-[var(--text-secondary)] font-medium">Aucun numéro créé</p>
                                        <p class="text-xs text-[var(--text-muted)] mt-1">Utilisez le bouton "Créer un lot de 10"</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="px-5 py-4 border-t border-[var(--divider)] flex items-center justify-between">
                        <span class="text-xs text-[var(--text-muted)] hidden sm:block"><?= ($page - 1) * $limit + 1 ?>-<?= min($page * $limit, $total_numeros) ?> sur <?= $total_numeros ?></span>
                        <div class="flex items-center gap-1.5 mx-auto sm:mx-0">
                            <a href="?page=<?= max(1, $page - 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-left text-xs"></i></a>
                            <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                <a href="?page=<?= $i ?>" class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i == $page ? 'btn-amber shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="?page=<?= min($totalPages, $page + 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-right text-xs"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- MODAL CRÉER LOT -->
    <div id="creerLotOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="creerLotContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-tags text-5xl text-amber-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Créer un lot de 10 numéros</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6">
            Prochain lot : <strong>R<?= str_pad($total_numeros + 1, 3, '0', STR_PAD_LEFT) ?></strong> à <strong>R<?= str_pad($total_numeros + 10, 3, '0', STR_PAD_LEFT) ?></strong>
        </p>
        <form method="POST" action="" class="space-y-4">
            <?php if ($is_pdg): ?>
                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5 text-left">Boutique *</label>
                    <select name="boutique_id" required class="w-full input-glass px-3 py-2.5 text-sm">
                        <?php foreach ($boutiques as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/30 text-xs text-amber-700 dark:text-amber-300">
                <i class="fas fa-info-circle mr-1"></i>10 numéros seront créés automatiquement au format <strong>R001, R002...</strong>
            </div>
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeCreerLotModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" name="creer_lot" class="btn-amber px-5 py-2.5 rounded-xl text-sm">Créer le lot</button>
            </div>
        </form>
    </div>

    <script>
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

        // Modal
        function openCreerLotModal() {
            document.getElementById('creerLotOverlay').classList.remove('hidden');
            document.getElementById('creerLotContent').classList.remove('hidden')
        }

        function closeCreerLotModal() {
            document.getElementById('creerLotOverlay').classList.add('hidden');
            document.getElementById('creerLotContent').classList.add('hidden')
        }
        document.getElementById('creerLotOverlay')?.addEventListener('click', function(e) {
            if (e.target === this) closeCreerLotModal()
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeCreerLotModal();
                if (sidebar.classList.contains('open')) toggleSidebar()
            }
        });
    </script>
    <?php unset($_SESSION['msg']); ?>
</body>

</html>