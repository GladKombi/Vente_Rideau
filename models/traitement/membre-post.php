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
                $postnom = $_POST['postnom'];
                $prenom = $_POST['prenom'];
                $adresse = $_POST['adresse'];
                $telephone = $_POST['telephone'];
                $email = $_POST['email'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO membres (nom, postnom, prenom, adresse, telephone, email, statut) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nom, $postnom, $prenom, $adresse, $telephone, $email, $statut]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Locataire ajouté avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de l\'ajout du locataire: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'modifier':
                // Traitement de la modification
                $id = $_POST['id'];
                $nom = $_POST['nom'];
                $postnom = $_POST['postnom'];
                $prenom = $_POST['prenom'];
                $adresse = $_POST['adresse'];
                $telephone = $_POST['telephone'];
                $email = $_POST['email'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                try {
                    $stmt = $pdo->prepare("UPDATE membres SET nom=?, postnom=?, prenom=?, adresse=?, telephone=?, email=?, statut=? WHERE id=?");
                    $stmt->execute([$nom, $postnom, $prenom, $adresse, $telephone, $email, $statut, $id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Locataire modifié avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la modification du locataire: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'supprimer':
                // Soft delete
                $id = $_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE membres SET statut=1 WHERE id=?");
                    $stmt->execute([$id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Locataire désactivé avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la désactivation du locataire: ' . $e->getMessage()
                    ];
                }
                break;
        }
        
        // Stocker le message dans la session
        $_SESSION['message'] = $message;
        
        // Redirection vers la page des membres
        header('Location: ../../views/membres.php');
        exit;
    }
}