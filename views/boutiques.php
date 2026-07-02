<?php
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$message_type = '';
$total_boutiques = 0;
$boutiques = [];

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// AJAX get boutique
if (isset($_GET['action']) && $_GET['action'] == 'get_boutique' && isset($_GET['id'])) {
    $query = $pdo->prepare("SELECT id, nom, email, actif FROM boutiques WHERE id = ? AND statut = 0");
    $query->execute([(int)$_GET['id']]);
    $boutique = $query->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($boutique ? ['success' => true, 'boutique' => $boutique] : ['success' => false, 'message' => 'Non trouvée']);
    exit;
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

try {
    $total_boutiques = $pdo->query("SELECT COUNT(*) FROM boutiques WHERE statut = 0")->fetchColumn();
    $totalPages = ceil($total_boutiques / $limit);
    if ($totalPages < 1) $totalPages = 1;

    $query = $pdo->prepare("SELECT * FROM boutiques WHERE statut = 0 ORDER BY actif DESC, date_creation DESC LIMIT :limit OFFSET :offset");
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    $boutiques = $query->fetchAll();

    $active_count = $pdo->query("SELECT COUNT(*) FROM boutiques WHERE actif = 1 AND statut = 0")->fetchColumn();
} catch (PDOException $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    $active_count = 0;
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Boutiques - NGS (PDG)</title>

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

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .dark .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
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
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-line w-4 text-center"></i>Tableau de bord</a>
            <a href="boutiques.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-store w-4 text-center"></i>Boutiques<?php if ($total_boutiques > 0): ?><span class="ml-auto bg-blue-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $total_boutiques ?></span><?php endif; ?></a>
            <a href="produits.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-box w-4 text-center"></i>Produits</a>
            <a href="stocks.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-warehouse w-4 text-center"></i>Stocks</a>
            <a href="transferts.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-exchange-alt w-4 text-center"></i>Transferts</a>
            <a href="utilisateurs.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-users w-4 text-center"></i>Utilisateurs</a>
            <a href="rapports_pdg.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-chart-bar w-4 text-center"></i>Rapports</a>
            <a href="numeros_rideaux.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tags w-4 text-center"></i>N° Rideaux</a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Gestion des Boutiques</h1>
                        <p class="text-xs text-[var(--text-muted)]">Administration des points de vente</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="openBoutiqueModal()" class="btn-glass px-4 py-2 rounded-xl text-sm flex items-center gap-2"><i class="fas fa-plus-circle"></i><span class="hidden sm:inline">Nouvelle boutique</span></button>
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
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Total</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-store text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_boutiques ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Boutiques</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Actives</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-check-circle text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $active_count ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Opérationnelles</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-red-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-red-600 dark:text-red-400">Inactives</span>
                        <div class="w-8 h-8 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center"><i class="fas fa-times-circle text-red-600 dark:text-red-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_boutiques - $active_count ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Désactivées</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-purple-600 dark:text-purple-400">Page</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-file-alt text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $page ?>/<?= $totalPages ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Pagination</p>
                </div>
            </div>

            <!-- Recherche -->
            <div class="premium-card p-4 animate-fade-in-up" style="animation-delay:0.15s">
                <div class="flex items-center gap-3">
                    <div class="relative flex-1 max-w-md">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-muted)]"></i>
                        <input type="text" id="searchInput" placeholder="Rechercher par nom ou email..." class="w-full input-glass pl-10 pr-4 py-2.5 text-sm">
                    </div>
                    <span class="text-xs text-[var(--text-muted)] hidden sm:block">Page <?= $page ?>/<?= $totalPages ?></span>
                    <button onclick="window.location.reload()" class="p-2.5 rounded-xl glass hover:bg-white/20 transition-all text-[var(--text-muted)]" title="Actualiser"><i class="fas fa-sync-alt text-sm"></i></button>
                </div>
            </div>

            <!-- Tableau -->
            <div class="premium-card overflow-hidden animate-fade-in-up" style="animation-delay:0.2s">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px]" id="boutiquesTable">
                        <thead>
                            <tr class="border-b border-[var(--divider)] text-left">
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">ID</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Nom</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase hidden sm:table-cell">Email</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase">Statut</th>
                                <th class="px-5 py-3 text-xs font-semibold text-[var(--text-muted)] uppercase text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--divider)]" id="tableBody">
                            <?php if (!empty($boutiques)): ?>
                                <?php foreach ($boutiques as $b): ?>
                                    <tr class="hover:bg-white/5 transition-colors boutique-row" data-name="<?= strtolower($b['nom']) ?>" data-email="<?= strtolower($b['email']) ?>">
                                        <td class="px-5 py-3.5 text-sm font-mono font-bold text-[var(--text-primary)]">#<?= $b['id'] ?></td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0"><i class="fas fa-store text-blue-600 dark:text-blue-400 text-xs"></i></div>
                                                <span class="text-sm font-medium text-[var(--text-primary)] truncate max-w-[150px]"><?= htmlspecialchars($b['nom']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 text-sm text-[var(--text-secondary)] hidden sm:table-cell"><?= htmlspecialchars($b['email']) ?></td>
                                        <td class="px-5 py-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $b['actif'] ? 'badge-success' : 'badge-danger' ?>"><?= $b['actif'] ? 'Active' : 'Inactive' ?></span>
                                        </td>
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button onclick="openBoutiqueModal(<?= $b['id'] ?>)" class="p-1.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors" title="Modifier"><i class="fas fa-edit text-xs"></i></button>
                                                <button onclick="openToggleModal(<?= $b['id'] ?>,'<?= htmlspecialchars(addslashes($b['nom'])) ?>',<?= $b['actif'] ?>)" class="p-1.5 rounded-lg <?= $b['actif'] ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400' : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' ?> hover:bg-opacity-80 transition-colors" title="<?= $b['actif'] ? 'Désactiver' : 'Activer' ?>"><i class="fas fa-power-off text-xs"></i></button>
                                                <button onclick="openDeleteModal(<?= $b['id'] ?>,'<?= htmlspecialchars(addslashes($b['nom'])) ?>')" class="p-1.5 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors" title="Supprimer"><i class="fas fa-trash text-xs"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-5 py-12 text-center"><i class="fas fa-store-slash text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                                        <p class="text-[var(--text-secondary)] font-medium">Aucune boutique</p><button onclick="openBoutiqueModal()" class="mt-4 btn-glass px-4 py-2 rounded-xl text-sm">Ajouter une boutique</button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResults" class="hidden text-center py-12"><i class="fas fa-search text-4xl text-[var(--text-muted)] opacity-30 mb-3 block"></i>
                    <p class="text-[var(--text-secondary)]">Aucun résultat</p>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="px-5 py-4 border-t border-[var(--divider)] flex items-center justify-between">
                        <span class="text-xs text-[var(--text-muted)] hidden sm:block"><?= ($page - 1) * $limit + 1 ?>-<?= min($page * $limit, $total_boutiques) ?> sur <?= $total_boutiques ?></span>
                        <div class="flex items-center gap-1.5 mx-auto sm:mx-0">
                            <a href="?page=<?= max(1, $page - 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page <= 1 ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-left text-xs"></i></a>
                            <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                                <a href="?page=<?= $i ?>" class="w-8 h-8 rounded-lg text-sm font-medium flex items-center justify-center transition-all <?= $i == $page ? 'btn-glass shadow-md' : 'glass hover:bg-white/20 text-[var(--text-secondary)]' ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            <a href="?page=<?= min($totalPages, $page + 1) ?>" class="w-8 h-8 rounded-lg glass flex items-center justify-center text-sm <?= $page >= $totalPages ? 'opacity-40 pointer-events-none' : 'hover:bg-white/20' ?>"><i class="fas fa-chevron-right text-xs"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- MODAL AJOUT/MODIF -->
    <div id="boutiqueOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="boutiqueContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-[var(--text-primary)]" id="modalTitle"><i class="fas fa-plus-circle mr-2 text-blue-500"></i>Nouvelle boutique</h3><button onclick="closeBoutiqueModal()" class="text-[var(--text-muted)] hover:text-[var(--text-primary)]"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="../models/traitement/boutique-post.php" class="space-y-4">
            <input type="hidden" name="id" id="boutiqueId">
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Nom *</label><input type="text" name="nom" id="nom" required class="w-full input-glass px-3 py-2.5 text-sm" placeholder="Ex: Boutique Centre"></div>
            <div><label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Email *</label><input type="email" name="email" id="email" required class="w-full input-glass px-3 py-2.5 text-sm" placeholder="contact@boutique.com"></div>
            <div id="pwdField">
                <label class="block text-xs font-medium text-[var(--text-secondary)] mb-1.5">Mot de passe <span id="pwdRequired" class="text-red-500">*</span></label>
                <input type="password" name="password" id="password" class="w-full input-glass px-3 py-2.5 text-sm" placeholder="••••••••">
                <p class="text-xs text-[var(--text-muted)] mt-1" id="pwdHint">Requis lors de l'ajout. Laisser vide pour conserver l'ancien.</p>
            </div>
            <div class="flex items-center gap-2"><input type="checkbox" name="actif" id="actif" value="1" checked class="w-4 h-4 rounded border-[var(--input-border)] text-blue-600 focus:ring-blue-500"><label for="actif" class="text-sm text-[var(--text-secondary)]">Boutique active</label></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeBoutiqueModal()" class="px-4 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
                <button type="submit" name="ajouter_boutique" id="submitBtn" class="btn-glass px-5 py-2.5 rounded-xl text-sm">Enregistrer</button>
            </div>
        </form>
    </div>

    <!-- MODAL TOGGLE -->
    <div id="toggleOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="toggleContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i id="toggleIcon" class="fas fa-power-off text-5xl mb-4 text-amber-500"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2" id="toggleTitle">Changer le statut ?</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6" id="toggleText"></p>
        <form method="POST" action="../models/traitement/boutique-post.php" class="flex justify-center gap-3">
            <input type="hidden" name="id" id="toggleId">
            <button type="button" onclick="closeToggleModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
            <button type="submit" name="toggle_actif" id="toggleBtn" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white hover:opacity-90 transition-all">Confirmer</button>
        </form>
    </div>

    <!-- MODAL DELETE -->
    <div id="deleteOverlay" class="modal-overlay fixed inset-0 z-[100] hidden"></div>
    <div id="deleteContent" class="modal-container fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] hidden w-[95%] max-w-sm p-6 text-center">
        <i class="fas fa-trash-alt text-5xl text-red-500 mb-4"></i>
        <h3 class="text-lg font-bold text-[var(--text-primary)] mb-2">Supprimer cette boutique ?</h3>
        <p class="text-sm text-[var(--text-secondary)] mb-6" id="deleteText"></p>
        <form method="POST" action="../models/traitement/boutique-post.php" class="flex justify-center gap-3">
            <input type="hidden" name="id" id="deleteId">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 rounded-xl glass text-sm text-[var(--text-secondary)] hover:bg-white/20 transition-all">Annuler</button>
            <button type="submit" name="supprimer_boutique" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-500 to-red-600 text-white text-sm font-semibold hover:opacity-90 transition-all">Supprimer</button>
        </form>
    </div>

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

        // Modals
        function openModal(o, c) {
            document.getElementById(o).classList.remove('hidden');
            document.getElementById(c).classList.remove('hidden')
        }

        function closeModal(o, c) {
            document.getElementById(o).classList.add('hidden');
            document.getElementById(c).classList.add('hidden')
        }

        function openBoutiqueModal(id = null) {
            document.getElementById('boutiqueForm')?.reset();
            if (id) {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit mr-2 text-blue-500"></i>Modifier la boutique';
                document.getElementById('submitBtn').textContent = 'Modifier';
                document.getElementById('submitBtn').name = 'modifier_boutique';
                document.getElementById('boutiqueId').value = id;
                document.getElementById('password').required = false;
                document.getElementById('pwdRequired').classList.add('hidden');
                document.getElementById('pwdHint').textContent = 'Laisser vide pour conserver le mot de passe actuel.';
                fetch('boutiques.php?action=get_boutique&id=' + id).then(r => r.json()).then(d => {
                    if (d.success) {
                        document.getElementById('nom').value = d.boutique.nom;
                        document.getElementById('email').value = d.boutique.email;
                        document.getElementById('actif').checked = d.boutique.actif == 1
                    }
                });
            } else {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle mr-2 text-blue-500"></i>Nouvelle boutique';
                document.getElementById('submitBtn').textContent = 'Enregistrer';
                document.getElementById('submitBtn').name = 'ajouter_boutique';
                document.getElementById('boutiqueId').value = '';
                document.getElementById('password').required = true;
                document.getElementById('pwdRequired').classList.remove('hidden');
                document.getElementById('pwdHint').textContent = 'Requis lors de l\'ajout.';
                document.getElementById('actif').checked = true;
            }
            openModal('boutiqueOverlay', 'boutiqueContent');
        }

        function closeBoutiqueModal() {
            closeModal('boutiqueOverlay', 'boutiqueContent')
        }

        function openToggleModal(id, nom, actif) {
            document.getElementById('toggleId').value = id;
            const action = actif ? 'désactiver' : 'activer';
            document.getElementById('toggleTitle').textContent = actif ? 'Désactiver la boutique ?' : 'Activer la boutique ?';
            document.getElementById('toggleText').innerHTML = `La boutique <strong>${nom}</strong> sera <strong>${action}</strong>.`;
            document.getElementById('toggleIcon').className = `fas fa-power-off text-5xl mb-4 ${actif?'text-orange-500':'text-emerald-500'}`;
            const btn = document.getElementById('toggleBtn');
            btn.className = actif ? 'px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-orange-500 to-orange-600 hover:opacity-90 transition-all' : 'px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:opacity-90 transition-all';
            btn.textContent = actif ? 'Désactiver' : 'Activer';
            openModal('toggleOverlay', 'toggleContent');
        }

        function closeToggleModal() {
            closeModal('toggleOverlay', 'toggleContent')
        }

        function openDeleteModal(id, nom) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteText').innerHTML = `Vous allez archiver la boutique <strong>${nom}</strong>. Ses données resteront en base (soft delete).`;
            openModal('deleteOverlay', 'deleteContent');
        }

        function closeDeleteModal() {
            closeModal('deleteOverlay', 'deleteContent')
        }

        ['boutiqueOverlay', 'toggleOverlay', 'deleteOverlay'].forEach(id => {
            document.getElementById(id)?.addEventListener('click', function(e) {
                if (e.target === this) closeModal(id, id.replace('Overlay', 'Content'))
            });
        });

        // Search
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const s = this.value.toLowerCase();
            let found = false;
            document.querySelectorAll('.boutique-row').forEach(r => {
                const m = r.dataset.name.includes(s) || r.dataset.email.includes(s);
                r.style.display = m ? '' : 'none';
                if (m) found = true;
            });
            document.getElementById('noResults')?.classList.toggle('hidden', found || s === '');
            document.getElementById('tableBody')?.classList.toggle('hidden', !found && s !== '');
        });

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeBoutiqueModal();
                closeToggleModal();
                closeDeleteModal();
                if (sidebar.classList.contains('open')) toggleSidebar()
            }
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>