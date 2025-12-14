<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGS | New Grace Service - Rideaux sur mesure d'exception</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0A2540;
            --secondary: #7B61FF;
            --accent: #00D4AA;
            --light: #F8FAFC;
            --dark: #1E293B;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FFFFFF;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        body.dark-mode {
            background-color: #0F172A;
            color: #E2E8F0;
        }
        
        .font-display {
            font-family: 'Outfit', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #0A2540 0%, #1E3A5F 100%);
        }
        
        .dark-mode .gradient-bg {
            background: linear-gradient(135deg, #1E293B 0%, #334155 100%);
        }
        
        .gradient-accent {
            background: linear-gradient(90deg, #7B61FF 0%, #00D4AA 100%);
        }
        
        .gradient-text {
            background: linear-gradient(90deg, #7B61FF 0%, #00D4AA 100%);
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
        
        .dark-mode .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .shadow-hover {
            transition: box-shadow 0.3s ease;
        }
        
        .shadow-hover:hover {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .dark-mode .shadow-hover:hover {
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .hover-lift {
            transition: transform 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-8px);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .grid-pattern {
            background-image: 
                linear-gradient(rgba(10, 37, 64, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(10, 37, 64, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }
        
        .dark-mode .grid-pattern {
            background-image: 
                linear-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.05) 1px, transparent 1px);
        }
        
        /* Mode sombre spécifique */
        .dark-mode .bg-gray-50 {
            background-color: #1E293B;
        }
        
        .dark-mode .bg-white {
            background-color: #334155;
        }
        
        .dark-mode .text-gray-800 {
            color: #E2E8F0;
        }
        
        .dark-mode .text-gray-600 {
            color: #94A3B8;
        }
        
        .dark-mode .text-gray-500 {
            color: #94A3B8;
        }
        
        .dark-mode .text-gray-900 {
            color: #F1F5F9;
        }
        
        .dark-mode .border-gray-100 {
            border-color: #334155;
        }
        
        .dark-mode .bg-gray-800 {
            background-color: #1E293B;
        }
        
        .dark-mode .bg-gray-900 {
            background-color: #0F172A;
        }
        
        .dark-mode .bg-gray-950 {
            background-color: #0A0F1C;
        }
        
        .dark-mode .border-gray-700 {
            border-color: #475569;
        }
        
        .dark-mode .border-gray-800 {
            border-color: #475569;
        }
        
        /* Bouton mode sombre */
        .theme-toggle {
            position: relative;
            width: 60px;
            height: 30px;
            border-radius: 50px;
            background: linear-gradient(135deg, #7B61FF 0%, #00D4AA 100%);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .theme-toggle::before {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s ease;
        }
        
        .dark-mode .theme-toggle::before {
            transform: translateX(30px);
        }
        
        .theme-toggle i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
        }
        
        .theme-toggle .fa-sun {
            left: 8px;
            color: white;
        }
        
        .theme-toggle .fa-moon {
            right: 8px;
            color: white;
        }
        
        /* Animation pour le logo NGS */
        @keyframes pulse-grace {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .pulse-grace {
            animation: pulse-grace 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="text-gray-800 bg-white overflow-x-hidden">
    <!-- Navigation Minimaliste -->
    <header class="fixed w-full z-50 py-4 px-6 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center">
                <!-- Logo NGS -->
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center pulse-grace">
                        <span class="font-bold text-white text-lg">NGS</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold text-gray-900 dark:text-white">NGS</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400">New Grace Service | depuis 1995</p>
                    </div>
                </div>
                
                <!-- Menu Desktop -->
                <nav class="hidden lg:flex items-center space-x-10">
                    <a href="#" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium transition">Accueil</a>
                    <a href="#produits" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium transition">Collection</a>
                    <a href="#services" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium transition">Services</a>
                    <a href="#boutiques" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium transition">Boutiques</a>
                    <a href="#contact" class="px-5 py-2 bg-gray-900 dark:bg-gray-800 text-white rounded-full font-medium hover:bg-gray-800 dark:hover:bg-gray-700 transition">Contact</a>
                    
                    <!-- Bouton mode sombre -->
                    <div class="theme-toggle ml-4" id="theme-toggle">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                    </div>
                </nav>
                
                <div class="flex items-center space-x-4 lg:hidden">
                    <!-- Bouton mode sombre mobile -->
                    <div class="theme-toggle" id="theme-toggle-mobile">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                    </div>
                    
                    <!-- Menu Mobile Toggle -->
                    <button id="menu-toggle" class="text-gray-700 dark:text-gray-300">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Menu Mobile -->
            <div id="mobile-menu" class="hidden lg:hidden mt-4 pb-4 border-t border-gray-100 dark:border-gray-800">
                <div class="flex flex-col space-y-4 pt-4">
                    <a href="#" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium">Accueil</a>
                    <a href="#produits" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium">Collection</a>
                    <a href="#services" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium">Services</a>
                    <a href="#boutiques" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium">Boutiques</a>
                    <a href="#contact" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium">Contact</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section avec vidéo/gradient -->
    <section class="pt-24 pb-20 gradient-bg text-white overflow-hidden">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col lg:flex-row items-center">
                <div class="lg:w-1/2 animate-fade-in">
                    <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm mb-6">
                        <span class="text-sm">Nouveau nom, même excellence</span>
                        <span class="px-2 py-0.5 gradient-accent rounded-full text-xs">NGS</span>
                    </div>
                    <h1 class="text-5xl lg:text-6xl font-display font-bold mb-6 leading-tight">
                        <span class="gradient-text">New Grace Service</span><br>
                        L'art du rideau sur mesure
                    </h1>
                    <p class="text-xl text-gray-300 mb-10 max-w-2xl">
                        Transformez vos intérieurs avec nos créations d'exception. Design minimaliste, matériaux nobles et savoir-faire français.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="#produits" class="px-8 py-4 gradient-accent text-white rounded-full font-medium hover:opacity-90 transition inline-flex items-center justify-center">
                            Découvrir la collection
                            <i class="fas fa-arrow-right ml-3"></i>
                        </a>
                        <a href="#services" class="px-8 py-4 border-2 border-white/30 text-white rounded-full font-medium hover:bg-white/10 transition text-center">
                            Prendre rendez-vous
                        </a>
                    </div>
                    
                    <!-- Stats -->
                    <div class="flex flex-wrap gap-10 mt-16">
                        <div>
                            <div class="text-3xl font-bold">28+</div>
                            <div class="text-gray-400">Ans d'expertise</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold">5</div>
                            <div class="text-gray-400">Boutiques</div>
                        </div>
                        <div>
                            <div class="text-3xl font-bold">2500+</div>
                            <div class="text-gray-400">Projets réalisés</div>
                        </div>
                    </div>
                </div>
                
                <div class="lg:w-1/2 mt-12 lg:mt-0 relative">
                    <div class="relative rounded-3xl overflow-hidden shadow-2xl">
                        <img src="https://images.unsplash.com/photo-1618220179428-22790b461013?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2729&q=80" 
                             alt="Rideau design moderne" 
                             class="w-full h-[600px] object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                    </div>
                    
                    <!-- Floating card -->
                    <div class="absolute -bottom-6 -left-6 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-soft w-64">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full gradient-accent flex items-center justify-center">
                                <i class="fas fa-star text-white"></i>
                            </div>
                            <div class="ml-4">
                                <div class="font-bold text-gray-900 dark:text-white">4.9/5</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Avis clients</div>
                            </div>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 text-sm">"Excellente réalisation, un travail d'une précision remarquable."</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Collection Produits -->
    <section id="produits" class="py-20 bg-gray-50 dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-display font-bold mb-6 dark:text-white">
                    Collection <span class="gradient-text">Signature NGS</span>
                </h2>
                <p class="text-gray-600 dark:text-gray-300 text-lg max-w-2xl mx-auto">
                    Des pièces uniques conçues avec des matériaux d'exception, pour des intérieurs d'exception.
                </p>
            </div>
            
            <!-- Filtres Minimalistes -->
            <div class="flex flex-wrap justify-center gap-3 mb-12">
                <button class="px-5 py-2 bg-gray-900 dark:bg-gray-800 text-white rounded-full font-medium">Tout voir</button>
                <button class="px-5 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full font-medium shadow-soft hover:shadow-hover transition">Luxe</button>
                <button class="px-5 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full font-medium shadow-soft hover:shadow-hover transition">Minimaliste</button>
                <button class="px-5 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full font-medium shadow-soft hover:shadow-hover transition">Naturel</button>
                <button class="px-5 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full font-medium shadow-soft hover:shadow-hover transition">Sur mesure</button>
            </div>
            
            <!-- Grille Produits Moderne -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Produit 1 -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift animate-fade-in">
                    <div class="h-64 overflow-hidden relative">
                        <img src="https://images.unsplash.com/photo-1616046229478-9901c5536a45?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1760&q=80" 
                             alt="Rideaux en lin" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <div class="absolute top-4 right-4">
                            <span class="px-3 py-1 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm rounded-full text-sm font-medium dark:text-white">Nouveau</span>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-bold text-xl mb-1 dark:text-white">Collection Lin</h3>
                                <p class="text-gray-500 dark:text-gray-400">Tissu naturel et respirant</p>
                            </div>
                            <span class="font-bold text-2xl dark:text-white">€289</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-6">Lin français de haute qualité, tissé artisanalement pour une texture unique.</p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-palette mr-2"></i>
                                <span class="text-sm">12 coloris</span>
                            </div>
                            <button class="px-5 py-2 border-2 border-gray-900 dark:border-gray-300 text-gray-900 dark:text-white rounded-full font-medium hover:bg-gray-900 dark:hover:bg-gray-700 hover:text-white transition">
                                Découvrir
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Produit 2 -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift animate-fade-in" style="animation-delay: 0.1s">
                    <div class="h-64 overflow-hidden relative">
                        <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1760&q=80" 
                             alt="Stores japonais" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    </div>
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-bold text-xl mb-1 dark:text-white">Stores Japonais</h3>
                                <p class="text-gray-500 dark:text-gray-400">Élégance et minimalisme</p>
                            </div>
                            <span class="font-bold text-2xl dark:text-white">€349</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-6">Design épuré, matériaux nobles. Contrôle précis de la lumière naturelle.</p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-ruler-combined mr-2"></i>
                                <span class="text-sm">Sur mesure</span>
                            </div>
                            <button class="px-5 py-2 border-2 border-gray-900 dark:border-gray-300 text-gray-900 dark:text-white rounded-full font-medium hover:bg-gray-900 dark:hover:bg-gray-700 hover:text-white transition">
                                Découvrir
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Produit 3 -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift animate-fade-in" style="animation-delay: 0.2s">
                    <div class="h-64 overflow-hidden relative">
                        <img src="https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1770&q=80" 
                             alt="Voilages légers" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <div class="absolute top-4 right-4">
                            <span class="px-3 py-1 gradient-accent text-white rounded-full text-sm font-medium">Best-seller</span>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-bold text-xl mb-1 dark:text-white">Voilages Ciel</h3>
                                <p class="text-gray-500 dark:text-gray-400">Légèreté et translucidité</p>
                            </div>
                            <span class="font-bold text-2xl dark:text-white">€159</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-6">Tissu organza de soie, diffuse la lumière pour une ambiance apaisante.</p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-leaf mr-2"></i>
                                <span class="text-sm">Éco-responsable</span>
                            </div>
                            <button class="px-5 py-2 border-2 border-gray-900 dark:border-gray-300 text-gray-900 dark:text-white rounded-full font-medium hover:bg-gray-900 dark:hover:bg-gray-700 hover:text-white transition">
                                Découvrir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-16">
                <a href="#" class="inline-flex items-center text-gray-900 dark:text-white font-medium hover:underline">
                    Voir toute la collection NGS
                    <i class="fas fa-arrow-right ml-3"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Services Premium -->
    <section id="services" class="py-20 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl lg:text-5xl font-display font-bold mb-6">
                    L'approche <span class="gradient-text">New Grace Service</span>
                </h2>
                <p class="text-gray-300 text-lg max-w-2xl mx-auto">
                    Un service personnalisé de la conception à l'installation.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Service 1 -->
                <div class="card-glass rounded-3xl p-8 hover-lift">
                    <div class="w-16 h-16 rounded-2xl gradient-accent flex items-center justify-center mb-6">
                        <i class="fas fa-pen-ruler text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Conception</h3>
                    <p class="text-gray-300 mb-6">
                        Notre designer vient à votre domicile pour comprendre votre espace et vos besoins.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Étude de vos besoins</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Choix des matériaux</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Proposition sur mesure</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Service 2 -->
                <div class="card-glass rounded-3xl p-8 hover-lift">
                    <div class="w-16 h-16 rounded-2xl gradient-accent flex items-center justify-center mb-6">
                        <i class="fas fa-cut text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Fabrication</h3>
                    <p class="text-gray-300 mb-6">
                        Nos artisans réalisent vos rideaux dans notre atelier avec un savoir-faire unique.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Coupe précise</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Finitions main</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Contrôle qualité</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Service 3 -->
                <div class="card-glass rounded-3xl p-8 hover-lift">
                    <div class="w-16 h-16 rounded-2xl gradient-accent flex items-center justify-center mb-6">
                        <i class="fas fa-toolbox text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Installation</h3>
                    <p class="text-gray-300 mb-6">
                        Nos experts installent vos rideaux avec précision pour un rendu parfait.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Pose professionnelle</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Réglage minutieux</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Conseils d'entretien</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="text-center mt-16">
                <a href="#contact" class="px-8 py-4 bg-white text-gray-900 rounded-full font-medium hover:bg-gray-100 transition inline-flex items-center">
                    <i class="fas fa-calendar mr-3"></i>
                    Prendre rendez-vous avec NGS
                </a>
            </div>
        </div>
    </section>

    <!-- Boutiques -->
    <section id="boutiques" class="py-20 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col lg:flex-row items-center justify-between mb-16">
                <div class="lg:w-1/2 mb-10 lg:mb-0">
                    <h2 class="text-4xl lg:text-5xl font-display font-bold mb-6 dark:text-white">
                        Nos <span class="gradient-text">espaces NGS</span>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 text-lg">
                        Visitez nos boutiques-ateliers pour découvrir nos matériaux et rencontrer nos experts.
                    </p>
                </div>
                <div class="lg:w-1/2 lg:pl-16">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl">
                            <div class="text-3xl font-bold gradient-text mb-2">5</div>
                            <div class="font-medium dark:text-gray-300">Boutiques en France</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl">
                            <div class="text-3xl font-bold gradient-text mb-2">28</div>
                            <div class="font-medium dark:text-gray-300">Designers experts</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Carte des boutiques -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">
                <!-- Boutique Paris -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift">
                    <div class="h-64 overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1771&q=80" 
                             alt="Boutique Paris" 
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                    </div>
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="font-bold text-2xl mb-2 dark:text-white">Paris | Le Marais</h3>
                                <p class="text-gray-500 dark:text-gray-400">Flagship store NGS</p>
                            </div>
                            <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm font-medium">Nouveau</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-6">Notre boutique principale avec salle d'exposition et atelier visible.</p>
                        <div class="flex items-center text-gray-500 dark:text-gray-400 mb-2">
                            <i class="fas fa-map-marker-alt mr-3"></i>
                            <span>12 Rue de la Couture, 75003 Paris</span>
                        </div>
                        <div class="flex items-center text-gray-500 dark:text-gray-400">
                            <i class="fas fa-clock mr-3"></i>
                            <span>Du mardi au samedi, 10h-19h</span>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des autres boutiques -->
                <div class="space-y-6">
                    <!-- Boutique Lyon -->
                    <div class="flex items-center p-6 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <div class="w-16 h-16 rounded-xl gradient-accent flex items-center justify-center mr-6">
                            <i class="fas fa-store text-white text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-lg mb-1 dark:text-white">Lyon | Presqu'île</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">45 Avenue des Tisserands, 69002 Lyon</p>
                        </div>
                    </div>
                    
                    <!-- Boutique Bordeaux -->
                    <div class="flex items-center p-6 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <div class="w-16 h-16 rounded-xl gradient-accent flex items-center justify-center mr-6">
                            <i class="fas fa-store text-white text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-lg mb-1 dark:text-white">Bordeaux | Centre</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">8 Rue des Draperies, 33000 Bordeaux</p>
                        </div>
                    </div>
                    
                    <!-- Boutique Lille -->
                    <div class="flex items-center p-6 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <div class="w-16 h-16 rounded-xl gradient-accent flex items-center justify-center mr-6">
                            <i class="fas fa-store text-white text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-lg mb-1 dark:text-white">Lille | Vieux Lille</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">22 Place du Théâtre, 59000 Lille</p>
                        </div>
                    </div>
                    
                    <!-- Boutique Toulouse -->
                    <div class="flex items-center p-6 bg-gray-50 dark:bg-gray-800 rounded-2xl hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        <div class="w-16 h-16 rounded-xl gradient-accent flex items-center justify-center mr-6">
                            <i class="fas fa-store text-white text-xl"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-lg mb-1 dark:text-white">Toulouse | Capitole</h4>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">15 Rue des Filatiers, 31000 Toulouse</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="py-20 bg-gray-900 text-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Formulaire -->
                <div>
                    <h2 class="text-4xl lg:text-5xl font-display font-bold mb-6">
                        Discutons de <span class="gradient-text">votre projet</span>
                    </h2>
                    <p class="text-gray-400 mb-10">
                        Prenez rendez-vous avec l'un de nos designers NGS pour une consultation gratuite à domicile.
                    </p>
                    
                    <form class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <input type="text" 
                                       placeholder="Prénom" 
                                       class="w-full px-6 py-4 bg-gray-800 border border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent">
                            </div>
                            <div>
                                <input type="text" 
                                       placeholder="Nom" 
                                       class="w-full px-6 py-4 bg-gray-800 border border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent">
                            </div>
                        </div>
                        
                        <div>
                            <input type="email" 
                                   placeholder="Email" 
                                   class="w-full px-6 py-4 bg-gray-800 border border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent">
                        </div>
                        
                        <div>
                            <input type="tel" 
                                   placeholder="Téléphone" 
                                   class="w-full px-6 py-4 bg-gray-800 border border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent">
                        </div>
                        
                        <div>
                            <textarea rows="5" 
                                      placeholder="Décrivez votre projet..." 
                                      class="w-full px-6 py-4 bg-gray-800 border border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent"></textarea>
                        </div>
                        
                        <button type="submit" class="px-8 py-4 gradient-accent text-white rounded-xl font-medium hover:opacity-90 transition w-full">
                            Envoyer ma demande
                        </button>
                    </form>
                </div>
                
                <!-- Infos contact -->
                <div class="lg:pl-12">
                    <div class="mb-12">
                        <h3 class="text-2xl font-bold mb-6">Nos coordonnées</h3>
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div class="w-12 h-12 rounded-xl bg-gray-800 flex items-center justify-center mr-4">
                                    <i class="fas fa-phone text-accent"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">Téléphone</h4>
                                    <p class="text-gray-400">01 23 45 67 89</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="w-12 h-12 rounded-xl bg-gray-800 flex items-center justify-center mr-4">
                                    <i class="fas fa-envelope text-accent"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">Email</h4>
                                    <p class="text-gray-400">contact@ngs-service.fr</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start">
                                <div class="w-12 h-12 rounded-xl bg-gray-800 flex items-center justify-center mr-4">
                                    <i class="fas fa-clock text-accent"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">Horaires</h4>
                                    <p class="text-gray-400">Lundi au vendredi : 9h-18h</p>
                                    <p class="text-gray-400">Samedi : 10h-17h</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ -->
                    <div>
                        <h3 class="text-2xl font-bold mb-6">Questions fréquentes</h3>
                        <div class="space-y-4">
                            <div class="bg-gray-800 rounded-xl p-6">
                                <h4 class="font-bold mb-2">Quel est le délai de fabrication ?</h4>
                                <p class="text-gray-400 text-sm">En moyenne 3 à 4 semaines selon la complexité du projet.</p>
                            </div>
                            <div class="bg-gray-800 rounded-xl p-6">
                                <h4 class="font-bold mb-2">Proposez-vous la pose ?</h4>
                                <p class="text-gray-400 text-sm">Oui, nos experts NGS installent tous nos produits.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 bg-gray-950 text-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-8 md:mb-0">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center pulse-grace">
                            <span class="font-bold text-white text-lg">NGS</span>
                        </div>
                        <div class="ml-4">
                            <h2 class="font-display text-xl font-bold">New Grace Service</h2>
                            <p class="text-gray-500 text-sm">Rideaux sur mesure d'exception | depuis 1995</p>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm">© 2023 NGS - New Grace Service. Tous droits réservés.</p>
                </div>
                
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-white transition">
                        <i class="fab fa-instagram text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition">
                        <i class="fab fa-pinterest text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition">
                        <i class="fab fa-linkedin text-xl"></i>
                    </a>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-12 pt-8 text-center text-gray-500 text-sm">
                <p>Fabrication française | Matériaux éco-responsables | Garantie 5 ans</p>
                <p class="mt-2">Anciennement Julien_Rideau - Nouveau nom : <span class="text-accent">New Grace Service (NGS)</span></p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Menu mobile
        document.getElementById('menu-toggle').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
        });

        // Fermer le menu mobile en cliquant sur un lien
        const mobileLinks = document.querySelectorAll('#mobile-menu a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                document.getElementById('mobile-menu').classList.add('hidden');
            });
        });

        // Animation au scroll
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

        // Observer les éléments à animer
        document.querySelectorAll('.hover-lift').forEach((el, index) => {
            el.style.animationDelay = `${index * 0.1}s`;
            observer.observe(el);
        });

        // Gestion du mode sombre
        const themeToggle = document.getElementById('theme-toggle');
        const themeToggleMobile = document.getElementById('theme-toggle-mobile');
        const body = document.body;

        // Vérifier le thème sauvegardé ou la préférence système
        const savedTheme = localStorage.getItem('theme') || 'light';
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Appliquer le thème initial
        if (savedTheme === 'dark' || (savedTheme === 'system' && prefersDark)) {
            body.classList.add('dark-mode');
        }

        // Fonction pour basculer le thème
        function toggleTheme() {
            body.classList.toggle('dark-mode');
            const isDarkMode = body.classList.contains('dark-mode');
            localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
        }

        // Ajouter les événements
        themeToggle.addEventListener('click', toggleTheme);
        themeToggleMobile.addEventListener('click', toggleTheme);
    </script>
</body>
</html>