<?php
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Dossier d'upload
$upload_dir = __DIR__ . '/../uploads/realisations/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function uploadImage($file, $prefix = 'real') {
    global $upload_dir;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) return ['error' => 'Format non autorisé. Formats acceptés : ' . implode(', ', $allowed)];
    if ($file['size'] > 10 * 1024 * 1024) return ['error' => 'Image trop volumineuse (max 10 MB)'];

    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => true, 'path' => 'uploads/realisations/' . $filename]; // chemin relatif racine
    }
    return ['error' => 'Erreur lors de l\'upload'];
}

// --- AJAX : récupérer une réalisation pour modification ---
if (isset($_GET['action']) && $_GET['action'] == 'get_realisation' && isset($_GET['id'])) {
    $real_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT r.*, b.nom as boutique_nom FROM realisations r JOIN boutiques b ON r.boutique_id=b.id WHERE r.id=? AND r.statut=0");
    $stmt->execute([$real_id]);
    $real = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($real) {
        $images = $pdo->query("SELECT * FROM realisation_images WHERE realisation_id=$real_id ORDER BY ordre ASC")->fetchAll();
        $real['images_supp'] = $images;
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $real]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non trouvée']);
    }
    exit;
}

// --- AJOUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_realisation'])) {
    try {
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categorie = $_POST['categorie'] ?? 'autre';
        $date_realisation = $_POST['date_realisation'] ?? date('Y-m-d');
        $client_nom = trim($_POST['client_nom'] ?? '');
        $client_ville = trim($_POST['client_ville'] ?? '');
        $prix_indicatif = (float)($_POST['prix_indicatif'] ?? 0);
        $bid = $is_pdg ? (int)($_POST['boutique_id'] ?? $boutique_id) : $boutique_id;

        if (empty($titre)) throw new Exception("Le titre est obligatoire");
        if (empty($_FILES['image_principale']) || $_FILES['image_principale']['error'] !== UPLOAD_ERR_OK) throw new Exception("L'image principale est obligatoire");

        $upload_main = uploadImage($_FILES['image_principale'], 'main');
        if (isset($upload_main['error'])) throw new Exception($upload_main['error']);
        $image_principale = $upload_main['path'];

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO realisations (boutique_id, titre, description, image_principale, categorie, date_realisation, client_nom, client_ville, prix_indicatif) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$bid, $titre, $description, $image_principale, $categorie, $date_realisation, $client_nom, $client_ville, $prix_indicatif]);
        $real_id = $pdo->lastInsertId();

        for ($i = 1; $i <= 2; $i++) {
            $f = "image_supp_" . $i;
            if (isset($_FILES[$f]) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
                $up = uploadImage($_FILES[$f], 'supp' . $i);
                if (isset($up['path'])) {
                    $pdo->prepare("INSERT INTO realisation_images (realisation_id, image_url, ordre) VALUES (?,?,?)")->execute([$real_id, $up['path'], $i]);
                }
            }
        }
        $pdo->commit();
        $_SESSION['flash_message'] = ['text' => "Réalisation ajoutée avec succès !", 'type' => "success"];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_message'] = ['text' => "Erreur : " . $e->getMessage(), 'type' => "error"];
    }
    header("Location: realisations.php");
    exit;
}

// --- MODIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_realisation'])) {
    try {
        $real_id = (int)$_POST['realisation_id'];
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categorie = $_POST['categorie'] ?? 'autre';
        $date_realisation = $_POST['date_realisation'] ?? date('Y-m-d');
        $client_nom = trim($_POST['client_nom'] ?? '');
        $client_ville = trim($_POST['client_ville'] ?? '');
        $prix_indicatif = (float)($_POST['prix_indicatif'] ?? 0);

        if (empty($titre)) throw new Exception("Le titre est obligatoire");

        if (!$is_pdg) {
            $check = $pdo->prepare("SELECT id, image_principale FROM realisations WHERE id=? AND boutique_id=?");
            $check->execute([$real_id, $boutique_id]);
            $existing = $check->fetch();
            if (!$existing) throw new Exception("Non autorisé");
        } else {
            $existing = $pdo->query("SELECT image_principale FROM realisations WHERE id=$real_id")->fetch();
        }
        $old_main = $existing['image_principale'] ?? '';

        $pdo->beginTransaction();

        $image_principale = $old_main;
        if (isset($_FILES['image_principale']) && $_FILES['image_principale']['error'] === UPLOAD_ERR_OK) {
            $upload_main = uploadImage($_FILES['image_principale'], 'main');
            if (isset($upload_main['error'])) throw new Exception($upload_main['error']);
            $image_principale = $upload_main['path'];
            $old_path = __DIR__ . '/../' . $old_main; // conversion en chemin physique
            if ($old_main && file_exists($old_path)) @unlink($old_path);
        }

        $pdo->prepare("UPDATE realisations SET titre=?, description=?, image_principale=?, categorie=?, date_realisation=?, client_nom=?, client_ville=?, prix_indicatif=? WHERE id=?")
            ->execute([$titre, $description, $image_principale, $categorie, $date_realisation, $client_nom, $client_ville, $prix_indicatif, $real_id]);

        $pdo->prepare("DELETE FROM realisation_images WHERE realisation_id=?")->execute([$real_id]);
        for ($i = 1; $i <= 2; $i++) {
            $f = "image_supp_" . $i;
            $keep = "keep_supp_" . $i;
            $old_url = $_POST["old_supp_{$i}_url"] ?? '';
            if (isset($_POST[$keep]) && $_POST[$keep] === '1' && $old_url) {
                $pdo->prepare("INSERT INTO realisation_images (realisation_id, image_url, ordre) VALUES (?,?,?)")->execute([$real_id, $old_url, $i]);
            } elseif (isset($_FILES[$f]) && $_FILES[$f]['error'] === UPLOAD_ERR_OK) {
                $up = uploadImage($_FILES[$f], 'supp' . $i);
                if (isset($up['path'])) {
                    $pdo->prepare("INSERT INTO realisation_images (realisation_id, image_url, ordre) VALUES (?,?,?)")->execute([$real_id, $up['path'], $i]);
                    if ($old_url && file_exists(__DIR__ . '/../' . $old_url)) @unlink(__DIR__ . '/../' . $old_url);
                }
            }
        }
        $pdo->commit();
        $_SESSION['flash_message'] = ['text' => "Réalisation modifiée !", 'type' => "success"];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_message'] = ['text' => "Erreur : " . $e->getMessage(), 'type' => "error"];
    }
    header("Location: realisations.php");
    exit;
}

