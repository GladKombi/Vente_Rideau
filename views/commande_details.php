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

$commande_id = $_GET['id'] ?? null;
if (!$commande_id) {
    header('Location: ventes_boutique.php');
    exit;
}

$message = '';
$message_type = '';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Vérifier la commande
try {
    $query = $pdo->prepare("SELECT c.*, b.nom as boutique_nom FROM commandes c JOIN boutiques b ON c.boutique_id = b.id WHERE c.id = ? AND c.boutique_id = ? AND c.statut = 0");
    $query->execute([$commande_id, $boutique_id]);
    $commande = $query->fetch(PDO::FETCH_ASSOC);
    if (!$commande) {
        $_SESSION['flash_message'] = ['text' => "Commande non trouvée.", 'type' => "error"];
        header('Location: ventes_boutique.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['text' => "Erreur: " . $e->getMessage(), 'type' => "error"];
    header('Location: ventes_boutique.php');
    exit;
}

// Produits de la commande
try {
    $query = $pdo->prepare("
        SELECT cp.id as commande_produit_id, cp.quantite, cp.prix_unitaire, s.id as stock_id, 
               p.matricule, p.designation, p.umProduit, nr.numero_rideau,
               (cp.quantite * cp.prix_unitaire) as total_ligne 
        FROM commande_produits cp 
        JOIN stock s ON cp.stock_id = s.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        LEFT JOIN numeros_rideaux nr ON s.numero_rideau_id = nr.id
        WHERE cp.commande_id = ? AND cp.statut = 0 AND s.statut = 0 AND p.statut = 0 
        ORDER BY cp.id DESC
    ");
    $query->execute([$commande_id]);
    $produits_commande = $query->fetchAll(PDO::FETCH_ASSOC);
    $total_commande = array_sum(array_column($produits_commande, 'total_ligne'));
} catch (PDOException $e) {
    $produits_commande = [];
    $total_commande = 0;
}

// Paiements
try {
    $query = $pdo->prepare("SELECT p.* FROM paiements p WHERE p.commandes_id = ? AND p.statut = 0 ORDER BY p.date DESC");
    $query->execute([$commande_id]);
    $paiements = $query->fetchAll(PDO::FETCH_ASSOC);
    $total_paye = array_sum(array_column($paiements, 'montant'));
    $reste_a_payer = $total_commande - $total_paye;
} catch (PDOException $e) {
    $paiements = [];
    $total_paye = 0;
    $reste_a_payer = $total_commande;
}

// Produits disponibles avec numéro de rideau
try {
    $query = $pdo->prepare("
        SELECT s.id as stock_id, s.quantite as stock_initial, s.prix, s.seuil_alerte_stock,
               p.matricule, p.designation, p.umProduit, nr.numero_rideau, nr.id as numero_rideau_id,
               COALESCE((SELECT SUM(cp2.quantite) FROM commande_produits cp2 WHERE cp2.stock_id = s.id AND cp2.statut = 0 AND cp2.commande_id != ?), 0) as deja_commande
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        LEFT JOIN numeros_rideaux nr ON s.numero_rideau_id = nr.id
        WHERE s.boutique_id = ? AND s.statut = 0 AND p.statut = 0 AND p.actif = 1 
        ORDER BY p.designation, nr.numero_rideau
    ");
    $query->execute([$commande_id, $boutique_id]);
    $produits_base = $query->fetchAll(PDO::FETCH_ASSOC);
    $produits_disponibles = [];
    foreach ($produits_base as $p) {
        $dispo = $p['stock_initial'] - $p['deja_commande'];
        if ($dispo > 0) {
            $p['stock_disponible'] = $dispo;
            $p['niveau_stock'] = ($dispo <= $p['seuil_alerte_stock']) ? 'faible' : 'ok';
            $produits_disponibles[] = $p;
        }
    }
} catch (PDOException $e) {
    $produits_disponibles = [];
}

// --- TRAITEMENT DES FORMULAIRES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ajouter produit
    if (isset($_POST['ajouter_produit'])) {
        try {
            $stock_id = (int)$_POST['stock_id'];
            $quantite_demandee = (float)$_POST['quantite'];
            
            // Vérifier que le stock appartient à la boutique
            $query = $pdo->prepare("
                SELECT s.quantite, s.prix, p.designation, p.umProduit, nr.numero_rideau 
                FROM stock s 
                JOIN produits p ON s.produit_matricule = p.matricule 
                LEFT JOIN numeros_rideaux nr ON s.numero_rideau_id = nr.id
                WHERE s.id = ? AND s.boutique_id = ? AND s.statut = 0
            ");
            $query->execute([$stock_id, $boutique_id]);
            $stock = $query->fetch(PDO::FETCH_ASSOC);
            if (!$stock) throw new Exception("Stock non trouvé");
            
            // Vérifier le stock disponible
            $query = $pdo->prepare("SELECT COALESCE(SUM(quantite),0) FROM commande_produits WHERE stock_id=? AND commande_id!=? AND statut=0");
            $query->execute([$stock_id, $commande_id]);
            $deja_autres = $query->fetchColumn();
            $dispo = $stock['quantite'] - $deja_autres;
            if ($quantite_demandee > $dispo) throw new Exception("Stock insuffisant. Disponible: " . number_format($dispo, 3));

            $prix = $_POST['prix_unitaire'] ? (float)$_POST['prix_unitaire'] : $stock['prix'];
            if ($prix <= 0) throw new Exception("Prix invalide");

            $pdo->prepare("INSERT INTO commande_produits (commande_id, stock_id, quantite, prix_unitaire) VALUES (?,?,?,?)")->execute([$commande_id, $stock_id, $quantite_demandee, $prix]);
            $_SESSION['flash_message'] = ['text' => "Produit ajouté !", 'type' => "success"];
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['text' => $e->getMessage(), 'type' => "error"];
        }
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }

    // Retirer produit
    if (isset($_POST['retirer_produit'])) {
        $pdo->prepare("UPDATE commande_produits SET statut=1 WHERE id=?")->execute([(int)$_POST['commande_produit_id']]);
        $_SESSION['flash_message'] = ['text' => "Produit retiré !", 'type' => "success"];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }

    // Modifier quantité
    if (isset($_POST['modifier_quantite'])) {
        try {
            $id = (int)$_POST['commande_produit_id'];
            $qte = (float)$_POST['nouvelle_quantite'];
            if ($qte <= 0) throw new Exception("Quantité invalide");
            $query = $pdo->prepare("SELECT cp.quantite, s.quantite as stock_init, p.umProduit FROM commande_produits cp JOIN stock s ON cp.stock_id=s.id JOIN produits p ON s.produit_matricule=p.matricule WHERE cp.id=? AND cp.statut=0");
            $query->execute([$id]);
            $p = $query->fetch(PDO::FETCH_ASSOC);
            if (!$p) throw new Exception("Produit non trouvé");
            
            // Vérifier le stock disponible
            $query = $pdo->prepare("SELECT COALESCE(SUM(quantite),0) FROM commande_produits WHERE stock_id=(SELECT stock_id FROM commande_produits WHERE id=?) AND commande_id!=? AND id!=? AND statut=0");
            $query->execute([$id, $commande_id, $id]);
            $autres = $query->fetchColumn();
            if ($qte > $p['stock_init'] - $autres) throw new Exception("Stock insuffisant");
            $pdo->prepare("UPDATE commande_produits SET quantite=? WHERE id=? AND statut=0")->execute([$qte, $id]);
            $_SESSION['flash_message'] = ['text' => "Quantité modifiée !", 'type' => "success"];
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['text' => $e->getMessage(), 'type' => "error"];
        }
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }

    // Modifier prix
    if (isset($_POST['modifier_prix'])) {
        $id = (int)$_POST['commande_produit_id'];
        $prix = (float)$_POST['nouveau_prix'];
        if ($prix < 0) {
            $_SESSION['flash_message'] = ['text' => "Prix invalide", 'type' => "error"];
        } else {
            $pdo->prepare("UPDATE commande_produits SET prix_unitaire=? WHERE id=? AND statut=0")->execute([$prix, $id]);
            $_SESSION['flash_message'] = ['text' => "Prix modifié !", 'type' => "success"];
        }
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }

    // Paiement CASH
    if (isset($_POST['enregistrer_paiement_cash'])) {
        try {
            if ($commande['etat'] == 'payee') throw new Exception("Déjà payée");
            if (empty($produits_commande)) throw new Exception("Aucun produit");
            if ($reste_a_payer <= 0) throw new Exception("Déjà payé");
            $pdo->prepare("INSERT INTO paiements (date, commandes_id, montant, statut) VALUES (?,?,?,0)")->execute([date('Y-m-d'), $commande_id, $reste_a_payer]);
            $pdo->prepare("UPDATE commandes SET etat='payee' WHERE id=? AND boutique_id=? AND statut=0")->execute([$commande_id, $boutique_id]);
            $_SESSION['flash_message'] = ['text' => "Paiement de " . number_format($reste_a_payer, 2) . " $ enregistré !", 'type' => "success"];
            header("Location: facture-cash.php?id=$commande_id");
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_message'] = ['text' => $e->getMessage(), 'type' => "error"];
            header("Location: commande_details.php?id=$commande_id");
            exit;
        }
    }

    // Annuler paiement
    if (isset($_POST['annuler_paiement'])) {
        $pid = (int)$_POST['paiement_id'];
        $pdo->prepare("UPDATE paiements SET statut=1 WHERE id=? AND commandes_id=?")->execute([$pid, $commande_id]);
        $np = $pdo->query("SELECT SUM(montant) FROM paiements WHERE commandes_id=$commande_id AND statut=0")->fetchColumn() ?? 0;
        if ($np < $total_commande) $pdo->prepare("UPDATE commandes SET etat='brouillon' WHERE id=? AND boutique_id=?")->execute([$commande_id, $boutique_id]);
        $_SESSION['flash_message'] = ['text' => "Paiement annulé !", 'type' => "success"];
        header("Location: commande_details.php?id=$commande_id");
        exit;
    }

    // Annuler commande
    if (isset($_POST['annuler_commande'])) {
        $pdo->prepare("UPDATE commande_produits SET statut=1 WHERE commande_id=? AND statut=0")->execute([$commande_id]);
        $pdo->prepare("UPDATE commandes SET statut=1 WHERE id=? AND boutique_id=?")->execute([$commande_id, $boutique_id]);
        $_SESSION['flash_message'] = ['text' => "Commande annulée !", 'type' => "success"];
        header("Location: ventes_boutique.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Commande #<?= $commande_id ?> - Boutique NGS</title>

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

        .btn-red {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.25);
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
            background: rgba(16, 185, 129, 0.1);
        }

        @media print {
            .sidebar,
            header,
            .print\:hidden,
            button,
            form,
            .modal,
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                font-size: 12pt;
                padding: 20px;
            }
            .premium-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
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
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white print:hidden">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg"><span class="font-bold text-white">NGS</span></div>
                <div><h2 class="font-bold text-sm">NGS Pro</h2><p class="text-[10px] text-gray-400">Dashboard Boutique</p></div>
            </div>
        </div>
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center"><i class="fas fa-store text-emerald-400"></i></div>
                <div class="min-w-0"><p class="font-semibold text-sm truncate"><?= htmlspecialchars($_SESSION['boutique_nom'] ?? 'Boutique') ?></p></div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Mes stocks</a>
            <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes</a>
            <a href="paiements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements</a>
            <a href="mouvements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements Caisse</a>
            <a href="transferts-boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-truck-loading w-4 text-center"></i>Transferts</a>
            <a href="rapports_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
            <a href="numeros_rideaux.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tags w-4 text-center"></i>N° Rideaux</a>

        </nav>
        <div class="p-3 border-t border-white/10 flex-shrink-0">
            <div class="flex items-center justify-between px-3 py-2 mb-2"><span class="text-xs text-gray-400"><i class="fas fa-moon mr-1"></i>Thème</span><button id="theme-toggle" class="theme-toggle" aria-label="Changer le thème"></button></div>
            <a href="../models/logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-red-400 hover:bg-red-500/10 transition-colors text-sm"><i class="fas fa-sign-out-alt w-4 text-center"></i>Déconnexion</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-y-auto">
        <header class="sticky top-0 z-30 glass border-b border-white/10 print:hidden">
            <div class="flex items-center justify-between px-4 md:px-6 py-4">
                <div class="flex items-center gap-3">
                    <button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors text-[var(--text-primary)]"><i class="fas fa-bars text-lg"></i></button>
                    <div>
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Commande #<?= $commande_id ?></h1>
                        <p class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($commande['numero_facture']) ?> • <?= $commande['client_nom'] ? htmlspecialchars($commande['client_nom']) : 'Client non renseigné' ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($commande['etat'] == 'brouillon'): ?>
                        <span class="px-3 py-1 rounded-full text-xs font-medium badge-warning">Brouillon</span>
                    <?php else: ?>
                        <span class="px-3 py-1 rounded-full text-xs font-medium badge-success">Payée</span>
                    <?php endif; ?>
                    <a href="ventes_boutique.php" class="btn-glass px-4 py-2 rounded-xl text-sm"><i class="fas fa-arrow-left mr-1.5"></i>Retour</a>
                    <button onclick="openAnnulationModal()" class="btn-red px-4 py-2 rounded-xl text-sm"><i class="fas fa-times mr-1.5"></i>Annuler</button>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <?php if ($message): ?>
                <div class="animate-fade-in-up print:hidden">
                    <div class="glass rounded-2xl p-4 border-l-4 <?= $message_type === 'success' ? 'border-emerald-500' : 'border-red-500' ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle text-emerald-500' : 'exclamation-circle text-red-500' ?> text-xl"></i>
                            <span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($message) ?></span>
                            <button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Résumé financier -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 animate-fade-in-up">
                <div class="premium-card p-5 border-l-4 border-blue-500">
                    <p class="text-xs text-[var(--text-muted)] mb-1">Total commande</p>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_commande, 2) ?> $</p>
                </div>
                <div class="premium-card p-5 border-l-4 border-emerald-500">
                    <p class="text-xs text-[var(--text-muted)] mb-1">Total payé</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($total_paye, 2) ?> $</p>
                </div>
                <div class="premium-card p-5 border-l-4 <?= $reste_a_payer > 0 ? 'border-amber-500' : 'border-emerald-500' ?>">
                    <p class="text-xs text-[var(--text-muted)] mb-1"><?= $reste_a_payer > 0 ? 'Reste à payer' : 'Total payé' ?></p>
                    <p class="text-2xl font-bold <?= $reste_a_payer > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' ?>"><?= number_format($reste_a_payer, 2) ?> $</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Formulaire ajout produit -->
                <div class="premium-card p-5 animate-fade-in-up print:hidden" style="animation-delay:0.1s">
                    <h3 class="text-base font-bold text-[var(--text-primary)] mb-4">Ajouter un produit</h3>
                    <?php if (empty($produits_disponibles)): ?>
                        <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/30 text-sm text-amber-700 dark:text-amber-300"><i class="fas fa-exclamation-triangle mr-1"></i>Aucun produit disponible en stock</div>
                    <?php else: ?>
                        <form method="POST" action="" class="space-y-3">
                            <!-- Recherche de produit par nom avec numéro de rideau -->
                            <div>
                                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Produit</label>
                                <div class="produit-search-wrapper">
                                    <input type="text" id="produitSearch" 
                                           class="w-full input-glass px-3 py-2.5 text-sm" 
                                           placeholder="Tapez le nom du produit ou le numéro de rideau..."
                                           autocomplete="off"
                                           oninput="filterProduits()"
                                           onfocus="filterProduits()">
                                    <div id="produitSuggestions" class="produit-suggestions"></div>
                                </div>
                                <input type="hidden" name="stock_id" id="stockHidden">
                                <div id="produitInfo" class="hidden mt-2 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/30 text-xs">
                                    <p><strong>Produit :</strong> <span id="infoDesignation">-</span></p>
                                    <?php if (isset($produits_disponibles[0]['numero_rideau'])): ?>
                                        <p><strong>N° Rideau :</strong> <span id="infoNumeroRideau" class="text-amber-600 dark:text-amber-400 font-medium">-</span></p>
                                    <?php endif; ?>
                                    <p><strong>Stock dispo :</strong> <span id="infoStock">-</span></p>
                                    <p><strong>Prix :</strong> <span id="infoPrix">-</span> $</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Prix unitaire ($)</label>
                                <input type="number" name="prix_unitaire" id="prixInput" step="0.01" min="0.01" class="w-full input-glass px-4 py-2.5 text-sm" placeholder="Auto si vide">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Quantité <span id="uniteLabel">unités</span></label>
                                <input type="number" name="quantite" id="qteInput" required step="0.001" min="0.001" class="w-full input-glass px-4 py-2.5 text-sm" placeholder="0.000">
                                <div class="flex justify-between mt-1">
                                    <span class="text-xs text-[var(--text-muted)]">Max: <span id="maxQte">0</span></span>
                                    <button type="button" onclick="setMax()" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Max dispo</button>
                                </div>
                            </div>
                            <button type="submit" name="ajouter_produit" class="btn-green w-full py-2.5 rounded-xl text-sm">Ajouter à la commande</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Produits de la commande -->
                <div class="premium-card p-5 lg:col-span-2 animate-fade-in-up" style="animation-delay:0.15s">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-base font-bold text-[var(--text-primary)]">Produits (<?= count($produits_commande) ?>)</h3>
                        <?php if ($commande['etat'] == 'brouillon' && $reste_a_payer > 0 && !empty($produits_commande)): ?>
                            <button onclick="openPaiementModal()" class="btn-green px-4 py-2 rounded-xl text-sm print:hidden"><i class="fas fa-money-bill-wave mr-1.5"></i>Paiement CASH</button>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($produits_commande)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[700px]">
                                <thead>
                                    <tr class="border-b border-[var(--divider)] text-left">
                                        <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Produit</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">N° Rideau</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Réf</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Qté</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Prix</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Total</th>
                                        <th class="px-4 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center print:hidden">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[var(--divider)]">
                                    <?php foreach ($produits_commande as $p):
                                        $ut = $p['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                    ?>
                                        <tr class="hover:bg-white/5 transition-colors">
                                            <td class="px-4 py-3.5 text-sm text-[var(--text-primary)]">
                                                <span class="font-medium"><?= htmlspecialchars($p['designation']) ?></span>
                                                <span class="text-xs text-[var(--text-muted)] block"><?= $ut ?></span>
                                            </td>
                                            <td class="px-4 py-3.5">
                                                <?php if (!empty($p['numero_rideau'])): ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                                        <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($p['numero_rideau']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-[var(--text-muted)]">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3.5 text-sm font-mono text-[var(--text-secondary)]"><?= $p['matricule'] ?></td>
                                            <td class="px-4 py-3.5 text-sm text-[var(--text-primary)]">
                                                <?= number_format($p['quantite'], 3) ?>
                                                <button onclick="openQtModal(<?= $p['commande_produit_id'] ?>,<?= $p['quantite'] ?>,'<?= $p['umProduit'] ?>')" class="text-blue-500 hover:text-blue-600 ml-1 print:hidden"><i class="fas fa-edit text-xs"></i></button>
                                            </td>
                                            <td class="px-4 py-3.5 text-sm text-[var(--text-primary)]">
                                                <?= number_format($p['prix_unitaire'], 2) ?> $
                                                <button onclick="openPrixModal(<?= $p['commande_produit_id'] ?>,<?= $p['prix_unitaire'] ?>)" class="text-blue-500 hover:text-blue-600 ml-1 print:hidden"><i class="fas fa-edit text-xs"></i></button>
                                            </td>
                                            <td class="px-4 py-3.5 text-sm font-bold text-[var(--text-primary)]"><?= number_format($p['total_ligne'], 2) ?> $</td>
                                            <td class="px-4 py-3.5 text-center print:hidden">
                                                <button onclick="openRetirerModal(<?= $p['commande_produit_id'] ?>,'<?= htmlspecialchars(addslashes($p['designation'])) ?>')" class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"><i class="fas fa-trash text-xs"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 pt-4 border-t border-[var(--divider)] text-right">
                            <span class="text-lg font-bold text-[var(--text-primary)]">TOTAL: <span class="text-2xl text-blue-600 dark:text-blue-400"><?= number_format($total_commande, 2) ?> $</span></span>
                        </div>
                        <div class="mt-4 flex justify-end print:hidden">
                            <a href="facture-credit.php?id=<?= $commande_id ?>" class="btn-glass px-4 py-2 rounded-xl text-sm"><i class="fas fa-print mr-1.5"></i>Imprimer</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-shopping-cart text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                            <p class="text-[var(--text-secondary)]">Aucun produit</p>
                        </div>
                    <?php endif; ?>

                    <!-- Paiements -->
                    <?php if (!empty($paiements)): ?>
                        <div class="mt-6 pt-6 border-t border-[var(--divider)]">
                            <h4 class="text-sm font-bold text-[var(--text-primary)] mb-3">Paiements</h4>
                            <div class="space-y-2">
                                <?php foreach ($paiements as $pa): ?>
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)]">
                                        <div>
                                            <span class="text-sm text-[var(--text-primary)]"><?= date('d/m/Y', strtotime($pa['date'])) ?></span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($pa['montant'], 2) ?> $</span>
                                            <form method="POST" action="" onsubmit="return confirm('Annuler ce paiement ?')" class="print:hidden">
                                                <input type="hidden" name="paiement_id" value="<?= $pa['id'] ?>">
                                                <button type="submit" name="annuler_paiement" class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"><i class="fas fa-times text-xs"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- MODALS -->
    <div id="annulationOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="annulationContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Annuler la commande ?</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6">Les produits seront de nouveau disponibles.</p>
        <form method="POST" action="" class="flex justify-center gap-3">
            <button type="button" onclick="closeModal('annulation')" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20">Annuler</button>
            <button type="submit" name="annuler_commande" class="btn-red px-5 py-2.5 rounded-xl text-sm">Confirmer</button>
        </form>
    </div>

    <div id="paiementOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="paiementContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-money-bill-wave text-5xl text-emerald-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Paiement CASH</h3>
        <div class="p-4 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)] mb-4">
            <p class="text-xs text-[var(--text-muted)]">Montant à payer</p>
            <p class="text-3xl font-bold text-[var(--text-primary)]"><?= number_format($reste_a_payer, 2) ?> $</p>
        </div>
        <form method="POST" action="" class="flex justify-center gap-3">
            <button type="button" onclick="closeModal('paiement')" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20">Annuler</button>
            <button type="submit" name="enregistrer_paiement_cash" class="btn-green px-5 py-2.5 rounded-xl text-sm">Confirmer</button>
        </form>
    </div>

    <div id="qtOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="qtContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6">
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-4">Modifier quantité</h3>
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="commande_produit_id" id="qtId">
            <input type="hidden" id="qtUnite">
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Nouvelle quantité (<span id="qtLabel">unités</span>)</label>
                <input type="number" name="nouvelle_quantite" id="qtVal" required step="0.001" min="0.001" class="w-full input-glass px-4 py-2.5 text-sm">
                <p class="text-xs text-[var(--text-muted)] mt-1">Actuelle: <span id="qtActuelle"></span></p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('qt')" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20">Annuler</button>
                <button type="submit" name="modifier_quantite" class="btn-glass px-5 py-2.5 rounded-xl text-sm">Enregistrer</button>
            </div>
        </form>
    </div>

    <div id="prixOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="prixContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6">
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-4">Modifier prix</h3>
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="commande_produit_id" id="prixId">
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Nouveau prix ($)</label>
                <input type="number" name="nouveau_prix" id="prixVal" required step="0.01" min="0" class="w-full input-glass px-4 py-2.5 text-sm">
                <p class="text-xs text-[var(--text-muted)] mt-1">Actuel: <span id="prixActuel"></span> $</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('prix')" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20">Annuler</button>
                <button type="submit" name="modifier_prix" class="btn-glass px-5 py-2.5 rounded-xl text-sm">Enregistrer</button>
            </div>
        </form>
    </div>

    <div id="retirerOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="retirerContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-trash-alt text-5xl text-red-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Retirer le produit ?</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6" id="retirerNom"></p>
        <form method="POST" action="" class="flex justify-center gap-3">
            <input type="hidden" name="commande_produit_id" id="retirerId">
            <button type="button" onclick="closeModal('retirer')" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20">Annuler</button>
            <button type="submit" name="retirer_produit" class="btn-red px-5 py-2.5 rounded-xl text-sm">Retirer</button>
        </form>
    </div>

    <script>
        // Données des produits disponibles avec numéro de rideau
        const produitsDisponibles = <?= json_encode($produits_disponibles) ?>;

        // Theme
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) html.classList.add('dark');
        themeToggle.addEventListener('click', () => { html.classList.toggle('dark'); localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light'); });

        // Sidebar
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        function toggleSidebar() { sidebar.classList.toggle('open'); overlay.classList.toggle('hidden'); }
        document.getElementById('mobileMenuBtn').addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Modals
        function openModal(type) { document.getElementById(type + 'Overlay').classList.remove('hidden'); document.getElementById(type + 'Content').classList.remove('hidden'); }
        function closeModal(type) { document.getElementById(type + 'Overlay').classList.add('hidden'); document.getElementById(type + 'Content').classList.add('hidden'); }
        function openAnnulationModal() { openModal('annulation'); }
        function openPaiementModal() { openModal('paiement'); }
        function openQtModal(id, qte, unite) {
            document.getElementById('qtId').value = id;
            document.getElementById('qtVal').value = qte;
            document.getElementById('qtUnite').value = unite;
            const ut = unite === 'metres' ? 'mètres' : 'pièces';
            document.getElementById('qtLabel').textContent = ut;
            document.getElementById('qtActuelle').textContent = qte + ' ' + ut;
            document.getElementById('qtVal').step = 0.001;
            document.getElementById('qtVal').min = 0.001;
            openModal('qt');
        }
        function openPrixModal(id, prix) {
            document.getElementById('prixId').value = id;
            document.getElementById('prixVal').value = prix;
            document.getElementById('prixActuel').textContent = prix;
            openModal('prix');
        }
        function openRetirerModal(id, nom) {
            document.getElementById('retirerId').value = id;
            document.getElementById('retirerNom').textContent = '« ' + nom + ' »';
            openModal('retirer');
        }

        ['annulation', 'paiement', 'qt', 'prix', 'retirer'].forEach(t => {
            document.getElementById(t + 'Overlay')?.addEventListener('click', function(e) { if (e.target === this) closeModal(t); });
        });

        // Autocomplétion des produits avec recherche sur nom ET numéro de rideau
        function filterProduits() {
            const searchTerm = document.getElementById('produitSearch').value.toLowerCase();
            const suggestions = document.getElementById('produitSuggestions');
            
            if (searchTerm.length === 0) { suggestions.classList.remove('show'); return; }

            // Recherche sur la désignation OU le numéro de rideau
            const filtered = produitsDisponibles.filter(p => 
                p.designation.toLowerCase().includes(searchTerm) ||
                (p.numero_rideau && p.numero_rideau.toLowerCase().includes(searchTerm))
            );
            
            if (filtered.length === 0) {
                suggestions.innerHTML = '<div class="produit-suggestion-item text-[var(--text-muted)]">Aucun produit trouvé</div>';
            } else {
                suggestions.innerHTML = filtered.map(p => {
                    const ut = p.umProduit === 'metres' ? 'mètres' : 'pièces';
                    const numeroDisplay = p.numero_rideau ? `<span class="text-xs text-amber-500">🔖 ${p.numero_rideau}</span>` : '';
                    return `<div class="produit-suggestion-item" onclick="selectProduit(${p.stock_id}, '${p.designation.replace(/'/g, "\\'")}', ${p.stock_disponible}, ${p.prix}, '${p.umProduit}', '${p.numero_rideau ? p.numero_rideau.replace(/'/g, "\\'") : ''}')">
                        ${p.designation} ${numeroDisplay}
                        <span class="text-xs text-[var(--text-muted)] block">(${numberFormat(p.stock_disponible, 3)} ${ut} dispo)</span>
                    </div>`;
                }).join('');
            }
            suggestions.classList.add('show');
        }

        function selectProduit(stockId, designation, stock, prix, unite, numeroRideau) {
            document.getElementById('produitSearch').value = designation + (numeroRideau ? ' (🔖 ' + numeroRideau + ')' : '');
            document.getElementById('stockHidden').value = stockId;
            document.getElementById('produitSuggestions').classList.remove('show');
            
            const ut = unite === 'metres' ? 'mètres' : 'pièces';
            document.getElementById('infoDesignation').textContent = designation;
            if (document.getElementById('infoNumeroRideau')) {
                document.getElementById('infoNumeroRideau').textContent = numeroRideau || 'Aucun';
            }
            document.getElementById('infoStock').textContent = numberFormat(stock, 3) + ' ' + ut;
            document.getElementById('infoPrix').textContent = numberFormat(prix, 2);
            document.getElementById('produitInfo').classList.remove('hidden');
            
            document.getElementById('prixInput').value = numberFormat(prix, 2);
            document.getElementById('uniteLabel').textContent = ut;
            document.getElementById('maxQte').textContent = numberFormat(stock, 3);
            document.getElementById('qteInput').step = 0.001;
            document.getElementById('qteInput').min = 0.001;
            document.getElementById('qteInput').value = '';
            document.getElementById('qteInput').max = stock;
        }

        function setMax() {
            const stockId = document.getElementById('stockHidden').value;
            if (!stockId) return;
            const produit = produitsDisponibles.find(p => p.stock_id == stockId);
            if (produit) {
                document.getElementById('qteInput').value = produit.stock_disponible;
            }
        }

        function numberFormat(number, decimals) {
            return parseFloat(number).toFixed(decimals);
        }

        // Fermer les suggestions au clic ailleurs
        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.produit-search-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('produitSuggestions').classList.remove('show');
            }
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                ['annulation', 'paiement', 'qt', 'prix', 'retirer'].forEach(t => closeModal(t));
                if (sidebar.classList.contains('open')) toggleSidebar();
            }
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>