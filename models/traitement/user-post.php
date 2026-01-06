<?php
require_once '../../connexion/connexion.php';

// Vérification de l'authentification PDG
if (!isset($_SESSION['user_type']) || strtolower($_SESSION['user_type']) !== 'pdg') {
    header('Location: ../login.php');
    exit;
}

// Définir le type de contenu
header('Content-Type: application/json');

// Initialisation de la réponse
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

try {
    // Vérifier la connexion à la base de données
    if (!$pdo) {
        throw new Exception('Erreur de connexion à la base de données');
    }

    // --- TRAITEMENT DE L'AJOUT D'UN UTILISATEUR ---
    if (isset($_POST['ajouter_utilisateur'])) {
        // Récupération et validation des données
        $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';
        $actif = isset($_POST['actif']) ? 1 : 0;

        // Validation des champs requis
        if (empty($nom_utilisateur) || empty($role) || empty($mot_de_passe)) {
            throw new Exception('Tous les champs obligatoires doivent être remplis');
        }

        // Validation du format d'email si fourni
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide');
        }

        // Validation du rôle
        if (!in_array($role, ['PDG', 'IT'])) {
            throw new Exception('Rôle invalide');
        }

        // Vérifier si le nom d'utilisateur existe déjà (en excluant ceux avec statut=1)
        $checkStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE nom_utilisateur = ? AND statut = 0");
        $checkStmt->execute([$nom_utilisateur]);
        if ($checkStmt->fetch()) {
            throw new Exception('Ce nom d\'utilisateur est déjà utilisé');
        }

        // Vérifier si l'email existe déjà (si fourni, en excluant ceux avec statut=1)
        if (!empty($email)) {
            $checkEmailStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND statut = 0");
            $checkEmailStmt->execute([$email]);
            if ($checkEmailStmt->fetch()) {
                throw new Exception('Cet email est déjà utilisé');
            }
        }

        // Hasher le mot de passe
        $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

        // Préparation de la requête d'insertion
        // Le statut est à 0 par défaut pour les nouveaux enregistrements
        $stmt = $pdo->prepare("
            INSERT INTO utilisateurs 
            (nom_utilisateur, email, role, mot_de_passe, actif, statut) 
            VALUES (?, ?, ?, ?, ?, 0)
        ");

        // Exécution
        if ($stmt->execute([$nom_utilisateur, $email, $role, $mot_de_passe_hash, $actif])) {
            $response['success'] = true;
            $response['message'] = 'Utilisateur ajouté avec succès';
            $_SESSION['flash_message'] = [
                'text' => 'Utilisateur ajouté avec succès',
                'type' => 'success'
            ];
        } else {
            throw new Exception('Erreur lors de l\'ajout de l\'utilisateur');
        }
    }

    // --- TRAITEMENT DE LA MODIFICATION D'UN UTILISATEUR ---
    elseif (isset($_POST['modifier_utilisateur'])) {
        // Récupération et validation des données
        $id = intval($_POST['id'] ?? 0);
        $nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $mot_de_passe = $_POST['mot_de_passe'] ?? '';
        $actif = isset($_POST['actif']) ? 1 : 0;

        // Validation de l'ID
        if ($id <= 0) {
            throw new Exception('ID utilisateur invalide');
        }

        // Validation des champs requis
        if (empty($nom_utilisateur) || empty($role)) {
            throw new Exception('Les champs obligatoires doivent être remplis');
        }

        // Validation du format d'email si fourni
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format d\'email invalide');
        }

        // Validation du rôle
        if (!in_array($role, ['PDG', 'IT'])) {
            throw new Exception('Rôle invalide');
        }

        // Vérifier si l'utilisateur existe et n'est pas supprimé (statut=0)
        $checkStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ? AND statut = 0");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            throw new Exception('Utilisateur non trouvé ou déjà supprimé');
        }

        // Vérifier si le nom d'utilisateur existe déjà pour un autre utilisateur non supprimé
        $checkUsernameStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE nom_utilisateur = ? AND id != ? AND statut = 0");
        $checkUsernameStmt->execute([$nom_utilisateur, $id]);
        if ($checkUsernameStmt->fetch()) {
            throw new Exception('Ce nom d\'utilisateur est déjà utilisé par un autre utilisateur');
        }

        // Vérifier si l'email existe déjà pour un autre utilisateur non supprimé (si fourni)
        if (!empty($email)) {
            $checkEmailStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ? AND statut = 0");
            $checkEmailStmt->execute([$email, $id]);
            if ($checkEmailStmt->fetch()) {
                throw new Exception('Cet email est déjà utilisé par un autre utilisateur');
            }
        }

        // Préparation de la requête de mise à jour
        if (!empty($mot_de_passe)) {
            // Si un nouveau mot de passe est fourni, le mettre à jour
            $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE utilisateurs 
                SET nom_utilisateur = ?, email = ?, role = ?, mot_de_passe = ?, actif = ?
                WHERE id = ? AND statut = 0
            ");
            $params = [$nom_utilisateur, $email, $role, $mot_de_passe_hash, $actif, $id];
        } else {
            // Sinon, conserver l'ancien mot de passe
            $stmt = $pdo->prepare("
                UPDATE utilisateurs 
                SET nom_utilisateur = ?, email = ?, role = ?, actif = ?
                WHERE id = ? AND statut = 0
            ");
            $params = [$nom_utilisateur, $email, $role, $actif, $id];
        }

        // Exécution
        if ($stmt->execute($params)) {
            $response['success'] = true;
            $response['message'] = 'Utilisateur modifié avec succès';
            $_SESSION['flash_message'] = [
                'text' => 'Utilisateur modifié avec succès',
                'type' => 'success'
            ];
        } else {
            throw new Exception('Erreur lors de la modification de l\'utilisateur');
        }
    }

    // --- TRAITEMENT DE L'ACTIVATION/DÉSACTIVATION ---
    elseif (isset($_POST['toggle_actif'])) {
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('ID utilisateur invalide');
        }

        // Récupérer l'état actuel (uniquement pour les utilisateurs non supprimés)
        $checkStmt = $pdo->prepare("SELECT actif FROM utilisateurs WHERE id = ? AND statut = 0");
        $checkStmt->execute([$id]);
        $user = $checkStmt->fetch();

        if (!$user) {
            throw new Exception('Utilisateur non trouvé ou déjà supprimé');
        }

        // Inverser l'état actif
        $newActif = $user['actif'] ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ? AND statut = 0");
        if ($stmt->execute([$newActif, $id])) {
            $response['success'] = true;
            $response['message'] = $newActif ? 'Utilisateur activé avec succès' : 'Utilisateur désactivé avec succès';
            $_SESSION['flash_message'] = [
                'text' => $newActif ? 'Utilisateur activé avec succès' : 'Utilisateur désactivé avec succès',
                'type' => 'success'
            ];
        } else {
            throw new Exception('Erreur lors de la modification du statut');
        }
    }

    // --- TRAITEMENT DE LA SUPPRESSION (ARCHIVAGE) - Changement du statut à 1 ---
    elseif (isset($_POST['supprimer_utilisateur'])) {
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('ID utilisateur invalide');
        }

        // Vérifier que l'utilisateur ne se supprime pas lui-même
        if ($id == ($_SESSION['user_id'] ?? 0)) {
            throw new Exception('Vous ne pouvez pas supprimer votre propre compte');
        }

        // Vérifier que l'utilisateur existe et n'est pas déjà supprimé
        $checkStmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ? AND statut = 0");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            throw new Exception('Utilisateur non trouvé ou déjà supprimé');
        }

        // Mettre à jour le statut à 1 (soft delete)
        $stmt = $pdo->prepare("UPDATE utilisateurs SET statut = 1 WHERE id = ?");
        if ($stmt->execute([$id])) {
            $response['success'] = true;
            $response['message'] = 'Utilisateur archivé avec succès';
            $_SESSION['flash_message'] = [
                'text' => 'Utilisateur archivé avec succès',
                'type' => 'success'
            ];
        } else {
            throw new Exception('Erreur lors de l\'archivage de l\'utilisateur');
        }
    }

    // --- AUCUNE ACTION VALIDE ---
    else {
        throw new Exception('Action non reconnue');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $_SESSION['flash_message'] = [
        'text' => $e->getMessage(),
        'type' => 'error'
    ];
}

// Définir la redirection
$response['redirect'] = '../../views/utilisateurs.php';

// Si ce n'est pas une requête AJAX, faire une redirection
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: ' . $response['redirect']);
    exit;
} else {
    // Pour les requêtes AJAX, retourner JSON
    echo json_encode($response);
    exit;
}