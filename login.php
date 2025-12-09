<?php
include_once 'connexion/connexion.php';

// Déterminer le type d'utilisateur depuis la session
$userType = '';
$pageTitle = 'Connexion';

// Vérifier si un type d'utilisateur est stocké en session
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

// Gérer la sélection du type d'utilisateur via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_type'])) {
    $_SESSION['user_type'] = $_POST['user_type'];
    header('Location: login.php');
    exit;
}

// Gérer le changement de profil
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

    <title><?php echo $pageTitle; ?> - Julien_Rideau</title>
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
            --primary: #0A2540;
            --secondary: #7B61FF;
            --accent: #00D4AA;
            --light: #F8FAFC;
            --dark: #1E293B;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #FFFFFF;
        }
        
        .font-display {
            font-family: 'Outfit', sans-serif;
        }
        
        .login-section {
            background: linear-gradient(135deg, #0A2540 0%, #1E3A5F 100%);
            min-height: 100vh;
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
        
        .card-hover {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .input-focus:focus {
            border-color: #7B61FF;
            box-shadow: 0 0 0 3px rgba(123, 97, 255, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7B61FF 0%, #00D4AA 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .user-type-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        
        .user-type-card:hover {
            border-color: #7B61FF;
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.1);
        }
        
        .user-type-input {
            display: none;
        }
        
        .user-type-input:checked + .user-type-card {
            border-color: #7B61FF;
            background: rgba(255, 255, 255, 0.15);
        }
        
        .bg-pdg {
            background: linear-gradient(135deg, #0A2540 0%, #1E3A5F 100%);
        }
        
        .bg-boutique {
            background: linear-gradient(135deg, #7B61FF 0%, #00D4AA 100%);
        }
        
        .bg-it {
            background: linear-gradient(135deg, #1E293B 0%, #475569 100%);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="font-inter login-section">
    <!-- Header Navigation -->
    <nav class="bg-white/10 backdrop-blur-sm border-b border-white/20">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <a href="index.php" class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full gradient-accent flex items-center justify-center">
                        <span class="font-bold text-white text-sm">JR</span>
                    </div>
                    <span class="text-lg font-semibold text-white font-display">Julien_Rideau</span>
                </a>
                <a href="index.php" class="text-white/80 hover:text-white transition-colors text-sm flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Notification Message -->
            <?php if (isset($_SESSION['msg']) && !empty($_SESSION['msg'])) { ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-white/10 backdrop-blur-md rounded-lg shadow-md p-4 border-l-4 border-red-500">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="bg-red-500/20 p-2 rounded-full mr-3">
                                    <i class="fas fa-exclamation-circle text-red-500"></i>
                                </div>
                                <div>
                                    <p class="text-sm text-white"><?= htmlspecialchars($_SESSION['msg']) ?></p>
                                </div>
                            </div>
                            <button onclick="this.parentElement.parentElement.style.display='none'" class="text-white/70 hover:text-white transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <!-- Login Card -->
            <div class="card-hover rounded-2xl shadow-2xl p-6 md:p-8">
                <!-- Header -->
                <div class="text-center mb-8">
                    <div class="w-16 h-16 rounded-full gradient-accent flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-lock text-white text-2xl"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-white mb-2 font-display"><?php echo $pageTitle; ?></h1>
                    <p class="text-gray-300">Accédez à votre espace personnel</p>
                </div>

                <!-- User Type Selection (only show if no specific type is selected in session) -->
                <?php if (empty($userType)) { ?>
                <form action="login.php" method="POST" id="userTypeForm" class="animate-fade-in">
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-white mb-6 text-center text-lg">Sélectionnez votre profil :</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- PDG Option -->
                            <input type="radio" name="user_type" value="pdg" id="pdg" class="user-type-input">
                            <label for="pdg" class="user-type-card bg-pdg border border-white/10 rounded-xl p-6 text-center hover:shadow-lg transition-all cursor-pointer">
                                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-crown text-yellow-500 text-xl"></i>
                                </div>
                                <h3 class="font-bold text-xl text-white mb-2">PDG</h3>
                                <p class="text-gray-300 text-sm mb-3">Direction générale</p>
                                <div class="mt-4">
                                    <span class="inline-block px-3 py-1 bg-white/10 rounded-full text-xs text-white">
                                        <i class="fas fa-chart-line mr-1"></i> Dashboard complet
                                    </span>
                                </div>
                            </label>
                            
                            <!-- Boutique Option -->
                            <input type="radio" name="user_type" value="boutique" id="boutique" class="user-type-input">
                            <label for="boutique" class="user-type-card bg-boutique border border-white/10 rounded-xl p-6 text-center hover:shadow-lg transition-all cursor-pointer">
                                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-store text-white text-xl"></i>
                                </div>
                                <h3 class="font-bold text-xl text-white mb-2">Boutique</h3>
                                <p class="text-gray-300 text-sm mb-3">Gestion de boutique</p>
                                <div class="mt-4">
                                    <span class="inline-block px-3 py-1 bg-white/10 rounded-full text-xs text-white">
                                        <i class="fas fa-boxes mr-1"></i> Gestion stock & vente
                                    </span>
                                </div>
                            </label>
                            
                            <!-- IT Option -->
                            <input type="radio" name="user_type" value="it" id="it" class="user-type-input">
                            <label for="it" class="user-type-card bg-it border border-white/10 rounded-xl p-6 text-center hover:shadow-lg transition-all cursor-pointer">
                                <div class="w-12 h-12 rounded-full bg-white/10 flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-server text-white text-xl"></i>
                                </div>
                                <h3 class="font-bold text-xl text-white mb-2">IT</h3>
                                <p class="text-gray-300 text-sm mb-3">Support technique</p>
                                <div class="mt-4">
                                    <span class="inline-block px-3 py-1 bg-white/10 rounded-full text-xs text-white">
                                        <i class="fas fa-tools mr-1"></i> Administration système
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Continue Button (hidden until a user type is selected) -->
                    <div id="continueSection" class="hidden animate-fade-in">
                        <button type="submit" 
                                class="w-full py-4 px-6 btn-primary text-white font-semibold rounded-xl shadow-lg">
                            <span class="flex items-center justify-center text-lg">
                                Continuer vers la connexion
                                <i class="fas fa-arrow-right ml-3"></i>
                            </span>
                        </button>
                    </div>
                </form>
                <?php } else { ?>

                <!-- Login Form for specific user type -->
                <form action="models/login_process.php" method="POST" class="space-y-6 animate-fade-in">
                    <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($userType); ?>">
                    
                    <!-- User Type Badge -->
                    <div class="flex justify-center mb-6">
                        <span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium 
                            <?php 
                            if ($userType === 'pdg') echo 'bg-gradient-to-r from-yellow-500/20 to-yellow-600/20 text-yellow-300 border border-yellow-500/30';
                            elseif ($userType === 'boutique') echo 'bg-gradient-to-r from-purple-500/20 to-blue-500/20 text-purple-300 border border-purple-500/30';
                            else echo 'bg-gradient-to-r from-gray-500/20 to-gray-600/20 text-gray-300 border border-gray-500/30';
                            ?>">
                            <i class="fas fa-<?php 
                                if ($userType === 'pdg') echo 'crown mr-2';
                                elseif ($userType === 'boutique') echo 'store mr-2';
                                else echo 'server mr-2';
                            ?>"></i>
                            <?php 
                            if ($userType === 'pdg') echo 'PDG - Direction Générale';
                            elseif ($userType === 'boutique') echo 'Boutique - Gestion des stocks';
                            else echo 'IT - Support Technique';
                            ?>
                        </span>
                    </div>

                    <?php if ($userType === 'boutique') { ?>
                    <!-- Boutique Login - Nom de boutique -->
                    <div>
                        <label for="nom_boutique" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-store mr-2"></i>Nom de la boutique
                        </label>
                        <div class="relative">
                            <input required type="text" name="nom_boutique" id="nom_boutique" 
                                   class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl input-focus focus:outline-none transition-colors text-white placeholder-gray-400"
                                   placeholder="Ex: Paris Le Marais">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-building text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    <?php } else { ?>
                    <!-- PDG/IT Login - Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-envelope mr-2"></i>Adresse email
                        </label>
                        <div class="relative">
                            <input required type="email" name="email" id="email" 
                                   class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl input-focus focus:outline-none transition-colors text-white placeholder-gray-400"
                                   placeholder="exemple@julien-rideau.fr">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <!-- Password Field -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-white mb-2">
                            <i class="fas fa-lock mr-2"></i>Mot de passe
                        </label>
                        <div class="relative">
                            <input required type="password" name="password" id="password" 
                                   class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl input-focus focus:outline-none transition-colors text-white placeholder-gray-400 pr-10"
                                   placeholder="Votre mot de passe sécurisé">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <button type="button" id="togglePassword" class="text-gray-400 hover:text-white transition-colors">
                                    <i id="eyeIcon" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember_me" name="remember_me" type="checkbox" 
                                   class="h-4 w-4 text-secondary focus:ring-secondary border-gray-300 rounded">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-300">
                                Se souvenir de moi
                            </label>
                        </div>
                        <a href="#" class="text-sm text-secondary hover:text-accent transition-colors">
                            <i class="fas fa-key mr-1"></i>Mot de passe oublié ?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" 
                                class="w-full py-4 px-6 btn-primary text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all">
                            <span class="flex items-center justify-center text-lg">
                                <i class="fas fa-sign-in-alt mr-3"></i>
                                Se connecter
                            </span>
                        </button>
                    </div>
                </form>
                <?php } ?>

                <!-- Additional Links -->
                <div class="mt-8 text-center">
                    <?php if (!empty($userType)) { ?>
                        <p class="text-sm text-gray-300">
                            Vous n'êtes pas 
                            <?php 
                            if ($userType === 'pdg') echo 'PDG';
                            elseif ($userType === 'boutique') echo 'une boutique';
                            else echo 'IT';
                            ?> ? 
                            <a href="login.php?change_profile=true" class="text-secondary hover:text-accent font-medium transition-colors">
                                <i class="fas fa-sync-alt mr-1"></i>Changer de profil
                            </a>
                        </p>
                    <?php } ?>
                    
                    <div class="mt-6 pt-6 border-t border-white/10">
                        <p class="text-xs text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            Pour toute demande d'accès, contactez le service IT : it@julien-rideau.fr
                        </p>
                    </div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="mt-8 text-center animate-fade-in">
                <div class="inline-flex items-center space-x-4 text-white/60">
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt mr-2"></i>
                        <span class="text-sm">Connexion sécurisée SSL</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-user-shield mr-2"></i>
                        <span class="text-sm">Authentification 2FA disponible</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-database mr-2"></i>
                        <span class="text-sm">Données chiffrées</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
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

        // User type selection functionality
        const userTypeInputs = document.querySelectorAll('.user-type-input');
        const continueSection = document.getElementById('continueSection');
        
        if (userTypeInputs && continueSection) {
            userTypeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.checked) {
                        continueSection.classList.remove('hidden');
                        
                        // Scroll to continue button
                        continueSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
                
                // Add click animation to cards
                const label = document.querySelector(`label[for="${input.id}"]`);
                if (label) {
                    label.addEventListener('click', function() {
                        userTypeInputs.forEach(inp => {
                            if (inp.id !== input.id) {
                                document.querySelector(`label[for="${inp.id}"]`).classList.remove('ring-2', 'ring-secondary');
                            }
                        });
                        this.classList.add('ring-2', 'ring-secondary');
                    });
                }
            });
        }

        // Add loading state to form submission
        const loginForm = document.querySelector('form[action*="login_process"]');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    const originalText = submitButton.innerHTML;
                    
                    submitButton.innerHTML = `
                        <span class="flex items-center justify-center text-lg">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Connexion en cours...
                        </span>
                    `;
                    submitButton.disabled = true;
                    
                    // Re-enable after 5 seconds in case of error
                    setTimeout(() => {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }, 5000);
                }
            });
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+1 for PDG, Ctrl+2 for Boutique, Ctrl+3 for IT
            if (e.ctrlKey) {
                const userTypeInputs = document.querySelectorAll('.user-type-input');
                switch(e.key) {
                    case '1':
                        if (userTypeInputs[0]) userTypeInputs[0].click();
                        break;
                    case '2':
                        if (userTypeInputs[1]) userTypeInputs[1].click();
                        break;
                    case '3':
                        if (userTypeInputs[2]) userTypeInputs[2].click();
                        break;
                    case 'Enter':
                        const continueBtn = document.querySelector('#continueSection button');
                        if (continueBtn && !continueBtn.classList.contains('hidden')) {
                            continueBtn.click();
                        }
                        break;
                }
            }
        });
    </script>

    <?php
    // Nettoyer le message de session après affichage
    unset($_SESSION['msg']);
    // session_destroy();
    ?>
</body>

</html>