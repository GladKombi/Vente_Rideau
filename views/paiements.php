<?php
// Vérification que la connexion PDO est disponible
if (!file_exists('../connexion/connexion.php')) {
    die('Erreur: Fichier de connexion introuvable');
}
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
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-t');
$commande_id_filter = $_GET['commande_id'] ?? '';
$statut_filter = $_GET['statut'] ?? '';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// --- AJOUTER UN PAIEMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_paiement'])) {
    try {
        $date_paiement = date('Y-m-d');
        $commande_id = (int)$_POST['commande_id'];
        $montant = (float)$_POST['montant'];

        if ($commande_id <= 0) throw new Exception("Veuillez sélectionner une commande");
        if ($montant <= 0) throw new Exception("Le montant doit être supérieur à 0");

        $query = $pdo->prepare("
            SELECT c.id, c.etat,
                   (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) 
                    FROM commande_produits cp 
                    WHERE cp.commande_id = c.id AND cp.statut = 0) as total_commande
            FROM commandes c 
            WHERE c.id = ? AND c.boutique_id = ? AND c.statut = 0 AND c.etat != 'payee'
        ");
        $query->execute([$commande_id, $boutique_id]);
        $commande = $query->fetch(PDO::FETCH_ASSOC);

        if (!$commande) throw new Exception("Commande non trouvée, déjà payée ou accès non autorisé");

        $query = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) as total_paye FROM paiements WHERE commandes_id = ? AND statut = 0");
        $query->execute([$commande_id]);
        $total_paye = $query->fetchColumn();

        $montant_restant = (float)$commande['total_commande'] - $total_paye;
        if ($montant > $montant_restant) {
            throw new Exception("Le montant (" . number_format($montant, 2) . " $) dépasse le reste à payer (" . number_format($montant_restant, 2) . " $)");
        }

        // Insérer le paiement
        $query = $pdo->prepare("INSERT INTO paiements (date, commandes_id, montant, statut) VALUES (?, ?, ?, 0)");
        $query->execute([$date_paiement, $commande_id, $montant]);
        $paiement_id = $pdo->lastInsertId();

        // Vérifier si la commande est maintenant entièrement payée
        $query = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) as nouveau_total FROM paiements WHERE commandes_id = ? AND statut = 0");
        $query->execute([$commande_id]);
        if ($query->fetchColumn() >= $commande['total_commande']) {
            $pdo->prepare("UPDATE commandes SET etat = 'payee' WHERE id = ? AND boutique_id = ? AND statut = 0")
                ->execute([$commande_id, $boutique_id]);
        }

        // Stocker le message de succès en session
        $_SESSION['flash_message'] = [
            'text' => "✅ Paiement #{$paiement_id} de " . number_format($montant, 2) . " $ enregistré avec succès pour la commande #{$commande_id} !",
            'type' => "success"
        ];

        // Redirection vers le reçu boutique compatible
        header("Location: recu.php?id={$paiement_id}");
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = [
            'text' => "❌ Erreur : " . $e->getMessage(),
            'type' => "error"
        ];
        header("Location: paiements.php");
        exit;
    }
}

