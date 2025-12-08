<?php
// Récupérer tous les paiements avec les informations des contrats, affectations et membres
$sql = "SELECT p.*, 
               c.loyer_mensuel, c.date_debut, c.date_fin, c.date_paiement as date_paiement_limite,
               m.nom as nom_membre, m.postnom as postnom_membre, m.prenom as prenom_membre,
               a.boutique as boutique_id
        FROM paiements p 
        LEFT JOIN contrats c ON p.contrat_id = c.id 
        LEFT JOIN affectation a ON p.affectation = a.id
        LEFT JOIN membres m ON a.membre = m.id
        WHERE p.statut = 0 
        ORDER BY p.date_paiement DESC";
$stmt = $pdo->query($sql);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les contrats actifs pour les filtres et le formulaire
$sql_contrats = "SELECT id, loyer_mensuel, date_debut, date_fin, date_paiement FROM contrats WHERE statut = 0 ORDER BY id DESC";
$stmt_contrats = $pdo->query($sql_contrats);
$contrats = $stmt_contrats->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les affectations actives avec les informations des membres pour le formulaire
$sql_affectations = "SELECT a.id, a.boutique as boutique_id, 
                            m.nom as nom_membre, m.postnom as postnom_membre, m.prenom as prenom_membre
                     FROM affectation a 
                     LEFT JOIN membres m ON a.membre = m.id 
                     WHERE a.statut = 0 
                     ORDER BY a.id DESC";
$stmt_affectations = $pdo->query($sql_affectations);
$affectations = $stmt_affectations->fetchAll(PDO::FETCH_ASSOC);
?>