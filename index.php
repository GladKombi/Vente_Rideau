<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGS | New Grace Service - Excellence en rideaux sur mesure</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        .gradient-border {
            position: relative;
            background: linear-gradient(135deg, #0A2540, #1E3A5F) border-box;
            border: 2px solid transparent;
        }
        
        .card-glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
        }
        
        .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }
        
        .dark-mode .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .shadow-hover {
            transition: box-shadow 0.3s ease, transform 0.3s ease;
        }
        
        .shadow-hover:hover {
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.12);
            transform: translateY(-8px);
        }
        
        .dark-mode .shadow-hover:hover {
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.3);
        }
        
        .hover-lift {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-12px);
        }
        
        .animate-fade-in {
            animation: fadeIn 1s ease-out forwards;
            opacity: 0;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-slide-in {
            animation: slideIn 0.8s ease-out forwards;
            opacity: 0;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .grid-pattern {
            background-image: 
                radial-gradient(circle at 1px 1px, rgba(10, 37, 64, 0.03) 1px, transparent 0);
            background-size: 40px 40px;
        }
        
        .dark-mode .grid-pattern {
            background-image: 
                radial-gradient(circle at 1px 1px, rgba(148, 163, 184, 0.05) 1px, transparent 0);
        }
        
        /* Animations spécifiques */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .float-animation {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .pulse-subtle {
            animation: pulse-subtle 2s ease-in-out infinite;
        }
        
        /* Mode sombre spécifique */
        .dark-mode .bg-white {
            background-color: #1E293B;
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
        
        .dark-mode .border-gray-200 {
            border-color: #334155;
        }
        
        .dark-mode .bg-gray-50 {
            background-color: #1E293B;
        }
        
        .dark-mode .bg-gray-100 {
            background-color: #334155;
        }
        
        /* Navigation améliorée */
        .nav-link {
            position: relative;
            padding: 8px 0;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #7B61FF, #00D4AA);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        /* Bouton CTA amélioré */
        .cta-button {
            background: linear-gradient(135deg, #7B61FF 0%, #00D4AA 100%);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .cta-button:hover::before {
            left: 100%;
        }
        
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(123, 97, 255, 0.3);
        }
        
        /* Icônes réseaux sociaux */
        .social-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            transform: translateY(-5px) scale(1.1);
        }
        
        .social-whatsapp { background: linear-gradient(135deg, #25D366, #128C7E); }
        .social-telegram { background: linear-gradient(135deg, #0088CC, #006699); }
        .social-facebook { background: linear-gradient(135deg, #1877F2, #0A5FBD); }
        .social-instagram { background: linear-gradient(135deg, #E1306C, #C13584); }
        .social-phone { background: linear-gradient(135deg, #00D4AA, #00B894); }
        .social-email { background: linear-gradient(135deg, #7B61FF, #5D45DB); }
        
        /* Loader pour images */
        .image-loader {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Marquee animation pour badges */
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        .marquee-container {
            overflow: hidden;
            white-space: nowrap;
        }
        
        .marquee-content {
            display: inline-block;
            animation: marquee 20s linear infinite;
        }
        
        /* Stats cards */
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
            background: linear-gradient(135deg, rgba(123, 97, 255, 0.1), rgba(0, 212, 170, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        /* Testimonial card */
        .testimonial-card {
            position: relative;
        }
        
        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: -20px;
            left: 20px;
            font-size: 80px;
            color: rgba(123, 97, 255, 0.1);
            font-family: serif;
            z-index: 0;
        }
    </style>
</head>
<body class="text-gray-800 bg-white overflow-x-hidden">
    <!-- Navigation Premium -->
    <header class="fixed w-full z-50 py-4 px-6 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md border-b border-gray-100 dark:border-gray-800">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center">
                <!-- Logo NGS -->
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full gradient-accent flex items-center justify-center shadow-lg float-animation">
                        <span class="font-bold text-white text-xl font-display">NGS</span>
                    </div>
                    <div>
                        <h1 class="font-display text-2xl font-bold text-gray-900 dark:text-white">New Grace Service</h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 tracking-wider">EXCELLENCE DEPUIS Bbo</p>
                    </div>
                </div>
                
                <!-- Menu Desktop -->
                <nav class="hidden lg:flex items-center space-x-12">
                    <a href="#" class="nav-link text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium text-sm uppercase tracking-wide">Accueil</a>
                    <a href="#produits" class="nav-link text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium text-sm uppercase tracking-wide">Collection</a>
                    <a href="#services" class="nav-link text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium text-sm uppercase tracking-wide">Services</a>
                    <a href="#boutiques" class="nav-link text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium text-sm uppercase tracking-wide">Boutiques</a>
                    <a href="#contact" class="nav-link text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium text-sm uppercase tracking-wide">Contact</a>
                    
                    <div class="flex items-center space-x-4">
                        <a href="login.php" class="px-6 py-2 border-2 border-gray-900 dark:border-gray-300 text-gray-900 dark:text-white rounded-full font-medium hover:bg-gray-900 dark:hover:bg-gray-700 hover:text-white transition-all text-sm uppercase tracking-wide">
                            Espace Pro
                        </a>
                        
                        <!-- Bouton mode sombre -->
                        <div class="theme-toggle ml-2 cursor-pointer" id="theme-toggle">
                            <i class="fas fa-sun"></i>
                            <i class="fas fa-moon"></i>
                        </div>
                    </div>
                </nav>
                
                <!-- Menu Mobile -->
                <div class="flex items-center space-x-4 lg:hidden">
                    <div class="theme-toggle cursor-pointer" id="theme-toggle-mobile">
                        <i class="fas fa-sun"></i>
                        <i class="fas fa-moon"></i>
                    </div>
                    
                    <button id="menu-toggle" class="text-gray-700 dark:text-gray-300">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Menu Mobile -->
            <div id="mobile-menu" class="hidden lg:hidden mt-4 pb-4">
                <div class="flex flex-col space-y-4 bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-soft">
                    <a href="#" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 border-b border-gray-100 dark:border-gray-700">Accueil</a>
                    <a href="#produits" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 border-b border-gray-100 dark:border-gray-700">Collection</a>
                    <a href="#services" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 border-b border-gray-100 dark:border-gray-700">Services</a>
                    <a href="#boutiques" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 border-b border-gray-100 dark:border-gray-700">Boutiques</a>
                    <a href="#contact" class="text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium py-2 border-b border-gray-100 dark:border-gray-700">Contact</a>
                    <a href="login.php" class="px-4 py-3 gradient-accent text-white rounded-xl font-medium text-center mt-2">
                        Espace Professionnel
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section Premium -->
    <section class="pt-32 pb-24 gradient-bg text-white overflow-hidden relative">
        <div class="absolute inset-0 grid-pattern opacity-10"></div>
        <div class="absolute top-20 right-20 w-96 h-96 bg-gradient-to-r from-purple-500/10 to-teal-500/10 rounded-full blur-3xl"></div>
        
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="flex flex-col lg:flex-row items-center">
                <div class="lg:w-1/2 animate-slide-in">
                    <div class="inline-flex items-center space-x-3 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm mb-8">
                        <div class="w-2 h-2 rounded-full bg-accent pulse-subtle"></div>
                        <span class="text-sm font-medium tracking-wide">NOUVEAU NOM • MÊME EXCELLENCE</span>
                    </div>
                    
                    <h1 class="text-5xl lg:text-7xl font-display font-bold mb-8 leading-tight">
                        L'art du rideau
                        <span class="block gradient-text mt-2">réinventé</span>
                    </h1>
                    
                    <p class="text-xl text-gray-300 mb-10 max-w-2xl leading-relaxed">
                        Chez New Grace Service, nous transformons vos intérieurs avec des créations sur mesure qui allient tradition artisanale et design contemporain.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-6">
                        <a href="#produits" class="cta-button px-10 py-5 text-white rounded-full font-bold text-lg flex items-center justify-center space-x-3">
                            <span>Explorer la collection</span>
                            <i class="fas fa-arrow-right text-xl"></i>
                        </a>
                        <a href="#contact" class="px-10 py-5 border-2 border-white/30 text-white rounded-full font-medium hover:bg-white/10 transition-all flex items-center justify-center space-x-3">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Consultation gratuite</span>
                        </a>
                    </div>
                    
                    <!-- Stats Premium -->
                    <div class="flex flex-wrap gap-12 mt-16">
                        <div class="stat-card bg-white/5 backdrop-blur-sm rounded-2xl p-6 min-w-[160px]">
                            <div class="text-4xl font-bold mb-2">28+</div>
                            <div class="text-gray-400 text-sm uppercase tracking-wide">Ans d'expertise</div>
                        </div>
                        <div class="stat-card bg-white/5 backdrop-blur-sm rounded-2xl p-6 min-w-[160px]">
                            <div class="text-4xl font-bold mb-2">2.5K+</div>
                            <div class="text-gray-400 text-sm uppercase tracking-wide">Projets réalisés</div>
                        </div>
                        <div class="stat-card bg-white/5 backdrop-blur-sm rounded-2xl p-6 min-w-[160px]">
                            <div class="text-4xl font-bold mb-2">98%</div>
                            <div class="text-gray-400 text-sm uppercase tracking-wide">Clients satisfaits</div>
                        </div>
                    </div>
                </div>
                
                <div class="lg:w-1/2 mt-16 lg:mt-0 relative">
                    <div class="relative rounded-3xl overflow-hidden shadow-2xl hover-lift">
                        <div class="image-loader w-full h-[600px] rounded-3xl"></div>
                        <img src="https://images.unsplash.com/photo-1618220179428-22790b461013?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2729&q=80" 
                             alt="Rideau design moderne NGS" 
                             class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500"
                             onload="this.style.opacity=1"
                             style="opacity:0">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                    </div>
                    
                    <!-- Floating badge -->
                    <div class="absolute -bottom-6 -left-6 bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-soft w-72">
                        <div class="flex items-center mb-6">
                            <div class="w-14 h-14 rounded-full gradient-accent flex items-center justify-center shadow-lg">
                                <i class="fas fa-star text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <div class="font-bold text-gray-900 dark:text-white text-2xl">4.9/5</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">Note moyenne clients</div>
                            </div>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 text-sm italic">"Une attention aux détails exceptionnelle et un service irréprochable."</p>
                        <div class="flex items-center mt-4">
                            <div class="w-8 h-8 rounded-full overflow-hidden mr-2">
                                <img src="https://images.unsplash.com/photo-1494790108755-2616b786d4d9?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80" 
                                     alt="Client" class="w-full h-full object-cover">
                            </div>
                            <span class="text-sm font-medium">Marie K.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Collection Produits Premium -->
    <section id="produits" class="py-24 bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-800">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-20">
                <h2 class="text-5xl lg:text-6xl font-display font-bold mb-8 dark:text-white">
                    Collection <span class="gradient-text">Signature</span>
                </h2>
                <p class="text-gray-600 dark:text-gray-300 text-xl max-w-3xl mx-auto leading-relaxed">
                    Des pièces uniques, conçues avec les plus beaux tissus du monde pour des intérieurs d'exception.
                </p>
            </div>
            
            <!-- Produits Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-20">
                <!-- Produit 1 -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift">
                    <div class="h-80 overflow-hidden relative">
                        <div class="image-loader w-full h-full"></div>
                        <img src="https://images.unsplash.com/photo-1616046229478-9901c5536a45?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1760&q=80" 
                             alt="Collection Lin Premium" 
                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                             onload="this.style.opacity=1"
                             style="opacity:0">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                        <div class="absolute top-6 right-6">
                            <span class="px-4 py-2 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm rounded-full text-sm font-medium dark:text-white uppercase tracking-wide">Nouveau</span>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-bold text-2xl mb-2 dark:text-white">Collection Lin Français</h3>
                                <p class="text-gray-500 dark:text-gray-400">Tissu noble et respirant</p>
                            </div>
                            <span class="font-bold text-3xl gradient-text">$289</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-8 leading-relaxed">
                            Lin cultivé en France, tissé artisanalement pour une texture unique et une durabilité exceptionnelle.
                        </p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm">12 coloris</span>
                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm">Éco-responsable</span>
                            </div>
                            <button class="px-6 py-3 gradient-accent text-white rounded-full font-medium hover:opacity-90 transition">
                                <i class="fas fa-eye mr-2"></i> Découvrir
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Produit 2 -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift">
                    <div class="h-80 overflow-hidden relative">
                        <div class="image-loader w-full h-full"></div>
                        <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1760&q=80" 
                             alt="Stores Japonais NGS" 
                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                             onload="this.style.opacity=1"
                             style="opacity:0">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                    </div>
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-bold text-2xl mb-2 dark:text-white">Stores Japonais</h3>
                                <p class="text-gray-500 dark:text-gray-400">Élégance et minimalisme</p>
                            </div>
                            <span class="font-bold text-3xl gradient-text">$349</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-8 leading-relaxed">
                            Design épuré inspiré de l'artisanat japonais. Contrôle précis de la lumière naturelle pour une ambiance zen.
                        </p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm">Sur mesure</span>
                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm">Motorisable</span>
                            </div>
                            <button class="px-6 py-3 gradient-accent text-white rounded-full font-medium hover:opacity-90 transition">
                                <i class="fas fa-eye mr-2"></i> Découvrir
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Produit 3 -->
                <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift">
                    <div class="h-80 overflow-hidden relative">
                        <div class="image-loader w-full h-full"></div>
                        <img src="https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1770&q=80" 
                             alt="Voilages Ciel Premium" 
                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                             onload="this.style.opacity=1"
                             style="opacity:0">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                        <div class="absolute top-6 right-6">
                            <span class="px-4 py-2 gradient-accent text-white rounded-full text-sm font-medium uppercase tracking-wide">Best-seller</span>
                        </div>
                    </div>
                    <div class="p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="font-bold text-2xl mb-2 dark:text-white">Voilages Ciel</h3>
                                <p class="text-gray-500 dark:text-gray-400">Légèreté et translucidité</p>
                            </div>
                            <span class="font-bold text-3xl gradient-text">$159</span>
                        </div>
                        <p class="text-gray-600 dark:text-gray-300 mb-8 leading-relaxed">
                            Tissu organza de soie naturelle qui diffuse délicatement la lumière pour créer une atmosphère apaisante et romantique.
                        </p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-4">
                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm">Anti-UV</span>
                                <span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full text-sm">Entretien facile</span>
                            </div>
                            <button class="px-6 py-3 gradient-accent text-white rounded-full font-medium hover:opacity-90 transition">
                                <i class="fas fa-eye mr-2"></i> Découvrir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Badges de qualité -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-8 shadow-soft">
                <div class="marquee-container">
                    <div class="marquee-content flex space-x-12">
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-leaf text-3xl text-accent"></i>
                            <span class="text-lg font-medium dark:text-white">Matériaux durables</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-ruler-combined text-3xl text-accent"></i>
                            <span class="text-lg font-medium dark:text-white">Sur mesure</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-shield-alt text-3xl text-accent"></i>
                            <span class="text-lg font-medium dark:text-white">Garantie 5 ans</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-truck text-3xl text-accent"></i>
                            <span class="text-lg font-medium dark:text-white">Installation incluse</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-hand-sparkles text-3xl text-accent"></i>
                            <span class="text-lg font-medium dark:text-white">Fabriqué main</span>
                        </div>
                        <!-- Dupliquer pour l'animation continue -->
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-leaf text-3xl text-accent"></i>
                            <span class="text-lg font-medium dark:text-white">Matériaux durables</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <i class="fas fa-ruler-combined text-3xl text-accent"></i>
                            <span class="text-lg font-medium dark:text-white">Sur mesure</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Processus NGS -->
    <section id="services" class="py-24 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-20">
                <h2 class="text-5xl lg:text-6xl font-display font-bold mb-8">
                    Notre <span class="gradient-text">Processus</span>
                </h2>
                <p class="text-gray-300 text-xl max-w-3xl mx-auto leading-relaxed">
                    Une approche méticuleuse en 4 étapes pour garantir un résultat parfait.
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Étape 1 -->
                <div class="card-glass rounded-3xl p-8 hover-lift">
                    <div class="text-4xl font-bold text-accent mb-6">01</div>
                    <div class="w-16 h-16 rounded-2xl gradient-accent flex items-center justify-center mb-6">
                        <i class="fas fa-comments text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Consultation</h3>
                    <p class="text-gray-300 mb-6">
                        Rencontre avec notre designer pour comprendre vos besoins et vos aspirations.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Analyse de votre espace</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Conseils personnalisés</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Devis détaillé</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Étape 2 -->
                <div class="card-glass rounded-3xl p-8 hover-lift">
                    <div class="text-4xl font-bold text-accent mb-6">02</div>
                    <div class="w-16 h-16 rounded-2xl gradient-accent flex items-center justify-center mb-6">
                        <i class="fas fa-pencil-alt text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Conception</h3>
                    <p class="text-gray-300 mb-6">
                        Création d'un projet sur mesure avec choix des matériaux et validation 3D.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Choix des matériaux</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Visualisation 3D</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Validation finale</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Étape 3 -->
                <div class="card-glass rounded-3xl p-8 hover-lift">
                    <div class="text-4xl font-bold text-accent mb-6">03</div>
                    <div class="w-16 h-16 rounded-2xl gradient-accent flex items-center justify-center mb-6">
                        <i class="fas fa-cut text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Fabrication</h3>
                    <p class="text-gray-300 mb-6">
                        Réalisation dans notre atelier par nos artisans experts.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Coupe précise au laser</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Finitions main</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Contrôle qualité</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Étape 4 -->
                <div class="card-glass rounded-3xl p-8 hover-lift">
                    <div class="text-4xl font-bold text-accent mb-6">04</div>
                    <div class="w-16 h-16 rounded-2xl gradient-accent flex items-center justify-center mb-6">
                        <i class="fas fa-tools text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Installation</h3>
                    <p class="text-gray-300 mb-6">
                        Pose par nos techniciens experts et conseils d'entretien.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Pose professionnelle</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Ajustements précis</span>
                        </li>
                        <li class="flex items-center text-sm">
                            <i class="fas fa-check text-accent mr-3"></i>
                            <span>Guide d'entretien</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Boutiques Premium -->
    <section id="boutiques" class="py-24 bg-white dark:bg-gray-900">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col lg:flex-row items-center justify-between mb-20">
                <div class="lg:w-1/2 mb-12 lg:mb-0">
                    <h2 class="text-5xl lg:text-6xl font-display font-bold mb-8 dark:text-white">
                        Nos <span class="gradient-text">Espaces</span>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 text-xl leading-relaxed">
                        Découvrez nos boutiques-ateliers où nos experts vous accueillent pour vous conseiller et vous présenter nos collections.
                    </p>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-6 mt-12">
                        <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl">
                            <div class="text-4xl font-bold gradient-text mb-2">3</div>
                            <div class="font-medium dark:text-gray-300 uppercase tracking-wide">Boutiques en RDC</div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl">
                            <div class="text-4xl font-bold gradient-text mb-2">28</div>
                            <div class="font-medium dark:text-gray-300 uppercase tracking-wide">Designers experts</div>
                        </div>
                    </div>
                </div>
                
                <!-- Carte principale -->
                <div class="lg:w-1/2 lg:pl-16">
                    <div class="group bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-soft hover-lift">
                        <div class="h-64 overflow-hidden">
                            <div class="image-loader w-full h-full"></div>
                            <img src="https://images.unsplash.com/photo-1558618047-3c8c76ca7d13?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1771&q=80" 
                                 alt="Boutique Butembo NGS" 
                                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                 onload="this.style.opacity=1"
                                 style="opacity:0">
                        </div>
                        <div class="p-8">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <h3 class="font-bold text-2xl mb-2 dark:text-white">Butembo | Rawbank</h3>
                                    <p class="text-gray-500 dark:text-gray-400">Showroom principal</p>
                                </div>
                                <span class="px-4 py-2 gradient-accent text-white rounded-full text-sm font-medium uppercase tracking-wide">Nouveau</span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-300 mb-6 leading-relaxed">
                                Notre boutique principale avec salle d'exposition et atelier visible. Venez découvrir notre savoir-faire.
                            </p>
                            <div class="space-y-3">
                                <div class="flex items-center text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-map-marker-alt mr-3 text-accent"></i>
                                    <span>Butembo, rue président de la république, près de la Rawbank</span>
                                </div>
                                <div class="flex items-center text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-clock mr-3 text-accent"></i>
                                    <span>Lundi au Samedi : 08h00 - 17h30</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Autres boutiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Boutique 2 -->
                <div class="flex items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-3xl shadow-soft hover:shadow-hover transition-all group">
                    <div class="w-20 h-20 rounded-2xl gradient-accent flex items-center justify-center mr-6 shadow-lg">
                        <i class="fas fa-store text-white text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-xl mb-2 dark:text-white group-hover:text-accent transition">Butembo Centre</h4>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Rue président de la république, Bâtiment Kibweli</p>
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-phone-alt mr-2"></i>
                            <span>+243 XXX XXX XXX</span>
                        </div>
                    </div>
                </div>
                
                <!-- Boutique 3 -->
                <div class="flex items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-3xl shadow-soft hover:shadow-hover transition-all group">
                    <div class="w-20 h-20 rounded-2xl gradient-accent flex items-center justify-center mr-6 shadow-lg">
                        <i class="fas fa-store text-white text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-xl mb-2 dark:text-white group-hover:text-accent transition">Beni | Boulevard Nyamwisi</h4>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Bâtiment Mbayahi, près de la Rawbank</p>
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-phone-alt mr-2"></i>
                            <span>+243 XXX XXX XXX</span>
                        </div>
                    </div>
                </div>
                
                <!-- Boutique 4 -->
                <div class="flex items-center p-8 bg-gray-50 dark:bg-gray-800 rounded-3xl shadow-soft hover:shadow-hover transition-all group">
                    <div class="w-20 h-20 rounded-2xl gradient-accent flex items-center justify-center mr-6 shadow-lg">
                        <i class="fas fa-store text-white text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-xl mb-2 dark:text-white group-hover:text-accent transition">Bunia | Rue Ituri</h4>
                        <p class="text-gray-600 dark:text-gray-400 mb-3">Bâtiment Qualitex, près de l'ancien SOFICOM</p>
                        <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                            <i class="fas fa-phone-alt mr-2"></i>
                            <span>+243 XXX XXX XXX</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Contact Premium - AVEC ICÔNES RÉSEAUX SOCIAUX -->
    <section id="contact" class="py-24 bg-gradient-to-br from-gray-900 to-gray-950 text-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-20">
                <h2 class="text-5xl lg:text-6xl font-display font-bold mb-8">
                    Restons <span class="gradient-text">Connectés</span>
                </h2>
                <p class="text-gray-400 text-xl max-w-3xl mx-auto leading-relaxed">
                    Contactez-nous via vos canaux préférés. Notre équipe est à votre écoute du lundi au samedi.
                </p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
                <!-- Informations de contact -->
                <div>
                    <h3 class="text-3xl font-bold mb-10">Nos Coordonnées</h3>
                    
                    <!-- Grid des icônes de contact -->
                    <div class="grid grid-cols-2 gap-6 mb-12">
                        <!-- Téléphone -->
                        <a href="tel:+243977421421" class="group">
                            <div class="social-icon social-phone shadow-lg">
                                <i class="fas fa-phone-alt text-white text-xl"></i>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-bold text-lg mb-1">Téléphone</h4>
                                <p class="text-gray-400 group-hover:text-accent transition">+243 977 421 421</p>
                            </div>
                        </a>
                        
                        <!-- Email -->
                        <a href="mailto:newgraceservice@gmail.com" class="group">
                            <div class="social-icon social-email shadow-lg">
                                <i class="fas fa-envelope text-white text-xl"></i>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-bold text-lg mb-1">Email</h4>
                                <p class="text-gray-400 group-hover:text-accent transition">newgraceservice@gmail.com</p>
                            </div>
                        </a>
                        
                        <!-- WhatsApp -->
                        <a href="https://wa.me/243977421421" target="_blank" class="group">
                            <div class="social-icon social-whatsapp shadow-lg">
                                <i class="fab fa-whatsapp text-white text-xl"></i>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-bold text-lg mb-1">WhatsApp</h4>
                                <p class="text-gray-400 group-hover:text-accent transition">Message direct</p>
                            </div>
                        </a>
                        
                        <!-- Telegram -->
                        <a href="https://t.me/newgraceservice" target="_blank" class="group">
                            <div class="social-icon social-telegram shadow-lg">
                                <i class="fab fa-telegram text-white text-xl"></i>
                            </div>
                            <div class="mt-4">
                                <h4 class="font-bold text-lg mb-1">Telegram</h4>
                                <p class="text-gray-400 group-hover:text-accent transition">@newgraceservice</p>
                            </div>
                        </a>
                    </div>
                    
                    <!-- Réseaux sociaux -->
                    <div class="mb-12">
                        <h4 class="text-xl font-bold mb-6">Suivez-nous</h4>
                        <div class="flex space-x-4">
                            <a href="https://facebook.com/newgraceservice" target="_blank" class="social-icon social-facebook">
                                <i class="fab fa-facebook-f text-white"></i>
                            </a>
                            <a href="https://instagram.com/newgraceservice" target="_blank" class="social-icon social-instagram">
                                <i class="fab fa-instagram text-white"></i>
                            </a>
                            <a href="#" class="social-icon bg-gradient-to-br from-red-500 to-pink-600">
                                <i class="fab fa-tiktok text-white"></i>
                            </a>
                            <a href="#" class="social-icon bg-gradient-to-br from-blue-500 to-blue-700">
                                <i class="fab fa-linkedin-in text-white"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Horaires -->
                    <div class="bg-gray-800/50 rounded-2xl p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-accent to-secondary flex items-center justify-center mr-4">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg">Horaires d'ouverture</h4>
                                <p class="text-gray-400">Lundi au Samedi : 8h00 - 17h00</p>
                            </div>
                        </div>
                        <p class="text-gray-400 text-sm">Sur rendez-vous le dimanche pour les projets urgents.</p>
                    </div>
                </div>
                
                <!-- Carte de localisation -->
                <div class="lg:pl-8">
                    <div class="bg-gray-800/50 rounded-3xl p-8 h-full">
                        <h3 class="text-3xl font-bold mb-8">Nos Boutiques</h3>
                        
                        <!-- Mini-cartes des boutiques -->
                        <div class="space-y-6">
                            <!-- Boutique 1 -->
                            <div class="flex items-center p-6 bg-gray-800/30 rounded-2xl hover:bg-gray-800/50 transition group">
                                <div class="w-4 h-4 rounded-full bg-accent mr-4"></div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-lg mb-1 group-hover:text-accent transition">Butembo | Rawbank</h4>
                                    <p class="text-gray-400 text-sm">Rue président de la république</p>
                                </div>
                                <i class="fas fa-chevron-right text-gray-500 group-hover:text-accent transition"></i>
                            </div>
                            
                            <!-- Boutique 2 -->
                            <div class="flex items-center p-6 bg-gray-800/30 rounded-2xl hover:bg-gray-800/50 transition group">
                                <div class="w-4 h-4 rounded-full bg-secondary mr-4"></div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-lg mb-1 group-hover:text-secondary transition">Butembo Centre</h4>
                                    <p class="text-gray-400 text-sm">Bâtiment Kibweli</p>
                                </div>
                                <i class="fas fa-chevron-right text-gray-500 group-hover:text-secondary transition"></i>
                            </div>
                            
                            <!-- Boutique 3 -->
                            <div class="flex items-center p-6 bg-gray-800/30 rounded-2xl hover:bg-gray-800/50 transition group">
                                <div class="w-4 h-4 rounded-full bg-purple-500 mr-4"></div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-lg mb-1 group-hover:text-purple-400 transition">Beni | Boulevard Nyamwisi</h4>
                                    <p class="text-gray-400 text-sm">Bâtiment Mbayahi</p>
                                </div>
                                <i class="fas fa-chevron-right text-gray-500 group-hover:text-purple-400 transition"></i>
                            </div>
                            
                            <!-- Boutique 4 -->
                            <div class="flex items-center p-6 bg-gray-800/30 rounded-2xl hover:bg-gray-800/50 transition group">
                                <div class="w-4 h-4 rounded-full bg-teal-400 mr-4"></div>
                                <div class="flex-1">
                                    <h4 class="font-bold text-lg mb-1 group-hover:text-teal-400 transition">Bunia | Rue Ituri</h4>
                                    <p class="text-gray-400 text-sm">Bâtiment Qualitex</p>
                                </div>
                                <i class="fas fa-chevron-right text-gray-500 group-hover:text-teal-400 transition"></i>
                            </div>
                        </div>
                        
                        <!-- Call to action -->
                        <div class="mt-8 p-6 gradient-accent rounded-2xl text-center">
                            <h4 class="font-bold text-xl mb-2">Besoin d'un conseil personnalisé ?</h4>
                            <p class="mb-4">Nos designers sont disponibles pour une consultation gratuite.</p>
                            <a href="tel:+243977421421" class="inline-flex items-center px-6 py-3 bg-white text-gray-900 rounded-full font-bold hover:bg-gray-100 transition">
                                <i class="fas fa-phone mr-3"></i>
                                Appelez-nous maintenant
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer Premium -->
    <footer class="py-16 bg-gray-950 text-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col lg:flex-row justify-between items-center mb-12">
                <!-- Logo -->
                <div class="mb-8 lg:mb-0">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="w-12 h-12 rounded-full gradient-accent flex items-center justify-center">
                            <span class="font-bold text-white text-xl">NGS</span>
                        </div>
                        <div>
                            <h2 class="font-display text-2xl font-bold">New Grace Service</h2>
                            <p class="text-gray-400 text-sm">Excellence depuis Bbo</p>
                        </div>
                    </div>
                    <p class="text-gray-400 max-w-md">
                        Créateurs d'ambiances lumineuses et élégantes à travers des rideaux sur mesure d'exception.
                    </p>
                </div>
                
                <!-- Liens rapides -->
                <div class="flex flex-wrap gap-12 mb-8 lg:mb-0">
                    <div>
                        <h3 class="font-bold text-lg mb-4">Entreprise</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-400 hover:text-white transition">À propos</a></li>
                            <li><a href="#services" class="text-gray-400 hover:text-white transition">Services</a></li>
                            <li><a href="#boutiques" class="text-gray-400 hover:text-white transition">Boutiques</a></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-bold text-lg mb-4">Services</h3>
                        <ul class="space-y-2">
                            <li><a href="#" class="text-gray-400 hover:text-white transition">Conception sur mesure</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white transition">Installation</a></li>
                            <li><a href="#" class="text-gray-400 hover:text-white transition">Entretien</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Séparateur -->
            <div class="border-t border-gray-800 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-500 text-sm mb-4 md:mb-0">
                        © 2024 New Grace Service. Tous droits réservés.
                    </p>
                    
                    <div class="flex space-x-6">
                        <a href="#" class="text-gray-500 hover:text-white transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-white transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-white transition">
                            <i class="fab fa-tiktok"></i>
                        </a>
                        <a href="#" class="text-gray-500 hover:text-white transition">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts améliorés -->
    <script>
        // Menu mobile
        document.getElementById('menu-toggle').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
            this.querySelector('i').classList.toggle('fa-bars');
            this.querySelector('i').classList.toggle('fa-times');
        });

        // Gestion du mode sombre
        const themeToggle = document.getElementById('theme-toggle');
        const themeToggleMobile = document.getElementById('theme-toggle-mobile');
        const body = document.body;

        // Vérifier le thème sauvegardé
        const savedTheme = localStorage.getItem('ngs-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        // Appliquer le thème initial
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            body.classList.add('dark-mode');
        }

        // Fonction pour basculer le thème
        function toggleTheme() {
            body.classList.toggle('dark-mode');
            const isDarkMode = body.classList.contains('dark-mode');
            localStorage.setItem('ngs-theme', isDarkMode ? 'dark' : 'light');
            
            // Animation des icônes
            const sunIcons = document.querySelectorAll('.fa-sun');
            const moonIcons = document.querySelectorAll('.fa-moon');
            
            if (isDarkMode) {
                sunIcons.forEach(icon => icon.style.opacity = '0.5');
                moonIcons.forEach(icon => icon.style.opacity = '1');
            } else {
                sunIcons.forEach(icon => icon.style.opacity = '1');
                moonIcons.forEach(icon => icon.style.opacity = '0.5');
            }
        }

        // Ajouter les événements
        themeToggle.addEventListener('click', toggleTheme);
        themeToggleMobile.addEventListener('click', toggleTheme);

        // Animation au scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observer les éléments à animer
        document.querySelectorAll('.hover-lift, .card-glass, .stat-card').forEach((el, index) => {
            el.style.animationDelay = `${index * 0.1}s`;
            observer.observe(el);
        });

        // Smooth scroll pour les ancres
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                    
                    // Fermer le menu mobile si ouvert
                    document.getElementById('mobile-menu').classList.add('hidden');
                }
            });
        });

        // Préchargement d'images amélioré
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            const loader = img.parentElement.querySelector('.image-loader');
            if (loader) {
                img.addEventListener('load', () => {
                    loader.style.display = 'none';
                });
            }
        });

        // Effet de parallaxe simple
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallaxElements = document.querySelectorAll('.float-animation');
            
            parallaxElements.forEach(element => {
                const speed = element.dataset.speed || 0.5;
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });

        // Initialiser les opacités des icônes de thème
        toggleTheme(); // Pour mettre à jour les icônes
    </script>
</body>
</html>