<?php
include '../../connexion/connexion.php';

header('Content-Type: application/json');

if (isset($_GET['alignement_id'])) {
    $alignement_id = intval($_GET['alignement_id']);
    
    $sql = "SELECT COALESCE(SUM(montant), 0) as total FROM paiments_Charge WHERE aligement_id = ? AND statut = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$alignement_id]);
    $total = $stmt->fetchColumn();
    
    echo json_encode(['total' => $total]);
} else {
    echo json_encode(['total' => 0]);
}