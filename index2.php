<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>NGS - New Grace Service | Excellence en Rideaux sur Mesure</title>
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
      --primary: #1e3a8a;
      --secondary: #3b82f6;
      --accent: #60a5fa;
    }
    
    .hero-gradient {
      background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
      transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(30, 58, 138, 0.3);
    }
    
    .card-hover {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .card-hover:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-fade-in {
      animation: fadeIn 0.8s ease-out forwards;
    }
    
    .stat-card {
      position: relative;
      overflow: hidden;
    }
    
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, rgba(30, 58, 138, 0.1), rgba(59, 130, 246, 0.1));
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .stat-card:hover::before {
      opacity: 1;
    }
    
    .section-title {
      position: relative;
      padding-bottom: 1rem;
    }
    
    .section-title::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 3px;
      background: linear-gradient(to right, #1e3a8a, #3b82f6);
      border-radius: 2px;
    }
    
    .nav-link {
      position: relative;
    }
    
    .nav-link::after {
      content: '';
      position: absolute;
      bottom: -5px;
      left: 0;
      width: 0;
      height: 2px;
      background: linear-gradient(to right, #1e3a8a, #3b82f6);
      transition: width 0.3s ease;
    }
    
    .nav-link:hover::after {
      width: 100%;
    }
  </style>
</head>

<body class="font-sans text-gray-800">
  <!-- Top Bar -->
  <div class="bg-gradient-to-r from-blue-900 to-blue-600 text-white py-2">
    <div class="container mx-auto px-4 flex flex-col md:flex-row justify-between items-center">
      <div class="flex items-center space-x-4 mb-2 md:mb-0">
        <div class="flex items-center">
          <i class="fas fa-envelope text-sm mr-2"></i>
          <span class="text-sm">newgraceservice@gmail.com</span>
        </div>
        <div class="flex items-center">
          <i class="fas fa-phone ml-4 text-sm mr-2"></i>
          <span class="text-sm">+243 977 421 421</span>
        </div>
      </div>
      <div class="flex space-x-4">
        <a href="https://wa.me/243977421421" target="_blank" class="hover:text-blue-200 transition">
          <i class="fab fa-whatsapp"></i>
        </a>
        <a href="#" class="hover:text-blue-200 transition">
          <i class="fab fa-facebook"></i>
        </a>
        <a href="#" class="hover:text-blue-200 transition">
          <i class="fab fa-instagram"></i>
        </a>
        <a href="#" class="hover:text-blue-200 transition">
          <i class="fab fa-tiktok"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- Header -->
  <header class="sticky top-0 z-50 bg-white shadow-sm">
    <div class="container mx-auto px-4 py-4">
      <div class="flex items-center justify-between">
        <!-- Logo -->
        <div class="flex items-center space-x-3">
          <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center">
            <span class="font-bold text-white text-xl">NGS</span>
          </div>
          <div>
            <h1 class="text-2xl font-bold">New Grace Service</h1>
            <p class="text-sm text-gray-500">Excellence en rideaux sur mesure</p>
          </div>
        </div>
        
        <!-- Navigation Desktop -->
        <nav class="hidden md:flex items-center space-x-8">
          <a href="#accueil" class="font-medium nav-link hover:text-blue-700 transition">Accueil</a>
          <a href="#produits" class="font-medium nav-link hover:text-blue-700 transition">Produits</a>
          <a href="#services" class="font-medium nav-link hover:text-blue-700 transition">Services</a>
          <a href="#boutiques" class="font-medium nav-link hover:text-blue-700 transition">Boutiques</a>
          <a href="#contact" class="font-medium nav-link hover:text-blue-700 transition">Contact</a>
          <a href="login.php" class="btn-primary px-6 py-2 text-white rounded-lg font-medium">
            Espace Pro
          </a>
        </nav>
        
        <!-- Mobile Menu Button -->
        <button id="mobile-menu-btn" class="md:hidden text-gray-600">
          <i class="fas fa-bars text-2xl"></i>
        </button>
      </div>
      
      <!-- Mobile Menu -->
      <div id="mobile-menu" class="hidden md:hidden mt-4 bg-white rounded-lg shadow-lg p-4">
        <div class="flex flex-col space-y-3">
          <a href="#accueil" class="font-medium py-2 hover:text-blue-700 transition">Accueil</a>
          <a href="#produits" class="font-medium py-2 hover:text-blue-700 transition">Produits</a>
          <a href="#services" class="font-medium py-2 hover:text-blue-700 transition">Services</a>
          <a href="#boutiques" class="font-medium py-2 hover:text-blue-700 transition">Boutiques</a>
          <a href="#contact" class="font-medium py-2 hover:text-blue-700 transition">Contact</a>
          <a href="login.php" class="btn-primary px-4 py-2 text-white rounded-lg font-medium text-center mt-2">
            Espace Professionnel
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section id="accueil" class="hero-gradient text-white py-16 md:py-24">
    <div class="container mx-auto px-4">
      <div class="flex flex-col md:flex-row items-center">
        <div class="md:w-1/2 mb-12 md:mb-0 animate-fade-in">
          <div class="inline-flex items-center px-4 py-2 bg-white/20 rounded-full mb-6">
            <div class="w-2 h-2 rounded-full bg-white mr-2"></div>
            <span class="text-sm font-medium">NOUVEAU NOM • MÊME EXCELLENCE</span>
          </div>
          
          <h1 class="text-4xl md:text-5xl font-bold mb-6 leading-tight">
            L'art du rideau
            <span class="block text-blue-200 mt-2">réinventé</span>
          </h1>
          
          <p class="text-xl mb-8 text-gray-100 leading-relaxed">
            Chez New Grace Service, nous transformons vos intérieurs avec des créations sur mesure 
            qui allient tradition artisanale et design contemporain.
          </p>
          
          <div class="flex flex-col sm:flex-row gap-4">
            <a href="#produits" class="btn-primary px-8 py-3 text-white rounded-lg font-bold text-lg text-center">
              <i class="fas fa-eye mr-2"></i> Découvrir nos collections
            </a>
            <a href="#contact" class="px-8 py-3 border-2 border-white text-white rounded-lg font-medium hover:bg-white/10 transition text-center">
              <i class="fas fa-calendar-alt mr-2"></i> Consultation gratuite
            </a>
          </div>
        </div>
        
        <div class="md:w-1/2 md:pl-12">
          <div class="relative">
            <div class="bg-white/10 rounded-2xl p-2 backdrop-blur-sm">
              <img src="https://images.unsplash.com/photo-1618220179428-22790b461013?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" 
                   alt="Rideau design moderne NGS" 
                   class="rounded-xl shadow-2xl w-full h-64 md:h-96 object-cover">
            </div>
            
            <!-- Floating stats -->
            <div class="absolute -bottom-6 -left-4 bg-white text-gray-800 rounded-xl p-6 shadow-lg w-72">
              <div class="flex items-center mb-4">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center">
                  <i class="fas fa-star text-white text-xl"></i>
                </div>
                <div class="ml-4">
                  <div class="font-bold text-2xl">4.9/5</div>
                  <div class="text-sm text-gray-500">Note moyenne clients</div>
                </div>
              </div>
              <p class="text-gray-600 text-sm italic">"Une attention aux détails exceptionnelle et un service irréprochable."</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Stats Section -->
  <section class="py-12 bg-gray-50">
    <div class="container mx-auto px-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <div class="stat-card bg-white rounded-xl p-6 text-center shadow-sm">
          <div class="text-3xl font-bold text-blue-900 mb-2">28+</div>
          <div class="text-gray-600 font-medium">Ans d'expertise</div>
        </div>
        <div class="stat-card bg-white rounded-xl p-6 text-center shadow-sm">
          <div class="text-3xl font-bold text-blue-700 mb-2">2.5K+</div>
          <div class="text-gray-600 font-medium">Projets réalisés</div>
        </div>
        <div class="stat-card bg-white rounded-xl p-6 text-center shadow-sm">
          <div class="text-3xl font-bold text-blue-900 mb-2">98%</div>
          <div class="text-gray-600 font-medium">Clients satisfaits</div>
        </div>
        <div class="stat-card bg-white rounded-xl p-6 text-center shadow-sm">
          <div class="text-3xl font-bold text-blue-700 mb-2">3</div>
          <div class="text-gray-600 font-medium">Boutiques en RDC</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Produits Section -->
  <section id="produits" class="py-16 bg-white">
    <div class="container mx-auto px-4">
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold mb-4 section-title">Nos Collections Signature</h2>
        <p class="text-gray-600 text-lg max-w-2xl mx-auto">
          Des pièces uniques, conçues avec les plus beaux tissus du monde pour des intérieurs d'exception.
        </p>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Produit 1 -->
        <div class="card-hover bg-white rounded-xl overflow-hidden shadow-lg">
          <div class="h-64 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1616046229478-9901c5536a45?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Collection Lin Premium" 
                 class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
          </div>
          <div class="p-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="font-bold text-xl mb-2">Collection Lin Français</h3>
                <p class="text-gray-500">Tissu noble et respirant</p>
              </div>
              <span class="font-bold text-2xl text-blue-700">$289</span>
            </div>
            <p class="text-gray-600 mb-6">
              Lin cultivé en France, tissé artisanalement pour une texture unique et une durabilité exceptionnelle.
            </p>
            <div class="flex justify-between items-center">
              <div class="flex space-x-2">
                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">12 coloris</span>
                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">Éco-responsable</span>
              </div>
              <button class="px-4 py-2 bg-gradient-to-r from-blue-900 to-blue-600 text-white rounded-lg font-medium hover:opacity-90 transition">
                <i class="fas fa-eye mr-2"></i> Découvrir
              </button>
            </div>
          </div>
        </div>
        
        <!-- Produit 2 -->
        <div class="card-hover bg-white rounded-xl overflow-hidden shadow-lg">
          <div class="h-64 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Stores Japonais" 
                 class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
          </div>
          <div class="p-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="font-bold text-xl mb-2">Stores Japonais</h3>
                <p class="text-gray-500">Élégance et minimalisme</p>
              </div>
              <span class="font-bold text-2xl text-blue-700">$349</span>
            </div>
            <p class="text-gray-600 mb-6">
              Design épuré inspiré de l'artisanat japonais. Contrôle précis de la lumière naturelle pour une ambiance zen.
            </p>
            <div class="flex justify-between items-center">
              <div class="flex space-x-2">
                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">Sur mesure</span>
                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">Motorisable</span>
              </div>
              <button class="px-4 py-2 bg-gradient-to-r from-blue-900 to-blue-600 text-white rounded-lg font-medium hover:opacity-90 transition">
                <i class="fas fa-eye mr-2"></i> Découvrir
              </button>
            </div>
          </div>
        </div>
        
        <!-- Produit 3 -->
        <div class="card-hover bg-white rounded-xl overflow-hidden shadow-lg">
          <div class="h-64 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Voilages Ciel" 
                 class="w-full h-full object-cover transition-transform duration-500 hover:scale-110">
          </div>
          <div class="p-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="font-bold text-xl mb-2">Voilages Ciel Premium</h3>
                <p class="text-gray-500">Légèreté et translucidité</p>
              </div>
              <span class="font-bold text-2xl text-blue-700">$159</span>
            </div>
            <p class="text-gray-600 mb-6">
              Tissu organza de soie naturelle qui diffuse délicatement la lumière pour créer une atmosphère apaisante.
            </p>
            <div class="flex justify-between items-center">
              <div class="flex space-x-2">
                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">Anti-UV</span>
                <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm">Entretien facile</span>
              </div>
              <button class="px-4 py-2 bg-gradient-to-r from-blue-900 to-blue-600 text-white rounded-lg font-medium hover:opacity-90 transition">
                <i class="fas fa-eye mr-2"></i> Découvrir
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Services Section -->
  <section id="services" class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold mb-4 section-title">Nos Services</h2>
        <p class="text-gray-600 text-lg max-w-2xl mx-auto">
          Une approche méticuleuse pour garantir un résultat parfait, de la conception à l'installation.
        </p>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Service 1 -->
        <div class="card-hover bg-white rounded-xl p-6 shadow-sm">
          <div class="w-16 h-16 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mb-6">
            <i class="fas fa-comments text-white text-2xl"></i>
          </div>
          <h3 class="font-bold text-xl mb-4">Consultation gratuite</h3>
          <p class="text-gray-600 mb-4">
            Rencontre avec notre designer pour comprendre vos besoins et vos aspirations.
          </p>
          <ul class="space-y-2">
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Analyse de votre espace</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Conseils personnalisés</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Devis détaillé</span>
            </li>
          </ul>
        </div>
        
        <!-- Service 2 -->
        <div class="card-hover bg-white rounded-xl p-6 shadow-sm">
          <div class="w-16 h-16 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mb-6">
            <i class="fas fa-pencil-alt text-white text-2xl"></i>
          </div>
          <h3 class="font-bold text-xl mb-4">Conception sur mesure</h3>
          <p class="text-gray-600 mb-4">
            Création d'un projet unique avec choix des matériaux et validation 3D.
          </p>
          <ul class="space-y-2">
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Choix des matériaux</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Visualisation 3D</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Validation finale</span>
            </li>
          </ul>
        </div>
        
        <!-- Service 3 -->
        <div class="card-hover bg-white rounded-xl p-6 shadow-sm">
          <div class="w-16 h-16 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mb-6">
            <i class="fas fa-cut text-white text-2xl"></i>
          </div>
          <h3 class="font-bold text-xl mb-4">Fabrication artisanale</h3>
          <p class="text-gray-600 mb-4">
            Réalisation dans notre atelier par nos artisans experts avec contrôle qualité.
          </p>
          <ul class="space-y-2">
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Coupe précise au laser</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Finitions main</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Contrôle qualité</span>
            </li>
          </ul>
        </div>
        
        <!-- Service 4 -->
        <div class="card-hover bg-white rounded-xl p-6 shadow-sm">
          <div class="w-16 h-16 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mb-6">
            <i class="fas fa-tools text-white text-2xl"></i>
          </div>
          <h3 class="font-bold text-xl mb-4">Installation professionnelle</h3>
          <p class="text-gray-600 mb-4">
            Pose par nos techniciens experts avec conseils d'entretien inclus.
          </p>
          <ul class="space-y-2">
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Pose professionnelle</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Ajustements précis</span>
            </li>
            <li class="flex items-center text-sm">
              <i class="fas fa-check text-blue-600 mr-2"></i>
              <span>Guide d'entretien</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <!-- Boutiques Section -->
  <section id="boutiques" class="py-16 bg-white">
    <div class="container mx-auto px-4">
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold mb-4 section-title">Nos Boutiques</h2>
        <p class="text-gray-600 text-lg max-w-2xl mx-auto">
          Découvrez nos boutiques-ateliers où nos experts vous accueillent pour vous conseiller.
        </p>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <!-- Boutique 1 -->
        <div class="card-hover bg-white rounded-xl overflow-hidden shadow-lg">
          <div class="h-48 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Boutique Butembo" 
                 class="w-full h-full object-cover">
          </div>
          <div class="p-6">
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="font-bold text-xl mb-2">Butembo | Rawbank</h3>
                <p class="text-gray-500">Showroom principal</p>
              </div>
              <span class="px-3 py-1 bg-gradient-to-r from-blue-900 to-blue-600 text-white rounded-full text-sm font-medium">Nouveau</span>
            </div>
            <p class="text-gray-600 mb-6">
              Notre boutique principale avec salle d'exposition et atelier visible. Venez découvrir notre savoir-faire.
            </p>
            <div class="space-y-3">
              <div class="flex items-center text-gray-500">
                <i class="fas fa-map-marker-alt mr-3 text-blue-600"></i>
                <span class="text-sm">Butembo, rue président de la république, près de la Rawbank</span>
              </div>
              <div class="flex items-center text-gray-500">
                <i class="fas fa-clock mr-3 text-blue-600"></i>
                <span class="text-sm">Lundi au Samedi : 08h00 - 17h30</span>
              </div>
              <div class="flex items-center text-gray-500">
                <i class="fas fa-phone mr-3 text-blue-600"></i>
                <span class="text-sm">+243 977 421 421</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Boutique 2 -->
        <div class="card-hover bg-white rounded-xl overflow-hidden shadow-lg">
          <div class="h-48 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Boutique Beni" 
                 class="w-full h-full object-cover">
          </div>
          <div class="p-6">
            <div class="mb-4">
              <h3 class="font-bold text-xl mb-2">Beni | Boulevard Nyamwisi</h3>
              <p class="text-gray-500">Boutique et atelier</p>
            </div>
            <p class="text-gray-600 mb-6">
              Notre espace à Beni avec une sélection exclusive de nos meilleures collections de rideaux.
            </p>
            <div class="space-y-3">
              <div class="flex items-center text-gray-500">
                <i class="fas fa-map-marker-alt mr-3 text-blue-600"></i>
                <span class="text-sm">Bâtiment Mbayahi, près de la Rawbank</span>
              </div>
              <div class="flex items-center text-gray-500">
                <i class="fas fa-clock mr-3 text-blue-600"></i>
                <span class="text-sm">Lundi au Samedi : 08h00 - 17h00</span>
              </div>
              <div class="flex items-center text-gray-500">
                <i class="fas fa-phone mr-3 text-blue-600"></i>
                <span class="text-sm">+243 XXX XXX XXX</span>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Boutique 3 -->
        <div class="card-hover bg-white rounded-xl overflow-hidden shadow-lg">
          <div class="h-48 overflow-hidden">
            <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" 
                 alt="Boutique Bunia" 
                 class="w-full h-full object-cover">
          </div>
          <div class="p-6">
            <div class="mb-4">
              <h3 class="font-bold text-xl mb-2">Bunia | Rue Ituri</h3>
              <p class="text-gray-500">Showroom moderne</p>
            </div>
            <p class="text-gray-600 mb-6">
              Notre dernière boutique avec une exposition immersive de nos collections premium.
            </p>
            <div class="space-y-3">
              <div class="flex items-center text-gray-500">
                <i class="fas fa-map-marker-alt mr-3 text-blue-600"></i>
                <span class="text-sm">Bâtiment Qualitex, près de l'ancien SOFICOM</span>
              </div>
              <div class="flex items-center text-gray-500">
                <i class="fas fa-clock mr-3 text-blue-600"></i>
                <span class="text-sm">Lundi au Samedi : 08h30 - 17h30</span>
              </div>
              <div class="flex items-center text-gray-500">
                <i class="fas fa-phone mr-3 text-blue-600"></i>
                <span class="text-sm">+243 XXX XXX XXX</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Call to Action - Espace Pro -->
  <section class="py-16 bg-gradient-to-r from-blue-900 to-blue-600 text-white">
    <div class="container mx-auto px-4 text-center">
      <h2 class="text-3xl md:text-4xl font-bold mb-6">Gérez votre entreprise avec notre logiciel NGS</h2>
      <p class="text-xl mb-8 max-w-2xl mx-auto">
        Accédez à votre espace professionnel pour gérer vos ventes, stocks, transferts et rapports en temps réel.
      </p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="login.php" class="bg-white text-blue-900 px-8 py-3 rounded-lg font-bold text-lg hover:bg-gray-100 transition">
          <i class="fas fa-sign-in-alt mr-2"></i> Accéder à l'Espace Pro
        </a>
        <a href="#contact" class="border-2 border-white text-white px-8 py-3 rounded-lg font-medium hover:bg-white/10 transition">
          <i class="fas fa-info-circle mr-2"></i> Demander une démo
        </a>
      </div>
    </div>
  </section>

  <!-- Contact Section -->
  <section id="contact" class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold mb-4 section-title">Contactez-nous</h2>
        <p class="text-gray-600 text-lg max-w-2xl mx-auto">
          Notre équipe est à votre écoute pour répondre à toutes vos questions et projets.
        </p>
      </div>
      
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Contact Info -->
        <div class="space-y-8">
          <div class="bg-white rounded-xl p-8 shadow-sm">
            <h3 class="font-bold text-2xl mb-6">Nos Coordonnées</h3>
            
            <div class="space-y-6">
              <div class="flex items-start">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mr-4">
                  <i class="fas fa-phone text-white"></i>
                </div>
                <div>
                  <h4 class="font-bold text-lg mb-1">Téléphone</h4>
                  <p class="text-gray-600">+243 977 421 421</p>
                  <p class="text-sm text-gray-500">Disponible du lundi au samedi</p>
                </div>
              </div>
              
              <div class="flex items-start">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mr-4">
                  <i class="fas fa-envelope text-white"></i>
                </div>
                <div>
                  <h4 class="font-bold text-lg mb-1">Email</h4>
                  <p class="text-gray-600">newgraceservice@gmail.com</p>
                  <p class="text-sm text-gray-500">Réponse sous 24h</p>
                </div>
              </div>
              
              <div class="flex items-start">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mr-4">
                  <i class="fab fa-whatsapp text-white"></i>
                </div>
                <div>
                  <h4 class="font-bold text-lg mb-1">WhatsApp</h4>
                  <p class="text-gray-600">+243 977 421 421</p>
                  <p class="text-sm text-gray-500">Message direct et rapide</p>
                </div>
              </div>
            </div>
            
            <div class="mt-8 pt-8 border-t border-gray-200">
              <h4 class="font-bold text-lg mb-4">Horaires d'ouverture</h4>
              <div class="flex items-center">
                <i class="fas fa-clock text-blue-600 mr-3"></i>
                <div>
                  <p class="font-medium">Lundi au Samedi : 8h00 - 17h30</p>
                  <p class="text-sm text-gray-500">Sur rendez-vous le dimanche pour les projets urgents</p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Réseaux sociaux avec liens vers nos pages -->
          <div class="bg-white rounded-xl p-8 shadow-sm">
            <h3 class="font-bold text-2xl mb-6">Suivez-nous sur nos réseaux</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <a href="https://www.facebook.com/NewGraceService" target="_blank" 
                 class="flex flex-col items-center p-4 rounded-lg bg-blue-50 hover:bg-blue-100 transition group">
                <div class="w-12 h-12 rounded-lg bg-blue-600 flex items-center justify-center mb-3 group-hover:bg-blue-700 transition">
                  <i class="fab fa-facebook-f text-white text-xl"></i>
                </div>
                <span class="font-medium text-blue-700">Facebook</span>
                <span class="text-xs text-gray-500 mt-1">@NewGraceService</span>
              </a>
              
              <a href="https://www.instagram.com/NewGraceService" target="_blank" 
                 class="flex flex-col items-center p-4 rounded-lg bg-pink-50 hover:bg-pink-100 transition group">
                <div class="w-12 h-12 rounded-lg bg-pink-600 flex items-center justify-center mb-3 group-hover:bg-pink-700 transition">
                  <i class="fab fa-instagram text-white text-xl"></i>
                </div>
                <span class="font-medium text-pink-700">Instagram</span>
                <span class="text-xs text-gray-500 mt-1">@NewGraceService</span>
              </a>
              
              <a href="https://wa.me/243977421421" target="_blank" 
                 class="flex flex-col items-center p-4 rounded-lg bg-green-50 hover:bg-green-100 transition group">
                <div class="w-12 h-12 rounded-lg bg-green-500 flex items-center justify-center mb-3 group-hover:bg-green-600 transition">
                  <i class="fab fa-whatsapp text-white text-xl"></i>
                </div>
                <span class="font-medium text-green-700">WhatsApp</span>
                <span class="text-xs text-gray-500 mt-1">+243 977 421 421</span>
              </a>
              
              <a href="https://www.tiktok.com/@NewGraceService" target="_blank" 
                 class="flex flex-col items-center p-4 rounded-lg bg-gray-50 hover:bg-gray-100 transition group">
                <div class="w-12 h-12 rounded-lg bg-black flex items-center justify-center mb-3 group-hover:bg-gray-800 transition">
                  <i class="fab fa-tiktok text-white text-xl"></i>
                </div>
                <span class="font-medium text-gray-800">TikTok</span>
                <span class="text-xs text-gray-500 mt-1">@NewGraceService</span>
              </a>
            </div>
            
            <!-- Téléchargement de notre application -->
            <div class="mt-8 pt-8 border-t border-gray-200">
              <h4 class="font-bold text-lg mb-4">Téléchargez notre application</h4>
              <div class="flex flex-col sm:flex-row gap-3">
                <a href="#" class="flex items-center p-3 bg-black text-white rounded-lg hover:bg-gray-800 transition">
                  <i class="fab fa-apple text-2xl mr-3"></i>
                  <div>
                    <div class="text-xs">Disponible sur</div>
                    <div class="font-bold">App Store</div>
                  </div>
                </a>
                <a href="#" class="flex items-center p-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                  <i class="fab fa-google-play text-2xl mr-3"></i>
                  <div>
                    <div class="text-xs">Disponible sur</div>
                    <div class="font-bold">Play Store</div>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Carte de localisation -->
        <div class="bg-white rounded-xl p-8 shadow-sm h-full">
          <h3 class="font-bold text-2xl mb-6">Nos Boutiques sur la carte</h3>
          
          <!-- Mini-cartes des boutiques -->
          <div class="space-y-4 mb-8">
            <!-- Boutique 1 -->
            <div class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition group">
              <div class="w-4 h-4 rounded-full bg-blue-600 mr-4"></div>
              <div class="flex-1">
                <h4 class="font-bold text-lg mb-1 group-hover:text-blue-700 transition">Butembo | Rawbank</h4>
                <p class="text-gray-600 text-sm">Rue président de la république</p>
              </div>
              <i class="fas fa-chevron-right text-gray-400 group-hover:text-blue-600 transition"></i>
            </div>
            
            <!-- Boutique 2 -->
            <div class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition group">
              <div class="w-4 h-4 rounded-full bg-blue-500 mr-4"></div>
              <div class="flex-1">
                <h4 class="font-bold text-lg mb-1 group-hover:text-blue-600 transition">Beni | Boulevard Nyamwisi</h4>
                <p class="text-gray-600 text-sm">Bâtiment Mbayahi, près de la Rawbank</p>
              </div>
              <i class="fas fa-chevron-right text-gray-400 group-hover:text-blue-500 transition"></i>
            </div>
            
            <!-- Boutique 3 -->
            <div class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition group">
              <div class="w-4 h-4 rounded-full bg-blue-400 mr-4"></div>
              <div class="flex-1">
                <h4 class="font-bold text-lg mb-1 group-hover:text-blue-500 transition">Bunia | Rue Ituri</h4>
                <p class="text-gray-600 text-sm">Bâtiment Qualitex, près de l'ancien SOFICOM</p>
              </div>
              <i class="fas fa-chevron-right text-gray-400 group-hover:text-blue-400 transition"></i>
            </div>
          </div>
          
          <!-- Carte simplifiée -->
          <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-6 text-center">
            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-map-marker-alt text-white text-2xl"></i>
            </div>
            <h4 class="font-bold text-xl mb-2">Visitez nos boutiques</h4>
            <p class="text-gray-600 mb-4">
              Venez découvrir nos collections en personne et rencontrer nos experts.
            </p>
            <a href="https://maps.google.com/?q=Butembo+République+Démocratique+du+Congo" 
               target="_blank" 
               class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-900 to-blue-600 text-white rounded-lg font-bold hover:opacity-90 transition">
              <i class="fas fa-directions mr-2"></i>
              Voir sur Google Maps
            </a>
          </div>
          
          <!-- Appel direct -->
          <div class="mt-6 p-6 bg-gradient-to-r from-blue-900 to-blue-600 rounded-xl text-white text-center">
            <h4 class="font-bold text-xl mb-2">Besoin d'un conseil immédiat ?</h4>
            <p class="mb-4 text-blue-100">Nos designers sont disponibles pour une consultation téléphonique.</p>
            <a href="tel:+243977421421" class="inline-flex items-center px-6 py-3 bg-white text-blue-900 rounded-lg font-bold hover:bg-gray-100 transition">
              <i class="fas fa-phone mr-3"></i>
              Appelez-nous maintenant
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-800 text-white py-12">
    <div class="container mx-auto px-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
        <div>
          <div class="flex items-center space-x-3 mb-6">
            <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-900 to-blue-600 flex items-center justify-center">
              <span class="font-bold text-white text-xl">NGS</span>
            </div>
            <div>
              <h2 class="font-bold text-xl">New Grace Service</h2>
              <p class="text-gray-400 text-sm">Excellence depuis Bbo</p>
            </div>
          </div>
          <p class="text-gray-400">
            Créateurs d'ambiances lumineuses et élégantes à travers des rideaux sur mesure d'exception.
          </p>
        </div>
        
        <div>
          <h4 class="font-bold text-lg mb-4">Liens rapides</h4>
          <ul class="space-y-2">
            <li><a href="#accueil" class="text-gray-400 hover:text-white transition">Accueil</a></li>
            <li><a href="#produits" class="text-gray-400 hover:text-white transition">Produits</a></li>
            <li><a href="#services" class="text-gray-400 hover:text-white transition">Services</a></li>
            <li><a href="#boutiques" class="text-gray-400 hover:text-white transition">Boutiques</a></li>
            <li><a href="login.php" class="text-blue-400 hover:text-blue-300 transition">Espace Pro</a></li>
          </ul>
        </div>
        
        <div>
          <h4 class="font-bold text-lg mb-4">Services</h4>
          <ul class="space-y-2">
            <li><a href="#" class="text-gray-400 hover:text-white transition">Rideaux sur mesure</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition">Voilages</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition">Stores japonais</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition">Installation</a></li>
            <li><a href="#" class="text-gray-400 hover:text-white transition">Entretien</a></li>
          </ul>
        </div>
        
        <div>
          <h4 class="font-bold text-lg mb-4">Logiciel NGS Pro</h4>
          <ul class="space-y-2">
            <li><a href="login.php" class="text-gray-400 hover:text-white transition">Gestion des ventes</a></li>
            <li><a href="login.php" class="text-gray-400 hover:text-white transition">Gestion du stock</a></li>
            <li><a href="login.php" class="text-gray-400 hover:text-white transition">Transferts entre boutiques</a></li>
            <li><a href="login.php" class="text-gray-400 hover:text-white transition">Rapports analytiques</a></li>
            <li><a href="login.php" class="text-gray-400 hover:text-white transition">Mouvement de caisse</a></li>
          </ul>
        </div>
      </div>
      
      <div class="border-t border-gray-700 pt-8 text-center">
        <p class="text-gray-400">
          © 2024 New Grace Service. Tous droits réservés. | 
          <span class="text-blue-400">Site web & Logiciel de gestion développé par NGS</span>
        </p>
        <p class="text-gray-500 text-sm mt-2">
          Butembo, République Démocratique du Congo
        </p>
      </div>
    </div>
  </footer>

  <script>
    // Mobile menu toggle
    document.getElementById('mobile-menu-btn').addEventListener('click', function() {
      const mobileMenu = document.getElementById('mobile-menu');
      mobileMenu.classList.toggle('hidden');
      this.querySelector('i').classList.toggle('fa-bars');
      this.querySelector('i').classList.toggle('fa-times');
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        if (this.getAttribute('href') === '#') return;
        
        e.preventDefault();
        const targetId = this.getAttribute('href');
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          window.scrollTo({
            top: targetElement.offsetTop - 80,
            behavior: 'smooth'
          });
          
          // Close mobile menu if open
          document.getElementById('mobile-menu').classList.add('hidden');
          document.getElementById('mobile-menu-btn').querySelector('i').classList.add('fa-bars');
          document.getElementById('mobile-menu-btn').querySelector('i').classList.remove('fa-times');
        }
      });
    });

    // Animate elements on scroll
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-fade-in');
        }
      });
    }, observerOptions);

    // Observe cards for animation
    document.querySelectorAll('.card-hover, .stat-card').forEach((el) => {
      observer.observe(el);
    });

    // Update copyright year
    document.addEventListener('DOMContentLoaded', function() {
      const year = new Date().getFullYear();
      const copyrightElements = document.querySelectorAll('footer p');
      copyrightElements.forEach(el => {
        if (el.textContent.includes('2024')) {
          el.textContent = el.textContent.replace('2024', year);
        }
      });
      
      // Add hover effect to product buttons
      document.querySelectorAll('.card-hover button').forEach(button => {
        button.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-2px)';
        });
        button.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
        });
      });
    });

    // Navigation active state on scroll
    window.addEventListener('scroll', function() {
      const sections = document.querySelectorAll('section[id]');
      const navLinks = document.querySelectorAll('.nav-link');
      
      let current = '';
      
      sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (scrollY >= (sectionTop - 100)) {
          current = section.getAttribute('id');
        }
      });
      
      navLinks.forEach(link => {
        link.classList.remove('text-blue-700');
        if (link.getAttribute('href') === `#${current}`) {
          link.classList.add('text-blue-700');
        }
      });
    });
  </script>
</body>
</html>