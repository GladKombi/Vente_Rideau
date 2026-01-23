<?php
// facture-credit.php
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

    // Récupérer les paiements
    $queryPay = $pdo->prepare("SELECT SUM(montant) as paye FROM paiements WHERE commandes_id = ? AND statut = 0");
    $queryPay->execute([$commande_id]);
    $total_paye = $queryPay->fetch(PDO::FETCH_ASSOC)['paye'] ?? 0;
    $reste_a_payer = $total_general - $total_paye;

    // Récupérer les détails des paiements
    $queryPayDetails = $pdo->prepare("SELECT * FROM paiements WHERE commandes_id = ? AND statut = 0 ORDER BY date DESC");
    $queryPayDetails->execute([$commande_id]);
    $paiements_details = $queryPayDetails->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}

// Générer un numéro de facture si non existant
if (empty($commande['numero_facture'])) {
    $prefixe = 'NGS-CREDIT-' . strtoupper(substr($commande['boutique_nom'], 0, 3)) . '-';
    $numero_facture = $prefixe . date('Ymd') . '-' . str_pad($commande_id, 4, '0', STR_PAD_LEFT);
} else {
    $numero_facture = $commande['numero_facture'];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>FACTURE CRÉDIT NGS <?= $numero_facture ?></title>
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
            background: linear-gradient(90deg, #8B5CF6 0%, #EC4899 100%);
            /* Violet-rose pour crédit */
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

        /* Badge CRÉDIT */
        .credit-badge {
            background: linear-gradient(135deg, #8B5CF6 0%, #EC4899 100%);
            color: white;
            font-weight: bold;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            display: inline-block;
            margin-left: 8px;
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

        /* Styles spécifiques pour crédit */
        .warning-note {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border: 1px solid #F59E0B;
            border-radius: 8px;
            padding: 8px;
            margin: 10px 0;
            text-align: center;
        }

        .montant-restant {
            color: #DC2626;
            font-weight: bold;
            font-size: 1.1em;
        }
    </style>
</head>

<body class="text-gray-900">

    <div class="no-print animate-fade-in mb-8 w-full max-w-md">
        <div class="bg-white rounded-xl shadow-2xl p-6 mb-4">
            <h2 class="text-xl font-bold text-center mb-4 text-gray-800">FACTURE CRÉDIT</h2>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-r from-purple-400 to-pink-600 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Facture N°</span>
                    <p class="font-bold text-gray-900"><?= $numero_facture ?></p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-r from-yellow-400 to-orange-600 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Date</span>
                    <p class="font-bold text-gray-900"><?= date('d/m/Y') ?></p>
                </div>
            </div>
            <button onclick="window.print()" class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white font-bold py-4 px-6 rounded-lg hover:from-purple-600 hover:to-pink-700 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center space-x-3">
                <i class="fas fa-print text-xl"></i>
                <span>IMPRIMER LA FACTURE CRÉDIT</span>
            </button>
            <a href="ventes_boutique.php" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-3 rounded-lg shadow transition-all flex items-center justify-center gap-3 mt-1">
                <i class="fas fa-arrow-left"></i>
                <span>RETOUR AUX VENTES</span>
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-2xl p-6">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Résumé de la facture crédit</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Articles:</span>
                    <span class="font-bold"><?= count($articles) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Général:</span>
                    <span class="font-bold text-blue-600"><?= number_format($total_general, 2) ?> $</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Montant Payé:</span>
                    <span class="font-bold text-green-600"><?= number_format($total_paye, 2) ?> $</span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-3">
                    <span class="text-gray-800 font-semibold">RESTE À PAYER:</span>
                    <span class="font-bold text-red-600 text-xl"><?= number_format($reste_a_payer, 2) ?> $</span>
                </div>

                <!-- État du paiement -->
                <div class="mt-4 p-3 rounded-lg <?= $reste_a_payer == 0 ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' ?>">
                    <div class="flex items-center">
                        <i class="fas <?= $reste_a_payer == 0 ? 'fa-check-circle text-green-600' : 'fa-exclamation-triangle text-yellow-600' ?> mr-2"></i>
                        <span class="font-medium <?= $reste_a_payer == 0 ? 'text-green-700' : 'text-yellow-700' ?>">
                            <?php if ($reste_a_payer == 0): ?>
                                Facture entièrement payée
                            <?php elseif ($total_paye == 0): ?>
                                Facture non payée (CRÉDIT)
                            <?php else: ?>
                                Facture partiellement payée (CRÉDIT)
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FACTURE CRÉDIT À IMPRIMER -->
    <div class="ticket force-black text-super-dark">
        <!-- En-tête NGS avec badge CRÉDIT -->
        <div class="text-center mb-1">
            <!-- <div class="flex justify-center items-center mb-2">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-600 to-blue-500 rounded-full flex items-center justify-center mr-3">
                    <span class="text-white font-bold text-lg">NGS</span>
                </div>
                <h1 class="text-xl font-black tracking-tight">NEW GRACE SERVICE</h1>
            </div> -->
            <p class="text-[9px] mt-1 opacity-90"><?= htmlspecialchars($commande['boutique_nom']) ?> | RDC</p>
            <p class="text-[9px] mt-1 opacity-80">RCCM : 20-A557</p>
            <p class="text-[9px] mt-1 opacity-80">+243 977 421 421</p>
        </div>

        <div class="line border-dark"></div>

        <!-- Informations facture CRÉDIT -->
        <div class="mb-4">
            <div class="flex justify-between items-start mb-2">
                <div class="text-[10px]">
                    <p class="font-bold text-[11px]">FACTURE CRÉDIT</p>
                    <p><span class="font-semibold">N°:</span> <?= $numero_facture ?></p>
                </div>
                <div class="text-right text-[10px]">
                    <p class="font-semibold"><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
                    <p class="text-[9px]">ID: <?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>

            <div class="bg-gray-100 p-2 rounded text-[10px] mt-2">
                <p class="font-semibold">CLIENT</p>
                <p><?= htmlspecialchars($commande['client_nom'] ?: 'CLIENT CRÉDIT') ?></p>
                <?php if ($commande['boutique_email']): ?>
                    <p class="text-[9px] opacity-80"><?= htmlspecialchars($commande['boutique_email']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Note importante pour crédit -->
        <div class="warning-note text-[9px] mb-3">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            <span class="font-semibold">FACTURE CRÉDIT</span>
        </div>

        <!-- Détails des articles -->
        <table class="w-full text-[10px] mb-1">
            <thead>
                <tr class="border-b-2 border-black">
                    <th class="text-left pb-1 font-black">ARTICLE</th>
                    <th class="text-center pb-1 font-black">P.U. /$</th>
                    <th class="text-center pb-1 font-black">QTÉ</th>
                    <th class="text-right pb-1 font-black">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($articles as $index => $art):
                    $uniteText = $art['umProduit'] == 'metres' ? 'm' : 'pce';
                ?>
                    <tr class="<?= $index % 2 === 0 ? 'bg-gray-50' : '' ?>">
                        <td class="py-1.5 pr-1">
                            <span class="font-medium"><?= htmlspecialchars($art['designation']) ?></span>
                            <div class="text-[7px] opacity-90"><?= $uniteText ?></div>
                        </td>
                        <td class="text-center py-1.5">
                            <?= number_format($art['prix_unitaire'], 2) ?>
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


        <!-- Totaux et informations de paiement -->
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
                    <div class="flex justify-between">
                        <span>Montant dû:</span>
                        <span class="font-bold montant-restant"><?= number_format($reste_a_payer, 2) ?> $</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-[10px] mt-2">
                    <div class="flex justify-between">
                        <span>Montant dû:</span>
                        <span class="font-bold montant-restant"><?= number_format($reste_a_payer, 2) ?> $</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Détails des paiements si existants -->
            <?php if (!empty($paiements_details)): ?>
                <div class="mt-3 pt-2 border-t border-black text-[8px]">
                    <p class="font-semibold mb-1">PAIEMENTS EFFECTUÉS:</p>
                    <?php foreach ($paiements_details as $paiement): ?>
                        <div class="flex justify-between">
                            <span><?= date('d/m/Y', strtotime($paiement['date'])) ?>:</span>
                            <span><?= number_format($paiement['montant'], 2) ?> $</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>


        <!-- Informations de contact pour crédit -->
        <div class="bg-gray-100 p-2 rounded text-[8px] mt-1">
            <p class="font-bold mb-1">INFORMATIONS PAIEMENT</p>
            <p>Pour régler cette facture:</p>
            <p>• Tél: +243 977 421 421</p>
        </div>

        <!-- Pied de page -->
        <div class="text-center mt-1 pt-2 ">
            <p class="text-[9px] font-bold mb-1">MERCI POUR VOTRE CONFIANCE</p>
            <p class="text-[7px] opacity-80">https://newgraceservices.com | +243 977 421 421</p>
        </div>

        <!-- Zone de sécurité pour la coupe -->
        <div class="cut-space bg-white force-black">
            <div class="text-center text-[6px] opacity-40 pt-4">
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
                confirmation.className = 'fixed top-4 right-4 bg-gradient-to-r from-purple-500 to-pink-600 text-white px-4 py-3 rounded-lg shadow-lg no-print animate-fade-in';
                confirmation.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Facture crédit imprimée</p>
                            <p class="text-sm opacity-90">N° ${'<?= $numero_facture ?>'}</p>
                            <p class="text-xs opacity-80 mt-1">Reste à payer: ${'<?= number_format($reste_a_payer, 2) ?>'} $</p>
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

        // Fermer la fenêtre après impression si venant d'un popup
        window.addEventListener('load', function() {
            // Vérifier si nous sommes dans un popup
            if (window.opener) {
                // Afficher un message
                const popupInfo = document.createElement('div');
                popupInfo.className = 'fixed bottom-4 right-4 bg-blue-500 text-white px-4 py-3 rounded-lg shadow-lg no-print animate-fade-in';
                popupInfo.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-info-circle text-xl"></i>
                        <div>
                            <p class="text-sm">Fermeture automatique dans 10 secondes après impression</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(popupInfo);

                // Fermer après 10 secondes si pas d'impression
                setTimeout(() => {
                    if (!document.body.classList.contains('printing')) {
                        popupInfo.remove();
                        setTimeout(() => {
                            window.close();
                        }, 1000);
                    }
                }, 10000);

                // Fermer après impression
                window.addEventListener('afterprint', function() {
                    setTimeout(() => {
                        popupInfo.remove();
                        window.close();
                    }, 2000);
                });
            }
        });
    </script>

</body>

</html>