<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>NGS - New Grace Service | Excellence en Rideaux sur Mesure</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
          },
        }
      }
    }
  </script>

  <style>
    :root {
      --glass-bg: rgba(255, 255, 255, 0.65);
      --glass-border: rgba(255, 255, 255, 0.3);
      --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
      --card-bg: rgba(255, 255, 255, 0.75);
      --card-hover-bg: rgba(255, 255, 255, 0.9);
      --text-primary: #1a1a2e;
      --text-secondary: #4a4a6a;
      --text-muted: #6b7280;
      --accent-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
      --surface-bg: rgba(248, 250, 252, 0.8);
      --divider: rgba(0, 0, 0, 0.06);
    }

    .dark {
      --glass-bg: rgba(15, 23, 42, 0.7);
      --glass-border: rgba(255, 255, 255, 0.08);
      --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
      --card-bg: rgba(30, 41, 59, 0.65);
      --card-hover-bg: rgba(30, 41, 59, 0.85);
      --text-primary: #f1f5f9;
      --text-secondary: #cbd5e1;
      --text-muted: #94a3b8;
      --accent-gradient: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
      --surface-bg: rgba(15, 23, 42, 0.6);
      --divider: rgba(255, 255, 255, 0.06);
    }

    body {
      background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 50%, #f5f3ff 100%);
      color: var(--text-primary);
      transition: background 0.4s ease, color 0.4s ease;
    }

    .dark body,
    body.dark {
      background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    }

    .glass {
      background: var(--glass-bg);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--glass-border);
      box-shadow: var(--glass-shadow);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .glass:hover {
      background: var(--card-hover-bg);
      transform: translateY(-2px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
    }

    .dark .glass:hover {
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    }

    .premium-card {
      background: var(--card-bg);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border: 1px solid var(--glass-border);
      border-radius: 1rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .premium-card:hover {
      background: var(--card-hover-bg);
      transform: translateY(-4px);
    }

    .section-underline {
      position: relative;
      display: inline-block;
      padding-bottom: 0.75rem;
    }

    .section-underline::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 3px;
      background: var(--accent-gradient);
      border-radius: 2px;
    }

    .btn-glass {
      background: var(--accent-gradient);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      transition: all 0.3s ease;
      font-weight: 600;
    }

    .btn-glass:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(30, 58, 138, 0.35);
    }

    .btn-glass:focus-visible {
      outline: 2px solid #60a5fa;
      outline-offset: 2px;
    }

    .nav-link {
      position: relative;
      color: var(--text-secondary);
      font-weight: 500;
      transition: color 0.3s ease;
      padding: 0.5rem 0;
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--accent-gradient);
      transition: width 0.3s ease;
      border-radius: 1px;
    }

    .nav-link:hover {
      color: var(--text-primary);
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .theme-toggle {
      width: 48px;
      height: 26px;
      background: #cbd5e1;
      border-radius: 13px;
      position: relative;
      cursor: pointer;
      transition: background 0.3s ease;
      border: 1px solid rgba(0, 0, 0, 0.1);
    }

    .dark .theme-toggle {
      background: #334155;
      border-color: rgba(255, 255, 255, 0.1);
    }

    .theme-toggle::after {
      content: '';
      position: absolute;
      top: 3px;
      left: 3px;
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
      transition: transform 0.3s ease;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .dark .theme-toggle::after {
      transform: translateX(22px);
      background: #fbbf24;
    }

    *:focus-visible {
      outline: 2px solid #60a5fa;
      outline-offset: 2px;
      border-radius: 4px;
    }

    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: transparent;
    }

    ::-webkit-scrollbar-thumb {
      background: rgba(100, 116, 139, 0.3);
      border-radius: 4px;
    }

    .dark ::-webkit-scrollbar-thumb {
      background: rgba(148, 163, 184, 0.2);
    }

    .line-clamp-1 {
      overflow: hidden;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 1;
    }

    .line-clamp-2 {
      overflow: hidden;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2;
    }

    @keyframes whatsappPulse {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5);
      }

      50% {
        box-shadow: 0 0 0 12px rgba(34, 197, 94, 0);
      }
    }

    .whatsapp-pulse {
      animation: whatsappPulse 2s infinite;
    }
  </style>
</head>

