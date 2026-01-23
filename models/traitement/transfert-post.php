<?php
# Connexion à la base de données
include '../../connexion/connexion.php';
// Récupérer les informations de la boutique connectée
try {
    // D'abord, vérifier si la boutique existe
    $queryBoutiqueConnectee = $pdo->prepare("SELECT id, nom, statut, actif FROM boutiques WHERE id = :boutique_id");
    $queryBoutiqueConnectee->execute([':boutique_id' => $boutique_connectee_id]);
    $boutique_connectee = $queryBoutiqueConnectee->fetch(PDO::FETCH_ASSOC);

    if (!$boutique_connectee['id']) {
        $_SESSION['flash_message'] = [
            'text' => "Boutique non trouvée dans la base de données",
            'type' => "error"
        ];
        header('Location: ../../login.php');
        exit;
    }

    // Vérifier si la boutique est active (mais ne pas bloquer l'accès)
    $isBoutiqueActive = ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1);

    if (!$isBoutiqueActive) {
        // Avertissement mais pas de redirection - l'utilisateur peut continuer
        $message = "Attention : Votre boutique est " .
            ($boutique_connectee['statut'] != 0 ? "suspendue" : "") .
            ($boutique_connectee['actif'] != 1 ? " désactivée" : "") .
            ". Certaines fonctionnalités peuvent être limitées.";
        $message_type = "warning";
    }

    // Récupérer la liste des boutiques destination actives seulement (exclure la boutique connectée)
    $queryBoutiquesDest = $pdo->prepare("
        SELECT id, nom 
        FROM boutiques 
        WHERE statut = 0 
          AND actif = 1 
          AND id != :boutique_id
        ORDER BY nom
    ");
    $queryBoutiquesDest->execute([':boutique_id' => $boutique_connectee_id]);
    $boutiques_destination = $queryBoutiquesDest->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les stocks disponibles POUR LA BOUTIQUE CONNECTÉE uniquement
    // Même si la boutique n'est pas active, on montre les stocks existants
    $queryStocks = $pdo->prepare("
        SELECT s.id, s.produit_matricule, s.quantite, s.boutique_id, s.prix,
               p.designation, p.umProduit,
               b.nom as boutique_nom
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        JOIN boutiques b ON s.boutique_id = b.id 
        WHERE s.statut = 0 
          AND s.quantite > 0
          AND s.boutique_id = :boutique_id  -- FILTRE IMPORTANT : uniquement la boutique connectée
          AND s.type_mouvement IN ('approvisionnement', 'transfert')
        ORDER BY p.designation
    ");
    $queryStocks->execute([':boutique_id' => $boutique_connectee_id]);
    $stocks = $queryStocks->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des données : " . $e->getMessage(),
        'type' => "error"
    ];
    $boutique_connectee = ['id' => $boutique_connectee_id, 'nom' => 'Boutique inconnue', 'statut' => 0, 'actif' => 1];
    $boutiques_destination = [];
    $stocks = [];
}
// Gestion du formulaire de transfert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['effectuer_transfert'])) {
    try {
        // Validation des données
        $stock_id = (int)$_POST['stock_id'];
        $quantite_transferee = (float)$_POST['quantite_transferee'];
        $boutique_destination = (int)$_POST['boutique_destination'];

        // Vérifier que le stock appartient bien à la boutique connectée
        $queryVerifStock = $pdo->prepare("
            SELECT boutique_id FROM stock WHERE id = :stock_id AND statut = 0
        ");
        $queryVerifStock->execute([':stock_id' => $stock_id]);
        $stockVerif = $queryVerifStock->fetch(PDO::FETCH_ASSOC);

        if (!$stockVerif || $stockVerif['boutique_id'] != $boutique_connectee_id) {
            throw new Exception("Ce stock ne vous appartient pas ou n'existe plus");
        }

        // Récupérer les informations du stock source
        $queryStock = $pdo->prepare("
            SELECT s.*, p.designation, p.umProduit, b.nom as boutique_nom
            FROM stock s 
            JOIN produits p ON s.produit_matricule = p.matricule 
            JOIN boutiques b ON s.boutique_id = b.id 
            WHERE s.id = :stock_id AND s.statut = 0
        ");
        $queryStock->execute([':stock_id' => $stock_id]);
        $stock_source = $queryStock->fetch(PDO::FETCH_ASSOC);

        if (!$stock_source) {
            throw new Exception("Stock source introuvable");
        }

        if ($stock_source['quantite'] < $quantite_transferee) {
            throw new Exception("Quantité insuffisante en stock. Quantité disponible : " . $stock_source['quantite']);
        }

        if ($stock_source['boutique_id'] == $boutique_destination) {
            throw new Exception("Impossible de transférer vers la même boutique");
        }

        if ($quantite_transferee <= 0) {
            throw new Exception("La quantité transférée doit être supérieure à 0");
        }

        // Vérifier si la boutique destination existe et est active
        $queryVerifBoutiqueDest = $pdo->prepare("
            SELECT id FROM boutiques WHERE id = :boutique_id AND statut = 0 AND actif = 1
        ");
        $queryVerifBoutiqueDest->execute([':boutique_id' => $boutique_destination]);
        if (!$queryVerifBoutiqueDest->fetch()) {
            throw new Exception("Boutique destination invalide ou désactivée");
        }

        // Vérifier si le produit existe déjà dans le stock de destination
        $queryStockDest = $pdo->prepare("
            SELECT id, quantite, prix 
            FROM stock 
            WHERE boutique_id = :boutique_id 
              AND produit_matricule = :produit_matricule 
              AND statut = 0
            LIMIT 1
        ");
        $queryStockDest->execute([
            ':boutique_id' => $boutique_destination,
            ':produit_matricule' => $stock_source['produit_matricule']
        ]);
        $stock_destination = $queryStockDest->fetch(PDO::FETCH_ASSOC);

        // Démarrer la transaction
        $pdo->beginTransaction();

        // 1. Mettre à jour le stock source (diminuer la quantité)
        $queryUpdateSource = $pdo->prepare("
            UPDATE stock 
            SET quantite = quantite - :quantite 
            WHERE id = :stock_id AND statut = 0
        ");
        $queryUpdateSource->execute([
            ':quantite' => $quantite_transferee,
            ':stock_id' => $stock_id
        ]);

        // 2. Si le produit existe déjà dans la boutique destination
        if ($stock_destination) {
            // Augmenter la quantité du stock existant
            $queryUpdateDest = $pdo->prepare("
                UPDATE stock 
                SET quantite = quantite + :quantite, 
                    type_mouvement = 'transfert'
                WHERE id = :stock_dest_id AND statut = 0
            ");
            $queryUpdateDest->execute([
                ':quantite' => $quantite_transferee,
                ':stock_dest_id' => $stock_destination['id']
            ]);
            $nouveau_stock_id = $stock_destination['id'];
        } else {
            // Créer un nouveau stock pour la boutique destination
            $queryInsertDest = $pdo->prepare("
                INSERT INTO stock (type_mouvement, boutique_id, produit_matricule, quantite, prix, seuil_alerte_stock)
                VALUES ('transfert', :boutique_id, :produit_matricule, :quantite, :prix, :seuil_alerte)
            ");
            $queryInsertDest->execute([
                ':boutique_id' => $boutique_destination,
                ':produit_matricule' => $stock_source['produit_matricule'],
                ':quantite' => $quantite_transferee,
                ':prix' => $stock_source['prix'],
                ':seuil_alerte' => $stock_source['seuil_alerte_stock']
            ]);
            $nouveau_stock_id = $pdo->lastInsertId();
        }

        // 3. Enregistrer le transfert dans la table transferts
        $queryInsertTransfert = $pdo->prepare("
            INSERT INTO transferts (date, stock_id, Expedition, Destination)
            VALUES (CURDATE(), :stock_id, :expedition, :destination)
        ");
        $queryInsertTransfert->execute([
            ':stock_id' => $stock_id,
            ':expedition' => $boutique_connectee_id,
            ':destination' => $boutique_destination
        ]);

        // Valider la transaction
        $pdo->commit();

        $uniteText = $stock_source['umProduit'] == 'metres' ? 'mètres' : 'pièces';

        $_SESSION['flash_message'] = [
            'text' => "Transfert effectué avec succès ! " . number_format($quantite_transferee, 3) . " " .
                $uniteText . " de " . $stock_source['designation'] . " transférés.",
            'type' => "success"
        ];

        header('Location: ../../views/transferts.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_message'] = [
            'text' => "Erreur lors du transfert : " . $e->getMessage(),
            'type' => "error"
        ];
    }
}
