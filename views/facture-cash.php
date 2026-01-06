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

    if (!$commande) {
        die("Commande non trouvée.");
    }

    // RÉCUPÉRATION ARTICLES
    $queryArticles = $pdo->prepare("SELECT cp.quantite, cp.prix_unitaire, p.designation, p.umProduit, (cp.quantite * cp.prix_unitaire) as total_ligne FROM commande_produits cp JOIN stock s ON cp.stock_id = s.id JOIN produits p ON s.produit_matricule = p.matricule WHERE cp.commande_id = ? AND cp.statut = 0");
    $queryArticles->execute([$commande_id]);
    $articles = $queryArticles->fetchAll(PDO::FETCH_ASSOC);

    $total_general = 0;
    foreach ($articles as $art) {
        $total_general += $art['total_ligne'];
    }

    $queryPay = $pdo->prepare("SELECT SUM(montant) as paye FROM paiements WHERE commandes_id = ? AND statut = 0");
    $queryPay->execute([$commande_id]);
    $total_paye = $queryPay->fetch(PDO::FETCH_ASSOC)['paye'] ?? 0;
    $reste_a_payer = $total_general - $total_paye;
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

// Générer un numéro de facture si non existant
if (empty($commande['numero_facture'])) {
    $prefixe = 'NGS-' . strtoupper(substr($commande['boutique_nom'], 0, 3)) . '-';
    $numero_facture = $prefixe . date('Ymd') . '-' . str_pad($commande_id, 4, '0', STR_PAD_LEFT);
} else {
    $numero_facture = $commande['numero_facture'];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>FACTURE NGS <?= $numero_facture ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            @page {
                margin: 0;
                padding: 0;
                size: 80mm auto;
            }

            body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                font-size: 12px !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .ticket {
                width: 76mm !important;
                min-width: 76mm !important;
                max-width: 76mm !important;
                margin: 0 auto !important;
                padding: 2mm 3mm !important;
                box-shadow: none !important;
                background: white !important;
                color: #000000 !important;
            }

            /* Zone de sécurité pour la coupe */
            .cut-space {
                height: 20mm !important;
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }

            /* Forcer les couleurs en noir pur */
            .force-black,
            .force-black * {
                color: #000000 !important;
                border-color: #000000 !important;
            }

            /* Améliorer la visibilité */
            .text-dark {
                color: #000000 !important;
            }

            .border-dark {
                border-color: #000000 !important;
            }

            .bg-dark {
                background-color: #000000 !important;
            }

            /* Optimiser les marges d'impression */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Désactiver les liens pour l'impression */
            a {
                text-decoration: none;
                color: #000000 !important;
            }
        }

        /* Styles écran */
        body {
            font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .ticket {
            width: 80mm;
            background: white;
            padding: 8mm;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .ticket::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }

        .line {
            border-top: 1px solid #000000;
            margin: 10px 0;
            opacity: 0.8;
        }

        .dotted-line {
            border-top: 1px dashed #000000;
            margin: 10px 0;
            opacity: 0.6;
        }

        .qr-code-placeholder {
            width: 60px;
            height: 60px;
            background: #f5f5f5;
            border: 1px dashed #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            opacity: 0.8;
        }

        /* Classes pour le texte foncé */
        .text-super-dark {
            color: #000000 !important;
        }

        .font-heavy {
            font-weight: 900 !important;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body class="text-gray-900">

    <div class="no-print animate-fade-in mb-8 w-full max-w-md">
        <div class="bg-white rounded-xl shadow-2xl p-6 mb-4">
            <h2 class="text-xl font-bold text-center mb-4 text-gray-800">Contrôle d'Impression</h2>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-file-invoice text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Facture N°</span>
                    <p class="font-bold text-gray-900"><?= $numero_facture ?></p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Date</span>
                    <p class="font-bold text-gray-900"><?= date('d/m/Y') ?></p>
                </div>
            </div>
            <button onclick="window.print()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold py-4 rounded-lg shadow-md transition-all flex items-center justify-center gap-3 mb-2">
                <i class="fas fa-print text-xl"></i>
                <span>IMPRIMER LE TICKET</span>
            </button>

            <a href="ventes_boutique.php" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-3 rounded-lg shadow transition-all flex items-center justify-center gap-3">
                <i class="fas fa-arrow-left"></i>
                <span>RETOUR AUX VENTES</span>
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-2xl p-6">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Aperçu Rapide</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Articles:</span>
                    <span class="font-bold"><?= count($articles) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Général:</span>
                    <span class="font-bold text-green-600"><?= number_format($total_general, 2) ?> $</span>
                </div>
                <?php if ($total_paye > 0): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Montant Payé:</span>
                        <span class="font-bold text-blue-600"><?= number_format($total_paye, 2) ?> $</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-3">
                        <span class="text-gray-800 font-semibold">Reste à Payer:</span>
                        <span class="font-bold text-red-600"><?= number_format($reste_a_payer, 2) ?> $</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FACTURE À IMPRIMER -->
    <div class="ticket force-black text-super-dark">
        <!-- En-tête NGS -->
        <div class="text-center mb-4">
            <div class="flex justify-center items-center mb-2">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center">
                    <span class="text-white font-bold text-lg">NGS</span>
                </div>
                <h1 class="text-xl font-black tracking-tight">NEW GRACE SERVICE</h1>
            </div>

            <p class="text-[9px] mt-1 opacity-80"><?= htmlspecialchars($commande['boutique_nom']) ?> | RDC</p>
        </div>

        <div class="line border-dark"></div>

        <!-- Informations facture -->
        <div class="mb-2">
            <div class="flex justify-between items-start ">
                <div class="text-[10px]">
                    <p class="font-bold text-[11px]">FACTURE</p>
                    <p><span class="font-bold">N°:</span> <?= $numero_facture ?></p>
                </div>
                <div class="text-right text-[10px]">
                    <p class="font-semibold"><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                    <p class="text-[9px]">ID: <?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>

            <div class="bg-gray-100 p-2 rounded text-[10px] ">
                <p class="font-semibold">CLIENT</p>
                <p><?= htmlspecialchars($commande['client_nom'] ?: 'CLIENT CASH') ?></p>
            </div>
        </div>

        <!-- Détails des articles -->
        <table class="w-full text-[10px] mb-3">
            <thead>
                <tr class="border-b-2 border-black">
                    <th class="text-left pb-1 font-black">ARTICLE</th>
                    <th class="text-center pb-1 font-black">QTÉ</th>
                    <th class="text-right pb-1 font-black">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articles as $index => $art): ?>
                    <tr class="<?= $index % 2 === 0 ? 'bg-gray-50' : '' ?>">
                        <td class="py-1.5 pr-2">
                            <span class="font-medium"><?= htmlspecialchars($art['designation']) ?></span>
                            <?php if ($art['umProduit']): ?>
                                <div class="text-[8px] opacity-90">Unité: <?= $art['umProduit'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center py-1.5">
                            <span class="font-bold"><?= (float)$art['quantite'] ?></span>
                        </td>
                        <td class="text-right py-1.5 font-bold">
                            <?= number_format($art['total_ligne'], 2) ?> $
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totaux -->
        <div class="text-right mb-4">
            <div class="text-[12px] font-black">
                <p class="mb-1">TOTAL GÉNÉRAL: <?= number_format($total_general, 2) ?> $</p>
            </div>

            <?php if ($total_paye > 0): ?>
                <div class="text-[10px] mt-2 space-y-1">
                    <div class="flex justify-between">
                        <span>Montant payé:</span>
                        <span class="font-semibold"><?= number_format($total_paye, 2) ?> $</span>
                    </div>
                    <div class="flex justify-between border-t border-black pt-1 mt-1">
                        <span class="font-bold">Reste à payer:</span>
                        <span class="font-black"><?= number_format($reste_a_payer, 2) ?> $</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- QR Code et informations additionnelles -->
        <!-- <div class="flex justify-between items-center mt-4 mb-2">
            <div class="text-left text-[8px] w-2/3">
                <p class="font-bold mb-1">CONDITIONS DE PAIEMENT</p>
                <p>Paiement comptant • TVA non applicable</p>
                <p class="mt-1">Validité: 30 jours • Facture originale requise</p>
            </div>
            <div class="qr-code-placeholder opacity-100">
                <span class="text-[6px] font-bold">QR CODE</span>
            </div>
        </div> -->

        <!-- Pied de page -->
        <div class="text-center mt-4 pt-2 border-t border-black">
            <p class="text-[9px] font-bold mb-1">MERCI POUR VOTRE CONFIANCE !</p>
            <p class="text-[7px] opacity-80">https://newgraceservices.com | +243 977 421 421</p>
        </div>

        <!-- Zone de sécurité pour la coupe -->
        <div class="cut-space bg-white force-black">
            <div class="text-center text-[6px] opacity-40 pt-8">
                <p>--- COUPER ICI ---</p>
            </div>
        </div>
    </div>

    <script>
        // Optimisation pour l'impression
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter un événement pour masquer l'interface avant impression
            window.addEventListener('beforeprint', function() {
                document.body.classList.add('printing');
            });

            window.addEventListener('afterprint', function() {
                document.body.classList.remove('printing');

                // Afficher un message de confirmation
                const confirmation = document.createElement('div');
                confirmation.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg no-print animate-fade-in';
                confirmation.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Facture imprimée avec succès</p>
                            <p class="text-sm opacity-90">N° ${'<?= $numero_facture ?>'}</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(confirmation);

                // Supprimer après 3 secondes
                setTimeout(() => {
                    confirmation.remove();
                }, 3000);
            });
        });

        // Raccourci clavier pour l'impression
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
    </script>

</body>

</html>