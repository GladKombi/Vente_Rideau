<?php
include '../../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'ajouter':
                $date = $_POST['date'] ?? date('Y-m-d');
                $montant = floatval($_POST['montant'] ?? 0);
                $alignement_id = intval($_POST['alignement_id'] ?? 0);
                
                // Validation des données
                if ($montant <= 0) {
                    throw new Exception("Le montant doit être supérieur à 0");
                }
                
                if ($alignement_id <= 0) {
                    throw new Exception("Veuillez sélectionner un alignement");
                }
                
                // Vérifier que l'alignement existe et est actif
                $sql_check_alignement = "
                    SELECT a.id, a.montant, c.designation, m.nom, m.prenom 
                    FROM aligements a
                    LEFT JOIN charges c ON a.charge_id = c.id
                    LEFT JOIN affectation aff ON a.affectation_id = aff.id
                    LEFT JOIN membres m ON aff.membre = m.id
                    WHERE a.id = ? AND a.statut = 0
                ";
                $stmt_check = $pdo->prepare($sql_check_alignement);
                $stmt_check->execute([$alignement_id]);
                $alignement = $stmt_check->fetch();
                
                if (!$alignement) {
                    throw new Exception("L'alignement sélectionné n'existe pas ou est inactif");
                }
                
                // Vérifier le total déjà payé
                $sql_total = "SELECT COALESCE(SUM(montant), 0) as total FROM paiments_Charge WHERE aligement_id = ? AND statut = 0";
                $stmt_total = $pdo->prepare($sql_total);
                $stmt_total->execute([$alignement_id]);
                $total_paye = $stmt_total->fetchColumn();
                
                $montant_alignement = $alignement['montant'];
                $reste = $montant_alignement - $total_paye;
                
                if ($montant > $reste) {
                    throw new Exception("Le montant dépasse le reste dû (" . number_format($reste, 2, ',', ' ') . " €)");
                }
                
                // Insérer le paiement
                $sql_insert = "INSERT INTO paiments_Charge (date, aligement_id, montant, statut) VALUES (?, ?, ?, 0)";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([$date, $alignement_id, $montant]);
                
                $_SESSION['message'] = [
                    'text' => 'Paiement enregistré avec succès',
                    'type' => 'success'
                ];
                break;
                
            case 'modifier':
                $id = intval($_POST['id'] ?? 0);
                $date = $_POST['date'] ?? date('Y-m-d');
                $montant = floatval($_POST['montant'] ?? 0);
                $alignement_id = intval($_POST['alignement_id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception("ID de paiement invalide");
                }
                
                if ($montant <= 0) {
                    throw new Exception("Le montant doit être supérieur à 0");
                }
                
                // Vérifier que le paiement existe
                $sql_check = "SELECT id, aligement_id FROM paiments_Charge WHERE id = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id]);
                
                if (!$stmt_check->fetch()) {
                    throw new Exception("Paiement non trouvé");
                }
                
                // Mettre à jour le paiement
                $sql_update = "UPDATE paiments_Charge SET date = ?, aligement_id = ?, montant = ? WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$date, $alignement_id, $montant, $id]);
                
                $_SESSION['message'] = [
                    'text' => 'Paiement modifié avec succès',
                    'type' => 'success'
                ];
                break;
                
            case 'supprimer':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception("ID manquant pour la suppression");
                }
                
                $sql_update = "UPDATE paiments_Charge SET statut = 1 WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$id]);
                
                $_SESSION['message'] = [
                    'text' => 'Paiement désactivé avec succès',
                    'type' => 'success'
                ];
                break;
                
            case 'reactiver':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception("ID manquant pour la réactivation");
                }
                
                $sql_update = "UPDATE paiments_Charge SET statut = 0 WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$id]);
                
                $_SESSION['message'] = [
                    'text' => 'Paiement réactivé avec succès',
                    'type' => 'success'
                ];
                break;
                
            default:
                throw new Exception("Action non reconnue");
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO: " . $e->getMessage());
        $_SESSION['message'] = [
            'text' => 'Une erreur de base de données est survenue',
            'type' => 'error'
        ];
    } catch (Exception $e) {
        $_SESSION['message'] = [
            'text' => $e->getMessage(),
            'type' => 'error'
        ];
    }
    
    header('Location: ../views/paiements_charges.php');
    exit;
}