// --- MODIFIER UN PAIEMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_paiement'])) {
    try {
        $paiement_id = (int)$_POST['paiement_id'];
        $montant = (float)$_POST['montant'];

        if ($montant <= 0) throw new Exception("Le montant doit être supérieur à 0");

        $query = $pdo->prepare("
            SELECT p.id, p.commandes_id, p.montant as ancien_montant,
                   (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) 
                    FROM commande_produits cp 
                    WHERE cp.commande_id = p.commandes_id AND cp.statut = 0) as total_commande
            FROM paiements p 
            JOIN commandes c ON p.commandes_id = c.id
            WHERE p.id = ? AND c.boutique_id = ? AND p.statut = 0
        ");
        $query->execute([$paiement_id, $boutique_id]);
        $paiement_info = $query->fetch(PDO::FETCH_ASSOC);

        if (!$paiement_info) throw new Exception("Paiement non trouvé");

        $query = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) - ? as total_sans_ancien FROM paiements WHERE commandes_id = ? AND statut = 0");
        $query->execute([$paiement_info['ancien_montant'], $paiement_info['commandes_id']]);
        $total_sans_ancien = $query->fetchColumn();

        $reste = (float)$paiement_info['total_commande'] - $total_sans_ancien;
        if ($montant > $reste) throw new Exception("Le nouveau montant dépasse le reste disponible (" . number_format($reste, 2) . " $)");

        $pdo->prepare("UPDATE paiements SET montant = ? WHERE id = ? AND statut = 0")->execute([$montant, $paiement_id]);

        $query = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM paiements WHERE commandes_id = ? AND statut = 0");
        $query->execute([$paiement_info['commandes_id']]);
        $nouveau_total = $query->fetchColumn();

        if ($nouveau_total >= $paiement_info['total_commande']) {
            $pdo->prepare("UPDATE commandes SET etat = 'payee' WHERE id = ? AND boutique_id = ? AND statut = 0")
                ->execute([$paiement_info['commandes_id'], $boutique_id]);
        } else {
            $pdo->prepare("UPDATE commandes SET etat = 'brouillon' WHERE id = ? AND boutique_id = ? AND statut = 0")
                ->execute([$paiement_info['commandes_id'], $boutique_id]);
        }

        $_SESSION['flash_message'] = ['text' => "✅ Paiement #{$paiement_id} modifié avec succès !", 'type' => "success"];
        header("Location: paiements.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['text' => "❌ Erreur : " . $e->getMessage(), 'type' => "error"];
        header("Location: paiements.php");
        exit;
    }
}

