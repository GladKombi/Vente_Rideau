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
                $date = date('Y-m-d'); // Date automatique (aujourd'hui)
                $membre = $_POST['membre'];
                $boutique = $_POST['boutique'];
                
                try {
                    // Vérifier si la boutique est déjà affectée (requête préparée)
                    $stmt = $pdo->prepare("SELECT id FROM affectation WHERE boutique = ? AND statut = 0");
                    $stmt->execute([$boutique]);
                    $existingAffectation = $stmt->fetch();
                    
                    if ($existingAffectation) {
                        $message = [
                            'type' => 'error',
                            'text' => 'Cette boutique est déjà affectée à un membre'
                        ];
                        break;
                    }
                    
                    // Vérifier si le membre a déjà une affectation active (requête préparée)
                    $stmt = $pdo->prepare("SELECT id FROM affectation WHERE membre = ? AND statut = 0");
                    $stmt->execute([$membre]);
                    $existingMembreAffectation = $stmt->fetch();
                    
                    if ($existingMembreAffectation) {
                        $message = [
                            'type' => 'error',
                            'text' => 'Ce membre a déjà une affectation active'
                        ];
                        break;
                    }
                    
                    // Commencer une transaction
                    $pdo->beginTransaction();
                    
                    // Insérer l'affectation (requête préparée)
                    $stmt = $pdo->prepare("INSERT INTO affectation (date, membre, boutique, statut) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$date, $membre, $boutique]);
                    
                    // Mettre à jour l'état de la boutique (requête préparée)
                    $stmt = $pdo->prepare("UPDATE boutiques SET etat = 'occupée' WHERE id = ?");
                    $stmt->execute([$boutique]);
                    
                    // Valider la transaction
                    $pdo->commit();
                    
                    $message = [
                        'type' => 'success',
                        'text' => 'Affectation créée avec succès'
                    ];
                } catch (PDOException $e) {
                    // Annuler la transaction en cas d'erreur
                    $pdo->rollBack();
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la création de l\'affectation: ' . $e->getMessage()
                    ];
                }
                break;
                
            case 'supprimer':
                // Soft delete
                $id = $_POST['id'];
                try {
                    // Commencer une transaction
                    $pdo->beginTransaction();
                    
                    // Récupérer l'ID de la boutique avant suppression (requête préparée)
                    $stmt = $pdo->prepare("SELECT boutique FROM affectation WHERE id = ?");
                    $stmt->execute([$id]);
                    $boutique_id = $stmt->fetchColumn();
                    
                    // Désactiver l'affectation (requête préparée)
                    $stmt = $pdo->prepare("UPDATE affectation SET statut = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Remettre la boutique en état "libre" (requête préparée)
                    $stmt = $pdo->prepare("UPDATE boutiques SET etat = 'libre' WHERE id = ?");
                    $stmt->execute([$boutique_id]);
                    
                    // Valider la transaction
                    $pdo->commit();
                    
                    $message = [
                        'type' => 'success',
                        'text' => 'Affectation désactivée avec succès'
                    ];
                } catch (PDOException $e) {
                    // Annuler la transaction en cas d'erreur
                    $pdo->rollBack();
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la désactivation de l\'affectation: ' . $e->getMessage()
                    ];
                }
                break;

            case 'reactiver':
                // Réactivation d'une affectation
                $id = $_POST['id'];
                try {
                    // Commencer une transaction
                    $pdo->beginTransaction();
                    
                    // Récupérer l'ID de la boutique avant réactivation (requête préparée)
                    $stmt = $pdo->prepare("SELECT boutique FROM affectation WHERE id = ?");
                    $stmt->execute([$id]);
                    $boutique_id = $stmt->fetchColumn();
                    
                    // Vérifier si la boutique n'est pas déjà affectée (requête préparée)
                    $stmt = $pdo->prepare("SELECT id FROM affectation WHERE boutique = ? AND statut = 0");
                    $stmt->execute([$boutique_id]);
                    $existingAffectation = $stmt->fetch();
                    
                    if ($existingAffectation) {
                        $message = [
                            'type' => 'error',
                            'text' => 'Cette boutique est déjà affectée à un autre membre'
                        ];
                        $pdo->rollBack();
                        break;
                    }
                    
                    // Réactiver l'affectation (requête préparée)
                    $stmt = $pdo->prepare("UPDATE affectation SET statut = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Remettre la boutique en état "occupée" (requête préparée)
                    $stmt = $pdo->prepare("UPDATE boutiques SET etat = 'occupée' WHERE id = ?");
                    $stmt->execute([$boutique_id]);
                    
                    // Valider la transaction
                    $pdo->commit();
                    
                    $message = [
                        'type' => 'success',
                        'text' => 'Affectation réactivée avec succès'
                    ];
                } catch (PDOException $e) {
                    // Annuler la transaction en cas d'erreur
                    $pdo->rollBack();
                    $message = [
                        'type' => 'error',
                        'text' => 'Erreur lors de la réactivation de l\'affectation: ' . $e->getMessage()
                    ];
                }
                break;
        }
        
        // Stocker le message dans la session
        $_SESSION['message'] = $message;
        
        // Redirection vers la page des affectations
        header('Location: ../../views/affectations.php');
        exit;
    }
}