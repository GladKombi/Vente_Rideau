<?php
# Se connecter à la BD
include '../../connexion/connexion.php';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Variable pour stocker les messages de statut
        $message = [];
        
        switch($_POST['action']) {
            case 'ajouter':
                // Traitement de l'ajout
                $nom = $_POST['nom'];
                $email = $_POST['email'];
                $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
                $role = $_POST['role'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                // Gestion de l'upload d'image
                $profil = 'default-avatar.png'; // Image par défaut
                if (isset($_FILES['profil']) && $_FILES['profil']['error'] === 0) {
                    $uploadDir = '../../uploads/profils/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileName = uniqid() . '_' . $_FILES['profil']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['profil']['tmp_name'], $filePath)) {
                        $profil = $fileName;
                    }
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role, profil, statut) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nom, $email, $mot_de_passe, $role, $profil, $statut]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Utilisateur ajouté avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de l\'ajout de l\'utilisateur: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'modifier':
                // Traitement de la modification
                $id = $_POST['id'];
                $nom = $_POST['nom'];
                $email = $_POST['email'];
                $role = $_POST['role'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                // Vérifier si un nouveau mot de passe est fourni
                if (!empty($_POST['mot_de_passe'])) {
                    $mot_de_passe = password_hash($_POST['mot_de_passe'], PASSWORD_DEFAULT);
                    $sql = "UPDATE utilisateurs SET nom=?, email=?, mot_de_passe=?, role=?, statut=? WHERE id=?";
                    $params = [$nom, $email, $mot_de_passe, $role, $statut, $id];
                } else {
                    $sql = "UPDATE utilisateurs SET nom=?, email=?, role=?, statut=? WHERE id=?";
                    $params = [$nom, $email, $role, $statut, $id];
                }
                
                // Gestion de l'upload d'image
                if (isset($_FILES['profil']) && $_FILES['profil']['error'] === 0) {
                    $uploadDir = '../../uploads/profils/';
                    $fileName = uniqid() . '_' . $_FILES['profil']['name'];
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['profil']['tmp_name'], $filePath)) {
                        // Récupérer l'ancienne image pour la supprimer
                        $oldStmt = $pdo->prepare("SELECT profil FROM utilisateurs WHERE id=?");
                        $oldStmt->execute([$id]);
                        $oldProfil = $oldStmt->fetchColumn();
                        
                        if ($oldProfil !== 'default-avatar.png' && file_exists($uploadDir . $oldProfil)) {
                            unlink($uploadDir . $oldProfil);
                        }
                        
                        // Mettre à jour avec la nouvelle image
                        if (strpos($sql, 'profil') === false) {
                            $sql = str_replace('SET nom=', 'SET profil=?, nom=', $sql);
                            array_unshift($params, $fileName);
                        }
                    }
                }
                
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $message = [
                        'type' => 'success',
                        'text' => 'Utilisateur modifié avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la modification de l\'utilisateur: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'supprimer':
                // Soft delete
                $id = $_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET statut=1 WHERE id=?");
                    $stmt->execute([$id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Utilisateur désactivé avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la désactivation de l\'utilisateur: ' . $e->getMessage()
                    ];
                }
                break;
        }
        
        // Stocker le message dans la session
        $_SESSION['message'] = $message;
        
        // Redirection vers la page des utilisateurs
        header('Location: ../../views/utilisateurs.php');
        exit;
    }
}