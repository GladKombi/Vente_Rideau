<?php
include_once 'connexion/connexion.php';

$userType = '';
$pageTitle = 'Connexion';

if (isset($_SESSION['user_type'])) {
    $userType = $_SESSION['user_type'];
    switch ($userType) {
        case 'pdg':
            $pageTitle = 'Connexion PDG';
            break;
        case 'boutique':
            $pageTitle = 'Connexion Boutique';
            break;
        case 'it':
            $pageTitle = 'Connexion IT';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_type'])) {
    $_SESSION['user_type'] = $_POST['user_type'];
    header('Location: login.php');
    exit;
}

if (isset($_GET['change_profile'])) {
    unset($_SESSION['user_type']);
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?php echo $pageTitle; ?> - New Grace Service</title>
  
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
    /* ============================================ */
    /* DESIGN SYSTEM - Premium Glassmorphism        */
    /* ============================================ */
    
    :root {
      --glass-bg: rgba(255, 255, 255, 0.7);
      --glass-border: rgba(255, 255, 255, 0.35);
      --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
      --card-bg: rgba(255, 255, 255, 0.8);
      --card-hover-bg: rgba(255, 255, 255, 0.95);
      --text-primary: #1a1a2e;
      --text-secondary: #4a4a6a;
      --text-muted: #6b7280;
      --accent-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
      --input-bg: rgba(255, 255, 255, 0.9);
      --input-border: rgba(0, 0, 0, 0.1);
      --divider: rgba(0, 0, 0, 0.06);
    }

    .dark {
      --glass-bg: rgba(15, 23, 42, 0.75);
      --glass-border: rgba(255, 255, 255, 0.08);
      --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
      --card-bg: rgba(30, 41, 59, 0.7);
      --card-hover-bg: rgba(30, 41, 59, 0.9);
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
      min-height: 100vh;
      transition: background 0.4s ease, color 0.4s ease;
    }

    .dark body,
    body.dark {
      background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    }

    /* Glass card */
    .glass {
      background: var(--glass-bg);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--glass-border);
      box-shadow: var(--glass-shadow);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .glass:hover {
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    }

    .dark .glass:hover {
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
    }

    /* Premium card */
    .premium-card {
      background: var(--card-bg);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--glass-border);
      border-radius: 1.5rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .premium-card:hover {
      background: var(--card-hover-bg);
    }

    /* Button glass */
    .btn-glass {
      background: var(--accent-gradient);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      transition: all 0.3s ease;
      font-weight: 600;
      box-shadow: 0 4px 15px rgba(30, 58, 138, 0.25);
    }

    .btn-glass:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(30, 58, 138, 0.4);
    }

    .btn-glass:focus-visible {
      outline: 2px solid #60a5fa;
      outline-offset: 2px;
    }

    /* Input field */
    .input-glass {
      background: var(--input-bg);
      border: 2px solid var(--input-border);
      color: var(--text-primary);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      transition: all 0.3s ease;
      border-radius: 0.875rem;
    }

    .input-glass:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
      outline: none;
    }

    .input-glass::placeholder {
      color: var(--text-muted);
      opacity: 0.7;
    }

    /* User type card */
    .user-type-card {
      background: var(--card-bg);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border: 2px solid var(--glass-border);
      border-radius: 1.25rem;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .user-type-card:hover {
      border-color: #3b82f6;
      transform: translateY(-4px);
      box-shadow: 0 12px 30px rgba(30, 58, 138, 0.15);
    }

    .dark .user-type-card:hover {
      box-shadow: 0 12px 30px rgba(59, 130, 246, 0.2);
    }

    .user-type-input:checked + .user-type-card {
      border-color: #3b82f6;
      background: linear-gradient(135deg, rgba(30, 58, 138, 0.08), rgba(59, 130, 246, 0.08));
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12), 0 12px 30px rgba(30, 58, 138, 0.2);
    }

    .dark .user-type-input:checked + .user-type-card {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(96, 165, 250, 0.1));
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2), 0 12px 30px rgba(59, 130, 246, 0.25);
    }

    /* Theme toggle */
    .theme-toggle {
      width: 48px;
      height: 26px;
      background: #cbd5e1;
      border-radius: 13px;
      position: relative;
      cursor: pointer;
      transition: background 0.3s ease;
      border: 1px solid rgba(0,0,0,0.1);
    }

    .dark .theme-toggle {
      background: #334155;
      border-color: rgba(255,255,255,0.1);
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
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .dark .theme-toggle::after {
      transform: translateX(22px);
      background: #fbbf24;
    }

    /* Pulse animation */
    @keyframes pulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5); }
      50% { box-shadow: 0 0 0 20px rgba(59, 130, 246, 0); }
    }

    .pulse-ring {
      animation: pulse 2.5s infinite;
    }

    /* Focus visible */
    *:focus-visible {
      outline: 2px solid #60a5fa;
      outline-offset: 2px;
      border-radius: 6px;
    }

    /* Animations */
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(24px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade-in-up {
      animation: fadeInUp 0.5s ease-out forwards;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .animate-fade-in {
      animation: fadeIn 0.4s ease-out;
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
        
        <!-- Logo -->
        <a href="index.php" class="flex items-center gap-3 group" aria-label="New Grace Service - Accueil">
          <div class="w-10 h-10 md:w-11 md:h-11 rounded-xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow">
            <span class="font-bold text-white text-lg md:text-xl">NGS</span>
          </div>
          <div class="hidden sm:block">
            <h2 class="text-base md:text-lg font-bold leading-tight text-[var(--text-primary)]">New Grace Service</h2>
            <p class="text-xs text-[var(--text-muted)] leading-tight">Espace de connexion</p>
          </div>
        </a>

        <!-- Right side -->
        <div class="flex items-center gap-3">
          <!-- Theme Toggle -->
          <button id="theme-toggle" 
                  class="theme-toggle focus-visible:ring-2 focus-visible:ring-blue-400 focus-visible:ring-offset-2" 
                  aria-label="Basculer le thème clair/sombre"
                  title="Changer le thème">
          </button>

          <!-- Back to home -->
          <a href="index.php" 
             class="hidden sm:inline-flex items-center gap-2 px-4 py-2 rounded-xl glass text-sm font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400">
            <i class="fas fa-home"></i>
            <span>Accueil</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- ============================================ -->
  <!-- MAIN CONTENT                                 -->
  <!-- ============================================ -->
  <main class="flex items-center justify-center min-h-[calc(100vh-80px)] px-4 py-8 md:py-12">
    
    <!-- Background blobs -->
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-400/15 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-400/15 rounded-full blur-3xl"></div>
    </div>

    <div class="w-full max-w-lg">
      
      <!-- Error/Notification Message -->
      <?php if (isset($_SESSION['msg']) && !empty($_SESSION['msg'])) { ?>
        <div class="mb-6 animate-fade-in-up">
          <div class="glass rounded-2xl p-4 border-l-4 border-red-500 shadow-lg">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
              </div>
              <div class="flex-1 min-w-0">
                <h4 class="font-semibold text-red-700 dark:text-red-400 text-sm mb-1">Alerte de sécurité</h4>
                <p class="text-sm text-[var(--text-secondary)]"><?= htmlspecialchars($_SESSION['msg']) ?></p>
              </div>
              <button onclick="this.closest('.animate-fade-in-up').remove()" 
                      class="text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors p-1 rounded-lg hover:bg-white/10 flex-shrink-0">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        </div>
      <?php } ?>

      <!-- Main Login Card -->
      <div class="premium-card shadow-2xl p-6 sm:p-8 animate-fade-in-up">
        
        <!-- Header -->
        <div class="text-center mb-6">
          <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-blue-900 to-blue-600 flex items-center justify-center mx-auto mb-5 pulse-ring shadow-xl">
            <i class="fas fa-lock text-white text-2xl sm:text-3xl"></i>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-[var(--text-primary)] mb-1"><?php echo $pageTitle; ?></h1>
          <p class="text-sm text-[var(--text-muted)]">Accédez à votre espace de gestion</p>
        </div>

        <!-- ============================================ -->
        <!-- USER TYPE SELECTION (if no type selected)    -->
        <!-- ============================================ -->
        <?php if (empty($userType)) { ?>
        <form action="login.php" method="POST" id="userTypeForm">
          <div class="mb-6">
            <p class="text-center text-sm font-semibold text-[var(--text-secondary)] mb-4">
              <i class="fas fa-user-tag mr-2 text-blue-500"></i>Sélectionnez votre profil
            </p>
            <div class="grid gap-3">
              
              <!-- PDG -->
              <input type="radio" name="user_type" value="pdg" id="pdg" class="user-type-input hidden">
              <label for="pdg" class="user-type-card p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-900 to-blue-700 flex items-center justify-center shadow-md flex-shrink-0">
                  <i class="fas fa-crown text-white text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="font-bold text-[var(--text-primary)]">Direction Générale</h3>
                  <p class="text-xs text-[var(--text-muted)]">Accès complet • Tous les rapports</p>
                </div>
                <i class="fas fa-chevron-right text-[var(--text-muted)] opacity-0 group-hover:opacity-100 transition-opacity"></i>
              </label>

              <!-- Boutique -->
              <input type="radio" name="user_type" value="boutique" id="boutique" class="user-type-input hidden">
              <label for="boutique" class="user-type-card p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-600 to-blue-400 flex items-center justify-center shadow-md flex-shrink-0">
                  <i class="fas fa-store text-white text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="font-bold text-[var(--text-primary)]">Gestion Boutique</h3>
                  <p class="text-xs text-[var(--text-muted)]">Ventes • Stocks • Transferts</p>
                </div>
                <i class="fas fa-chevron-right text-[var(--text-muted)] opacity-0 group-hover:opacity-100 transition-opacity"></i>
              </label>

              <!-- IT -->
              <input type="radio" name="user_type" value="it" id="it" class="user-type-input hidden">
              <label for="it" class="user-type-card p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-500 flex items-center justify-center shadow-md flex-shrink-0">
                  <i class="fas fa-server text-white text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <h3 class="font-bold text-[var(--text-primary)]">Support Technique</h3>
                  <p class="text-xs text-[var(--text-muted)]">Admin • Maintenance • Supervision</p>
                </div>
                <i class="fas fa-chevron-right text-[var(--text-muted)] opacity-0 group-hover:opacity-100 transition-opacity"></i>
              </label>

            </div>
          </div>
          
          <!-- Continue Button (hidden until selection) -->
          <div id="continueSection" class="hidden animate-fade-in">
            <button type="submit" 
                    class="w-full btn-glass py-3.5 rounded-xl text-base flex items-center justify-center gap-3">
              <span>Continuer vers la connexion</span>
              <i class="fas fa-arrow-right"></i>
            </button>
          </div>
        </form>
        <?php } else { ?>

        <!-- ============================================ -->
        <!-- LOGIN FORM (specific user type)              -->
        <!-- ============================================ -->
        <form action="models/login_process.php" method="POST" class="space-y-5 animate-fade-in">
          <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($userType); ?>">
          
          <!-- User Type Badge -->
          <div class="text-center">
            <div class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold text-white shadow-lg mb-3
              <?php 
              if ($userType === 'pdg') echo 'bg-gradient-to-r from-blue-900 to-blue-700';
              elseif ($userType === 'boutique') echo 'bg-gradient-to-r from-blue-600 to-blue-400';
              else echo 'bg-gradient-to-r from-indigo-600 to-purple-500';
              ?>">
              <i class="fas fa-<?php 
                if ($userType === 'pdg') echo 'crown';
                elseif ($userType === 'boutique') echo 'store';
                else echo 'server';
              ?>"></i>
              <span>
              <?php 
              if ($userType === 'pdg') echo 'DIRECTION GÉNÉRALE';
              elseif ($userType === 'boutique') echo 'GESTION BOUTIQUE';
              else echo 'SUPPORT TECHNIQUE';
              ?>
              </span>
            </div>
            <p class="text-xs text-[var(--text-muted)]">
              <?php 
              if ($userType === 'pdg') echo 'Accès au tableau de bord global et rapports stratégiques';
              elseif ($userType === 'boutique') echo 'Accès à la gestion des ventes, stocks et transferts';
              else echo 'Accès à l\'administration système et maintenance';
              ?>
            </p>
          </div>

          <!-- Fields -->
          <?php if ($userType === 'boutique') { ?>
            <!-- Boutique Name -->
            <div>
              <label for="nom_boutique" class="block text-sm font-semibold text-[var(--text-secondary)] mb-2">
                <i class="fas fa-store mr-2 text-blue-500"></i>Nom de la boutique
              </label>
              <div class="relative">
                <input required type="text" name="nom_boutique" id="nom_boutique" 
                       class="w-full input-glass px-4 py-3.5 text-base placeholder:text-[var(--text-muted)]"
                       placeholder="Ex: Butembo Rawbank">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                  <i class="fas fa-building text-[var(--text-muted)] opacity-50"></i>
                </div>
              </div>
              <p class="mt-1.5 text-xs text-[var(--text-muted)]">
                <i class="fas fa-info-circle mr-1"></i>Saisissez le nom exact de votre boutique
              </p>
            </div>
          <?php } else { ?>
            <!-- Email -->
            <div>
              <label for="email" class="block text-sm font-semibold text-[var(--text-secondary)] mb-2">
                <i class="fas fa-envelope mr-2 text-blue-500"></i>Adresse email professionnelle
              </label>
              <div class="relative">
                <input required type="email" name="email" id="email" 
                       class="w-full input-glass px-4 py-3.5 text-base placeholder:text-[var(--text-muted)]"
                       placeholder="votre.email@newgraceservice.com"
                       autocomplete="email">
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                  <i class="fas fa-user text-[var(--text-muted)] opacity-50"></i>
                </div>
              </div>
            </div>
          <?php } ?>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-semibold text-[var(--text-secondary)] mb-2">
              <i class="fas fa-lock mr-2 text-blue-500"></i>Mot de passe sécurisé
            </label>
            <div class="relative">
              <input required type="password" name="password" id="password" 
                     class="w-full input-glass px-4 py-3.5 pr-12 text-base placeholder:text-[var(--text-muted)]"
                     placeholder="••••••••"
                     autocomplete="current-password">
              <button type="button" id="togglePassword" 
                      class="absolute inset-y-0 right-0 pr-3 flex items-center text-[var(--text-muted)] hover:text-blue-500 transition-colors p-2 focus-visible:ring-2 focus-visible:ring-blue-400 rounded-lg"
                      aria-label="Afficher/masquer le mot de passe">
                <i id="eyeIcon" class="far fa-eye"></i>
              </button>
            </div>
          </div>

          <!-- Submit -->
          <div class="pt-2">
            <button type="submit" 
                    class="w-full btn-glass py-4 rounded-xl text-base flex items-center justify-center gap-3 group">
              <i class="fas fa-sign-in-alt group-hover:scale-110 transition-transform"></i>
              <span>Se connecter à l'espace NGS</span>
            </button>
          </div>
        </form>
        <?php } ?>

        <!-- ============================================ -->
        <!-- FOOTER LINKS                                 -->
        <!-- ============================================ -->
        <div class="mt-8 pt-6 border-t border-[var(--divider)]">
          
          <?php if (!empty($userType)) { ?>
            <!-- Change profile -->
            <div class="text-center mb-5">
              <p class="text-sm text-[var(--text-muted)] mb-2">
                Vous n'êtes pas 
                <?php 
                if ($userType === 'pdg') echo 'PDG';
                elseif ($userType === 'boutique') echo 'une boutique';
                else echo 'IT';
                ?> ?
              </p>
              <a href="login.php?change_profile=true" 
                 class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl glass text-sm font-medium text-[var(--text-secondary)] hover:bg-white/20 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-blue-400">
                <i class="fas fa-sync-alt"></i>
                Changer de profil
              </a>
            </div>
          <?php } ?>

          <!-- Support -->
          <div class="glass rounded-2xl p-5">
            <h4 class="font-bold text-[var(--text-primary)] text-sm mb-3 flex items-center gap-2">
              <i class="fas fa-headset text-blue-500"></i>Support technique
            </h4>
            <div class="space-y-2.5 text-sm">
              <a href="mailto:it@newgraceservice.com" class="flex items-center gap-3 text-[var(--text-secondary)] hover:text-blue-500 transition-colors">
                <i class="fas fa-envelope text-blue-500 w-4 text-center"></i>
                <span>it@newgraceservice.com</span>
              </a>
              <a href="tel:+243977421421" class="flex items-center gap-3 text-[var(--text-secondary)] hover:text-blue-500 transition-colors">
                <i class="fas fa-phone text-blue-500 w-4 text-center"></i>
                <span>+243 977 421 421</span>
              </a>
            </div>
          </div>

        </div>

      </div>

    </div>
  </main>

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
    // TOGGLE PASSWORD VISIBILITY
    // ============================================
    const togglePasswordBtn = document.getElementById('togglePassword');
    if (togglePasswordBtn) {
      togglePasswordBtn.addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          eyeIcon.classList.remove('fa-eye');
          eyeIcon.classList.add('fa-eye-slash');
        } else {
          passwordInput.type = 'password';
          eyeIcon.classList.remove('fa-eye-slash');
          eyeIcon.classList.add('fa-eye');
        }
      });
    }

    // ============================================
    // USER TYPE SELECTION
    // ============================================
    const userTypeInputs = document.querySelectorAll('.user-type-input');
    const continueSection = document.getElementById('continueSection');
    
    if (userTypeInputs.length > 0 && continueSection) {
      userTypeInputs.forEach(input => {
        input.addEventListener('change', function() {
          if (this.checked) {
            continueSection.classList.remove('hidden');
            continueSection.classList.add('animate-fade-in');
            
            // Scroll to button
            setTimeout(() => {
              continueSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 200);
          }
        });
      });
    }

    // ============================================
    // FORM SUBMISSION LOADING STATE
    // ============================================
    const loginForm = document.querySelector('form[action*="login_process"]');
    if (loginForm) {
      loginForm.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn && !this.classList.contains('submitting')) {
          this.classList.add('submitting');
          const originalHTML = submitBtn.innerHTML;
          
          submitBtn.innerHTML = `
            <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span>Connexion en cours...</span>
          `;
          submitBtn.disabled = true;
        }
      });
    }

    // ============================================
    // AUTO-FOCUS FIRST INPUT
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
      const firstInput = document.querySelector('input[type="text"], input[type="email"]');
      if (firstInput) {
        setTimeout(() => firstInput.focus(), 400);
      }
    });

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
      // Alt+1, Alt+2, Alt+3 for user type selection
      if (e.altKey) {
        const shortcuts = { '1': 'pdg', '2': 'boutique', '3': 'it' };
        if (shortcuts[e.key]) {
          document.getElementById(shortcuts[e.key])?.click();
        }
      }
      
      // Ctrl+P to focus password
      if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        document.getElementById('password')?.focus();
      }
    });
  </script>

  <?php
  // Nettoyer le message de session après affichage
  unset($_SESSION['msg']);
  ?>
</body>
</html>