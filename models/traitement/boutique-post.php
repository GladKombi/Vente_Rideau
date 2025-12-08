<?php
session_start();
# Se connecter à la BD
include '../../connexion/connexion.php';

// Fonction pour générer le numéro automatique
function genererNumeroAuto($pdo, $categorie_id) {
    try {
        // Récupérer l'initiale de la catégorie
        $stmt = $pdo->prepare("SELECT designation FROM categorie WHERE id = ?");
        $stmt->execute([$categorie_id]);
        $categorie = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($categorie) {
            $initiale = strtoupper(substr($categorie['designation'], 0, 1));
            
            // Trouver le dernier numéro pour cette catégorie
            $stmt = $pdo->prepare("SELECT numero FROM boutiques WHERE numero LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$initiale . '%']);
            $dernier_numero = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dernier_numero) {
                // Extraire le numéro et incrémenter
                $numero_actuel = intval(substr($dernier_numero['numero'], 1));
                $nouveau_numero = $numero_actuel + 1;
            } else {
                $nouveau_numero = 1;
            }
            
            // Formater le numéro (ex: B001, Q001, etc.)
            return $initiale . sprintf("%03d", $nouveau_numero);
        }
    } catch (PDOException $e) {
        error_log("Erreur génération numéro: " . $e->getMessage());
    }
    
    return null;
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Variable pour stocker les messages de statut
        $message = [];
        
        switch($_POST['action']) {
            case 'ajouter':
                // Traitement de l'ajout
                $surface = $_POST['surface'];
                $etat = $_POST['etat'];
                $categorie = $_POST['categorie'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                // Générer le numéro automatiquement
                $numero = genererNumeroAuto($pdo, $categorie);
                
                if (!$numero) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la génération du numéro de boutique'
                    ];
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO boutiques (numero, surface, etat, categorie, statut) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$numero, $surface, $etat, $categorie, $statut]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Boutique ' . $numero . ' ajoutée avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de l\'ajout de la boutique: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'modifier':
                // Traitement de la modification
                $id = $_POST['id'];
                $surface = $_POST['surface'];
                $etat = $_POST['etat'];
                $categorie = $_POST['categorie'];
                $statut = isset($_POST['statut']) ? 1 : 0;
                
                try {
                    // Récupérer l'ancien numéro pour le message
                    $stmt = $pdo->prepare("SELECT numero FROM boutiques WHERE id = ?");
                    $stmt->execute([$id]);
                    $ancien_numero = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("UPDATE boutiques SET surface=?, etat=?, categorie=?, statut=? WHERE id=?");
                    $stmt->execute([$surface, $etat, $categorie, $statut, $id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Boutique ' . $ancien_numero . ' modifiée avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la modification de la boutique: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'supprimer':
                // Soft delete
                $id = $_POST['id'];
                try {
                    // Récupérer le numéro pour le message
                    $stmt = $pdo->prepare("SELECT numero FROM boutiques WHERE id = ?");
                    $stmt->execute([$id]);
                    $numero = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("UPDATE boutiques SET statut=1 WHERE id=?");
                    $stmt->execute([$id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Boutique ' . $numero . ' désactivée avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la désactivation de la boutique: ' . $e->getMessage()
                    ];
                }
                break;

            case 'reactiver':
                // Réactivation d'une boutique
                $id = $_POST['id'];
                try {
                    // Récupérer le numéro pour le message
                    $stmt = $pdo->prepare("SELECT numero FROM boutiques WHERE id = ?");
                    $stmt->execute([$id]);
                    $numero = $stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("UPDATE boutiques SET statut=0 WHERE id=?");
                    $stmt->execute([$id]);
                    $message = [
                        'type' => 'success',
                        'text' => 'Boutique ' . $numero . ' réactivée avec succès'
                    ];
                } catch (PDOException $e) {
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la réactivation de la boutique: ' . $e->getMessage()
                    ];
                }
                break;
        }
        
        // Stocker le message dans la session
        $_SESSION['message'] = $message;
        
        // Redirection vers la page des boutiques
        header('Location: ../../views/boutiques.php');
        exit;
    }
}