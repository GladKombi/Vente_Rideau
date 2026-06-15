<?php
# Connexion à la base de données
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification PDG
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';
$total_transferts = 0;
$transferts = [];

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer les boutiques et produits
try {
    $queryBoutiques = $pdo->prepare("SELECT id, nom FROM boutiques WHERE statut = 0 AND actif = 1 ORDER BY nom");
    $queryBoutiques->execute();
    $boutiques = $queryBoutiques->fetchAll(PDO::FETCH_ASSOC);

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
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    $boutiques = [];
    $produits = [];
}

// Traitement du transfert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['effectuer_transfert'])) {
    try {
        $produit_matricule = $_POST['produit_matricule'];
        $boutique_expedition = (int)$_POST['boutique_expedition'];
        $boutique_destination = (int)$_POST['boutique_destination'];
        $quantite_transferee = (float)$_POST['quantite_transferee'];

        if ($boutique_expedition == $boutique_destination) throw new Exception("Impossible de transférer vers la même boutique");
        if ($quantite_transferee <= 0) throw new Exception("La quantité doit être supérieure à 0");

        $queryStockSource = $pdo->prepare("
            SELECT s.*, p.designation, p.umProduit FROM stock s 
            JOIN produits p ON s.produit_matricule = p.matricule 
            WHERE s.boutique_id = ? AND s.produit_matricule = ? AND s.statut = 0 AND s.quantite >= ?
            ORDER BY s.date_creation ASC LIMIT 1
        ");
        $queryStockSource->execute([$boutique_expedition, $produit_matricule, $quantite_transferee]);
        $stock_source = $queryStockSource->fetch(PDO::FETCH_ASSOC);

        if (!$stock_source) {
            $queryQuantiteTotale = $pdo->prepare("SELECT SUM(quantite) as quantite_totale FROM stock WHERE boutique_id = ? AND produit_matricule = ? AND statut = 0");
            $queryQuantiteTotale->execute([$boutique_expedition, $produit_matricule]);
            $qt = $queryQuantiteTotale->fetch(PDO::FETCH_ASSOC)['quantite_totale'] ?? 0;
            if ($qt == 0) throw new Exception("Ce produit n'existe pas dans le stock source");
            else throw new Exception("Quantité insuffisante. Disponible : " . number_format($qt, 3));
        }

        $queryStockDest = $pdo->prepare("SELECT id, quantite, prix FROM stock WHERE boutique_id = ? AND produit_matricule = ? AND statut = 0 LIMIT 1");
        $queryStockDest->execute([$boutique_destination, $produit_matricule]);
        $stock_destination = $queryStockDest->fetch(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        $queryUpdateSource = $pdo->prepare("UPDATE stock SET quantite = quantite - ? WHERE id = ? AND statut = 0");
        $queryUpdateSource->execute([$quantite_transferee, $stock_source['id']]);

        if ($stock_destination) {
            $queryUpdateDest = $pdo->prepare("UPDATE stock SET quantite = quantite + ?, type_mouvement = 'transfert' WHERE id = ? AND statut = 0");
            $queryUpdateDest->execute([$quantite_transferee, $stock_destination['id']]);
        } else {
            $queryInsertDest = $pdo->prepare("INSERT INTO stock (type_mouvement, boutique_id, produit_matricule, quantite, prix, seuil_alerte_stock) VALUES ('transfert', ?, ?, ?, ?, ?)");
            $queryInsertDest->execute([$boutique_destination, $produit_matricule, $quantite_transferee, $stock_source['prix'], $stock_source['seuil_alerte_stock']]);
        }

        $queryInsertTransfert = $pdo->prepare("INSERT INTO transferts (date, stock_id, Expedition, Destination) VALUES (CURDATE(), ?, ?, ?)");
        $queryInsertTransfert->execute([$stock_source['id'], $boutique_expedition, $boutique_destination]);

        $pdo->commit();

        $boutique_exp_nom = '';
        $boutique_dest_nom = '';
        foreach ($boutiques as $b) {
            if ($b['id'] == $boutique_expedition) $boutique_exp_nom = $b['nom'];
            if ($b['id'] == $boutique_destination) $boutique_dest_nom = $b['nom'];
        }

        $_SESSION['flash_message'] = [
            'text' => "Transfert réussi ! " . number_format($quantite_transferee, 3) . " " . ($stock_source['umProduit'] == 'metres' ? 'mètres' : 'pièces') . " de " . $stock_source['designation'] . " : " . $boutique_exp_nom . " → " . $boutique_dest_nom,
            'type' => "success"
        ];
        header('Location: transferts.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Récupération des données
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM transferts WHERE statut = 0");
    $countQuery->execute();
    $total_transferts = $countQuery->fetchColumn();
    $totalPages = ceil($total_transferts / $limit);
    if ($totalPages < 1) $totalPages = 1;

    $query = $pdo->prepare("
        SELECT t.*, s.produit_matricule, p.designation as produit_designation, p.umProduit,
               b1.nom as boutique_expedition, b2.nom as boutique_destination,
               st.quantite as quantite_transferee, st.prix as prix_unitaire
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

    $queryStats = $pdo->prepare("
        SELECT COUNT(DISTINCT t.Expedition) as b_exp, COUNT(DISTINCT t.Destination) as b_dest,
               COUNT(DISTINCT p.matricule) as p_transf, SUM(s.quantite) as qt_totale,
               SUM(s.quantite * s.prix) as val_totale
        FROM transferts t 
        JOIN stock s ON t.stock_id = s.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE t.statut = 0
    ");
    $queryStats->execute();
    $stats = $queryStats->fetch(PDO::FETCH_ASSOC);
    $boutiques_expeditrices = $stats['b_exp'] ?? 0;
    $boutiques_destinataires = $stats['b_dest'] ?? 0;
    $produits_transferes = $stats['p_transf'] ?? 0;
    $quantite_totale_transferee = $stats['qt_totale'] ?? 0;
    $valeur_totale_transferee = $stats['val_totale'] ?? 0;
} catch (PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
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
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Transferts - NGS (New Grace Service)</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    },
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
            border-color: #8b5cf6;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
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

        .btn-purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.25);
        }

        .btn-purple:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.4);
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

        .badge-entree {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-sortie {
            background: #fee2e2;
            color: #991b1b;
        }

        .dark .badge-entree {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .dark .badge-sortie {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .badge-purple {
            background: linear-gradient(90deg, #8b5cf6, #7c3aed);
            color: white;
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

        *:focus-visible {
            outline: 2px solid #8b5cf6;
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

    <!-- Overlay mobile -->
    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- ============================================ -->
    <!-- SIDEBAR PDG                                   -->
    <!-- ============================================ -->
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center shadow-lg">
                    <span class="font-bold text-white">NGS</span>
                </div>
                <div>
                    <h2 class="font-bold text-sm">NGS Pro</h2>
                    <p class="text-[10px] text-gray-400">Dashboard PDG</p>
                </div>
            </div>
        </div>

        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-yellow-500/20 border border-yellow-400/30 flex items-center justify-center">
                    <i class="fas fa-crown text-yellow-400"></i>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($_SESSION['user_name'] ?? 'PDG') ?></p>
                    <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord
            </a>
            <a href="boutiques.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-store w-4 text-center"></i>Boutiques
            </a>
            <a href="produits.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-box w-4 text-center"></i>Produits
            </a>
            <a href="stocks.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-warehouse w-4 text-center"></i>Stocks
            </a>
            <a href="transferts.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-exchange-alt w-4 text-center"></i>Transferts
                <span class="ml-auto bg-purple-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_transferts ?></span>
            </a>
            <a href="utilisateurs.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-users w-4 text-center"></i>Utilisateurs
            </a>
            <a href="rapports_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-chart-bar w-4 text-center"></i>Rapports
            </a>
        </nav>

        <div class="p-3 border-t border-white/10 flex-shrink-0">
            <div class="flex items-center justify-between px-3 py-2 mb-2">
                <span class="text-xs text-gray-400"><i class="fas fa-moon mr-1"></i>Thème</span>
                <button id="theme-toggle" class="theme-toggle" aria-label="Changer le thème"></button>
            </div>
            <a href="../models/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-red-400 hover:bg-red-500/10 transition-colors text-sm">
                <i class="fas fa-sign-out-alt w-4 text-center"></i>Déconnexion
            </a>
        </div>
    </aside>

    <!-- ============================================ -->
    <!-- MAIN CONTENT                                 -->
    <!-- ============================================ -->
    <main class="flex-1 overflow-y-auto">

        <header class="sticky top-0 z-30 glass border-b border-white/10">
            <div class="flex items-center justify-between px-4 md:px-6 py-4">
                <div class="flex items-center gap-3">
                    <button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors text-[var(--text-primary)]">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Gestion des Transferts</h1>
                        <p class="text-xs text-[var(--text-muted)]">New Grace Service • Dashboard PDG</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openTransfertModal()" class="btn-purple px-4 py-2 rounded-xl text-sm flex items-center gap-2">
                        <i class="fas fa-exchange-alt"></i><span class="hidden sm:inline">Nouveau transfert</span>
                    </button>
                    <a href="stocks.php" class="btn-glass px-4 py-2 rounded-xl text-sm hidden sm:flex items-center gap-2">
                        <i class="fas fa-warehouse"></i>Stocks
                    </a>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <!-- Message -->
            <?php if ($message): ?>
                <div class="animate-fade-in-up">
                    <div class="glass rounded-2xl p-4 border-l-4 <?= $message_type === 'success' ? 'border-green-500' : 'border-red-500' ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle text-green-500' : 'exclamation-circle text-red-500' ?> text-xl"></i>
                            <span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($message) ?></span>
                            <button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-purple-600 dark:text-purple-400">Total</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-purple-600 dark:text-purple-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_transferts ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Transferts effectués</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-indigo-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Boutiques</span>
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                            <i class="fas fa-store text-indigo-600 dark:text-indigo-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $boutiques_expeditrices ?> → <?= $boutiques_destinataires ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Exp. → Dest.</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Produits</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                            <i class="fas fa-boxes text-emerald-600 dark:text-emerald-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $produits_transferes ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Types de produits</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-cyan-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-cyan-600 dark:text-cyan-400">Valeur</span>
                        <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-cyan-600 dark:text-cyan-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($valeur_totale_transferee, 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Valeur transférée</p>
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
                    <button onclick="window.location.reload()" class="p-2.5 rounded-xl glass hover:bg-white/20 transition-all text-[var(--text-muted)]" title="Actualiser">
                        <i class="fas fa-sync-alt text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Tableau -->
            <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.2s">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px]" id="transfertsTable">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">ID</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produit</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Transfert</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Qté</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Valeur</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]" id="tableBody">
                            <?php if (!empty($transferts)): ?>
                                <?php foreach ($transferts as $transfert):
                                    $uniteText = $transfert['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                    $valeur = $transfert['quantite_transferee'] * $transfert['prix_unitaire'];
                                ?>
                                    <tr class="hover:bg-white/5 transition-colors transfert-row"
                                        data-id="<?= $transfert['id'] ?>"
                                        data-expedition="<?= strtolower($transfert['boutique_expedition']) ?>"
                                        data-destination="<?= strtolower($transfert['boutique_destination']) ?>"
                                        data-produit="<?= strtolower($transfert['produit_designation']) ?>">
                                        <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $transfert['id'] ?></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= date('d/m/Y', strtotime($transfert['date'])) ?></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]">
                                            <span class="font-medium"><?= htmlspecialchars($transfert['produit_designation']) ?></span>
                                            <span class="text-xs text-[var(--text-muted)] block font-mono"><?= $transfert['produit_matricule'] ?></span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400"><?= htmlspecialchars($transfert['boutique_expedition']) ?></span>
                                                <i class="fas fa-arrow-right text-purple-500 text-xs"></i>
                                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400"><?= htmlspecialchars($transfert['boutique_destination']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm font-bold text-[var(--text-primary)]"><?= number_format($transfert['quantite_transferee'], 3) ?> <span class="text-xs text-[var(--text-muted)] font-normal"><?= $uniteText ?></span></td>
                                        <td class="px-5 py-3.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400"><?= number_format($valeur, 2) ?> $</td>
                                        <td class="px-5 py-3.5 text-center">
                                            <button onclick="openDeleteModal(<?= $transfert['id'] ?>, '<?= htmlspecialchars(addslashes($transfert['produit_designation'])) ?>')"
                                                class="p-2 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center">
                                        <i class="fas fa-exchange-alt text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                                        <p class="text-[var(--text-secondary)] font-medium">Aucun transfert enregistré</p>
                                        <p class="text-xs text-[var(--text-muted)] mt-1">Utilisez le bouton "Nouveau transfert" pour commencer</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResults" class="hidden text-center py-12">
                    <i class="fas fa-search text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                    <p class="text-[var(--text-secondary)] font-medium">Aucun résultat trouvé</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Essayez d'autres termes de recherche</p>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="px-5 py-4 border-t border-[var(--divider)] flex items-center justify-between">
                        <span class="text-xs text-[var(--text-muted)] hidden sm:block"><?= ($page - 1) * $limit + 1 ?>-<?= min($page * $limit, $total_transferts) ?> sur <?= $total_transferts ?></span>
                        <div class="flex items-center gap-1.5 mx-auto sm:mx-0">
                            <a href="?page=<?= max(1, $page - 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-left text-xs"></i></a>
                            <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                <a href="?page=<?= $i ?>" class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i == $page ? 'btn-purple shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="?page=<?= min($totalPages, $page + 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-right text-xs"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- ============================================ -->
    <!-- MODAL NOUVEAU TRANSFERT                       -->
    <!-- ============================================ -->
    <div id="transfertModalOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="transfertModalContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-lg p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-exchange-alt mr-2 text-purple-500"></i>Nouveau transfert</h3>
            <button onclick="closeTransfertModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="transferts.php" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Boutique expédition *</label>
                    <select name="boutique_expedition" required class="w-full input-glass px-3 py-2.5 text-sm">
                        <option value="">Sélectionnez...</option>
                        <?php foreach ($boutiques as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Boutique destination *</label>
                    <select name="boutique_destination" required class="w-full input-glass px-3 py-2.5 text-sm">
                        <option value="">Sélectionnez...</option>
                        <?php foreach ($boutiques as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Produit *</label>
                <select name="produit_matricule" id="produitSelect" required class="w-full input-glass px-3 py-2.5 text-sm">
                    <option value="">Sélectionnez un produit</option>
                    <?php foreach ($produits as $p): ?>
                        <option value="<?= $p['matricule'] ?>" data-unite="<?= $p['umProduit'] ?>">
                            <?= htmlspecialchars($p['designation']) ?> (<?= $p['matricule'] ?> - <?= number_format($p['quantite_totale'], 3) ?> disp.)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Quantité *</label>
                <div class="relative">
                    <input type="number" name="quantite_transferee" step="0.001" min="0.001" required placeholder="0.000" class="w-full input-glass pl-4 pr-16 py-2.5 text-sm">
                    <span id="uniteLabel" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)] bg-[var(--input-bg)] px-2 py-0.5 rounded">unités</span>
                </div>
            </div>
            <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800/30">
                <p class="text-xs text-purple-700 dark:text-purple-300"><i class="fas fa-info-circle mr-1"></i>La quantité sera déduite du stock source et ajoutée au stock destination. Le prix unitaire est conservé.</p>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeTransfertModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" name="effectuer_transfert" class="btn-purple px-5 py-2.5 rounded-xl text-sm"><i class="fas fa-paper-plane mr-1.5"></i>Effectuer le transfert</button>
            </div>
        </form>
    </div>

    <!-- ============================================ -->
    <!-- MODAL CONFIRMATION SUPPRESSION               -->
    <!-- ============================================ -->
    <div id="deleteModalOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="deleteModalContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Supprimer ce transfert ?</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6" id="deleteModalText">Cette action est irréversible.</p>
        <form method="POST" action="../models/traitement/transfert-post.php" class="flex justify-center gap-3">
            <input type="hidden" name="transfert_id" id="deleteTransfertId">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
            <button type="submit" name="supprimer_transfert" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-semibold hover:opacity-90 transition-all"><i class="fas fa-trash mr-1.5"></i>Supprimer</button>
        </form>
    </div>

    <script>
        // Theme
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) html.classList.add('dark');
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
        document.querySelectorAll('.sidebar a').forEach(l => l.addEventListener('click', () => {
            if (window.innerWidth < 768) {
                sidebar.classList.remove('open');
                overlay.classList.add('hidden')
            }
        }));

        // Modals
        function openModal(o, c) {
            document.getElementById(o).classList.remove('hidden');
            document.getElementById(c).classList.remove('hidden')
        }

        function closeModal(o, c) {
            document.getElementById(o).classList.add('hidden');
            document.getElementById(c).classList.add('hidden')
        }

        function openTransfertModal() {
            document.getElementById('transfertForm')?.reset();
            document.getElementById('uniteLabel').textContent = 'unités';
            openModal('transfertModalOverlay', 'transfertModalContent');
        }

        function closeTransfertModal() {
            closeModal('transfertModalOverlay', 'transfertModalContent');
        }

        function openDeleteModal(id, produit) {
            document.getElementById('deleteTransfertId').value = id;
            document.getElementById('deleteModalText').innerHTML = `Vous allez supprimer le transfert #${id} (<strong>${produit}</strong>). Cette action est irréversible.`;
            openModal('deleteModalOverlay', 'deleteModalContent');
        }

        function closeDeleteModal() {
            closeModal('deleteModalOverlay', 'deleteModalContent');
        }

        // Close on overlay click
        ['transfertModalOverlay', 'deleteModalOverlay'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function(e) {
                if (e.target === this) closeModal(id, id.replace('Overlay', 'Content'));
            });
        });

        // Update unite label
        document.getElementById('produitSelect')?.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            document.getElementById('uniteLabel').textContent = opt?.dataset?.unite === 'metres' ? 'mètres' : (opt?.value ? 'pièces' : 'unités');
        });

        // Search
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const s = this.value.toLowerCase();
            let found = false;
            document.querySelectorAll('.transfert-row').forEach(r => {
                const match = r.dataset.id.includes(s) || r.dataset.expedition.includes(s) || r.dataset.destination.includes(s) || r.dataset.produit.includes(s);
                r.style.display = match ? '' : 'none';
                if (match) found = true;
            });
            document.getElementById('noResults')?.classList.toggle('hidden', found || s === '');
            document.getElementById('tableBody')?.classList.toggle('hidden', !found && s !== '');
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeTransfertModal();
                closeDeleteModal();
                if (sidebar.classList.contains('open')) toggleSidebar();
            }
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>