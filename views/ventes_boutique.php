<?php
# Connexion à la base de données
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification BOUTIQUE
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
$total_commandes = 0;
$commandes = [];

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Générer un numéro de facture unique
function generateNumeroFacture($pdo, $boutique_id)
{
    $prefix = 'FACT-B' . $boutique_id . '-';
    $query = $pdo->prepare("SELECT numero_facture FROM commandes WHERE numero_facture LIKE ? ORDER BY id DESC LIMIT 1");
    $query->execute([$prefix . '%']);
    $lastFacture = $query->fetch(PDO::FETCH_ASSOC);
    if ($lastFacture && preg_match('/-(\d+)$/', $lastFacture['numero_facture'], $m)) {
        return $prefix . str_pad((int)$m[1] + 1, 3, '0', STR_PAD_LEFT);
    }
    return $prefix . '001';
}

// --- TRAITEMENT DES FORMULAIRES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajouter_commande'])) {
        try {
            $numero_facture = generateNumeroFacture($pdo, $boutique_id);
            $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM commandes WHERE numero_facture = ?");
            $checkQuery->execute([$numero_facture]);
            if ($checkQuery->fetchColumn() > 0) {
                if (preg_match('/-(\d+)$/', $numero_facture, $m)) {
                    $numero_facture = substr($numero_facture, 0, strrpos($numero_facture, '-') + 1) . str_pad((int)$m[1] + 1, 3, '0', STR_PAD_LEFT);
                }
            }
            $query = $pdo->prepare("INSERT INTO commandes (numero_facture, client_nom, boutique_id, date_commande) VALUES (?, ?, ?, NOW())");
            $query->execute([$numero_facture, $_POST['client_nom'] ?? '', $boutique_id]);
            $commande_id = $pdo->lastInsertId();
            $_SESSION['flash_message'] = ['text' => "Commande #$numero_facture créée !", 'type' => "success"];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = 'error';
        }
    } elseif (isset($_POST['modifier_commande'])) {
        try {
            $query = $pdo->prepare("UPDATE commandes SET client_nom = ? WHERE id = ? AND boutique_id = ? AND statut = 0");
            $query->execute([$_POST['client_nom'] ?? '', $_POST['commande_id'], $boutique_id]);
            $_SESSION['flash_message'] = ['text' => "Commande modifiée !", 'type' => "success"];
            header("Location: ventes_boutique.php");
            exit;
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = 'error';
        }
    } elseif (isset($_POST['archiver_commande'])) {
        try {
            $query = $pdo->prepare("UPDATE commandes SET statut = 1 WHERE id = ? AND boutique_id = ? AND statut = 0");
            $query->execute([$_POST['commande_id'], $boutique_id]);
            $_SESSION['flash_message'] = ['text' => "Commande archivée !", 'type' => "success"];
        } catch (PDOException $e) {
            $message = "Erreur : " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// AJAX : récupérer une commande
if (isset($_GET['action']) && $_GET['action'] == 'get_commande' && isset($_GET['id'])) {
    $query = $pdo->prepare("SELECT c.* FROM commandes c WHERE c.id = ? AND c.boutique_id = ? AND c.statut = 0");
    $query->execute([(int)$_GET['id'], $boutique_id]);
    $commande = $query->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($commande ? ['success' => true, 'commande' => $commande] : ['success' => false, 'message' => 'Non trouvée']);
    exit;
}

// Filtres
$filter_etat = $_GET['etat'] ?? '';
$filter_date_debut = $_GET['date_debut'] ?? '';
$filter_date_fin = $_GET['date_fin'] ?? '';
$search_term = $_GET['search'] ?? '';

$whereConditions = ["c.boutique_id = ?", "c.statut = 0"];
$params = [$boutique_id];
if ($filter_etat) {
    $whereConditions[] = "c.etat = ?";
    $params[] = $filter_etat;
}
if ($filter_date_debut) {
    $whereConditions[] = "DATE(c.date_commande) >= ?";
    $params[] = $filter_date_debut;
}
if ($filter_date_fin) {
    $whereConditions[] = "DATE(c.date_commande) <= ?";
    $params[] = $filter_date_fin;
}
if ($search_term) {
    $whereConditions[] = "(c.numero_facture LIKE ? OR c.client_nom LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}
$whereClause = implode(' AND ', $whereConditions);

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM commandes c WHERE $whereClause");
    $countQuery->execute($params);
    $total_commandes = $countQuery->fetchColumn();
    $totalPages = ceil($total_commandes / $limit);
    if ($totalPages < 1) $totalPages = 1;

    $sql = "SELECT c.*, (SELECT COUNT(*) FROM commande_produits cp WHERE cp.commande_id = c.id AND cp.statut = 0) as nb_produits, (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) FROM commande_produits cp WHERE cp.commande_id = c.id AND cp.statut = 0) as montant_total FROM commandes c WHERE $whereClause ORDER BY c.date_commande DESC LIMIT ? OFFSET ?";

    $query = $pdo->prepare($sql);
    $paramIndex = 1;
    foreach ($params as $p) {
        $query->bindValue($paramIndex, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $paramIndex++;
    }
    $query->bindValue($paramIndex, $limit, PDO::PARAM_INT);
    $query->bindValue($paramIndex + 1, $offset, PDO::PARAM_INT);
    $query->execute();
    $commandes = $query->fetchAll(PDO::FETCH_ASSOC);

    $statsQuery = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN etat='payee' THEN 1 ELSE 0 END) as payees, SUM(CASE WHEN etat='brouillon' THEN 1 ELSE 0 END) as brouillons, COALESCE((SELECT SUM(cp.quantite * cp.prix_unitaire) FROM commande_produits cp JOIN commandes c2 ON cp.commande_id=c2.id WHERE c2.boutique_id=? AND c2.statut=0 AND c2.etat='payee' AND cp.statut=0),0) as ca FROM commandes WHERE boutique_id=? AND statut=0");
    $statsQuery->execute([$boutique_id, $boutique_id]);
    $stats = $statsQuery->fetch(PDO::FETCH_ASSOC);
    $chiffre_affaires = $stats['ca'] ?? 0;
    $commandes_payees = $stats['payees'] ?? 0;
    $commandes_brouillon = $stats['brouillons'] ?? 0;
} catch (PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    $chiffre_affaires = 0;
    $commandes_payees = 0;
    $commandes_brouillon = 0;
    $commandes = [];
    $total_commandes = 0;
    $totalPages = 1;
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Ventes - Boutique NGS</title>

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
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
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
            border-left: 3px solid #34d399;
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
            outline: 2px solid #10b981;
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
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg">
                    <span class="font-bold text-white">NGS</span>
                </div>
                <div>
                    <h2 class="font-bold text-sm">NGS Pro</h2>
                    <p class="text-[10px] text-gray-400">Dashboard Boutique</p>
                </div>
            </div>
        </div>
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center"><i class="fas fa-store text-emerald-400"></i></div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($_SESSION['boutique_nom'] ?? 'Boutique') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Mes stocks</a>
            <a href="ventes_boutique.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes<?php if ($total_commandes > 0): ?><span class="ml-auto bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_commandes ?></span><?php endif; ?></a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Gestion des Ventes</h1>
                        <p class="text-xs text-[var(--text-muted)]">Nouvelle commande • Suivi</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openCommandeModal()" class="btn-green px-4 py-2 rounded-xl text-sm flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouvelle vente</span>
                    </button>
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
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Total</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-shopping-cart text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_commandes ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Commandes</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Payées</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-check-circle text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $commandes_payees ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Réglées</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-amber-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-amber-600 dark:text-amber-400">Brouillons</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center"><i class="fas fa-edit text-amber-600 dark:text-amber-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $commandes_brouillon ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">En attente</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-purple-600 dark:text-purple-400">CA</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-money-bill-wave text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($chiffre_affaires, 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Ventes</p>
                </div>
            </div>

            <!-- Filtres -->
            <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.15s">
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">État</label><select name="etat" class="w-full input-glass px-3 py-2.5 text-sm">
                                <option value="">Tous</option>
                                <option value="brouillon" <?= $filter_etat == 'brouillon' ? 'selected' : '' ?>>Brouillon</option>
                                <option value="payee" <?= $filter_etat == 'payee' ? 'selected' : '' ?>>Payée</option>
                            </select></div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date début</label><input type="date" name="date_debut" value="<?= $filter_date_debut ?>" class="w-full input-glass px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date fin</label><input type="date" name="date_fin" value="<?= $filter_date_fin ?>" class="w-full input-glass px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Recherche</label><input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Facture ou client..." class="w-full input-glass px-3 py-2.5 text-sm"></div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <a href="ventes_boutique.php" class="px-4 py-2 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Réinitialiser</a>
                        <button type="submit" class="btn-green px-4 py-2 rounded-xl text-sm">Filtrer</button>
                    </div>
                </form>
            </div>

            <!-- Tableau -->
            <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.2s">
                <div class="px-5 py-4 border-b border-[var(--divider)] flex items-center justify-between">
                    <h3 class="text-base font-bold text-[var(--text-primary)]">Liste des commandes</h3>
                    <span class="text-xs text-[var(--text-muted)]"><?= $total_commandes ?> commande(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px]">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Facture</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Client</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">État</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produits</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Montant</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]">
                            <?php if (!empty($commandes)): ?>
                                <?php foreach ($commandes as $c): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]"><?= htmlspecialchars($c['numero_facture']) ?></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= $c['client_nom'] ? htmlspecialchars($c['client_nom']) : '<span class="text-[var(--text-muted)] italic">Non renseigné</span>' ?></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= date('d/m/Y H:i', strtotime($c['date_commande'])) ?></td>
                                        <td class="px-5 py-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $c['etat'] == 'payee' ? 'badge-success' : 'badge-warning' ?>">
                                                <i class="fas fa-<?= $c['etat'] == 'payee' ? 'check-circle' : 'edit' ?> mr-1"></i><?= $c['etat'] == 'payee' ? 'Payée' : 'Brouillon' ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= $c['nb_produits'] ?? 0 ?> produit(s)</td>
                                        <td class="px-5 py-3.5 text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($c['montant_total'] ?? 0, 2) ?> $</td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <a href="commande_details.php?id=<?= $c['id'] ?>" class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors" title="Voir"><i class="fas fa-eye text-xs"></i></a>
                                                <button onclick="openDeleteModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['numero_facture'])) ?>')" class="p-2 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Archiver"><i class="fas fa-archive text-xs"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center"><i class="fas fa-shopping-cart text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                                        <p class="text-[var(--text-secondary)] font-medium">Aucune commande</p>
                                        <p class="text-xs text-[var(--text-muted)] mt-1">Créez votre première vente</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="px-5 py-4 border-t border-[var(--divider)] flex items-center justify-between">
                        <span class="text-xs text-[var(--text-muted)] hidden sm:block"><?= ($page - 1) * $limit + 1 ?>-<?= min($page * $limit, $total_commandes) ?> sur <?= $total_commandes ?></span>
                        <div class="flex items-center gap-1.5 mx-auto sm:mx-0">
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-left text-xs"></i></a>
                            <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i == $page ? 'btn-green shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-right text-xs"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- MODAL NOUVELLE COMMANDE -->
    <div id="commandeModalOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="commandeModalContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-plus-circle mr-2 text-emerald-500"></i>Nouvelle vente</h3>
            <button onclick="closeCommandeModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Nom du client</label>
                <input type="text" name="client_nom" class="w-full input-glass px-4 py-2.5 text-sm" placeholder="Ex: Jean Dupont (optionnel)">
            </div>
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/30">
                <p class="text-xs text-emerald-700 dark:text-emerald-300"><i class="fas fa-info-circle mr-1"></i>Numéro de facture automatique : <strong>FACT-B<?= $boutique_id ?>-XXX</strong></p>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeCommandeModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" name="ajouter_commande" class="btn-green px-5 py-2.5 rounded-xl text-sm">Créer la vente</button>
            </div>
        </form>
    </div>

    <!-- MODAL CONFIRMATION ARCHIVAGE -->
    <div id="deleteModalOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="deleteModalContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-archive text-5xl text-red-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Archiver cette commande ?</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6" id="deleteModalText">La commande sera masquée des listes.</p>
        <form method="POST" action="" class="flex justify-center gap-3">
            <input type="hidden" name="commande_id" id="deleteCommandeId">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
            <button type="submit" name="archiver_commande" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-semibold hover:opacity-90 transition-all">Archiver</button>
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

        // Modals
        function openModal(o, c) {
            document.getElementById(o).classList.remove('hidden');
            document.getElementById(c).classList.remove('hidden')
        }

        function closeModal(o, c) {
            document.getElementById(o).classList.add('hidden');
            document.getElementById(c).classList.add('hidden')
        }

        function openCommandeModal() {
            openModal('commandeModalOverlay', 'commandeModalContent');
        }

        function closeCommandeModal() {
            closeModal('commandeModalOverlay', 'commandeModalContent');
        }

        function openDeleteModal(id, facture) {
            document.getElementById('deleteCommandeId').value = id;
            document.getElementById('deleteModalText').innerHTML = `Vous allez archiver la commande <strong>${facture}</strong>.`;
            openModal('deleteModalOverlay', 'deleteModalContent');
        }

        function closeDeleteModal() {
            closeModal('deleteModalOverlay', 'deleteModalContent');
        }

        ['commandeModalOverlay', 'deleteModalOverlay'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function(e) {
                if (e.target === this) closeModal(id, id.replace('Overlay', 'Content'));
            });
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeCommandeModal();
                closeDeleteModal();
                if (sidebar.classList.contains('open')) toggleSidebar();
            }
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>