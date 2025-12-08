<!-- Barre latérale -->
<div id="sidebar" class="sidebar text-white w-64 min-h-screen fixed shadow-xl">
    <div class="p-4">
        <!-- En-tête de la barre latérale -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Principal</h2>
            <ul class="mt-2 sidebar-section">
                <li>
                    <a href="dashboard.php" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Tableau de bord
                    </a>
                </li>
            </ul>
        </div>

        <!-- Section Interface -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Gestion</h2>
            <ul class="mt-2 sidebar-section">
                <li class="mb-1">
                    <a href="boutiques.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-store mr-3"></i>
                            Boutiques
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">14</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="membres.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-users mr-3"></i>
                            Locataires
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">8</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="affectations.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-link mr-3"></i>
                            Affectations
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">12</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="contrats.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-file-contract mr-3"></i>
                            Contrats
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">12</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="paiements.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-file-invoice-dollar mr-3"></i>
                            Paiements Loyer
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">42</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="charges.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-money-bill-wave mr-3"></i>
                            Charges
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">5</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="alignements.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-euro-sign mr-3"></i>
                            Alignements
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">15</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="paiements_charges.php" class="flex items-center justify-between py-2 px-4 glass-effect rounded-lg hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-credit-card mr-3"></i>
                            Paiements Charges
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full"><?php echo count($paiements); ?></span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="categories.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-tags mr-3"></i>
                            Catégories
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">6</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="utilisateurs.php" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-user-cog mr-3"></i>
                            Utilisateurs
                        </div>
                        <span class="bg-white bg-opacity-20 text-xs px-2 py-1 rounded-full">3</span>
                    </a>
                </li>
                <li class="mb-1">
                    <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <div class="flex items-center">
                            <i class="fas fa-chart-line mr-3"></i>
                            Rapports
                        </div>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Section Addons -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-white text-opacity-80 uppercase tracking-wider">Outils</h2>
            <ul class="mt-2 sidebar-section">
                <li class="mb-1">
                    <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <i class="fas fa-chart-pie mr-3"></i>
                        Statistiques
                    </a>
                </li>
                <li class="mb-1">
                    <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <i class="fas fa-table mr-3"></i>
                        Documents
                    </a>
                </li>
                <li class="mb-1">
                    <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        Calendrier
                    </a>
                </li>
                <li class="mb-1">
                    <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <i class="fas fa-envelope mr-3"></i>
                        Messages
                    </a>
                </li>
                <li class="mb-1">
                    <a href="#" class="flex items-center py-2 px-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-colors duration-200 hover-lift">
                        <i class="fas fa-tasks mr-3"></i>
                        Tâches
                    </a>
                </li>
            </ul>
        </div>

        <!-- Pied de page de la barre latérale -->
        <div class="absolute bottom-0 left-0 right-0 p-4 glass-effect rounded-t-lg">
            <div class="text-sm text-white text-opacity-70">Connecté en tant que :</div>
            <div class="font-semibold">Administrateur</div>
            <div class="text-xs text-white text-opacity-60 mt-1">Dernière connexion : Aujourd'hui</div>
        </div>
    </div>
</div>