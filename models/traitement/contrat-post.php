<?php
include '../../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';
    $loyer_mensuel = $_POST['loyer_mensuel'] ?? '';
    $date_paiement = $_POST['date_paiement'] ?? '';
    $conditions_speciales = $_POST['conditions_speciales'] ?? '';
    $categorie = $_POST['categorie'] ?? null;

    try {
        if ($action === 'ajouter') {
            $sql = "INSERT INTO contrats (date_debut, date_fin, categorie, loyer_mensuel, date_paiement, conditions_speciales, statut) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date_debut, $date_fin, $categorie, $loyer_mensuel, $date_paiement, $conditions_speciales]);
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Contrat ajouté avec succès'];
            
        } elseif ($action === 'modifier') {
            $id = $_POST['id'] ?? '';
            $sql = "UPDATE contrats SET date_debut = ?, date_fin = ?, categorie = ?, loyer_mensuel = ?, date_paiement = ?, conditions_speciales = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$date_debut, $date_fin, $categorie, $loyer_mensuel, $date_paiement, $conditions_speciales, $id]);
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Contrat modifié avec succès'];
            
        } elseif ($action === 'supprimer') {
            $id = $_POST['id'] ?? '';
            $sql = "UPDATE contrats SET statut = 1 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Contrat désactivé avec succès'];
            
        } elseif ($action === 'reactiver') {
            $id = $_POST['id'] ?? '';
            $sql = "UPDATE contrats SET statut = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Contrat réactivé avec succès'];
        }
        
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Erreur: ' . $e->getMessage()];
    }
    
    header('Location: ../../views/contrats.php');
    exit;
}
?>