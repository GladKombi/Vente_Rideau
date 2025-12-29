<?php
# Connexion à la base de données
include '../connexion/connexion.php';

// Récupérer l'ID de la boutique connectée
$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

// Fonction améliorée pour générer un numéro de facture unique
function generateNumeroFacture($pdo, $boutique_id) {
    $prefix = 'FACT-' . date('Ymd') . '-B' . $boutique_id . '-';
    
    // Utiliser une transaction pour éviter les doublons en cas de requêtes simultanées
    $pdo->beginTransaction();
    
    try {
        // Récupérer le dernier numéro de facture pour aujourd'hui avec verrouillage
        $query = $pdo->prepare("
            SELECT numero_facture 
            FROM commandes 
            WHERE numero_facture LIKE ? 
            AND DATE(date_commande) = CURDATE()
            ORDER BY id DESC 
            LIMIT 1 FOR UPDATE
        ");
        
        $likePattern = $prefix . '%';
        $query->execute([$likePattern]);
        $lastFacture = $query->fetch(PDO::FETCH_ASSOC);
        
        if ($lastFacture && !empty($lastFacture['numero_facture'])) {
            // Extraire le numéro incrémental du dernier numéro (ex: FACT-20251217-B1-001)
            $lastNumber = substr($lastFacture['numero_facture'], strlen($prefix));
            $nextNumber = (int)$lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        $pdo->commit();
        
        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        // Fallback: utiliser un timestamp pour garantir l'unicité
        $microtime = microtime(true);
        $timestampPart = substr(str_replace('.', '', (string)$microtime), -6);
        return $prefix . str_pad($timestampPart, 6, '0', STR_PAD_LEFT);
    }
}
$numero_facture = generateNumeroFacture($pdo, $boutique_id);
echo $numero_facture;