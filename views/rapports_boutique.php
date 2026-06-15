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

$message = '';
$message_type = '';
$boutique_info = null;

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

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

$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-t');
$periode = $_GET['periode'] ?? 'mois';

if ($periode === 'semaine') {
    $date_debut = date('Y-m-d', strtotime('monday this week'));
    $date_fin = date('Y-m-d', strtotime('sunday this week'));
} elseif ($periode === 'jour') {
    $date_debut = date('Y-m-d');
    $date_fin = date('Y-m-d');
}

$rapport_stocks = [];
$alertes_stock = [];
$rapport_ventes = [];
$top_produits = [];
$rapport_mouvements = [];
$rapport_transferts = [];
$rapport_paiements = [];
$valeur_stock = 0;
$chiffre_affaires = 0;
$total_entrees = 0;
$total_sorties = 0;
$solde_caisse = 0;
$nb_produits = 0;
$quantite_totale = 0;
$stats_unite = [];

try {
    // Stocks
    $queryStocks = $pdo->prepare("SELECT p.matricule, p.designation, p.umProduit, SUM(s.quantite) as quantite_totale, AVG(s.prix) as prix_moyen, MIN(s.prix) as prix_min, MAX(s.prix) as prix_max, SUM(s.quantite*s.prix) as valeur_totale, COUNT(s.id) as nb FROM stock s JOIN produits p ON s.produit_matricule=p.matricule WHERE s.boutique_id=? AND s.statut=0 AND s.quantite>0 GROUP BY p.matricule ORDER BY valeur_totale DESC");
    $queryStocks->execute([$boutique_id]);
    $rapport_stocks = $queryStocks->fetchAll();

    $queryAlertes = $pdo->prepare("SELECT p.designation, p.umProduit, s.quantite, s.seuil_alerte_stock, s.date_creation FROM stock s JOIN produits p ON s.produit_matricule=p.matricule WHERE s.boutique_id=? AND s.statut=0 AND s.quantite<=s.seuil_alerte_stock AND s.quantite>0 ORDER BY s.quantite ASC");
    $queryAlertes->execute([$boutique_id]);
    $alertes_stock = $queryAlertes->fetchAll();

    // Ventes
    $queryVentes = $pdo->prepare("SELECT DATE(c.date_commande) as date_vente, COUNT(DISTINCT c.id) as nb_cmd, SUM(cp.quantite) as qte, SUM(cp.quantite*cp.prix_unitaire) as ca, AVG(cp.quantite*cp.prix_unitaire) as panier_moyen FROM commandes c JOIN commande_produits cp ON c.id=cp.commande_id WHERE c.boutique_id=? AND c.statut=0 AND cp.statut=0 AND DATE(c.date_commande) BETWEEN ? AND ? AND c.etat='payee' GROUP BY DATE(c.date_commande) ORDER BY date_vente DESC LIMIT 30");
    $queryVentes->execute([$boutique_id, $date_debut, $date_fin]);
    $rapport_ventes = $queryVentes->fetchAll();

    $queryTop = $pdo->prepare("SELECT p.designation, p.umProduit, SUM(cp.quantite) as qte, SUM(cp.quantite*cp.prix_unitaire) as ca, COUNT(DISTINCT c.id) as nb_cmd FROM commande_produits cp JOIN commandes c ON cp.commande_id=c.id JOIN stock s ON cp.stock_id=s.id JOIN produits p ON s.produit_matricule=p.matricule WHERE c.boutique_id=? AND cp.statut=0 AND c.statut=0 AND c.etat='payee' AND DATE(c.date_commande) BETWEEN ? AND ? GROUP BY p.designation ORDER BY qte DESC LIMIT 10");
    $queryTop->execute([$boutique_id, $date_debut, $date_fin]);
    $top_produits = $queryTop->fetchAll();

    // Caisse
    $queryMvts = $pdo->prepare("SELECT DATE(mc.date_mouvement) as date_mvt, mc.type_mouvement, SUM(mc.montant) as total, COUNT(mc.id) as nb FROM mouvement_caisse mc WHERE mc.id_boutique=? AND DATE(mc.date_mouvement) BETWEEN ? AND ? AND mc.statut=0 GROUP BY DATE(mc.date_mouvement), mc.type_mouvement ORDER BY date_mvt DESC LIMIT 30");
    $queryMvts->execute([$boutique_id, $date_debut, $date_fin]);
    $rapport_mouvements = $queryMvts->fetchAll();

    // Transferts
    $queryTrans = $pdo->prepare("SELECT t.*, be.nom as boutique_exp, bd.nom as boutique_dest, p.designation, p.umProduit FROM transferts t JOIN stock s ON t.stock_id=s.id JOIN produits p ON s.produit_matricule=p.matricule LEFT JOIN boutiques be ON t.Expedition=be.id LEFT JOIN boutiques bd ON t.Destination=bd.id WHERE (t.Expedition=? OR t.Destination=?) AND t.statut=0 AND DATE(t.date) BETWEEN ? AND ? ORDER BY t.date DESC");
    $queryTrans->execute([$boutique_id, $boutique_id, $date_debut, $date_fin]);
    $rapport_transferts = $queryTrans->fetchAll();

    // Paiements
    $queryPaie = $pdo->prepare("SELECT p.*, c.numero_facture, c.client_nom FROM paiements p JOIN commandes c ON p.commandes_id=c.id WHERE c.boutique_id=? AND p.statut=0 AND DATE(p.date) BETWEEN ? AND ? ORDER BY p.date DESC");
    $queryPaie->execute([$boutique_id, $date_debut, $date_fin]);
    $rapport_paiements = $queryPaie->fetchAll();
    $total_paiements = array_sum(array_column($rapport_paiements, 'montant'));

    // Stats globales
    $valeur_stock = $pdo->query("SELECT COALESCE(SUM(quantite*prix),0) FROM stock WHERE boutique_id=$boutique_id AND statut=0 AND quantite>0")->fetchColumn();
    $chiffre_affaires = $pdo->query("SELECT COALESCE(SUM(cp.quantite*cp.prix_unitaire),0) FROM commande_produits cp JOIN commandes c ON cp.commande_id=c.id WHERE c.boutique_id=$boutique_id AND cp.statut=0 AND c.statut=0 AND c.etat='payee' AND DATE(c.date_commande) BETWEEN '$date_debut' AND '$date_fin'")->fetchColumn();
    $nb_produits = $pdo->query("SELECT COUNT(DISTINCT produit_matricule) FROM stock WHERE boutique_id=$boutique_id AND statut=0 AND quantite>0")->fetchColumn();
    $quantite_totale = $pdo->query("SELECT COALESCE(SUM(quantite),0) FROM stock WHERE boutique_id=$boutique_id AND statut=0 AND quantite>0")->fetchColumn();

    $tc = $pdo->query("SELECT type_mouvement, SUM(montant) as total FROM mouvement_caisse WHERE id_boutique=$boutique_id AND DATE(date_mouvement) BETWEEN '$date_debut' AND '$date_fin' AND statut=0 GROUP BY type_mouvement")->fetchAll();
    foreach ($tc as $r) {
        if ($r['type_mouvement'] == 'entrée') $total_entrees = $r['total'];
        else $total_sorties = $r['total'];
    }
    $solde_caisse = $total_entrees - $total_sorties;

    $su = $pdo->query("SELECT p.umProduit, COUNT(DISTINCT s.produit_matricule) as nb, SUM(s.quantite) as qt, SUM(s.quantite*s.prix) as val FROM stock s JOIN produits p ON s.produit_matricule=p.matricule WHERE s.boutique_id=$boutique_id AND s.statut=0 AND s.quantite>0 GROUP BY p.umProduit")->fetchAll();
    $stats_unite = $su;
} catch (PDOException $e) {
    error_log("Erreur rapports: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Rapports - <?= htmlspecialchars($boutique_info['nom']) ?> - NGS</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            border-left: 3px solid #a78bfa;
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

        .tab-btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .tab-btn.active {
            background: var(--accent-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(30, 58, 138, 0.25);
        }

        .tab-btn:not(.active) {
            background: var(--glass-bg);
            color: var(--text-secondary);
            border: 1px solid var(--glass-border);
        }

        .tab-btn:not(.active):hover {
            background: var(--card-bg);
        }

        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
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

    <!-- SIDEBAR -->
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-lg"><span class="font-bold text-white">NGS</span></div>
                <div>
                    <h2 class="font-bold text-sm">NGS Pro</h2>
                    <p class="text-[10px] text-gray-400">Dashboard Boutique</p>
                </div>
            </div>
        </div>
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-purple-500/20 border border-purple-400/30 flex items-center justify-center"><i class="fas fa-store text-purple-400"></i></div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($boutique_info['nom']) ?></p>
                    <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($boutique_info['email'] ?? '') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Mes stocks</a>
            <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes</a>
            <a href="paiements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements</a>
            <a href="mouvements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements Caisse</a>
            <a href="transferts-boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-truck-loading w-4 text-center"></i>Transferts</a>
            <a href="rapports_boutique.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Rapports - <?= htmlspecialchars($boutique_info['nom']) ?></h1>
                        <p class="text-xs text-[var(--text-muted)]">Analyse de votre boutique</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="window.location.reload()" class="p-2 rounded-xl glass hover:bg-white/20 transition-all text-[var(--text-muted)]" title="Actualiser"><i class="fas fa-sync-alt text-sm"></i></button>
                    <button onclick="exportRapports()" class="btn-glass px-4 py-2 rounded-xl text-sm"><i class="fas fa-download mr-1.5"></i>Exporter</button>
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

            <!-- Filtres période -->
            <div class="premium-card p-5 animate-fade-in-up">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Période</label>
                            <select name="periode" class="w-full input-glass px-3 py-2.5 text-sm" onchange="this.form.submit()">
                                <option value="mois" <?= $periode == 'mois' ? 'selected' : '' ?>>Ce mois</option>
                                <option value="semaine" <?= $periode == 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                                <option value="jour" <?= $periode == 'jour' ? 'selected' : '' ?>>Aujourd'hui</option>
                                <option value="personnalise" <?= $periode == 'personnalise' ? 'selected' : '' ?>>Personnalisé</option>
                            </select>
                        </div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date début</label><input type="date" name="date_debut" value="<?= $date_debut ?>" class="w-full input-glass px-3 py-2.5 text-sm" <?= $periode !== 'personnalise' ? 'disabled' : '' ?>></div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date fin</label><input type="date" name="date_fin" value="<?= $date_fin ?>" class="w-full input-glass px-3 py-2.5 text-sm" <?= $periode !== 'personnalise' ? 'disabled' : '' ?>></div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <a href="rapports_boutique.php" class="px-4 py-2 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Réinitialiser</a>
                        <button type="submit" class="btn-glass px-4 py-2 rounded-xl text-sm">Appliquer</button>
                    </div>
                </form>
            </div>

            <!-- Onglets -->
            <div class="flex flex-wrap gap-2 animate-fade-in-up" style="animation-delay:0.1s">
                <button class="tab-btn active" data-tab="stats"><i class="fas fa-chart-pie mr-1.5"></i>Stats</button>
                <button class="tab-btn" data-tab="stocks"><i class="fas fa-warehouse mr-1.5"></i>Stocks</button>
                <button class="tab-btn" data-tab="ventes"><i class="fas fa-shopping-cart mr-1.5"></i>Ventes</button>
                <button class="tab-btn" data-tab="caisse"><i class="fas fa-exchange-alt mr-1.5"></i>Caisse</button>
                <button class="tab-btn" data-tab="transferts"><i class="fas fa-truck mr-1.5"></i>Transferts</button>
                <button class="tab-btn" data-tab="paiements"><i class="fas fa-money-bill-wave mr-1.5"></i>Paiements</button>
                <button class="tab-btn" data-tab="alertes"><i class="fas fa-exclamation-triangle mr-1.5"></i>Alertes</button>
            </div>

            <!-- Contenu onglets -->
            <div id="tab-contents">

                <!-- STATS -->
                <div id="tab-stats" class="tab-content space-y-6">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0s">
                            <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Valeur stock</span>
                                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-boxes text-blue-600 dark:text-blue-400 text-sm"></i></div>
                            </div>
                            <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($valeur_stock, 2) ?> $</p>
                            <p class="text-xs text-[var(--text-muted)] mt-1"><?= $nb_produits ?> produits</p>
                        </div>
                        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.1s">
                            <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">CA période</span>
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-chart-line text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                            </div>
                            <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($chiffre_affaires, 2) ?> $</p>
                            <p class="text-xs text-[var(--text-muted)] mt-1"><?= date('d/m', strtotime($date_debut)) ?>-<?= date('d/m', strtotime($date_fin)) ?></p>
                        </div>
                        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-cyan-500" style="animation-delay:0.2s">
                            <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-cyan-600 dark:text-cyan-400">Qté stock</span>
                                <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center"><i class="fas fa-weight-hanging text-cyan-600 dark:text-cyan-400 text-sm"></i></div>
                            </div>
                            <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($quantite_totale, 3) ?></p>
                            <p class="text-xs text-[var(--text-muted)] mt-1">Unités</p>
                        </div>
                        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.3s">
                            <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-purple-600 dark:text-purple-400">Solde caisse</span>
                                <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-money-bill-wave text-purple-600 dark:text-purple-400 text-sm"></i></div>
                            </div>
                            <p class="text-2xl font-bold <?= $solde_caisse >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>"><?= number_format($solde_caisse, 2) ?> $</p>
                            <p class="text-xs text-[var(--text-muted)] mt-1">E:<?= number_format($total_entrees, 0) ?> S:<?= number_format($total_sorties, 0) ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="premium-card p-5">
                            <h3 class="text-base font-bold text-[var(--text-primary)] mb-4"><i class="fas fa-chart-line text-blue-500 mr-2"></i>Ventes</h3>
                            <div class="chart-container"><canvas id="ventesChart"></canvas></div>
                        </div>
                        <div class="premium-card p-5">
                            <h3 class="text-base font-bold text-[var(--text-primary)] mb-4"><i class="fas fa-exchange-alt text-purple-500 mr-2"></i>Caisse</h3>
                            <div class="chart-container"><canvas id="caisseChart"></canvas></div>
                        </div>
                    </div>

                    <?php if (!empty($stats_unite)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($stats_unite as $s): ?>
                                <div class="premium-card p-5">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center gap-2"><i class="fas fa-<?= $s['umProduit'] == 'metres' ? 'ruler-combined text-blue-500' : 'cube text-emerald-500' ?>"></i><span class="font-bold text-sm text-[var(--text-primary)]"><?= $s['umProduit'] == 'metres' ? 'Rideaux' : 'Produits' ?></span></div>
                                        <span class="px-2 py-0.5 rounded-full text-xs <?= $s['umProduit'] == 'metres' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' ?>"><?= $s['umProduit'] == 'metres' ? 'Mètres' : 'Pièces' ?></span>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between"><span class="text-[var(--text-muted)]">Produits :</span><span class="font-medium"><?= $s['nb'] ?></span></div>
                                        <div class="flex justify-between"><span class="text-[var(--text-muted)]">Quantité :</span><span class="font-medium"><?= number_format($s['qt'], 3) ?></span></div>
                                        <div class="flex justify-between"><span class="text-[var(--text-muted)]">Valeur :</span><span class="font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($s['val'], 2) ?> $</span></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- STOCKS -->
                <div id="tab-stocks" class="tab-content hidden">
                    <div class="premium-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-[var(--divider)] flex items-center justify-between">
                            <h3 class="text-base font-bold text-[var(--text-primary)]">Détail des stocks</h3><span class="text-xs text-[var(--text-muted)]"><?= count($rapport_stocks) ?> produits</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[800px]">
                                <thead>
                                    <tr class="border-b border-[var(--divider)] text-left">
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produit</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Unité</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Qté</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Prix moy</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Valeur</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--divider)]">
                                    <?php if (!empty($rapport_stocks)): ?>
                                        <?php foreach ($rapport_stocks as $s): $ut = $s['umProduit'] == 'metres' ? 'mètres' : 'pièces'; ?>
                                            <tr class="hover:bg-white/5 transition-colors">
                                                <td class="px-5 py-3.5"><span class="text-sm font-medium text-[var(--text-primary)]"><?= htmlspecialchars($s['designation']) ?></span><span class="text-xs text-[var(--text-muted)] block font-mono"><?= $s['matricule'] ?></span></td>
                                                <td class="px-5 py-3.5"><span class="px-2 py-0.5 rounded-full text-xs <?= $s['umProduit'] == 'metres' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' ?>"><?= $ut ?></span></td>
                                                <td class="px-5 py-3.5 text-sm font-bold text-[var(--text-primary)]"><?= number_format($s['quantite_totale'], 3) ?></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= number_format($s['prix_moyen'], 2) ?> $</td>
                                                <td class="px-5 py-3.5 text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($s['valeur_totale'], 2) ?> $</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?><tr>
                                            <td colspan="5" class="px-5 py-12 text-center text-[var(--text-muted)]">Aucun stock</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- VENTES -->
                <div id="tab-ventes" class="tab-content hidden space-y-6">
                    <div class="premium-card p-5">
                        <h3 class="text-base font-bold text-[var(--text-primary)] mb-4"><i class="fas fa-trophy text-amber-500 mr-2"></i>Top 10 ventes</h3>
                        <?php if (!empty($top_produits)): ?>
                            <div class="space-y-2">
                                <?php foreach ($top_produits as $i => $p): ?>
                                    <div class="flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-colors">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-bold flex-shrink-0"><?= $i + 1 ?></span>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-[var(--text-primary)] truncate"><?= htmlspecialchars($p['designation']) ?></p>
                                                <p class="text-xs text-[var(--text-muted)]"><?= $p['nb_cmd'] ?> cmd</p>
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0 ml-3"><span class="text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($p['ca'], 2) ?> $</span>
                                            <p class="text-xs text-[var(--text-muted)]"><?= $p['qte'] ?> vendus</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?><p class="text-center py-8 text-[var(--text-muted)]">Aucune vente</p><?php endif; ?>
                    </div>
                    <div class="premium-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-[var(--divider)]">
                            <h3 class="text-base font-bold text-[var(--text-primary)]">Ventes par jour</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[600px]">
                                <thead>
                                    <tr class="border-b border-[var(--divider)] text-left">
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Cmd</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Qté</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">CA</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Panier moy</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--divider)]">
                                    <?php if (!empty($rapport_ventes)): ?>
                                        <?php foreach ($rapport_ventes as $v): ?>
                                            <tr class="hover:bg-white/5 transition-colors">
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]"><?= date('d/m/Y', strtotime($v['date_vente'])) ?></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= $v['nb_cmd'] ?></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= number_format($v['qte'], 3) ?></td>
                                                <td class="px-5 py-3.5 text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($v['ca'], 2) ?> $</td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= number_format($v['panier_moyen'], 2) ?> $</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?><tr>
                                            <td colspan="5" class="px-5 py-12 text-center text-[var(--text-muted)]">Aucune vente</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- CAISSE -->
                <div id="tab-caisse" class="tab-content hidden space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="premium-card p-5 border-l-4 border-emerald-500">
                            <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Entrées</span><i class="fas fa-arrow-down text-emerald-500"></i></div>
                            <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_entrees, 2) ?> $</p>
                        </div>
                        <div class="premium-card p-5 border-l-4 border-red-500">
                            <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-red-600 dark:text-red-400">Sorties</span><i class="fas fa-arrow-up text-red-500"></i></div>
                            <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_sorties, 2) ?> $</p>
                        </div>
                        <div class="premium-card p-5 border-l-4 border-blue-500">
                            <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Solde</span><i class="fas fa-balance-scale text-blue-500"></i></div>
                            <p class="text-2xl font-bold <?= $solde_caisse >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>"><?= number_format($solde_caisse, 2) ?> $</p>
                        </div>
                    </div>
                    <div class="premium-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-[var(--divider)]">
                            <h3 class="text-base font-bold text-[var(--text-primary)]">Mouvements</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[500px]">
                                <thead>
                                    <tr class="border-b border-[var(--divider)] text-left">
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Type</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Montant</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Nb</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--divider)]">
                                    <?php if (!empty($rapport_mouvements)): ?>
                                        <?php foreach ($rapport_mouvements as $m): ?>
                                            <tr class="hover:bg-white/5 transition-colors">
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]"><?= date('d/m/Y', strtotime($m['date_mvt'])) ?></td>
                                                <td class="px-5 py-3.5"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $m['type_mouvement'] == 'entrée' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($m['type_mouvement']) ?></span></td>
                                                <td class="px-5 py-3.5 text-sm font-bold <?= $m['type_mouvement'] == 'entrée' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>"><?= number_format($m['total'], 2) ?> $</td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= $m['nb'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?><tr>
                                            <td colspan="4" class="px-5 py-12 text-center text-[var(--text-muted)]">Aucun mouvement</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TRANSFERTS -->
                <div id="tab-transferts" class="tab-content hidden">
                    <div class="premium-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-[var(--divider)] flex items-center justify-between">
                            <h3 class="text-base font-bold text-[var(--text-primary)]">Transferts</h3><span class="text-xs text-[var(--text-muted)]"><?= count($rapport_transferts) ?> transfert(s)</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[700px]">
                                <thead>
                                    <tr class="border-b border-[var(--divider)] text-left">
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Type</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produit</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Source</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Dest.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--divider)]">
                                    <?php if (!empty($rapport_transferts)): ?>
                                        <?php foreach ($rapport_transferts as $t): $estExp = $t['Expedition'] == $boutique_id; ?>
                                            <tr class="hover:bg-white/5 transition-colors">
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]"><?= date('d/m/Y', strtotime($t['date'])) ?></td>
                                                <td class="px-5 py-3.5"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $estExp ? 'badge-warning' : 'badge-success' ?>"><?= $estExp ? 'Envoi' : 'Réception' ?></span></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]"><?= htmlspecialchars($t['designation']) ?></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($t['boutique_exp']) ?></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($t['boutique_dest']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?><tr>
                                            <td colspan="5" class="px-5 py-12 text-center text-[var(--text-muted)]">Aucun transfert</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- PAIEMENTS -->
                <div id="tab-paiements" class="tab-content hidden space-y-6">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="premium-card p-5 text-center">
                            <p class="text-xs text-[var(--text-muted)] mb-1">Nombre</p>
                            <p class="text-2xl font-bold text-[var(--text-primary)]"><?= count($rapport_paiements) ?></p>
                        </div>
                        <div class="premium-card p-5 text-center">
                            <p class="text-xs text-[var(--text-muted)] mb-1">Total</p>
                            <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($total_paiements, 2) ?> $</p>
                        </div>
                        <div class="premium-card p-5 text-center">
                            <p class="text-xs text-[var(--text-muted)] mb-1">Moyenne</p>
                            <p class="text-2xl font-bold text-[var(--text-primary)]"><?= count($rapport_paiements) > 0 ? number_format($total_paiements / count($rapport_paiements), 2) : '0.00' ?> $</p>
                        </div>
                    </div>
                    <div class="premium-card overflow-hidden">
                        <div class="px-5 py-4 border-b border-[var(--divider)]">
                            <h3 class="text-base font-bold text-[var(--text-primary)]">Détail</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[600px]">
                                <thead>
                                    <tr class="border-b border-[var(--divider)] text-left">
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Facture</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Client</th>
                                        <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Montant</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--divider)]">
                                    <?php if (!empty($rapport_paiements)): ?>
                                        <?php foreach ($rapport_paiements as $p): ?>
                                            <tr class="hover:bg-white/5 transition-colors">
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]"><?= date('d/m/Y', strtotime($p['date'])) ?></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($p['numero_facture']) ?></td>
                                                <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($p['client_nom']) ?></td>
                                                <td class="px-5 py-3.5 text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($p['montant'], 2) ?> $</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?><tr>
                                            <td colspan="4" class="px-5 py-12 text-center text-[var(--text-muted)]">Aucun paiement</td>
                                        </tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ALERTES -->
                <div id="tab-alertes" class="tab-content hidden">
                    <div class="premium-card p-5">
                        <h3 class="text-base font-bold text-[var(--text-primary)] mb-4"><i class="fas fa-exclamation-triangle text-amber-500 mr-2"></i>Alertes stock faible</h3>
                        <?php if (!empty($alertes_stock)): ?>
                            <div class="space-y-2">
                                <?php foreach ($alertes_stock as $a): ?>
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800/30">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-[var(--text-primary)] truncate"><?= htmlspecialchars($a['designation']) ?></p>
                                            <p class="text-xs text-[var(--text-muted)]">Seuil: <?= $a['seuil_alerte_stock'] ?> • <?= $a['umProduit'] ?></p>
                                        </div>
                                        <span class="text-lg font-bold text-amber-600 dark:text-amber-400 flex-shrink-0 ml-3"><?= $a['quantite'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?><p class="text-center py-8 text-emerald-500"><i class="fas fa-check-circle text-4xl mb-3"></i><span class="text-[var(--text-secondary)]">Aucune alerte</span></p><?php endif; ?>
                    </div>
                </div>

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

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const tabId = btn.dataset.tab;
                document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
                document.getElementById('tab-' + tabId)?.classList.remove('hidden');
                if (tabId === 'stats') {
                    setTimeout(() => {
                        initCharts()
                    }, 100)
                }
            });
        });

        // Charts
        function initCharts() {
            const vCtx = document.getElementById('ventesChart'),
                cCtx = document.getElementById('caisseChart');
            if (!vCtx || !cCtx) return;

            <?php $vl = [];
            $vd = [];
            foreach ($rapport_ventes as $v) {
                $vl[] = "'" . date('d/m', strtotime($v['date_vente'])) . "'";
                $vd[] = $v['ca'];
            } ?>
            new Chart(vCtx, {
                type: 'line',
                data: {
                    labels: [<?= implode(',', $vl) ?>],
                    datasets: [{
                        label: 'CA ($)',
                        data: [<?= implode(',', $vd) ?>],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => v.toLocaleString('fr-FR') + ' $'
                            }
                        }
                    }
                }
            });

            <?php
            $md = [];
            $me = [];
            $ms = [];
            foreach ($rapport_mouvements as $m) {
                $md[] = "'" . date('d/m', strtotime($m['date_mvt'])) . "'";
                $me[] = $m['type_mouvement'] == 'entrée' ? $m['total'] : 0;
                $ms[] = $m['type_mouvement'] == 'sortie' ? $m['total'] : 0;
            }
            ?>
            new Chart(cCtx, {
                type: 'bar',
                data: {
                    labels: [<?= implode(',', $md) ?>],
                    datasets: [{
                        label: 'Entrées',
                        data: [<?= implode(',', $me) ?>],
                        backgroundColor: '#10b981'
                    }, {
                        label: 'Sorties',
                        data: [<?= implode(',', $ms) ?>],
                        backgroundColor: '#ef4444'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: v => v.toLocaleString('fr-FR') + ' $'
                            }
                        }
                    }
                }
            });
        }

        // Init charts on load
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(initCharts, 300)
        });

        // Filter period
        document.querySelector('select[name="periode"]')?.addEventListener('change', function() {
            const dd = document.querySelector('input[name="date_debut"]'),
                df = document.querySelector('input[name="date_fin"]');
            if (this.value === 'personnalise') {
                dd.disabled = false;
                df.disabled = false
            } else {
                dd.disabled = true;
                df.disabled = true
            }
        });

        // Export
        function exportRapports() {
            const tab = document.querySelector('.tab-btn.active')?.dataset.tab || 'stats';
            window.open(`export_rapports.php?tab=${tab}&date_debut=<?= $date_debut ?>&date_fin=<?= $date_fin ?>&boutique_id=<?= $boutique_id ?>`, '_blank');
        }

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) toggleSidebar()
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>