// --- SUPPRESSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_realisation'])) {
    $real_id = (int)$_POST['realisation_id'];
    if (!$is_pdg) {
        $check = $pdo->prepare("SELECT id FROM realisations WHERE id=? AND boutique_id=?");
        $check->execute([$real_id, $boutique_id]);
        if (!$check->fetch()) { $_SESSION['flash_message'] = ['text' => "Non autorisé", 'type' => "error"]; header("Location: realisations.php"); exit; }
    }
    $pdo->prepare("UPDATE realisations SET statut=1 WHERE id=?")->execute([$real_id]);
    $_SESSION['flash_message'] = ['text' => "Réalisation supprimée !", 'type' => "success"];
    header("Location: realisations.php");
    exit;
}

// --- PUBLIER/DÉPUBLIER ---
if (isset($_GET['toggle_publie']) && isset($_GET['id'])) {
    $real_id = (int)$_GET['id'];
    if (!$is_pdg) {
        $check = $pdo->prepare("SELECT id, est_publie FROM realisations WHERE id=? AND boutique_id=?");
        $check->execute([$real_id, $boutique_id]);
        $r = $check->fetch();
        if (!$r) { header("Location: realisations.php"); exit; }
        $pdo->prepare("UPDATE realisations SET est_publie=? WHERE id=?")->execute([$r['est_publie'] ? 0 : 1, $real_id]);
    } else {
        $pdo->prepare("UPDATE realisations SET est_publie = NOT est_publie WHERE id=?")->execute([$real_id]);
    }
    $_SESSION['flash_message'] = ['text' => "Statut de publication modifié !", 'type' => "success"];
    header("Location: realisations.php");
    exit;
}

// --- PAGINATION ---
$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$where = $is_pdg ? "" : "AND r.boutique_id = $boutique_id";

$countQuery = $pdo->query("SELECT COUNT(*) FROM realisations r WHERE r.statut=0 $where");
$total_realisations = $countQuery->fetchColumn();
$totalPages = ceil($total_realisations / $limit);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

