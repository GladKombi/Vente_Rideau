<?php
require_once '../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_type = $_POST['user_type'] ?? '';

    // Validation du type d'utilisateur
    if (empty($user_type) || !in_array($user_type, ['pdg', 'boutique', 'it'])) {
        $_SESSION['msg'] = "Type d'utilisateur invalide.";
        header('Location: ../login.php');
        exit;
    }

    try {
        switch ($user_type) {
            case 'pdg':
            case 'it':
                // Connexion PDG ou IT (table utilisateurs)
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';

                if (empty($email) || empty($password)) {
                    $_SESSION['msg'] = "Veuillez remplir tous les champs.";
                    header('Location: ../login.php');
                    exit;
                }

                // Vérifier dans la table utilisateurs
                $query = "SELECT * FROM utilisateurs 
                         WHERE email = :email 
                         AND role = :role 
                         AND statut = 0 
                         AND actif = 1";

                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    'email' => $email,
                    'role' => strtoupper($user_type)
                ]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['mot_de_passe'])) {
                    // Authentification réussie pour PDG/IT
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = $user_type;
                    $_SESSION['user_name'] = $user['nom_utilisateur'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    // Redirection vers le dashboard approprié
                    $redirect = ($user_type === 'pdg') ? 'dashboard_pdg.php' : 'dashboard_it.php';
                    header("Location: ../views/$redirect");
                    exit;
                } else {
                    $_SESSION['msg'] = "Email ou mot de passe incorrect.";
                    header('Location: ../login.php');
                    exit;
                }
                break;

            case 'boutique':
                // Connexion Boutique (table boutiques)
                $nom_boutique = $_POST['nom_boutique'] ?? '';
                $password = $_POST['password'] ?? '';

                if (empty($nom_boutique) || empty($password)) {
                    $_SESSION['msg'] = "Veuillez remplir tous les champs.";
                    header('Location: ../login.php');
                    exit;
                }

                // Vérifier dans la table boutiques
                $query = "SELECT * FROM boutiques 
                         WHERE nom = :nom_boutique 
                         AND statut = 0 
                         AND actif = 1";

                $stmt = $pdo->prepare($query);
                $stmt->execute(['nom_boutique' => $nom_boutique]);
                $boutique = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($boutique && password_verify($password, $boutique['password'])) {
                    // Authentification réussie pour la boutique
                    $_SESSION['boutique_id'] = $boutique['id'];
                    $_SESSION['user_type'] = 'boutique';
                    $_SESSION['boutique_nom'] = $boutique['nom'];
                    $_SESSION['boutique_email'] = $boutique['email'];

                    // Redirection vers le dashboard boutique
                    header("Location: ../views/dashboard_boutique.php");
                    exit;
                } else {
                    $_SESSION['msg'] = "Nom de boutique ou mot de passe incorrect.";
                    header('Location: ../login.php');
                    exit;
                }
                break;
        }
    } catch (PDOException $e) {
        $_SESSION['msg'] = "Erreur de connexion à la base de données.";
        error_log("Login error: " . $e->getMessage());
        header('Location: ../login.php');
        exit;
    }
} else {
    header('Location: ../login.php');
    exit;
}
