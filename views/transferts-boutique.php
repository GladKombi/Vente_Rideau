<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

# Connexion à la base de données
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification BOUTIQUE
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    $_SESSION['flash_message'] = ['text' => 'Veuillez vous connecter pour accéder à cette page', 'type' => 'error'];
    header('Location: ../login.php');
    exit;
}

$boutique_connectee_id = isset($_SESSION['boutique_id']) ? (int)$_SESSION['boutique_id'] : 0;
if ($boutique_connectee_id <= 0) {
    $_SESSION['flash_message'] = ['text' => "ID boutique invalide", 'type' => "error"];
    header('Location: ../login.php');
    exit;
}

$flash_message = '';
$flash_message_type = '';
$warning_message = '';
$total_transferts = 0;
$transferts = [];

if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message']['text'];
    $flash_message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer les infos de la boutique
try {
    $queryBoutiqueConnectee = $pdo->prepare("SELECT id, nom, statut, actif FROM boutiques WHERE id = :boutique_id");
    $queryBoutiqueConnectee->execute([':boutique_id' => $boutique_connectee_id]);
    $boutique_connectee = $queryBoutiqueConnectee->fetch(PDO::FETCH_ASSOC);

    if (!$boutique_connectee) {
        $_SESSION['flash_message'] = ['text' => "Boutique non trouvée", 'type' => "error"];
        header('Location: ../login.php');
        exit;
    }

    $isBoutiqueActive = ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1);
    if (!$isBoutiqueActive) {
        $warning_message = "Votre boutique est " . ($boutique_connectee['statut'] != 0 ? "suspendue" : "désactivée") . ". Certaines fonctionnalités peuvent être limitées.";
    }

    // Boutiques destination
    $queryBoutiquesDest = $pdo->prepare("SELECT id, nom FROM boutiques WHERE statut = 0 AND actif = 1 AND id != :boutique_id ORDER BY nom");
    $queryBoutiquesDest->execute([':boutique_id' => $boutique_connectee_id]);
    $boutiques_destination = $queryBoutiquesDest->fetchAll(PDO::FETCH_ASSOC);

    // Stocks disponibles
    $queryStocks = $pdo->prepare("
        SELECT s.id, s.produit_matricule, s.quantite, s.boutique_id, s.prix, s.seuil_alerte_stock,
               p.designation, p.umProduit
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        WHERE s.statut = 0 AND s.quantite > 0 AND s.boutique_id = :boutique_id
        ORDER BY p.designation
    ");
    $queryStocks->execute([':boutique_id' => $boutique_connectee_id]);
    $stocks = $queryStocks->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $flash_message = "Erreur : " . $e->getMessage();
    $flash_message_type = 'error';
    $boutique_connectee = ['id' => $boutique_connectee_id, 'nom' => 'Boutique inconnue', 'statut' => 0, 'actif' => 1];
    $boutiques_destination = [];
    $stocks = [];
}

