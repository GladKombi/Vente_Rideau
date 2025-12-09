<?php
// session_start();
// if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'it') {
//     header('Location: ../login.php');
//     exit;
// }

include '../connexion/connexion.php';

// Récupérer les statistiques IT
try {
    // Nombre d'utilisateurs
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM utilisateurs WHERE statut = 0 AND actif = 1");
    $total_users = $stmt->fetch()['total_users'];

    // Nombre de boutiques
    $stmt = $pdo->query("SELECT COUNT(*) as total_boutiques FROM boutiques WHERE statut = 0 AND actif = 1");
    $total_boutiques = $stmt->fetch()['total_boutiques'];

    // Erreurs système récentes (exemple de table logs)
    $stmt = $pdo->query("SELECT COUNT(*) as erreurs_recentes FROM logs 
                         WHERE niveau = 'ERROR' 
                         AND date_log >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $erreurs_recentes = $stmt->fetch()['erreurs_recentes'] ?? 0;

    // Espace disque (simulation)
    $espace_disque = 85; // pourcentage utilisé

    // Derniers logs
    $stmt = $pdo->query("SELECT * FROM logs 
                         ORDER BY date_log DESC 
                         LIMIT 10");
    $logs_recents = $stmt->fetchAll();

    // Utilisateurs par rôle
    $stmt = $pdo->query("SELECT role, COUNT(*) as count 
                         FROM utilisateurs 
                         WHERE statut = 0 AND actif = 1 
                         GROUP BY role");
    $users_par_role = $stmt->fetchAll();

    // Sauvegardes récentes
    $stmt = $pdo->query("SELECT * FROM backups 
                         ORDER BY date_backup DESC 
                         LIMIT 5");
    $sauvegardes = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Erreur dashboard IT: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Dashboard IT - Julien_Rideau</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1E293B;
            --secondary: #475569;
            --accent: #3B82F6;
            --light: #F8FAFC;
            --dark: #0F172A;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
        }
        
        .font-display {
            font-family: 'Outfit', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #1E293B 0%, #475569 100%);
        }
        
        .gradient-accent {
            background: linear-gradient(90deg, #3B82F6 0%, #60A5FA 100%);
        }
        
        .gradient-text {
            background: linear-gradient(90deg, #3B82F6 0%, #60A5FA 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .card-glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        }
        
        .hover-lift {
            transition: transform 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .stats-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stats-card:hover {
            transform: translateX(5px);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background: #E5E7EB;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .log-error { color: #DC2626; }
        .log-warning { color: #D97706; }
        .log-info { color: #2563EB; }
        .log-success { color: #059669; }
    </style>
</head>

<body class="font-inter min-h-screen bg-gray-50">
    <!-- Navigation Sidebar -->
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 gradient-bg text-white flex flex-col">
            <!-- Logo -->
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center">
                        <span class="font-bold text-white text-lg">JR</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold">Julien_Rideau</h1>
                        <p class="text-xs text-gray-300">Dashboard IT</p>
                    </div>
                </div>
            </div>
            
            <!-- User Info -->
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-blue-500/20 border border-blue-500/30 flex items-center justify-center">
                        <i class="fas fa-server text-blue-500"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold"><?= $_SESSION['user_name'] ?? 'IT Admin' ?></h3>
                        <p class="text-sm text-gray-300"><?= $_SESSION['user_email'] ?? '' ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Menu -->
            <nav class="flex-1 p-4 space-y-2">
                <a href="dashboard_it.php" class="flex items-center space-x-3 p-3 rounded-lg bg-white/10">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="utilisateurs.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-users-cog"></i>
                    <span>Gestion utilisateurs</span>
                </a>
                <a href="logs.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Logs système</span>
                </a>
                <a href="backups.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-database"></i>
                    <span>Sauvegardes</span>
                </a>
                <a href="maintenance.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                </a>
                <a href="parametres_systeme.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5">
                    <i class="fas fa-sliders-h"></i>
                    <span>Paramètres système</span>
                </a>
            </nav>
            
            <!-- Logout -->
            <div class="p-4 border-t border-white/10">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white border-b border-gray-200 p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord IT</h1>
                        <p class="text-gray-600">Surveillance et administration système</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <i class="fas fa-exclamation-triangle text-yellow-500 text-xl"></i>
                            <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                <?= $erreurs_recentes ?>
                            </span>
                        </div>
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-server mr-2"></i>
                            Serveur: Production
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Statistiques système -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Carte 1 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-blue-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-600">Active</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_users ?? 0 ?></h3>
                        <p class="text-gray-600">Utilisateurs actifs</p>
                    </div>
                    
                    <!-- Carte 2 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-store text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Online</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_boutiques ?? 0 ?></h3>
                        <p class="text-gray-600">Boutiques</p>
                    </div>
                    
                    <!-- Carte 3 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-red-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-red-600">Alert</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $erreurs_recentes ?? 0 ?></h3>
                        <p class="text-gray-600">Erreurs (24h)</p>
                    </div>
                    
                    <!-- Carte 4 -->
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-yellow-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-yellow-100 flex items-center justify-center">
                                <i class="fas fa-hdd text-yellow-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium <?= $espace_disque > 80 ? 'text-red-600' : 'text-yellow-600' ?>">
                                <?= $espace_disque ?>%
                            </span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $espace_disque ?>%</h3>
                        <p class="text-gray-600">Espace disque</p>
                        <div class="progress-bar mt-2">
                            <div class="progress-fill <?= $espace_disque > 80 ? 'bg-red-500' : 'bg-yellow-500' ?>" 
                                 style="width: <?= $espace_disque ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Logs système et sauvegardes -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Logs récents -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Logs système récents</h2>
                            <a href="logs.php" class="text-sm text-accent hover:text-blue-700">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($logs_recents as $log): ?>
                            <div class="p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium <?= 'log-' . strtolower($log['niveau'] ?? 'info') ?>">
                                            <i class="fas fa-<?= 
                                                $log['niveau'] == 'ERROR' ? 'exclamation-circle' : 
                                                ($log['niveau'] == 'WARNING' ? 'exclamation-triangle' : 
                                                ($log['niveau'] == 'SUCCESS' ? 'check-circle' : 'info-circle')) 
                                            ?> mr-2"></i>
                                            <?= htmlspecialchars($log['message'] ?? '') ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?= htmlspecialchars($log['module'] ?? 'Système') ?> • 
                                            <?= date('H:i:s', strtotime($log['date_log'] ?? 'now')) ?>
                                        </p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full 
                                        <?= $log['niveau'] == 'ERROR' ? 'bg-red-100 text-red-800' : 
                                           ($log['niveau'] == 'WARNING' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($log['niveau'] == 'SUCCESS' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800')) ?>">
                                        <?= htmlspecialchars($log['niveau'] ?? 'INFO') ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Sauvegardes -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-bold text-gray-900">Sauvegardes récentes</h2>
                            <a href="backups.php" class="text-sm text-accent hover:text-blue-700">
                                Gérer <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <div class="space-y-4">
                            <?php foreach ($sauvegardes as $backup): ?>
                            <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 hover:bg-gray-100">
                                <div>
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($backup['nom'] ?? 'Sauvegarde') ?></h4>
                                    <p class="text-sm text-gray-500">
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($backup['date_backup'] ?? 'now')) ?>
                                    </p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs px-2 py-1 rounded-full 
                                        <?= $backup['statut'] == 'COMPLETED' ? 'bg-green-100 text-green-800' : 
                                           ($backup['statut'] == 'FAILED' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                        <?= htmlspecialchars($backup['statut'] ?? 'IN PROGRESS') ?>
                                    </span>
                                    <span class="text-sm text-gray-600">
                                        <?= round($backup['taille'] / 1024 / 1024, 2) ?> MB
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <button onclick="runBackup()" class="w-full py-3 px-4 bg-accent text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Lancer une sauvegarde manuelle
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Utilisateurs et distribution -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Distribution des rôles -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-6">Distribution des rôles</h2>
                        <div class="space-y-4">
                            <?php foreach ($users_par_role as $role): ?>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-700">
                                        <?= $role['role'] == 'PDG' ? 'PDG' : 
                                           ($role['role'] == 'IT' ? 'IT' : 'Autre') ?>
                                    </span>
                                    <span class="text-sm font-medium text-gray-900"><?= $role['count'] ?> utilisateurs</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill bg-accent" 
                                         style="width: <?= ($role['count'] / $total_users) * 100 ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Actions rapides IT -->
                    <div class="bg-white rounded-2xl shadow-soft p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-6">Actions rapides</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <a href="nouvel_utilisateur.php" class="p-4 rounded-xl border border-gray-200 hover:border-accent hover:bg-blue-50 transition-all text-center">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-user-plus text-blue-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Nouvel utilisateur</span>
                            </a>
                            
                            <a href="logs.php" class="p-4 rounded-xl border border-gray-200 hover:border-purple-500 hover:bg-purple-50 transition-all text-center">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-search text-purple-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Audit logs</span>
                            </a>
                            
                            <a href="maintenance.php" class="p-4 rounded-xl border border-gray-200 hover:border-yellow-500 hover:bg-yellow-50 transition-all text-center">
                                <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-tools text-yellow-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Maintenance</span>
                            </a>
                            
                            <a href="parametres_systeme.php" class="p-4 rounded-xl border border-gray-200 hover:border-green-500 hover:bg-green-50 transition-all text-center">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-sliders-h text-green-600"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Paramètres</span>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Animation des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Fonction pour lancer une sauvegarde
        function runBackup() {
            if (confirm('Êtes-vous sûr de vouloir lancer une sauvegarde manuelle ?')) {
                // Afficher un indicateur de chargement
                const btn = event.target.closest('button');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Lancement...';
                btn.disabled = true;
                
                // Simuler une requête AJAX
                setTimeout(() => {
                    alert('Sauvegarde lancée avec succès !');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    // Rafraîchir la page pour voir la nouvelle sauvegarde
                    location.reload();
                }, 2000);
            }
        }

        // Mise à jour automatique des logs (toutes les 30 secondes)
        function refreshLogs() {
            // Simuler un rafraîchissement des logs
            console.log('Logs rafraîchis');
        }
        
        setInterval(refreshLogs, 30000);
    </script>
</body>
</html>