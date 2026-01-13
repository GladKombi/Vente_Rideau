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

    <title><?php echo $pageTitle; ?> - New Grace Service</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a8a;
            --secondary: #3b82f6;
            --accent: #60a5fa;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(30, 58, 138, 0.1);
        }
        
        .gradient-primary {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }
        
        .gradient-pdg {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        }
        
        .gradient-boutique {
            background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
        }
        
        .gradient-it {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(30, 58, 138, 0.3);
        }
        
        .user-type-card {
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            background: white;
        }
        
        .user-type-card:hover {
            border-color: #3b82f6;
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(30, 58, 138, 0.15);
        }
        
        .user-type-input {
            display: none;
        }
        
        .user-type-input:checked + .user-type-card {
            border-color: #3b82f6;
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.05), rgba(59, 130, 246, 0.05));
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 15px 40px rgba(30, 58, 138, 0.15);
        }
        
        .input-field {
            background: white;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .glow {
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.3);
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>

<body class="text-gray-800">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-blue-100 shadow-sm">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-lg gradient-primary flex items-center justify-center shadow-lg">
                        <span class="font-bold text-white text-lg">NGS</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">New Grace Service</h1>
                        <p class="text-sm text-gray-600">Espace de connexion</p>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-600 hover:text-blue-600 transition flex items-center">
                        <i class="fas fa-home mr-2"></i>
                        <span class="hidden md:inline">Retour à l'accueil</span>
                    </a>
                    <a href="#aide" class="text-gray-600 hover:text-blue-600 transition">
                        <i class="fas fa-question-circle"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Notification Message -->
            <?php if (isset($_SESSION['msg']) && !empty($_SESSION['msg'])) { ?>
                <div class="mb-8 animate-slide-up">
                    <div class="bg-red-50 border-l-4 border-red-500 rounded-r-lg p-4 shadow-md">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="font-bold text-red-800 mb-1">Alerte de sécurité</h4>
                                    <p class="text-sm text-red-700"><?= htmlspecialchars($_SESSION['msg']) ?></p>
                                </div>
                            </div>
                            <button onclick="this.parentElement.parentElement.style.display='none'" 
                                    class="text-red-400 hover:text-red-600 transition-colors p-2 rounded-full hover:bg-red-100">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <!-- Main Login Card -->
            <div class="login-container rounded-3xl shadow-2xl p-6 md:p-8 animate-slide-up">
                <!-- Header -->
                <div class="text-center mb-3">
                    <div class="w-20 h-20 rounded-2xl gradient-primary flex items-center justify-center mx-auto mb-6 pulse">
                        <i class="fas fa-lock text-white text-3xl"></i>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-2"><?php echo $pageTitle; ?></h1>
                    <p class="text-gray-600 text-lg">Accédez à votre espace de gestion</p>
                </div>

                <!-- User Type Selection (only show if no specific type is selected in session) -->
                <?php if (empty($userType)) { ?>
                <form action="login.php" method="POST" id="userTypeForm">
                    <div class="mb-3">
                        <label class="block text-xl font-medium text-gray-900 mb-2 text-center">
                            <i class="fas fa-user-tag mr-2 text-gradient"></i>
                            <span class="text-gradient">Sélectionnez votre profil</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- PDG Option -->
                            <input type="radio" name="user_type" value="pdg" id="pdg" class="user-type-input">
                            <label for="pdg" class="user-type-card rounded-2xl p-6 text-center cursor-pointer">
                                <div class="w-16 h-16 rounded-full gradient-pdg flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <i class="fas fa-crown text-white text-2xl"></i>
                                </div>
                                <h3 class="font-bold text-xl text-gray-900 mb-3">Direction Générale</h3>
                                <p class="text-gray-600 text-sm mb-4">Accès complet à toutes les fonctionnalités</p>
                               
                            </label>
                            
                            <!-- Boutique Option -->
                            <input type="radio" name="user_type" value="boutique" id="boutique" class="user-type-input">
                            <label for="boutique" class="user-type-card rounded-2xl p-6 text-center cursor-pointer">
                                <div class="w-16 h-16 rounded-full gradient-boutique flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <i class="fas fa-store text-white text-2xl"></i>
                                </div>
                                <h3 class="font-bold text-xl text-gray-900 mb-3">Gestion Boutique</h3>
                                <p class="text-gray-600 text-sm mb-4">Gestion quotidienne des opérations</p>
                                
                            </label>
                            
                            <!-- IT Option -->
                            <input type="radio" name="user_type" value="it" id="it" class="user-type-input">
                            <label for="it" class="user-type-card rounded-2xl p-6 text-center cursor-pointer">
                                <div class="w-16 h-16 rounded-full gradient-it flex items-center justify-center mx-auto mb-4 shadow-lg">
                                    <i class="fas fa-server text-white text-2xl"></i>
                                </div>
                                <h3 class="font-bold text-xl text-gray-900 mb-3">Support Technique</h3>
                                <p class="text-gray-600 text-sm mb-4">Administration et maintenance système</p>
                               
                            </label>
                        </div>
                    </div>
                    
                    <!-- Continue Button (hidden until a user type is selected) -->
                    <div id="continueSection" class="hidden animate-fade-in">
                        <button type="submit" 
                                class="w-full py-4 px-6 btn-primary text-white font-bold text-lg rounded-xl shadow-lg hover:shadow-xl transition-all">
                            <span class="flex items-center justify-center">
                                <span>Continuer vers la connexion</span>
                                <i class="fas fa-arrow-right ml-3 text-xl"></i>
                            </span>
                        </button>
                    </div>
                </form>
                <?php } else { ?>

                <!-- Login Form for specific user type -->
                <form action="models/login_process.php" method="POST" class="space-y-8 animate-fade-in">
                    <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($userType); ?>">
                    
                    <!-- User Type Header -->
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center px-6 py-3 rounded-full text-lg font-medium mb-4 shadow-md
                            <?php 
                            if ($userType === 'pdg') echo 'gradient-pdg text-white';
                            elseif ($userType === 'boutique') echo 'gradient-boutique text-white';
                            else echo 'gradient-it text-white';
                            ?>">
                            <i class="fas fa-<?php 
                                if ($userType === 'pdg') echo 'crown mr-3';
                                elseif ($userType === 'boutique') echo 'store mr-3';
                                else echo 'server mr-3';
                            ?>"></i>
                            <span>
                            <?php 
                            if ($userType === 'pdg') echo 'DIRECTION GÉNÉRALE';
                            elseif ($userType === 'boutique') echo 'GESTION BOUTIQUE';
                            else echo 'SUPPORT TECHNIQUE';
                            ?>
                            </span>
                        </div>
                        <p class="text-gray-600">
                            <?php 
                            if ($userType === 'pdg') echo 'Accès au tableau de bord global et aux rapports stratégiques';
                            elseif ($userType === 'boutique') echo 'Accès à la gestion des ventes, stocks et transferts';
                            else echo 'Accès à l\'administration système et la maintenance';
                            ?>
                        </p>
                    </div>

                    <div class="space-y-6">
                        <?php if ($userType === 'boutique') { ?>
                        <!-- Boutique Login - Nom de boutique -->
                        <div>
                            <label for="nom_boutique" class="block text-sm font-medium text-gray-900 mb-3">
                                <span class="flex items-center">
                                    <i class="fas fa-store mr-3 text-blue-500"></i>
                                    <span>Nom de la boutique</span>
                                </span>
                            </label>
                            <div class="relative group">
                                <input required type="text" name="nom_boutique" id="nom_boutique" 
                                       class="w-full px-5 py-4 input-field rounded-xl focus:outline-none text-gray-900 placeholder-gray-500 text-lg"
                                       placeholder="Ex: Butembo Rawbank">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                    <i class="fas fa-building text-gray-400"></i>
                                </div>
                                <div class="mt-2 text-sm text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Saisissez le nom exact de votre boutique
                                </div>
                            </div>
                        </div>
                        <?php } else { ?>
                        <!-- PDG/IT Login - Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-900 mb-3">
                                <span class="flex items-center">
                                    <i class="fas fa-envelope mr-3 text-blue-500"></i>
                                    <span>Adresse email professionnelle</span>
                                </span>
                            </label>
                            <div class="relative group">
                                <input required type="email" name="email" id="email" 
                                       class="w-full px-5 py-4 input-field rounded-xl focus:outline-none text-gray-900 placeholder-gray-500 text-lg"
                                       placeholder="votre.email@newgraceservice.com">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                
                            </div>
                        </div>
                        <?php } ?>

                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-900 mb-3">
                                <span class="flex items-center">
                                    <i class="fas fa-lock mr-3 text-blue-500"></i>
                                    <span>Mot de passe sécurisé</span>
                                </span>
                            </label>
                            <div class="relative group">
                                <input required type="password" name="password" id="password" 
                                       class="w-full px-5 py-4 input-field rounded-xl focus:outline-none text-gray-900 placeholder-gray-500 text-lg pr-12"
                                       placeholder="••••••••">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                    <button type="button" id="togglePassword" 
                                            class="text-gray-500 hover:text-blue-600 transition-colors p-2 rounded-full hover:bg-blue-50">
                                        <i id="eyeIcon" class="fas fa-eye"></i>
                                    </button>
                                </div>
                                
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-6">
                            <button type="submit" 
                                    class="w-full py-5 px-6 btn-primary text-white font-bold text-lg rounded-xl shadow-lg hover:shadow-xl transition-all group">
                                <span class="flex items-center justify-center">
                                    <i class="fas fa-sign-in-alt mr-4 text-xl group-hover:animate-pulse"></i>
                                    <span>Se connecter à l'espace NGS</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
                <?php } ?>

                <!-- Additional Links -->
                <div class="mt-12 pt-8 border-t border-gray-200">
                    <?php if (!empty($userType)) { ?>
                        <div class="text-center mb-6">
                            <p class="text-gray-600 mb-2">
                                Vous n'êtes pas 
                                <?php 
                                if ($userType === 'pdg') echo 'PDG';
                                elseif ($userType === 'boutique') echo 'une boutique';
                                else echo 'IT';
                                ?> ? 
                            </p>
                            <a href="login.php?change_profile=true" 
                               class="inline-flex items-center px-5 py-2 border-2 border-blue-500 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors font-medium">
                                <i class="fas fa-sync-alt mr-3"></i>
                                Changer de profil de connexion
                            </a>
                        </div>
                    <?php } ?>
                    
                    <!-- Support Information -->
                    <div class="bg-blue-50 rounded-2xl p-6">
                        <h4 class="font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-headset mr-3 text-blue-500"></i>
                            Support technique
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div class="flex items-center text-gray-700">
                                <i class="fas fa-envelope mr-3 text-blue-500"></i>
                                <div>
                                    <div class="font-medium">Email support</div>
                                    <div class="text-blue-600 font-semibold">it@newgraceservice.com</div>
                                </div>
                            </div>
                            <div class="flex items-center text-gray-700">
                                <i class="fas fa-phone mr-3 text-blue-500"></i>
                                <div>
                                    <div class="font-medium">Téléphone support</div>
                                    <div class="text-blue-600 font-semibold">+243 977 421 421</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
        </div>
    </main>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
                this.classList.add('text-blue-600');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
                this.classList.remove('text-blue-600');
            }
        });

        // User type selection functionality
        const userTypeInputs = document.querySelectorAll('.user-type-input');
        const continueSection = document.getElementById('continueSection');
        
        if (userTypeInputs && continueSection) {
            userTypeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.checked) {
                        // Show continue button with animation
                        continueSection.classList.remove('hidden');
                        continueSection.classList.add('animate-fade-in');
                        
                        // Add visual feedback
                        userTypeInputs.forEach(inp => {
                            const label = document.querySelector(`label[for="${inp.id}"]`);
                            label.classList.remove('glow');
                        });
                        const currentLabel = document.querySelector(`label[for="${this.id}"]`);
                        currentLabel.classList.add('glow');
                        
                        // Scroll to continue button smoothly
                        setTimeout(() => {
                            continueSection.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'center' 
                            });
                        }, 300);
                    }
                });
                
                // Add hover effect to cards
                const label = document.querySelector(`label[for="${input.id}"]`);
                if (label) {
                    label.addEventListener('mouseenter', function() {
                        if (!input.checked) {
                            this.style.transform = 'translateY(-5px)';
                        }
                    });
                    
                    label.addEventListener('mouseleave', function() {
                        if (!input.checked) {
                            this.style.transform = 'translateY(0)';
                        }
                    });
                }
            });
        }

        // Form submission loading animation
        const loginForm = document.querySelector('form[action*="login_process"]');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    const originalHTML = submitButton.innerHTML;
                    
                    submitButton.innerHTML = `
                        <span class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Connexion en cours...
                        </span>
                    `;
                    submitButton.disabled = true;
                    submitButton.classList.add('opacity-75');
                    
                    // Prevent multiple submissions
                    this.classList.add('submitting');
                }
            });
        }

        // Keyboard shortcuts for accessibility
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case '1':
                        document.getElementById('pdg')?.click();
                        break;
                    case '2':
                        document.getElementById('boutique')?.click();
                        break;
                    case '3':
                        document.getElementById('it')?.click();
                        break;
                    case 'Enter':
                        const continueBtn = document.querySelector('#continueSection button');
                        if (continueBtn && continueBtn.offsetParent !== null) {
                            continueBtn.click();
                        }
                        break;
                }
            }
            
            // Focus on password field with Ctrl+P
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                document.getElementById('password')?.focus();
            }
        });

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"], input[type="email"]');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 300);
            }
            
            // Add subtle pulse animation to security icons
            const securityIcons = document.querySelectorAll('.fa-shield-alt, .fa-user-shield, .fa-lock');
            securityIcons.forEach((icon, index) => {
                icon.style.animationDelay = `${index * 0.2}s`;
                icon.classList.add('animate-pulse');
            });
        });

        // Input validation feedback
        const inputs = document.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('border-red-500');
                } else {
                    this.classList.remove('border-red-500');
                    this.classList.add('border-green-500');
                    setTimeout(() => {
                        this.classList.remove('border-green-500');
                    }, 1000);
                }
            });
        });
    </script>

    <?php
    // Nettoyer le message de session après affichage
    unset($_SESSION['msg']);
    ?>
</body>

</html>