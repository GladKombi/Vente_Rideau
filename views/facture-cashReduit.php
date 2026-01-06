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
    $query = $pdo->prepare("SELECT c.*, b.nom as boutique_nom, b.email as boutique_email FROM commandes c JOIN boutiques b ON c.boutique_id = b.id WHERE c.id = ? AND c.boutique_id = ?");
    $query->execute([$commande_id, $boutique_id]);
    $commande = $query->fetch(PDO::FETCH_ASSOC);

    if (!$commande) { die("Commande non trouvée."); }

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

$numero_facture = $commande['numero_facture'] ?: ('NGS-' . str_pad($commande_id, 6, '0', STR_PAD_LEFT));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>FAC <?= $numero_facture ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { margin: 0; size: 80mm auto; }
            body { margin: 0; padding: 0; background: white; font-size: 11px; }
            .no-print { display: none !important; }
            .ticket { 
                width: 76mm; 
                margin: 0 auto; 
                padding: 1mm 2mm !important; /* Marges réduites */
            }
            .cut-space { height: 15mm; display: block; } /* Réduit de 20mm à 15mm */
        }

        /* Styles écran */
        body { background: #f3f4f6; padding: 20px; font-family: 'monospace', sans-serif; }
        .ticket { width: 80mm; background: white; padding: 5mm; margin: 0 auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); color: black; }
        .line { border-top: 1px dashed black; margin: 5px 0; }
        table td { padding: 2px 0; line-height: 1.1; }
    </style>
</head>
<body>

    <div class="no-print mb-6 flex justify-center">
        <button onclick="window.print()" class="bg-black text-white px-6 py-2 rounded-lg font-bold shadow-lg">
            IMPRIMER (ÉCONOMIE PAPIER)
        </button>
    </div>

    <div class="ticket">
        <div class="text-center">
            <h1 class="text-lg font-black">NEW GRACE SERVICE</h1>
            <p class="text-[10px] uppercase font-bold"><?= htmlspecialchars($commande['boutique_nom']) ?></p>
        </div>

        <div class="line"></div>

        <div class="text-[10px]">
            <div class="flex justify-between">
                <span>FAC N°: <b><?= $numero_facture ?></b></span>
                <span><?= date('d/m/y H:i', strtotime($commande['date_commande'])) ?></span>
            </div>
            <div class="mt-1">
                <span>CLIENT: <b><?= htmlspecialchars($commande['client_nom'] ?: 'CASH') ?></b></span>
            </div>
        </div>

        <div class="line"></div>

        <table class="w-full text-[10px]">
            <thead>
                <tr class="border-b border-black text-left">
                    <th class="pb-1">ART</th>
                    <th class="pb-1 text-center">QTÉ</th>
                    <th class="pb-1 text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($articles as $art): ?>
                <tr>
                    <td class="align-top">
                        <div class="font-bold"><?= htmlspecialchars($art['designation']) ?></div>
                    </td>
                    <td class="text-center align-top"><?= (float)$art['quantite'] ?></td>
                    <td class="text-right align-top font-bold"><?= number_format($art['total_ligne'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="line"></div>

        <div class="text-right text-[11px] font-bold space-y-0.5">
            <div class="flex justify-between">
                <span>TOTAL GÉNÉRAL:</span>
                <span><?= number_format($total_general, 2) ?> $</span>
            </div>
            <?php if($total_paye > 0): ?>
            <div class="flex justify-between text-[10px] font-normal">
                <span>Payé:</span>
                <span><?= number_format($total_paye, 2) ?> $</span>
            </div>
            <div class="flex justify-between border-t border-black">
                <span>RESTE:</span>
                <span><?= number_format($reste_a_payer, 2) ?> $</span>
            </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-4 text-[9px]">
            <p class="font-bold">MERCI POUR VOTRE CONFIANCE !</p>
            <p>+243 977 421 421 | NGS</p>
        </div>

        <div class="cut-space"></div>
    </div>

</body>
</html>