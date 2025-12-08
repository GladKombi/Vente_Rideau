<?php

// Récupérer les catégories
$sql_categories = "SELECT * FROM categorie WHERE statut = 0 ORDER BY designation";
$stmt_categories = $pdo->query($sql_categories);
$categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les contrats avec jointure sur catégorie
$sql_contrats = "SELECT c.*, cat.designation as categorie_nom 
                 FROM contrats c 
                 LEFT JOIN categorie cat ON c.categorie = cat.id 
                 WHERE c.statut = 0 
                 ORDER BY c.created_at DESC";
$stmt_contrats = $pdo->query($sql_contrats);
$contrats = $stmt_contrats->fetchAll(PDO::FETCH_ASSOC);

// Dates par défaut
$date_automatique = date('Y-m-d');
$date_paiement_par_defaut = date('Y-m-d', strtotime('+1 month'));
