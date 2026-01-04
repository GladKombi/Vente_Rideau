<?php
// facture-cash.php
include '../connexion/connexion.php';

// 1. SÉCURITÉ ET RÉCUPÉRATION
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

$boutique_id = $_SESSION['boutique_id'] ?? null;
$commande_id = $_GET['id'] ?? null;

if (!$commande_id || !$boutique_id) {
    die("ID manquant.");
}

try {
    // RÉCUPÉRATION COMMANDE
    $query = $pdo->prepare("SELECT c.*, b.nom as boutique_nom FROM commandes c JOIN boutiques b ON c.boutique_id = b.id WHERE c.id = ? AND c.boutique_id = ?");
    $query->execute([$commande_id, $boutique_id]);
    $commande = $query->fetch(PDO::FETCH_ASSOC);

    // RÉCUPÉRATION ARTICLES
    $queryArticles = $pdo->prepare("SELECT cp.quantite, cp.prix_unitaire, p.designation, p.umProduit, (cp.quantite * cp.prix_unitaire) as total_ligne FROM commande_produits cp JOIN stock s ON cp.stock_id = s.id JOIN produits p ON s.produit_matricule = p.matricule WHERE cp.commande_id = ? AND cp.statut = 0");
    $queryArticles->execute([$commande_id]);
    $articles = $queryArticles->fetchAll(PDO::FETCH_ASSOC);

    $total_general = 0;
    foreach ($articles as $art) { $total_general += $art['total_ligne']; }

    $queryPay = $pdo->prepare("SELECT SUM(montant) as paye FROM paiements WHERE commandes_id = ? AND statut = 0");
    $queryPay->execute([$commande_id]);
    $total_paye = $queryPay->fetch(PDO::FETCH_ASSOC)['paye'] ?? 0;
    $reste_a_payer = $total_general - $total_paye;

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture_<?= $commande['numero_facture'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { 
                margin: 0; 
                size: 80mm auto; 
            }
            body { margin: 0; padding: 0; background: white; }
            .no-print { display: none !important; }
            .ticket { 
                width: 72mm; 
                margin: 0 auto;
                padding: 0;
            }
            /* L'espace critique pour laisser le temps au massicot de couper sans toucher au texte */
            .cut-space {
                height: 40mm; 
                display: block;
            }
        }

        body { font-family: 'Courier New', Courier, monospace; background-color: #f0f2f5; }
        .ticket { 
            width: 80mm; 
            background: white; 
            padding: 5mm; 
            margin: 20px auto; 
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
            color: black !important;
        }
        .facture-noir * { border-color: black !important; color: black !important; }
        .line { border-top: 1px dashed black; margin: 8px 0; }
    </style>
</head>
<body>

    <div class="no-print flex justify-center py-4 bg-gray-800 w-full mb-4">
        <button onclick="window.print()" class="bg-green-600 text-white px-10 py-3 rounded font-bold hover:bg-green-700">
            IMPRIMER LA FACTURE
        </button>
    </div>

    <div class="ticket facture-noir">
        <div class="text-center">
            <h1 class="text-lg font-bold">NEW GRACE SERVICE</h1>
            <p class="text-xs">Boutique: <?= htmlspecialchars($commande['boutique_nom']) ?></p>
            <p class="text-[10px]">Kinshasa, RD Congo</p>
        </div>

        <div class="line"></div>

        <div class="text-[11px]">
            <p><b>FAC N°:</b> <?= $commande['numero_facture'] ?></p>
            <p><b>Date:</b> <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
            <p><b>Client:</b> <?= htmlspecialchars($commande['client_nom'] ?: 'CASH') ?></p>
        </div>

        <div class="line"></div>

        <table class="w-full text-[11px]">
            <thead>
                <tr class="border-b border-black text-left">
                    <th>Article</th>
                    <th class="text-center">Qté</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($articles as $art): ?>
                <tr>
                    <td class="py-1"><?= htmlspecialchars($art['designation']) ?></td>
                    <td class="text-center"><?= (float)$art['quantite'] ?></td>
                    <td class="text-right font-bold"><?= number_format($art['total_ligne'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="line"></div>

        <div class="text-right text-sm font-black">
            <p>TOTAL: <?= number_format($total_general, 2) ?> $</p>
            <?php if($total_paye > 0): ?>
                <p class="text-xs font-normal">Versé: <?= number_format($total_paye, 2) ?> $</p>
                <p class="text-xs">Reste: <?= number_format($reste_a_payer, 2) ?> $</p>
            <?php endif; ?>
        </div>

        <div class="mt-6 text-center text-[10px]">
            <p>MERCI POUR VOTRE CONFIANCE</p>
        </div>

        <div class="cut-space"></div>
    </div>

</body>
</html>