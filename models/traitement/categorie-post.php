<?php
# Démarrer la session
session_start();

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
                $designation = $_POST['designation'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO categorie (designation, statut) VALUES (?, ?)");
                    $stmt->execute([$designation, $statut]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Catégorie ajoutée avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de l\'ajout de la catégorie: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'modifier':
                // Traitement de la modification
                $id = $_POST['id'];
                $designation = $_POST['designation'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                try {
                    $stmt = $pdo->prepare("UPDATE categorie SET designation=?, statut=? WHERE id=?");
                    $stmt->execute([$designation, $statut, $id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Catégorie modifiée avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la modification de la catégorie: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'supprimer':
                // Soft delete
                $id = $_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE categorie SET statut=1 WHERE id=?");
                    $stmt->execute([$id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Catégorie désactivée avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la désactivation de la catégorie: ' . $e->getMessage()
                    ];
                }
                break;
        }
        
        // Stocker le message dans la session
        $_SESSION['message'] = $message;
        
        // Redirection vers la page des catégories
        header('Location: ../../views/categories.php');
        exit;
    }
}