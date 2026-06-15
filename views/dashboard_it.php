<?php
include '../connexion/connexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'it') {
    header('Location: ../login.php');
    exit;
}

// Stats de base
try {
    $total_users = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE statut = 0 AND actif = 1")->fetchColumn();
    $total_boutiques = $pdo->query("SELECT COUNT(*) FROM boutiques WHERE statut = 0 AND actif = 1")->fetchColumn();
    $total_produits = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut = 0 AND actif = 1")->fetchColumn();
    $total_stocks_actifs = $pdo->query("SELECT COUNT(*) FROM stock WHERE statut = 0 AND quantite > 0")->fetchColumn();

    $users_par_role = $pdo->query("SELECT role, COUNT(*) as count FROM utilisateurs WHERE statut = 0 AND actif = 1 GROUP BY role")->fetchAll();

    // Vérifier si la table logs existe
    $has_logs = false;
    $logs_recents = [];
    try {
        $logs_recents = $pdo->query("SELECT * FROM logs ORDER BY date_log DESC LIMIT 10")->fetchAll();
        $has_logs = !empty($logs_recents);
        $erreurs_recentes = $pdo->query("SELECT COUNT(*) FROM logs WHERE niveau='ERROR' AND date_log >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    } catch (PDOException $e) {
        $erreurs_recentes = 0;
    }

    // Vérifier si la table backups existe
    $has_backups = false;
    $sauvegardes = [];
    try {
        $sauvegardes = $pdo->query("SELECT * FROM backups ORDER BY date_backup DESC LIMIT 5")->fetchAll();
        $has_backups = !empty($sauvegardes);
    } catch (PDOException $e) {
        // Table inexistante
    }

    // Espace disque (simulation)
    $espace_disque = 85;
    $memoire_usage = 62;
    $cpu_usage = 34;
    $uptime_jours = 47;
} catch (PDOException $e) {
    error_log("Erreur dashboard IT: " . $e->getMessage());
    $total_users = 0;
    $total_boutiques = 0;
    $total_produits = 0;
    $total_stocks_actifs = 0;
    $erreurs_recentes = 0;
    $espace_disque = 0;
    $memoire_usage = 0;
    $cpu_usage = 0;
    $uptime_jours = 0;
    $users_par_role = [];
    $logs_recents = [];
    $sauvegardes = [];
    $has_logs = false;
    $has_backups = false;
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dashboard IT - NGS</title>

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
            --it-gradient: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
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
            --it-gradient: linear-gradient(135deg, #22d3ee 0%, #06b6d4 100%);
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

        .btn-cyan {
            background: var(--it-gradient);
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(8, 145, 178, 0.25);
        }

        .btn-cyan:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(8, 145, 178, 0.4);
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
            border-left: 3px solid #22d3ee;
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

        .progress-bar {
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            background: var(--input-border);
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .dark .badge-error {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .dark .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fcd34d;
        }

        .dark .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .dark .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: #93c5fd;
        }

        *:focus-visible {
            outline: 2px solid #0891b2;
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

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse-slow {
            animation: pulse 2s infinite;
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

    <!-- SIDEBAR IT -->
    <aside id="sidebar" class="sidebar w-64 flex flex-col fixed md:sticky top-0 h-full z-50 transition-transform duration-300 text-white">
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-500 to-teal-600 flex items-center justify-center shadow-lg"><span class="font-bold text-white">JR</span></div>
                <div>
                    <h2 class="font-bold text-sm">Julien_Rideau</h2>
                    <p class="text-[10px] text-gray-400">Dashboard IT</p>
                </div>
            </div>
        </div>
        <div class="p-5 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center"><i class="fas fa-server text-cyan-400"></i></div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate"><?= htmlspecialchars($_SESSION['user_name'] ?? 'IT Admin') ?></p>
                    <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></p>
                </div>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-1">
            <a href="dashboard_it.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tachometer-alt w-4 text-center"></i>Tableau de bord</a>
            <a href="utilisateurs.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-users-cog w-4 text-center"></i>Utilisateurs</a>
            <a href="logs.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-clipboard-list w-4 text-center"></i>Logs système<?php if ($erreurs_recentes > 0): ?><span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $erreurs_recentes ?></span><?php endif; ?></a>
            <a href="backups.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-database w-4 text-center"></i>Sauvegardes</a>
            <a href="maintenance.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-tools w-4 text-center"></i>Maintenance</a>
            <a href="parametres_systeme.php" class="nav-link flex items-center gap-3 px-3 py-2.5 text-sm"><i class="fas fa-sliders-h w-4 text-center"></i>Paramètres</a>
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
                        <h1 class="text-lg md:text-xl font-bold text-[var(--text-primary)]">Tableau de bord IT</h1>
                        <p class="text-xs text-[var(--text-muted)]">
                            <i class="fas fa-server mr-1 text-cyan-500"></i>Serveur: Production •
                            <span class="text-emerald-500"><i class="fas fa-circle text-[6px] align-middle mr-1"></i>Online</span> •
                            Uptime: <?= $uptime_jours ?> jours
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($erreurs_recentes > 0): ?>
                        <span class="px-3 py-1.5 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 flex items-center gap-1.5">
                            <i class="fas fa-exclamation-triangle animate-pulse-slow"></i><?= $erreurs_recentes ?> erreurs (24h)
                        </span>
                    <?php endif; ?>
                    <button onclick="window.location.reload()" class="p-2 rounded-xl glass hover:bg-white/20 transition-all text-[var(--text-muted)]" title="Actualiser"><i class="fas fa-sync-alt text-sm"></i></button>
                </div>
            </div>
        </header>

        <div class="p-4 md:p-6 space-y-6">

            <!-- Stats système -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-blue-500" style="animation-delay:0s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-blue-600 dark:text-blue-400">Utilisateurs</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"><i class="fas fa-users text-blue-600 dark:text-blue-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_users ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Actifs</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-purple-500" style="animation-delay:0.1s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-purple-600 dark:text-purple-400">Boutiques</span>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"><i class="fas fa-store text-purple-600 dark:text-purple-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_boutiques ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">En ligne</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-cyan-500" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-cyan-600 dark:text-cyan-400">Produits</span>
                        <div class="w-8 h-8 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 flex items-center justify-center"><i class="fas fa-box text-cyan-600 dark:text-cyan-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_produits ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">Référencés</p>
                </div>
                <div class="premium-card p-5 stat-card animate-fade-in-up border-l-4 border-emerald-500" style="animation-delay:0.3s">
                    <div class="flex items-center justify-between mb-2"><span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Stocks actifs</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center"><i class="fas fa-warehouse text-emerald-600 dark:text-emerald-400 text-sm"></i></div>
                    </div>
                    <p class="text-2xl font-bold text-[var(--text-primary)]"><?= $total_stocks_actifs ?></p>
                    <p class="text-xs text-[var(--text-muted)] mt-1">En stock</p>
                </div>
            </div>

            <!-- Ressources serveur -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 animate-fade-in-up" style="animation-delay:0.15s">
                <div class="premium-card p-5">
                    <div class="flex items-center justify-between mb-3"><span class="text-xs font-medium text-[var(--text-secondary)]"><i class="fas fa-hdd mr-1 text-amber-500"></i>Disque</span><span class="text-xs font-bold <?= $espace_disque > 80 ? 'text-red-500' : 'text-amber-500' ?>"><?= $espace_disque ?>%</span></div>
                    <div class="progress-bar">
                        <div class="progress-fill <?= $espace_disque > 80 ? 'bg-red-500' : 'bg-amber-500' ?>" style="width:<?= $espace_disque ?>%"></div>
                    </div>
                    <p class="text-xs text-[var(--text-muted)] mt-2"><?= 100 - $espace_disque ?>% libre</p>
                </div>
                <div class="premium-card p-5">
                    <div class="flex items-center justify-between mb-3"><span class="text-xs font-medium text-[var(--text-secondary)]"><i class="fas fa-microchip mr-1 text-blue-500"></i>CPU</span><span class="text-xs font-bold <?= $cpu_usage > 80 ? 'text-red-500' : 'text-blue-500' ?>"><?= $cpu_usage ?>%</span></div>
                    <div class="progress-bar">
                        <div class="progress-fill <?= $cpu_usage > 80 ? 'bg-red-500' : 'bg-blue-500' ?>" style="width:<?= $cpu_usage ?>%"></div>
                    </div>
                    <p class="text-xs text-[var(--text-muted)] mt-2">4 cœurs • 2.4 GHz</p>
                </div>
                <div class="premium-card p-5">
                    <div class="flex items-center justify-between mb-3"><span class="text-xs font-medium text-[var(--text-secondary)]"><i class="fas fa-memory mr-1 text-purple-500"></i>RAM</span><span class="text-xs font-bold <?= $memoire_usage > 80 ? 'text-red-500' : 'text-purple-500' ?>"><?= $memoire_usage ?>%</span></div>
                    <div class="progress-bar">
                        <div class="progress-fill <?= $memoire_usage > 80 ? 'bg-red-500' : 'bg-purple-500' ?>" style="width:<?= $memoire_usage ?>%"></div>
                    </div>
                    <p class="text-xs text-[var(--text-muted)] mt-2">8 GB total</p>
                </div>
            </div>

            <!-- Logs et Distribution -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Logs récents -->
                <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.2s">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-bold text-[var(--text-primary)]"><i class="fas fa-clipboard-list text-cyan-500 mr-2"></i>Logs système</h2>
                        <a href="logs.php" class="text-xs text-cyan-500 hover:text-cyan-600 font-medium">Voir tout →</a>
                    </div>
                    <?php if ($has_logs): ?>
                        <div class="space-y-2 max-h-80 overflow-y-auto">
                            <?php foreach ($logs_recents as $log):
                                $color = $log['niveau'] == 'ERROR' ? 'badge-error' : ($log['niveau'] == 'WARNING' ? 'badge-warning' : ($log['niveau'] == 'SUCCESS' ? 'badge-success' : 'badge-info'));
                            ?>
                                <div class="p-3 rounded-xl border border-[var(--divider)] hover:bg-white/5 transition-colors">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-sm text-[var(--text-primary)] truncate"><?= htmlspecialchars($log['message'] ?? '') ?></p>
                                            <p class="text-xs text-[var(--text-muted)] mt-0.5"><?= htmlspecialchars($log['module'] ?? 'Système') ?> • <?= date('H:i', strtotime($log['date_log'] ?? 'now')) ?></p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 <?= $color ?>"><?= htmlspecialchars($log['niveau'] ?? 'INFO') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-clipboard-list text-4xl text-[var(--text-muted)] opacity-30 mb-3"></i>
                            <p class="text-[var(--text-secondary)] text-sm">Table <code>logs</code> non configurée</p>
                            <p class="text-xs text-[var(--text-muted)] mt-1">Créez la table pour activer la journalisation</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Distribution rôles -->
                <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.25s">
                    <h2 class="text-base font-bold text-[var(--text-primary)] mb-4"><i class="fas fa-chart-pie text-purple-500 mr-2"></i>Répartition utilisateurs</h2>
                    <?php if (!empty($users_par_role)): ?>
                        <div class="space-y-4">
                            <?php foreach ($users_par_role as $role):
                                $pct = $total_users > 0 ? ($role['count'] / $total_users) * 100 : 0;
                                $icon = $role['role'] == 'PDG' ? 'fa-crown text-amber-500' : ($role['role'] == 'IT' ? 'fa-server text-cyan-500' : 'fa-user text-blue-500');
                            ?>
                                <div>
                                    <div class="flex justify-between mb-1.5">
                                        <span class="text-sm text-[var(--text-secondary)]"><i class="fas <?= $icon ?> mr-1.5"></i><?= $role['role'] ?></span>
                                        <span class="text-sm font-medium text-[var(--text-primary)]"><?= $role['count'] ?> (<?= round($pct) ?>%)</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill bg-gradient-to-r from-cyan-500 to-blue-500" style="width:<?= $pct ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center py-8 text-[var(--text-muted)]">Aucune donnée</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Sauvegardes -->
            <div class="premium-card p-5 animate-fade-in-up" style="animation-delay:0.3s">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-bold text-[var(--text-primary)]"><i class="fas fa-database text-emerald-500 mr-2"></i>Sauvegardes</h2>
                    <a href="backups.php" class="text-xs text-cyan-500 hover:text-cyan-600 font-medium">Gérer →</a>
                </div>
                <?php if ($has_backups): ?>
                    <div class="space-y-2">
                        <?php foreach ($sauvegardes as $backup):
                            $status = $backup['statut'] == 'COMPLETED' ? 'badge-success' : ($backup['statut'] == 'FAILED' ? 'badge-error' : 'badge-warning');
                        ?>
                            <div class="flex items-center justify-between p-3 rounded-xl bg-[var(--input-bg)] border border-[var(--input-border)]">
                                <div>
                                    <p class="text-sm font-medium text-[var(--text-primary)]"><?= htmlspecialchars($backup['nom'] ?? 'Sauvegarde') ?></p>
                                    <p class="text-xs text-[var(--text-muted)]"><?= date('d/m/Y H:i', strtotime($backup['date_backup'] ?? 'now')) ?> • <?= round(($backup['taille'] ?? 0) / 1024 / 1024, 2) ?> MB</p>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $status ?>"><?= htmlspecialchars($backup['statut'] ?? 'PENDING') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-database text-4xl text-[var(--text-muted)] opacity-30 mb-3"></i>
                        <p class="text-[var(--text-secondary)] text-sm">Table <code>backups</code> non configurée</p>
                    </div>
                <?php endif; ?>
                <div class="mt-4 pt-4 border-t border-[var(--divider)]">
                    <button onclick="runBackup()" class="w-full py-2.5 btn-cyan rounded-xl text-sm"><i class="fas fa-plus mr-1.5"></i>Lancer une sauvegarde</button>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-fade-in-up" style="animation-delay:0.35s">
                <a href="utilisateurs.php" class="premium-card p-5 text-center hover:border-cyan-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-user-plus text-blue-600 dark:text-blue-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Utilisateurs</span>
                </a>
                <a href="logs.php" class="premium-card p-5 text-center hover:border-purple-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-search text-purple-600 dark:text-purple-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Audit logs</span>
                </a>
                <a href="maintenance.php" class="premium-card p-5 text-center hover:border-amber-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-tools text-amber-600 dark:text-amber-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Maintenance</span>
                </a>
                <a href="parametres_systeme.php" class="premium-card p-5 text-center hover:border-emerald-500/50 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform"><i class="fas fa-sliders-h text-emerald-600 dark:text-emerald-400"></i></div>
                    <span class="text-sm font-medium text-[var(--text-primary)]">Paramètres</span>
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

        // Backup
        function runBackup() {
            if (confirm('Lancer une sauvegarde manuelle ?')) {
                const btn = event.target.closest('button');
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Lancement...';
                setTimeout(() => {
                    alert('Sauvegarde lancée !');
                    btn.innerHTML = orig;
                    btn.disabled = false
                }, 2000);
            }
        }

        // Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) toggleSidebar()
        });
    </script>

    <?php unset($_SESSION['msg']); ?>
</body>

</html>