<?php
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'PDG') {
    header('Location: ../login.php');
    exit;
}

$total_boutiques = 0;
$ca_total = 0;
$produits_total = 0;
$alertes_stock = 0;
$dernieres_ventes = [];
$boutiques_stats = [];
$produits_populaires = [];
$ca_mois_courant = 0;
$ca_mois_precedent = 0;
$variation_ca = 0;
$total_produits_actifs = 0;

try {
    $total_boutiques = $pdo->query("SELECT COUNT(*) FROM boutiques WHERE statut=0 AND actif=1")->fetchColumn();
    $ca_total = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut=0")->fetchColumn();
    $produits_total = $pdo->query("SELECT COUNT(DISTINCT produit_matricule) FROM stock WHERE quantite>0 AND statut=0")->fetchColumn();
    $alertes_stock = $pdo->query("SELECT COUNT(*) FROM stock WHERE quantite<=seuil_alerte_stock AND quantite>0 AND statut=0")->fetchColumn();
    $total_produits_actifs = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut=0 AND actif=1")->fetchColumn();

    $dernieres_ventes = $pdo->query("SELECT p.id, p.date, p.montant, b.nom as boutique_nom FROM paiements p JOIN commandes c ON p.commandes_id=c.id JOIN boutiques b ON c.boutique_id=b.id WHERE p.statut=0 ORDER BY p.date DESC LIMIT 5")->fetchAll();

    $boutiques_stats = $pdo->query("SELECT b.*, (SELECT COUNT(*) FROM stock WHERE boutique_id=b.id AND quantite>0 AND statut=0) as produits_en_stock, (SELECT COUNT(*) FROM stock WHERE boutique_id=b.id AND quantite<=seuil_alerte_stock AND quantite>0 AND statut=0) as alertes_boutique FROM boutiques b WHERE b.statut=0 AND b.actif=1 ORDER BY b.nom")->fetchAll();

    $produits_populaires = $pdo->query("SELECT p.matricule, p.designation, p.umProduit, COALESCE(SUM(cp.quantite),0) as total_vendu, COALESCE(SUM(cp.quantite*cp.prix_unitaire),0) as ca FROM commande_produits cp JOIN stock s ON cp.stock_id=s.id JOIN produits p ON s.produit_matricule=p.matricule WHERE cp.statut=0 GROUP BY p.matricule ORDER BY total_vendu DESC LIMIT 5")->fetchAll();

    $ca_mois_courant = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut=0 AND MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())")->fetchColumn();
    $ca_mois_precedent = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut=0 AND MONTH(date)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(date)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetchColumn();
    $variation_ca = $ca_mois_precedent > 0 ? (($ca_mois_courant - $ca_mois_precedent) / $ca_mois_precedent) * 100 : ($ca_mois_courant > 0 ? 100 : 0);
} catch (PDOException $e) {
    error_log("Erreur dashboard PDG: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dashboard PDG - NGS</title>

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

        *:focus-visible {
            outline: 2px solid #fbbf24;
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
                    <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_pdg.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="boutiques.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-store w-4 text-center"></i>Boutiques<?php if ($total_boutiques > 0): ?><span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_boutiques ?></span><?php endif; ?></a>
            <a href="produits.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-box w-4 text-center"></i>Produits</a>
            <a href="stocks.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Stocks<?php if ($alertes_stock > 0): ?><span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $alertes_stock ?></span><?php endif; ?></a>
            <a href="transferts.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Transferts</a>
            <a href="utilisateurs.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-users w-4 text-center"></i>Utilisateurs</a>
            <a href="rapports_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Tableau de bord PDG</h1>
                        <p class="text-xs text-[var(--text-muted)]"><i class="far fa-calendar-alt mr-1"></i><?= date('d/m/Y') ?> • <span id="clock"><?= date('H:i') ?></span></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($alertes_stock > 0): ?>
                        <a href="stocks.php" class="px-3 py-2 rounded-xl bg-gradient-to-r from-red-500 to-orange-500 text-white text-xs font-semibold flex items-center gap-1.5 shadow-md hover:opacity-90 transition-opacity">
                            <i class="fas fa-exclamation-triangle"></i><?= $alertes_stock ?> alerte(s)
                        </a>
                    <?php endif; ?>
                    <a href="boutiques.php" class="btn-glass px-4 py-2 rounded-xl text-sm flex items-center gap-2"><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouvelle boutique</span></a>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <!-- Stats KPI -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Boutiques</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-store text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_boutiques ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Actives</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">CA Total</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-dollar-sign text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($ca_total, 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">
                        <?php if ($variation_ca != 0): ?>
                            <span class="<?= $variation_ca > 0 ? 'text-emerald-500' : 'text-red-500' ?>"><i class="fas fa-arrow-<?= $variation_ca > 0 ? 'up' : 'down' ?> mr-0.5"></i><?= abs(round($variation_ca)) ?>% vs mois précédent</span>
                            <?php else: ?>Stable<?php endif; ?>
                    </p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-purple-600 dark:text-purple-400">Produits</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-boxes text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $produits_total ?>/<?= $total_produits_actifs ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">En stock / Total</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-amber-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-amber-600 dark:text-amber-400">Alertes</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center"><i class="fas fa-exclamation-triangle text-amber-600 dark:text-amber-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $alertes_stock ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Stock faible</p>
                </div>
            </div>

            <!-- Tableaux -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Dernières transactions -->
                <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.15s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-[var(--text-primary)]"><i class="fas fa-receipt text-emerald-500 mr-2"></i>Dernières transactions</h2>
                        <a href="paiements.php" class="text-xs text-amber-500 hover:text-amber-600 font-medium">Voir tout →</a>
                    </div>
                    <?php if (!empty($dernieres_ventes)): ?>
                        <div class="space-y-2">
                            <?php foreach ($dernieres_ventes as $v): ?>
                                <div class="flex items-center justify-between p-3 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)]">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-[var(--text-primary)]">#<?= $v['id'] ?> • <?= htmlspecialchars($v['boutique_nom'] ?? 'N/A') ?></p>
                                        <p class="text-xs text-[var(--text-muted)]"><?= date('d/m/Y', strtotime($v['date'])) ?></p>
                                    </div>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 flex-shrink-0 ml-3"><?= number_format($v['montant'], 2) ?> $</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center py-8 text-[var(--text-muted)]">Aucune transaction</p>
                    <?php endif; ?>
                </div>

                <!-- Top produits -->
                <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-[var(--text-primary)]"><i class="fas fa-trophy text-amber-500 mr-2"></i>Top produits</h2>
                        <span class="text-xs text-[var(--text-muted)]">Global</span>
                    </div>
                    <?php if (!empty($produits_populaires)): ?>
                        <div class="space-y-2">
                            <?php foreach ($produits_populaires as $i => $p): ?>
                                <div class="flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-colors">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="w-7 h-7 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs font-bold flex-shrink-0"><?= $i + 1 ?></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-[var(--text-primary)] truncate"><?= htmlspecialchars($p['designation']) ?></p>
                                            <p class="text-xs text-[var(--text-muted)]"><?= $p['umProduit'] == 'metres' ? 'mètres' : 'pièces' ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-3"><span class="text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= $p['total_vendu'] ?></span>
                                        <p class="text-xs text-[var(--text-muted)]"><?= number_format($p['ca'], 0) ?> $</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center py-8 text-[var(--text-muted)]">Aucune donnée</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Boutiques -->
            <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.25s">
                <h2 class="text-base font-bold text-[var(--text-primary)] mb-4"><i class="fas fa-store text-blue-500 mr-2"></i>État des boutiques</h2>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px]">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Boutique</th>
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produits</th>
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Alertes</th>
                                <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]">
                            <?php if (!empty($boutiques_stats)): ?>
                                <?php foreach ($boutiques_stats as $b): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0"><i class="fas fa-store text-blue-600 dark:text-blue-400 text-xs"></i></div>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-[var(--text-primary)] truncate"><?= htmlspecialchars($b['nom']) ?></p>
                                                    <p class="text-xs text-[var(--text-muted)] truncate"><?= htmlspecialchars($b['email']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-[var(--text-secondary)]"><?= $b['produits_en_stock'] ?? 0 ?></td>
                                        <td class="px-4 py-3"><?= ($b['alertes_boutique'] ?? 0) > 0 ? '<span class="px-2 py-0.5 rounded-full text-xs font-medium badge-danger">' . $b['alertes_boutique'] . '</span>' : '<span class="px-2 py-0.5 rounded-full text-xs font-medium badge-success">OK</span>' ?></td>
                                        <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $b['actif'] ? 'badge-success' : 'badge-danger' ?>"><?= $b['actif'] ? 'Active' : 'Inactive' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-[var(--text-muted)]">Aucune boutique</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-fade-in-up" style="animation-delay:0.3s">
                <a href="boutiques.php" class="premium-card p-5 text-center hover:border-emerald-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-plus text-emerald-600 dark:text-emerald-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Nouvelle boutique</span>
                </a>
                <a href="produits.php" class="premium-card p-5 text-center hover:border-blue-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-box text-blue-600 dark:text-blue-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Nouveau produit</span>
                </a>
                <a href="stocks.php" class="premium-card p-5 text-center hover:border-purple-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-warehouse text-purple-600 dark:text-purple-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Gestion stocks</span>
                </a>
                <a href="rapports_pdg.php" class="premium-card p-5 text-center hover:border-amber-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-chart-bar text-amber-600 dark:text-amber-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Rapports</span>
                </a>
            </div>

        </div>
    </main>

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

        // Clock
        function updateClock() {
            document.getElementById('clock').textContent = new Date().toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            })
        }
        setInterval(updateClock, 30000);
        updateClock();

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) toggleSidebar()
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>