$realisations = $pdo->query("
    SELECT r.*, b.nom as boutique_nom,
           (SELECT COUNT(*) FROM realisation_likes rl WHERE rl.realisation_id=r.id) as likes,
           (SELECT COUNT(*) FROM realisation_images WHERE realisation_id=r.id) as img_count
    FROM realisations r 
    JOIN boutiques b ON r.boutique_id=b.id 
    WHERE r.statut=0 $where 
    ORDER BY r.date_creation DESC 
    LIMIT $limit OFFSET $offset
")->fetchAll();

$total_likes = $pdo->query("SELECT COUNT(*) FROM realisation_likes")->fetchColumn();
$total_demandes = $pdo->query("SELECT COUNT(*) FROM demandes_service WHERE statut!='annulee'")->fetchColumn();
$demandes = $pdo->query("SELECT d.*, r.titre as realisation_titre, b.nom as boutique_nom FROM demandes_service d JOIN realisations r ON d.realisation_id=r.id JOIN boutiques b ON d.boutique_id=b.id WHERE d.statut!='annulee' ORDER BY d.date_creation DESC")->fetchAll();
$boutiques = $pdo->query("SELECT id, nom FROM boutiques WHERE statut=0 AND actif=1 ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Réalisations & Demandes - NGS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>tailwind.config={darkMode:'class',theme:{extend:{fontFamily:{sans:['Inter','system-ui','-apple-system','sans-serif']}}}}</script>
  <style>
    :root {
      --sidebar-bg: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
      --glass-bg: rgba(255, 255, 255, 0.7); --glass-border: rgba(255, 255, 255, 0.3);
      --card-bg: rgba(255, 255, 255, 0.8); --text-primary: #1a1a2e; --text-secondary: #4a4a6a; --text-muted: #6b7280;
      --accent-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
      --input-bg: rgba(255, 255, 255, 0.9); --input-border: rgba(0, 0, 0, 0.1); --divider: rgba(0, 0, 0, 0.06);
    }
    .dark {
      --sidebar-bg: linear-gradient(180deg, #020617 0%, #0f172a 100%);
      --glass-bg: rgba(15, 23, 42, 0.75); --glass-border: rgba(255, 255, 255, 0.08);
      --card-bg: rgba(30, 41, 59, 0.7); --text-primary: #f1f5f9; --text-secondary: #cbd5e1; --text-muted: #94a3b8;
      --accent-gradient: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
      --input-bg: rgba(30, 41, 59, 0.8); --input-border: rgba(255, 255, 255, 0.1); --divider: rgba(255, 255, 255, 0.06);
    }
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 50%, #f5f3ff 100%); color: var(--text-primary); transition: background 0.4s ease, color 0.4s ease; }
    .dark body { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%); }
    .sidebar { background: var(--sidebar-bg); }
    .glass { background: var(--glass-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid var(--glass-border); transition: all 0.3s ease; }
    .premium-card { background: var(--card-bg); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid var(--glass-border); border-radius: 1.25rem; box-shadow: 0 4px 24px rgba(0,0,0,0.04); transition: all 0.3s ease; }
    .premium-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.08); } .dark .premium-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
    .input-glass { background: var(--input-bg); border: 2px solid var(--input-border); color: var(--text-primary); border-radius: 0.75rem; transition: all 0.3s ease; }
    .input-glass:focus { border-color: #ec4899; box-shadow: 0 0 0 4px rgba(236,72,153,0.1); outline: none; }
    .btn-glass { background: var(--accent-gradient); color: white; border: 1px solid rgba(255,255,255,0.2); font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(30,58,138,0.2); }
    .btn-glass:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(30,58,138,0.35); }
    .btn-pink { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); color: white; font-weight: 600; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(236,72,153,0.25); }
    .btn-pink:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(236,72,153,0.4); }
    .nav-link { color: rgba(255,255,255,0.7); transition: all 0.3s ease; border-radius: 0.75rem; }
    .nav-link:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 1.25rem; }
    .nav-link.active { background: rgba(255,255,255,0.15); color: white; border-left: 3px solid #f472b6; }
    .stat-card { transition: all 0.3s ease; } .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
    .theme-toggle { width: 44px; height: 24px; background: #cbd5e1; border-radius: 12px; position: relative; cursor: pointer; transition: background 0.3s ease; }
    .dark .theme-toggle { background: #334155; }
    .theme-toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: transform 0.3s ease; }
    .dark .theme-toggle::after { transform: translateX(20px); background: #fbbf24; }
    .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
    .modal-container { background: var(--card-bg); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--glass-border); border-radius: 1.5rem; box-shadow: 0 25px 60px rgba(0,0,0,0.2); max-height: 85vh; overflow-y: auto; }
    .badge-success { background: #d1fae5; color: #065f46; } .badge-warning { background: #fef3c7; color: #92400e; } .badge-danger { background: #fee2e2; color: #991b1b; } .badge-info { background: #dbeafe; color: #1e40af; }
    .dark .badge-success { background: rgba(16,185,129,0.2); color: #6ee7b7; } .dark .badge-warning { background: rgba(245,158,11,0.2); color: #fcd34d; } .dark .badge-danger { background: rgba(239,68,68,0.2); color: #fca5a5; } .dark .badge-info { background: rgba(59,130,246,0.2); color: #93c5fd; }
    .file-upload-area { border: 2px dashed var(--input-border); border-radius: 0.75rem; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.3s ease; }
    .file-upload-area:hover, .file-upload-area.dragover { border-color: #ec4899; background: rgba(236,72,153,0.05); }
    .file-upload-area.has-file { border-color: #10b981; background: rgba(16,185,129,0.05); border-style: solid; }
    .preview-img { width: 100%; height: 120px; object-fit: cover; border-radius: 0.5rem; }
    *:focus-visible { outline: 2px solid #ec4899; outline-offset: 2px; border-radius: 6px; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in-up { animation: fadeInUp 0.4s ease-out forwards; }
    .real-card { transition: all 0.3s ease; }
    .real-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(236,72,153,0.1); }
    @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } }
  </style>
</head>
<body class="h-screen flex overflow-hidden">
  <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

  <!-- SIDEBAR -->
  <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
    <div class="p-5 border-b border-white/10 flex-shrink-0">
      <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center shadow-lg"><span class="font-bold text-white">NGS</span></div><div><h2 class="font-bold text-sm">NGS Pro</h2><p class="text-[10px] text-gray-400">Dashboard <?= $is_pdg ? 'PDG' : 'Boutique' ?></p></div></div>
    </div>
    <div class="p-5 border-b border-white/10 flex-shrink-0">
      <div class="flex items-center gap-3"><div class="w-10 h-10 rounded-full bg-pink-500/20 border border-pink-400/30 flex items-center justify-center"><i class="fas fa-<?= $is_pdg ? 'crown text-amber-400' : 'store text-blue-400' ?>"></i></div><div class="min-w-0"><p class="font-semibold text-sm truncate"><?= $is_pdg ? 'Directeur Général' : htmlspecialchars($_SESSION['boutique_nom'] ?? 'Boutique') ?></p></div></div>
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
        <a href="realisations.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations<?php if($total_realisations>0): ?><span class="ml-auto bg-pink-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_realisations ?></span><?php endif; ?></a>
      <?php else: ?>
        <a href="dashboard_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
        <a href="stock_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Mes stocks</a>
        <a href="ventes_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-shopping-cart w-4 text-center"></i>Ventes</a>
        <a href="paiements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-money-bill-wave w-4 text-center"></i>Paiements</a>
        <a href="mouvements.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Mouvements</a>
        <a href="transferts-boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-truck-loading w-4 text-center"></i>Transferts</a>
        <a href="rapports_boutique.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
        <a href="realisations.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-images w-4 text-center"></i>Réalisations<?php if($total_realisations>0): ?><span class="ml-auto bg-pink-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_realisations ?></span><?php endif; ?></a>
      <?php endif; ?>
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
        <div class="flex items-center gap-3"><button id="mobileMenuBtn" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors text-[var(--text-primary)]"><i class="fas fa-bars text-lg"></i></button><div><h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Gestion des Réalisations</h1><p class="text-xs text-[var(--text-muted)]">Portfolio & Demandes de service • <?= $total_realisations ?> réalisation(s)</p></div></div>
        <div class="flex items-center gap-2"><button onclick="openAjoutModal()" class="btn-pink px-4 py-2 rounded-xl text-sm flex items-center gap-2"><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouvelle réalisation</span></button></div>
      </div>
    </header>

    <div class="p-4 md:p-6 space-y-6">
      <?php if ($message): ?>
        <div class="animate-fade-in-up"><div class="glass rounded-2xl p-4 border-l-4 <?= $message_type==='success' ? 'border-emerald-500' : 'border-red-500' ?>"><div class="flex items-center gap-3"><i class="fas fa-<?= $message_type==='success' ? 'check-circle text-emerald-500' : 'exclamation-circle text-red-500' ?> text-xl"></i><span class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($message) ?></span><button onclick="this.closest('.animate-fade-in-up').remove()" class="ml-auto text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button></div></div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-pink-500" style="animation-delay:0s"><div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-pink-600 dark:text-pink-400">Total</span><div class="w-8 h-8 rounded-lg bg-pink-100 dark:bg-pink-900/30 flex items-center justify-center"><i class="fas fa-images text-pink-600 dark:text-pink-400 text-sm"></i></div></div><p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_realisations ?></p><p class="text-xs text-[var(--text-muted)] mt-1"><?= count(array_filter($realisations, fn($r)=>$r['est_publie'])) ?> publiées</p></div>
        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-rose-500" style="animation-delay:0.1s"><div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-rose-600 dark:text-rose-400">Likes</span><div class="w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center"><i class="fas fa-heart text-rose-600 dark:text-rose-400 text-sm"></i></div></div><p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_likes ?></p><p class="text-xs text-[var(--text-muted)] mt-1">Total</p></div>
        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-amber-500" style="animation-delay:0.2s"><div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-amber-600 dark:text-amber-400">Demandes</span><div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center"><i class="fas fa-envelope text-amber-600 dark:text-amber-400 text-sm"></i></div></div><p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_demandes ?></p><p class="text-xs text-[var(--text-muted)] mt-1">En attente</p></div>
        <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.3s"><div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Page</span><div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-file-alt text-emerald-600 dark:text-emerald-400 text-sm"></i></div></div><p class="text-2xl font-bold text-[var(--text-primary)]"><?= $page ?>/<?= $totalPages ?></p><p class="text-xs text-[var(--text-muted)] mt-1">Pagination</p></div>
      </div>

      <!-- Pagination top -->
      <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between animate-fade-in-up" style="animation-delay:0.15s">
          <span class="text-xs text-[var(--text-muted)]"><?= ($page-1)*$limit+1 ?>-<?= min($page*$limit, $total_realisations) ?> sur <?= $total_realisations ?></span>
          <div class="flex items-center gap-1.5">
            <a href="?page=<?= max(1, $page-1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page<=1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-left text-xs"></i></a>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?><a href="?page=<?= $i ?>" class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i==$page ? 'btn-pink shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>"><?= $i ?></a><?php endfor; ?>
            <a href="?page=<?= min($totalPages, $page+1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page>=$totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-right text-xs"></i></a>
          </div>
        </div>
      <?php endif; ?>

      <!-- Onglets -->
      <div class="flex flex-wrap gap-2 animate-fade-in-up" style="animation-delay:0.15s">
        <button class="tab-btn active px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-pink-500 to-rose-500 text-white shadow-md" data-tab="realisations"><i class="fas fa-images mr-1.5"></i>Réalisations</button>
        <button class="tab-btn px-4 py-2 rounded-xl text-sm font-medium glass text-[var(--text-secondary)]" data-tab="demandes"><i class="fas fa-envelope mr-1.5"></i>Demandes (<?= $total_demandes ?>)</button>
      </div>

      <div id="tab-contents">
        <!-- RÉALISATIONS -->
        <div id="tab-realisations" class="tab-content space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (!empty($realisations)): ?>
              <?php foreach ($realisations as $r): ?>
                <?php $imgSrc = '../' . $r['image_principale']; // préfixe pour admin ?>
                <div class="premium-card overflow-hidden real-card">
                  <div class="relative h-48 overflow-hidden">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($r['titre']) ?>" class="w-full h-full object-cover" onerror="this.src='https://images.unsplash.com/photo-1618220179428-22790b461013?w=400'">
                    <div class="absolute top-3 left-3 flex gap-2"><span class="px-2 py-1 rounded-full text-xs font-medium bg-pink-500/80 text-white backdrop-blur-sm"><?= ucfirst($r['categorie']) ?></span><?php if (!$r['est_publie']): ?><span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-500/80 text-white backdrop-blur-sm">Brouillon</span><?php endif; ?></div>
                    <div class="absolute top-3 right-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-white/80 text-pink-600 backdrop-blur-sm"><i class="fas fa-heart mr-1"></i><?= $r['likes'] ?></span></div>
                    <?php if ($r['img_count'] > 1): ?><div class="absolute bottom-3 right-3 px-2 py-1 rounded-full text-xs bg-black/50 text-white backdrop-blur-sm"><i class="fas fa-images mr-1"></i><?= $r['img_count'] ?></div><?php endif; ?>
                  </div>
                  <div class="p-4">
                    <h3 class="font-bold text-[var(--text-primary)] mb-1 line-clamp-1"><?= htmlspecialchars($r['titre']) ?></h3>
                    <p class="text-xs text-[var(--text-muted)] mb-2"><?= htmlspecialchars($r['boutique_nom']) ?> • <?= date('d/m/Y', strtotime($r['date_realisation'] ?? $r['date_creation'])) ?></p>
                    <p class="text-sm text-[var(--text-secondary)] mb-3 line-clamp-2"><?= htmlspecialchars($r['description'] ?? 'Aucune description') ?></p>
                    <div class="flex items-center justify-between">
                      <span class="text-xs text-[var(--text-muted)]"><i class="fas fa-map-marker-alt mr-1 text-pink-500"></i><?= htmlspecialchars($r['client_ville'] ?? 'Non spécifié') ?></span>
                      <div class="flex gap-1.5">
                        <button onclick="openPublishModal(<?= $r['id'] ?>, '<?= addslashes($r['titre']) ?>', <?= $r['est_publie'] ?>)" class="p-1.5 rounded-lg <?= $r['est_publie'] ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' : 'bg-gray-100 dark:bg-gray-800 text-gray-500' ?> hover:bg-opacity-80 transition-colors" title="<?= $r['est_publie'] ? 'Dépublier' : 'Publier' ?>"><i class="fas fa-<?= $r['est_publie'] ? 'eye' : 'eye-slash' ?> text-xs"></i></button>
                        <button onclick="openEditModal(<?= $r['id'] ?>)" class="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors" title="Modifier"><i class="fas fa-edit text-xs"></i></button>
                        <button onclick="openDeleteModal(<?= $r['id'] ?>, '<?= addslashes($r['titre']) ?>')" class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Supprimer"><i class="fas fa-trash text-xs"></i></button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-span-full text-center py-12"><i class="fas fa-images text-5xl text-[var(--text-muted)] opacity-30 mb-4 block"></i><p class="text-[var(--text-secondary)] text-lg">Aucune réalisation</p><p class="text-xs text-[var(--text-muted)] mt-1">Ajoutez votre première réalisation</p></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- DEMANDES -->
        <div id="tab-demandes" class="tab-content hidden">
          <div class="premium-card overflow-hidden">
            <div class="px-5 py-4 border-b border-[var(--divider)] flex items-center justify-between"><h3 class="text-base font-bold text-[var(--text-primary)]">Demandes de service</h3><span class="text-xs text-[var(--text-muted)]"><?= $total_demandes ?> demande(s)</span></div>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[800px]">
                <thead><tr class="border-b border-[var(--divider)] text-left"><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">ID</th><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Date</th><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Client</th><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Réalisation</th><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Boutique</th><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Statut</th><th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Actions</th></tr></thead>
                <tbody class="divide-y divide-[var(--divider)]">
                  <?php if (!empty($demandes)): ?>
                    <?php foreach ($demandes as $d): $sc = ['nouvelle'=>'badge-warning','contactee'=>'badge-info','en_cours'=>'badge-info','terminee'=>'badge-success','annulee'=>'badge-danger']; ?>
                      <tr class="hover:bg-white/5 transition-colors">
                        <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $d['id'] ?></td>
                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= date('d/m/Y H:i', strtotime($d['date_creation'])) ?></td>
                        <td class="px-5 py-3.5"><span class="text-sm font-medium text-[var(--text-primary)]"><?= htmlspecialchars($d['client_nom']) ?></span><span class="text-xs text-[var(--text-muted)] block"><?= htmlspecialchars($d['client_telephone']) ?></span></td>
                        <td class="px-5 py-3.5 text-sm text-[var(--text-primary)] max-w-[200px] truncate"><?= htmlspecialchars($d['realisation_titre']) ?></td>
                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($d['boutique_nom']) ?></td>
                        <td class="px-5 py-3.5"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $sc[$d['statut']] ?? 'badge-info' ?>"><?= ucfirst($d['statut']) ?></span></td>
                        <td class="px-5 py-3.5"><div class="flex items-center justify-center gap-1.5"><button onclick="openDemandeDetail(<?= $d['id'] ?>)" class="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors" title="Détails"><i class="fas fa-eye text-xs"></i></button><form method="POST" action="../models/traitement/demande-action.php" class="inline" onsubmit="return confirm('Changer le statut ?')"><input type="hidden" name="demande_id" value="<?= $d['id'] ?>"><input type="hidden" name="action" value="changer_statut"><input type="hidden" name="nouveau_statut" value="<?= $d['statut']=='nouvelle' ? 'contactee' : ($d['statut']=='contactee' ? 'en_cours' : 'terminee') ?>"><button type="submit" class="p-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-200 dark:hover:bg-emerald-900/50 transition-colors" title="Avancer"><i class="fas fa-arrow-right text-xs"></i></button></form></div></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?><tr><td colspan="7" class="px-5 py-12 text-center"><i class="fas fa-envelope text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i><p class="text-[var(--text-secondary)]">Aucune demande</p></td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination bottom -->
      <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-center gap-1.5 pt-4 animate-fade-in-up" style="animation-delay:0.2s">
          <a href="?page=<?= max(1, $page-1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page<=1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-left text-xs"></i></a>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?><?php if ($i == 1 || $i == $totalPages || ($i >= $page-1 && $i <= $page+1)): ?><a href="?page=<?= $i ?>" class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i==$page ? 'btn-pink shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>"><?= $i ?></a><?php elseif ($i == $page-2 || $i == $page+2): ?><span class="px-1 text-[var(--text-muted)] text-sm">...</span><?php endif; ?><?php endfor; ?>
          <a href="?page=<?= min($totalPages, $page+1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page>=$totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-right text-xs"></i></a>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- MODALS -->
  <!-- AJOUT -->
  <div id="ajoutOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="ajoutContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-lg p-6">
    <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-plus-circle mr-2 text-pink-500"></i>Nouvelle réalisation</h3><button onclick="closeAjoutModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button></div>
    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
      <?php if ($is_pdg): ?><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Boutique *</label><select name="boutique_id" required class="w-full input-glass px-3 py-2.5 text-sm"><?php foreach ($boutiques as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nom']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="grid grid-cols-2 gap-3"><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Titre *</label><input type="text" name="titre" required class="w-full input-glass px-3 py-2.5 text-sm" placeholder="Ex: Rideaux salon luxueux"></div><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Catégorie</label><select name="categorie" class="w-full input-glass px-3 py-2.5 text-sm"><option value="rideaux">Rideaux</option><option value="voilages">Voilages</option><option value="stores">Stores</option><option value="installation">Installation</option><option value="sur_mesure">Sur mesure</option><option value="autre">Autre</option></select></div></div>
      <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Image principale *</label><div class="file-upload-area" id="mainUploadArea" onclick="document.getElementById('mainFile').click()"><input type="file" name="image_principale" id="mainFile" accept="image/*" required class="hidden" onchange="previewFile(this, 'mainPreview', 'mainUploadArea')"><div id="mainPreview" class="space-y-2"><i class="fas fa-cloud-upload-alt text-3xl text-[var(--text-muted)]"></i><p class="text-sm text-[var(--text-muted)]">Cliquez pour sélectionner l'image principale</p><p class="text-xs text-[var(--text-muted)]">JPG, PNG, WEBP - Max 10 MB</p></div></div></div>
      <div class="grid grid-cols-2 gap-3"><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Image secondaire 1</label><div class="file-upload-area" id="supp1Area" onclick="document.getElementById('supp1File').click()"><input type="file" name="image_supp_1" id="supp1File" accept="image/*" class="hidden" onchange="previewFile(this, 'supp1Preview', 'supp1Area')"><div id="supp1Preview" class="space-y-1"><i class="fas fa-cloud-upload-alt text-2xl text-[var(--text-muted)]"></i><p class="text-xs text-[var(--text-muted)]">Optionnelle</p></div></div></div><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Image secondaire 2</label><div class="file-upload-area" id="supp2Area" onclick="document.getElementById('supp2File').click()"><input type="file" name="image_supp_2" id="supp2File" accept="image/*" class="hidden" onchange="previewFile(this, 'supp2Preview', 'supp2Area')"><div id="supp2Preview" class="space-y-1"><i class="fas fa-cloud-upload-alt text-2xl text-[var(--text-muted)]"></i><p class="text-xs text-[var(--text-muted)]">Optionnelle</p></div></div></div></div>
      <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Description</label><textarea name="description" rows="2" class="w-full input-glass px-3 py-2.5 text-sm resize-none"></textarea></div>
      <div class="grid grid-cols-3 gap-3"><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date</label><input type="date" name="date_realisation" value="<?= date('Y-m-d') ?>" class="w-full input-glass px-3 py-2.5 text-sm"></div><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Client</label><input type="text" name="client_nom" class="w-full input-glass px-3 py-2.5 text-sm" placeholder="Nom"></div><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Ville</label><input type="text" name="client_ville" class="w-full input-glass px-3 py-2.5 text-sm" placeholder="Ville"></div></div>
      <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Prix indicatif ($)</label><input type="number" name="prix_indicatif" step="0.01" class="w-full input-glass px-3 py-2.5 text-sm" placeholder="0.00"></div>
      <div class="p-4 rounded-xl bg-pink-50 dark:bg-pink-900/20 border border-pink-200 dark:border-pink-800/30 text-xs text-pink-700 dark:text-pink-300"><i class="fas fa-info-circle mr-1"></i>Formats acceptés : JPG, PNG, WEBP, GIF. Taille max : 10 MB par image.</div>
      <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeAjoutModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button><button type="submit" name="ajouter_realisation" class="btn-pink px-5 py-2.5 rounded-xl text-sm">Ajouter</button></div>
    </form>
  </div>

  <!-- MODIFICATION -->
  <div id="editOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="editContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-lg p-6">
    <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-bold text-[var(--text-primary)]"><i class="fas fa-edit mr-2 text-blue-500"></i>Modifier la réalisation</h3><button onclick="closeEditModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button></div>
    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="realisation_id" id="editId">
      <div class="grid grid-cols-2 gap-3"><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Titre *</label><input type="text" name="titre" id="editTitre" required class="w-full input-glass px-3 py-2.5 text-sm"></div><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Catégorie</label><select name="categorie" id="editCategorie" class="w-full input-glass px-3 py-2.5 text-sm"><option value="rideaux">Rideaux</option><option value="voilages">Voilages</option><option value="stores">Stores</option><option value="installation">Installation</option><option value="sur_mesure">Sur mesure</option><option value="autre">Autre</option></select></div></div>
      <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Image principale</label><div class="file-upload-area" id="editMainArea" onclick="document.getElementById('editMainFile').click()"><input type="file" name="image_principale" id="editMainFile" accept="image/*" class="hidden" onchange="previewFile(this, 'editMainPreview', 'editMainArea')"><div id="editMainPreview"><p class="text-xs text-[var(--text-muted)]">Image actuelle : <span id="editMainName" class="text-pink-500"></span></p><p class="text-xs text-[var(--text-muted)]">Cliquez pour changer (laisser vide pour conserver)</p></div></div></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Image secondaire 1</label><input type="hidden" name="old_supp_1_url" id="oldSupp1Url"><input type="hidden" name="keep_supp_1" id="keepSupp1" value="0"><div class="file-upload-area" id="editSupp1Area" onclick="document.getElementById('editSupp1File').click()"><input type="file" name="image_supp_1" id="editSupp1File" accept="image/*" class="hidden" onchange="previewFile(this, 'editSupp1Preview', 'editSupp1Area'); document.getElementById('keepSupp1').value='0';"><div id="editSupp1Preview"><p class="text-xs text-[var(--text-muted)]">Aucune</p></div></div></div>
        <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Image secondaire 2</label><input type="hidden" name="old_supp_2_url" id="oldSupp2Url"><input type="hidden" name="keep_supp_2" id="keepSupp2" value="0"><div class="file-upload-area" id="editSupp2Area" onclick="document.getElementById('editSupp2File').click()"><input type="file" name="image_supp_2" id="editSupp2File" accept="image/*" class="hidden" onchange="previewFile(this, 'editSupp2Preview', 'editSupp2Area'); document.getElementById('keepSupp2').value='0';"><div id="editSupp2Preview"><p class="text-xs text-[var(--text-muted)]">Aucune</p></div></div></div>
      </div>
      <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Description</label><textarea name="description" id="editDesc" rows="2" class="w-full input-glass px-3 py-2.5 text-sm resize-none"></textarea></div>
      <div class="grid grid-cols-3 gap-3"><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Date</label><input type="date" name="date_realisation" id="editDate" class="w-full input-glass px-3 py-2.5 text-sm"></div><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Client</label><input type="text" name="client_nom" id="editClient" class="w-full input-glass px-3 py-2.5 text-sm"></div><div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Ville</label><input type="text" name="client_ville" id="editVille" class="w-full input-glass px-3 py-2.5 text-sm"></div></div>
      <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Prix indicatif ($)</label><input type="number" name="prix_indicatif" id="editPrix" step="0.01" class="w-full input-glass px-3 py-2.5 text-sm"></div>
      <div class="flex justify-end gap-3 pt-2"><button type="button" onclick="closeEditModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button><button type="submit" name="modifier_realisation" class="btn-glass px-5 py-2.5 rounded-xl text-sm">Enregistrer</button></div>
    </form>
  </div>

  <!-- PUBLIER/DÉPUBLIER -->
  <div id="publishOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="publishContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
    <i id="publishIcon" class="fas fa-eye text-5xl mb-4 text-emerald-500"></i>
    <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2" id="publishTitle">Publier cette réalisation ?</h3>
    <p class="text-sm text-[var(--text-secondary)] mb-6" id="publishText"></p>
    <div class="flex justify-center gap-3"><button type="button" onclick="closePublishModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button><a href="#" id="publishLink" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:opacity-90 transition-all">Confirmer</a></div>
  </div>

  <!-- SUPPRESSION -->
  <div id="deleteOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="deleteContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
    <i class="fas fa-trash-alt text-5xl text-red-500 mb-4"></i>
    <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Supprimer cette réalisation ?</h3>
    <p class="text-sm text-[var(--text-secondary)] mb-6" id="deleteText"></p>
    <form method="POST" action="" class="flex justify-center gap-3"><input type="hidden" name="realisation_id" id="deleteRealId"><button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button><button type="submit" name="supprimer_realisation" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-semibold hover:opacity-90 transition-all">Supprimer</button></form>
  </div>

  <!-- DÉTAIL DEMANDE -->
  <div id="demandeOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
  <div id="demandeContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
    <div class="flex items-center justify-between mb-5"><h3 class="text-lg font-bold text-[var(--text-primary)]">Détail demande</h3><button onclick="closeDemandeModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button></div>
    <div id="demandeDetailContent" class="space-y-3 text-sm"></div>
  </div>

  <script>
    // Theme
    const themeToggle=document.getElementById('theme-toggle'),html=document.documentElement;
    if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&window.matchMedia('(prefers-color-scheme:dark)').matches))html.classList.add('dark');
    themeToggle.addEventListener('click',()=>{html.classList.toggle('dark');localStorage.setItem('theme',html.classList.contains('dark')?'dark':'light')});

    // Sidebar
    const sidebar=document.getElementById('sidebar'),overlay=document.getElementById('overlay');
    function toggleSidebar(){sidebar.classList.toggle('open');overlay.classList.toggle('hidden')}
    document.getElementById('mobileMenuBtn').addEventListener('click',toggleSidebar);overlay.addEventListener('click',toggleSidebar);

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn=>{btn.addEventListener('click',()=>{document.querySelectorAll('.tab-btn').forEach(b=>{b.classList.remove('active','bg-gradient-to-r','from-pink-500','to-rose-500','text-white','shadow-md');b.classList.add('glass','text-[var(--text-secondary)]')});btn.classList.add('active','bg-gradient-to-r','from-pink-500','to-rose-500','text-white','shadow-md');btn.classList.remove('glass','text-[var(--text-secondary)]');document.querySelectorAll('.tab-content').forEach(c=>c.classList.add('hidden'));document.getElementById('tab-'+btn.dataset.tab)?.classList.remove('hidden')})});

    // Preview image
    function previewFile(input,previewId,areaId){const preview=document.getElementById(previewId),area=document.getElementById(areaId);if(input.files&&input.files[0]){const reader=new FileReader();reader.onload=function(e){preview.innerHTML=`<img src="${e.target.result}" class="preview-img" alt="Preview"><p class="text-xs text-emerald-500 mt-1">${input.files[0].name}</p>`;area.classList.add('has-file')};reader.readAsDataURL(input.files[0])}}

    // Modals
    function openModal(o,c){document.getElementById(o).classList.remove('hidden');document.getElementById(c).classList.remove('hidden')}
    function closeModal(o,c){document.getElementById(o).classList.add('hidden');document.getElementById(c).classList.add('hidden')}
    
    function openAjoutModal(){resetAjoutForm();openModal('ajoutOverlay','ajoutContent')}
    function closeAjoutModal(){closeModal('ajoutOverlay','ajoutContent')}
    function resetAjoutForm(){
      document.getElementById('mainPreview').innerHTML='<div class="space-y-2"><i class="fas fa-cloud-upload-alt text-3xl text-[var(--text-muted)]"></i><p class="text-sm text-[var(--text-muted)]">Cliquez pour sélectionner l\'image principale</p><p class="text-xs text-[var(--text-muted)]">JPG, PNG, WEBP - Max 10 MB</p></div>';
      document.getElementById('mainUploadArea').classList.remove('has-file');
      document.getElementById('supp1Preview').innerHTML='<div class="space-y-1"><i class="fas fa-cloud-upload-alt text-2xl text-[var(--text-muted)]"></i><p class="text-xs text-[var(--text-muted)]">Optionnelle</p></div>';
      document.getElementById('supp2Preview').innerHTML='<div class="space-y-1"><i class="fas fa-cloud-upload-alt text-2xl text-[var(--text-muted)]"></i><p class="text-xs text-[var(--text-muted)]">Optionnelle</p></div>';
      ['supp1Area','supp2Area'].forEach(id=>document.getElementById(id)?.classList.remove('has-file'));
    }

    // MODIFICATION via AJAX
    // MODIFICATION via API externe (plus fiable)
async function openEditModal(id) {
    try {
        const resp = await fetch('../api/get_realisation.php?id=' + id);
        if (!resp.ok) {
            throw new Error('Erreur HTTP ' + resp.status);
        }
        const json = await resp.json();
        if (!json.success) {
            alert('Erreur : ' + (json.message || 'Impossible de charger les données'));
            return;
        }

        const r = json.data;

        // Remplissage du formulaire
        document.getElementById('editId').value = r.id;
        document.getElementById('editTitre').value = r.titre;
        document.getElementById('editDesc').value = r.description || '';
        document.getElementById('editCategorie').value = r.categorie;
        document.getElementById('editDate').value = r.date_realisation || r.date_creation;
        document.getElementById('editClient').value = r.client_nom || '';
        document.getElementById('editVille').value = r.client_ville || '';
        document.getElementById('editPrix').value = r.prix_indicatif || '';

        // Image principale actuelle
        const imgName = r.image_principale.split('/').pop();
        document.getElementById('editMainName').textContent = imgName;
        document.getElementById('editMainPreview').innerHTML =
            `<p class="text-xs text-[var(--text-muted)]">Image actuelle : <span class="text-pink-500">${imgName}</span></p>
             <p class="text-xs text-[var(--text-muted)]">Cliquez pour changer (laisser vide pour conserver)</p>`;
        document.getElementById('editMainArea').classList.remove('has-file');

        // Réinitialisation des images supplémentaires
        ['editSupp1Preview', 'editSupp2Preview'].forEach(el => {
            document.getElementById(el).innerHTML = '<p class="text-xs text-[var(--text-muted)]">Aucune</p>';
        });
        ['editSupp1Area', 'editSupp2Area'].forEach(el => {
            document.getElementById(el)?.classList.remove('has-file');
        });
        document.getElementById('keepSupp1').value = '0';
        document.getElementById('keepSupp2').value = '0';
        document.getElementById('oldSupp1Url').value = '';
        document.getElementById('oldSupp2Url').value = '';

        // Images supplémentaires existantes
        if (r.images_supp && r.images_supp.length > 0) {
            r.images_supp.forEach((img, i) => {
                const idx = i + 1;
                const elUrl = document.getElementById(`oldSupp${idx}Url`);
                const elKeep = document.getElementById(`keepSupp${idx}`);
                const elPreview = document.getElementById(`editSupp${idx}Preview`);
                const elArea = document.getElementById(`editSupp${idx}Area`);

                if (elUrl) elUrl.value = img.image_url;
                if (elKeep) elKeep.value = '1';
                if (elPreview) {
                    elPreview.innerHTML = `<img src="../${img.image_url}" class="preview-img" alt="Supp ${idx}">
                        <p class="text-xs text-emerald-500 mt-1">Image conservée (cliquez pour changer)</p>`;
                }
                if (elArea) elArea.classList.add('has-file');
            });
        }

        openModal('editOverlay', 'editContent');

    } catch (error) {
        console.error('Erreur AJAX:', error);
        alert('Erreur réseau. Veuillez réessayer.');
    }
}

    function closeEditModal(){closeModal('editOverlay','editContent')}

    function openPublishModal(id,titre,estPublie){
      const action=estPublie?'dépublier':'publier';
      document.getElementById('publishTitle').textContent=estPublie?'Dépublier cette réalisation ?':'Publier cette réalisation ?';
      document.getElementById('publishText').textContent=`La réalisation "${titre}" sera ${action}.`;
      document.getElementById('publishIcon').className=`fas fa-${estPublie?'eye-slash':'eye'} text-5xl mb-4 ${estPublie?'text-amber-500':'text-emerald-500'}`;
      document.getElementById('publishLink').href=`realisations.php?toggle_publie=1&id=${id}`;
      openModal('publishOverlay','publishContent');
    }
    function closePublishModal(){closeModal('publishOverlay','publishContent')}

    function openDeleteModal(id,titre){
      document.getElementById('deleteRealId').value=id;
      document.getElementById('deleteText').textContent=`Vous allez supprimer définitivement la réalisation "${titre}". Cette action est irréversible.`;
      openModal('deleteOverlay','deleteContent');
    }
    function closeDeleteModal(){closeModal('deleteOverlay','deleteContent')}

    function openDemandeDetail(id){
      fetch(`../api/demande-detail.php?id=${id}`).then(r=>r.json()).then(d=>{
        if(d.success){
          const dd=d.data;
          document.getElementById('demandeDetailContent').innerHTML=`
            <p><strong>Client :</strong> ${dd.client_nom}</p>
            <p><strong>Téléphone :</strong> ${dd.client_telephone}</p>
            <p><strong>Email :</strong> ${dd.client_email||'-'}</p>
            <p><strong>Ville :</strong> ${dd.client_ville||'-'}</p>
            <p><strong>Réalisation :</strong> ${dd.realisation_titre}</p>
            <p><strong>Message :</strong> ${dd.message||'-'}</p>
            <p><strong>Budget estimé :</strong> ${dd.budget_estime||'Non spécifié'} $</p>
            <p><strong>Statut :</strong> ${dd.statut}</p>
            <p><strong>Date :</strong> ${new Date(dd.date_creation).toLocaleString('fr-FR')}</p>
          `;
          openModal('demandeOverlay','demandeContent');
        }
      });
    }
    function closeDemandeModal(){closeModal('demandeOverlay','demandeContent')}

    ['ajoutOverlay','editOverlay','publishOverlay','deleteOverlay','demandeOverlay'].forEach(id=>{document.getElementById(id)?.addEventListener('click',function(e){if(e.target===this)closeModal(id,id.replace('Overlay','Content'))})});

    // Drag & drop
    document.querySelectorAll('.file-upload-area').forEach(area=>{area.addEventListener('dragover',e=>{e.preventDefault();area.classList.add('dragover')});area.addEventListener('dragleave',()=>area.classList.remove('dragover'));area.addEventListener('drop',e=>{e.preventDefault();area.classList.remove('dragover');const input=area.querySelector('input[type="file"]');if(input&&e.dataTransfer.files.length>0){input.files=e.dataTransfer.files;input.dispatchEvent(new Event('change'))}})});

    // Escape
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeAjoutModal();closeEditModal();closePublishModal();closeDeleteModal();closeDemandeModal();if(sidebar.classList.contains('open'))toggleSidebar()}});
  </script>
  <?php unset($_SESSION['msg']); ?>
</body>
</html>