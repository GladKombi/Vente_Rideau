<?php
include '../../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type_alignement = $_POST['type_alignement'] ?? 'global';
    
    try {
        switch ($action) {
            case 'ajouter':
                // Utiliser la date actuelle automatiquement
                $date = date('Y-m-d');
                $montant = floatval($_POST['montant'] ?? 0);
                $charge_id = intval($_POST['charge_id'] ?? 0);
                
                // Validation des données
                if ($montant <= 0) {
                    throw new Exception("Le montant doit être supérieur à 0");
                }
                
                if ($charge_id <= 0) {
                    throw new Exception("Veuillez sélectionner une charge");
                }
                
                // Vérifier que la charge existe
                $sql_check_charge = "SELECT id, designation FROM charges WHERE id = ? AND statut = 0";
                $stmt_check_charge = $pdo->prepare($sql_check_charge);
                $stmt_check_charge->execute([$charge_id]);
                $charge = $stmt_check_charge->fetch();
                
                if (!$charge) {
                    throw new Exception("La charge sélectionnée n'existe pas ou est inactive");
                }
                
                if ($type_alignement === 'global') {
                    // Alignement global pour une charge - appliqué à toutes les affectations actives
                    // Récupérer toutes les affectations actives (sans filtre par charge)
                    $sql_affectations = "SELECT id FROM affectation WHERE statut = 0";
                    $stmt_affectations = $pdo->prepare($sql_affectations);
                    $stmt_affectations->execute();
                    $affectations = $stmt_affectations->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($affectations)) {
                        throw new Exception("Aucune affectation active trouvée");
                    }
                    
                    // Créer un alignement pour chaque affectation
                    $sql_insert = "INSERT INTO aligements (date, affectation_id, charge_id, montant, statut) VALUES (?, ?, ?, ?, 0)";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $count = 0;
                    
                    foreach ($affectations as $affectation) {
                        try {
                            $stmt_insert->execute([$date, $affectation['id'], $charge_id, $montant]);
                            $count++;
                        } catch (PDOException $e) {
                            // Continuer même si une insertion échoue (duplicata possible)
                            continue;
                        }
                    }
                    
                    $_SESSION['message'] = [
                        'text' => $count . ' alignement(s) créé(s) avec succès pour la charge "' . $charge['designation'] . '"',
                        'type' => 'success'
                    ];
                    
                } elseif ($type_alignement === 'specifique') {
                    // Alignement spécifique pour une affectation
                    $affectation_id = intval($_POST['affectation_id'] ?? 0);
                    
                    if ($affectation_id <= 0) {
                        throw new Exception("Veuillez sélectionner une affectation");
                    }
                    
                    // Vérifier que l'affectation existe et est active
                    $sql_check_affectation = "
                        SELECT aff.id, m.nom, m.prenom, b.numero 
                        FROM affectation aff
                        INNER JOIN membres m ON aff.membre = m.id
                        INNER JOIN boutiques b ON aff.boutique = b.id
                        WHERE aff.id = ? AND aff.statut = 0
                    ";
                    $stmt_check_affectation = $pdo->prepare($sql_check_affectation);
                    $stmt_check_affectation->execute([$affectation_id]);
                    $affectation = $stmt_check_affectation->fetch();
                    
                    if (!$affectation) {
                        throw new Exception("L'affectation sélectionnée n'existe pas ou est inactive");
                    }
                    
                    // Vérifier si un alignement existe déjà pour cette affectation et cette charge
                    $sql_check_existing = "SELECT id FROM aligements WHERE affectation_id = ? AND charge_id = ? AND statut = 0";
                    $stmt_check_existing = $pdo->prepare($sql_check_existing);
                    $stmt_check_existing->execute([$affectation_id, $charge_id]);
                    $existing = $stmt_check_existing->fetch();
                    
                    if ($existing) {
                        throw new Exception("Un alignement actif existe déjà pour cette affectation et cette charge");
                    }
                    
                    $sql_insert = "INSERT INTO aligements (date, affectation_id, charge_id, montant, statut) VALUES (?, ?, ?, ?, 0)";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->execute([$date, $affectation_id, $charge_id, $montant]);
                    
                    $locataire_nom = $affectation['nom'] . ' ' . $affectation['prenom'];
                    $_SESSION['message'] = [
                        'text' => 'Alignement créé avec succès pour ' . $locataire_nom . ' (Boutique ' . $affectation['numero'] . ')',
                        'type' => 'success'
                    ];
                } else {
                    throw new Exception("Type d'alignement non valide");
                }
                break;
                
            case 'modifier':
                $id = intval($_POST['id'] ?? 0);
                $montant = floatval($_POST['montant'] ?? 0);
                $charge_id = intval($_POST['charge_id'] ?? 0);
                $affectation_id = intval($_POST['affectation_id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception("ID d'alignement invalide");
                }
                
                if ($montant <= 0) {
                    throw new Exception("Le montant doit être supérieur à 0");
                }
                
                // Vérifier que l'alignement existe
                $sql_check_alignement = "
                    SELECT a.*, c.designation as charge_designation 
                    FROM aligements a 
                    LEFT JOIN charges c ON a.charge_id = c.id 
                    WHERE a.id = ?
                ";
                $stmt_check = $pdo->prepare($sql_check_alignement);
                $stmt_check->execute([$id]);
                $alignement = $stmt_check->fetch();
                
                if (!$alignement) {
                    throw new Exception("Alignement non trouvé");
                }
                
                // Vérifier que la charge existe
                if ($charge_id > 0) {
                    $sql_check_charge = "SELECT id FROM charges WHERE id = ? AND statut = 0";
                    $stmt_check_charge = $pdo->prepare($sql_check_charge);
                    $stmt_check_charge->execute([$charge_id]);
                    
                    if (!$stmt_check_charge->fetch()) {
                        throw new Exception("La charge sélectionnée n'existe pas ou est inactive");
                    }
                }
                
                // Vérifier que l'affectation existe si spécifiée
                if ($affectation_id > 0) {
                    $sql_check_affectation = "SELECT id FROM affectation WHERE id = ? AND statut = 0";
                    $stmt_check_affectation = $pdo->prepare($sql_check_affectation);
                    $stmt_check_affectation->execute([$affectation_id]);
                    
                    if (!$stmt_check_affectation->fetch()) {
                        throw new Exception("L'affectation sélectionnée n'existe pas ou est inactive");
                    }
                }
                
                // Pour la modification, on met à jour le montant et potentiellement la charge/affectation
                $sql_update = "UPDATE aligements SET montant = ?, charge_id = ?, affectation_id = ?, updated_at = NOW() WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                
                // Utiliser les valeurs existantes si non fournies
                $update_charge_id = ($charge_id > 0) ? $charge_id : $alignement['charge_id'];
                $update_affectation_id = ($affectation_id > 0) ? $affectation_id : $alignement['affectation_id'];
                
                $stmt_update->execute([$montant, $update_charge_id, $update_affectation_id, $id]);
                
                $_SESSION['message'] = [
                    'text' => 'Alignement modifié avec succès',
                    'type' => 'success'
                ];
                break;
                
            case 'supprimer':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception("ID manquant pour la suppression");
                }
                
                // Vérifier que l'alignement existe
                $sql_check = "SELECT id FROM aligements WHERE id = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id]);
                
                if (!$stmt_check->fetch()) {
                    throw new Exception("Alignement non trouvé");
                }
                
                $sql_update = "UPDATE aligements SET statut = 1, updated_at = NOW() WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$id]);
                
                $_SESSION['message'] = [
                    'text' => 'Alignement désactivé avec succès',
                    'type' => 'success'
                ];
                break;
                
            case 'reactiver':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    throw new Exception("ID manquant pour la réactivation");
                }
                
                // Vérifier que l'alignement existe
                $sql_check = "SELECT id FROM aligements WHERE id = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$id]);
                
                if (!$stmt_check->fetch()) {
                    throw new Exception("Alignement non trouvé");
                }
                
                $sql_update = "UPDATE aligements SET statut = 0, updated_at = NOW() WHERE id = ?";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([$id]);
                
                $_SESSION['message'] = [
                    'text' => 'Alignement réactivé avec succès',
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
    
    header('Location: ../../views/alignements.php');
    exit;
}