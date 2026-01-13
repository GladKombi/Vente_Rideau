<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>NGS Dashboard - Gestion Vente & Stockage</title>
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <style>
    :root {
      --primary: #1a5fb4;
      --secondary: #26a269;
      --accent: #c64600;
    }
    
    .sidebar {
      transition: all 0.3s ease;
    }
    
    .sidebar.collapsed {
      width: 70px;
    }
    
    .sidebar.collapsed .sidebar-text {
      display: none;
    }
    
    .main-content {
      transition: margin-left 0.3s ease;
    }
    
    .sidebar.collapsed ~ .main-content {
      margin-left: 70px;
    }
    
    .stat-card {
      border-left: 4px solid;
    }
    
    .stat-card.sales {
      border-left-color: #1a5fb4;
    }
    
    .stat-card.stock {
      border-left-color: #26a269;
    }
    
    .stat-card.alert {
      border-left-color: #c64600;
    }
    
    .stat-card.transfer {
      border-left-color: #9141ac;
    }
    
    .badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .badge.success {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .badge.warning {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .badge.danger {
      background-color: #fee2e2;
      color: #991b1b;
    }
    
    .badge.info {
      background-color: #dbeafe;
      color: #1e40af;
    }
    
    .notification-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: #ef4444;
      position: absolute;
      top: 8px;
      right: 8px;
    }
    
    .progress-bar {
      height: 6px;
      border-radius: 3px;
      overflow: hidden;
      background-color: #e5e7eb;
    }
    
    .progress-fill {
      height: 100%;
      border-radius: 3px;
    }
    
    .hover-lift {
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .hover-lift:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in {
      animation: fadeIn 0.5s ease-out forwards;
    }
  </style>
</head>

<body class="bg-gray-50 text-gray-800">
  <!-- Top Bar -->
  <div class="bg-gradient-to-r from-blue-600 to-green-600 text-white py-2 px-4">
    <div class="container mx-auto flex justify-between items-center">
      <div class="flex items-center space-x-4">
        <i class="fas fa-envelope text-sm"></i>
        <span class="text-sm">newgraceservice@gmail.com</span>
        <i class="fas fa-phone ml-4 text-sm"></i>
        <span class="text-sm">+243 977 421 421</span>
      </div>
      <div class="hidden md:flex space-x-4">
        <a href="#" class="hover:text-blue-200 transition"><i class="fab fa-whatsapp"></i></a>
        <a href="#" class="hover:text-blue-200 transition"><i class="fab fa-facebook"></i></a>
        <a href="#" class="hover:text-blue-200 transition"><i class="fab fa-instagram"></i></a>
        <a href="#" class="hover:text-blue-200 transition"><i class="fab fa-linkedin"></i></a>
      </div>
    </div>
  </div>

  <!-- Header -->
  <header class="bg-white shadow-sm sticky top-0 z-40">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-blue-600 to-green-600 flex items-center justify-center">
          <span class="font-bold text-white text-lg">NGS</span>
        </div>
        <div>
          <h1 class="text-xl font-bold">New Grace Service</h1>
          <p class="text-sm text-gray-500">Dashboard de Gestion</p>
        </div>
      </div>
      
      <div class="flex items-center space-x-4">
        <button id="sidebar-toggle" class="md:hidden text-gray-600 hover:text-gray-900">
          <i class="fas fa-bars text-xl"></i>
        </button>
        <div class="relative hidden md:block">
          <input type="text" placeholder="Rechercher..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg w-64 focus:outline-none focus:border-blue-500">
          <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>
        <div class="relative">
          <button class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 relative">
            <i class="fas fa-bell text-gray-600"></i>
            <div class="notification-dot"></div>
          </button>
        </div>
        <div class="hidden md:flex items-center space-x-3">
          <div class="w-9 h-9 rounded-full bg-gradient-to-r from-blue-500 to-green-500 flex items-center justify-center">
            <span class="text-white font-bold text-sm">PDG</span>
          </div>
          <div>
            <p class="text-sm font-medium">Admin PDG</p>
            <p class="text-xs text-gray-500">Administrateur</p>
          </div>
        </div>
      </div>
    </div>
  </header>

  <div class="flex">
    <!-- Sidebar -->
    <div class="sidebar fixed h-full bg-white border-r border-gray-200 z-30 w-64 hidden md:block" id="desktop-sidebar">
      <div class="p-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="font-bold text-lg">Navigation</h2>
        </div>
      </div>
      
      <div class="p-4">
        <!-- Menu principal -->
        <div class="mb-6">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">PRINCIPAL</h3>
          <nav class="space-y-1">
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600">
              <i class="fas fa-home"></i>
              <span class="sidebar-text">Tableau de bord</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-chart-bar"></i>
              <span class="sidebar-text">Analytiques</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-shopping-cart"></i>
              <span class="sidebar-text">Ventes</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-boxes"></i>
              <span class="sidebar-text">Stock</span>
            </a>
          </nav>
        </div>
        
        <!-- Gestion -->
        <div class="mb-6">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">GESTION</h3>
          <nav class="space-y-1">
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-store"></i>
              <span class="sidebar-text">Boutiques</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-box"></i>
              <span class="sidebar-text">Produits</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-exchange-alt"></i>
              <span class="sidebar-text">Transferts</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-cash-register"></i>
              <span class="sidebar-text">Mouvement Caisse</span>
            </a>
          </nav>
        </div>
        
        <!-- Administration -->
        <div class="mb-6">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">ADMINISTRATION</h3>
          <nav class="space-y-1">
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-users"></i>
              <span class="sidebar-text">Utilisateurs</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-cog"></i>
              <span class="sidebar-text">Paramètres</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-file-alt"></i>
              <span class="sidebar-text">Rapports</span>
            </a>
          </nav>
        </div>
        
        <!-- Boutique sélectionnée -->
        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium">Boutique active</span>
            <i class="fas fa-store text-gray-400"></i>
          </div>
          <select class="w-full p-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:border-blue-500">
            <option>Butembo | Rawbank (Principal)</option>
            <option>Butembo Centre</option>
            <option>Beni | Boulevard Nyamwisi</option>
            <option>Bunia | Rue Ituri</option>
          </select>
        </div>
      </div>
      
      <!-- Déconnexion -->
      <div class="absolute bottom-0 w-full p-4 border-t border-gray-200">
        <a href="logout.php" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
          <i class="fas fa-sign-out-alt"></i>
          <span class="sidebar-text">Déconnexion</span>
        </a>
      </div>
    </div>

    <!-- Mobile Sidebar -->
    <div id="mobile-sidebar" class="fixed inset-0 z-40 bg-black bg-opacity-50 hidden">
      <div class="sidebar bg-white h-full w-64 transform transition-transform">
        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
          <h2 class="font-bold text-lg">Menu</h2>
          <button id="close-mobile-sidebar" class="text-gray-600 hover:text-gray-900">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="p-4 overflow-y-auto h-[calc(100%-80px)]">
          <!-- Menu content same as desktop -->
          <nav class="space-y-1">
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-50 text-blue-600">
              <i class="fas fa-home"></i>
              <span>Tableau de bord</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-chart-bar"></i>
              <span>Analytiques</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-shopping-cart"></i>
              <span>Ventes</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-boxes"></i>
              <span>Stock</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-store"></i>
              <span>Boutiques</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-box"></i>
              <span>Produits</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-exchange-alt"></i>
              <span>Transferts</span>
            </a>
            <a href="#" class="flex items-center space-x-3 p-3 rounded-lg text-gray-600 hover:bg-gray-50">
              <i class="fas fa-users"></i>
              <span>Utilisateurs</span>
            </a>
          </nav>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="main-content flex-1 md:ml-64">
      <!-- Hero Stats Section -->
      <section class="py-6 px-4 md:px-8">
        <div class="container mx-auto">
          <h2 class="text-2xl font-bold mb-6">Tableau de bord</h2>
          
          <!-- Stats Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 stat-card sales hover-lift">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <p class="text-gray-500 text-sm">Ventes aujourd'hui</p>
                  <h3 class="text-2xl font-bold">2.845.000 FC</h3>
                </div>
                <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                  <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                </div>
              </div>
              <div class="flex items-center text-sm">
                <span class="text-green-600 flex items-center">
                  <i class="fas fa-arrow-up mr-1"></i> 12.5%
                </span>
                <span class="text-gray-500 ml-2">vs hier</span>
              </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-6 stat-card stock hover-lift">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <p class="text-gray-500 text-sm">Stock total</p>
                  <h3 class="text-2xl font-bold">1.248</h3>
                </div>
                <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                  <i class="fas fa-boxes text-green-600 text-xl"></i>
                </div>
              </div>
              <p class="text-sm text-gray-500">Produits en inventaire</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-6 stat-card alert hover-lift">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <p class="text-gray-500 text-sm">Alertes stock bas</p>
                  <h3 class="text-2xl font-bold">18</h3>
                </div>
                <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center">
                  <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                </div>
              </div>
              <p class="text-sm text-gray-500">Produits à réapprovisionner</p>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-6 stat-card transfer hover-lift">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <p class="text-gray-500 text-sm">Transferts en attente</p>
                  <h3 class="text-2xl font-bold">7</h3>
                </div>
                <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center">
                  <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                </div>
              </div>
              <p class="text-sm text-gray-500">Entre boutiques</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Charts & Tables Section -->
      <section class="py-6 px-4 md:px-8 bg-gray-50">
        <div class="container mx-auto">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Graphique des ventes -->
            <div class="bg-white rounded-xl shadow-sm p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-lg">Ventes des 7 derniers jours</h3>
                <select class="text-sm border border-gray-300 rounded-lg px-3 py-1 focus:outline-none focus:border-blue-500">
                  <option>Cette semaine</option>
                  <option>Ce mois</option>
                  <option>Cette année</option>
                </select>
              </div>
              <div class="h-64">
                <canvas id="salesChart"></canvas>
              </div>
            </div>
            
            <!-- Top produits -->
            <div class="bg-white rounded-xl shadow-sm p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-lg">Top 5 des produits</h3>
                <a href="#" class="text-blue-600 text-sm font-medium">Voir tout</a>
              </div>
              <div class="space-y-4">
                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                      <i class="fas fa-tshirt text-blue-600"></i>
                    </div>
                    <div>
                      <p class="font-medium">Rideau Lin Premium</p>
                      <p class="text-sm text-gray-500">MAT-2024-LIN001</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="font-bold">142 ventes</p>
                    <p class="text-sm text-green-600">+24%</p>
                  </div>
                </div>
                
                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                      <i class="fas fa-sun text-green-600"></i>
                    </div>
                    <div>
                      <p class="font-medium">Voilage Ciel</p>
                      <p class="text-sm text-gray-500">MAT-2024-VOI003</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="font-bold">98 ventes</p>
                    <p class="text-sm text-green-600">+18%</p>
                  </div>
                </div>
                
                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
                  <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                      <i class="fas fa-border-all text-purple-600"></i>
                    </div>
                    <div>
                      <p class="font-medium">Store Japonais</p>
                      <p class="text-sm text-gray-500">MAT-2024-STO002</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="font-bold">76 ventes</p>
                    <p class="text-sm text-green-600">+12%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Tables détaillées -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Dernières ventes -->
            <div class="bg-white rounded-xl shadow-sm p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-lg">Dernières ventes</h3>
                <a href="#" class="text-blue-600 text-sm font-medium">Voir tout</a>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr class="text-left text-gray-500 text-sm border-b">
                      <th class="pb-3">N° Facture</th>
                      <th class="pb-3">Client</th>
                      <th class="pb-3">Montant</th>
                      <th class="pb-3">Statut</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="border-b hover:bg-gray-50">
                      <td class="py-4">
                        <p class="font-medium">FAC-2024-00128</p>
                        <p class="text-sm text-gray-500">Aujourd'hui, 10:15</p>
                      </td>
                      <td class="py-4">Hôtel Victoria</td>
                      <td class="py-4 font-bold">850.000 FC</td>
                      <td class="py-4">
                        <span class="badge success">Payée</span>
                      </td>
                    </tr>
                    <tr class="border-b hover:bg-gray-50">
                      <td class="py-4">
                        <p class="font-medium">FAC-2024-00127</p>
                        <p class="text-sm text-gray-500">Hier, 16:30</p>
                      </td>
                      <td class="py-4">M. Kalume Jean</td>
                      <td class="py-4 font-bold">420.000 FC</td>
                      <td class="py-4">
                        <span class="badge success">Payée</span>
                      </td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                      <td class="py-4">
                        <p class="font-medium">FAC-2024-00126</p>
                        <p class="text-sm text-gray-500">Hier, 14:20</p>
                      </td>
                      <td class="py-4">Restaurant Le Bon Goût</td>
                      <td class="py-4 font-bold">1.250.000 FC</td>
                      <td class="py-4">
                        <span class="badge warning">Brouillon</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            
            <!-- Alertes stock -->
            <div class="bg-white rounded-xl shadow-sm p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-lg">Alertes stock bas</h3>
                <a href="#" class="text-blue-600 text-sm font-medium">Réapprovisionner</a>
              </div>
              <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg">
                  <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center">
                      <i class="fas fa-exclamation text-red-600"></i>
                    </div>
                    <div>
                      <p class="font-medium">Rideau Velours Royal</p>
                      <p class="text-sm text-gray-500">Stock: 3 pcs</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-sm text-gray-500">Seuil: 10 pcs</p>
                    <div class="progress-bar w-24 mt-1">
                      <div class="progress-fill bg-red-500" style="width: 30%"></div>
                    </div>
                  </div>
                </div>
                
                <div class="flex items-center justify-between p-4 bg-orange-50 rounded-lg">
                  <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-lg bg-orange-100 flex items-center justify-center">
                      <i class="fas fa-exclamation text-orange-600"></i>
                    </div>
                    <div>
                      <p class="font-medium">Tissu Organza Blanc</p>
                      <p class="text-sm text-gray-500">Stock: 8 mètres</p>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-sm text-gray-500">Seuil: 15 mètres</p>
                    <div class="progress-bar w-24 mt-1">
                      <div class="progress-fill bg-orange-500" style="width: 53%"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Quick Actions Section -->
      <section class="py-8 px-4 md:px-8">
        <div class="container mx-auto">
          <h3 class="font-bold text-lg mb-6">Actions rapides</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="#" class="bg-white rounded-xl shadow-sm p-6 text-center hover-lift">
              <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-plus text-blue-600 text-xl"></i>
              </div>
              <p class="font-medium">Nouvelle vente</p>
            </a>
            
            <a href="#" class="bg-white rounded-xl shadow-sm p-6 text-center hover-lift">
              <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-truck text-green-600 text-xl"></i>
              </div>
              <p class="font-medium">Réapprovisionner</p>
            </a>
            
            <a href="#" class="bg-white rounded-xl shadow-sm p-6 text-center hover-lift">
              <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
              </div>
              <p class="font-medium">Transfert stock</p>
            </a>
            
            <a href="#" class="bg-white rounded-xl shadow-sm p-6 text-center hover-lift">
              <div class="w-12 h-12 rounded-lg bg-orange-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-file-invoice-dollar text-orange-600 text-xl"></i>
              </div>
              <p class="font-medium">Générer rapport</p>
            </a>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <footer class="bg-gray-800 text-white py-8 px-4 md:px-8">
        <div class="container mx-auto">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
              <div class="flex items-center space-x-3 mb-4">
                <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-blue-600 to-green-600 flex items-center justify-center">
                  <span class="font-bold text-white text-lg">NGS</span>
                </div>
                <div>
                  <h2 class="font-bold text-xl">New Grace Service</h2>
                  <p class="text-gray-400 text-sm">Excellence depuis Bbo</p>
                </div>
              </div>
              <p class="text-gray-400 text-sm">
                Créateurs d'ambiances lumineuses et élégantes à travers des rideaux sur mesure d'exception.
              </p>
            </div>
            
            <div>
              <h4 class="font-bold text-lg mb-4">Liens rapides</h4>
              <ul class="space-y-2">
                <li><a href="#" class="text-gray-400 hover:text-white transition">Tableau de bord</a></li>
                <li><a href="#" class="text-gray-400 hover:text-white transition">Ventes</a></li>
                <li><a href="#" class="text-gray-400 hover:text-white transition">Stock</a></li>
                <li><a href="#" class="text-gray-400 hover:text-white transition">Boutiques</a></li>
              </ul>
            </div>
            
            <div>
              <h4 class="font-bold text-lg mb-4">Services</h4>
              <ul class="space-y-2">
                <li><a href="#" class="text-gray-400 hover:text-white transition">Conception sur mesure</a></li>
                <li><a href="#" class="text-gray-400 hover:text-white transition">Gestion stock</a></li>
                <li><a href="#" class="text-gray-400 hover:text-white transition">Transferts</a></li>
                <li><a href="#" class="text-gray-400 hover:text-white transition">Rapports</a></li>
              </ul>
            </div>
            
            <div>
              <h4 class="font-bold text-lg mb-4">Contact</h4>
              <div class="space-y-2 text-gray-400">
                <p class="flex items-center">
                  <i class="fas fa-phone mr-2"></i>
                  +243 977 421 421
                </p>
                <p class="flex items-center">
                  <i class="fas fa-envelope mr-2"></i>
                  newgraceservice@gmail.com
                </p>
                <p class="flex items-center">
                  <i class="fas fa-map-marker-alt mr-2"></i>
                  Butembo, RDC
                </p>
              </div>
            </div>
          </div>
          
          <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400 text-sm">
            <p>© 2024 New Grace Service. Tous droits réservés.</p>
            <p class="mt-2">Logiciel de gestion vente et stockage de rideaux</p>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script>
    // Mobile sidebar toggle
    document.getElementById('sidebar-toggle').addEventListener('click', function() {
      document.getElementById('mobile-sidebar').classList.remove('hidden');
    });
    
    document.getElementById('close-mobile-sidebar').addEventListener('click', function() {
      document.getElementById('mobile-sidebar').classList.add('hidden');
    });
    
    // Close sidebar when clicking outside
    document.getElementById('mobile-sidebar').addEventListener('click', function(e) {
      if (e.target.id === 'mobile-sidebar') {
        this.classList.add('hidden');
      }
    });
    
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(salesCtx, {
      type: 'line',
      data: {
        labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
        datasets: [{
          label: 'Ventes (en milliers FC)',
          data: [120, 190, 300, 500, 200, 300, 450],
          borderColor: '#1a5fb4',
          backgroundColor: 'rgba(26, 95, 180, 0.1)',
          borderWidth: 2,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return value + 'k';
              }
            }
          }
        }
      }
    });
    
    // Notification click
    document.querySelector('.notification-dot').parentElement.addEventListener('click', function() {
      alert('Vous avez 5 nouvelles notifications:\n- 3 alertes stock bas\n- 2 transferts en attente');
    });
    
    // Animate cards on load
    document.addEventListener('DOMContentLoaded', function() {
      const cards = document.querySelectorAll('.hover-lift');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('animate-fade-in');
      });
    });
    
    // Update date and time
    function updateDateTime() {
      const now = new Date();
      const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
      const dateElement = document.createElement('div');
      dateElement.className = 'text-sm text-gray-600 hidden md:block';
      dateElement.textContent = now.toLocaleDateString('fr-FR', options) + ' • ' + now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
      
      const existingDate = document.querySelector('.date-time');
      if (existingDate) {
        existingDate.remove();
      }
      
      const header = document.querySelector('header .container');
      header.appendChild(dateElement);
      dateElement.classList.add('date-time');
    }
    
    updateDateTime();
    setInterval(updateDateTime, 60000);
  </script>
</body>
</html>