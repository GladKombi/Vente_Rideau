<?php
include '../../connexion/connexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'ajouter':
                ajouterCharge($pdo);
                break;
            case 'modifier':
                modifierCharge($pdo);
                break;
            case 'supprimer':
                supprimerCharge($pdo);
                break;
            case 'reactiver':
                reactiverCharge($pdo);
                break;
            default:
                throw new Exception('Action non reconnue');
        }
    } catch (Exception $e) {
        $_SESSION['message'] = [
            'text' => 'Erreur: ' . $e->getMessage(),
            'type' => 'error'
        ];
        header('Location: ../../views/charges.php');
        exit;
    }
}

function ajouterCharge($pdo) {
    $designation = trim($_POST['designation']);

    // Validation
    if (empty($designation)) {
        throw new Exception('La désignation est obligatoire');
    }

    if (strlen($designation) < 2) {
        throw new Exception('La désignation doit contenir au moins 2 caractères');
    }

    if (strlen($designation) > 50) {
        throw new Exception('La désignation ne peut pas dépasser 50 caractères');
    }

    // Vérifier si la charge existe déjà
    $sql_check = "SELECT id FROM charges WHERE designation = ? AND statut = 0";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$designation]);
    
    if ($stmt_check->fetch()) {
        throw new Exception('Cette charge existe déjà');
    }

    $sql = "INSERT INTO charges (designation, statut) VALUES (?, 0)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$designation]);

    $_SESSION['message'] = [
        'text' => 'Charge ajoutée avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/charges.php');
    exit;
}

function modifierCharge($pdo) {
    $id = $_POST['id'];
    $designation = trim($_POST['designation']);

    // Validation
    if (empty($designation)) {
        throw new Exception('La désignation est obligatoire');
    }

    if (strlen($designation) < 2) {
        throw new Exception('La désignation doit contenir au moins 2 caractères');
    }

    if (strlen($designation) > 50) {
        throw new Exception('La désignation ne peut pas dépasser 50 caractères');
    }

    // Vérifier si la charge existe déjà (pour un autre ID)
    $sql_check = "SELECT id FROM charges WHERE designation = ? AND id != ? AND statut = 0";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$designation, $id]);
    
    if ($stmt_check->fetch()) {
        throw new Exception('Cette charge existe déjà');
    }

    $sql = "UPDATE charges SET designation = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$designation, $id]);

    $_SESSION['message'] = [
        'text' => 'Charge modifiée avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/charges.php');
    exit;
}

function supprimerCharge($pdo) {
    $id = $_POST['id'];
    
    $sql = "UPDATE charges SET statut = 1 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    $_SESSION['message'] = [
        'text' => 'Charge désactivée avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/charges.php');
    exit;
}

function reactiverCharge($pdo) {
    $id = $_POST['id'];
    
    $sql = "UPDATE charges SET statut = 0 WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    $_SESSION['message'] = [
        'text' => 'Charge réactivée avec succès',
        'type' => 'success'
    ];
    header('Location: ../../views/charges.php');
    exit;
}
?>