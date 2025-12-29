<?php
# transfert-post.php - Traitement des transferts
session_start();
include '../connexion.php';

// Vérification de l'authentification PDG
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'pdg') {
    header('Location: ../login.php');
    exit;
}

// Suppression d'un transfert
if (isset($_POST['supprimer_transfert'])) {
    $transfert_id = (int)$_POST['transfert_id'];
    
    try {
        // Vérifier si le transfert existe
        $query = $pdo->prepare("SELECT * FROM transferts WHERE id = ? AND statut = 0");
        $query->execute([$transfert_id]);
        $transfert = $query->fetch(PDO::FETCH_ASSOC);
        
        if (!$transfert) {
            $_SESSION['flash_message'] = [
                'text' => "Transfert introuvable ou déjà supprimé",
                'type' => "error"
            ];
            header('Location: ../pages/transferts.php');
            exit;
        }
        
        // Mettre à jour le statut à 1 (supprimé)
        $updateQuery = $pdo->prepare("UPDATE transferts SET statut = 1 WHERE id = ?");
        $updateQuery->execute([$transfert_id]);
        
        $_SESSION['flash_message'] = [
            'text' => "Transfert supprimé avec succès",
            'type' => "success"
        ];
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors de la suppression : " . $e->getMessage(),
            'type' => "error"
        ];
    }
    
    header('Location: ../pages/transferts.php');
    exit;
}