<body class="min-h-screen font-sans antialiased">

  <!-- ============================================ -->
  <!-- HEADER - Glass Navigation                    -->
  <!-- ============================================ -->
  <header class="sticky top-0 z-50 glass border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 md:h-20">

        <a href="#accueil" class="flex items-center gap-3 group" aria-label="New Grace Service - Accueil">
          <div class="w-10 h-10 md:w-11 md:h-11 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow">
            <span class="font-bold text-white text-lg md:text-xl">NGS</span>
          </div>
          <div class="hidden sm:block">
            <h2 class="text-base md:text-lg font-bold leading-tight text-[var(--text-primary)]">New Grace Service</h2>
            <p class="text-xs text-[var(--text-muted)] leading-tight">Excellence en rideaux</p>
          </div>
        </a>

        <nav class="hidden lg:flex items-center gap-1" aria-label="Navigation principale">
          <a href="#accueil" class="nav-link px-3 py-2 text-sm">Accueil</a>
          <a href="#realisations" class="nav-link px-3 py-2 text-sm">Réalisations</a>
          <a href="#services" class="nav-link px-3 py-2 text-sm">Services</a>
          <a href="#boutiques" class="nav-link px-3 py-2 text-sm">Boutiques</a>
          <a href="#contact" class="nav-link px-3 py-2 text-sm">Contact</a>
        </nav>

        <div class="flex items-center gap-3">
          <button id="theme-toggle"
            class="theme-toggle focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:ring-offset-2"
            aria-label="Basculer le thème clair/sombre"
            title="Changer le thème">
          </button>

          <a href="login.php"
            class="hidden md:inline-flex btn-glass px-4 py-2 text-sm rounded-xl">
            <i class="fas fa-lock mr-2"></i>Espace Pro
          </a>

          <button id="mobile-menu-btn"
            class="lg:hidden p-2 rounded-lg hover:bg-white/20 transition-colors focus-visible:ring-2 focus-visible:ring-blue-400"
            aria-label="Menu mobile"
            aria-expanded="false">
            <i class="fas fa-bars text-xl text-[var(--text-primary)]"></i>
          </button>
        </div>
      </div>

      <div id="mobile-menu" class="hidden lg:hidden pb-4">
        <nav class="flex flex-col gap-1 glass rounded-2xl p-4 mt-2" aria-label="Navigation mobile">
          <a href="#accueil" class="nav-link px-4 py-3 text-sm rounded-xl hover:bg-white/10 transition-colors">Accueil</a>
          <a href="#realisations" class="nav-link px-4 py-3 text-sm rounded-xl hover:bg-white/10 transition-colors">Réalisations</a>
          <a href="#services" class="nav-link px-4 py-3 text-sm rounded-xl hover:bg-white/10 transition-colors">Services</a>
          <a href="#boutiques" class="nav-link px-4 py-3 text-sm rounded-xl hover:bg-white/10 transition-colors">Boutiques</a>
          <a href="#contact" class="nav-link px-4 py-3 text-sm rounded-xl hover:bg-white/10 transition-colors">Contact</a>
          <a href="login.php" class="btn-glass text-center mt-2 py-3 rounded-xl">
            <i class="fas fa-lock mr-2"></i>Espace Professionnel
          </a>
        </nav>
      </div>
    </div>
  </header>

  <!-- ============================================ -->
  <!-- HERO SECTION                                 -->
  <!-- ============================================ -->
  <section id="accueil" class="relative overflow-hidden py-16 md:py-24 lg:py-32">
    <div class="absolute inset-0 -z-10 overflow-hidden">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-400/20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">

        <div class="space-y-6 md:space-y-8">
          <div class="inline-flex items-center gap-2 px-4 py-2 glass rounded-full text-sm">
            <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
            <span class="text-[var(--text-secondary)] font-medium">Un intérieur • Une beauté</span>
          </div>

          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-tight tracking-tight text-[var(--text-primary)]">
            L'art du rideau
            <span class="block bg-gradient-to-r from-blue-600 to-blue-400 bg-clip-text text-transparent mt-2">
              Sur mesure
            </span>
          </h1>

          <p class="text-lg md:text-xl text-[var(--text-secondary)] leading-relaxed max-w-xl">
            Chez New Grace Service, nous transformons vos intérieurs avec des créations sur mesure
            qui allient tradition artisanale et design contemporain.
          </p>

          <div class="flex flex-col sm:flex-row gap-4 pt-2">
            <a href="#realisations" class="btn-glass px-8 py-3.5 text-center rounded-xl">
              <i class="fas fa-eye mr-2"></i>Voir nos réalisations
            </a>
            <a href="#contact" class="px-8 py-3.5 text-center rounded-xl border-2 border-[var(--glass-border)] text-[var(--text-primary)] font-semibold hover:bg-white/10 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400">
              <i class="fas fa-calendar-alt mr-2"></i>Consultation gratuite
            </a>
          </div>
        </div>

        <div class="relative">
          <div class="glass rounded-2xl p-2 sm:p-3">
            <img src="https://images.unsplash.com/photo-1618220179428-22790b461013?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80"
              alt="Rideau design moderne NGS"
              class="rounded-xl w-full h-64 sm:h-80 lg:h-96 object-cover shadow-lg"
              loading="eager">
          </div>

          <div class="absolute -bottom-6 -left-4 sm:-left-6 glass rounded-2xl p-4 sm:p-5 max-w-[260px] sm:max-w-[280px]">
            <div class="flex items-center gap-3 mb-3">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center shadow-md">
                <i class="fas fa-star text-white text-sm"></i>
              </div>
              <div>
                <div class="font-bold text-xl text-[var(--text-primary)]">4.9/5</div>
                <div class="text-xs text-[var(--text-muted)]">Note moyenne</div>
              </div>
            </div>
            <p class="text-sm text-[var(--text-secondary)] italic leading-relaxed">
              "Une attention aux détails exceptionnelle et un service irréprochable."
            </p>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!-- STATS SECTION                                -->
  <!-- ============================================ -->
  <section class="py-12 md:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

        <div class="glass rounded-2xl p-6 text-center">
          <div class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-blue-600 to-blue-400 bg-clip-text text-transparent mb-1">10+</div>
          <div class="text-sm text-[var(--text-muted)] font-medium">Ans d'expertise</div>
        </div>

        <div class="glass rounded-2xl p-6 text-center">
          <div class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-blue-600 to-blue-400 bg-clip-text text-transparent mb-1">2.5K+</div>
          <div class="text-sm text-[var(--text-muted)] font-medium">Projets réalisés</div>
        </div>

        <div class="glass rounded-2xl p-6 text-center">
          <div class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-blue-600 to-blue-400 bg-clip-text text-transparent mb-1">97%</div>
          <div class="text-sm text-[var(--text-muted)] font-medium">Clients satisfaits</div>
        </div>

        <div class="glass rounded-2xl p-6 text-center">
          <div class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-blue-600 to-blue-400 bg-clip-text text-transparent mb-1">3</div>
          <div class="text-sm text-[var(--text-muted)] font-medium">Boutiques en RDC</div>
        </div>

      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!-- RÉALISATIONS SECTION (6 dernières)           -->
  <!-- ============================================ -->
  <section id="realisations" class="py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center mb-12 md:mb-16">
        <span class="inline-block glass px-4 py-1.5 rounded-full text-sm text-[var(--text-secondary)] mb-4">
          <i class="fas fa-star text-blue-500 mr-1.5"></i>Notre savoir-faire en images
        </span>
        <h2 class="section-underline text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[var(--text-primary)] mb-4">
          Nos Réalisations Récentes
        </h2>
        <p class="text-[var(--text-secondary)] text-lg max-w-2xl mx-auto leading-relaxed">
          Découvrez nos plus belles créations. Chaque projet est unique, pensé pour sublimer vos intérieurs.
        </p>
      </div>

      <!-- Filtres -->
      <div class="flex flex-wrap justify-center gap-2 sm:gap-3 mb-10" id="filtres-container">
        <button class="filtre-btn active px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-900 to-blue-600 text-white text-sm font-semibold shadow-lg transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400" data-categorie="tous">
          <i class="fas fa-th-large mr-1.5"></i>Tous
        </button>
        <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400" data-categorie="rideaux">
          <i class="fas fa-window-maximize mr-1.5"></i>Rideaux
        </button>
        <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400" data-categorie="voilages">
          <i class="fas fa-cloud mr-1.5"></i>Voilages
        </button>
        <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400" data-categorie="stores">
          <i class="fas fa-vector-square mr-1.5"></i>Stores
        </button>
        <button class="filtre-btn px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400" data-categorie="installation">
          <i class="fas fa-tools mr-1.5"></i>Installations
        </button>
      </div>

      <!-- Grille des réalisations -->
      <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8" id="realisations-grid">
        <!-- Loader -->
        <div class="col-span-full flex justify-center py-16" id="loader">
          <div class="flex flex-col items-center gap-3">
            <div class="w-10 h-10 border-[3px] border-blue-200 border-t-blue-600 rounded-full animate-spin dark:border-gray-700 dark:border-t-blue-400"></div>
            <p class="text-sm text-[var(--text-muted)]">Chargement des réalisations...</p>
          </div>
        </div>
      </div>

      <!-- Bouton Voir toutes les réalisations -->
      <div class="text-center mt-10">
        <a href="realisations.php" class="inline-flex items-center gap-2 px-8 py-3.5 rounded-xl border-2 border-blue-500/50 text-[var(--text-primary)] font-semibold hover:bg-blue-500/10 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400">
          <i class="fas fa-th-large"></i> Voir toutes nos réalisations
        </a>
      </div>

    </div>
  </section>

  <!-- Modal Détail Réalisation -->
  <div id="modal-realisation" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden opacity-0 transition-opacity duration-300 p-4">
    <div class="premium-card max-w-3xl w-full max-h-[85vh] overflow-y-auto transform scale-95 transition-transform duration-300 shadow-2xl" id="modal-content">
    </div>
  </div>

  <!-- ============================================ -->
  <!-- SERVICES SECTION                             -->
  <!-- ============================================ -->
  <section id="services" class="py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center mb-12 md:mb-16">
        <span class="inline-block glass px-4 py-1.5 rounded-full text-sm text-[var(--text-secondary)] mb-4">
          <i class="fas fa-cog text-blue-500 mr-1.5"></i>Expertise
        </span>
        <h2 class="section-underline text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[var(--text-primary)] mb-4">
          Nos Services
        </h2>
        <p class="text-[var(--text-secondary)] text-lg max-w-2xl mx-auto leading-relaxed">
          Une approche méticuleuse pour garantir un résultat parfait, de la conception à l'installation.
        </p>
      </div>

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

        <div class="premium-card p-6 group">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center mb-5 shadow-lg group-hover:shadow-xl transition-shadow">
            <i class="fas fa-comments text-white text-lg"></i>
          </div>
          <h3 class="font-bold text-lg text-[var(--text-primary)] mb-3">Consultation gratuite</h3>
          <p class="text-[var(--text-secondary)] text-sm mb-4 leading-relaxed">Rencontre avec notre designer pour comprendre vos besoins.</p>
          <ul class="space-y-2 text-sm">
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Analyse de votre espace
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Conseils personnalisés
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Devis détaillé
            </li>
          </ul>
        </div>

        <div class="premium-card p-6 group">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center mb-5 shadow-lg group-hover:shadow-xl transition-shadow">
            <i class="fas fa-pencil-alt text-white text-lg"></i>
          </div>
          <h3 class="font-bold text-lg text-[var(--text-primary)] mb-3">Conception sur mesure</h3>
          <p class="text-[var(--text-secondary)] text-sm mb-4 leading-relaxed">Création d'un projet unique avec choix des matériaux.</p>
          <ul class="space-y-2 text-sm">
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Choix des matériaux
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Visualisation 3D
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Validation finale
            </li>
          </ul>
        </div>

        <div class="premium-card p-6 group">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center mb-5 shadow-lg group-hover:shadow-xl transition-shadow">
            <i class="fas fa-cut text-white text-lg"></i>
          </div>
          <h3 class="font-bold text-lg text-[var(--text-primary)] mb-3">Fabrication artisanale</h3>
          <p class="text-[var(--text-secondary)] text-sm mb-4 leading-relaxed">Réalisation dans notre atelier par nos artisans experts.</p>
          <ul class="space-y-2 text-sm">
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Coupe précise
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Finitions main
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Contrôle qualité
            </li>
          </ul>
        </div>

        <div class="premium-card p-6 group">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center mb-5 shadow-lg group-hover:shadow-xl transition-shadow">
            <i class="fas fa-tools text-white text-lg"></i>
          </div>
          <h3 class="font-bold text-lg text-[var(--text-primary)] mb-3">Installation pro</h3>
          <p class="text-[var(--text-secondary)] text-sm mb-4 leading-relaxed">Pose par nos techniciens experts avec conseils d'entretien.</p>
          <ul class="space-y-2 text-sm">
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Pose professionnelle
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Ajustements précis
            </li>
            <li class="flex items-center gap-2 text-[var(--text-secondary)]">
              <i class="fas fa-check text-blue-500 text-xs w-4"></i>Guide d'entretien
            </li>
          </ul>
        </div>

      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!-- BOUTIQUES SECTION                            -->
  <!-- ============================================ -->
  <section id="boutiques" class="py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center mb-12 md:mb-16">
        <span class="inline-block glass px-4 py-1.5 rounded-full text-sm text-[var(--text-secondary)] mb-4">
          <i class="fas fa-store text-blue-500 mr-1.5"></i>Nos adresses
        </span>
        <h2 class="section-underline text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[var(--text-primary)] mb-4">
          Nos Boutiques
        </h2>
        <p class="text-[var(--text-secondary)] text-lg max-w-2xl mx-auto leading-relaxed">
          Découvrez nos boutiques-ateliers où nos experts vous accueillent pour vous conseiller.
        </p>
      </div>

      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">

        <article class="premium-card overflow-hidden group">
          <div class="relative h-48 overflow-hidden">
            <img src="img/butembo-boutique.jpeg"
              alt="Boutique Butembo"
              class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
              loading="lazy">
            <span class="absolute top-4 right-4 px-3 py-1 bg-gradient-to-r from-blue-600 to-blue-400 text-white text-xs font-semibold rounded-full shadow-lg">
              Principal
            </span>
          </div>
          <div class="p-5 sm:p-6">
            <h3 class="font-bold text-lg text-[var(--text-primary)] mb-1">Butembo | Rawbank</h3>
            <p class="text-sm text-[var(--text-muted)] mb-4">Showroom principal</p>
            <p class="text-[var(--text-secondary)] text-sm mb-5 leading-relaxed">
              Notre boutique principale avec salle d'exposition et atelier visible.
            </p>
            <div class="space-y-3 text-sm">
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-map-marker-alt text-blue-500 w-4 text-center"></i>
                <span>Butembo, rue président de la république</span>
              </div>
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-clock text-blue-500 w-4 text-center"></i>
                <span>Lun-Sam : 08h00 - 17h30</span>
              </div>
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-phone text-blue-500 w-4 text-center"></i>
                <span>+243 977 421 421</span>
              </div>
            </div>
          </div>
        </article>

        <article class="premium-card overflow-hidden group">
          <div class="relative h-48 overflow-hidden">
            <img src="img/beni-boutique.jpeg"
              alt="Boutique Beni"
              class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
              loading="lazy">
          </div>
          <div class="p-5 sm:p-6">
            <h3 class="font-bold text-lg text-[var(--text-primary)] mb-1">Beni | Boulevard Nyamwisi</h3>
            <p class="text-sm text-[var(--text-muted)] mb-4">Boutique et atelier</p>
            <p class="text-[var(--text-secondary)] text-sm mb-5 leading-relaxed">
              Notre espace à Beni avec une sélection exclusive de nos meilleures collections.
            </p>
            <div class="space-y-3 text-sm">
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-map-marker-alt text-blue-500 w-4 text-center"></i>
                <span>Bâtiment Mbayahi, près de la Rawbank</span>
              </div>
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-clock text-blue-500 w-4 text-center"></i>
                <span>Lun-Sam : 08h00 - 17h00</span>
              </div>
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-phone text-blue-500 w-4 text-center"></i>
                <span>+243 977 421 421</span>
              </div>
            </div>
          </div>
        </article>

        <article class="premium-card overflow-hidden group">
          <div class="relative h-48 overflow-hidden">
            <img src="img/bunia-boutique.jpeg"
              alt="Boutique Bunia"
              class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
              loading="lazy">
          </div>
          <div class="p-5 sm:p-6">
            <h3 class="font-bold text-lg text-[var(--text-primary)] mb-1">Bunia | Rue Ituri</h3>
            <p class="text-sm text-[var(--text-muted)] mb-4">Showroom moderne</p>
            <p class="text-[var(--text-secondary)] text-sm mb-5 leading-relaxed">
              Notre dernière boutique avec une exposition immersive de nos collections premium.
            </p>
            <div class="space-y-3 text-sm">
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-map-marker-alt text-blue-500 w-4 text-center"></i>
                <span>Bâtiment Qualitex, près de l'ancien SOFICOM</span>
              </div>
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-clock text-blue-500 w-4 text-center"></i>
                <span>Lun-Sam : 08h30 - 17h30</span>
              </div>
              <div class="flex items-center gap-3 text-[var(--text-secondary)]">
                <i class="fas fa-phone text-blue-500 w-4 text-center"></i>
                <span>+243 977 421 421</span>
              </div>
            </div>
          </div>
        </article>

      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!-- CONTACT SECTION                              -->
  <!-- ============================================ -->
  <section id="contact" class="py-16 md:py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center mb-12 md:mb-16">
        <span class="inline-block glass px-4 py-1.5 rounded-full text-sm text-[var(--text-secondary)] mb-4">
          <i class="fas fa-envelope text-blue-500 mr-1.5"></i>Contact
        </span>
        <h2 class="section-underline text-3xl sm:text-4xl lg:text-5xl font-extrabold text-[var(--text-primary)] mb-4">
          Contactez-nous
        </h2>
        <p class="text-[var(--text-secondary)] text-lg max-w-2xl mx-auto leading-relaxed">
          Notre équipe est à votre écoute pour répondre à toutes vos questions et projets.
        </p>
      </div>

      <div class="grid lg:grid-cols-2 gap-8 lg:gap-12">

        <div class="space-y-6">
          <div class="premium-card p-6 sm:p-8">
            <h3 class="font-bold text-xl text-[var(--text-primary)] mb-6">Nos Coordonnées</h3>

            <div class="space-y-5">
              <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center shadow-md flex-shrink-0">
                  <i class="fas fa-phone text-white"></i>
                </div>
                <div>
                  <h4 class="font-semibold text-[var(--text-primary)]">Téléphone</h4>
                  <p class="text-[var(--text-secondary)] text-sm">+243 977 421 421</p>
                  <p class="text-xs text-[var(--text-muted)]">Disponible du lundi au samedi</p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center shadow-md flex-shrink-0">
                  <i class="fas fa-envelope text-white"></i>
                </div>
                <div>
                  <h4 class="font-semibold text-[var(--text-primary)]">Email</h4>
                  <p class="text-[var(--text-secondary)] text-sm">newgraceservice@gmail.com</p>
                  <p class="text-xs text-[var(--text-muted)]">Réponse sous 24h</p>
                </div>
              </div>

              <div class="flex items-start gap-4">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center shadow-md flex-shrink-0">
                  <i class="fab fa-whatsapp text-white"></i>
                </div>
                <div>
                  <h4 class="font-semibold text-[var(--text-primary)]">WhatsApp</h4>
                  <p class="text-[var(--text-secondary)] text-sm">+243 977 421 421</p>
                  <p class="text-xs text-[var(--text-muted)]">Message direct et rapide</p>
                </div>
              </div>
            </div>
          </div>

          <div class="premium-card p-6 sm:p-8">
            <h3 class="font-bold text-xl text-[var(--text-primary)] mb-5">Suivez-nous</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <a href="https://www.facebook.com/NewGraceService" target="_blank" rel="noopener"
                class="flex flex-col items-center p-3 rounded-xl bg-blue-50/60 hover:bg-blue-100/80 transition-colors dark:bg-blue-900/20 dark:hover:bg-blue-900/40 group focus-visible:ring-2 focus-visible:ring-blue-400">
                <i class="fab fa-facebook-f text-blue-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs font-medium text-[var(--text-secondary)]">Facebook</span>
              </a>

              <a href="https://www.instagram.com/NewGraceService" target="_blank" rel="noopener"
                class="flex flex-col items-center p-3 rounded-xl bg-pink-50/60 hover:bg-pink-100/80 transition-colors dark:bg-pink-900/20 dark:hover:bg-pink-900/40 group focus-visible:ring-2 focus-visible:ring-pink-400">
                <i class="fab fa-instagram text-pink-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs font-medium text-[var(--text-secondary)]">Instagram</span>
              </a>

              <a href="https://wa.me/243977421421" target="_blank" rel="noopener"
                class="flex flex-col items-center p-3 rounded-xl bg-green-50/60 hover:bg-green-100/80 transition-colors dark:bg-green-900/20 dark:hover:bg-green-900/40 group focus-visible:ring-2 focus-visible:ring-green-400">
                <i class="fab fa-whatsapp text-green-600 text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs font-medium text-[var(--text-secondary)]">WhatsApp</span>
              </a>

              <a href="https://www.tiktok.com/@NewGraceService" target="_blank" rel="noopener"
                class="flex flex-col items-center p-3 rounded-xl bg-gray-100/60 hover:bg-gray-200/80 transition-colors dark:bg-gray-800/40 dark:hover:bg-gray-800/60 group focus-visible:ring-2 focus-visible:ring-gray-400">
                <i class="fab fa-tiktok text-gray-800 dark:text-white text-xl mb-2 group-hover:scale-110 transition-transform"></i>
                <span class="text-xs font-medium text-[var(--text-secondary)]">TikTok</span>
              </a>
            </div>
          </div>
        </div>

        <div class="premium-card p-6 sm:p-8 h-fit lg:sticky lg:top-24">
          <h3 class="font-bold text-xl text-[var(--text-primary)] mb-6">Nos Boutiques sur la carte</h3>

          <div class="space-y-3 mb-6">
            <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 transition-colors">
              <span class="w-3 h-3 rounded-full bg-blue-600 flex-shrink-0"></span>
              <div>
                <p class="font-medium text-sm text-[var(--text-primary)]">Butembo | Rawbank</p>
                <p class="text-xs text-[var(--text-muted)]">Rue président de la république</p>
              </div>
            </div>
            <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 transition-colors">
              <span class="w-3 h-3 rounded-full bg-blue-500 flex-shrink-0"></span>
              <div>
                <p class="font-medium text-sm text-[var(--text-primary)]">Beni | Boulevard Nyamwisi</p>
                <p class="text-xs text-[var(--text-muted)]">Bâtiment Mbayahi</p>
              </div>
            </div>
            <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-white/10 transition-colors">
              <span class="w-3 h-3 rounded-full bg-blue-400 flex-shrink-0"></span>
              <div>
                <p class="font-medium text-sm text-[var(--text-primary)]">Bunia | Rue Ituri</p>
                <p class="text-xs text-[var(--text-muted)]">Bâtiment Qualitex</p>
              </div>
            </div>
          </div>

          <a href="https://maps.google.com/?q=Butembo+République+Démocratique+du+Congo"
            target="_blank" rel="noopener"
            class="flex items-center justify-center gap-2 w-full py-3 glass rounded-xl font-semibold text-sm text-[var(--text-primary)] hover:bg-white/20 transition-all duration-300 mb-4 focus-visible:ring-2 focus-visible:ring-blue-400">
            <i class="fas fa-directions text-blue-500"></i>
            Voir sur Google Maps
          </a>

          <div class="p-5 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 text-white text-center">
            <h4 class="font-bold text-lg mb-2">Besoin d'un conseil ?</h4>
            <p class="text-sm text-blue-100 mb-4">Nos designers sont disponibles.</p>
            <a href="tel:+243977421421"
              class="inline-flex items-center gap-2 px-6 py-3 bg-white text-blue-900 rounded-xl font-bold hover:bg-gray-100 transition-colors focus-visible:ring-2 focus-visible:ring-white">
              <i class="fas fa-phone"></i>
              Appelez-nous
            </a>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ============================================ -->
  <!-- FOOTER                                       -->
  <!-- ============================================ -->
  <footer class="border-t border-[var(--divider)] py-12 md:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">

        <div>
          <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center shadow-md">
              <span class="font-bold text-white">NGS</span>
            </div>
            <div>
              <h3 class="font-bold text-[var(--text-primary)]">New Grace Service</h3>
              <p class="text-xs text-[var(--text-muted)]">Excellence depuis Bbo</p>
            </div>
          </div>
          <p class="text-sm text-[var(--text-secondary)] leading-relaxed">
            Créateurs d'ambiances lumineuses et élégantes à travers des rideaux sur mesure d'exception.
          </p>
        </div>

        <div>
          <h4 class="font-bold text-[var(--text-primary)] mb-4">Liens rapides</h4>
          <ul class="space-y-2.5 text-sm">
            <li><a href="#accueil" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Accueil</a></li>
            <li><a href="#realisations" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Réalisations</a></li>
            <li><a href="#services" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Services</a></li>
            <li><a href="#boutiques" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Boutiques</a></li>
            <li><a href="login.php" class="text-blue-500 hover:text-blue-400 transition-colors font-medium">Espace Pro</a></li>
          </ul>
        </div>

        <div>
          <h4 class="font-bold text-[var(--text-primary)] mb-4">Services</h4>
          <ul class="space-y-2.5 text-sm">
            <li><a href="#" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Rideaux sur mesure</a></li>
            <li><a href="#" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Voilages</a></li>
            <li><a href="#" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Stores japonais</a></li>
            <li><a href="#" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Installation</a></li>
            <li><a href="#" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Entretien</a></li>
          </ul>
        </div>

        <div>
          <h4 class="font-bold text-[var(--text-primary)] mb-4">NGS Pro</h4>
          <ul class="space-y-2.5 text-sm">
            <li><a href="login.php" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Gestion des ventes</a></li>
            <li><a href="login.php" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Gestion du stock</a></li>
            <li><a href="login.php" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Transferts</a></li>
            <li><a href="login.php" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Rapports</a></li>
            <li><a href="login.php" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Caisse</a></li>
          </ul>
        </div>

      </div>

      <div class="border-t border-[var(--divider)] pt-8 text-center">
        <p class="text-sm text-[var(--text-muted)]">
          © 2026 New Grace Service. Tous droits réservés. |
          <span class="text-blue-500">Design by Lad_77</span>
        </p>
        <p class="text-xs text-[var(--text-muted)] mt-2">
          Butembo, République Démocratique du Congo
        </p>
      </div>
    </div>
  </footer>

  <!-- ============================================ -->
  <!-- SCRIPTS                                      -->
  <!-- ============================================ -->
  <script>
    // ============================================
    // DARK/LIGHT MODE TOGGLE
    // ============================================
    const themeToggle = document.getElementById('theme-toggle');
    const html = document.documentElement;

    const savedTheme = localStorage.getItem('theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (savedTheme === 'dark' || (!savedTheme && systemDark)) {
      html.classList.add('dark');
    }

    themeToggle.addEventListener('click', () => {
      html.classList.toggle('dark');
      const isDark = html.classList.contains('dark');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });

    // ============================================
    // MOBILE MENU
    // ============================================
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    mobileMenuBtn.addEventListener('click', () => {
      const isExpanded = mobileMenuBtn.getAttribute('aria-expanded') === 'true';
      mobileMenuBtn.setAttribute('aria-expanded', !isExpanded);
      mobileMenu.classList.toggle('hidden');

      const icon = mobileMenuBtn.querySelector('i');
      if (mobileMenu.classList.contains('hidden')) {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      } else {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
      }
    });

    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileMenu.classList.add('hidden');
        mobileMenuBtn.setAttribute('aria-expanded', 'false');
        const icon = mobileMenuBtn.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      });
    });

    // ============================================
    // SMOOTH SCROLL
    // ============================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        if (href === '#') return;

        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
          const headerHeight = 80;
          const targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight;

          window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
          });
        }
      });
    });

    // ============================================
    // CONFIGURATION RÉALISATIONS
    // ============================================
    const API_BASE_URL = 'api/realisations.php';
    const WHATSAPP_NUMBER = '243977421421';
    let currentCategorie = 'tous';
    let sessionId = getOrCreateSessionId();

    const categorieLabels = {
      'rideaux': 'Rideaux',
      'voilages': 'Voilages',
      'stores': 'Stores',
      'installation': 'Installation',
      'sur_mesure': 'Sur mesure',
      'autre': 'Autre'
    };

    function getOrCreateSessionId() {
      let sid = localStorage.getItem('ngs_session_id');
      if (!sid) {
        sid = 'ngs_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('ngs_session_id', sid);
      }
      return sid;
    }

    function generateWhatsAppMessage(real) {
      const dateRealisation = new Date(real.date_realisation || real.date_creation);
      const formattedDate = dateRealisation.toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });

      let message = `🖼️ *${real.titre}*\n\n`;
      message += `Bonjour NGS ! Je suis intéressé(e) par cette réalisation que j'ai vue sur votre site.\n\n`;
      message += `📋 *Détails de la réalisation :*\n`;
      message += `📌 Titre : ${real.titre}\n`;
      message += `📂 Catégorie : ${categorieLabels[real.categorie] || real.categorie}\n`;
      if (real.description) {
        const desc = real.description.length > 150 ? real.description.substring(0, 150) + '...' : real.description;
        message += `📝 Description : ${desc}\n`;
      }
      if (real.client_ville) message += `📍 Lieu : ${real.client_ville}\n`;
      message += `📅 Date : ${formattedDate}\n`;
      message += `🏪 Boutique : ${real.boutique_nom || 'NGS'}\n`;
      message += `🔗 Réf : #${real.id}\n\n`;
      message += `📸 *Voir l'image :* ${real.image_principale}\n\n`;
      message += `✨ Je souhaiterais un service similaire. Pouvez-vous me faire un devis ?\n\n`;
      message += `Merci d'avance !`;

      return message;
    }

    async function loadRealisations(categorie = 'tous') {
      const grid = document.getElementById('realisations-grid');
      const loader = document.getElementById('loader');
      grid.innerHTML = '';
      loader.classList.remove('hidden');

      try {
        let url = `${API_BASE_URL}?action=liste&page=1&limit=6&sort=recent`;
        if (categorie !== 'tous') url += `&categorie=${categorie}`;

        const response = await fetch(url);
        const data = await response.json();
        if (!data.success) throw new Error('Erreur de chargement');

        const realisations = data.data;
        if (realisations.length === 0) {
          grid.innerHTML = `<div class="col-span-full text-center py-16"><i class="fas fa-images text-5xl text-[var(--text-muted)] opacity-30 mb-4 block"></i><p class="text-[var(--text-secondary)] text-lg">Aucune réalisation trouvée</p><p class="text-[var(--text-muted)] text-sm mt-1">Revenez bientôt pour découvrir nos nouveaux projets !</p></div>`;
          return;
        }

        realisations.forEach(real => grid.appendChild(createRealisationCard(real)));
      } catch (error) {
        console.error('Erreur:', error);
        grid.innerHTML = `<div class="col-span-full text-center py-16"><i class="fas fa-exclamation-circle text-5xl text-red-300 mb-4 block"></i><p class="text-red-500">Erreur de chargement. Veuillez réessayer.</p></div>`;
      } finally {
        loader.classList.add('hidden');
      }
    }

    function createRealisationCard(realisation) {
      const card = document.createElement('article');
      card.className = 'premium-card overflow-hidden group cursor-pointer';

      const dateRealisation = new Date(realisation.date_realisation || realisation.date_creation);
      const formattedDate = dateRealisation.toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
      const whatsappUrl = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(generateWhatsAppMessage(realisation))}`;

      card.innerHTML = `
        <div class="relative h-52 sm:h-60 overflow-hidden">
          <img src="${realisation.image_principale}" alt="${realisation.titre}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1618220179428-22790b461013?w=800'">
          <div class="absolute top-3 left-3"><span class="px-3 py-1 glass rounded-full text-xs font-medium text-[var(--text-primary)] shadow-sm"><i class="fas fa-tag mr-1 text-blue-500"></i>${categorieLabels[realisation.categorie] || realisation.categorie}</span></div>
          <button onclick="event.stopPropagation(); toggleLike(${realisation.id}, this)" class="like-btn absolute top-3 right-3 w-9 h-9 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm flex items-center justify-center shadow-sm hover:bg-white dark:hover:bg-gray-700 transition-all duration-300 group/like focus-visible:ring-2 focus-visible:ring-blue-400" aria-label="Aimer cette réalisation">
            <i class="far fa-heart text-sm text-gray-600 dark:text-gray-300 group-hover/like:text-red-500 transition-colors"></i>
            <span class="absolute -bottom-8 right-0 bg-gray-900 text-white text-xs px-2 py-1 rounded opacity-0 group-hover/like:opacity-100 transition pointer-events-none whitespace-nowrap"><span class="likes-count">${realisation.likes_count || 0}</span> j'aime</span>
          </button>
          ${realisation.images_count > 1 ? `<div class="absolute bottom-3 right-3 bg-black/40 backdrop-blur-sm text-white text-xs px-2.5 py-1 rounded-full"><i class="fas fa-images mr-1"></i>${realisation.images_count}</div>` : ''}
        </div>
        <div class="p-5">
          <div class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-2.5"><i class="fas fa-calendar-alt text-blue-500"></i><span>${formattedDate}</span>${realisation.client_ville ? `<span class="text-[var(--divider)]">•</span><i class="fas fa-map-marker-alt text-blue-500"></i><span>${realisation.client_ville}</span>` : ''}</div>
          <h3 class="font-bold text-[var(--text-primary)] mb-1.5 line-clamp-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">${realisation.titre}</h3>
          <p class="text-sm text-[var(--text-secondary)] mb-4 line-clamp-2 leading-relaxed">${realisation.description || 'Réalisation sur mesure par nos experts.'}</p>
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-[var(--text-muted)] flex items-center gap-1"><i class="fas fa-store text-blue-500"></i>${realisation.boutique_nom || 'NGS'}</span>
            <div class="flex gap-2">
              <button onclick="event.stopPropagation(); openDetailModal(${realisation.id})" class="px-3.5 py-2 rounded-xl glass text-xs font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400"><i class="fas fa-eye mr-1"></i>Détails</button>
              <a href="${whatsappUrl}" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-green-500 to-green-600 text-white text-xs font-semibold hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-md hover:shadow-lg focus-visible:ring-2 focus-visible:ring-green-400 inline-flex items-center gap-1 whatsapp-pulse"><i class="fab fa-whatsapp"></i>Commander</a>
            </div>
          </div>
        </div>
      `;
      card.addEventListener('click', () => openDetailModal(realisation.id));
      return card;
    }

    async function toggleLike(realisationId, buttonElement) {
      try {
        const heartIcon = buttonElement.querySelector('i');
        const likesCountSpan = buttonElement.querySelector('.likes-count');
        heartIcon.style.transform = 'scale(1.3)';
        setTimeout(() => heartIcon.style.transform = 'scale(1)', 200);

        const response = await fetch(`${API_BASE_URL}?action=like`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            realisation_id: realisationId,
            session_id: sessionId
          })
        });
        const data = await response.json();
        if (data.success) {
          if (data.liked) {
            heartIcon.classList.remove('far');
            heartIcon.classList.add('fas', 'text-red-500');
          } else {
            heartIcon.classList.remove('fas', 'text-red-500');
            heartIcon.classList.add('far');
          }
          if (likesCountSpan) likesCountSpan.textContent = data.likes_count;
        }
      } catch (error) {
        console.error('Erreur like:', error);
      }
    }

    async function openDetailModal(realisationId) {
      const modal = document.getElementById('modal-realisation');
      const modalContent = document.getElementById('modal-content');
      modalContent.innerHTML = `<div class="flex justify-center items-center py-16"><div class="w-10 h-10 border-[3px] border-blue-200 border-t-blue-600 rounded-full animate-spin"></div></div>`;
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
      setTimeout(() => {
        modal.classList.remove('opacity-0');
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
      }, 50);
      try {
        const response = await fetch(`${API_BASE_URL}?action=detail&id=${realisationId}&session_id=${sessionId}`);
        const data = await response.json();
        if (!data.success) throw new Error('Non trouvé');
        const real = data.data;
        const dateRealisation = new Date(real.date_realisation || real.date_creation);
        const formattedDate = dateRealisation.toLocaleDateString('fr-FR', {
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
        const whatsappUrl = `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(generateWhatsAppMessage(real))}`;
        const allImages = [{
          url: real.image_principale,
          isMain: true
        }];
        if (real.galerie && real.galerie.length > 0) real.galerie.forEach(img => allImages.push({
          url: img.image_url,
          isMain: false
        }));
        const hasMultipleImages = allImages.length > 1;
        const thumbnailsHTML = hasMultipleImages ? `<div class="flex gap-2 overflow-x-auto pb-2 px-5 pt-4" id="thumbnail-gallery">${allImages.map((img, i) => `<img src="${img.url}" class="thumbnail w-16 h-16 rounded-lg object-cover cursor-pointer border-2 flex-shrink-0 transition-all duration-200 ${i === 0 ? 'border-blue-600 opacity-100' : 'border-transparent opacity-70 hover:opacity-100'}" onclick="changeMainImage(this, '${img.url}', ${i})" data-index="${i}" alt="Image ${i + 1}">`).join('')}</div>` : '';
        modalContent.innerHTML = `
          <div class="relative">
            <div class="relative h-64 sm:h-80 overflow-hidden rounded-t-2xl" id="main-image-container">
              <img src="${real.image_principale}" alt="${real.titre}" class="w-full h-full object-cover transition-opacity duration-300" id="main-detail-image" onerror="this.src='https://images.unsplash.com/photo-1618220179428-22790b461013?w=1200'">
              ${hasMultipleImages ? `<button onclick="navigateImage(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm flex items-center justify-center shadow-lg hover:bg-white dark:hover:bg-gray-700 transition z-10 focus-visible:ring-2 focus-visible:ring-blue-400"><i class="fas fa-chevron-left text-gray-700 dark:text-gray-200 text-sm"></i></button><button onclick="navigateImage(1)" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm flex items-center justify-center shadow-lg hover:bg-white dark:hover:bg-gray-700 transition z-10 focus-visible:ring-2 focus-visible:ring-blue-400"><i class="fas fa-chevron-right text-gray-700 dark:text-gray-200 text-sm"></i></button><div class="absolute bottom-3 right-3 bg-black/50 backdrop-blur-sm text-white text-xs px-3 py-1.5 rounded-full z-10"><span id="image-counter">1</span> / ${allImages.length}</div>` : ''}
              <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent pointer-events-none"></div>
              <button onclick="closeDetailModal()" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm flex items-center justify-center hover:bg-white dark:hover:bg-gray-700 transition shadow-lg z-20 focus-visible:ring-2 focus-visible:ring-blue-400"><i class="fas fa-times text-gray-700 dark:text-gray-200"></i></button>
              <div class="absolute bottom-5 left-5 right-5 z-10"><span class="inline-block px-3 py-1 glass rounded-full text-xs font-medium text-white mb-3">${categorieLabels[real.categorie] || real.categorie}</span><h2 class="text-xl sm:text-2xl font-bold text-white">${real.titre}</h2></div>
            </div>
            ${thumbnailsHTML}
            <div class="p-5 sm:p-6">
              <div class="space-y-5">
                <div><h3 class="font-bold text-[var(--text-primary)] mb-2">✨ Description du projet</h3><p class="text-sm text-[var(--text-secondary)] leading-relaxed">${real.description || 'Aucune description détaillée.'}</p></div>
                <div class="flex flex-wrap gap-3"><div class="flex items-center gap-2 px-3 py-2 glass rounded-xl text-sm"><i class="fas fa-calendar-check text-blue-500"></i><span class="text-[var(--text-secondary)]">${formattedDate}</span></div>${real.client_ville ? `<div class="flex items-center gap-2 px-3 py-2 glass rounded-xl text-sm"><i class="fas fa-map-marker-alt text-blue-500"></i><span class="text-[var(--text-secondary)]">${real.client_ville}</span></div>` : ''}</div>
                ${real.client_nom ? `<div class="p-4 rounded-xl bg-blue-50/50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30"><p class="text-sm text-[var(--text-secondary)]"><i class="fas fa-user-check text-blue-500 mr-2"></i><span class="font-medium">Client :</span> ${real.client_nom}</p></div>` : ''}
                <div class="p-5 rounded-xl bg-gradient-to-r from-green-500 to-green-600 text-white"><div class="flex items-center gap-3 mb-3"><i class="fab fa-whatsapp text-2xl"></i><h4 class="font-bold">Commander ce service</h4></div><p class="text-sm text-green-100 mb-4">Partagez cette réalisation directement sur WhatsApp. L'image et les détails seront inclus dans le message !</p><a href="${whatsappUrl}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 w-full py-3 bg-white text-green-600 rounded-xl font-bold hover:bg-green-50 transition-colors focus-visible:ring-2 focus-visible:ring-white whatsapp-pulse"><i class="fab fa-whatsapp text-lg"></i>Partager sur WhatsApp</a><p class="text-xs text-green-200 text-center mt-2 opacity-80">📸 L'image sera incluse dans le message</p></div>
                <div class="flex items-center justify-between pt-2"><div class="flex items-center gap-2 text-sm text-[var(--text-muted)]"><div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center"><i class="fas fa-store text-white text-xs"></i></div><span>${real.boutique_nom || 'NGS'}</span></div><button onclick="toggleLike(${real.id}, this)" class="flex items-center gap-2 px-4 py-2 rounded-xl glass text-sm font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400"><i class="${real.liked_by_user ? 'fas text-red-500' : 'far'} fa-heart transition-colors"></i><span><span class="likes-count">${real.likes_count}</span> j'aime</span></button></div>
              </div>
            </div>
          </div>`;
        window._currentDetailImages = allImages;
        window._currentImageIndex = 0;
      } catch (error) {
        modalContent.innerHTML = `<div class="p-8 text-center"><i class="fas fa-exclamation-circle text-4xl text-red-300 mb-4 block"></i><p class="text-[var(--text-secondary)]">Erreur de chargement des détails.</p><button onclick="closeDetailModal()" class="mt-4 px-6 py-2 glass rounded-xl text-sm hover:bg-white/20 transition-colors focus-visible:ring-2 focus-visible:ring-blue-400">Fermer</button></div>`;
      }
    }

    function changeMainImage(thumbnail, imageUrl, index) {
      const mainImage = document.getElementById('main-detail-image');
      if (mainImage) {
        mainImage.style.opacity = '0';
        setTimeout(() => {
          mainImage.src = imageUrl;
          mainImage.style.opacity = '1';
        }, 150);
      }
      document.querySelectorAll('.thumbnail').forEach(thumb => {
        thumb.classList.remove('border-blue-600', 'opacity-100');
        thumb.classList.add('border-transparent', 'opacity-70');
      });
      thumbnail.classList.remove('border-transparent', 'opacity-70');
      thumbnail.classList.add('border-blue-600', 'opacity-100');
      window._currentImageIndex = index;
      const counter = document.getElementById('image-counter');
      if (counter) counter.textContent = index + 1;
    }

    function navigateImage(direction) {
      const images = window._currentDetailImages;
      if (!images || images.length <= 1) return;
      let newIndex = (window._currentImageIndex || 0) + direction;
      if (newIndex < 0) newIndex = images.length - 1;
      if (newIndex >= images.length) newIndex = 0;
      window._currentImageIndex = newIndex;
      const mainImage = document.getElementById('main-detail-image');
      if (mainImage) {
        mainImage.style.opacity = '0';
        setTimeout(() => {
          mainImage.src = images[newIndex].url;
          mainImage.style.opacity = '1';
        }, 150);
      }
      document.querySelectorAll('.thumbnail').forEach((thumb, i) => {
        if (i === newIndex) {
          thumb.classList.remove('border-transparent', 'opacity-70');
          thumb.classList.add('border-blue-600', 'opacity-100');
        } else {
          thumb.classList.remove('border-blue-600', 'opacity-100');
          thumb.classList.add('border-transparent', 'opacity-70');
        }
      });
      const counter = document.getElementById('image-counter');
      if (counter) counter.textContent = newIndex + 1;
    }

    document.addEventListener('keydown', function(e) {
      const modal = document.getElementById('modal-realisation');
      if (modal && !modal.classList.contains('hidden')) {
        if (e.key === 'ArrowLeft') {
          e.preventDefault();
          navigateImage(-1);
        } else if (e.key === 'ArrowRight') {
          e.preventDefault();
          navigateImage(1);
        }
      }
    });

    function closeDetailModal() {
      const modal = document.getElementById('modal-realisation');
      const modalContent = document.getElementById('modal-content');
      modal.classList.add('opacity-0');
      modalContent.classList.add('scale-95');
      modalContent.classList.remove('scale-100');
      setTimeout(() => {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        window._currentDetailImages = null;
        window._currentImageIndex = 0;
      }, 300);
    }

    document.getElementById('modal-realisation').addEventListener('click', function(e) {
      if (e.target === this) closeDetailModal();
    });

    // Filtres
    document.querySelectorAll('.filtre-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        document.querySelectorAll('.filtre-btn').forEach(b => {
          b.classList.remove('active', 'bg-gradient-to-r', 'from-blue-900', 'to-blue-600', 'text-white', 'shadow-lg');
          b.classList.add('glass', 'text-[var(--text-secondary)]');
        });
        this.classList.add('active', 'bg-gradient-to-r', 'from-blue-900', 'to-blue-600', 'text-white', 'shadow-lg');
        this.classList.remove('glass', 'text-[var(--text-secondary)]');
        currentCategorie = this.dataset.categorie;
        loadRealisations(currentCategorie);
      });
    });

    document.addEventListener('DOMContentLoaded', function() {
      loadRealisations('tous');
    });
  </script>
</body>

</html>