// --- SUPPRIMER UN PAIEMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_paiement'])) {
    try {
        $paiement_id = (int)$_POST['paiement_id'];

        $query = $pdo->prepare("
            SELECT p.id, p.commandes_id 
            FROM paiements p 
            JOIN commandes c ON p.commandes_id = c.id 
            WHERE p.id = ? AND c.boutique_id = ? AND p.statut = 0
        ");
        $query->execute([$paiement_id, $boutique_id]);
        $paiement_info = $query->fetch(PDO::FETCH_ASSOC);

        if (!$paiement_info) throw new Exception("Paiement non trouvé");

        $pdo->prepare("UPDATE paiements SET statut = 1 WHERE id = ?")->execute([$paiement_id]);

        $query = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM paiements WHERE commandes_id = ? AND statut = 0");
        $query->execute([$paiement_info['commandes_id']]);
        $total_restant = $query->fetchColumn();

        $query = $pdo->prepare("SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) as total FROM commande_produits cp WHERE cp.commande_id = ? AND cp.statut = 0");
        $query->execute([$paiement_info['commandes_id']]);
        $total_commande = $query->fetchColumn();

        if ($total_restant >= $total_commande) {
            $pdo->prepare("UPDATE commandes SET etat = 'payee' WHERE id = ? AND boutique_id = ? AND statut = 0")
                ->execute([$paiement_info['commandes_id'], $boutique_id]);
        } else {
            $pdo->prepare("UPDATE commandes SET etat = 'brouillon' WHERE id = ? AND boutique_id = ? AND statut = 0")
                ->execute([$paiement_info['commandes_id'], $boutique_id]);
        }

        $_SESSION['flash_message'] = ['text' => "✅ Paiement #{$paiement_id} supprimé avec succès !", 'type' => "success"];
        header("Location: paiements.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = ['text' => "❌ Erreur : " . $e->getMessage(), 'type' => "error"];
        header("Location: paiements.php");
        exit;
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
try {
    $sql = "SELECT p.id as paiement_id, p.date, p.montant, p.statut, c.id as commande_id, c.numero_facture, c.client_nom, c.date_commande, c.etat as commande_etat FROM paiements p JOIN commandes c ON p.commandes_id = c.id WHERE c.boutique_id = ?";
    $params = [$boutique_id];
    if (!empty($date_debut)) {
        $sql .= " AND p.date >= ?";
        $params[] = $date_debut;
    }
    if (!empty($date_fin)) {
        $sql .= " AND p.date <= ?";
        $params[] = $date_fin;
    }
    if (!empty($commande_id_filter)) {
        $sql .= " AND c.id = ?";
        $params[] = $commande_id_filter;
    }
    if ($statut_filter !== '' && $statut_filter !== 'all') {
        $sql .= " AND p.statut = ?";
        $params[] = $statut_filter;
    } elseif ($statut_filter === '') {
        $sql .= " AND p.statut = 0";
    }
    $sql .= " ORDER BY p.date DESC, p.id DESC";

    $query = $pdo->prepare($sql);
    $query->execute($params);
    $paiements = $query->fetchAll(PDO::FETCH_ASSOC);

    $commande_ids = array_unique(array_column($paiements, 'commande_id'));
    $totaux_commandes = [];
    $totaux_payes = [];

    if (!empty($commande_ids)) {
        $placeholders = implode(',', array_fill(0, count($commande_ids), '?'));
        $q = $pdo->prepare("SELECT cp.commande_id, COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) as total FROM commande_produits cp WHERE cp.commande_id IN ($placeholders) AND cp.statut = 0 GROUP BY cp.commande_id");
        $q->execute($commande_ids);
        foreach ($q->fetchAll() as $row) $totaux_commandes[$row['commande_id']] = (float)$row['total'];

        $q = $pdo->prepare("SELECT commandes_id, COALESCE(SUM(montant), 0) as total FROM paiements WHERE commandes_id IN ($placeholders) AND statut = 0 GROUP BY commandes_id");
        $q->execute($commande_ids);
        foreach ($q->fetchAll() as $row) $totaux_payes[$row['commandes_id']] = (float)$row['total'];
    }

    foreach ($paiements as &$p) {
        $cid = $p['commande_id'];
        $p['total_commande'] = $totaux_commandes[$cid] ?? 0;
        $p['total_paye'] = $totaux_payes[$cid] ?? 0;
        $p['reste_a_payer'] = $p['total_commande'] - $p['total_paye'];
        $p['statut_paiement'] = $p['reste_a_payer'] <= 0 ? 'payee' : ($p['total_paye'] > 0 ? 'partiel' : 'impayee');
    }
    unset($p);

    $total_paiements = array_sum(array_column($paiements, 'montant'));
    $total_actifs = array_sum(array_column(array_filter($paiements, fn($p) => $p['statut'] == 0), 'montant'));
    $total_supprimes = array_sum(array_column(array_filter($paiements, fn($p) => $p['statut'] == 1), 'montant'));
} catch (PDOException $e) {
    $paiements = [];
    $total_paiements = 0;
    $total_actifs = 0;
    $total_supprimes = 0;
}

// Commandes avec reste à payer
try {
    $query = $pdo->prepare("SELECT c.id, c.numero_facture, c.client_nom, c.date_commande, (SELECT COALESCE(SUM(cp.quantite * cp.prix_unitaire), 0) FROM commande_produits cp WHERE cp.commande_id = c.id AND cp.statut = 0) as total_commande, (SELECT COALESCE(SUM(p.montant), 0) FROM paiements p WHERE p.commandes_id = c.id AND p.statut = 0) as total_paye FROM commandes c WHERE c.boutique_id = ? AND c.statut = 0 AND c.etat = 'brouillon' ORDER BY c.date_commande DESC LIMIT 100");
    $query->execute([$boutique_id]);
    $commandes_avec_reste = array_filter($query->fetchAll(PDO::FETCH_ASSOC), fn($c) => ($c['total_commande'] - $c['total_paye']) > 0);
} catch (PDOException $e) {
    $commandes_avec_reste = [];
}

// Pas de section mensuelle sur cette page
$stats_mois = []; 
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Paiements - Boutique NGS</title>
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
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg"><span class="font-bold text-white">NGS</span></div>
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
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($_SESSION['boutique_nom'] ?? 'Boutique') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Mes stocks</a>
            <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes</a>
            <a href="paiements.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements</a>
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
                <div class="flex items-center gap-3"><button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors text-[var(--text-primary)]"><i class="fas fa-bars text-lg"></i></button>
                    <div>
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Gestion des Paiements</h1>
                        <p class="text-xs text-[var(--text-muted)]">Suivi et gestion des paiements clients</p>
                    </div>
                </div>
                <div class="flex items-center gap-2"><button onclick="openAjoutModal()" class="btn-purple px-4 py-2 rounded-xl text-sm flex items-center gap-2" <?= empty($commandes_avec_reste) ? 'disabled' : '' ?>><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouveau paiement</span></button></div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <?php if ($message): ?>
                <div class="animate-fade-in-up">
                    <div class="glass rounded-2xl p-4 border-l-4 <?= $message_type === 'success' ? 'border-emerald-500' : 'border-red-500' ?>">
                        <div class="flex items-center gap-3"><i class="fas fa-<?= $message_type === 'success' ? 'check-circle text-emerald-500' : 'exclamation-circle text-red-500' ?> text-xl"></i><span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($message) ?></span><button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-3"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Actifs</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-check-circle text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_actifs, 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1"><?= count(array_filter($paiements, fn($p) => $p['statut'] == 0)) ?> paiement(s)</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-3"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Total</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-chart-line text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_paiements, 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1"><?= count($paiements) ?> paiement(s)</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-red-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-3"><span class="text-xs font-medium text-red-600 dark:text-red-400">Supprimés</span>
                        <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center"><i class="fas fa-trash-alt text-red-600 dark:text-red-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_supprimes, 2) ?> $</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1"><?= count(array_filter($paiements, fn($p) => $p['statut'] == 1)) ?> paiement(s)</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-3"><span class="text-xs font-medium text-purple-600 dark:text-purple-400">À payer</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-exclamation-circle text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= count($commandes_avec_reste) ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Commandes en attente</p>
                </div>
            </div>

            <!-- Filtres -->
            <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.15s">
                <form method="GET" action="" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date début</label><input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" class="w-full input-glass px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date fin</label><input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" class="w-full input-glass px-3 py-2.5 text-sm"></div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Commande</label><select name="commande_id" class="w-full input-glass px-3 py-2.5 text-sm">
                                <option value="">Toutes</option><?php foreach ($commandes_avec_reste as $c): ?><option value="<?= $c['id'] ?>" <?= $commande_id_filter == $c['id'] ? 'selected' : '' ?>>#<?= $c['id'] ?> - <?= htmlspecialchars($c['client_nom']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Statut</label><select name="statut" class="w-full input-glass px-3 py-2.5 text-sm">
                                <option value="" <?= $statut_filter === '' ? 'selected' : '' ?>>Actifs</option>
                                <option value="0" <?= $statut_filter === '0' ? 'selected' : '' ?>>Actifs</option>
                                <option value="1" <?= $statut_filter === '1' ? 'selected' : '' ?>>Supprimés</option>
                                <option value="all" <?= $statut_filter === 'all' ? 'selected' : '' ?>>Tous</option>
                            </select></div>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                        <div class="flex gap-2"><button type="button" onclick="setCurrentMonth()" class="px-3 py-1.5 rounded-xl glass text-xs text-[var(--text-secondary)] hover:bg-white/20 transition-all">Ce mois</button><button type="button" onclick="setPrevMonth()" class="px-3 py-1.5 rounded-xl glass text-xs text-[var(--text-secondary)] hover:bg-white/20 transition-all">Mois précédent</button><button type="button" onclick="setAllDates()" class="px-3 py-1.5 rounded-xl glass text-xs text-[var(--text-secondary)] hover:bg-white/20 transition-all">Toutes</button></div>
                        <div class="flex gap-2"><a href="paiements.php" class="px-4 py-2 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Réinitialiser</a><button type="submit" class="btn-glass px-4 py-2 rounded-xl text-sm">Appliquer</button></div>
                    </div>
                </form>
            </div>

            <!-- Tableau -->
            <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.25s">
                <div class="px-5 py-4 border-b border-[var(--divider)] flex items-center justify-between">
                    <h3 class="text-base font-bold text-[var(--text-primary)]">Liste des paiements</h3><span class="text-xs text-[var(--text-muted)]"><?= count($paiements) ?> paiement(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px]">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">ID</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Commande</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Client</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Montant</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Total Cmd</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Déjà Payé</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Reste</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Statut</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]">
                            <?php if (!empty($paiements)): ?>
                                <?php foreach ($paiements as $p): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $p['paiement_id'] ?></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= date('d/m/Y', strtotime($p['date'])) ?></td>
                                        <td class="px-5 py-3.5"><span class="text-sm font-medium text-[var(--text-primary)]">#<?= $p['commande_id'] ?></span><span class="text-xs text-[var(--text-muted)] block"><?= htmlspecialchars($p['numero_facture']) ?></span></td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($p['client_nom'] ?? '-') ?></td>
                                        <td class="px-5 py-3.5 text-sm font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($p['montant'], 2) ?> $</td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= number_format($p['total_commande'], 2) ?> $</td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= number_format($p['total_paye'], 2) ?> $</td>
                                        <td class="px-5 py-3.5 text-sm font-bold <?= $p['reste_a_payer'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' ?>"><?= number_format($p['reste_a_payer'], 2) ?> $</td>
                                        <td class="px-5 py-3.5 text-center">
                                            <?php if ($p['statut'] == 1): ?><span class="px-2.5 py-1 rounded-full text-xs font-medium badge-danger">Supprimé</span>
                                            <?php elseif ($p['statut_paiement'] == 'payee'): ?><span class="px-2.5 py-1 rounded-full text-xs font-medium badge-success">Payée</span>
                                            <?php elseif ($p['statut_paiement'] == 'partiel'): ?><span class="px-2.5 py-1 rounded-full text-xs font-medium badge-warning">Partiel</span>
                                            <?php else: ?><span class="px-2.5 py-1 rounded-full text-xs font-medium badge-danger">Impayée</span><?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <?php if ($p['statut'] == 0): ?>
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <a href="recu.php?id=<?= $p['paiement_id'] ?>" target="_blank" class="p-2 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 hover:bg-orange-200 dark:hover:bg-orange-900/50 transition-colors" title="Reçu"><i class="fas fa-print text-xs"></i></a>
                                                    <button onclick="openModifierModal(<?= $p['paiement_id'] ?>,'<?= $p['date'] ?>',<?= $p['montant'] ?>,<?= $p['commande_id'] ?>)" class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors" title="Modifier"><i class="fas fa-edit text-xs"></i></button>
                                                    <form method="POST" action="" onsubmit="return confirm('Supprimer ce paiement ?')" class="inline"><input type="hidden" name="paiement_id" value="<?= $p['paiement_id'] ?>"><button type="submit" name="supprimer_paiement" class="p-2 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Supprimer"><i class="fas fa-trash text-xs"></i></button></form>
                                                </div>
                                            <?php else: ?><span class="text-xs text-[var(--text-muted)]">-</span><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="bg-[var(--divider)]">
                                    <td colspan="4" class="px-5 py-3 text-sm font-bold text-[var(--text-primary)] text-right">Totaux :</td>
                                    <td class="px-5 py-3 text-sm font-bold text-[var(--text-primary)]"><?= number_format($total_paiements, 2) ?> $</td>
                                    <td colspan="5"></td>
                                </tr>
                            <?php else: ?><tr>
                                    <td colspan="10" class="px-5 py-12 text-center"><i class="fas fa-money-bill-wave text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                                        <p class="text-[var(--text-secondary)] font-medium">Aucun paiement trouvé</p>
                                    </td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Infos statuts -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 animate-fade-in-up" style="animation-delay:0.3s">
                <div class="premium-card p-5">
                    <h4 class="font-bold text-sm text-[var(--text-primary)] mb-3"><i class="fas fa-info-circle text-blue-500 mr-2"></i>Statuts des paiements</h4>
                    <div class="space-y-2 text-xs text-[var(--text-secondary)]">
                        <p><span class="px-2 py-0.5 rounded-full badge-success text-[11px]">Actif</span> Paiement comptabilisé</p>
                        <p><span class="px-2 py-0.5 rounded-full badge-danger text-[11px]">Supprimé</span> Paiement archivé</p>
                    </div>
                </div>
                <div class="premium-card p-5">
                    <h4 class="font-bold text-sm text-[var(--text-primary)] mb-3"><i class="fas fa-money-check-alt text-emerald-500 mr-2"></i>Statuts commande</h4>
                    <div class="space-y-2 text-xs text-[var(--text-secondary)]">
                        <p><span class="px-2 py-0.5 rounded-full badge-success text-[11px]">Payée</span> Entièrement payée</p>
                        <p><span class="px-2 py-0.5 rounded-full badge-warning text-[11px]">Partiel</span> Acompte versé</p>
                        <p><span class="px-2 py-0.5 rounded-full badge-danger text-[11px]">Impayée</span> Aucun paiement</p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL AJOUT -->
    <div id="ajoutModalOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="ajoutModalContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-plus-circle mr-2 text-purple-500"></i>Nouveau paiement</h3><button onclick="closeAjoutModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="" class="space-y-4">
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date</label>
                <div class="p-3 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)] text-sm text-[var(--text-primary)]"><?= date('d/m/Y') ?> (automatique)</div>
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Commande *</label>
                <select name="commande_id" id="ajoutCmd" required class="w-full input-glass px-3 py-2.5 text-sm" onchange="updateInfo()">
                    <option value="">Sélectionnez...</option>
                    <?php foreach ($commandes_avec_reste as $c): ?>
                        <option value="<?= $c['id'] ?>" data-total="<?= $c['total_commande'] ?>" data-paye="<?= $c['total_paye'] ?>" data-reste="<?= $c['total_commande'] - $c['total_paye'] ?>">#<?= $c['id'] ?> - <?= htmlspecialchars($c['client_nom']) ?> (Reste: <?= number_format($c['total_commande'] - $c['total_paye'], 2) ?> $)</option>
                    <?php endforeach; ?>
                </select>
                <div id="infoCmd" class="hidden mt-2 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 text-xs space-y-1">
                    <p><strong>Total :</strong> <span id="iTotal">0</span> $</p>
                    <p><strong>Déjà payé :</strong> <span id="iPaye" class="text-emerald-600">0</span> $</p>
                    <p><strong>Reste :</strong> <span id="iReste" class="text-red-600">0</span> $</p>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Montant ($) *</label>
                <input type="number" name="montant" id="ajoutMontant" step="0.01" min="0.01" required class="w-full input-glass px-4 py-2.5 text-sm" placeholder="0.00">
                <p id="valMsg" class="text-xs mt-1 hidden"></p>
            </div>
            <div class="p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800/30">
                <p class="text-xs text-blue-700 dark:text-blue-300"><i class="fas fa-check-circle mr-1"></i>Le paiement sera enregistré immédiatement.</p>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeAjoutModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" name="ajouter_paiement" class="btn-purple px-5 py-2.5 rounded-xl text-sm">Ajouter le paiement</button>
            </div>
        </form>
    </div>

    <!-- MODAL MODIFIER -->
    <div id="modifierModalOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="modifierModalContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-edit mr-2 text-blue-500"></i>Modifier le paiement</h3><button onclick="closeModifierModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="paiement_id" id="modId">
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date</label>
                <div class="p-3 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)] text-sm" id="modDate"></div>
            </div>
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Commande</label>
                <div class="p-3 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)] text-sm" id="modCmd"></div>
            </div>
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Montant ($) *</label><input type="number" name="montant" id="modMontant" step="0.01" min="0.01" required class="w-full input-glass px-4 py-2.5 text-sm"></div>
            <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeModifierModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button><button type="submit" name="modifier_paiement" class="btn-glass px-5 py-2.5 rounded-xl text-sm">Enregistrer</button></div>
        </form>
    </div>

    <script>
        // Theme
        const themeToggle = document.getElementById('theme-toggle'),
            html = document.documentElement;
        const st = localStorage.getItem('theme');
        if (st === 'dark' || (!st && window.matchMedia('(prefers-color-scheme:dark)').matches)) html.classList.add('dark');
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

        function openAjoutModal() {
            openModal('ajoutModalOverlay', 'ajoutModalContent');
            resetAjoutForm()
        }

        function closeAjoutModal() {
            closeModal('ajoutModalOverlay', 'ajoutModalContent')
        }

        function resetAjoutForm() {
            document.getElementById('ajoutCmd').selectedIndex = 0;
            document.getElementById('ajoutMontant').value = '';
            document.getElementById('infoCmd').classList.add('hidden');
            document.getElementById('valMsg').classList.add('hidden')
        }

        function openModifierModal(id, date, montant, cmdId) {
            document.getElementById('modId').value = id;
            document.getElementById('modMontant').value = montant;
            document.getElementById('modDate').textContent = new Date(date).toLocaleDateString('fr-FR') + ' (originale)';
            document.getElementById('modCmd').textContent = 'Commande #' + cmdId;
            openModal('modifierModalOverlay', 'modifierModalContent');
            setTimeout(() => {
                document.getElementById('modMontant').focus();
                document.getElementById('modMontant').select()
            }, 100)
        }

        function closeModifierModal() {
            closeModal('modifierModalOverlay', 'modifierModalContent')
        }
        ['ajoutModalOverlay', 'modifierModalOverlay'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function(e) {
                if (e.target === this) closeModal(id, id.replace('Overlay', 'Content'))
            })
        });

        // Update info commande
        function updateInfo() {
            const sel = document.getElementById('ajoutCmd'),
                opt = sel.options[sel.selectedIndex],
                box = document.getElementById('infoCmd');
            if (opt && opt.value) {
                document.getElementById('iTotal').textContent = parseFloat(opt.dataset.total).toFixed(2);
                document.getElementById('iPaye').textContent = parseFloat(opt.dataset.paye).toFixed(2);
                document.getElementById('iReste').textContent = parseFloat(opt.dataset.reste).toFixed(2);
                box.classList.remove('hidden');
                document.getElementById('ajoutMontant').value = parseFloat(opt.dataset.reste).toFixed(2);
                document.getElementById('ajoutMontant').max = opt.dataset.reste
            } else {
                box.classList.add('hidden')
            }
            validateAjout()
        }

        function validateAjout() {
            const sel = document.getElementById('ajoutCmd'),
                opt = sel?.options[sel?.selectedIndex],
                msg = document.getElementById('valMsg');
            if (!opt || !opt.value) {
                msg.classList.add('hidden');
                return
            }
            const reste = parseFloat(opt.dataset.reste),
                v = parseFloat(document.getElementById('ajoutMontant').value) || 0;
            msg.classList.remove('hidden');
            if (v > reste) {
                msg.className = 'text-xs mt-1 text-red-500';
                msg.textContent = `Dépasse le reste (${reste.toFixed(2)} $)`
            } else if (v <= 0) {
                msg.className = 'text-xs mt-1 text-red-500';
                msg.textContent = 'Doit être > 0'
            } else {
                msg.className = 'text-xs mt-1 text-emerald-500';
                msg.textContent = `Valide. Reste après: ${(reste-v).toFixed(2)} $`
            }
        }
        document.getElementById('ajoutMontant')?.addEventListener('input', validateAjout);

        // Date shortcuts
        function setCurrentMonth() {
            const d = new Date();
            document.querySelector('input[name="date_debut"]').value = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-01`;
            document.querySelector('input[name="date_fin"]').value = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${new Date(d.getFullYear(),d.getMonth()+1,0).getDate()}`
        }

        function setPrevMonth() {
            const d = new Date();
            document.querySelector('input[name="date_debut"]').value = `${d.getFullYear()}-${String(d.getMonth()).padStart(2,'0')}-01`;
            document.querySelector('input[name="date_fin"]').value = `${d.getFullYear()}-${String(d.getMonth()).padStart(2,'0')}-${new Date(d.getFullYear(),d.getMonth(),0).getDate()}`
        }

        function setAllDates() {
            document.querySelector('input[name="date_debut"]').value = '';
            document.querySelector('input[name="date_fin"]').value = ''
        }

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeAjoutModal();
                closeModifierModal();
                if (sidebar.classList.contains('open')) toggleSidebar()
            }
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>