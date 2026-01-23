<?php
// imprimer_recu.php
include '../connexion/connexion.php';

// Vérification de l'authentification BOUTIQUE
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'boutique') {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID de la boutique connectée
$boutique_id = $_SESSION['boutique_id'] ?? null;
if (!$boutique_id) {
    header('Location: ../login.php');
    exit;
}

// Récupérer l'ID du paiement
$paiement_id = $_GET['id'] ?? null;
if (!$paiement_id) {
    header('Location: paiements.php');
    exit;
}

try {
    // Récupérer les informations du paiement
    $query = $pdo->prepare("
        SELECT 
            p.id as paiement_id,
            p.date as date_paiement,
            p.montant,
            p.statut,
            
            c.id as commande_id,
            c.numero_facture,
            c.client_nom,
            c.date_commande,
            c.etat as commande_etat,
            
            b.nom as boutique_nom
            
        FROM paiements p
        JOIN commandes c ON p.commandes_id = c.id
        JOIN boutiques b ON c.boutique_id = b.id
        WHERE p.id = ? AND c.boutique_id = ? AND p.statut = 0
    ");
    
    $query->execute([$paiement_id, $boutique_id]);
    $paiement = $query->fetch(PDO::FETCH_ASSOC);
    
    if (!$paiement) {
        $_SESSION['flash_message'] = [
            'text' => "Paiement non trouvé ou vous n'avez pas les droits d'accès.",
            'type' => "error"
        ];
        header("Location: paiements.php");
        exit;
    }
    
    // Récupérer les détails de la commande
    $query = $pdo->prepare("
        SELECT 
            cp.quantite,
            cp.prix_unitaire,
            p.designation,
            p.umProduit,
            (cp.quantite * cp.prix_unitaire) as total_ligne
        FROM commande_produits cp
        JOIN stock s ON cp.stock_id = s.id
        JOIN produits p ON s.produit_matricule = p.matricule
        WHERE cp.commande_id = ? AND cp.statut = 0
        ORDER BY cp.id
    ");
    
    $query->execute([$paiement['commande_id']]);
    $produits = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le total de la commande
    $total_commande = 0;
    foreach ($produits as $produit) {
        $total_commande += $produit['total_ligne'];
    }
    
    // Récupérer le total déjà payé pour cette commande
    $query = $pdo->prepare("
        SELECT COALESCE(SUM(montant), 0) as total_paye
        FROM paiements 
        WHERE commandes_id = ? AND statut = 0
    ");
    $query->execute([$paiement['commande_id']]);
    $total_paye = $query->fetchColumn();
    
    // Calculer le reste à payer
    $reste_apres = $total_commande - $total_paye;
    
    // Formater les dates
    $date_paiement_formatted = date('d/m/Y H:i', strtotime($paiement['date_paiement']));
    $date_commande_formatted = date('d/m/Y H:i', strtotime($paiement['date_commande']));
    
    // Générer un numéro de reçu unique
    $numero_recu = 'RC-' . date('Ymd') . '-' . str_pad($paiement['paiement_id'], 4, '0', STR_PAD_LEFT);
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = [
        'text' => "Erreur lors de la récupération des données: " . $e->getMessage(),
        'type' => "error"
    ];
    header("Location: paiements.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>REÇU PAIEMENT NGS <?= $numero_recu ?></title>
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
            .force-black, .force-black * {
                color: #000000 !important;
                border-color: #000000 !important;
            }
            /* Améliorer la visibilité */
            .text-dark { color: #000000 !important; }
            .border-dark { border-color: #000000 !important; }
            .bg-dark { background-color: #000000 !important; }
            
            /* Optimiser les marges d'impression */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
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
            background: linear-gradient(90deg, #10B981 0%, #059669 100%); /* Vert pour paiement */
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
        
        /* Badge PAIEMENT */
        .payment-badge {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
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
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Styles spécifiques pour paiement */
        .success-note {
            background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
            border: 1px solid #10B981;
            border-radius: 8px;
            padding: 8px;
            margin: 10px 0;
            text-align: center;
        }
        
        .montant-paye {
            color: #059669;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .reste-a-payer {
            color: #DC2626;
            font-weight: bold;
        }
    </style>
</head>
<body class="text-gray-900">

    <div class="no-print animate-fade-in mb-8 w-full max-w-md">
        <div class="bg-white rounded-xl shadow-2xl p-6 mb-4">
            <h2 class="text-xl font-bold text-center mb-4 text-gray-800">REÇU DE PAIEMENT</h2>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-r from-green-400 to-green-600 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Reçu N°</span>
                    <p class="font-bold text-gray-900"><?= $numero_recu ?></p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-gradient-to-r from-blue-400 to-blue-600 rounded-full flex items-center justify-center mb-2">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Date</span>
                    <p class="font-bold text-gray-900"><?= date('d/m/Y') ?></p>
                </div>
            </div>
            <button onclick="window.print()" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white font-bold py-4 px-6 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-lg hover:shadow-xl flex items-center justify-center space-x-3">
                <i class="fas fa-print text-xl"></i>
                <span>IMPRIMER LE REÇU</span>
            </button>
            <a href="paiements.php" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-3 rounded-lg shadow transition-all flex items-center justify-center gap-3 mt-1">
                <i class="fas fa-arrow-left"></i>
                <span>RETOUR AUX PAIEMENTS</span>
            </a>
        </div>
        
        <div class="bg-white rounded-xl shadow-2xl p-6">
            <h3 class="font-bold text-lg mb-4 text-gray-800">Résumé du paiement</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">N° Commande:</span>
                    <span class="font-bold">#<?= $paiement['commande_id'] ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Client:</span>
                    <span class="font-bold"><?= htmlspecialchars($paiement['client_nom']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Commande:</span>
                    <span class="font-bold text-blue-600"><?= number_format($total_commande, 2) ?> $</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Montant Total Payé:</span>
                    <span class="font-bold text-green-600"><?= number_format($total_paye, 2) ?> $</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Montant de ce paiement:</span>
                    <span class="font-bold text-green-700"><?= number_format($paiement['montant'], 2) ?> $</span>
                </div>
                <div class="flex justify-between border-t border-gray-200 pt-3">
                    <span class="text-gray-800 font-semibold">RESTE À PAYER:</span>
                    <span class="font-bold <?= $reste_apres > 0 ? 'text-red-600' : 'text-green-600' ?> text-xl">
                        <?= number_format($reste_apres, 2) ?> $
                    </span>
                </div>
                
                <!-- État du paiement -->
                <div class="mt-4 p-3 rounded-lg <?= $reste_apres == 0 ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200' ?>">
                    <div class="flex items-center">
                        <i class="fas <?= $reste_apres == 0 ? 'fa-check-circle text-green-600' : 'fa-exclamation-triangle text-yellow-600' ?> mr-2"></i>
                        <span class="font-medium <?= $reste_apres == 0 ? 'text-green-700' : 'text-yellow-700' ?>">
                            <?php if($reste_apres == 0): ?>
                                Commande entièrement payée
                            <?php else: ?>
                                Commande partiellement payée
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REÇU À IMPRIMER -->
    <div class="ticket force-black text-super-dark">
        <!-- En-tête NGS avec badge PAIEMENT -->
        <div class="text-center mb-1">
            <!-- <div class="flex justify-center items-center mb-2">
                <div class="w-12 h-12 bg-gradient-to-r from-blue-600 to-blue-500 rounded-full flex items-center justify-center mr-3">
                    <span class="text-white font-bold text-lg">NGS</span>
                </div>
                <h1 class="text-xl font-black tracking-tight">NEW GRACE SERVICE</h1>
            </div> -->
            <p class="text-[9px] mt-1 opacity-90"><?= htmlspecialchars($paiement['boutique_nom']) ?> | RDC</p>
        </div>

        <div class="line border-dark"></div>

        <!-- Informations du reçu -->
        <div class="mb-4">
            <div class="flex justify-between items-start mb-2">
                <div class="text-[10px]">
                    <p class="font-bold text-[11px]">REÇU DE PAIEMENT</p>
                    <p><span class="font-semibold">N°:</span> <?= $numero_recu ?></p>
                </div>
                <div class="text-right text-[10px]">
                    <p class="font-semibold"><?= $date_paiement_formatted ?></p>
                    <p class="text-[9px]">ID: <?= str_pad($paiement_id, 6, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>
            
            <!-- Informations client et commande -->
            <div class="bg-gray-100 p-2 rounded text-[10px] mt-2">
                <div class="flex justify-between">
                    <div>
                        <p class="font-semibold">CLIENT</p>
                        <p><?= htmlspecialchars($paiement['client_nom']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold">COMMANDE</p>
                        <p>#<?= $paiement['commande_id'] ?></p>
                        <p class="text-[9px]"><?= $date_commande_formatted ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Détails du paiement -->
        <div class="mb-4">
            <table class="w-full text-[10px] mb-3">
                <thead>
                    <tr class="border-b-2 border-black">
                        <th class="text-left pb-1 font-black">DÉTAIL</th>
                        <th class="text-right pb-1 font-black">MONTANT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-gray-50">
                        <td class="py-1.5 pr-2">
                            <span class="font-medium">MONTANT PAYÉ</span>
                        </td>
                        <td class="text-right py-1.5 font-bold montant-paye">
                            <?= number_format($paiement['montant'], 2) ?> $
                        </td>
                    </tr>
                    <tr>
                        <td class="py-1.5 pr-2">
                            <span class="font-medium">TOTAL COMMANDE</span>
                        </td>
                        <td class="text-right py-1.5 font-bold">
                            <?= number_format($total_commande, 2) ?> $
                        </td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td class="py-1.5 pr-2">
                            <span class="font-medium">TOTAL PAYÉ</span>
                            <div class="text-[8px] opacity-90">(incl. ce paiement)</div>
                        </td>
                        <td class="text-right py-1.5 font-bold text-green-700">
                            <?= number_format($total_paye, 2) ?> $
                        </td>
                    </tr>
                    <tr>
                        <td class="py-1.5 pr-2">
                            <span class="font-medium">RESTE À PAYER</span>
                        </td>
                        <td class="text-right py-1.5 font-bold reste-a-payer">
                            <?= number_format($reste_apres, 2) ?> $
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php if($reste_apres > 0): ?>
            <div class="text-center text-[9px] mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-1"></i>
                <span class="font-semibold">SOLDE IMPAYÉ: <?= number_format($reste_apres, 2) ?> $</span>
            </div>
            <?php else: ?>
            <div class="text-center text-[9px] mt-3 p-2 bg-green-50 border border-green-200 rounded">
                <i class="fas fa-check-circle text-green-600 mr-1"></i>
                <span class="font-semibold">COMMANDE ENTIÈREMENT PAYÉE</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pied de page -->
        <div class="text-center mt-4 pt-2">
            <p class="text-[9px] font-bold mb-1">MERCI POUR VOTRE CONFIANCE</p>
            <p class="text-[7px] opacity-80">https://newgraceservices.com  | +243 977 421 421</p>
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
                confirmation.className = 'fixed top-4 right-4 bg-gradient-to-r from-green-500 to-green-600 text-white px-4 py-3 rounded-lg shadow-lg no-print animate-fade-in';
                confirmation.innerHTML = `
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div>
                            <p class="font-bold">Reçu imprimé</p>
                            <p class="text-sm opacity-90">N° ${'<?= $numero_recu ?>'}</p>
                            <p class="text-xs opacity-80 mt-1">Montant: ${'<?= number_format($paiement["montant"], 2) ?>'} $</p>
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
                            <p class="text-sm">Fermeture automatique après impression</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(popupInfo);
                
                // Fermer après impression
                window.addEventListener('afterprint', function() {
                    setTimeout(() => {
                        popupInfo.remove();
                        window.close();
                    }, 1000);
                });
            }
        });
    </script>

</body>
</html>