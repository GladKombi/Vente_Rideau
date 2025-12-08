<?php
# Se connecter à la BD
include '../../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'ajouter':
                ajouterPaiement($pdo);
                break;
            case 'modifier':
                modifierPaiement($pdo);
                break;
            case 'supprimer':
                supprimerPaiement($pdo);
                break;
            case 'reactiver':
                reactiverPaiement($pdo);
                break;
            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        $_SESSION['message'] = [
            'text' => 'Erreur: ' . $e->getMessage(),
            'type' => 'error'
        ];
        header('Location: ../../views/paiements.php');
        exit;
    }
}

function calculerEtatPaiement($contrat_id, $montant, $pdo) {
    // Récupérer les informations du contrat
    $sql_contrat = "SELECT loyer_mensuel, date_paiement FROM contrats WHERE id = ?";
    $stmt_contrat = $pdo->prepare($sql_contrat);
    $stmt_contrat->execute([$contrat_id]);
    $contrat = $stmt_contrat->fetch(PDO::FETCH_ASSOC);
    
    if (!$contrat) {
        return 'en attente';
    }
    
    $loyer_mensuel = $contrat['loyer_mensuel'];
    $date_paiement_limite = $contrat['date_paiement'];
    $aujourdhui = date('Y-m-d');
    
    // Logique de calcul de l'état
    if ($montant >= $loyer_mensuel) {
        // Vérifier si la date limite est dépassée
        if ($date_paiement_limite && $aujourdhui > $date_paiement_limite) {
            return 'en retard';
        } else {
            return 'payé';
        }
    } else {
        // Vérifier si la date limite est dépassée
        if ($date_paiement_limite && $aujourdhui > $date_paiement_limite) {
            return 'en retard';
        } else {
            return 'en attente';
        }
    }
}

function ajouterPaiement($pdo) {
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d'); // Date automatique si non fournie
    $contrat_id = $_POST['contrat_id'];
    $affectation = $_POST['affectation'];
    $montant = $_POST['montant'];
    $notes = $_POST['notes'] ?? '';

    // Validation
    if (empty($contrat_id) || empty($affectation) || empty($montant)) {
        throw new Exception('Tous les champs obligatoires doivent être remplis');
    }

    // Calculer l'état automatiquement
    $etat = calculerEtatPaiement($contrat_id, $montant, $pdo);

    $sql = "INSERT INTO paiements (date_paiement, contrat_id, affectation, montant, etat, notes, statut) 
            VALUES (?, ?, ?, ?, ?, ?, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_paiement, $contrat_id, $affectation, $montant, $etat, $notes]);

    $_SESSION['message'] = [
        'text' => 'Paiement ajouté avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/paiements.php');
    exit;
}

function modifierPaiement($pdo) {
    $id = $_POST['id'];
    $contrat_id = $_POST['contrat_id'];
    $affectation = $_POST['affectation'];
    $montant = $_POST['montant'];
    $notes = $_POST['notes'] ?? '';

    // Calculer l'état automatiquement
    $etat = calculerEtatPaiement($contrat_id, $montant, $pdo);

    $sql = "UPDATE paiements SET contrat_id = ?, affectation = ?, montant = ?, etat = ?, notes = ? 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contrat_id, $affectation, $montant, $etat, $notes, $id]);

    $_SESSION['message'] = [
        'text' => 'Paiement modifié avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/paiements.php');
    exit;
}

function supprimerPaiement($pdo) {
    $id = $_POST['id'];
    
    $sql = "UPDATE paiements SET statut = 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    $_SESSION['message'] = [
        'text' => 'Paiement désactivé avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/paiements.php');
    exit;
}

function reactiverPaiement($pdo) {
    $id = $_POST['id'];
    
    $sql = "UPDATE paiements SET statut = 0 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    $_SESSION['message'] = [
        'text' => 'Paiement réactivé avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/paiements.php');
    exit;
}
?>