// Traitement du transfert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['effectuer_transfert'])) {
    try {
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        $quantite_transferee = (float)($_POST['quantite_transferee'] ?? 0);
        $boutique_destination = (int)($_POST['boutique_destination'] ?? 0);

        if ($stock_id <= 0 || $quantite_transferee <= 0 || $boutique_destination <= 0) throw new Exception("Tous les champs sont obligatoires");
        if ($boutique_connectee_id == $boutique_destination) throw new Exception("Impossible de transférer vers la même boutique");

        $queryVerifStock = $pdo->prepare("
            SELECT s.*, p.designation, p.umProduit FROM stock s 
            JOIN produits p ON s.produit_matricule = p.matricule 
            WHERE s.id = :stock_id AND s.statut = 0 AND s.boutique_id = :boutique_id
        ");
        $queryVerifStock->execute([':stock_id' => $stock_id, ':boutique_id' => $boutique_connectee_id]);
        $stock_source = $queryVerifStock->fetch(PDO::FETCH_ASSOC);

        if (!$stock_source) throw new Exception("Ce stock ne vous appartient pas ou n'existe plus");
        if ($stock_source['quantite'] < $quantite_transferee) throw new Exception("Quantité insuffisante. Disponible : " . number_format($stock_source['quantite'], 3));

        $queryVerifBoutiqueDest = $pdo->prepare("SELECT id, nom FROM boutiques WHERE id = :boutique_id AND statut = 0 AND actif = 1");
        $queryVerifBoutiqueDest->execute([':boutique_id' => $boutique_destination]);
        $boutique_dest_info = $queryVerifBoutiqueDest->fetch(PDO::FETCH_ASSOC);
        if (!$boutique_dest_info) throw new Exception("Boutique destination invalide ou désactivée");

        $queryStockDest = $pdo->prepare("SELECT id, quantite, prix FROM stock WHERE boutique_id = :boutique_id AND produit_matricule = :produit_matricule AND prix = :prix AND statut = 0 LIMIT 1");
        $queryStockDest->execute([':boutique_id' => $boutique_destination, ':produit_matricule' => $stock_source['produit_matricule'], ':prix' => $stock_source['prix']]);
        $stock_destination = $queryStockDest->fetch(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        $queryUpdateSource = $pdo->prepare("UPDATE stock SET quantite = quantite - :quantite WHERE id = :stock_id AND statut = 0");
        $queryUpdateSource->execute([':quantite' => $quantite_transferee, ':stock_id' => $stock_id]);

        if ($stock_destination) {
            $queryUpdateDest = $pdo->prepare("UPDATE stock SET quantite = quantite + :quantite, type_mouvement = 'transfert' WHERE id = :stock_dest_id AND statut = 0");
            $queryUpdateDest->execute([':quantite' => $quantite_transferee, ':stock_dest_id' => $stock_destination['id']]);
        } else {
            $queryInsertDest = $pdo->prepare("INSERT INTO stock (type_mouvement, boutique_id, produit_matricule, quantite, prix, seuil_alerte_stock, date_creation, statut) VALUES ('transfert', :boutique_id, :produit_matricule, :quantite, :prix, :seuil_alerte, NOW(), 0)");
            $queryInsertDest->execute([':boutique_id' => $boutique_destination, ':produit_matricule' => $stock_source['produit_matricule'], ':quantite' => $quantite_transferee, ':prix' => $stock_source['prix'], ':seuil_alerte' => $stock_source['seuil_alerte_stock']]);
        }

        $queryInsertTransfert = $pdo->prepare("INSERT INTO transferts (date, stock_id, Expedition, Destination, statut) VALUES (NOW(), :stock_id, :expedition, :destination, 0)");
        $queryInsertTransfert->execute([':stock_id' => $stock_id, ':expedition' => $boutique_connectee_id, ':destination' => $boutique_destination]);

        $pdo->commit();

        $uniteText = $stock_source['umProduit'] == 'metres' ? 'mètres' : 'pièces';
        $_SESSION['flash_message'] = ['text' => "Transfert réussi ! " . number_format($quantite_transferee, 3) . " $uniteText de '" . $stock_source['designation'] . "' vers '" . $boutique_dest_info['nom'] . "'", 'type' => "success"];
        header('Location: transferts-boutique.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $flash_message = $e->getMessage();
        $flash_message_type = 'error';
    }
}

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM transferts t WHERE t.statut = 0 AND (t.Expedition = :b1 OR t.Destination = :b2)");
    $countQuery->execute([':b1' => $boutique_connectee_id, ':b2' => $boutique_connectee_id]);
    $total_transferts = $countQuery->fetchColumn();
    $totalPages = ceil($total_transferts / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

    $query = $pdo->prepare("
        SELECT t.*, s.produit_matricule, p.designation as produit_designation, p.umProduit,
               b1.nom as boutique_expedition, b2.nom as boutique_destination,
               st.quantite as quantite_source, st.prix as prix_unitaire
        FROM transferts t 
        JOIN stock s ON t.stock_id = s.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        JOIN boutiques b1 ON t.Expedition = b1.id 
        JOIN boutiques b2 ON t.Destination = b2.id
        JOIN stock st ON t.stock_id = st.id
        WHERE t.statut = 0 AND (t.Expedition = :b1 OR t.Destination = :b2)
        ORDER BY t.date DESC, t.id DESC 
        LIMIT :limit OFFSET :offset
    ");
    $query->bindValue(':b1', $boutique_connectee_id, PDO::PARAM_INT);
    $query->bindValue(':b2', $boutique_connectee_id, PDO::PARAM_INT);
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $transferts = $query->fetchAll(PDO::FETCH_ASSOC);

    $queryStats = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN t.Expedition = :b1 THEN 1 END) as envoyes,
            COUNT(CASE WHEN t.Destination = :b2 THEN 1 END) as recus,
            COUNT(DISTINCT p.matricule) as produits,
            SUM(st.quantite) as qt_totale
        FROM transferts t 
        JOIN stock st ON t.stock_id = st.id 
        JOIN produits p ON st.produit_matricule = p.matricule 
        WHERE t.statut = 0 AND (t.Expedition = :b3 OR t.Destination = :b4)
    ");
    $queryStats->execute([':b1' => $boutique_connectee_id, ':b2' => $boutique_connectee_id, ':b3' => $boutique_connectee_id, ':b4' => $boutique_connectee_id]);
    $stats = $queryStats->fetch(PDO::FETCH_ASSOC);
    $transferts_envoyes = $stats['envoyes'] ?? 0;
    $transferts_recus = $stats['recus'] ?? 0;
    $produits_transferes = $stats['produits'] ?? 0;
    $quantite_totale_transferee = $stats['qt_totale'] ?? 0;
} catch (PDOException $e) {
    $flash_message = "Erreur : " . $e->getMessage();
    $flash_message_type = 'error';
    $transferts_envoyes = 0;
    $transferts_recus = 0;
    $produits_transferes = 0;
    $quantite_totale_transferee = 0;
    $transferts = [];
    $total_transferts = 0;
    $totalPages = 1;
}

$statut_boutique = ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1)
    ? '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">Active</span>'
    : '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">Désactivée</span>';
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Transferts - <?= htmlspecialchars($boutique_connectee['nom']) ?> - NGS</title>

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

        .stock-card {
            background: var(--card-bg);
            backdrop-filter: blur(6px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .stock-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        }

        .stock-card-low {
            border-left-color: #ef4444;
        }

        .stock-card-medium {
            border-left-color: #f59e0b;
        }

        .stock-card-good {
            border-left-color: #10b981;
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
    <!-- SIDEBAR BOUTIQUE                              -->
    <!-- ============================================ -->
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg">
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
                <div class="w-10 h-10 rounded-full bg-blue-500/20 border border-blue-400/30 flex items-center justify-center">
                    <i class="fas fa-store text-blue-400"></i>
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($boutique_connectee['nom']) ?></p>
                    <div class="flex items-center gap-2 mt-0.5">
                        <?= $statut_boutique ?>
                    </div>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord
            </a>
            <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-warehouse w-4 text-center"></i>Mes stocks
            </a>
            <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-shopping-cart w-4 text-center"></i>Ventes
            </a>
            <a href="paiements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements
            </a>
            <a href="mouvements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements Caisse
            </a>
            <a href="transferts-boutique.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-truck-loading w-4 text-center"></i>Transferts
                <?php if ($total_transferts > 0): ?>
                    <span class="ml-auto bg-purple-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_transferts ?></span>
                <?php endif; ?>
            </a>
            <a href="rapports_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
                <i class="fas fa-chart-bar w-4 text-center"></i>Rapports
            </a>
            <a href="realisations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations</a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Transferts entre boutiques</h1>
                        <p class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($boutique_connectee['nom']) ?> • NGS</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1): ?>
                        <button onclick="openTransfertModal()" class="btn-purple px-4 py-2 rounded-xl text-sm flex items-center gap-2">
                            <i class="fas fa-exchange-alt"></i><span class="hidden sm:inline">Nouveau transfert</span>
                        </button>
                    <?php endif; ?>
                    <a href="stock_boutique.php" class="btn-glass px-4 py-2 rounded-xl text-sm hidden sm:flex items-center gap-2">
                        <i class="fas fa-warehouse"></i>Stocks
                    </a>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <!-- Warning message -->
            <?php if ($warning_message): ?>
                <div class="animate-fade-in-up">
                    <div class="glass rounded-2xl p-4 border-l-4 border-yellow-500">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                            <span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($warning_message) ?></span>
                            <button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Flash message -->
            <?php if ($flash_message): ?>
                <div class="animate-fade-in-up">
                    <div class="glass rounded-2xl p-4 border-l-4 <?= $flash_message_type === 'success' ? 'border-green-500' : 'border-red-500' ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-<?= $flash_message_type === 'success' ? 'check-circle text-green-500' : 'exclamation-circle text-red-500' ?> text-xl"></i>
                            <span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($flash_message) ?></span>
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
                    <p class="text-xs text-[var(--text-muted)] mt-1">Transferts</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-indigo-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400">Envoyés</span>
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                            <i class="fas fa-paper-plane text-indigo-600 dark:text-indigo-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $transferts_envoyes ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Par votre boutique</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Reçus</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                            <i class="fas fa-inbox text-emerald-600 dark:text-emerald-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $transferts_recus ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Par votre boutique</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-cyan-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-medium text-cyan-600 dark:text-cyan-400">Qté totale</span>
                        <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center">
                            <i class="fas fa-weight-hanging text-cyan-600 dark:text-cyan-400 text-sm"></i>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($quantite_totale_transferee, 3) ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Unités</p>
                </div>
            </div>

            <!-- Stocks disponibles -->
            <?php if (!empty($stocks)): ?>
                <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.15s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-[var(--text-primary)]">Stocks disponibles pour transfert</h2>
                        <span class="text-xs text-[var(--text-muted)]"><?= count($stocks) ?> produits</span>
                    </div>
                    <?php if ($boutique_connectee['statut'] != 0 || $boutique_connectee['actif'] != 1): ?>
                        <div class="p-4 rounded-xl bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800/30 mb-4">
                            <p class="text-xs text-yellow-700 dark:text-yellow-300"><i class="fas fa-exclamation-triangle mr-1"></i>Transferts temporairement désactivés. Vous pouvez consulter vos stocks.</p>
                        </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($stocks as $stock):
                            $uniteText = $stock['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                            $cardClass = $stock['quantite'] < 5 ? 'stock-card-low' : ($stock['quantite'] < 10 ? 'stock-card-medium' : 'stock-card-good');
                            $isActive = $boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1;
                        ?>
                            <div class="stock-card p-4 <?= $cardClass ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-medium text-sm text-[var(--text-primary)]"><?= htmlspecialchars($stock['designation']) ?></h3>
                                        <p class="text-xs text-[var(--text-muted)] font-mono"><?= $stock['produit_matricule'] ?></p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $stock['umProduit'] == 'metres' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' ?>"><?= $uniteText ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-3">
                                    <span class="text-lg font-bold text-[var(--text-primary)]"><?= number_format($stock['quantite'], 3) ?> <span class="text-sm font-normal text-[var(--text-muted)]"><?= $uniteText ?></span></span>
                                    <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400"><?= number_format($stock['quantite'] * $stock['prix'], 2) ?> $</span>
                                </div>
                                <?php if ($isActive): ?>
                                    <button onclick="selectStockForTransfert(<?= $stock['id'] ?>, '<?= htmlspecialchars(addslashes($stock['designation'])) ?>', <?= $stock['quantite'] ?>, '<?= $stock['umProduit'] ?>')"
                                        class="w-full py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity">
                                        <i class="fas fa-exchange-alt mr-1.5"></i>Transférer ce stock
                                    </button>
                                <?php else: ?>
                                    <button disabled class="w-full py-2 bg-gray-300 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-xs font-semibold rounded-lg cursor-not-allowed">
                                        <i class="fas fa-lock mr-1.5"></i>Transfert désactivé
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recherche -->
            <div class="premium-card p-4 animate-fade-in-up" style="animation-delay:0.2s">
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
            <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.25s">
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
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]" id="tableBody">
                            <?php if (!empty($transferts)): ?>
                                <?php foreach ($transferts as $t):
                                    $uniteText = $t['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                    $isExpediteur = ($t['Expedition'] == $boutique_connectee_id);
                                    $valeur = $t['quantite_source'] * $t['prix_unitaire'];
                                ?>
                                    <tr class="hover:bg-white/5 transition-colors transfert-row"
                                        data-id="<?= $t['id'] ?>"
                                        data-expedition="<?= strtolower($t['boutique_expedition']) ?>"
                                        data-destination="<?= strtolower($t['boutique_destination']) ?>"
                                        data-produit="<?= strtolower($t['produit_designation']) ?>">
                                        <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $t['id'] ?></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= date('d/m/Y', strtotime($t['date'])) ?></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-primary)]">
                                            <span class="font-medium"><?= htmlspecialchars($t['produit_designation']) ?></span>
                                            <span class="text-xs text-[var(--text-muted)] block font-mono"><?= $t['produit_matricule'] ?></span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $isExpediteur ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' : 'bg-gray-100 dark:bg-gray-800 text-[var(--text-secondary)]' ?>"><?= htmlspecialchars($t['boutique_expedition']) ?></span>
                                                <i class="fas fa-arrow-right text-purple-500 text-xs"></i>
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= !$isExpediteur ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' : 'bg-gray-100 dark:bg-gray-800 text-[var(--text-secondary)]' ?>"><?= htmlspecialchars($t['boutique_destination']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm font-bold text-[var(--text-primary)]"><?= number_format($t['quantite_source'], 3) ?> <span class="text-xs text-[var(--text-muted)] font-normal"><?= $uniteText ?></span></td>
                                        <td class="px-5 py-3.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400"><?= number_format($valeur, 2) ?> $</td>
                                        <td class="px-5 py-3.5 text-center">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $isExpediteur ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' ?>">
                                                <i class="fas fa-<?= $isExpediteur ? 'paper-plane' : 'inbox' ?> mr-1"></i><?= $isExpediteur ? 'Envoi' : 'Réception' ?>
                                            </span>
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
        <form method="POST" action="transferts-boutique.php" class="space-y-4">
            <input type="hidden" name="effectuer_transfert" value="1">

            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Boutique source</label>
                <div class="p-3 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)] text-sm font-medium text-[var(--text-primary)]">
                    <?= htmlspecialchars($boutique_connectee['nom']) ?> <span class="text-xs text-[var(--text-muted)]">(Votre boutique)</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Stock à transférer *</label>
                <select name="stock_id" id="stockSelect" required class="w-full input-glass px-3 py-2.5 text-sm" onchange="updateStockInfo()">
                    <option value="">Sélectionnez un stock</option>
                    <?php foreach ($stocks as $s): ?>
                        <option value="<?= $s['id'] ?>" data-quantite="<?= $s['quantite'] ?>" data-produit="<?= htmlspecialchars($s['designation']) ?>" data-unite="<?= $s['umProduit'] ?>" data-prix="<?= $s['prix'] ?>">
                            <?= htmlspecialchars($s['designation']) ?> (<?= number_format($s['quantite'], 3) ?> disp.)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="stockInfo" class="hidden mt-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 text-xs space-y-1">
                    <p><strong>Produit :</strong> <span id="infoProduit">-</span></p>
                    <p><strong>Disponible :</strong> <span id="infoQuantite">0</span> <span id="infoUnite">unités</span></p>
                    <p><strong>Prix unitaire :</strong> <span id="infoPrix">0.00</span> $</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Boutique destination *</label>
                <select name="boutique_destination" required class="w-full input-glass px-3 py-2.5 text-sm">
                    <option value="">Sélectionnez...</option>
                    <?php foreach ($boutiques_destination as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($boutiques_destination)): ?>
                    <p class="text-xs text-red-500 mt-1">Aucune autre boutique disponible</p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Quantité *</label>
                <div class="relative">
                    <input type="number" name="quantite_transferee" id="quantiteInput" step="0.001" min="0.001" required placeholder="0.000" class="w-full input-glass pl-4 pr-16 py-2.5 text-sm">
                    <span id="uniteLabel" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[var(--text-muted)] bg-[var(--input-bg)] px-2 py-0.5 rounded">unités</span>
                </div>
                <p class="text-xs text-[var(--text-muted)] mt-1" id="quantiteMaxInfo"></p>
            </div>

            <div id="valeurBox" class="hidden p-4 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800/30">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-purple-700 dark:text-purple-300 font-medium">Valeur du transfert</p>
                    <p class="text-lg font-bold text-purple-900 dark:text-purple-200" id="valeurTotale">0.00 $</p>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800/30">
                <p class="text-xs text-purple-700 dark:text-purple-300"><i class="fas fa-info-circle mr-1"></i>La quantité sera déduite de votre stock et ajoutée au stock destination. Le prix unitaire est conservé.</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeTransfertModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" name="effectuer_transfert" id="submitBtn" class="btn-purple px-5 py-2.5 rounded-xl text-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    <i class="fas fa-paper-plane mr-1.5"></i>Effectuer le transfert
                </button>
            </div>
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
            document.getElementById('stockInfo')?.classList.add('hidden');
            document.getElementById('valeurBox')?.classList.add('hidden');
            document.getElementById('quantiteMaxInfo').textContent = '';
            document.getElementById('uniteLabel').textContent = 'unités';
            document.getElementById('submitBtn').disabled = true;
            openModal('transfertModalOverlay', 'transfertModalContent');
        }

        function closeTransfertModal() {
            closeModal('transfertModalOverlay', 'transfertModalContent');
        }

        function selectStockForTransfert(id, produit, quantite, unite) {
            document.getElementById('stockSelect').value = id;
            updateStockInfo();
            openTransfertModal();
            setTimeout(() => document.getElementById('quantiteInput').focus(), 300);
        }

        // Update stock info
        function updateStockInfo() {
            const sel = document.getElementById('stockSelect');
            const opt = sel.options[sel.selectedIndex];
            const infoBox = document.getElementById('stockInfo');
            if (opt && opt.value) {
                const q = parseFloat(opt.dataset.quantite);
                const u = opt.dataset.unite === 'metres' ? 'mètres' : 'pièces';
                document.getElementById('infoProduit').textContent = opt.dataset.produit;
                document.getElementById('infoQuantite').textContent = q.toFixed(3);
                document.getElementById('infoUnite').textContent = u;
                document.getElementById('infoPrix').textContent = parseFloat(opt.dataset.prix).toFixed(2);
                document.getElementById('uniteLabel').textContent = u;
                document.getElementById('quantiteMaxInfo').textContent = `Maximum : ${q.toFixed(3)} ${u}`;
                document.getElementById('quantiteInput').max = q;
                document.getElementById('quantiteInput').value = '';
                document.getElementById('valeurBox').classList.add('hidden');
                document.getElementById('submitBtn').disabled = document.getElementById('boutique_destination').options.length <= 1;
                infoBox.classList.remove('hidden');
            } else {
                infoBox.classList.add('hidden');
                document.getElementById('quantiteMaxInfo').textContent = '';
                document.getElementById('submitBtn').disabled = true;
            }
        }

        // Valeur en temps réel
        document.getElementById('quantiteInput').addEventListener('input', function() {
            const sel = document.getElementById('stockSelect');
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return;
            const q = parseFloat(opt.dataset.quantite);
            const p = parseFloat(opt.dataset.prix);
            const v = parseFloat(this.value) || 0;
            const box = document.getElementById('valeurBox');
            if (v > 0 && v <= q) {
                box.classList.remove('hidden');
                document.getElementById('valeurTotale').textContent = (v * p).toFixed(2) + ' $';
                document.getElementById('submitBtn').disabled = false;
            } else {
                box.classList.add('hidden');
                document.getElementById('submitBtn').disabled = true;
            }
        });

        // Close modal on overlay click
        document.getElementById('transfertModalOverlay')?.addEventListener('click', function(e) {
            if (e.target === this) closeTransfertModal();
        });

        // Search
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const s = this.value.toLowerCase();
            let found = false;
            document.querySelectorAll('.transfert-row').forEach(r => {
                const m = r.dataset.id.includes(s) || r.dataset.expedition.includes(s) || r.dataset.destination.includes(s) || r.dataset.produit.includes(s);
                r.style.display = m ? '' : 'none';
                if (m) found = true;
            });
            document.getElementById('noResults')?.classList.toggle('hidden', found || s === '');
            document.getElementById('tableBody')?.classList.toggle('hidden', !found && s !== '');
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeTransfertModal();
                if (sidebar.classList.contains('open')) toggleSidebar();
            }
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>