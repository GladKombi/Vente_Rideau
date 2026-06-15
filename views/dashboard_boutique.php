<?php
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

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

try {
    $stmt = $pdo->prepare("SELECT * FROM boutiques WHERE id = ? AND statut = 0");
    $stmt->execute([$boutique_id]);
    $boutique = $stmt->fetch();
    if (!$boutique) {
        session_destroy();
        header('Location: ../login.php');
        exit;
    }

    // CA total
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(p.montant), 0) FROM paiements p WHERE p.statut = 0");
    $stmt->execute();
    $stats['ca_total'] = $stmt->fetchColumn();

    // CA mois
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(p.montant), 0) FROM paiements p WHERE p.statut = 0 AND MONTH(p.date)=MONTH(CURDATE()) AND YEAR(p.date)=YEAR(CURDATE())");
    $stmt->execute();
    $stats['ca_mois'] = $stmt->fetchColumn();

    // CA semaine
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(p.montant), 0) FROM paiements p WHERE p.statut = 0 AND YEARWEEK(p.date,1)=YEARWEEK(CURDATE(),1)");
    $stmt->execute();
    $stats['ca_semaine'] = $stmt->fetchColumn();

    // CA jour
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(p.montant), 0) FROM paiements p WHERE p.statut = 0 AND DATE(p.date)=CURDATE()");
    $stmt->execute();
    $stats['ca_jour'] = $stmt->fetchColumn();

    // Commandes
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT p.commandes_id) as total, SUM(CASE WHEN DATE(p.date)=CURDATE() THEN 1 ELSE 0 END) as aujourdhui FROM paiements p WHERE p.statut = 0");
    $stmt->execute();
    $r = $stmt->fetch();
    $stats['commandes_total'] = $r['total'] ?? 0;
    $stats['commandes_jour'] = $r['aujourdhui'] ?? 0;

    // Stock
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT produit_matricule) FROM stock WHERE boutique_id=? AND statut=0 AND quantite>0");
    $stmt->execute([$boutique_id]);
    $stats['produits_stock'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE boutique_id=? AND statut=0 AND quantite<=seuil_alerte_stock AND quantite>0");
    $stmt->execute([$boutique_id]);
    $stats['alertes_stock'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite*prix),0) FROM stock WHERE boutique_id=? AND statut=0 AND quantite>0");
    $stmt->execute([$boutique_id]);
    $stats['valeur_stock'] = $stmt->fetchColumn();

    // Produits stock bas
    $stmt = $pdo->prepare("SELECT s.*, p.designation, p.matricule, p.umProduit FROM stock s JOIN produits p ON s.produit_matricule=p.matricule WHERE s.boutique_id=? AND s.statut=0 AND s.quantite<=s.seuil_alerte_stock ORDER BY s.quantite ASC LIMIT 10");
    $stmt->execute([$boutique_id]);
    $produits_stock_bas = $stmt->fetchAll();

    // Produits populaires
    $stmt = $pdo->prepare("SELECT p.matricule, p.designation, p.umProduit, COALESCE(SUM(cp.quantite),0) as total_vendu, COALESCE(SUM(cp.quantite*cp.prix_unitaire),0) as ca, (SELECT quantite FROM stock WHERE produit_matricule=p.matricule AND boutique_id=? AND statut=0 LIMIT 1) as stock_actuel FROM commande_produits cp JOIN stock s ON cp.stock_id=s.id JOIN produits p ON s.produit_matricule=p.matricule WHERE s.boutique_id=? AND cp.statut=0 GROUP BY p.matricule ORDER BY total_vendu DESC LIMIT 8");
    $stmt->execute([$boutique_id, $boutique_id]);
    $produits_populaires = $stmt->fetchAll();

    // Mouvements récents
    $stmt = $pdo->prepare("(SELECT 'vente' as type, cp.quantite, p.designation, 'Sortie' as sens, DATE_FORMAT((SELECT MAX(date) FROM paiements WHERE commandes_id=cp.commande_id),'%d/%m %H:%i') as date_str FROM commande_produits cp JOIN stock s ON cp.stock_id=s.id JOIN produits p ON s.produit_matricule=p.matricule WHERE s.boutique_id=? AND cp.statut=0 ORDER BY (SELECT MAX(date) FROM paiements WHERE commandes_id=cp.commande_id) DESC LIMIT 5) UNION ALL (SELECT 'approvisionnement', s.quantite, p.designation, 'Entrée', DATE_FORMAT(s.date_creation,'%d/%m %H:%i') FROM stock s JOIN produits p ON s.produit_matricule=p.matricule WHERE s.boutique_id=? AND s.statut=0 AND s.type_mouvement='approvisionnement' ORDER BY s.date_creation DESC LIMIT 5) ORDER BY date_str DESC LIMIT 10");
    $stmt->execute([$boutique_id, $boutique_id]);
    $mouvements_recent = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur dashboard: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dashboard - <?= htmlspecialchars($boutique['nom'] ?? 'NGS') ?></title>

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

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
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

        .dark .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
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

        @keyframes numberUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-number {
            animation: numberUp 0.6s ease-out forwards;
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
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($boutique['nom'] ?? 'Boutique') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_boutique.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-warehouse w-4 text-center"></i>Mes stocks
                <?php if ($stats['alertes_stock'] > 0): ?><span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $stats['alertes_stock'] ?></span><?php endif; ?>
            </a>
            <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes</a>
            <a href="paiements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements</a>
            <a href="mouvements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements Caisse</a>
            <a href="transferts-boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-truck-loading w-4 text-center"></i>Transferts</a>
            <a href="rapports_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
            <a href="realisations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations</a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Tableau de bord</h1>
                        <p class="text-xs text-[var(--text-muted)]"><i class="far fa-calendar-alt mr-1"></i><?= date('d/m/Y') ?> • <span id="clock"><?= date('H:i') ?></span></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($stats['alertes_stock'] > 0): ?>
                        <a href="stock_boutique.php" class="px-3 py-2 rounded-xl bg-gradient-to-r from-red-500 to-orange-500 text-white text-xs font-semibold flex items-center gap-1.5 shadow-md hover:opacity-90 transition-opacity">
                            <i class="fas fa-exclamation-triangle"></i><?= $stats['alertes_stock'] ?> alerte(s)
                        </a>
                    <?php endif; ?>
                    <a href="ventes_boutique.php" class="btn-green px-4 py-2 rounded-xl text-sm flex items-center gap-2"><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouvelle vente</span></a>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <!-- Stats KPI -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-purple-600 dark:text-purple-400">Ce mois</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-euro-sign text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)] animate-number"><?= number_format($stats['ca_mois'], 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Chiffre d'affaires</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Aujourd'hui</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-shopping-cart text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)] animate-number"><?= $stats['commandes_jour'] ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Commandes</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Valeur</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-boxes text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)] animate-number"><?= number_format($stats['valeur_stock'], 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Stock boutique</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-amber-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-amber-600 dark:text-amber-400">En stock</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center"><i class="fas fa-box text-amber-600 dark:text-amber-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)] animate-number"><?= $stats['produits_stock'] ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Produits différents</p>
                </div>
            </div>

            <!-- Tableaux -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Alertes stock -->
                <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.15s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-[var(--text-primary)]"><i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i>Alertes stock</h2>
                        <a href="stock_boutique.php" class="text-xs text-blue-500 hover:text-blue-600 font-medium">Gérer →</a>
                    </div>
                    <?php if (!empty($produits_stock_bas)): ?>
                        <div class="space-y-2">
                            <?php foreach ($produits_stock_bas as $p): ?>
                                <div class="flex items-center justify-between p-3 rounded-xl <?= $p['quantite'] == 0 ? 'bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/30' : 'bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/30' ?>">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-[var(--text-primary)] truncate"><?= htmlspecialchars($p['designation']) ?></p>
                                        <p class="text-xs text-[var(--text-muted)]">Réf: <?= $p['matricule'] ?> • <?= $p['umProduit'] ?></p>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-3">
                                        <span class="text-lg font-bold <?= $p['quantite'] == 0 ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' ?>"><?= $p['quantite'] ?></span>
                                        <p class="text-xs text-[var(--text-muted)]">Seuil: <?= $p['seuil_alerte_stock'] ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-4xl text-emerald-400 mb-3"></i>
                            <p class="text-[var(--text-secondary)]">Aucune alerte stock</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Produits populaires -->
                <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-[var(--text-primary)]"><i class="fas fa-fire text-orange-500 mr-2"></i>Produits populaires</h2>
                        <span class="text-xs text-[var(--text-muted)]">Top ventes</span>
                    </div>
                    <?php if (!empty($produits_populaires)): ?>
                        <div class="space-y-2">
                            <?php foreach ($produits_populaires as $p): ?>
                                <div class="flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-colors">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-[var(--text-primary)] truncate"><?= htmlspecialchars($p['designation']) ?></p>
                                        <p class="text-xs text-[var(--text-muted)]">Réf: <?= $p['matricule'] ?> • <?= $p['umProduit'] ?></p>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-3">
                                        <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= $p['total_vendu'] ?> vendus</span>
                                        <p class="text-xs text-[var(--text-muted)]"><?= number_format($p['ca'], 2) ?> $</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-chart-line text-4xl text-[var(--text-muted)] opacity-30 mb-3"></i>
                            <p class="text-[var(--text-secondary)]">Aucune donnée de vente</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Mouvements récents -->
            <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.25s">
                <h2 class="text-base font-bold text-[var(--text-primary)] mb-4"><i class="fas fa-history text-blue-500 mr-2"></i>Mouvements récents</h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px]">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Type</th>
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produit</th>
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Qté</th>
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Sens</th>
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]">
                            <?php if (!empty($mouvements_recent)): ?>
                                <?php foreach ($mouvements_recent as $m): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $m['type'] == 'vente' ? 'badge-success' : 'badge-info' ?>"><?= ucfirst($m['type']) ?></span></td>
                                        <td class="px-4 py-3 text-sm text-[var(--text-primary)]"><?= htmlspecialchars($m['designation']) ?></td>
                                        <td class="px-4 py-3 text-sm font-medium text-[var(--text-primary)]"><?= $m['quantite'] ?></td>
                                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $m['sens'] == 'Entrée' ? 'badge-success' : 'badge-danger' ?>"><?= $m['sens'] ?></span></td>
                                        <td class="px-4 py-3 text-sm text-[var(--text-muted)]"><?= $m['date_str'] ?? '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-[var(--text-muted)]">Aucun mouvement récent</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-fade-in-up" style="animation-delay:0.3s">
                <a href="ventes_boutique.php" class="premium-card p-5 text-center hover:border-emerald-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                        <i class="fas fa-cash-register text-emerald-600 dark:text-emerald-400"></i>
                    </div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Nouvelle vente</span>
                </a>
                <a href="stock_boutique.php" class="premium-card p-5 text-center hover:border-blue-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                        <i class="fas fa-boxes text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Gestion stock</span>
                </a>
                <a href="paiements.php" class="premium-card p-5 text-center hover:border-purple-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                        <i class="fas fa-money-bill-wave text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Paiements</span>
                </a>
                <a href="rapports_boutique.php" class="premium-card p-5 text-center hover:border-amber-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                        <i class="fas fa-chart-bar text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Rapports</span>
                </a>
            </div>

        </div>
    </main>

    <script>
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

        // Clock
        function updateClock() {
            document.getElementById('clock').textContent = new Date().toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        setInterval(updateClock, 30000);
        updateClock();

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) toggleSidebar();
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>