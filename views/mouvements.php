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
$total_mouvements = 0;
$mouvements = [];
$boutique_info = null;
$solde_caisse = 0;

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer les informations de la boutique
try {
    $queryBoutique = $pdo->prepare("SELECT id, nom, email, date_creation, actif FROM boutiques WHERE id = ? AND statut = 0");
    $queryBoutique->execute([$boutique_id]);
    $boutique_info = $queryBoutique->fetch(PDO::FETCH_ASSOC);
    
    if (!$boutique_info) {
        $_SESSION['flash_message'] = ['text' => "Boutique introuvable ou supprimée", 'type' => "error"];
        header('Location: ../login.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = ['text' => "Erreur : " . $e->getMessage(), 'type' => "error"];
    header('Location: ../login.php');
    exit;
}

// --- TRAITEMENT DU FORMULAIRE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        if ($_POST['action'] === 'ajouter_mouvement') {
            try {
                $type_mouvement = $_POST['type_mouvement'] ?? '';
                $montant = (float)($_POST['montant'] ?? 0);
                $motif = $_POST['motif'] ?? '';
                
                if (!in_array($type_mouvement, ['entrée', 'sortie'])) throw new Exception("Type de mouvement invalide");
                if ($montant <= 0) throw new Exception("Le montant doit être supérieur à 0");
                if (empty($motif)) throw new Exception("Le motif est obligatoire");
                
                $query = $pdo->prepare("INSERT INTO mouvement_caisse (id_boutique, type_mouvement, montant, motif, date_mouvement, statut) VALUES (?, ?, ?, ?, NOW(), 0)");
                $query->execute([$boutique_id, $type_mouvement, $montant, $motif]);
                
                $_SESSION['flash_message'] = ['text' => "Mouvement enregistré avec succès", 'type' => "success"];
                header('Location: mouvements.php');
                exit;
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = 'error';
            }
        }
        
        elseif ($_POST['action'] === 'modifier_mouvement') {
            try {
                $mouvement_id = (int)($_POST['mouvement_id'] ?? 0);
                $type_mouvement = $_POST['type_mouvement'] ?? '';
                $montant = (float)($_POST['montant'] ?? 0);
                $motif = $_POST['motif'] ?? '';
                
                if ($mouvement_id <= 0) throw new Exception("ID invalide");
                if (!in_array($type_mouvement, ['entrée', 'sortie'])) throw new Exception("Type invalide");
                if ($montant <= 0) throw new Exception("Montant invalide");
                if (empty($motif)) throw new Exception("Motif obligatoire");
                
                $checkQuery = $pdo->prepare("SELECT id FROM mouvement_caisse WHERE id = ? AND id_boutique = ? AND statut = 0");
                $checkQuery->execute([$mouvement_id, $boutique_id]);
                if (!$checkQuery->fetch()) throw new Exception("Mouvement introuvable");
                
                $query = $pdo->prepare("UPDATE mouvement_caisse SET type_mouvement = ?, montant = ?, motif = ? WHERE id = ? AND id_boutique = ? AND statut = 0");
                $query->execute([$type_mouvement, $montant, $motif, $mouvement_id, $boutique_id]);
                
                $_SESSION['flash_message'] = ['text' => "Mouvement modifié avec succès", 'type' => "success"];
                header('Location: mouvements.php');
                exit;
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = 'error';
            }
        }
        
        elseif ($_POST['action'] === 'supprimer_mouvement') {
            try {
                $mouvement_id = (int)($_POST['mouvement_id'] ?? 0);
                if ($mouvement_id <= 0) throw new Exception("ID invalide");
                
                $checkQuery = $pdo->prepare("SELECT id FROM mouvement_caisse WHERE id = ? AND id_boutique = ? AND statut = 0");
                $checkQuery->execute([$mouvement_id, $boutique_id]);
                if (!$checkQuery->fetch()) throw new Exception("Mouvement introuvable");
                
                $query = $pdo->prepare("UPDATE mouvement_caisse SET statut = 1 WHERE id = ? AND id_boutique = ? AND statut = 0");
                $query->execute([$mouvement_id, $boutique_id]);
                
                $_SESSION['flash_message'] = ['text' => "Mouvement supprimé avec succès", 'type' => "success"];
                header('Location: mouvements.php');
                exit;
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// --- RÉCUPÉRATION DES DONNÉES ---
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$type_filtre = $_GET['type'] ?? 'tous';
$search_term = $_GET['search'] ?? '';

if (!empty($date_debut) && !empty($date_fin)) {
    if (strtotime($date_debut) > strtotime($date_fin)) {
        $temp = $date_debut; $date_debut = $date_fin; $date_fin = $temp;
    }
}

$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$conditions = ["mc.id_boutique = ?", "mc.statut = 0"];
$params = [$boutique_id];

if (!empty($date_debut) && !empty($date_fin)) {
    $conditions[] = "DATE(mc.date_mouvement) BETWEEN ? AND ?";
    $params[] = $date_debut; $params[] = $date_fin;
}
if ($type_filtre !== 'tous') {
    $conditions[] = "mc.type_mouvement = ?";
    $params[] = $type_filtre;
}
if (!empty($search_term)) {
    $conditions[] = "(mc.motif LIKE ?)";
    $params[] = "%$search_term%";
}

try {
    $countQuery = $pdo->prepare("SELECT COUNT(*) FROM mouvement_caisse mc WHERE " . implode(" AND ", $conditions));
    $countQuery->execute($params);
    $total_mouvements = $countQuery->fetchColumn();
    $totalPages = ceil($total_mouvements / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;
    
    $sql = "SELECT mc.* FROM mouvement_caisse mc WHERE " . implode(" AND ", $conditions) . " ORDER BY mc.date_mouvement DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $query = $pdo->prepare($sql);
    $query->execute($params);
    $mouvements = $query->fetchAll(PDO::FETCH_ASSOC);
    
    $querySolde = $pdo->prepare("SELECT SUM(CASE WHEN mc.type_mouvement = 'entrée' THEN mc.montant ELSE 0 END) AS total_entrees, SUM(CASE WHEN mc.type_mouvement = 'sortie' THEN mc.montant ELSE 0 END) AS total_sorties FROM mouvement_caisse mc WHERE " . implode(" AND ", $conditions));
    $querySolde->execute($params);
    $solde_data = $querySolde->fetch(PDO::FETCH_ASSOC);
    $total_entrees = $solde_data['total_entrees'] ?? 0;
    $total_sorties = $solde_data['total_sorties'] ?? 0;
    $solde_caisse = $total_entrees - $total_sorties;
    
    $queryStats = $pdo->prepare("SELECT mc.type_mouvement, COUNT(*) as nombre_mouvements, SUM(mc.montant) as montant_total FROM mouvement_caisse mc WHERE " . implode(" AND ", $conditions) . " GROUP BY mc.type_mouvement ORDER BY mc.type_mouvement");
    $queryStats->execute($params);
    $stats = $queryStats->fetchAll(PDO::FETCH_ASSOC);
    $stats_entrees = []; $stats_sorties = [];
    foreach ($stats as $stat) {
        if ($stat['type_mouvement'] == 'entrée') $stats_entrees = $stat;
        else $stats_sorties = $stat;
    }
    
    $queryDernierMouvement = $pdo->prepare("SELECT mc.* FROM mouvement_caisse mc WHERE mc.id_boutique = ? AND mc.statut = 0 ORDER BY mc.date_mouvement DESC LIMIT 1");
    $queryDernierMouvement->execute([$boutique_id]);
    $dernier_mouvement = $queryDernierMouvement->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    $mouvements = []; $total_mouvements = 0; $totalPages = 1;
    $solde_caisse = 0; $total_entrees = 0; $total_sorties = 0;
    $stats_entrees = []; $stats_sorties = []; $dernier_mouvement = null;
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Mouvements de Caisse - <?= htmlspecialchars($boutique_info['nom']) ?> - NGS</title>
  
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
      --success-color: #10b981;
      --danger-color: #ef4444;
      --warning-color: #f59e0b;
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

    .dark body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%); }

    .sidebar { background: var(--sidebar-bg); }
    
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
      box-shadow: 0 4px 24px rgba(0,0,0,0.04);
      transition: all 0.3s ease;
    }

    .premium-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
    .dark .premium-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.3); }

    .input-glass {
      background: var(--input-bg);
      border: 2px solid var(--input-border);
      color: var(--text-primary);
      border-radius: 0.75rem;
      transition: all 0.3s ease;
    }

    .input-glass:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59,130,246,0.1);
      outline: none;
    }

    .btn-glass {
      background: var(--accent-gradient);
      color: white;
      border: 1px solid rgba(255,255,255,0.2);
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(30,58,138,0.2);
    }

    .btn-glass:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(30,58,138,0.35);
    }

    .nav-link {
      color: rgba(255,255,255,0.7);
      transition: all 0.3s ease;
      border-radius: 0.75rem;
    }

    .nav-link:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 1.25rem; }
    .nav-link.active { background: rgba(255,255,255,0.15); color: white; border-left: 3px solid #60a5fa; }

    .stat-card {
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }

    .badge-entree { background: #d1fae5; color: #065f46; }
    .badge-sortie { background: #fee2e2; color: #991b1b; }
    .dark .badge-entree { background: rgba(16,185,129,0.2); color: #6ee7b7; }
    .dark .badge-sortie { background: rgba(239,68,68,0.2); color: #fca5a5; }

    .montant-entree { color: #059669; }
    .montant-sortie { color: #dc2626; }
    .dark .montant-entree { color: #34d399; }
    .dark .montant-sortie { color: #f87171; }

    .theme-toggle {
      width: 44px; height: 24px;
      background: #cbd5e1; border-radius: 12px;
      position: relative; cursor: pointer;
      transition: background 0.3s ease;
    }
    .dark .theme-toggle { background: #334155; }
    .theme-toggle::after {
      content: ''; position: absolute;
      top: 2px; left: 2px;
      width: 20px; height: 20px;
      background: white; border-radius: 50%;
      transition: transform 0.3s ease;
    }
    .dark .theme-toggle::after { transform: translateX(20px); background: #fbbf24; }

    .modal-overlay {
      background: rgba(0,0,0,0.5);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
    }

    .modal-container {
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--glass-border);
      border-radius: 1.5rem;
      box-shadow: 0 25px 60px rgba(0,0,0,0.2);
    }

    *:focus-visible {
      outline: 2px solid #60a5fa;
      outline-offset: 2px;
      border-radius: 6px;
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(16px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
    }
  </style>
</head>
<body class="h-screen flex overflow-hidden">

  <!-- ============================================ -->
  <!-- OVERLAY MOBILE                                -->
  <!-- ============================================ -->
  <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

  <!-- ============================================ -->
  <!-- SIDEBAR                                       -->
  <!-- ============================================ -->
  <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
    
    <!-- Header -->
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

    <!-- Profile -->
    <div class="p-5 border-b border-white/10 flex-shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-blue-500/20 border border-blue-400/30 flex items-center justify-center">
          <i class="fas fa-store text-blue-400"></i>
        </div>
        <div class="min-w-0">
          <p class="font-semibold text-sm truncate"><?= htmlspecialchars($boutique_info['nom']) ?></p>
          <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($boutique_info['email'] ?? '') ?></p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
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
      <a href="mouvements.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm">
        <i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements Caisse
      </a>
      <a href="transferts-boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
        <i class="fas fa-truck-loading w-4 text-center"></i>Transferts
      </a>
      <a href="rapports_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm">
        <i class="fas fa-chart-bar w-4 text-center"></i>Rapports
      </a>
      <a href="numeros_rideaux.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tags w-4 text-center"></i>N° Rideaux</a>
      <a href="realisations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations</a>
    </nav>

    <!-- Footer -->
    <div class="p-3 border-t border-white/10 flex-shrink-0">
      <!-- Theme toggle -->
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
    
    <!-- Header -->
    <header class="sticky top-0 z-30 glass border-b border-white/10">
      <div class="flex items-center justify-between px-4 md:px-6 py-4">
        <div class="flex items-center gap-3">
          <button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors text-[var(--text-primary)]">
            <i class="fas fa-bars text-lg"></i>
          </button>
          <div>
            <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Mouvements de Caisse</h1>
            <p class="text-xs text-[var(--text-muted)]"><?= htmlspecialchars($boutique_info['nom']) ?> • NGS</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span class="hidden sm:inline-flex items-center gap-1.5 text-sm text-[var(--text-muted)] glass px-3 py-1.5 rounded-full">
            <i class="fas fa-calculator text-blue-500"></i>
            <strong class="<?= $solde_caisse >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' ?>">
              <?= number_format($solde_caisse, 2) ?> $
            </strong>
          </span>
          <button onclick="openAjoutModal()" class="btn-glass px-4 py-2 rounded-xl text-sm flex items-center gap-2">
            <i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouveau</span>
          </button>
        </div>
      </div>
    </header>

    <div class="p-4 md:p-6 space-y-6">
      
      <!-- Message notification -->
      <?php if ($message): ?>
        <div class="animate-fade-in-up">
          <div class="glass rounded-2xl p-4 border-l-4 <?= $message_type === 'success' ? 'border-green-500' : 'border-red-500' ?>">
            <div class="flex items-center gap-3">
              <i class="fas fa-<?= $message_type === 'success' ? 'check-circle text-green-500' : 'exclamation-circle text-red-500' ?> text-xl"></i>
              <span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($message) ?></span>
              <button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Stats cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay: 0s">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Entrées</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
              <i class="fas fa-sign-in-alt text-emerald-600 dark:text-emerald-400 text-sm"></i>
            </div>
          </div>
          <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_entrees, 2) ?> $</p>
          <p class="text-xs text-[var(--text-muted)] mt-1"><?= $stats_entrees['nombre_mouvements'] ?? 0 ?> mouvement(s)</p>
        </div>

        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-red-500" style="animation-delay: 0.1s">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-red-600 dark:text-red-400">Sorties</span>
            <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
              <i class="fas fa-sign-out-alt text-red-600 dark:text-red-400 text-sm"></i>
            </div>
          </div>
          <p class="text-2xl font-bold text-[var(--text-primary)]"><?= number_format($total_sorties, 2) ?> $</p>
          <p class="text-xs text-[var(--text-muted)] mt-1"><?= $stats_sorties['nombre_mouvements'] ?? 0 ?> mouvement(s)</p>
        </div>

        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 <?= $solde_caisse >= 0 ? 'border-blue-500' : 'border-orange-500' ?>" style="animation-delay: 0.2s">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Solde</span>
            <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <i class="fas fa-balance-scale text-blue-600 dark:text-blue-400 text-sm"></i>
            </div>
          </div>
          <p class="text-2xl font-bold <?= $solde_caisse > 0 ? 'montant-entree' : ($solde_caisse < 0 ? 'montant-sortie' : 'text-[var(--text-primary)]') ?>">
            <?= number_format($solde_caisse, 2) ?> $
          </p>
          <p class="text-xs text-[var(--text-muted)] mt-1">Période sélectionnée</p>
        </div>

        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay: 0.3s">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-purple-600 dark:text-purple-400">Total</span>
            <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
              <i class="fas fa-list text-purple-600 dark:text-purple-400 text-sm"></i>
            </div>
          </div>
          <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_mouvements ?></p>
          <p class="text-xs text-[var(--text-muted)] mt-1">Mouvements</p>
        </div>
      </div>

      <!-- Filtres -->
      <div class="premium-card p-5 animate-fade-in-up" style="animation-delay: 0.15s">
        <form method="GET" action="mouvements.php" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date début</label>
              <input type="date" name="date_debut" value="<?= htmlspecialchars($date_debut) ?>" class="w-full input-glass px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date fin</label>
              <input type="date" name="date_fin" value="<?= htmlspecialchars($date_fin) ?>" class="w-full input-glass px-3 py-2.5 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Type</label>
              <select name="type" class="w-full input-glass px-3 py-2.5 text-sm">
                <option value="tous" <?= $type_filtre === 'tous' ? 'selected' : '' ?>>Tous</option>
                <option value="entrée" <?= $type_filtre === 'entrée' ? 'selected' : '' ?>>Entrées</option>
                <option value="sortie" <?= $type_filtre === 'sortie' ? 'selected' : '' ?>>Sorties</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Recherche</label>
              <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Motif..." class="w-full input-glass px-3 py-2.5 text-sm">
            </div>
          </div>
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
            <span class="text-xs text-[var(--text-muted)]"><?= $total_mouvements ?> résultat(s)</span>
            <div class="flex gap-2">
              <button type="button" onclick="window.location.href='mouvements.php'" class="px-4 py-2 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Réinitialiser</button>
              <button type="submit" class="btn-glass px-4 py-2 rounded-xl text-sm">Appliquer</button>
            </div>
          </div>
        </form>
      </div>

      <!-- Tableau -->
      <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay: 0.2s">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[700px]">
            <thead>
              <tr class="border-b border-[var(--divider)] text-left">
                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">ID</th>
                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th>
                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Type</th>
                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Montant</th>
                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Motif</th>
                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--divider)]">
              <?php if (!empty($mouvements)): ?>
                <?php foreach ($mouvements as $mouvement): ?>
                  <tr class="hover:bg-white/5 transition-colors">
                    <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $mouvement['id'] ?></td>
                    <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]">
                      <?= date('d/m/Y', strtotime($mouvement['date_mouvement'])) ?>
                      <span class="text-xs text-[var(--text-muted)] block"><?= date('H:i', strtotime($mouvement['date_mouvement'])) ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $mouvement['type_mouvement'] == 'entrée' ? 'badge-entree' : 'badge-sortie' ?>">
                        <i class="fas fa-<?= $mouvement['type_mouvement'] == 'entrée' ? 'sign-in-alt' : 'sign-out-alt' ?> text-[10px]"></i>
                        <?= ucfirst($mouvement['type_mouvement']) ?>
                      </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm font-bold <?= $mouvement['type_mouvement'] == 'entrée' ? 'montant-entree' : 'montant-sortie' ?>">
                      <?= $mouvement['type_mouvement'] == 'entrée' ? '+' : '-' ?><?= number_format($mouvement['montant'], 2) ?> $
                    </td>
                    <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)] max-w-[200px] truncate"><?= htmlspecialchars($mouvement['motif']) ?></td>
                    <td class="px-5 py-3.5">
                      <div class="flex items-center justify-center gap-1.5">
                        <button onclick="editerMouvement(<?= $mouvement['id'] ?>, '<?= $mouvement['type_mouvement'] ?>', <?= $mouvement['montant'] ?>, '<?= htmlspecialchars(addslashes($mouvement['motif'])) ?>')" 
                                class="p-2 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 hover:bg-yellow-200 dark:hover:bg-yellow-900/50 transition-colors" title="Modifier">
                          <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button onclick="confirmerSuppression(<?= $mouvement['id'] ?>)" 
                                class="p-2 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Supprimer">
                          <i class="fas fa-trash text-xs"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="px-5 py-12 text-center">
                    <i class="fas fa-exchange-alt text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                    <p class="text-[var(--text-secondary)] font-medium">Aucun mouvement trouvé</p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Essayez d'ajuster les filtres</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="px-5 py-4 border-t border-[var(--divider)] flex items-center justify-between">
            <span class="text-xs text-[var(--text-muted)] hidden sm:block">
              Page <?= $page ?> sur <?= $totalPages ?> • <?= $total_mouvements ?> résultats
            </span>
            <div class="flex items-center gap-1.5 mx-auto sm:mx-0">
              <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>"
                 class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>">
                <i class="fas fa-chevron-left text-xs"></i>
              </a>
              <?php
              $startPage = max(1, $page - 1);
              $endPage = min($totalPages, $page + 1);
              for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i == $page ? 'btn-glass shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>">
                  <?= $i ?>
                </a>
              <?php endfor; ?>
              <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])) ?>"
                 class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>">
                <i class="fas fa-chevron-right text-xs"></i>
              </a>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>

  <!-- ============================================ -->
  <!-- MODAL AJOUT                                  -->
  <!-- ============================================ -->
  <div id="modalAjoutOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="modalAjoutContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-plus-circle mr-2 text-blue-500"></i>Nouveau mouvement</h3>
      <button onclick="closeAjoutModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="mouvements.php" class="space-y-4">
      <input type="hidden" name="action" value="ajouter_mouvement">
      <div>
        <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Type</label>
        <div class="grid grid-cols-2 gap-3">
          <label class="cursor-pointer">
            <input type="radio" name="type_mouvement" value="entrée" checked class="hidden peer" onchange="togglePrefix('ajout')">
            <div class="py-3 rounded-xl text-center text-sm font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 peer-checked:ring-2 peer-checked:ring-emerald-500 transition-all">
              <i class="fas fa-sign-in-alt mr-1"></i>Entrée
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="type_mouvement" value="sortie" class="hidden peer" onchange="togglePrefix('ajout')">
            <div class="py-3 rounded-xl text-center text-sm font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 peer-checked:ring-2 peer-checked:ring-red-500 transition-all">
              <i class="fas fa-sign-out-alt mr-1"></i>Sortie
            </div>
          </label>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Montant ($)</label>
        <div class="relative">
          <span id="prefixAjout" class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600 dark:text-emerald-400 font-bold text-sm">+</span>
          <input type="number" name="montant" step="0.01" min="0.01" required placeholder="0.00" class="w-full input-glass pl-8 pr-4 py-3 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Motif</label>
        <textarea name="motif" rows="2" required placeholder="Raison du mouvement..." class="w-full input-glass px-4 py-3 text-sm resize-none"></textarea>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeAjoutModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
        <button type="submit" class="btn-glass px-5 py-2.5 rounded-xl text-sm"><i class="fas fa-save mr-1.5"></i>Enregistrer</button>
      </div>
    </form>
  </div>

  <!-- ============================================ -->
  <!-- MODAL MODIFIER                                -->
  <!-- ============================================ -->
  <div id="modalModifierOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="modalModifierContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-edit mr-2 text-yellow-500"></i>Modifier</h3>
      <button onclick="closeModifierModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="mouvements.php" class="space-y-4">
      <input type="hidden" name="action" value="modifier_mouvement">
      <input type="hidden" name="mouvement_id" id="editId">
      <div>
        <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Type</label>
        <div class="grid grid-cols-2 gap-3">
          <label class="cursor-pointer">
            <input type="radio" name="type_mouvement" value="entrée" id="editEntree" class="hidden peer" onchange="togglePrefix('edit')">
            <div class="py-3 rounded-xl text-center text-sm font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 peer-checked:ring-2 peer-checked:ring-emerald-500 transition-all">
              <i class="fas fa-sign-in-alt mr-1"></i>Entrée
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="type_mouvement" value="sortie" id="editSortie" class="hidden peer" onchange="togglePrefix('edit')">
            <div class="py-3 rounded-xl text-center text-sm font-semibold bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 peer-checked:ring-2 peer-checked:ring-red-500 transition-all">
              <i class="fas fa-sign-out-alt mr-1"></i>Sortie
            </div>
          </label>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Montant ($)</label>
        <div class="relative">
          <span id="prefixEdit" class="absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600 dark:text-emerald-400 font-bold text-sm">+</span>
          <input type="number" name="montant" id="editMontant" step="0.01" min="0.01" required class="w-full input-glass pl-8 pr-4 py-3 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Motif</label>
        <textarea name="motif" id="editMotif" rows="2" required class="w-full input-glass px-4 py-3 text-sm resize-none"></textarea>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeModifierModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-yellow-500 to-orange-500 text-white text-sm font-semibold hover:opacity-90 transition-all"><i class="fas fa-save mr-1.5"></i>Mettre à jour</button>
      </div>
    </form>
  </div>

  <!-- ============================================ -->
  <!-- MODAL CONFIRMATION SUPPRESSION               -->
  <!-- ============================================ -->
  <div id="modalConfirmOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="modalConfirmContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
    <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
    <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Confirmer la suppression</h3>
    <p class="text-sm text-[var(--text-secondary)] mb-6">Cette action est irréversible.</p>
    <form method="POST" action="mouvements.php" class="flex justify-center gap-3">
      <input type="hidden" name="action" value="supprimer_mouvement">
      <input type="hidden" name="mouvement_id" id="deleteId">
      <button type="button" onclick="closeConfirmModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
      <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-semibold hover:opacity-90 transition-all"><i class="fas fa-trash mr-1.5"></i>Supprimer</button>
    </form>
  </div>

  <script>
    // Theme toggle
    const themeToggle = document.getElementById('theme-toggle');
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (savedTheme === 'dark' || (!savedTheme && systemDark)) html.classList.add('dark');
    themeToggle.addEventListener('click', () => {
      html.classList.toggle('dark');
      localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
    });

    // Sidebar mobile
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    function toggleSidebar() {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('hidden');
    }
    document.getElementById('mobileMenuBtn').addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);
    document.querySelectorAll('.sidebar a').forEach(link => link.addEventListener('click', () => {
      if (window.innerWidth < 768) { sidebar.classList.remove('open'); overlay.classList.add('hidden'); }
    }));

    // Modals
    function openModal(overlayId, contentId) {
      document.getElementById(overlayId).classList.remove('hidden');
      document.getElementById(contentId).classList.remove('hidden');
    }
    function closeModal(overlayId, contentId) {
      document.getElementById(overlayId).classList.add('hidden');
      document.getElementById(contentId).classList.add('hidden');
    }
    function openAjoutModal() { document.getElementById('formAjoutMouvement')?.reset(); togglePrefix('ajout'); openModal('modalAjoutOverlay', 'modalAjoutContent'); }
    function closeAjoutModal() { closeModal('modalAjoutOverlay', 'modalAjoutContent'); }
    function editerMouvement(id, type, montant, motif) {
      document.getElementById('editId').value = id;
      document.getElementById('editMontant').value = montant;
      document.getElementById('editMotif').value = motif;
      if (type === 'entrée') document.getElementById('editEntree').checked = true;
      else document.getElementById('editSortie').checked = true;
      togglePrefix('edit');
      openModal('modalModifierOverlay', 'modalModifierContent');
    }
    function closeModifierModal() { closeModal('modalModifierOverlay', 'modalModifierContent'); }
    function confirmerSuppression(id) {
      document.getElementById('deleteId').value = id;
      openModal('modalConfirmOverlay', 'modalConfirmContent');
    }
    function closeConfirmModal() { closeModal('modalConfirmOverlay', 'modalConfirmContent'); }

    // Close modals on overlay click
    ['modalAjoutOverlay','modalModifierOverlay','modalConfirmOverlay'].forEach(id => {
      document.getElementById(id)?.addEventListener('click', function(e) {
        if (e.target === this) { this.classList.add('hidden'); document.getElementById(this.id.replace('Overlay','Content')).classList.add('hidden'); }
      });
    });

    // Toggle prefix
    function togglePrefix(type) {
      const prefix = document.getElementById(type === 'ajout' ? 'prefixAjout' : 'prefixEdit');
      const entreeChecked = document.getElementById(type === 'ajout' ? 'type_mouvement' : 'editEntree')?.checked ?? document.querySelector(`input[name="type_mouvement"][value="entrée"]`)?.checked;
      if (entreeChecked) {
        prefix.textContent = '+';
        prefix.className = 'absolute left-3 top-1/2 -translate-y-1/2 text-emerald-600 dark:text-emerald-400 font-bold text-sm';
      } else {
        prefix.textContent = '-';
        prefix.className = 'absolute left-3 top-1/2 -translate-y-1/2 text-red-600 dark:text-red-400 font-bold text-sm';
      }
    }

    // Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeAjoutModal(); closeModifierModal(); closeConfirmModal();
        if (sidebar.classList.contains('open')) toggleSidebar();
      }
    });

    // Init
    togglePrefix('ajout');
  </script>

  <?php unset($_SESSION['msg']); ?>
</body>
</html>