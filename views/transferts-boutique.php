<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

# Connexion à la base de données
include '../connexion/connexion.php';

// DEBUG - Pour voir ce qui se passe
error_log("=== TRANSFERTS PAGE ACCESSED ===");
error_log("REQUEST METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST DATA: " . print_r($_POST, true));

// Vérification de l'authentification
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    $_SESSION['flash_message'] = [
        'text' => 'Veuillez vous connecter pour accéder à cette page',
        'type' => 'error'
    ];
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de la boutique connectée avec validation
$boutique_connectee_id = isset($_SESSION['boutique_id']) ? (int)$_SESSION['boutique_id'] : 0;

if ($boutique_connectee_id <= 0) {
    $_SESSION['flash_message'] = [
        'text' => "ID boutique invalide",
        'type' => "error"
    ];
    header('Location: ../login.php');
    exit;
}

// Initialisation des variables
$flash_message = '';
$flash_message_type = '';
$warning_message = '';
$warning_message_type = 'warning';
$total_transferts = 0;
$transferts = [];

// --- GESTION DES MESSAGES VIA SESSIONS ---
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message']['text'];
    $flash_message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']);
}

// Récupérer les informations de la boutique connectée
try {
    // D'abord, vérifier si la boutique existe
    $queryBoutiqueConnectee = $pdo->prepare("SELECT id, nom, statut, actif FROM boutiques WHERE id = :boutique_id");
    $queryBoutiqueConnectee->execute([':boutique_id' => $boutique_connectee_id]);
    $boutique_connectee = $queryBoutiqueConnectee->fetch(PDO::FETCH_ASSOC);
    
    if (!$boutique_connectee) {
        $_SESSION['flash_message'] = [
            'text' => "Boutique non trouvée dans la base de données",
            'type' => "error"
        ];
        header('Location: ../login.php');
        exit;
    }
    
    // Vérifier si la boutique est active (mais ne pas bloquer l'accès)
    $isBoutiqueActive = ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1);
    
    if (!$isBoutiqueActive) {
        // Avertissement mais pas de redirection - l'utilisateur peut continuer
        $warning_message = "Attention : Votre boutique est " . 
                   ($boutique_connectee['statut'] != 0 ? "suspendue" : "") . 
                   ($boutique_connectee['actif'] != 1 ? " désactivée" : "") . 
                   ". Certaines fonctionnalités peuvent être limitées.";
        $warning_message_type = "warning";
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
    $queryStocks = $pdo->prepare("
        SELECT s.id, s.produit_matricule, s.quantite, s.boutique_id, s.prix, s.seuil_alerte_stock,
               p.designation, p.umProduit,
               b.nom as boutique_nom
        FROM stock s 
        JOIN produits p ON s.produit_matricule = p.matricule 
        JOIN boutiques b ON s.boutique_id = b.id 
        WHERE s.statut = 0 
          AND s.quantite > 0
          AND s.boutique_id = :boutique_id
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
    error_log("=== FORM SUBMITTED ===");
    
    try {
        // Validation des données
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        $quantite_transferee = (float)($_POST['quantite_transferee'] ?? 0);
        $boutique_destination = (int)($_POST['boutique_destination'] ?? 0);
        
        error_log("Données reçues: stock_id=$stock_id, quantite=$quantite_transferee, boutique_dest=$boutique_destination");
        
        // Vérifications de base
        if ($stock_id <= 0 || $quantite_transferee <= 0 || $boutique_destination <= 0) {
            throw new Exception("Tous les champs sont obligatoires et doivent être valides");
        }
        
        if ($boutique_connectee_id == $boutique_destination) {
            throw new Exception("Impossible de transférer vers la même boutique");
        }
        
        // Vérifier que le stock appartient bien à la boutique connectée
        $queryVerifStock = $pdo->prepare("
            SELECT s.*, p.designation, p.umProduit, b.nom as boutique_nom 
            FROM stock s 
            JOIN produits p ON s.produit_matricule = p.matricule 
            JOIN boutiques b ON s.boutique_id = b.id 
            WHERE s.id = :stock_id 
              AND s.statut = 0 
              AND s.boutique_id = :boutique_id
        ");
        $queryVerifStock->execute([
            ':stock_id' => $stock_id,
            ':boutique_id' => $boutique_connectee_id
        ]);
        $stock_source = $queryVerifStock->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock_source) {
            throw new Exception("Ce stock ne vous appartient pas ou n'existe plus");
        }
        
        if ($stock_source['quantite'] < $quantite_transferee) {
            throw new Exception("Quantité insuffisante en stock. Quantité disponible : " . number_format($stock_source['quantite'], 3));
        }
        
        // Vérifier si la boutique destination existe et est active
        $queryVerifBoutiqueDest = $pdo->prepare("
            SELECT id, nom FROM boutiques 
            WHERE id = :boutique_id 
              AND statut = 0 
              AND actif = 1
        ");
        $queryVerifBoutiqueDest->execute([':boutique_id' => $boutique_destination]);
        $boutique_dest_info = $queryVerifBoutiqueDest->fetch(PDO::FETCH_ASSOC);
        
        if (!$boutique_dest_info) {
            throw new Exception("Boutique destination invalide ou désactivée");
        }
        
        // CORRECTION : Vérifier si le produit existe déjà dans le stock de destination AVEC LE MÊME PRIX
        $queryStockDest = $pdo->prepare("
            SELECT id, quantite, prix 
            FROM stock 
            WHERE boutique_id = :boutique_id 
              AND produit_matricule = :produit_matricule 
              AND prix = :prix  -- IMPORTANT : vérifier aussi le prix
              AND statut = 0
            LIMIT 1
        ");
        $queryStockDest->execute([
            ':boutique_id' => $boutique_destination,
            ':produit_matricule' => $stock_source['produit_matricule'],
            ':prix' => $stock_source['prix']  // Ajout du prix comme critère
        ]);
        $stock_destination = $queryStockDest->fetch(PDO::FETCH_ASSOC);
        
        error_log("Stock destination trouvé: " . print_r($stock_destination, true));
        
        // Démarrer la transaction
        $pdo->beginTransaction();
        
        try {
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
            
            if ($queryUpdateSource->rowCount() === 0) {
                throw new Exception("Échec de la mise à jour du stock source");
            }
            
            error_log("Stock source mis à jour avec succès");
            
            // 2. Mettre à jour ou créer le stock destination
            if ($stock_destination) {
                // Augmenter la quantité du stock existant (avec même prix)
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
                error_log("Stock destination existant mis à jour: ID $nouveau_stock_id");
            } else {
                // CORRECTION : Créer un nouveau stock pour la boutique destination
                $queryInsertDest = $pdo->prepare("
                    INSERT INTO stock 
                    (type_mouvement, boutique_id, produit_matricule, quantite, prix, seuil_alerte_stock, date_creation, statut)
                    VALUES ('transfert', :boutique_id, :produit_matricule, :quantite, :prix, :seuil_alerte, NOW(), 0)
                ");
                $queryInsertDest->execute([
                    ':boutique_id' => $boutique_destination,
                    ':produit_matricule' => $stock_source['produit_matricule'],
                    ':quantite' => $quantite_transferee,
                    ':prix' => $stock_source['prix'],
                    ':seuil_alerte' => $stock_source['seuil_alerte_stock']
                ]);
                $nouveau_stock_id = $pdo->lastInsertId();
                error_log("Nouveau stock destination créé: ID $nouveau_stock_id");
            }
            
            // 3. Enregistrer le transfert dans la table transferts
            $queryInsertTransfert = $pdo->prepare("
                INSERT INTO transferts (date, stock_id, Expedition, Destination, statut)
                VALUES (NOW(), :stock_id, :expedition, :destination, 0)
            ");
            $queryInsertTransfert->execute([
                ':stock_id' => $stock_id,
                ':expedition' => $boutique_connectee_id,
                ':destination' => $boutique_destination
            ]);
            
            $transfert_id = $pdo->lastInsertId();
            error_log("Transfert enregistré: ID $transfert_id");
            
            // Valider la transaction
            $pdo->commit();
            
            $uniteText = $stock_source['umProduit'] == 'metres' ? 'mètres' : 'pièces';
            
            $_SESSION['flash_message'] = [
                'text' => " Transfert effectué avec succès ! " . 
                         number_format($quantite_transferee, 3) . " " . $uniteText . 
                         " de '" . $stock_source['designation'] . 
                         "' transférés vers '" . $boutique_dest_info['nom'] . "'",
                'type' => "success"
            ];
            
            error_log("Redirection vers la page...");
            
            // Rediriger vers la même page
            header('Location: transferts-boutique.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur transaction: " . $e->getMessage());
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Erreur générale: " . $e->getMessage());
        $_SESSION['flash_message'] = [
            'text' => " Erreur lors du transfert : " . $e->getMessage(),
            'type' => "error"
        ];
    }
}

// Pagination pour les historiques de transfert
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Compter le nombre total de transferts de la boutique connectée
try {
    $countQuery = $pdo->prepare("
        SELECT COUNT(*) 
        FROM transferts t 
        WHERE t.statut = 0 
          AND (t.Expedition = :boutique_id OR t.Destination = :boutique_id2)
    ");
    $countQuery->execute([
        ':boutique_id' => $boutique_connectee_id,
        ':boutique_id2' => $boutique_connectee_id
    ]);
    $total_transferts = $countQuery->fetchColumn();
    $totalPages = ceil($total_transferts / $limit);
    if ($totalPages < 1) $totalPages = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

    // Requête paginée avec jointures
    $query = $pdo->prepare("
        SELECT t.*, 
               s.produit_matricule,
               p.designation as produit_designation,
               p.umProduit,
               b1.nom as boutique_expedition,
               b2.nom as boutique_destination,
               st.quantite as quantite_source,
               st.prix as prix_unitaire
        FROM transferts t 
        JOIN stock s ON t.stock_id = s.id 
        JOIN produits p ON s.produit_matricule = p.matricule 
        JOIN boutiques b1 ON t.Expedition = b1.id 
        JOIN boutiques b2 ON t.Destination = b2.id
        JOIN stock st ON t.stock_id = st.id
        WHERE t.statut = 0 
          AND (t.Expedition = :boutique_id OR t.Destination = :boutique_id2)
        ORDER BY t.date DESC, t.id DESC 
        LIMIT :limit OFFSET :offset
    ");

    $query->bindValue(':boutique_id', $boutique_connectee_id, PDO::PARAM_INT);
    $query->bindValue(':boutique_id2', $boutique_connectee_id, PDO::PARAM_INT);
    $query->bindValue(':limit', $limit, PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, PDO::PARAM_INT);
    $query->execute();
    
    $transferts = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiques
    $queryStats = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN t.Expedition = :boutique_id THEN 1 END) as transferts_envoyes,
            COUNT(CASE WHEN t.Destination = :boutique_id2 THEN 1 END) as transferts_recus,
            COUNT(DISTINCT p.matricule) as produits_transferes,
            SUM(st.quantite) as quantite_totale_transferee
        FROM transferts t 
        JOIN stock st ON t.stock_id = st.id 
        JOIN produits p ON st.produit_matricule = p.matricule 
        WHERE t.statut = 0
          AND (t.Expedition = :boutique_id3 OR t.Destination = :boutique_id4)
    ");
    
    $queryStats->execute([
        ':boutique_id' => $boutique_connectee_id,
        ':boutique_id2' => $boutique_connectee_id,
        ':boutique_id3' => $boutique_connectee_id,
        ':boutique_id4' => $boutique_connectee_id
    ]);
    
    $stats = $queryStats->fetch(PDO::FETCH_ASSOC);
    
    $transferts_envoyes = $stats['transferts_envoyes'] ?? 0;
    $transferts_recus = $stats['transferts_recus'] ?? 0;
    $produits_transferes = $stats['produits_transferes'] ?? 0;
    $quantite_totale_transferee = $stats['quantite_totale_transferee'] ?? 0;

} catch (PDOException $e) {
    error_log("Erreur chargement transferts: " . $e->getMessage());
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors du chargement des transferts: " . $e->getMessage(),
        'type' => "error"
    ];
    $transferts_envoyes = 0;
    $transferts_recus = 0;
    $produits_transferes = 0;
    $quantite_totale_transferee = 0;
    $transferts = [];
    $total_transferts = 0;
    $totalPages = 1;
}

// Déterminer le statut de la boutique pour l'affichage
$statut_boutique = '';
if (isset($boutique_connectee['statut']) && isset($boutique_connectee['actif'])) {
    if ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1) {
        $statut_boutique = '<span class="status-badge status-active">Active</span>';
    } else {
        $statut_boutique = '<span class="status-badge status-inactive">';
        if ($boutique_connectee['statut'] != 0) $statut_boutique .= 'Suspendue';
        if ($boutique_connectee['actif'] != 1) $statut_boutique .= ' Désactivée';
        $statut_boutique .= '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Transferts entre boutiques - NGS (New Grace Service)</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0A2540;
            --secondary: #7B61FF;
            --accent: #00D4AA;
            --light: #F8FAFC;
            --dark: #1E293B;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #F8FAFC;
        }

        .font-display {
            font-family: 'Outfit', sans-serif;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #0A2540 0%, #1E3A5F 100%);
        }

        .gradient-accent {
            background: linear-gradient(90deg, #7B61FF 0%, #00D4AA 100%);
        }

        .gradient-blue-btn {
            background: linear-gradient(90deg, #4F86F7 0%, #1A5A9C 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-blue-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .gradient-purple-btn {
            background: linear-gradient(90deg, #8B5CF6 0%, #7C3AED 100%); 
            color: white; 
            transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
        }

        .gradient-purple-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .shadow-soft {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .slide-down {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-active {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-inactive {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar-header, .sidebar-profile, .sidebar-footer {
            flex-shrink: 0;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            transition: background 0.3s ease;
        }

        .sidebar-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) rgba(255, 255, 255, 0.05);
        }

        .nav-link {
            position: relative;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            padding-left: 1.25rem;
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: var(--accent);
            border-radius: 0 4px 4px 0;
        }

        .main-content {
            height: 100vh;
            overflow-y: auto;
        }

        html {
            scroll-behavior: smooth;
        }

        .mobile-menu-btn {
            transition: transform 0.3s ease;
        }

        .mobile-menu-btn.active {
            transform: rotate(90deg);
        }

        .fade-in-row {
            animation: fadeInRow 0.5s ease-out forwards;
            opacity: 0;
        }

        @keyframes fadeInRow {
            to {
                opacity: 1;
            }
        }

        .stats-card {
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(50%, -50%);
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .table-container {
            overflow-x: auto;
        }

        .transfert-row:hover {
            background-color: #f9fafb;
        }

        .badge-unite {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-metres {
            background-color: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
        }
        
        .badge-pieces {
            background-color: #DCFCE7;
            color: #166534;
            border: 1px solid #BBF7D0;
        }
        
        .badge-transfert {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(90deg, #8B5CF6 0%, #7C3AED 100%);
            color: white;
        }
        
        .badge-expedition {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background-color: #FEE2E2;
            color: #991B1B;
        }
        
        .badge-destination {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background-color: #DCFCE7;
            color: #065F46;
        }
        
        .badge-recus {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .arrow-transfert {
            color: #8B5CF6;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        
        .info-box p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }
        
        .input-with-unite {
            position: relative;
        }
        
        .unite-label {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: #f1f5f9;
            padding: 0 8px;
            border-radius: 4px;
            color: #64748b;
            font-size: 12px;
            pointer-events: none;
        }
        
        .input-with-unite input {
            padding-right: 70px;
        }
        
        .stock-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .stock-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stock-card-low {
            border-left-color: #EF4444;
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
        }
        
        .stock-card-medium {
            border-left-color: #F59E0B;
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
        }
        
        .stock-card-good {
            border-left-color: #10B981;
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .nav-link {
                padding: 0.75rem 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .transfert-flow {
                flex-direction: column;
                align-items: center;
            }
            
            .transfert-arrow {
                transform: rotate(90deg);
                margin: 10px 0;
            }
        }
    </style>
</head>
<body class="font-inter min-h-screen bg-gray-50">
    <button id="mobileMenuButton" class="mobile-menu-btn md:hidden fixed top-4 left-4 z-50 p-3 text-white bg-gradient-to-r from-blue-600 to-purple-600 rounded-full shadow-lg hover:shadow-xl transition-shadow">
        <i class="fas fa-bars"></i>
    </button>

    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <div class="flex h-screen">
        <aside id="sidebar" class="sidebar w-64 gradient-bg text-white flex flex-col fixed inset-y-0 left-0 transform -translate-x-full md:sticky md:top-0 md:h-full md:translate-x-0 transition-transform duration-300 ease-in-out z-50 md:z-auto">
            <div class="sidebar-header p-6 border-b border-white/10">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full gradient-accent flex items-center justify-center shadow-lg">
                        <span class="font-bold text-white text-lg font-display">NGS</span>
                    </div>
                    <div>
                        <h1 class="font-display text-xl font-bold">NGS</h1>
                        <p class="text-xs text-gray-300"><?= htmlspecialchars($boutique_connectee['nom']) ?></p>
                        <div class="mt-1"><?= $statut_boutique ?></div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav p-4 space-y-1">
                <a href="dashboard_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors relative">
                    <i class="fas fa-chart-line w-5 text-gray-300"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="ventes_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-shopping-cart w-5 text-gray-300"></i>
                    <span>Ventes</span>
                </a>
                <a href="paiements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-credit-card w-5 text-gray-300"></i>
                    <span>Paiements</span>
                </a>
                <a href="stock_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-box w-5 text-gray-300"></i>
                    <span>Mes Stocks</span>
                </a>
                <a href="transferts-boutique.php" class="nav-link active flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-truck-loading w-5 text-white"></i>
                    <span>Transferts</span>
                    <?php if ($total_transferts > 0): ?>
                        <span class="notification-badge"><?= $total_transferts ?></span>
                    <?php endif; ?>
                </a>
                <a href="mouvements.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-exchange-alt w-5 text-gray-300"></i>
                    <span>Mouvements Caisse</span>
                </a>
                <a href="rapports_boutique.php" class="nav-link flex items-center space-x-3 p-3 rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-bar w-5 text-gray-300"></i>
                    <span>Rapports</span>
                </a>
            </nav>

            <div class="sidebar-footer p-4 border-t border-white/10">
                <a href="../models/logout.php" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-500/10 text-red-300 hover:text-red-200 transition-colors">
                    <i class="fas fa-sign-out-alt w-5"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <div class="main-content flex-1 overflow-y-auto">
            <header class="bg-white border-b border-gray-200 p-4 md:p-6 sticky top-0 z-30 shadow-sm">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-900">Transferts entre boutiques</h1>
                        <p class="text-gray-600 text-sm md:text-base">Boutique : <?= htmlspecialchars($boutique_connectee['nom']) ?> | <?= $statut_boutique ?> | Nouveau Grace Service</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <?php if ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1): ?>
                        <button onclick="openTransfertModal()"
                            class="px-4 py-3 gradient-purple-btn text-white rounded-lg hover:opacity-90 flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="hidden md:inline">Nouveau transfert</span>
                            <span class="md:hidden">Transfert</span>
                        </button>
                        <?php else: ?>
                        <button onclick="alert('Votre boutique est désactivée. Vous ne pouvez pas effectuer de transferts.');"
                            class="px-4 py-3 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center space-x-2 shadow-md">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="hidden md:inline">Transfert désactivé</span>
                            <span class="md:hidden">Désactivé</span>
                        </button>
                        <?php endif; ?>
                        <a href="stock_boutique.php"
                            class="px-4 py-3 gradient-blue-btn text-white rounded-lg flex items-center space-x-2 shadow-md hover-lift transition-all duration-300">
                            <i class="fas fa-warehouse"></i>
                            <span class="hidden md:inline">Voir mes stocks</span>
                            <span class="md:hidden">Stocks</span>
                        </a>
                    </div>
                </div>
            </header>

            <main class="p-4 md:p-6">
                <?php if ($warning_message): ?>
                    <div class="mb-6 fade-in relative z-10 animate-fade-in">
                        <div class="bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-xl p-4 flex items-center justify-between shadow-soft">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-exclamation-triangle text-yellow-600 text-lg"></i>
                                <span><?= htmlspecialchars($warning_message) ?></span>
                            </div>
                            <button onclick="this.parentElement.parentElement.style.display='none'" class="text-yellow-400 hover:text-yellow-600 transition-colors">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($flash_message): ?>
                    <div class="mb-6 fade-in relative z-10 animate-fade-in">
                        <div class="
                            <?php if ($flash_message_type === 'success'): ?>bg-green-50 text-green-700 border border-green-200
                            <?php elseif ($flash_message_type === 'error'): ?>bg-red-50 text-red-700 border border-red-200
                            <?php else: ?>bg-blue-50 text-blue-700 border border-blue-200<?php endif; ?>
                            rounded-xl p-4 flex items-center justify-between shadow-soft">
                            <div class="flex items-center space-x-3">
                                <?php if ($flash_message_type === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                                <?php elseif ($flash_message_type === 'error'): ?>
                                    <i class="fas fa-exclamation-circle text-red-600 text-lg"></i>
                                <?php else: ?>
                                    <i class="fas fa-info-circle text-blue-600 text-lg"></i>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($flash_message) ?></span>
                            </div>
                            <button onclick="this.parentElement.parentElement.style.display='none'" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8 stats-grid">
                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-purple-500 animate-fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-exchange-alt text-purple-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-600">Total</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $total_transferts ?></h3>
                        <p class="text-gray-600">Transferts impliquant votre boutique</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-indigo-500 animate-fade-in" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center">
                                <i class="fas fa-paper-plane text-indigo-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-indigo-600">Envoyés</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $transferts_envoyes ?></h3>
                        <p class="text-gray-600">Transferts effectués par votre boutique</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-emerald-500 animate-fade-in" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                                <i class="fas fa-inbox text-emerald-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-emerald-600">Reçus</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= $transferts_recus ?></h3>
                        <p class="text-gray-600">Transferts reçus par votre boutique</p>
                    </div>

                    <div class="bg-white rounded-2xl shadow-soft p-6 stats-card border-l-4 border-cyan-500 animate-fade-in" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-cyan-100 flex items-center justify-center">
                                <i class="fas fa-weight-hanging text-cyan-600 text-xl"></i>
                            </div>
                            <span class="text-sm font-medium text-cyan-600">Quantité totale</span>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-2"><?= number_format($quantite_totale_transferee, 3) ?></h3>
                        <p class="text-gray-600">Unités transférées</p>
                    </div>
                </div>

                <!-- Section stocks disponibles pour transfert -->
                <?php if (!empty($stocks) && $boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1): ?>
                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Stocks disponibles pour transfert</h2>
                        <span class="text-sm text-gray-500"><?= count($stocks) ?> produits disponibles</span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($stocks as $stock): 
                            $uniteText = $stock['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                            $quantiteDisponible = number_format($stock['quantite'], 3);
                            $valeurStock = number_format($stock['quantite'] * $stock['prix'], 2);
                            
                            // Déterminer la classe CSS selon la quantité
                            if ($stock['quantite'] < 5) {
                                $cardClass = 'stock-card-low';
                            } elseif ($stock['quantite'] < 10) {
                                $cardClass = 'stock-card-medium';
                            } else {
                                $cardClass = 'stock-card-good';
                            }
                        ?>
                        <div class="stock-card p-4 rounded-xl <?= $cardClass ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($stock['designation']) ?></h3>
                                    <p class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($stock['produit_matricule']) ?></p>
                                </div>
                                <span class="badge-unite <?= $stock['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                    <i class="<?= $stock['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1 text-xs"></i>
                                    <?= $uniteText ?>
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <span class="text-lg font-bold text-gray-900"><?= $quantiteDisponible ?></span>
                                    <span class="text-sm text-gray-500 ml-1"><?= $uniteText ?></span>
                                </div>
                                <span class="text-sm font-medium text-green-600"><?= $valeurStock ?> $</span>
                            </div>
                            
                            <div class="text-xs text-gray-500 mb-3">
                                <i class="fas fa-store mr-1"></i> Votre boutique
                            </div>
                            
                            <button onclick="selectStockForTransfert(<?= $stock['id'] ?>, '<?= htmlspecialchars(addslashes($stock['designation'])) ?>', <?= $stock['quantite'] ?>, '<?= $stock['umProduit'] ?>')"
                                    class="w-full py-2 bg-gradient-to-r from-purple-600 to-indigo-600 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                                <i class="fas fa-exchange-alt mr-2"></i>Transférer ce stock
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif (!empty($stocks) && ($boutique_connectee['statut'] != 0 || $boutique_connectee['actif'] != 1)): ?>
                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Vos stocks (transferts désactivés)</h2>
                        <span class="text-sm text-gray-500"><?= count($stocks) ?> produits</span>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-yellow-700 font-medium">Transferts temporairement désactivés</p>
                                <p class="text-xs text-yellow-600 mt-1">Votre boutique est actuellement désactivée. Vous pouvez consulter vos stocks mais les transferts sont temporairement indisponibles.</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($stocks as $stock): 
                            $uniteText = $stock['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                            $quantiteDisponible = number_format($stock['quantite'], 3);
                            $valeurStock = number_format($stock['quantite'] * $stock['prix'], 2);
                            $cardClass = 'stock-card-good';
                        ?>
                        <div class="stock-card p-4 rounded-xl <?= $cardClass ?>">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($stock['designation']) ?></h3>
                                    <p class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($stock['produit_matricule']) ?></p>
                                </div>
                                <span class="badge-unite <?= $stock['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces' ?>">
                                    <i class="<?= $stock['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube' ?> mr-1 text-xs"></i>
                                    <?= $uniteText ?>
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center mb-2">
                                <div>
                                    <span class="text-lg font-bold text-gray-900"><?= $quantiteDisponible ?></span>
                                    <span class="text-sm text-gray-500 ml-1"><?= $uniteText ?></span>
                                </div>
                                <span class="text-sm font-medium text-green-600"><?= $valeurStock ?> $</span>
                            </div>
                            
                            <div class="text-xs text-gray-500 mb-3">
                                <i class="fas fa-store mr-1"></i> Votre boutique
                            </div>
                            
                            <button onclick="alert('Transferts désactivés pour votre boutique.');"
                                    class="w-full py-2 bg-gray-400 text-white text-sm font-medium rounded-lg cursor-not-allowed">
                                <i class="fas fa-exchange-alt mr-2"></i>Transfert désactivé
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-soft p-6 mb-6 animate-fade-in" style="animation-delay: 0.4s">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="relative flex-1 max-w-lg">
                            <input type="text"
                                id="searchInput"
                                placeholder="Rechercher par boutique, produit ou ID..."
                                class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-secondary focus:border-secondary transition-all shadow-sm">
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-sm text-gray-600 hidden md:flex items-center space-x-2">
                                <i class="fas fa-info-circle text-purple-500"></i>
                                <span>Page <?= $page ?> sur <?= $totalPages ?></span>
                            </div>
                            <button onclick="refreshPage()" class="p-2 text-gray-600 hover:text-purple-600 transition-colors" title="Actualiser">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-soft overflow-hidden animate-fade-in" style="animation-delay: 0.5s">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Historique des transferts - Votre boutique</h2>
                    </div>

                    <div class="table-container">
                        <table class="w-full min-w-[1000px]" id="transfertsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfert</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valeur</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="tableBody">
                                <?php if (!empty($transferts)): ?>
                                    <?php foreach ($transferts as $index => $transfert): ?>
                                        <?php 
                                        $uniteClass = $transfert['umProduit'] == 'metres' ? 'badge-metres' : 'badge-pieces';
                                        $uniteText = $transfert['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                        $uniteIcon = $transfert['umProduit'] == 'metres' ? 'fas fa-ruler-combined' : 'fas fa-cube';
                                        $isExpediteur = ($transfert['Expedition'] == $boutique_connectee_id);
                                        $transfertType = $isExpediteur ? 'envoi' : 'réception';
                                        $typeBadgeClass = $isExpediteur ? 'badge-expedition' : 'badge-recus';
                                        $typeIcon = $isExpediteur ? 'fas fa-paper-plane' : 'fas fa-inbox';
                                        ?>
                                        <tr class="transfert-row hover:bg-gray-50 transition-colors fade-in-row"
                                            data-transfert-id="<?= htmlspecialchars($transfert['id']) ?>"
                                            data-expedition="<?= htmlspecialchars(strtolower($transfert['boutique_expedition'])) ?>"
                                            data-destination="<?= htmlspecialchars(strtolower($transfert['boutique_destination'])) ?>"
                                            data-produit="<?= htmlspecialchars(strtolower($transfert['produit_designation'])) ?>"
                                            style="animation-delay: <?= $index * 0.05 ?>s">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-mono font-bold">#<?= htmlspecialchars($transfert['id']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d/m/Y', strtotime($transfert['date'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div>
                                                    <div class="flex items-center">
                                                        <span class="font-medium"><?= htmlspecialchars($transfert['produit_designation']) ?></span>
                                                        <span class="badge-unite ml-2 <?= $uniteClass ?>">
                                                            <i class="<?= $uniteIcon ?> mr-1 text-xs"></i>
                                                            <?= $uniteText ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 font-mono mt-1">
                                                        <?= htmlspecialchars($transfert['produit_matricule']) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center space-x-2 transfert-flow">
                                                    <span class="<?= $isExpediteur ? 'badge-expedition' : '' ?>">
                                                        <i class="fas fa-paper-plane mr-1"></i>
                                                        <?= htmlspecialchars($transfert['boutique_expedition']) ?>
                                                    </span>
                                                    <span class="transfert-arrow arrow-transfert">
                                                        <i class="fas fa-long-arrow-alt-right"></i>
                                                    </span>
                                                    <span class="<?= !$isExpediteur ? 'badge-recus' : '' ?>">
                                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                                        <?= htmlspecialchars($transfert['boutique_destination']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="font-bold"><?= number_format($transfert['quantite_source'], 3) ?></span>
                                                    <span class="text-xs text-gray-500 ml-1">
                                                        <?= $uniteText ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-lg text-sm font-medium">
                                                        <?= number_format($transfert['quantite_source'] * $transfert['prix_unitaire'], 2) ?> $
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <span class="<?= $typeBadgeClass ?>">
                                                    <i class="<?= $typeIcon ?> mr-1"></i>
                                                    <?= $transfertType ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center">
                                            <div class="text-gray-500">
                                                <i class="fas fa-exchange-alt text-4xl mb-4"></i>
                                                <p class="text-lg">Aucun transfert enregistré</p>
                                                <p class="text-sm mt-2">
                                                    <?php if ($boutique_connectee['statut'] == 0 && $boutique_connectee['actif'] == 1): ?>
                                                        Effectuez votre premier transfert en utilisant le bouton "Nouveau transfert"
                                                    <?php else: ?>
                                                        Les transferts sont désactivés pour votre boutique
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="noResults" class="hidden text-center py-12">
                        <div class="bg-gray-50 rounded-2xl p-8 max-w-md mx-auto shadow-soft">
                            <i class="fas fa-search text-6xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Aucun résultat trouvé</h3>
                            <p class="text-gray-600">Aucun transfert ne correspond à votre recherche</p>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 hidden sm:block">
                                    Affichage de <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> à
                                    <span class="font-medium"><?= min($page * $limit, $total_transferts) ?></span> sur
                                    <span class="font-medium"><?= $total_transferts ?></span> transferts
                                </div>

                                <div class="flex items-center space-x-2 mx-auto sm:mx-0">
                                    <a href="?page=<?= max(1, $page - 1) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>

                                    <?php
                                    $startPage = max(1, $page - 1);
                                    $endPage = min($totalPages, $page + 1);

                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isActive = $i == $page;
                                    ?>
                                        <a href="?page=<?= $i ?>"
                                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors <?= $isActive ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-md' : 'text-gray-700 hover:bg-gray-100 border border-gray-300' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php } ?>

                                    <a href="?page=<?= min($totalPages, $page + 1) ?>"
                                        class="px-3 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors <?= $page >= $totalPages ? 'opacity-50 pointer-events-none' : '' ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal pour nouveau transfert -->
    <div id="transfertModal" class="modal transition-all duration-300 ease-in-out">
        <div class="modal-content slide-down p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-bold text-gray-900">Nouveau transfert - <?= htmlspecialchars($boutique_connectee['nom']) ?></h3>
                <button onclick="closeTransfertModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form id="transfertForm" method="POST" action="transferts-boutique.php">
                <input type="hidden" name="effectuer_transfert" value="1">
                
                <div class="space-y-4">
                    <!-- Boutique source (pré-remplie et non modifiable) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Boutique source</label>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-medium"><?= htmlspecialchars($boutique_connectee['nom']) ?></span>
                                    <span class="text-xs text-gray-500 ml-2">(Votre boutique)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sélection du stock -->
                    <div>
                        <label for="stock_id" class="block text-sm font-medium text-gray-700 mb-1">Stock à transférer *</label>
                        <select name="stock_id" id="stock_id" required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                                onchange="updateStockInfo()">
                            <option value="">Sélectionnez un stock à transférer</option>
                            <?php foreach ($stocks as $stock): 
                                $uniteText = $stock['umProduit'] == 'metres' ? 'mètres' : 'pièces';
                                $quantiteDisponible = number_format($stock['quantite'], 3);
                            ?>
                                <option value="<?= $stock['id'] ?>" 
                                        data-quantite="<?= $stock['quantite'] ?>"
                                        data-produit="<?= htmlspecialchars($stock['designation']) ?>"
                                        data-unite="<?= $stock['umProduit'] ?>"
                                        data-prix="<?= $stock['prix'] ?>">
                                    <?= htmlspecialchars($stock['designation']) ?> 
                                    (<?= $quantiteDisponible ?> <?= $uniteText ?> disponibles)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="stockInfo" class="info-box hidden mt-2">
                            <p><strong>Quantité disponible :</strong> <span id="quantiteDisponible">0</span> <span id="uniteDisponible">unités</span></p>
                            <p><strong>Produit :</strong> <span id="produitInfo">-</span></p>
                            <p><strong>Prix unitaire :</strong> <span id="prixUnitaire">0.00</span> $</p>
                        </div>
                    </div>
                    
                    <!-- Boutique destination -->
                    <div>
                        <label for="boutique_destination" class="block text-sm font-medium text-gray-700 mb-1">Boutique destination *</label>
                        <select name="boutique_destination" id="boutique_destination" required
                                class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3">
                            <option value="">Sélectionnez une boutique destination</option>
                            <?php foreach ($boutiques_destination as $boutique): ?>
                                <option value="<?= $boutique['id'] ?>"><?= htmlspecialchars($boutique['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($boutiques_destination)): ?>
                        <p class="text-xs text-red-500 mt-1">Aucune autre boutique disponible pour le transfert</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quantité à transférer -->
                    <div>
                        <label for="quantite_transferee" class="block text-sm font-medium text-gray-700 mb-1">Quantité à transférer *</label>
                        <div class="input-with-unite">
                            <input type="number" name="quantite_transferee" id="quantite_transferee" required step="0.001" min="0.001"
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-secondary focus:border-secondary p-3"
                                   placeholder="Ex: 5.000">
                            <span id="quantiteUniteLabel" class="unite-label">unités</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" id="quantiteMaxInfo"></p>
                        <p class="text-xs text-red-500 mt-1 hidden" id="quantiteError"></p>
                    </div>
                    
                    <!-- Information de valeur -->
                    <div id="valeurTransfert" class="hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-blue-700">Valeur du transfert</p>
                                    <p class="text-lg font-bold text-blue-900" id="valeurTotale">0.00 $</p>
                                </div>
                                <i class="fas fa-money-bill-wave text-blue-500 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Processus de transfert -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-purple-500 mt-0.5"></i>
                            <div>
                                <p class="text-sm text-purple-700 font-medium">Processus de transfert</p>
                                <ul class="text-xs text-purple-600 mt-1 list-disc pl-4 space-y-1">
                                    <li>La quantité sera déduite de votre stock</li>
                                    <li>La quantité sera ajoutée au stock de la boutique destination</li>
                                    <li>Un nouveau stock sera créé si le produit n'existe pas dans la boutique destination</li>
                                    <li>Le prix unitaire est conservé lors du transfert</li>
                                    <li>Le transfert sera enregistré dans l'historique</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeTransfertModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="effectuer_transfert"
                            class="px-4 py-2 gradient-purple-btn text-white rounded-lg hover:opacity-90 transition-opacity shadow-md"
                            id="submitTransfertBtn">
                        Effectuer le transfert
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // --- GESTION DE LA SIDEBAR MOBILE ---
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
            mobileMenuButton.classList.toggle('active');
        }

        mobileMenuButton.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // --- GESTION DES LIENS ACTIFS ---
        const currentPage = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });

        // --- GESTION DE LA MODALE DE TRANSFERT ---
        const transfertModal = document.getElementById('transfertModal');
        const stockSelect = document.getElementById('stock_id');
        const stockInfo = document.getElementById('stockInfo');
        const quantiteDisponible = document.getElementById('quantiteDisponible');
        const uniteDisponible = document.getElementById('uniteDisponible');
        const produitInfo = document.getElementById('produitInfo');
        const prixUnitaire = document.getElementById('prixUnitaire');
        const quantiteMaxInfo = document.getElementById('quantiteMaxInfo');
        const quantiteTransfereeInput = document.getElementById('quantite_transferee');
        const quantiteUniteLabel = document.getElementById('quantiteUniteLabel');
        const quantiteError = document.getElementById('quantiteError');
        const valeurTransfert = document.getElementById('valeurTransfert');
        const valeurTotale = document.getElementById('valeurTotale');
        const submitTransfertBtn = document.getElementById('submitTransfertBtn');

        function updateStockInfo() {
            const selectedOption = stockSelect.options[stockSelect.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const quantite = parseFloat(selectedOption.getAttribute('data-quantite'));
                const produit = selectedOption.getAttribute('data-produit');
                const unite = selectedOption.getAttribute('data-unite');
                const prix = parseFloat(selectedOption.getAttribute('data-prix'));
                const uniteText = unite === 'metres' ? 'mètres' : 'pièces';
                
                // Mettre à jour les informations
                quantiteDisponible.textContent = quantite.toFixed(3);
                uniteDisponible.textContent = uniteText;
                produitInfo.textContent = produit;
                prixUnitaire.textContent = prix.toFixed(2);
                
                // Mettre à jour le label d'unité
                quantiteUniteLabel.textContent = uniteText;
                
                // Mettre à jour l'information de quantité max
                quantiteMaxInfo.textContent = `Quantité maximale transférable : ${quantite.toFixed(3)} ${uniteText}`;
                
                // Réinitialiser les erreurs
                quantiteError.classList.add('hidden');
                quantiteError.textContent = '';
                
                // Activer/désactiver le bouton de soumission
                const boutiquesDisponibles = document.getElementById('boutique_destination').options.length > 1;
                submitTransfertBtn.disabled = !boutiquesDisponibles;
                
                // Afficher les informations
                stockInfo.classList.remove('hidden');
                
                // Mettre à jour la valeur max de l'input
                quantiteTransfereeInput.max = quantite;
                quantiteTransfereeInput.value = '';
                
                // Masquer la valeur du transfert
                valeurTransfert.classList.add('hidden');
                
            } else {
                stockInfo.classList.add('hidden');
                quantiteMaxInfo.textContent = '';
                valeurTransfert.classList.add('hidden');
                submitTransfertBtn.disabled = true;
            }
        }

        // Calculer la valeur du transfert en temps réel
        quantiteTransfereeInput.addEventListener('input', function() {
            const selectedOption = stockSelect.options[stockSelect.selectedIndex];
            if (!selectedOption || !selectedOption.value) return;
            
            const quantite = parseFloat(selectedOption.getAttribute('data-quantite'));
            const prix = parseFloat(selectedOption.getAttribute('data-prix'));
            const quantiteSaisie = parseFloat(this.value) || 0;
            const unite = selectedOption.getAttribute('data-unite');
            const uniteText = unite === 'metres' ? 'mètres' : 'pièces';
            
            // Vérifier la quantité
            if (quantiteSaisie > quantite) {
                quantiteError.textContent = `Quantité trop élevée ! Maximum : ${quantite.toFixed(3)} ${uniteText}`;
                quantiteError.classList.remove('hidden');
                valeurTransfert.classList.add('hidden');
                submitTransfertBtn.disabled = true;
            } else if (quantiteSaisie <= 0) {
                quantiteError.textContent = 'La quantité doit être supérieure à 0';
                quantiteError.classList.remove('hidden');
                valeurTransfert.classList.add('hidden');
                submitTransfertBtn.disabled = true;
            } else {
                quantiteError.classList.add('hidden');
                valeurTransfert.classList.remove('hidden');
                submitTransfertBtn.disabled = false;
                
                // Calculer et afficher la valeur
                const valeur = quantiteSaisie * prix;
                valeurTotale.textContent = valeur.toFixed(2) + ' $';
            }
        });

        // Fonction pour pré-sélectionner un stock depuis la carte
        function selectStockForTransfert(stockId, produitNom, quantite, unite) {
            stockSelect.value = stockId;
            updateStockInfo();
            openTransfertModal();
            
            // Focus sur la quantité
            setTimeout(() => {
                quantiteTransfereeInput.focus();
            }, 300);
        }

        function openTransfertModal() {
            // Vérifier s'il y a des boutiques destination
            const boutiquesDisponibles = document.getElementById('boutique_destination').options.length > 1;
            
            if (!boutiquesDisponibles) {
                alert('Aucune autre boutique disponible pour effectuer un transfert.');
                return;
            }
            
            // Réinitialiser le formulaire
            document.getElementById('transfertForm').reset();
            stockInfo.classList.add('hidden');
            quantiteMaxInfo.textContent = '';
            valeurTransfert.classList.add('hidden');
            quantiteError.classList.add('hidden');
            transfertModal.classList.add('show');
            
            // Activer/désactiver le bouton
            submitTransfertBtn.disabled = !boutiquesDisponibles;
        }

        function closeTransfertModal() {
            transfertModal.classList.remove('show');
        }

        // Écouter les changements sur le select de stock
        stockSelect.addEventListener('change', updateStockInfo);

        // --- GESTION DE LA RECHERCHE ---
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.transfert-row');
            let found = false;

            rows.forEach(row => {
                const transfertId = row.dataset.transfertId;
                const expedition = row.dataset.expedition;
                const destination = row.dataset.destination;
                const produit = row.dataset.produit;

                if (transfertId.includes(searchTerm) || 
                    expedition.includes(searchTerm) || 
                    destination.includes(searchTerm) || 
                    produit.includes(searchTerm)) {
                    row.style.display = '';
                    found = true;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('noResults').classList.toggle('hidden', found);
            document.getElementById('tableBody').classList.toggle('hidden', !found && searchTerm !== '');
        });

        // --- FONCTION DE RAFRAÎCHISSEMENT ---
        function refreshPage() {
            const button = event.target.closest('button');
            button.classList.add('animate-spin');
            setTimeout(() => {
                button.classList.remove('animate-spin');
                window.location.reload();
            }, 500);
        }

        // --- VALIDATION DU FORMULAIRE DE TRANSFERT ---
        const transfertForm = document.getElementById('transfertForm');
        
        transfertForm.addEventListener('submit', function(e) {
            const stockId = document.getElementById('stock_id').value;
            const quantiteTransferee = parseFloat(document.getElementById('quantite_transferee').value);
            const boutiqueDestination = document.getElementById('boutique_destination').value;
            
            // Validation basique
            if (!stockId) {
                e.preventDefault();
                alert('Veuillez sélectionner un stock à transférer.');
                return false;
            }
            
            if (!boutiqueDestination) {
                e.preventDefault();
                alert('Veuillez sélectionner une boutique destination.');
                return false;
            }
            
            if (quantiteTransferee <= 0 || isNaN(quantiteTransferee)) {
                e.preventDefault();
                alert('La quantité transférée doit être supérieure à 0.');
                return false;
            }
            
            // Validation de la quantité disponible
            const selectedOption = stockSelect.options[stockSelect.selectedIndex];
            const quantiteDisponible = selectedOption ? parseFloat(selectedOption.getAttribute('data-quantite')) : 0;
            
            if (quantiteTransferee > quantiteDisponible) {
                e.preventDefault();
                alert(`Quantité insuffisante. Quantité disponible : ${quantiteDisponible.toFixed(3)}`);
                return false;
            }
            
            // Confirmation
            if (!confirm('Êtes-vous sûr de vouloir effectuer ce transfert ?')) {
                e.preventDefault();
                return false;
            }
            
            // Désactiver le bouton pour éviter les doubles clics
            submitTransfertBtn.disabled = true;
            submitTransfertBtn.innerHTML = '<span class="loading-spinner"></span> Traitement en cours...';
            
            // Ajouter un délai pour permettre à l'utilisateur de voir le spinner
            setTimeout(() => {
                submitTransfertBtn.disabled = false;
                submitTransfertBtn.innerHTML = 'Effectuer le transfert';
            }, 5000);
            
            return true;
        });

        // --- NAVIGATION CLAVIER ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (transfertModal.classList.contains('show')) closeTransfertModal();
                if (!sidebar.classList.contains('-translate-x-full')) toggleSidebar();
            }
        });

        // --- ANIMATION DES LIGNES AU CHARGEMENT ---
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.fade-in-row');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
            });
        });

        // --- GESTION DES EFFETS VISUELS ---
        document.querySelectorAll('.stats-card, .nav-link').forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // --- DÉTECTION DE LA CONNEXION ---
        window.addEventListener('online', function() {
            showNotification('Vous êtes reconnecté à internet', 'success');
        });

        window.addEventListener('offline', function() {
            showNotification('Vous êtes hors ligne', 'warning');
        });

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg text-white z-50 animate-fade-in ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 
                'bg-blue-500'
            }`;
            notification.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Fermer le modal en cliquant en dehors
        document.getElementById('transfertModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTransfertModal();
            }
        });
    </script>
</body>
</html>