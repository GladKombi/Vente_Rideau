
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Gestion de loyer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            transition: all 0.3s;
        }
        .sidebar.collapsed {
            margin-left: -16rem;
        }
        .main-content {
            transition: all 0.3s;
        }
        .main-content.expanded {
            margin-left: 0;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Barre de navigation supérieure -->
    <nav class="bg-gray-800 text-white fixed w-full z-10">
        <div class="flex items-center justify-between p-4">
            <!-- Logo et nom de l'application -->
            <div class="flex items-center">
                <button id="sidebarToggle" class="mr-4 text-gray-300 hover:text-white">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="text-xl font-bold">La veranda</h1>
            </div>

            <!-- Barre de recherche -->
            <div class="hidden md:flex items-center">
                <div class="relative">
                    <input type="text" placeholder="Rechercher..." class="bg-gray-700 text-white rounded-lg py-2 px-4 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </div>

            <!-- Menu utilisateur -->
            <div class="relative">
                <button id="userMenuButton" class="flex items-center text-gray-300 hover:text-white focus:outline-none">
                    <i class="fas fa-user-circle text-xl"></i>
                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                </button>
                <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 text-gray-700">
                    <a href="#" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-cog mr-2"></i>Paramètres</a>
                    <a href="#" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-history mr-2"></i>Journal d'activité</a>
                    <div class="border-t my-1"></div>
                    <a href="#" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-sign-out-alt mr-2"></i>Déconnexion</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16">
        <!-- Barre latérale -->
        <div id="sidebar" class="sidebar bg-gray-800 text-white w-64 min-h-screen fixed">
            <div class="p-4">
                <!-- En-tête de la barre latérale -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-400 uppercase tracking-wider">Principal</h2>
                    <ul class="mt-2">
                        <li>
                            <a href="#" class="flex items-center py-2 px-4 bg-blue-700 rounded-lg">
                                <i class="fas fa-tachometer-alt mr-3"></i>
                                Tableau de bord
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Section Interface -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold text-gray-400 uppercase tracking-wider">Gestion</h2>
                    <ul class="mt-2">
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-home mr-3"></i>
                                    Biens immobiliers
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-users mr-3"></i>
                                    Locataires
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-file-invoice-dollar mr-3"></i>
                                    Paiements
                                </div>
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center justify-between py-2 px-4 hover:bg-gray-700 rounded-lg">
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
                    <h2 class="text-lg font-semibold text-gray-400 uppercase tracking-wider">Outils</h2>
                    <ul class="mt-2">
                        <li class="mb-1">
                            <a href="#" class="flex items-center py-2 px-4 hover:bg-gray-700 rounded-lg">
                                <i class="fas fa-chart-pie mr-3"></i>
                                Statistiques
                            </a>
                        </li>
                        <li class="mb-1">
                            <a href="#" class="flex items-center py-2 px-4 hover:bg-gray-700 rounded-lg">
                                <i class="fas fa-table mr-3"></i>
                                Documents
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Pied de page de la barre latérale -->
                <div class="absolute bottom-0 left-0 right-0 p-4 bg-gray-900">
                    <div class="text-sm text-gray-400">Connecté en tant que :</div>
                    <div class="font-semibold">Propriétaire</div>
                </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div id="mainContent" class="main-content ml-64 p-6 w-full">
            <!-- En-tête et fil d'Ariane -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Tableau de bord</h1>
                <div class="flex items-center text-sm text-gray-600 mt-1">
                    <span>Tableau de bord</span>
                </div>
            </div>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <!-- Carte Paiements en attente -->
                <div class="bg-blue-500 text-white rounded-lg shadow">
                    <div class="p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium">Paiements en attente</p>
                                <p class="text-2xl font-bold mt-1">1 250 €</p>
                            </div>
                            <div class="bg-blue-600 p-3 rounded-lg">
                                <i class="fas fa-clock text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-600 px-4 py-3 rounded-b-lg">
                        <a href="#" class="flex items-center justify-between text-sm font-medium">
                            <span>Voir les détails</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Carte Retards de paiement -->
                <div class="bg-yellow-500 text-white rounded-lg shadow">
                    <div class="p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium">Retards de paiement</p>
                                <p class="text-2xl font-bold mt-1">750 €</p>
                            </div>
                            <div class="bg-yellow-600 p-3 rounded-lg">
                                <i class="fas fa-exclamation-triangle text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-yellow-600 px-4 py-3 rounded-b-lg">
                        <a href="#" class="flex items-center justify-between text-sm font-medium">
                            <span>Voir les détails</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Carte Paiements effectués -->
                <div class="bg-green-500 text-white rounded-lg shadow">
                    <div class="p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium">Paiements effectués</p>
                                <p class="text-2xl font-bold mt-1">5 600 €</p>
                            </div>
                            <div class="bg-green-600 p-3 rounded-lg">
                                <i class="fas fa-check-circle text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-600 px-4 py-3 rounded-b-lg">
                        <a href="#" class="flex items-center justify-between text-sm font-medium">
                            <span>Voir les détails</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Carte Dépenses -->
                <div class="bg-red-500 text-white rounded-lg shadow">
                    <div class="p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-sm font-medium">Dépenses ce mois</p>
                                <p class="text-2xl font-bold mt-1">1 850 €</p>
                            </div>
                            <div class="bg-red-600 p-3 rounded-lg">
                                <i class="fas fa-chart-line text-lg"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-red-600 px-4 py-3 rounded-b-lg">
                        <a href="#" class="flex items-center justify-between text-sm font-medium">
                            <span>Voir les détails</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Graphique des revenus -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-chart-area mr-2 text-blue-500"></i>
                            Évolution des revenus
                        </h2>
                    </div>
                    <div class="h-80">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Graphique des paiements -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-chart-bar mr-2 text-blue-500"></i>
                            Statut des paiements
                        </h2>
                    </div>
                    <div class="h-80">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tableau des derniers paiements -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-semibold text-gray-800">
                        <i class="fas fa-table mr-2 text-blue-500"></i>
                        Derniers paiements
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Locataire</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adresse</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">Martin Dubois</div>
                                            <div class="text-sm text-gray-500">Appartement B2</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">15 Rue de la Paix, Paris</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">750 €</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">15/10/2023</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Payé
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">Sophie Lambert</div>
                                            <div class="text-sm text-gray-500">Appartement A1</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">22 Avenue des Champs, Lyon</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">650 €</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">12/10/2023</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Payé
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">Thomas Moreau</div>
                                            <div class="text-sm text-gray-500">Studio C3</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">8 Rue du Commerce, Marseille</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">500 €</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">10/10/2023</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        En attente
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">Laura Petit</div>
                                            <div class="text-sm text-gray-500">Appartement D4</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">5 Boulevard Voltaire, Toulouse</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">820 €</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">05/10/2023</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        En retard
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">Pierre Lefevre</div>
                                            <div class="text-sm text-gray-500">Appartement E5</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">12 Rue de la République, Lille</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">700 €</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">01/10/2023</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Payé
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pied de page -->
            <footer class="py-4 bg-white mt-6 rounded-lg shadow">
                <div class="container mx-auto px-4">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="text-gray-600 text-sm mb-2 md:mb-0">
                            Copyright &copy; GestionLoyer 2023
                        </div>
                        <div class="flex space-x-4 text-sm">
                            <a href="#" class="text-gray-600 hover:text-gray-900">Politique de confidentialité</a>
                            <span class="text-gray-400">•</span>
                            <a href="#" class="text-gray-600 hover:text-gray-900">Conditions d'utilisation</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Toggle de la barre latérale
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('ml-64');
            mainContent.classList.toggle('ml-0');
        });

        // Toggle du menu utilisateur
        document.getElementById('userMenuButton').addEventListener('click', function() {
            const userMenu = document.getElementById('userMenu');
            userMenu.classList.toggle('hidden');
        });

        // Fermer le menu utilisateur en cliquant ailleurs
        document.addEventListener('click', function(event) {
            const userMenuButton = document.getElementById('userMenuButton');
            const userMenu = document.getElementById('userMenu');
            
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });

        // Graphique des revenus
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Revenus mensuels (€)',
                    data: [4500, 5200, 4800, 6100, 5800, 7000, 7500, 7200, 6800, 6500, 5900, 6300],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + ' €';
                            }
                        }
                    }
                }
            }
        });

        // Graphique des paiements
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: ['Payés', 'En attente', 'En retard'],
                datasets: [{
                    data: [75, 15, 10],
                    backgroundColor: [
                        'rgb(34, 197, 94)',
                        'rgb(251, 191, 36)',
                        'rgb(239, 68, 68)'
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>